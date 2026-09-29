<?php

namespace Agentoom\Core\Contracts;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\McpServer;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Integration contract for the PII replacement / privacy capability.
 *
 * The core package binds a no-op default: text passes through unchanged,
 * no anonymization is applied and every runtime gate reports the
 * capability as disabled. When the optional `agentoom/privacy` package
 * is installed, its service provider rebinds this contract to the real
 * orchestrating implementation.
 */
interface PrivacyIntegration
{
    /**
     * Whether PII redaction is enabled (env/config master switch).
     */
    public function isEnabled(): bool;

    /**
     * Replace AGPII tags (<agpii-{type}>value</agpii-{type}>) with
     * deterministic pseudonyms. Always-on phase 1 processing.
     */
    public function redactAgpiiTags(string $text): string;

    /**
     * Whether the text contains redaction placeholders that can be restored.
     */
    public function hasPlaceholders(string $text): bool;

    /**
     * Anonymize text by detecting PII, replacing it with placeholders,
     * and storing the mapping.
     *
     * @return array{text: string, mapping_key: string}
     */
    public function anonymize(string $text, string $mappingKey, ?string $language = null): array;

    /**
     * Restore PII placeholders in the text to their original values.
     */
    public function restore(string $text, string $mappingKey): string;

    /**
     * Restore PII placeholders, wrapping restored values in highlighted
     * markup for frontend display.
     */
    public function restoreHighlighted(string $text, string $mappingKey): string;

    /**
     * Determine whether PII anonymization should be applied for a conversation.
     */
    public function shouldAnonymize(?string $conversationId): bool;

    /**
     * Determine whether PII anonymization should be applied for executions
     * of the given agent.
     *
     * Unlike shouldAnonymize(), this gate does not depend on a conversation
     * context: headless runs (API endpoints, background jobs) have no
     * conversation and must not bypass the agent's privacy flag.
     */
    public function shouldAnonymizeForAgent(Agent $agent): bool;

    /**
     * Redact PII from text based on the viewer's permissions.
     */
    public function redactForViewer(string $text, ?Authenticatable $user): string;

    /**
     * Redact PII from text for log output.
     */
    public function redactForLogs(string $text): string;

    /**
     * Check whether the anonymization service is currently reachable.
     */
    public function isServiceAvailable(): bool;

    /**
     * Get the configured action when no anonymization service is available.
     *
     * @return string 'warn_and_continue' or 'warn_and_stop'
     */
    public function getOnUnavailableAction(): string;

    /**
     * Handle the case where anonymization is unavailable.
     *
     * @return string|null Error message for warn_and_continue, or null.
     *
     * @throws \RuntimeException When the configured action is warn_and_stop.
     */
    public function handleUnavailable(): ?string;

    /**
     * Get notification emails configured for unavailability alerts.
     *
     * @return string[]
     */
    public function getUnavailableNotificationEmails(): array;

    /**
     * Sync privacy rules from a single MCP server into the database.
     *
     * @return array{imported: int, updated: int, existing: int}
     */
    public function syncServerPrivacyRecognizers(McpServer $server): array;

    /**
     * Build and return the complete array of all active privacy rules.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildRecognizers(): array;

    /**
     * Import privacy rules from the agentoom-privacy-recognizers config
     * into the database.
     *
     * @return array{imported: int, updated: int, existing: int}
     */
    public function importConfigRecognizers(): array;
}
