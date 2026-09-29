<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Attributes\Actionable;
use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Exceptions\ActionPausedException;
use Agentoom\Core\Execution\ActionExecution;
use Agentoom\Core\Models\PipelineRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

#[GroupVisibility('agentoom-core')]
class WaitService
{
    /**
     * Pause the execution for a specified amount of time.
     */
    #[Actionable(name: 'Wait', description: 'Pause the current automation execution for a specified duration (e.g., "30 seconds", "5 minutes", "1 hour").', category: 'System')]
    public function wait(string $duration): string
    {
        $resumeAt = $this->parseDuration($duration);

        // Find current execution context
        $pipelineRunId = null;

        if (app()->bound(ActionExecution::class)) {
            $pipelineRunId = app(ActionExecution::class)->pipelineRunId;
        } elseif (app()->bound('current_pipeline_run_id')) {
            $pipelineRunId = app('current_pipeline_run_id');
        }

        if ($pipelineRunId) {
            $run = PipelineRun::find($pipelineRunId);
            if ($run) {
                Log::info("Pausing pipeline run #{$run->id} until {$resumeAt->toDateTimeString()} (Duration: {$duration}).");

                $run->update([
                    'status' => 'paused',
                    'paused_at' => now(),
                    'resume_at' => $resumeAt,
                ]);

                throw new ActionPausedException("Execution paused until {$resumeAt->toDateTimeString()}.");
            }
        }

        // Handle other contexts if needed (AiTask, ScheduleLog, etc.)
        // For now we focus on PipelineRun as reported by the user.

        Log::info("Wait tool called outside of pipeline context. Duration: {$duration}.");

        // If not in a pipeline, we might just sleep, but that's not ideal for long waits.
        // For now, let's just return a message if we can't pause.
        return "Waiting for {$duration} (until {$resumeAt->toDateTimeString()})...";
    }

    /**
     * Parse duration string into a Carbon instance.
     */
    protected function parseDuration(string $duration): Carbon
    {
        try {
            return now()->add($duration);
        } catch (\Exception $e) {
            // Fallback to a simple parser if Carbon's add() fails for some reason
            if (preg_match('/^(\d+)\s*(second|minute|hour|day)s?$/i', $duration, $matches)) {
                $value = (int) $matches[1];
                $unit = strtolower($matches[2]);

                return match ($unit) {
                    'second' => now()->addSeconds($value),
                    'minute' => now()->addMinutes($value),
                    'hour' => now()->addHours($value),
                    'day' => now()->addDays($value),
                    default => now()->addMinutes($value),
                };
            }

            // Default to 1 minute if parsing fails
            return now()->addMinute();
        }
    }
}
