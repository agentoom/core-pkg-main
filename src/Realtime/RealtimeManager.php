<?php

namespace Agentoom\Core\Realtime;

use Closure;
use Illuminate\Support\Facades\Broadcast;

class RealtimeManager
{
    /**
     * The registered channel authorizers.
     *
     * @var array<string, Closure>
     */
    protected array $channels = [];

    /**
     * Channels every message broadcast is additionally published to.
     *
     * @var string[]
     */
    protected array $broadcastChannels = [];

    /**
     * Register a channel and its authorizer.
     *
     * @param  bool  $broadcast  Publish every message broadcast to this channel as well.
     */
    public function channel(string $channel, Closure $callback, bool $broadcast = false): void
    {
        $this->channels[$channel] = $callback;

        if ($broadcast && ! in_array($channel, $this->broadcastChannels, true)) {
            $this->broadcastChannels[] = $channel;
        }

        Broadcast::channel($channel, $callback);
    }

    /**
     * Get all registered channels.
     *
     * @return array<string, Closure>
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Get the channels that every message broadcast is published to.
     *
     * @return string[]
     */
    public function broadcastChannels(): array
    {
        return $this->broadcastChannels;
    }
}
