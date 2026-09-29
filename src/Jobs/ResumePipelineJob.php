<?php

namespace Agentoom\Core\Jobs;

use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Services\PipelineRunnerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResumePipelineJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct(public int $pipelineRunId) {}

    public function handle(PipelineRunnerService $pipelineRunner): void
    {
        $run = PipelineRun::find($this->pipelineRunId);

        if (! $run || ! in_array($run->status, ['paused', 'failed'])) {
            return;
        }

        try {
            $pipelineRunner->executeRun($run, $run->input, background: true);
        } catch (Throwable $e) {
            Log::error("ResumePipelineJob failed for run #{$this->pipelineRunId}: ".$e->getMessage());
            throw $e;
        }
    }
}
