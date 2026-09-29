<?php

namespace Agentoom\Core\Services;

class ToolRegistry
{
    /**
     * Global extension tools: every agent is offered these, and their calls are
     * not wrapped in the action pipeline.
     *
     * @var list<class-string>
     */
    protected array $tools = [];

    /**
     * Tools contributed by packages that behave like core's Ai/Tools/Public
     * tools: discovered into the tools table, offered only to agents that have
     * them assigned, and executed through the action pipeline.
     *
     * @var list<class-string>
     */
    protected array $publicTools = [];

    /**
     * Register a global extension tool.
     */
    public function register(string|array $tool): void
    {
        if (is_array($tool)) {
            $this->tools = array_merge($this->tools, $tool);
        } else {
            $this->tools[] = $tool;
        }

        $this->tools = array_unique($this->tools);
    }

    /**
     * Register a tool that behaves exactly like a core Ai/Tools/Public tool.
     */
    public function registerPublic(string|array $tool): void
    {
        if (is_array($tool)) {
            $this->publicTools = array_merge($this->publicTools, $tool);
        } else {
            $this->publicTools[] = $tool;
        }

        $this->publicTools = array_unique($this->publicTools);
    }

    /**
     * Get all registered global extension tool classes.
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Get all registered public tool classes.
     */
    public function publicTools(): array
    {
        return $this->publicTools;
    }
}
