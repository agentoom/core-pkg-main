<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Contracts\PrivacyIntegration;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\McpServer;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Core default privacy integration used when the optional
 * `agentoom/privacy` package is not installed.
 *
 * Text passes through unchanged, no mappings are stored and every
 * runtime gate reports the capability as disabled, leaving execution
 * unblocked.
 */
class NoopPrivacyIntegration implements PrivacyIntegration
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function redactAgpiiTags(string $text): string
    {
        return $text;
    }

    public function hasPlaceholders(string $text): bool
    {
        return false;
    }

    public function anonymize(string $text, string $mappingKey, ?string $language = null): array
    {
        return [
            'text' => $text,
            'mapping_key' => $mappingKey,
        ];
    }

    public function restore(string $text, string $mappingKey): string
    {
        return $text;
    }

    public function restoreHighlighted(string $text, string $mappingKey): string
    {
        return $text;
    }

    public function shouldAnonymize(?string $conversationId): bool
    {
        return false;
    }

    public function shouldAnonymizeForAgent(Agent $agent): bool
    {
        return false;
    }

    public function redactForViewer(string $text, ?Authenticatable $user): string
    {
        return $text;
    }

    public function redactForLogs(string $text): string
    {
        return $text;
    }

    public function isServiceAvailable(): bool
    {
        return true;
    }

    public function getOnUnavailableAction(): string
    {
        return 'warn_and_continue';
    }

    public function handleUnavailable(): ?string
    {
        return null;
    }

    public function getUnavailableNotificationEmails(): array
    {
        return [];
    }

    public function syncServerPrivacyRecognizers(McpServer $server): array
    {
        return ['imported' => 0, 'updated' => 0, 'existing' => 0];
    }

    public function buildRecognizers(): array
    {
        return [];
    }

    public function importConfigRecognizers(): array
    {
        return ['imported' => 0, 'updated' => 0, 'existing' => 0];
    }
}
