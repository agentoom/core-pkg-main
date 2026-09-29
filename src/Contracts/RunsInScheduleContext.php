<?php

namespace Agentoom\Core\Contracts;

interface RunsInScheduleContext
{
    /**
     * Get the schedule ID if this agent is running in a scheduled context.
     */
    public function getScheduleId(): ?int;

    /**
     * Get the schedule log ID if this agent is running in a scheduled context.
     */
    public function getScheduleLogId(): ?int;
}
