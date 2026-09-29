<?php

namespace Agentoom\Core\Contracts;

use Agentoom\Core\Exceptions\EmergencyStopActiveException;
use Agentoom\Core\Models\AgentConversationMessage;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\TextResponse;

/**
 * Integration contract for the EU AI Act compliance capability.
 *
 * The core package binds a no-op default that reports every feature as
 * disabled and performs no enforcement. When the optional
 * `agentoom/compliance` package is installed, its service provider
 * rebinds this contract to the real implementation, restoring runtime
 * feature checks, emergency-stop enforcement, audit event dispatching
 * and agent-run tracing.
 */
interface ComplianceIntegration
{
    /**
     * Whether the compliance system is enabled globally (master switch,
     * DB-over-config precedence).
     */
    public function isEnabled(): bool;

    /**
     * Whether a specific compliance feature is enabled.
     *
     * @param  string  $feature  One of: audit_logging, emergency_stops, auto_incidents,
     *                           auto_alerts, governance_gate, ai_disclosure, risk_enforcement
     */
    public function isFeatureEnabled(string $feature): bool;

    /**
     * All enabled compliance feature flags.
     *
     * @return array<string, bool>
     */
    public function enabledFeatures(): array;

    /**
     * Assert that no emergency stop is active for the given entity.
     *
     * @param  string  $entityType  e.g. 'agent', 'pipeline', 'task', 'endpoint'
     *
     * @throws EmergencyStopActiveException
     */
    public function assertNotStopped(string $entityType, int|string $entityId): void;

    /**
     * Dispatch an audit event for an agent execution.
     */
    public function dispatchAgentEvent(
        string $eventType,
        int $agentId,
        mixed $input = null,
        mixed $output = null,
        array $metadata = [],
        ?int $taskId = null,
        ?int $pipelineRunId = null,
        ?int $endpointId = null,
        ?int $scheduleLogId = null,
        ?int $userId = null,
    ): void;

    /**
     * Dispatch an audit event for an endpoint-triggered agent run.
     */
    public function dispatchEndpointEvent(
        string $eventType,
        int $endpointId,
        int $agentId,
        mixed $input = null,
        mixed $output = null,
        array $metadata = [],
    ): void;

    /**
     * Dispatch an audit event for a task execution.
     */
    public function dispatchTaskEvent(
        string $eventType,
        int $taskId,
        int $agentId,
        mixed $input = null,
        mixed $output = null,
        array $metadata = [],
        ?int $userId = null,
    ): void;

    /**
     * Record an agent-run trace for auditing purposes.
     */
    public function traceAgentRun(
        Agent $agent,
        TextResponse $response,
        ?AgentConversationMessage $currentMessage = null,
        ?int $executionTimeMs = null,
        ?string $traceableType = null,
        ?int $traceableId = null,
    ): ?object;
}
