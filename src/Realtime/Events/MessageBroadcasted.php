<?php

namespace Agentoom\Core\Realtime\Events;

use Agentoom\Core\Realtime\RealtimeManager;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;

class MessageBroadcasted extends BroadcastableEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $conversationId,
        public array $message
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('conversations.'.$this->conversationId),
            new PrivateChannel('conversations.'.$this->conversationId),
        ];

        foreach (app(RealtimeManager::class)->broadcastChannels() as $channel) {
            $channels[] = new PrivateChannel($channel);
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversationId' => $this->conversationId,
            'message' => $this->sanitizeMessage($this->message),
        ];
    }

    /**
     * Pusher's maximum payload size (~10KB). With JSON overhead (~20-30%),
     * a safe per-field budget when both content and anonymized_content are
     * present is ~4000 bytes each. When only content is present (chunks),
     * it can use a larger budget.
     */
    private const MAX_CONTENT_ONLY_BYTES = 7000;

    private const MAX_DUAL_FIELD_BYTES = 5000;

    /**
     * Sanitize the message to ensure it fits within broadcasting limits (e.g., Pusher 10KB).
     */
    protected function sanitizeMessage(array $message): array
    {
        // Remove large image data (Base64 strings are too large for broadcasting)
        if (isset($message['images']) && is_array($message['images'])) {
            foreach ($message['images'] as $key => $image) {
                if (isset($image['base64'])) {
                    $message['images'][$key]['base64'] = '[STRIPPED_FOR_BROADCAST]';
                }
            }
        }

        // Strip attachments entirely — they can contain large data and are not
        // needed for real-time display on the frontend
        unset($message['attachments']);

        $hasAnonymizedContent = isset($message['anonymized_content']);
        $limit = $hasAnonymizedContent
            ? self::MAX_DUAL_FIELD_BYTES
            : self::MAX_CONTENT_ONLY_BYTES;

        // Truncate content if it exceeds the limit (HTML with PII spans can be large)
        if (isset($message['content']) && strlen($message['content']) > $limit) {
            $message['content'] = mb_strcut($message['content'], 0, $limit).'... [TRUNCATED]';
            $message['is_truncated'] = true;
        }

        // Truncate anonymized_content — it mirrors the full raw content and can
        // easily exceed Pusher limits when combined with the highlighted content
        if ($hasAnonymizedContent && strlen($message['anonymized_content']) > $limit) {
            $message['anonymized_content'] = mb_strcut($message['anonymized_content'], 0, $limit).'... [TRUNCATED]';
            $message['is_truncated'] = true;
        }

        return $message;
    }
}
