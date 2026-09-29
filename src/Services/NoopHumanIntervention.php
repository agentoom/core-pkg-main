<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Contracts\HumanIntervention;
use Agentoom\Core\Models\Agent;

/**
 * Core default human-handoff integration used when the optional
 * `agentoom/live-support` package is not installed.
 *
 * Every gate reports the capability as absent, so no handoff tool is offered
 * to agents, no handoff is ever detected, and no conversation is ever paused
 * for a human.
 */
class NoopHumanIntervention implements HumanIntervention
{
    public function isSupported(): bool
    {
        return false;
    }

    public function toolClass(): ?string
    {
        return null;
    }

    public function isHumanAvailable(): bool
    {
        return false;
    }

    public function isWithinOperationalHours(): bool
    {
        return false;
    }

    public function getOperationalHoursString(): string
    {
        return '';
    }

    public function isActive(string $conversationId): bool
    {
        return false;
    }

    public function start(string $conversationId, ?string $reason = null): void {}

    public function startIfAvailable(string $conversationId, ?string $reason = null): bool
    {
        return false;
    }

    public function end(string $conversationId): void {}

    public function shouldTrigger(string $text, Agent $agent, array $customTriggers = []): bool
    {
        return false;
    }

    public function handoffFromToolResult(string $toolName, mixed $rawResult, Agent $agent): ?array
    {
        return null;
    }
}
