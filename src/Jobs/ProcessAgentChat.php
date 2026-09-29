<?php

namespace Agentoom\Core\Jobs;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Ai\Support\CostCaptureStore;
use Agentoom\Core\Exceptions\EmergencyStopActiveException;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentConversation;
use Agentoom\Core\Models\User;
use Agentoom\Core\Realtime\Events\MessageBroadcasted;
use Agentoom\Core\Services\AiCreditService;
use Agentoom\Core\Services\InjectCurrentAiConfigService;
use Agentoom\Core\Storage\AgentAwareConversationStore;
use Agentoom\Core\Contracts\HumanIntervention;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Audio;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Streaming\Events\TextDelta;

class ProcessAgentChat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The delays between attempts, in seconds.
     *
     * @var array<int, int>
     */
    public $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $conversationId,
        public int $agentId,
        public int $userId,
        public int $iteration = 1,
        public ?string $messageId = null,
        public string $preContent = '',
        public string $locale = 'en'
    ) {
        $this->queue = 'agent-chat-processing';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        app()->setLocale($this->locale);

        $startTime = microtime(true);
        $user = User::findOrFail($this->userId);
        auth()->login($user);

        $agentModel = Agent::findOrFail($this->agentId);

        $providerConfig = InjectCurrentAiConfigService::inject($agentModel);

        $conversation = AgentConversation::find($this->conversationId);
        if (! $conversation) {
            Log::warning('ProcessAgentChat: Conversation no longer exists', [
                'conversationId' => $this->conversationId,
            ]);

            return;
        }

        $aiMessageId = $this->messageId ?: (string) Str::uuid7();

        // Broadcast that the agent is starting to process
        if ($this->preContent !== '') {
            MessageBroadcasted::dispatch($this->conversationId, [
                'id' => $aiMessageId,
                'role' => 'ai',
                'content' => $this->deanonymizeForBroadcast($this->preContent, highlighted: true),
                'content_html' => $this->shouldBroadcastHtml(),
                'is_chunk' => true,
            ]);
        }

        $lastMessage = $conversation->messages()->where('role', 'user')->latest()->first();

        if (! $lastMessage) {
            return;
        }

        $attachments = [];
        $attachmentDataArray = $lastMessage->attachments ?? [];
        foreach ($attachmentDataArray as $data) {
            $path = $data['real_path'] ?? (Storage::disk('public')->exists($data['path'] ?? '') ? Storage::disk('public')->path($data['path']) : storage_path('app/public/'.($data['path'] ?? '')));
            if (! file_exists($path)) {
                continue;
            }

            if ($data['type'] === 'document') {
                $attachments[] = Document::fromPath($path);
            } elseif ($data['type'] === 'image') {
                $attachments[] = Image::fromPath($path);
            } elseif ($data['type'] === 'audio') {
                $mimeType = $data['mime_type'] ?? null;
                if ($mimeType === 'video/webm') {
                    $mimeType = 'audio/webm';
                }
                $audio = Audio::fromPath($path, $mimeType);

                try {
                    MessageBroadcasted::dispatch($this->conversationId, [
                        'id' => $aiMessageId,
                        'role' => 'ai',
                        'content' => 'Transcribing audio...',
                        'is_chunk' => true,
                    ]);

                    $transcriptionConfig = InjectCurrentAiConfigService::getTranscriptionConfig();
                    if (! empty($transcriptionConfig['provider'])) {
                        config(["ai.providers.{$transcriptionConfig['provider']}.key" => $transcriptionConfig['api_key']]);

                        if (! empty($transcriptionConfig['url'])) {
                            config(["ai.providers.{$transcriptionConfig['provider']}.url" => $transcriptionConfig['url']]);
                        }
                    }

                    $capture = app(CostCaptureStore::class);
                    $capture->begin();
                    $response = $audio->transcription()
                        ->generate(
                            $transcriptionConfig['provider'] ?: null,
                            $transcriptionConfig['model'] ?: null
                        );

                    // Update the user message with transcription
                    $lastMessage->update(['content' => trim($lastMessage->content."\n\n".$response->text)]);

                    // Broadcast updated user message
                    MessageBroadcasted::dispatch($this->conversationId, [
                        'id' => $lastMessage->id,
                        'role' => 'user',
                        'content' => $lastMessage->content,
                        'anonymized_content' => $lastMessage->anonymized_content,
                        'attachments' => $lastMessage->attachments,
                        'images' => $lastMessage->images,
                        'created_at' => $lastMessage->created_at?->format('H:i A') ?? now()->format('H:i A'),
                    ]);

                    // Log usage
                    app(AiCreditService::class)->logUsage(
                        user: $user,
                        actionType: 'audio_transcription',
                        usage: $response->usage,
                        meta: $response->meta,
                        agentId: $agentModel?->id,
                        projectId: $agentModel?->project_id
                    );
                    app(CostCaptureStore::class)->end();
                } catch (\Throwable $e) {
                    Log::error('ProcessAgentChat: Transcription failed', ['error' => $e->getMessage()]);
                    app(CostCaptureStore::class)->end();
                }
            }
        }

        $agent = new DynamicAgent($agentModel);
        $agent->continue($this->conversationId, $user);

        $isContinuation = $this->iteration > 1;
        $prompt = $isContinuation ? 'Please continue your previous response.' : $lastMessage->content;

        $attachments = $isContinuation ? [] : $attachments;

        // Phase 1 (always-on): Replace AGPII tags with deterministic pseudonyms
        // before the prompt reaches the Privacy Service or LLM.
        if (! $isContinuation) {
            $prompt = app(PrivacyIntegration::class)->redactAgpiiTags($prompt);
        }

        // Anonymize the prompt before sending to LLM when privacy is enabled
        if (! $isContinuation && app(PrivacyIntegration::class)->shouldAnonymizeForAgent($agentModel)) {
            try {
                $prompt = app(PrivacyIntegration::class)->anonymize($prompt, $this->conversationId)['text'];
            } catch (\Throwable) {
                // Never proceed silently: the privacy policy decides whether
                // to stop (throws) or continue unredacted (logged + notified).
                app(PrivacyIntegration::class)->handleUnavailable();
            }
        }

        $response = $agent->stream(
            $prompt,
            attachments: $attachments,
            provider: $providerConfig['provider_name'],
            model: $providerConfig['model'],
            timeout: $this->timeout,
        );

        $fullContent = $this->preContent;
        $lastPersistAt = microtime(true);
        $toolResults = [];
        $images = [];
        $broadcastingFailed = false;

        foreach ($response as $event) {
            if (Cache::has("stop-chat-{$this->conversationId}")) {
                Cache::forget("stop-chat-{$this->conversationId}");
                $this->flushUnbilledUsage($user, $agentModel, $providerConfig);
                break;
            }

            if (microtime(true) - $startTime > ($this->timeout - 30)) {
                $hasContent = $fullContent !== '';

                Log::info('ProcessAgentChat: Job approaching timeout, handing over to child job', [
                    'conversationId' => $this->conversationId,
                    'iteration' => $this->iteration,
                    'hasContent' => $hasContent,
                ]);

                // Only persist and hand over if we have content — storing
                // an empty assistant message causes downstream 400 errors.
                if ($hasContent) {
                    // Deanonymize before storing to DB (LLM response contains PII placeholders)
                    $clearContent = $this->deanonymizeForBroadcast($fullContent);

                    // Save current progress to DB
                    app(AgentAwareConversationStore::class)->upsertStreamingAssistantMessage(
                        conversationId: $this->conversationId,
                        userId: $this->userId,
                        agent: DynamicAgent::class,
                        content: $clearContent,
                        messageId: $aiMessageId,
                        anonymizedContent: $fullContent,
                    );
                }

                if ($this->iteration < 10) {
                    self::dispatch(
                        $this->conversationId,
                        $this->agentId,
                        $this->userId,
                        $this->iteration + 1,
                        $aiMessageId,
                        $fullContent,
                        locale: $this->locale
                    )->delay(now()->addSecond());
                } else {
                    Log::warning('ProcessAgentChat: Max iterations reached, stopping handover', [
                        'conversationId' => $this->conversationId,
                    ]);
                }

                $this->flushUnbilledUsage($user, $agentModel, $providerConfig);

                return;
            }

            if ($event instanceof TextDelta) {
                $fullContent .= $event->delta;

                // Periodically save to DB (deanonymized)
                $now = microtime(true);
                if ($now - $lastPersistAt >= 0.5) {
                    $clearContent = $this->deanonymizeForBroadcast($fullContent, highlighted: false);
                    app(AgentAwareConversationStore::class)->upsertStreamingAssistantMessage(
                        conversationId: $this->conversationId,
                        userId: $this->userId,
                        agent: DynamicAgent::class,
                        content: $clearContent,
                        messageId: $aiMessageId,
                        anonymizedContent: $fullContent,
                    );
                    $lastPersistAt = $now;
                }

                // Broadcast the chunk (anonymized_content omitted — frontend
                // only renders highlighted content during streaming and omitting
                // it halves the Pusher payload per chunk)
                if (! $broadcastingFailed) {
                    try {
                        MessageBroadcasted::dispatch($this->conversationId, [
                            'id' => $aiMessageId,
                            'role' => 'ai',
                            'content' => $this->deanonymizeForBroadcast($fullContent, highlighted: true),
                            'content_html' => $this->shouldBroadcastHtml(),
                            'is_chunk' => true,
                        ]);
                    } catch (\Throwable $e) {
                        $broadcastingFailed = true;
                        Log::error('ProcessAgentChat: Broadcasting chunk failed, disabling for this job', [
                            'error' => $e->getMessage(),
                            'conversationId' => $this->conversationId,
                        ]);
                    }
                }
            }
        }

        // After stream is complete, we get the full response to handle tool results
        $finalResponse = null;
        $response->then(function ($res) use (&$finalResponse) {
            $finalResponse = $res;
        });

        if ($finalResponse) {
            $toolResults = $finalResponse->toolResults;
            if (property_exists($finalResponse, 'images')) {
                $images = collect($finalResponse->images)->map(fn ($img) => $img->toArray())->toArray();
            }
        }

        // Handle tool results (e.g., the live-support handoff tool).
        $humanIntervention = app(HumanIntervention::class);
        foreach ($toolResults as $result) {
            $handoff = $humanIntervention->handoffFromToolResult($result->name, $result->result, $agentModel);

            if ($handoff === null) {
                continue;
            }

            if ($humanIntervention->startIfAvailable($this->conversationId, $handoff['reason'])) {
                break;
            }
        }

        // Finalize the message in DB (deanonymized, plain text)
        $clearContent = $this->deanonymizeForBroadcast($fullContent, highlighted: false);
        app(AgentAwareConversationStore::class)->upsertStreamingAssistantMessage(
            conversationId: $this->conversationId,
            userId: $this->userId,
            agent: DynamicAgent::class,
            content: $clearContent,
            messageId: $aiMessageId,
            anonymizedContent: $fullContent,
        );

        if ($this->conversationId && $conversation->shouldGenerateTitle()) {
            GenerateConversationTitleJob::dispatch($this->conversationId)->delay(now()->addSeconds(2));
        }

        // Signal the frontend that the stream is complete. The full highlighted
        // content is already in the database (stored every 500ms during streaming).
        // The frontend fetches it via API instead of receiving it through Pusher,
        // avoiding the 10KB payload limit entirely for final messages.
        if (! $broadcastingFailed) {
            try {
                MessageBroadcasted::dispatch($this->conversationId, [
                    'id' => $aiMessageId,
                    'is_final' => true,
                ]);
            } catch (\Throwable $e) {
                Log::error('ProcessAgentChat: Broadcasting final signal failed', [
                    'error' => $e->getMessage(),
                    'conversationId' => $this->conversationId,
                ]);
            }
        }
    }

    /**
     * A stream abandoned before completion (stopped chat, timeout hand-over)
     * never reaches the middleware's completion callback, so any
     * provider-reported cost captured up to that point is billed here.
     */
    private function flushUnbilledUsage(User $user, Agent $agentModel, array $providerConfig): void
    {
        app(AiCreditService::class)->flushCapturedUsage(
            user: $user,
            actionType: 'agent_interaction_interrupted',
            provider: $providerConfig['provider_name'] ?? null,
            model: $providerConfig['model'] ?? null,
            agentId: $agentModel->id,
            projectId: $agentModel->project_id,
        );
    }

    /**
     * Deanonymize content by restoring PII placeholders to their original values.
     *
     * The LLM response may contain PII placeholders (e.g. <REDACTED-PERSON-1A2B>) that
     * were introduced during prompt and history anonymization.
     * This method restores them so the frontend and database receive clear text.
     */
    private function deanonymizeForBroadcast(string $content, bool $highlighted = false): string
    {
        if (! app(PrivacyIntegration::class)->shouldAnonymize($this->conversationId)) {
            return $content;
        }

        try {
            if ($highlighted) {
                // Replace <REDACTED-...> placeholders with highlighted <span> tags
                // BEFORE converting to HTML via Str::markdown(). The highlighted
                // spans are valid inline HTML that the markdown parser preserves.
                $highlightedRaw = app(PrivacyIntegration::class)->restoreHighlighted($content, $this->conversationId);

                return Str::markdown($highlightedRaw);
            }

            return app(PrivacyIntegration::class)->restore($content, $this->conversationId);
        } catch (\Throwable $e) {
            Log::warning('ProcessAgentChat: Deanonymization failed', [
                'error' => $e->getMessage(),
                'conversationId' => $this->conversationId,
            ]);

            return $content;
        }
    }

    /**
     * Whether broadcast chunk content is HTML.
     *
     * When anonymization is active for the conversation, chunks carry
     * markdown-rendered HTML with PII highlight spans; otherwise they are
     * plain text that the client must render as markdown.
     */
    private function shouldBroadcastHtml(): bool
    {
        return app(PrivacyIntegration::class)->shouldAnonymize($this->conversationId);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $context = [
            'conversationId' => $this->conversationId,
            'error' => $exception->getMessage(),
        ];

        // Capture the full response body from AI provider errors for diagnostics
        if ($exception instanceof RequestException && $exception->response) {
            $context['response_status'] = $exception->response->status();
            $context['response_body'] = $exception->response->body();
        }

        Log::error('ProcessAgentChat job failed', $context);

        try {
            if ($exception instanceof EmergencyStopActiveException) {
                $agentName = 'the AI agent';
                try {
                    $agent = Agent::find($this->agentId);
                    if ($agent) {
                        $agentName = $agent->name;
                    }
                } catch (\Throwable) {
                    // Ignore — use fallback name
                }

                $content = "The agent \"{$agentName}\" has been temporarily suspended by an administrator for compliance or safety reasons. Please try again later or contact support.";
            } else {
                $content = 'Sorry, an error occurred while processing your request. Please try again.';
            }

            MessageBroadcasted::dispatch($this->conversationId, [
                'role' => 'ai',
                'content' => $content,
                'is_chunk' => false,
                'error' => true,
                'created_at' => now()->format('H:i A'),
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessAgentChat: Broadcasting failure message failed', [
                'error' => $e->getMessage(),
                'conversationId' => $this->conversationId,
            ]);
        }
    }
}
