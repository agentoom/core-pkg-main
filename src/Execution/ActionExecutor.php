<?php

namespace Agentoom\Core\Execution;

use Agentoom\Core\Helpers\Helpers;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ActionExecutor
{
    /**
     * Create a new action executor instance.
     */
    public function __construct(protected Pipeline $pipeline) {}

    /**
     * Execute an agent tool call through the security pipeline.
     */
    public function execute(
        Agent $agent,
        Tool $tool,
        Request $request,
        ?int $scheduleId = null,
        ?int $pipelineRunId = null,
        ?int $aiTaskId = null,
        ?int $endpointId = null,
        ?int $scheduleLogId = null,
        ?int $pipelineStepOrder = null,
        ?int $endpointLogId = null
    ): Stringable|string {
        $action = new ActionExecution($agent, $tool, $request, $scheduleId, $pipelineRunId, $aiTaskId, $endpointId, $scheduleLogId, $pipelineStepOrder, $endpointLogId);

        // Bind the current action execution to the container for tool context
        app()->instance(ActionExecution::class, $action);

        $pipes = array_merge(
            Config::get('agentoom.execution.pipes', []),
            app(ExecutionPipeRegistry::class)->all(),
        );

        return $this->pipeline
            ->send($action)
            ->through($pipes)
            ->then(function (ActionExecution $action) {
                $toolName = method_exists($action->tool, 'name') ? $action->tool->name() : class_basename($action->tool);
                Helpers::stream('statusContent', json_encode(['state' => 'executing', 'tool' => $toolName]), true);

                return $action->tool->handle($action->request);
            });
    }
}
