<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Models\PipelineRun;

class PipelineExecutionService
{
    protected ?PipelineRun $currentRun = null;

    protected bool $stopRequested = false;

    protected string $stopReason = '';

    protected string $stopStatus = 'stopped';

    protected ?int $jumpToOrder = null;

    protected string $jumpReason = '';

    protected ?array $jumpParams = null;

    protected int|string|null $nextPipelineIdOrName = null;

    protected ?array $nextPipelineInput = null;

    protected string $runPipelineReason = '';

    /**
     * Set the current pipeline run.
     */
    public function setCurrentRun(PipelineRun $run): void
    {
        $this->currentRun = $run;
    }

    /**
     * Get the current pipeline run.
     */
    public function getCurrentRun(): ?PipelineRun
    {
        return $this->currentRun;
    }

    /**
     * Jump to a different step in the current pipeline execution.
     *
     * @param  int  $stepOrder  The order of the step to jump to.
     * @param  string  $reason  The reason for jumping.
     * @param  array|null  $params  Optional parameters to pass to the target step.
     */
    public function jumpToStep(int $stepOrder, string $reason = '', ?array $params = null): void
    {
        $this->jumpToOrder = $stepOrder;
        $this->jumpReason = $reason;
        $this->jumpParams = $params;
    }

    /**
     * Stop the current pipeline execution.
     *
     * @param  string  $reason  The reason for stopping.
     * @param  string  $status  The status to set for the pipeline run.
     */
    public function stopPipeline(string $reason = '', string $status = 'stopped'): void
    {
        $this->stopRequested = true;
        $this->stopReason = $reason;
        $this->stopStatus = $status;
    }

    public function hasStopped(): bool
    {
        return $this->stopRequested;
    }

    public function getStopReason(): string
    {
        return $this->stopReason;
    }

    public function getStopStatus(): string
    {
        return $this->stopStatus;
    }

    public function hasJumped(): bool
    {
        return $this->jumpToOrder !== null;
    }

    public function getJumpOrder(): ?int
    {
        return $this->jumpToOrder;
    }

    public function getJumpReason(): string
    {
        return $this->jumpReason;
    }

    public function getJumpParams(): ?array
    {
        return $this->jumpParams;
    }

    /**
     * Trigger a new pipeline run.
     *
     * @param  int|string  $pipelineIdOrName  The ID or name of the pipeline to run.
     * @param  array|null  $input  Initial data to pass to the new pipeline.
     * @param  string  $reason  The reason for running the new pipeline.
     */
    public function runPipeline(int|string $pipelineIdOrName, ?array $input = null, string $reason = ''): void
    {
        $this->nextPipelineIdOrName = $pipelineIdOrName;
        $this->nextPipelineInput = $input;
        $this->runPipelineReason = $reason;
    }

    public function hasNextPipeline(): bool
    {
        return $this->nextPipelineIdOrName !== null;
    }

    public function getNextPipelineIdOrName(): int|string|null
    {
        return $this->nextPipelineIdOrName;
    }

    public function getNextPipelineInput(): ?array
    {
        return $this->nextPipelineInput;
    }

    public function getRunPipelineReason(): string
    {
        return $this->runPipelineReason;
    }

    public function clear(): void
    {
        $this->currentRun = null;
        $this->stopRequested = false;
        $this->stopReason = '';
        $this->stopStatus = 'stopped';
        $this->jumpToOrder = null;
        $this->jumpReason = '';
        $this->jumpParams = null;
        $this->nextPipelineIdOrName = null;
        $this->nextPipelineInput = null;
        $this->runPipelineReason = '';
    }
}
