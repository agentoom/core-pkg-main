<?php

namespace Agentoom\Core\Storage;

use Agentoom\Core\Contracts\PrivacyIntegration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Audio;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Laravel\Ai\Storage\DatabaseConversationStore;

class AgentAwareConversationStore extends DatabaseConversationStore
{
    /**
     * Store a new conversation and return its ID.
     */
    public function storeConversation(string|int|null $userId, string $title, ?int $agentId = null): string
    {
        $conversationId = (string) Str::uuid7();

        $agentId = $agentId ?: (app()->bound('current_agent_id') ? app('current_agent_id') : config('agentoom.current_agent_id'));
        $projectId = app()->bound('current_project_id') ? app('current_project_id') : null;
        $widgetId = app()->bound('current_widget_id') ? app('current_widget_id') : null;

        $allowMemoryAnalysis = true;

        if ($agentId) {
            $agent = DB::table('agents')->where('id', $agentId)->first();
            if ($agent) {
                $allowMemoryAnalysis = (bool) $agent->allow_memory_analysis;
            }
        }

        if ($widgetId) {
            $widget = DB::table('agent_widgets')->where('id', $widgetId)->first();
            if ($widget) {
                $allowMemoryAnalysis = $allowMemoryAnalysis && (bool) $widget->allow_memory_analysis;
            }
        }

        if (app()->bound('current_allow_memory_analysis')) {
            $allowMemoryAnalysis = $allowMemoryAnalysis && (bool) app('current_allow_memory_analysis');
        }

        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => $userId,
            'session_id' => session()->getId(),
            'agent_id' => $agentId,
            'project_id' => $projectId,
            'widget_id' => $widgetId,
            'title' => $title,
            'allow_memory_analysis' => $allowMemoryAnalysis,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $conversationId;
    }

    /**
     * Create or update an in-progress assistant message while a streamed response is being generated.
     */
    public function upsertStreamingAssistantMessage(
        string $conversationId,
        string|int|null $userId,
        string $agent,
        string $content,
        ?string $messageId = null,
        ?string $anonymizedContent = null,
    ): string {
        $resolvedMessageId = $messageId ?: (string) Str::uuid7();

        $exists = DB::table('agent_conversation_messages')->where('id', $resolvedMessageId)->exists();

        // Inherit anonymization flag from the latest user message in this conversation
        $anonymize = $this->latestUserMessageAnonymizationFlag($conversationId);

        $values = [
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'agent' => $agent,
            'role' => 'assistant',
            'content' => $content,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => json_encode(['streaming' => true]),
            'privacy_anonymized' => $anonymize,
            'updated_at' => now(),
        ];

        if ($anonymizedContent !== null) {
            $values['anonymized_content'] = $anonymizedContent;
            $values['highlighted_content'] = $this->highlightedContentFor($conversationId, $anonymizedContent);
        }

        if (! $exists) {
            $values['id'] = $resolvedMessageId;
            $values['created_at'] = now();
            DB::table('agent_conversation_messages')->insert($values);
        } else {
            DB::table('agent_conversation_messages')->where('id', $resolvedMessageId)->update($values);
        }

        DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->update(['updated_at' => now()]);

        return $resolvedMessageId;
    }

    /**
     * Finalize an assistant message persisted during streaming with full response metadata.
     *
     * The $response->text has already been deanonymized by LogAgentInteraction
     * middleware at this point. We compute anonymized_content by re-running
     * anonymization on the deanonymized text. Since the MappingStore is keyed
     * by conversation ID, re-anonymizing produces the same placeholders the
     * LLM originally received — no data loss or placeholder drift.
     */
    public function finalizeStreamingAssistantMessage(
        string $messageId,
        AgentPrompt $prompt,
        AgentResponse $response,
    ): void {
        $values = [
            'agent' => $prompt->agent::class,
            'content' => $response->text,
            'tool_calls' => json_encode($response->toolCalls),
            'tool_results' => json_encode($response->toolResults),
            'usage' => json_encode($response->usage),
            'meta' => json_encode($response->meta),
            'updated_at' => now(),
        ];

        // Compute anonymized_content if not already set during streaming
        $current = DB::table('agent_conversation_messages')->where('id', $messageId)->first();

        if ($current && $current->conversation_id) {
            $anonymizedContent = $current->anonymized_content ?: null;

            if (empty($anonymizedContent)) {
                $anonymizedContent = $this->anonymizedContentFor($current->conversation_id, (string) $values['content']);

                if ($anonymizedContent !== null) {
                    $values['anonymized_content'] = $anonymizedContent;
                }
            }

            if ($anonymizedContent !== null && empty($current->highlighted_content)) {
                $values['highlighted_content'] = $this->highlightedContentFor($current->conversation_id, $anonymizedContent);
            }
        }

        DB::table('agent_conversation_messages')
            ->where('id', $messageId)
            ->update($values);
    }

