<?php

namespace Agentoom\Core\Jobs;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Contracts\ComplianceIntegration;
use Agentoom\Core\Exceptions\ActionPausedException;
use Agentoom\Core\Models\AiTask;
use Agentoom\Core\Services\AiTaskService;
use Agentoom\Core\Services\InjectCurrentAiConfigService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAiTaskJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 3;

    public int $taskId;

    public function __construct(int $taskId)
    {
        $this->taskId = $taskId;
    }

    public function handle(AiTaskService $taskService): void
    {
        $task = AiTask::find($this->taskId);

        if (! $task || ! $task->is_active) {
            return;
        }

        if ($task->project_id && $task->project?->system_user_id) {
            Auth::onceUsingId($task->project->system_user_id);
        } elseif ($task->user_id) {
            Auth::onceUsingId($task->user_id);
        }

        if ($task->project_id) {
            app()->instance('current_project_id', $task->project_id);
            app()->instance('current_agent_id', $task->agent_id);
        }

        $isResume = $task->status === 'paused';
        $task->update(['status' => 'running', 'last_run_at' => now()]);

        // Emergency stop check — blocks execution if a stop is active
        app(ComplianceIntegration::class)->assertNotStopped('task', $task->id);
        if ($task->agent_id) {
            app(ComplianceIntegration::class)->assertNotStopped('agent', $task->agent_id);
        }

        try {
            $agentModel = $task->agent;
            $agentModel->load('skills', 'tools', 'knowledgeFiles');
            $dynamicAgent = new DynamicAgent($agentModel, isHeadless: true, aiTaskId: $task->id, user: $task->user);
            $providerConfig = InjectCurrentAiConfigService::inject($agentModel);

            $initialPrompt = "Execute the following task:\n\n".
                'Name: '.$task->name."\n".
                'Description: '.$task->description."\n";

            if (! empty($task->parameters)) {
                $initialPrompt .= 'Parameters: '.json_encode($task->parameters)."\n";
            }

            if ($isResume) {
                $prompt = "Security authorization successful. Please proceed with your previous action. For context, here is the task you were performing:\n\n".$initialPrompt;
            } else {
                $prompt = $initialPrompt;
            }

            $response = $dynamicAgent->prompt(
                $prompt,
                provider: $providerConfig['provider_name'],
                model: $providerConfig['model'],
            );

            $results = [
                'text' => $response->text,
                'usage' => $response->usage?->toArray(),
                'meta' => $response->meta?->toArray(),
            ];

            $taskService->addLog($task->id, "Task execution response:\n\n".$response->text, $results, $agentModel->id);

            app(ComplianceIntegration::class)->dispatchTaskEvent(
                eventType: 'task.completed',
                taskId: $task->id,
                agentId: $agentModel->id,
                input: $prompt,
                output: $response->text,
                userId: $task->user_id,
            );

            if ($task->period !== 'once') {
                $task->update([
                    'next_run_at' => $taskService->calculateNextRunAt($task->period, $task->cron),
                    'status' => 'pending',
                ]);
            } else {
                $task->update(['status' => 'completed', 'next_run_at' => null]);
            }

        } catch (ActionPausedException $e) {
            $taskService->addLog($task->id, 'Task paused awaiting security approval: '.$e->getMessage(), null, $task->agent_id);
            $task->update(['status' => 'paused']);

            app(ComplianceIntegration::class)->dispatchTaskEvent(
                eventType: 'task.paused',
                taskId: $task->id,
                agentId: $task->agent_id,
                output: $e->getMessage(),
                userId: $task->user_id,
            );
            // Don't rethrow, as it's a known state
        } catch (Throwable $e) {
            Log::error("ProcessAiTaskJob failed for task #{$task->id}: ".$e->getMessage());
            $taskService->addLog($task->id, 'Task failed: '.$e->getMessage(), null, $task->agent_id);
            $task->update(['status' => 'failed']);

            app(ComplianceIntegration::class)->dispatchTaskEvent(
                eventType: 'task.failed',
                taskId: $task->id,
                agentId: $task->agent_id,
                output: $e->getMessage(),
                metadata: ['error' => class_basename($e)],
                userId: $task->user_id,
            );

            throw $e;
        }
    }
}
