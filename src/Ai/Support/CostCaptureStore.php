<?php

namespace Agentoom\Core\Ai\Support;

final class CostCaptureStore
{
    private bool $active = false;

    private ?float $cost = null;

    public function begin(): void
    {
        $this->active = true;
        $this->cost = null;
    }

    public function add(float $cost): void
    {
        if (! $this->active || ! is_finite($cost) || $cost < 0) {
            return;
        }

        $this->cost = ($this->cost ?? 0.0) + $cost;
    }

    public function total(): ?float
    {
        return $this->active ? $this->cost : null;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function end(): ?float
    {
        $total = $this->cost;
        $this->active = false;

        return $total;
    }

    public function reset(): void
    {
        $this->active = false;
        $this->cost = null;
    }
}
