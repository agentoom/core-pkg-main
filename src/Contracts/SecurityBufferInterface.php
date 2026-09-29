<?php

namespace Agentoom\Core\Contracts;

use Agentoom\Core\Execution\ActionExecution;
use Closure;

interface SecurityBufferInterface
{
    /**
     * Handle an agent action execution.
     */
    public function handle(ActionExecution $action, Closure $next): mixed;
}