    /**
     * Store a new assistant message for the given conversation and return its ID.
     *
     * The $response->text has already been deanonymized by LogAgentInteraction
     * middleware. We compute anonymized_content by re-running anonymization on
     * the deanonymized text (MappingStore is keyed by conversation ID, so
     * placeholders are consistent).
     */
    public function storeAssistantMessage(string $conversationId, string|int|null $userId, AgentPrompt $prompt, AgentResponse $response): string
    {
        $latestAssistantMessageId = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->where('meta', 'like', '%"streaming":true%')
            ->orderByDesc('updated_at')
            ->value('id');

        if ($latestAssistantMessageId) {
            $this->finalizeStreamingAssistantMessage($latestAssistantMessageId, $prompt, $response);

            return $latestAssistantMessageId;
        }

        $messageId = parent::storeAssistantMessage($conversationId, $userId, $prompt, $response);

        // Inherit anonymization flag from the latest user message in this conversation
        $anonymize = $this->latestUserMessageAnonymizationFlag($conversationId);

        DB::table('agent_conversation_messages')
            ->where('id', $messageId)
            ->update(['privacy_anonymized' => $anonymize]);

        // Compute and store anonymized_content for non-streaming assistant messages
        $anonymizedContent = $this->anonymizedContentFor($conversationId, $response->text);

        if ($anonymizedContent !== null) {
            DB::table('agent_conversation_messages')
                ->where('id', $messageId)
                ->update([
                    'anonymized_content' => $anonymizedContent,
                    'highlighted_content' => $this->highlightedContentFor($conversationId, $anonymizedContent),
                ]);
        }

        return $messageId;
    }

