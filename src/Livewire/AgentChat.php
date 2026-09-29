<?php

namespace Agentoom\Core\Livewire;

use Agentoom\ChatWidget\Models\AgentWidget;
use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Helpers\Helpers;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Realtime\Events\MessageBroadcasted;
use Agentoom\Core\Services\AiCreditService;
use Agentoom\Core\Services\InjectCurrentAiConfigService;
use Agentoom\Core\Contracts\HumanIntervention;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Streaming\Events\TextDelta;
use Livewire\Component;

class AgentChat extends Component
{
    public Agent $agent;

    public ?AgentWidget $widget = null;

    public string $prompt = '';

    public array $messages = [];

    public array $providerConfig = [];

    public string $streamedContent = '';

    public bool $streaming = false;

    public bool $hasCredits = true;

    public ?string $conversationId = null;

    public function mount(Agent $agent): void
    {
        if (! $agent->is_active) {
            abort(404);
        }

        if (! $agent->is_public) {
            if (auth()->guest() || ! $agent->users()->whereKey(auth()->id())->exists()) {
                abort(403, 'You do not have access to this agent.');
            }
        }

        $user = auth()->user();
        if ($user) {
            $this->hasCredits = app(AiCreditService::class)->hasSufficientCredits($user);
        }

        $this->agent = $agent;
        // Widgets belong to the optional chat-widget package.
        $this->widget = class_exists(AgentWidget::class) ? $agent->widgets()->first() : null;
    }

    public function send(): void
    {
        Log::debug('AgentChat: send() called', ['prompt' => $this->prompt]);
        $user = auth()->user();
        $this->hasCredits = app(AiCreditService::class)->hasSufficientCredits($user);

        if (! $this->hasCredits) {
            $this->addError('prompt', __('agentoom-core::front.credits_exhausted'));

            return;
        }

        $this->validate(['prompt' => 'required|string|max:10000']);

        $userMessage = trim($this->prompt);
        $this->prompt = '';
        $this->messages[] = [
            'role' => 'user',
            'content' => $userMessage,
            'created_at' => now()->format('H:i A'),
        ];
        $this->streamedContent = '';
        $this->streaming = true;

        if ($this->isHumanInterventionActive()) {
            $this->streaming = false;

            return;
        }

        Log::debug('AgentChat: Triggering ask() via JS');
        $this->js('$wire.ask()');
    }

    /**
     * Human intervention lives in the optional live-support package.
     */
    protected function isHumanInterventionActive(): bool
    {
        if (! $this->conversationId) {
            return false;
        }

        return app(HumanIntervention::class)->isActive($this->conversationId);
    }

    public function ask(): void
    {
        Log::debug('AgentChat: ask() called');

        header('X-Accel-Buffering: no');
        set_time_limit(0);

        // Check if human intervention is active
        if ($this->isHumanInterventionActive()) {
            $this->streaming = false;

            return;
        }

        $this->providerConfig = InjectCurrentAiConfigService::inject($this->agent);
        app()->instance('current_agent_id', $this->agent->id);
        $messages = $this->messages;
        $userMessage = end($messages)['content'];
        $timeout = 120;
        $buffer = '';

        $participant = auth()->user() ?: (object) ['id' => null];
        $agent = new DynamicAgent($this->agent);

        if ($this->conversationId) {
            $agent->continue($this->conversationId, $participant);
        } else {
            $agent->forUser($participant);
        }

        $agent->stream(
            $userMessage,
            provider: $this->providerConfig['provider_name'],
            model: $this->providerConfig['model'],
            timeout: $timeout,
        )
            ->each(function ($event) use (&$buffer) {
                if ($event instanceof TextDelta) {
                    $buffer .= $event->delta;
                    $converted_buffer = Helpers::convertStringToViewableFormat($buffer);
                    $this->stream(
                        content: $converted_buffer,
                        replace: true,
                        to: 'streamedContent');
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            })
            ->then(function ($response) use (&$buffer) {
                $this->conversationId = $response->conversationId;
                $this->streamedContent = $buffer;

                // Handle tool results (e.g., the live-support handoff tool).
                $humanIntervention = app(HumanIntervention::class);
                foreach ($response->toolResults as $result) {
                    $handoff = $humanIntervention->handoffFromToolResult($result->name, $result->result, $this->agent);

                    if ($handoff === null) {
                        continue;
                    }

                    if ($humanIntervention->startIfAvailable($this->conversationId, $handoff['reason'])) {
                        $this->messages[] = [
                            'role' => 'assistant',
                            'content' => $handoff['message'] ?? __('agentoom-core::admin.human_intervention.handoff_started'),
                            'created_at' => now()->format('H:i A'),
                        ];
                        $this->streaming = false;

                        return;
                    }
                }

                $this->messages[] = $msg = [
                    'role' => 'assistant',
                    'content' => Helpers::convertStringToViewableFormat($this->streamedContent),
                    'created_at' => now()->format('H:i A'),
                ];

                if ($this->conversationId) {
                    try {
                        MessageBroadcasted::dispatch($this->conversationId, $msg);
                    } catch (\Throwable $e) {
                        Log::error('AgentChat: Broadcasting failed', [
                            'error' => $e->getMessage(),
                            'conversationId' => $this->conversationId,
                        ]);
                    }
                }

                $this->streaming = false;
            });
    }

    public function getListeners()
    {
        if (! $this->conversationId) {
            return [];
        }

        return [
            "echo-private:conversations.{$this->conversationId},.MessageBroadcasted" => 'onMessageBroadcasted',
        ];
    }

    public function onMessageBroadcasted($event): void
    {
        $message = $event['message'];
        $messageId = $message['id'] ?? null;

        // If we have an ID, try to update existing message
        if ($messageId) {
            foreach ($this->messages as &$m) {
                if (($m['id'] ?? null) === $messageId) {
                    $m['content'] = $message['content'] ?? '';

                    return;
                }
            }
        }

        // Avoid duplication if the message is from the same component instance
        $messages = $this->messages;
        $lastMessage = end($messages);
        if ($lastMessage && ($lastMessage['content'] ?? null) === ($message['content'] ?? null) && ($lastMessage['role'] ?? null) === $message['role']) {
            return;
        }

        $this->messages[] = [
            'id' => $messageId,
            'role' => $message['role'],
            'content' => $message['content'] ?? '',
            'agent_name' => $message['agent_name'] ?? null,
            'created_at' => $message['created_at'] ?? now()->format('H:i A'),
        ];
    }

    public function render()
    {
        return view('agentoom-core::livewire.agent-chat')
            ->layout('agentoom-core::layouts.app', ['title' => $this->agent->chatbot_title ?: $this->agent->name]);
    }
}
