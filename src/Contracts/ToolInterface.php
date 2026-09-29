<?php

namespace Agentoom\Core\Contracts;

use Laravel\Ai\Contracts\Tool;

interface ToolInterface extends Tool
{
    /**
     * Get the tool's name.
     */
    public function name(): string;
}
