<?php

namespace Agentoom\Core\Models;

use Agentoom\Core\Models\Traits\HasProjectOrUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int|null $endpoint_id Scopes notifications to a single endpoint. Null means all endpoints (or none, if endpoint events aren't selected).
 * @property int|null $agent_id Scopes notifications to a single agent. Null means all agents (or none, if agent_id === 0).
 * @property int|null $pipeline_id Scopes notifications to a single pipeline. Null means all pipelines (or none, if pipeline_id === 0).
 */
class WebhookSubscription extends Model
{
    use HasProjectOrUser;

    protected $fillable = [
        'url',
        'slug',
        'secret',
        'events',
        'is_active',
        'user_id',
        'project_id',
        'agent_id',
        'pipeline_id',
        'exclude_agents',
        'exclude_pipelines',
        'channel',
        'channel_config',
        'last_delivered_at',
        'failed_attempts',
        'mcp_server_id',
        'endpoint_id',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'agent_id' => 'integer',
            'pipeline_id' => 'integer',
            'exclude_agents' => 'boolean',
            'exclude_pipelines' => 'boolean',
            'channel_config' => 'array',
            'last_delivered_at' => 'datetime',
            'failed_attempts' => 'integer',
            'mcp_server_id' => 'integer',
            'endpoint_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WebhookSubscription $webhook) {
            if (! $webhook->slug && $webhook->url) {
                $webhook->slug = Str::slug(parse_url($webhook->url, PHP_URL_HOST).'-'.Str::random(6));
            }
        });
    }

    public function hasEvent(string $eventName): bool
    {
        return in_array($eventName, $this->events ?? []);
    }

    /**
     * Check if this subscription watches only agent events.
     */
    public function isAgentScoped(): bool
    {
        return $this->agent_id !== null;
    }

    /**
     * Check if this subscription watches only pipeline events.
     */
    public function isPipelineScoped(): bool
    {
        return $this->pipeline_id !== null;
    }

    /**
     * Check if this subscription watches only endpoint events.
     */
    public function isEndpointScoped(): bool
    {
        return $this->endpoint_id !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class);
    }

    public function mcpServer(): BelongsTo
    {
        return $this->belongsTo(McpServer::class);
    }
}
