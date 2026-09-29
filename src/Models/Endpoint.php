<?php

namespace Agentoom\Core\Models;

use Agentoom\Core\Models\Traits\HasProjectOrUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Endpoint extends Model
{
    use HasProjectOrUser;

    protected $fillable = [
        'user_id',
        'project_id',
        'agent_id',
        'pipeline_id',
        'name',
        'slug',
        'description',
        'prompt',
        'is_active',
        'data_structure',
        'allowed_sources',
        'allow_memory_analysis',
        'auth_token_enabled',
        'auth_token',
        'auth_token_header',
        'rate_limit_enabled',
        'rate_limit_max_per_minute',
        'rate_limit_max_per_hour',
        'force_json',
        'mcp_server_id',
        'direct_response_enabled',
        'direct_response_body',
        'direct_response_code',
        'direct_response_content_type',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'project_id' => 'integer',
            'is_active' => 'boolean',
            'data_structure' => 'array',
            'allowed_sources' => 'array',
            'allow_memory_analysis' => 'boolean',
            'auth_token_enabled' => 'boolean',
            'rate_limit_enabled' => 'boolean',
            'rate_limit_max_per_minute' => 'integer',
            'rate_limit_max_per_hour' => 'integer',
            'force_json' => 'boolean',
            'mcp_server_id' => 'integer',
            'direct_response_enabled' => 'boolean',
            'direct_response_code' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Endpoint $endpoint) {
            if (! $endpoint->slug) {
                $endpoint->slug = Str::slug($endpoint->name);
            }
        });
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasAnyRole(['superadmin', 'admin'])) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhereHas('project', function ($pq) use ($user) {
                    $pq->where('user_id', $user->id)
                        ->orWhere('system_user_id', $user->id)
                        ->orWhereHas('assignedUsers', fn ($uq) => $uq->where('users.id', $user->id));
                });
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(EndpointLog::class)->latest();
    }

    public function mcpServer(): BelongsTo
    {
        return $this->belongsTo(McpServer::class);
    }
}