    /**
     * Store a completed assistant message whose content is known up front.
     *
     * Used by channels that do not stream into the message row (the
     * OpenAI-compatible API): the caller supplies the finished text, the
     * anonymization flag and redacted copy are derived exactly as they are for
     * middleware-driven conversations.
     */
    public function storeCompletedAssistantMessage(
        string $conversationId,
        string|int|null $userId,
        string $agent,
        string $content,
        AgentResponse $response,
    ): string {
        $messageId = (string) Str::uuid7();
        $now = now();
        $anonymizedContent = $this->anonymizedContentFor($conversationId, $content);

        DB::table('agent_conversation_messages')->insert([
            'id' => $messageId,
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'agent' => $agent,
            'role' => 'assistant',
            'content' => $content,
            'attachments' => '[]',
            'tool_calls' => json_encode($response->toolCalls->values()),
            'tool_results' => json_encode($response->toolResults->values()),
            'usage' => json_encode($response->usage),
            'meta' => json_encode($response->meta),
            'privacy_anonymized' => $this->latestUserMessageAnonymizationFlag($conversationId),
            'anonymized_content' => $anonymizedContent,
            'highlighted_content' => $this->highlightedContentFor($conversationId, $anonymizedContent),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->touchConversation($conversationId, $now);

        return $messageId;
    }

    /**
     * Compute the display copy of a message: clear text where every restored
     * PII value is wrapped in a highlight span carrying its anonymized
     * placeholder. Returns null when there is nothing to highlight.
     */
    protected function highlightedContentFor(string $conversationId, ?string $anonymizedContent): ?string
    {
        if ($anonymizedContent === null || $anonymizedContent === '') {
            return null;
        }

        try {
            if (! app(PrivacyIntegration::class)->hasPlaceholders($anonymizedContent)) {
                return null;
            }

            return app(PrivacyIntegration::class)->restoreHighlighted($anonymizedContent, $conversationId);
        } catch (\Throwable) {
            // Highlighting is a display nicety — never let it break message storage.
            return null;
        }
    }

    /**
     * Anonymization flag to record on assistant messages: inherited from the
     * latest user message of the conversation, defaulting to enabled.
     */
    protected function latestUserMessageAnonymizationFlag(string $conversationId): bool
    {
        return (bool) (DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->orderByDesc('id')
            ->value('privacy_anonymized') ?? true);
    }

    /**
     * Compute the redacted copy of assistant content when privacy is active.
     *
     * Returns null when no redaction applies (privacy disabled, empty content)
     * or when the anonymization service is unavailable.
     */
    protected function anonymizedContentFor(string $conversationId, string $content): ?string
    {
        if ($content === '' || ! app(PrivacyIntegration::class)->shouldAnonymize($conversationId)) {
            return null;
        }

        try {
            return app(PrivacyIntegration::class)->anonymize($content, $conversationId)['text'];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get the latest messages for the given conversation, including the current user message.
     * When anonymization is enabled, all message content is anonymized in a single pass
     * before being returned to the LLM. The current prompt is also anonymized separately
     * (see ProcessAgentChat) — both pass through the same mapping key for consistent placeholders.
     */
    public function getLatestConversationMessages(string $conversationId, int $limit): Collection
    {
        $records = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $shouldAnonymize = app(PrivacyIntegration::class)->shouldAnonymize($conversationId);

        return $records->flatMap(function ($record) use ($conversationId, $shouldAnonymize) {
            $toolCalls = collect(json_decode($record->tool_calls, true))->values();
            $toolResults = collect(json_decode($record->tool_results, true))->values();

            $content = $record->content ?? '';

            if ($shouldAnonymize && $content !== '') {
                // Use pre-stored anonymized content when available;
                // otherwise fall back to on-the-fly anonymization for legacy messages.
                if (! empty($record->anonymized_content)) {
                    $content = $record->anonymized_content;
                } else {
                    try {
                        $content = app(PrivacyIntegration::class)->anonymize($content, $conversationId)['text'];
                    } catch (\Throwable) {
                        // Fall back to original content if anonymization fails
                    }
                }
            }

            if ($record->role === 'user') {
                $attachments = $this->rehydrateAttachments($record->attachments)
                    ->reject(fn ($attachment): bool => $attachment instanceof Audio);

                if ($attachments->isNotEmpty()) {
                    return [new UserMessage($content, $attachments)];
                }

                return [new Message('user', $content)];
            }

            if ($toolCalls->isNotEmpty()) {
                $messages = [];

                $messages[] = new AssistantMessage(
                    $content ?: '',
                    $toolCalls->map(fn ($toolCall) => new ToolCall(
                        id: $toolCall['id'],
                        name: $toolCall['name'],
                        arguments: $toolCall['arguments'],
                        resultId: $toolCall['result_id'] ?? null,
                        reasoningId: $toolCall['reasoning_id'] ?? null,
                        reasoningSummary: $toolCall['reasoning_summary'] ?? null,
                    ))
                );

                if ($toolResults->isNotEmpty()) {
                    $messages[] = new ToolResultMessage(
                        $toolResults->map(fn ($toolResult) => new ToolResult(
                            id: $toolResult['id'],
                            name: $toolResult['name'],
                            arguments: $toolResult['arguments'],
                            result: $toolResult['result'],
                            resultId: $toolResult['result_id'] ?? null,
                        ))
                    );
                }

                return $messages;
            }

            // Skip assistant messages with neither content nor tool_calls —
            // sending these to the provider causes a 400 error.
            if ($content === '' && $toolCalls->isEmpty()) {
                return [];
            }

            return [new AssistantMessage($content)];
        });
    }

    /**
     * Store a new user message for the given conversation and return its ID.
     *
     * When anonymization is active, the incoming $prompt->prompt is already
     * anonymized (by ProcessAgentChat). Instead of creating a duplicate row,
     * we find the existing clear-text message stored by the controller and
     * update its anonymized_content field. This eliminates the duplicate-message
     * bug where both clear-text and anonymized versions appeared as separate rows.
     */
    public function storeUserMessage(string $conversationId, string|int|null $userId, AgentPrompt $prompt): string
    {
        $content = $prompt->prompt;

        $existing = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->where('created_at', '>=', now()->subSeconds(120))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            // Update anonymized_content on the existing clear-text message
            DB::table('agent_conversation_messages')
                ->where('id', $existing->id)
                ->update([
                    'anonymized_content' => $content,
                    'highlighted_content' => $this->highlightedContentFor($conversationId, $content),
                    'updated_at' => now(),
                ]);

            return $existing->id;
        }

        // No existing message found (unusual path); insert and set anonymized_content
        $messageId = parent::storeUserMessage($conversationId, $userId, $prompt);

        DB::table('agent_conversation_messages')
            ->where('id', $messageId)
            ->update([
                'anonymized_content' => $content,
                'highlighted_content' => $this->highlightedContentFor($conversationId, $content),
            ]);

        return $messageId;
    }

    /**
     * Ensure a user message is stored in the database, avoiding duplicates.
     *
     * When privacy is enabled, also computes the anonymized version of the
     * message content and stores it in anonymized_content. This allows
     * getLatestConversationMessages() to serve the pre-computed anonymized
     * content directly without re-processing.
     */
    public function ensureUserMessageStored(
        string $conversationId,
        string|int|null $userId,
        string $agent,
        string $content,
        array $attachments = [],
        bool $privacyAnonymized = true
    ): string {
        $existing = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->where('content', $content)
            ->where('privacy_anonymized', $privacyAnonymized)
            ->where('created_at', '>=', now()->subSeconds(120))
            ->first();

        if ($existing) {
            return $existing->id;
        }

        // Compute anonymized_content when privacy is active
        $anonymizedContent = null;
        if (app(PrivacyIntegration::class)->shouldAnonymize($conversationId)) {
            try {
                $anonymizedContent = app(PrivacyIntegration::class)->anonymize($content, $conversationId)['text'];
            } catch (\Throwable) {
                // Fall back to leaving anonymized_content null
            }
        }

        $messageId = (string) Str::uuid7();

        DB::table('agent_conversation_messages')->insert([
            'id' => $messageId,
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'agent' => $agent,
            'role' => 'user',
            'content' => $content,
            'attachments' => json_encode($attachments),
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'privacy_anonymized' => $privacyAnonymized,
            'anonymized_content' => $anonymizedContent,
            'highlighted_content' => $this->highlightedContentFor($conversationId, $anonymizedContent),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $messageId;
    }
}
