<?php

namespace Agentoom\Core\Contracts;

use Agentoom\Core\Models\Agent;

/**
 * Human handoff capability.
 *
 * Owned by the optional `agentoom/live-support` package. Core binds this
 * contract to {@see \Agentoom\Core\Services\NoopHumanIntervention} unless a
 * package rebinds it, so the rest of the application can offer — or hide —
 * human handoff without ever referencing a package class.
 */
interface HumanIntervention
{
    /**
     * Whether a real human-handoff implementation is installed.
     */
    public function isSupported(): bool;

    /**
     * The tool class that lets an agent request a human, or null when the
     * capability is unavailable.
     */
    public function toolClass(): ?string;

    /**
     * Whether a human is reachable right now: a support agent has manually
     * marked themselves available, or the current time is inside operational
     * hours.
     */
    public function isHumanAvailable(): bool;

    /**
     * Whether the current time is inside the configured operational window.
     */
    public function isWithinOperationalHours(): bool;

    /**
     * Human-readable operational window, e.g. "09:00 to 17:00 (UTC)".
     */
    public function getOperationalHoursString(): string;

    /**
     * Whether a human has taken over the given conversation.
     */
    public function isActive(string $conversationId): bool;

    /**
     * Hand the given conversation over to a human.
     */
    public function start(string $conversationId, ?string $reason = null): void;

    /**
     * Start the handoff only if a human is reachable right now — a support
     * agent is marked available, or the current time is inside operational
     * hours. Every channel routes its escalation through this so they cannot
     * disagree about whether anyone is there.
     *
     * @return bool True when the handoff started.
     */
    public function startIfAvailable(string $conversationId, ?string $reason = null): bool;

    /**
     * Hand the given conversation back to the AI.
     */
    public function end(string $conversationId): void;

    /**
     * Whether an inbound message asks for a human.
     *
     * @param  array<int, string>  $customTriggers  Channel-specific extra triggers.
     */
    public function shouldTrigger(string $text, Agent $agent, array $customTriggers = []): bool;

    /**
     * Inspect a tool result from a completed agent run and return the handoff
     * payload when it is this capability's tool reporting that the agent wants
     * a human. Returns null for every other result.
     *
     * @param  mixed  $rawResult  The raw tool result string.
     * @return array{reason: ?string, message: ?string}|null
     */
    public function handoffFromToolResult(string $toolName, mixed $rawResult, Agent $agent): ?array;
}
