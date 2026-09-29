<?php

namespace Agentoom\Core\Services;

class MenuRegistry
{
    protected array $items = [];

    /**
     * Register a menu item.
     */
    public function register(string $group, array $item): void
    {
        if (! isset($this->items[$group])) {
            $this->items[$group] = [];
        }

        $this->items[$group][] = $item;
    }

    /**
     * Get all registered menu items.
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get items for a specific group.
     */
    public function group(string $group): array
    {
        return $this->items[$group] ?? [];
    }
}
