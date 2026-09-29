<?php

namespace Agentoom\Core\Jobs;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Exceptions\ActionPausedException;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Models\ScheduleLog;
use Agentoom\Core\Services\InjectCurrentAiConfigService;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Traits\ParsesParameters;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunScheduleJob implements ShouldQueue
{
    use ParsesParameters, Queueable;

    public int $timeout = 3600;

    /** @var int Maximum number of attempts before the job is marked as failed */
    public int $tries = 5;

    public int $scheduleId;

    public ?int $logId;

    public function __construct(int $scheduleId, ?int $logId = null)
    {
        $this->scheduleId = $scheduleId;
        $this->logId = $logId;
    }

    /**
     * Seconds to wait before retrying: 30s → 60s → 120s
     *
     * @return int[]
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(PipelineRunnerService $runner): void
    {
        $schedule = Schedule::find($this->scheduleId);

        if (! $schedule || ! $schedule->active) {
            Log::warning("RunScheduleJob: schedule {$this->scheduleId} not found or inactive.");

            return;
        }

        if ($schedule->project_id && $schedule->project?->system_user_id) {
            Log::info("RunScheduleJob: running schedule #{$schedule->id} \"{$schedule->name}\" for project {$schedule->project_id} as project user {$schedule->project->system_user_id}");
            Auth::onceUsingId($schedule->project->system_user_id);
        } elseif ($schedule->user_id) {
            Log::info("RunScheduleJob: running schedule #{$schedule->id} \"{$schedule->name}\" as user {$schedule->user_id}");
            Auth::onceUsingId($schedule->user_id);
        }

        if ($schedule->project_id) {
            app()->instance('current_project_id', $schedule->project_id);
        }

        $log = $this->logId ? ScheduleLog::find($this->logId) : null;
        $isResume = $log && $log->status === 'paused';

        if (! $log) {
            $log = ScheduleLog::create([
                'schedule_id' => $schedule->id,
                'status' => 'running', // updated to 'success' on completion
                'started_at' => now(),
            ]);
        } else {
            $log->update([
                'status' => 'running',
                'started_at' => now(),
            ]);
        }

        try {
            [$output, $pipelineRunId] = $this->executeTarget($schedule, $runner, $log, $isResume);

            $log->update([
                'status' => 'success',
                'log' => $output,
                'pipeline_run_id' => $pipelineRunId,
                'completed_at' => now(),
            ]);
        } catch (ActionPausedException $e) {
            $log->update([
                'status' => 'paused',
                'log' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error("RunScheduleJob: schedule {$schedule->id} failed.", [
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'log' => "[Attempt {$this->attempts()}] {$e->getMessage()}",
                'completed_at' => now(),
            ]);

            throw $e; // re-throw so Laravel can retry
        } finally {
            $schedule->update(['last_execution' => now()]);
        }
    }

    /**
     * Called by Laravel after all retry attempts are exhausted.
     * Auto-disables the schedule if consecutive failures reach the threshold.
     */
    public function failed(Throwable $e): void
    {
        $schedule = Schedule::find($this->scheduleId);

        if (! $schedule) {
            return;
        }

        $consecutiveFailures = $schedule->consecutiveFailuresCount();
        $threshold = 5;

        if ($consecutiveFailures >= $threshold) {
            $schedule->update(['active' => false]);

            Log::warning("RunScheduleJob: schedule #{$schedule->id} \"{$schedule->name}\" auto-disabled after {$consecutiveFailures} consecutive failures.");
        }
    }

    /**
     * Resolve and run either an Agent or a Pipeline.
     *
     * Returns a tuple: [output string, pipeline_run_id or null]
     *
     * @return array{string, int|null}
     *
     * @throws Throwable
     */
    private function executeTarget(Schedule $schedule, PipelineRunnerService $runner, ScheduleLog $log, bool $isResume = false): array
    {
        if ($schedule->method) {
            $class = $schedule->model;
            $method = $schedule->method;
            $instance = ($schedule->model_id && is_subclass_of($class, Model::class))
                ? app($class)->find($schedule->model_id)
                : app($class);

            $params = json_decode($schedule->instructions ?? '[]', true);
            if (is_array($params)) {
                // If the instructions are in the JsonStructureHelper format, convert them to a simple key-value array
                if (! empty($params) && isset($params[0]) && is_array($params[0]) && isset($params[0]['name'])) {
                    $formattedParams = [];
                    foreach ($params as $param) {
                        if (isset($param['name'])) {
                            $value = $param['value'] ?? null;
                            $type = $param['type'] ?? 'string';

                            if ($value !== null) {
                                $value = $this->parseParameterValue($value, $type, $param['name']);
                            }

                            $formattedParams[$param['name']] = $value;
                        }
                    }
                    $params = $formattedParams;
                }

                $params = $this->resolveDynamicParameters($params);
            }
            $result = $instance->$method(...$params);

            return [is_string($result) ? $result : json_encode($result, JSON_PRETTY_PRINT), null];
        }

        $target = $schedule->resolveModel();
        $instructions = $schedule->instructions ?? '';

        // Also attempt to resolve dynamic dates in the raw instructions string
        // if it looks like it might contain a relative time (e.g. "now - 5 hours")
        if (is_string($instructions) && $this->isLikelyRelativeTime($instructions)) {
            $instructions = $this->resolveStringDifferentials($instructions);
        }

        if ($target instanceof Pipeline) {
            $run = $runner->run($target, $instructions, scheduleId: $schedule->id, scheduleLogId: $log->id);

            return [$run->output ?? '', $run->id];
        }

        if ($target instanceof Agent) {
            $target->load('skills', 'tools', 'knowledgeFiles');

            app()->instance('current_agent_id', $target->id);
            $agent = new DynamicAgent($target, scheduleId: $schedule->id, isHeadless: true, scheduleLogId: $log->id, user: $schedule->user);
            $providerConfig = InjectCurrentAiConfigService::inject($target);

            $initialPrompt = $instructions;

            if ($isResume) {
                $prompt = "Security authorization successful. Please proceed with your previous action. For context, here is the task you were performing:\n\n".$initialPrompt;
            } else {
                $prompt = $initialPrompt;
            }

            $response = $agent->prompt(
                $prompt,
                provider: $providerConfig['provider_name'],
                model: $providerConfig['model'],
            );

            return [$response->text, null];
        }

        throw new \RuntimeException("Unsupported schedulable model: {$schedule->model}");
    }

    /**
     * Recursively resolve relative date strings in an array of parameters.
     */
    private function resolveDynamicParameters(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $params[$key] = $this->resolveDynamicParameters($value);
            } elseif (is_string($value)) {
                $params[$key] = $this->resolveStringDifferentials($value);
            }
        }

        return $params;
    }

    /**
     * Resolve relative time differentials within a string, handling ranges like [now - 5h, now]
     * and single values like "yesterday".
     */
    private function resolveStringDifferentials(string $input): string
    {
        // Handle PrestaShop style ranges: [5 hours before now, 1 hour before now]
        if (str_contains($input, '[') && str_contains($input, ']')) {
            return preg_replace_callback('/\[([^\]]+)\]/', function ($matches) {
                $parts = explode(',', $matches[1]);
                $resolvedParts = [];
                foreach ($parts as $part) {
                    $part = trim($part);
                    $normalized = str_replace(' before now', ' ago', $part);
                    if ($this->isLikelyRelativeTime($normalized)) {
                        try {
                            $resolvedParts[] = Carbon::parse($normalized)->toDateTimeString();
                        } catch (Throwable $e) {
                            $resolvedParts[] = $part;
                        }
                    } else {
                        $resolvedParts[] = $part;
                    }
                }

                return '['.implode(',', $resolvedParts).']';
            }, $input);
        }

        // Handle single values
        $normalized = str_replace(' before now', ' ago', $input);
        if ($this->isLikelyRelativeTime($normalized)) {
            try {
                // For single values, we prefer ISO8601 as it's more standard across APIs
                return Carbon::parse($normalized)->toIso8601String();
            } catch (Throwable $e) {
                return $input;
            }
        }

        return $input;
    }

    /**
     * Check if a string contains keywords that suggest a relative time.
     */
    private function isLikelyRelativeTime(string $value): bool
    {
        $keywords = [
            ' ago', 'last ', 'yesterday', 'tomorrow', 'now', 'next ',
            ' days', ' hours', ' minutes', ' months', ' years', ' weeks',
            ' day', ' hour', ' minute', ' month', ' year', ' week',
            '+', '-',
        ];

        $lowerValue = strtolower($value);
        foreach ($keywords as $keyword) {
            if (str_contains($lowerValue, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
