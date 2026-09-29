<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentConversation;
use Illuminate\Support\Facades\Log;

class ConversationTitleService
{
    /**
     * Generate a title for the conversation based on its content.
     */
    public function generateTitle(string $conversationId): void
    {
        $conversation = AgentConversation::find($conversationId);

        if (! $conversation) {
            return;
        }

        // Only generate if title is still a default one
        if (! $conversation->shouldGenerateTitle()) {
            return;
        }

        $messages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id', 'asc')
            ->limit(4)
            ->get();

        if ($messages->isEmpty()) {
            return;
        }

        $context = $messages->map(fn ($m) => "{$m->role}: ".strip_tags($m->content))->implode("\n");

        $instructions = 'You are a helpful assistant that generates concise, relevant titles for AI conversations.
        Analyze the provided conversation snippet and generate a short title (max 6 words).
        Return ONLY the title text, nothing else.';

        $prompt = "Conversation snippet:\n{$context}\n\nConcise Title:";

        try {
            // Use summarizer agent if available, otherwise a generic one
            $agentModel = Agent::where('slug', 'summarizer')->first();

            // If no summarizer, use a default agent
            if (! $agentModel) {
                $agentModel = Agent::where('system_agent', false)->first();
            }

            if (! $agentModel) {
                Log::warning('ConversationTitleService: No agent found to generate title.');

                return;
            }

            $providerConfig = InjectCurrentAiConfigService::inject($agentModel);

            $dynamicAgent = new DynamicAgent(
                agentModel: $agentModel,
                extraInstructions: $instructions,
                silent: true
            );

            $response = $dynamicAgent->prompt($prompt, model: $providerConfig['model']);
            $title = trim((string) $response);

            // Clean up title (remove quotes if any)
            $title = trim($title, '"\' ');

            if (! empty($title) && strlen($title) > 2) {
                $conversation->update(['title' => $title]);
                Log::debug("ConversationTitleService: Generated title for {$conversationId}: {$title}");
            }
        } catch (\Exception $e) {
            Log::error("ConversationTitleService: Failed to generate title for {$conversationId}: ".$e->getMessage());
        }
    }
}
