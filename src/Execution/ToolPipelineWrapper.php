<?php

namespace Agentoom\Core\Execution;

use Agentoom\Core\Contracts\RunsInBackgroundContext;
use Agentoom\Core\Contracts\RunsInScheduleContext;
use Agentoom\Core\Exceptions\ActionPausedException;
use Agentoom\Core\Exceptions\StopPipelineException;
use Agentoom\Core\Models\AiTask;
use Agentoom\Core\Models\EndpointLog;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\ScheduleLog;
use Agentoom\Core\Services\PipelineExecutionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ToolPipelineWrapper implements Tool
{
    /**
     * Create a new tool pipeline wrapper instance.
     */
    public function __construct(
        protected Agent $agent,
        protected Tool $tool,
        protected ActionExecutor $executor
    ) {}

    /**
     * Get the tool's name, delegating to the wrapped tool if available.
     */
    public function name(): string
    {
        return method_exists($this->tool, 'name')
            ? $this->tool->name()
            : class_basename($this->tool);
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return $this->tool->description();
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        $fields = $this->tool->schema($schema);

        if (! isset($fields['explanation'])) {
            $fields['explanation'] = $schema->string()
                ->required()
                ->description('A brief explanation of why you are calling this tool, which will be shown to the user/approver if security authorization is required.');
        }

        return $fields;
    }

    /**
     * Execute the tool through the pipeline.
     */
    public function handle(Request $request): Stringable|string
    {
        if ($this->isPaused()) {
            throw new ActionPausedException('Execution paused awaiting authorization.');
        }

        $executionService = app(PipelineExecutionService::class);
        if ($executionService->hasStopped()) {
            throw new StopPipelineException($executionService->getStopReason(), $executionService->getStopStatus());
        }

        $scheduleId = ($this->agent instanceof RunsInScheduleContext)
            ? $this->agent->getScheduleId()
            : null;

        $scheduleLogId = ($this->agent instanceof RunsInScheduleContext)
            ? $this->agent->getScheduleLogId()
            : null;

        $pipelineRunId = ($this->agent instanceof RunsInBackgroundContext)
            ? $this->agent->getPipelineRunId()
            : null;

        $aiTaskId = ($this->agent instanceof RunsInBackgroundContext)
            ? $this->agent->getAiTaskId()
            : null;

        $endpointId = ($this->agent instanceof RunsInBackgroundContext)
            ? $this->agent->getEndpointId()
            : null;

        $pipelineStepOrder = ($this->agent instanceof RunsInBackgroundContext)
            ? $this->agent->getPipelineStepOrder()
            : null;

        $endpointLogId = ($this->agent instanceof RunsInBackgroundContext)
            ? $this->agent->getEndpointLogId()
            : null;

        return $this->executor->execute($this->agent, $this->tool, $request, $scheduleId, $pipelineRunId, $aiTaskId, $endpointId, $scheduleLogId, $pipelineStepOrder, $endpointLogId);
    }

    /**
     * Check if the current execution context is already paused.
     */
    protected function isPaused(): bool
    {
        if ($this->agent instanceof RunsInScheduleContext) {
            $scheduleLogId = $this->agent->getScheduleLogId();
            if ($scheduleLogId && ScheduleLog::where('id', $scheduleLogId)->where('status', 'paused')->exists()) {
                return true;
            }
        }

        if ($this->agent instanceof RunsInBackgroundContext) {
            $pipelineRunId = $this->agent->getPipelineRunId();
            if ($pipelineRunId && PipelineRun::where('id', $pipelineRunId)->where('status', 'paused')->exists()) {
                return true;
            }

            $aiTaskId = $this->agent->getAiTaskId();
            if ($aiTaskId && AiTask::where('id', $aiTaskId)->where('status', 'paused')->exists()) {
                return true;
            }

            $endpointLogId = $this->agent->getEndpointLogId();
            if ($endpointLogId && EndpointLog::where('id', $endpointLogId)->where('status', 'paused')->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the underlying tool.
     */
    public function getTool(): Tool
    {
        return $this->tool;
    }
}
