<?php

namespace Agentoom\Core\Jobs;

use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Services\PipelineRunnerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(public int $pipelineRunId)
    {
        $this->queue = 'pipeline';
        Log::info('RunPipelineJob instantiated for run #'.$this->pipelineRunId);
    }

    public function handle(PipelineRunnerService $pipelineRunner): void
    {
        Log::info('RunPipelineJob started for run #'.$this->pipelineRunId);
        $run = PipelineRun::find($this->pipelineRunId);

        if (! $run) {
            Log::warning('RunPipelineJob: PipelineRun #'.$this->pipelineRunId.' not found.');

            return;
        }

        if (in_array($run->status, ['completed', 'paused'])) {
            Log::info('RunPipelineJob: PipelineRun #'.$this->pipelineRunId.' already in status '.$run->status.'. Skipping.');

            return;
        }

        try {
            $pipelineRunner->executeRun($run, $run->input, background: true);
            Log::info('RunPipelineJob finished successfully for run #'.$this->pipelineRunId);
        } catch (Throwable $e) {
            Log::error("RunPipelineJob failed for run #{$this->pipelineRunId}: ".$e->getMessage());
            // Fail is handled inside executeRun for most cases, but if not:
            $run = $run->fresh();
            if ($run && ($run->status === 'running' || $run->status === 'pending')) {
                $run->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'completed_at' => now(),
                ]);
            }
            throw $e;
        }
    }
}
