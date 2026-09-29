<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Contracts\ComplianceIntegration;
use Agentoom\Core\Models\AgentConversationMessage;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\TextResponse;

/**
 * Core default compliance integration used when the optional
 * `agentoom/compliance` package is not installed.
 *
 * Every runtime check reports the capability as disabled and no
 * enforcement, auditing or tracing occurs.
 */
class NoopComplianceIntegration implements ComplianceIntegration
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function isFeatureEnabled(string $feature): bool
    {
        return false;
    }

    public function enabledFeatures(): array
    {
        return [];
    }

    public function assertNotStopped(string $entityType, int|string $entityId): void
    {
        //
    }

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
    ): void {
        //
    }

    public function dispatchEndpointEvent(
        string $eventType,
        int $endpointId,
        int $agentId,
        mixed $input = null,
        mixed $output = null,
        array $metadata = [],
    ): void {
        //
    }

    public function dispatchTaskEvent(
        string $eventType,
        int $taskId,
        int $agentId,
        mixed $input = null,
        mixed $output = null,
        array $metadata = [],
        ?int $userId = null,
    ): void {
        //
    }

    public function traceAgentRun(
        Agent $agent,
        TextResponse $response,
        ?AgentConversationMessage $currentMessage = null,
        ?int $executionTimeMs = null,
        ?string $traceableType = null,
        ?int $traceableId = null,
    ): ?object {
        return null;
    }
}
