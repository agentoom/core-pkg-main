<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Contracts\PrivacyIntegration;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemoryWorkerService
{
    /**
     * Process all unanalyzed messages in the system.
     */
    public function processUnanalyzedMessages(): void
    {
        // Group unanalyzed messages by conversation_id, excluding those from the worker agent itself to avoid infinite loops
        $workerAgentId = $this->getWorkerAgentId();
        $query = DB::table('agent_conversation_messages')
            ->where('agent_conversation_messages.is_analyzed', false);

        if ($workerAgentId) {
            $query->whereNotExists(function ($q) use ($workerAgentId) {
                $q->select(DB::raw(1))
                    ->from('agent_conversations')
                    ->whereColumn('agent_conversations.id', 'agent_conversation_messages.conversation_id')
                    ->where('agent_conversations.agent_id', $workerAgentId);
            });
        }

        // Include only conversations that explicitly have memory worker enabled and have an identified user
        $query->whereExists(function ($q) {
            $q->select(DB::raw(1))
                ->from('agent_conversations')
                ->whereColumn('agent_conversations.id', 'agent_conversation_messages.conversation_id')
                ->where('agent_conversations.allow_memory_analysis', true)
                ->where(function ($q) {
                    $q->whereNotNull('agent_conversations.user_id')
                        ->orWhereNotNull('agent_conversation_messages.user_id');
                });
        });

        $unanalyzedMessagesByConversation = $query->orderBy('agent_conversation_messages.created_at', 'asc')
            ->get()
            ->groupBy('conversation_id');

        if ($unanalyzedMessagesByConversation->isEmpty()) {
            return;
        }

        Log::debug('MemoryWorkerService: Found '.count($unanalyzedMessagesByConversation).' conversations to analyze.');

        foreach ($unanalyzedMessagesByConversation as $conversationId => $messages) {
            try {
                Log::debug("MemoryWorkerService: Processing conversation {$conversationId} (".count($messages).' messages).');

                if ($this->processConversationMemory($conversationId, $messages)) {
                    // Update is_analyzed flag to true after processing to prevent duplicate loops
                    $updatedCount = DB::table('agent_conversation_messages')
                        ->whereIn('id', $messages->pluck('id'))
                        ->update(['is_analyzed' => true]);

                    Log::debug("MemoryWorkerService: Successfully processed conversation {$conversationId}. Updated {$updatedCount} messages.");
                } else {
                    Log::info("MemoryWorkerService: Skipping is_analyzed update for conversation {$conversationId} due to processing failure/skip.");
                }
            } catch (\Exception $e) {
                Log::error("MemoryWorkerService: Error processing conversation {$conversationId}: ".$e->getMessage());
            }
        }
    }

    /**
     * Process memory for a single conversation.
     */
    protected function processConversationMemory(string $conversationId, Collection $messages): bool
    {
        Log::debug("MemoryWorkerService: Starting analysis for conversation {$conversationId}.");

        // Include the latest conversation messages, not only the newly flagged batch.
        // A user correction and its assistant response can be marked analyzed separately.
        $contextWindow = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->reverse()
            ->values();

        // Find user and agent associated with the conversation
        $conversation = DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->first();

        if ($conversation && $conversation->project_id) {
            app()->instance('current_project_id', $conversation->project_id);
        }

        $userId = $conversation?->user_id;
        $conversationAgentId = $conversation?->agent_id;

        if (! $userId) {
            // Fallback to messages if conversation record is missing
            $message = DB::table('agent_conversation_messages')
                ->where('conversation_id', $conversationId)
                ->first();
            $userId = $message?->user_id;
        }

        if (! $userId) {
            Log::info("MemoryWorkerService: No user found for conversation {$conversationId}. Skipping extraction.");

            return false;
        }

        // AI Model Agnostic: structure where a smaller model can be plugged in
        $workerAgentId = $this->getWorkerAgentId();

        if (! $workerAgentId) {
            Log::error("MemoryWorkerService: worker_agent_id not configured and 'memory-worker' agent not found. Skipping extraction.");

            return false;
        }

        $workerAgentModel = Agent::find($workerAgentId);

        if (! $workerAgentModel) {
            Log::warning("MemoryWorkerService: Worker agent with ID {$workerAgentId} not found. Skipping extraction.");

            return false;
        }

        Log::debug("MemoryWorkerService: Using worker agent '{$workerAgentModel->name}' ({$workerAgentModel->id}) for extraction.");

        // Impersonate the user for correct context and tool access
        $originalUser = Auth::user();
        Auth::onceUsingId($userId);

        try {
            // Inject AI configuration for the worker agent
            $providerConfig = InjectCurrentAiConfigService::inject($workerAgentModel);

            // Fetch existing knowledge to avoid duplicates
            $existingIdentity = DB::table('user_information')
                ->where('user_id', $userId)
                ->get(['title', 'content', 'importance', 'expires_at'])
                ->toArray();

            $existingSkills = [];
            if ($conversationAgentId) {
                $existingSkills = DB::table('agent_learned_skills')
                    ->where('agent_id', $conversationAgentId)
                    ->get(['title', 'content'])
                    ->toArray();
            }

            // Extract information using the worker agent
            $extractedData = $this->extractData($workerAgentModel, $contextWindow, $conversationId, $providerConfig, $existingIdentity, $existingSkills);

            Log::debug("MemoryWorkerService: Extraction complete for conversation {$conversationId}. Identity count: ".count($extractedData['identity'] ?? []).', Skills count: '.count($extractedData['skills'] ?? []));

            // Save extracted data
            $this->saveExtractedData($conversationId, $extractedData);

            return true;
        } finally {
            // Restore original user context without affecting the session
            if ($originalUser) {
                Auth::setUser($originalUser);
            }
        }
    }

    /**
     * Extract identity and skills from the context using the worker agent.
     * This is AI Model Agnostic - plug in model logic here.
     */
    protected function extractData(Agent $agent, Collection $context, string $conversationId, array $providerConfig = [], array $existingIdentity = [], array $existingSkills = []): array
    {
        Log::debug('MemoryWorkerService: Extracting data using agent: '.$agent->name);

        $messagesContext = $context->map(fn ($m) => "{$m->role}: {$m->content}")->implode("\n");

        // Anonymize message content before sending to the LLM so the
        // provider never sees raw PII from stored conversation history.
        if (app(PrivacyIntegration::class)->shouldAnonymize($conversationId)) {
            try {
                $privacyService = app(PrivacyIntegration::class);
                $messagesContext = $privacyService->anonymize($messagesContext, $conversationId)['text'];
            } catch (\Throwable) {
                // Fall back to original text if anonymization fails
            }
        }

        $identityJson = json_encode($existingIdentity);
        $skillsJson = json_encode($existingSkills);

        $extractionInstructions = 'Analyze the following conversation context and extract user preferences (identity) and successful solutions or specialized procedures (learned skills).

STRICT DEDUPLICATION AND UPDATE POLICY:
- Avoid creating duplicate keys or titles.
- When a user explicitly changes an existing preference, emit the exact existing title with the new value so it replaces the saved value.
- Explicit user corrections take precedence over older saved values.
- Ignore temporary requests, short-lived experiments, greetings, and conversational fluff.
- If the context does not contain a new or changed preference or skill, return empty arrays.

SCOPE:
- IDENTITY: Focus on long‑term preferences (names, roles, websites, persistent formatting).
- LEARNED SKILLS: Focus on SOLUTIONS TO PROBLEMS, fixes for common errors, or specific procedures that work when generic approaches fail.
  * PRIORITIZE: Procedures discovered to fix authentication/credential issues (e.g., correct URL formats, specific headers, required permissions), handling API-specific quirks, or non-obvious tool usage that resolved a previous failure.
  * ACTIONABLE: A learned skill should tell future agents EXACTLY what to do to solve the same problem.
  * AVOID: Generic procedures that any LLM already knows. Focus on what was learned or discovered in THIS specific context.
- Ignore greetings, short‑lived states, or conversational fluff.

OUTPUT FORMAT (JSON ONLY, no extra text before or after):
{
  "identity": [
    {"title": "string", "content": "string", "importance": 1, "expires_at": null}
  ],
  "skills": [
    {"title": "string", "content": "string"}
  ]
}

OUTPUT RULES:
- Identity updates MUST reuse the exact existing title/key whenever one matches.
- Assign importance from 1 (low) to 5 (critical). Stable preferred names, language, and explicit long-term preferences are usually 4 or 5.
- Set expires_at to an ISO 8601 timestamp only for genuinely temporary information; otherwise use null.
- Do not add qualifiers such as "Updated", "(updated)", "v2", dates, or numbering to titles.
- Return changed existing identities as well as genuinely new identities.
- If there are no genuinely new or changed items, return both arrays as empty.

';
        $extractionPrompt = "EXISTING KNOWLEDGE:
- IDENTITY: {$identityJson}
- SKILLS: {$skillsJson}

CONTEXT:
{$messagesContext}";

        try {
            $model = $providerConfig['model'] ?? null;

            // Use DynamicAgent with silent mode to avoid recording background extraction prompts as new conversation messages.
            // Even in silent mode, the LogAgentInteraction middleware will be executed to track AI usage credits.
            $dynamicAgent = new DynamicAgent(
                agentModel: $agent,
                extraInstructions: $extractionInstructions,
                silent: true
            );

            $response = $dynamicAgent->prompt($extractionPrompt, model: $model);

            $content = (string) $response;

            Log::debug('MemoryWorkerService: Agent response: '.$content);
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $data = json_decode($matches[0], true);
                if (is_array($data)) {
                    $identity = $data['identity'] ?? $data['user_information'] ?? [];
                    $identity = collect($identity)
                        ->map(fn (array $item): array => [
                            'title' => $item['title'] ?? $item['key'] ?? '',
                            'content' => $item['content'] ?? $item['value'] ?? '',
                            'importance' => $item['importance'] ?? null,
                            'expires_at' => $item['expires_at'] ?? null,
                        ])
                        ->filter(fn (array $item): bool => $item['title'] !== '' && $item['content'] !== '')
                        ->values()
                        ->all();

                    return [
                        'identity' => $identity,
                        'skills' => $data['skills'] ?? [],
                    ];
                }
            }

            Log::warning('MemoryWorkerService: Failed to parse JSON from agent response: '.$content);
        } catch (\Exception $e) {
            Log::error('MemoryWorkerService: Error during AI extraction: '.$e->getMessage());
        }

        return [
            'identity' => [],
            'skills' => [],
        ];
    }

    /**
     * Placeholder method to save extracted data into tables.
     */
    protected function saveExtractedData(string $conversationId, array $extractedData): void
    {
        // Find user and agent associated with the conversation
        $conversation = DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->first();

        $userId = $conversation?->user_id;
        $agentId = $conversation?->agent_id;

        if (! $userId) {
            // Fallback to messages if conversation record is missing (unlikely but safe)
            $message = DB::table('agent_conversation_messages')
                ->where('conversation_id', $conversationId)
                ->first();
            $userId = $message?->user_id;
        }

        if (! $userId) {
            Log::warning("MemoryWorkerService: No user found for conversation {$conversationId}. Skipping save.");

            return;
        }

        // Save into user_information table
        if (! empty($extractedData['identity'])) {
            foreach ($extractedData['identity'] as $info) {
                $title = $info['title'] ?? 'Preference';
                $content = $info['content'] ?? '';

                if (empty($content)) {
                    continue;
                }

                $existing = DB::table('user_information')
                    ->where('user_id', $userId)
                    ->whereRaw('LOWER(title) = ?', [strtolower($title)])
                    ->first();

                $importance = array_key_exists('importance', $info) && $info['importance'] !== null
                    ? min(max((int) $info['importance'], 1), 5)
                    : (int) ($existing->importance ?? 3);
                $expiresAt = array_key_exists('expires_at', $info)
                    ? $this->normalizeExpiration($info['expires_at'])
                    : ($existing->expires_at ?? null);

                if ($existing) {
                    DB::table('user_information')
                        ->where('id', $existing->id)
                        ->update([
                            'content' => $content,
                            'title' => $title,
                            'importance' => $importance,
                            'expires_at' => $expiresAt,
                            'updated_at' => now(),
                        ]);
                    Log::debug("MemoryWorkerService: Updated user information for user {$userId}: {$title}");

                    continue;
                }

                $contentExists = DB::table('user_information')
                    ->where('user_id', $userId)
                    ->where('content', $content)
                    ->exists();

                if (! $contentExists) {
                    DB::table('user_information')->insert([
                        'user_id' => $userId,
                        'title' => $title,
                        'content' => $content,
                        'importance' => $importance,
                        'expires_at' => $expiresAt,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    Log::debug("MemoryWorkerService: Saved user information for user {$userId}: {$title}");
                }
            }
        }

        // Save into agent_learned_skills table
        if (! empty($extractedData['skills']) && $agentId) {
            foreach ($extractedData['skills'] as $skill) {
                $title = $skill['title'] ?? 'Learned Skill';
                $content = $skill['content'] ?? '';

                if (empty($content)) {
                    continue;
                }

                // Check for existing records by title (case-insensitive)
                $existing = DB::table('agent_learned_skills')
                    ->where('agent_id', $agentId)
                    ->whereRaw('LOWER(title) = ?', [strtolower($title)])
                    ->first();

                if ($existing) {
                    if ($existing->content !== $content) {
                        DB::table('agent_learned_skills')
                            ->where('id', $existing->id)
                            ->update([
                                'content' => $content,
                                'title' => $title, // Sync title casing/version
                                'updated_at' => now(),
                            ]);
                        Log::debug("MemoryWorkerService: Updated learned skill for agent {$agentId}: {$title}");
                    } else {
                        Log::debug("MemoryWorkerService: Skipped redundant learned skill for agent {$agentId}: {$title}");
                    }

                    continue;
                }

                // Check if the same content already exists under a different title
                $contentExists = DB::table('agent_learned_skills')
                    ->where('agent_id', $agentId)
                    ->where('content', $content)
                    ->exists();

                if (! $contentExists) {
                    DB::table('agent_learned_skills')->insert([
                        'agent_id' => $agentId,
                        'title' => $title,
                        'content' => $content,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    Log::debug("MemoryWorkerService: Saved learned skill for agent {$agentId}: {$title}");
                } else {
                    Log::debug("MemoryWorkerService: Skipped learned skill with duplicate content for agent {$agentId}: {$title}");
                }
            }
        } elseif (! empty($extractedData['skills'])) {
            Log::warning("MemoryWorkerService: No agent ID found for conversation {$conversationId}. Cannot save learned skills.");
        }

        Log::debug("MemoryWorkerService: Finalized extraction data for conversation {$conversationId} (User: {$userId})", $extractedData);
    }

    private function normalizeExpiration(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            $date = Carbon::parse((string) $value);

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)
                ? $date->endOfDay()->toDateTimeString()
                : $date->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get the configured worker agent ID.
     */
    protected function getWorkerAgentId(): ?int
    {
        $workerAgentId = Configuration::getValue('worker_agent_id');

        if (! $workerAgentId) {
            $workerAgentId = Agent::where('slug', 'memory-worker')->first()?->id;
        }

        return $workerAgentId ? (int) $workerAgentId : null;
    }
}
