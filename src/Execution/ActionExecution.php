<?php

namespace Agentoom\Core\Execution;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ActionExecution
{
    /**
     * Create a new action execution instance.
     */
    public function __construct(
        public Agent $agent,
        public Tool $tool,
        public Request $request,
        public ?int $scheduleId = null,
        public ?int $pipelineRunId = null,
        public ?int $aiTaskId = null,
        public ?int $endpointId = null,
        public ?int $scheduleLogId = null,
        public ?int $pipelineStepOrder = null,
        public ?int $endpointLogId = null
    ) {}
}
