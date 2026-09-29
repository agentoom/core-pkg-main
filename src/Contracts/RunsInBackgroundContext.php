<?php

namespace Agentoom\Core\Contracts;

interface RunsInBackgroundContext
{
    public function getPipelineRunId(): ?int;

    public function getAiTaskId(): ?int;

    public function getEndpointId(): ?int;

    public function getPipelineStepOrder(): ?int;

    public function getEndpointLogId(): ?int;
}
