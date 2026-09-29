<?php

namespace Agentoom\Core\Jobs;

use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\WebhookSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $subscriptionId,
        public string $event,
        public array $payload,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $subscription = WebhookSubscription::find($this->subscriptionId);

        if (! $subscription || ! $subscription->is_active) {
            return;
        }

        if (! $subscription->hasEvent($this->event)) {
            return;
        }

        // Route to channel if configured
        if ($subscription->channel) {
            $this->deliverViaChannel($subscription);

            return;
        }

        $body = json_encode([
            'event' => $this->event,
            'timestamp' => now()->toIso8601String(),
            'data' => $this->payload,
        ]);

        $signature = hash_hmac('sha256', $body, $subscription->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                ])
                ->post($subscription->url, $body);

            if ($response->successful()) {
                $subscription->update([
                    'last_delivered_at' => now(),
                    'failed_attempts' => 0,
                ]);
            } elseif ($response->serverError()) {
                throw new \Exception('Server error: '.$response->status());
            }
            // 4xx errors are not retried — they indicate a client error
        } catch (\Exception $e) {
            Log::warning('Webhook delivery failed', [
                'subscription_id' => $this->subscriptionId,
                'event' => $this->event,
                'url' => $subscription->url,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            $subscription->increment('failed_attempts');

            // Release with exponential backoff
            if ($this->attempts() < $this->tries) {
                $this->release(pow(2, $this->attempts()) * 10);
            }

            throw $e;
        }
    }

    /**
     * Deliver the webhook payload via a messaging channel.
     */
    protected function deliverViaChannel(WebhookSubscription $subscription): void
    {
        $message = $this->formatMessage();

        try {
            $success = match ($subscription->channel) {
                'telegram' => $this->deliverTelegram($subscription, $message),
                'whatsapp' => $this->deliverWhatsApp($subscription, $message),
                default => throw new \Exception('Unknown channel: '.$subscription->channel),
            };

            if ($success) {
                $subscription->update([
                    'last_delivered_at' => now(),
                    'failed_attempts' => 0,
                ]);
            } else {
                throw new \Exception('Channel delivery returned false');
            }
        } catch (\Exception $e) {
            Log::warning('Channel delivery failed', [
                'subscription_id' => $this->subscriptionId,
                'event' => $this->event,
                'channel' => $subscription->channel,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            $subscription->increment('failed_attempts');

            if ($this->attempts() < $this->tries) {
                $this->release(pow(2, $this->attempts()) * 10);
            }

            throw $e;
        }
    }

    /**
     * Format the webhook payload as a human-readable message.
     */
    protected function formatMessage(): string
    {
        $entityType = $this->payload['entity_type'] ?? 'unknown';
        $entityName = $this->payload['entity_name'] ?? 'Unknown';
        $output = $this->payload['output'] ?? '';

        $status = str_contains($this->event, 'failed') ? '❌ Failed' : '✅ Completed';

        $lines = [
            "{$status}: {$entityType} \"{$entityName}\"",
            "Event: {$this->event}",
        ];

        if ($output) {
            $truncated = mb_strlen($output) > 500 ? mb_substr($output, 0, 500).'...' : $output;
            $lines[] = "Output: {$truncated}";
        }

        return implode("\n", $lines);
    }

    /**
     * Deliver via Telegram.
     */
    protected function deliverTelegram(WebhookSubscription $subscription, string $message): bool
    {
        $chatId = $subscription->channel_config['chat_id'] ?? null;

        if (! $chatId) {
            Log::error('Telegram delivery failed: no chat_id configured');

            return false;
        }

        // Use the first available Telegram bot token from config
        $token = config('agentoom-instant-messaging.telegram.bots.0.token');

        if (! $token) {
            Log::error('Telegram delivery failed: no bot token configured');

            return false;
        }

        $response = Http::timeout(10)
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

        return $response->successful();
    }

    /**
     * Deliver via WhatsApp.
     */
    protected function deliverWhatsApp(WebhookSubscription $subscription, string $message): bool
    {
        $phoneNumber = $subscription->channel_config['phone_number'] ?? null;

        if (! $phoneNumber) {
            Log::error('WhatsApp delivery failed: no phone_number configured');

            return false;
        }

        $config = Configuration::getValue('instant_messaging_whatsapp');
        $token = $config['api_key'] ?? config('agentoom-instant-messaging.whatsapp.token');
        $phoneId = $config['phone_number_id'] ?? config('agentoom-instant-messaging.whatsapp.phone_number_id');

        if (! $token || ! $phoneId) {
            Log::error('WhatsApp delivery failed: WhatsApp not configured');

            return false;
        }

        $response = Http::withToken($token)
            ->timeout(10)
            ->post("https://graph.facebook.com/v17.0/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phoneNumber,
                'type' => 'text',
                'text' => ['body' => $message],
            ]);

        return $response->successful();
    }

    /**
     * Calculate the exponential backoff.
     */
    public function backoff(): array
    {
        return [10, 20, 40];
    }
}
