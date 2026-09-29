<?php

namespace Agentoom\Core\Models;

use Agentoom\Core\Models\Traits\HasProjectOrUser;
use Database\Factories\PipelineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Pipeline extends Model
{
    use HasFactory, HasProjectOrUser;

    public const TYPE_STANDARD = 'standard';

    public const TYPE_ENDPOINT = 'endpoint';

    protected $fillable = [
        'user_id',
        'project_id',
        'name',
        'slug',
        'type',
        'description',
        'is_active',
        'model',
        'config',
        'allow_memory_analysis',
        'concurrency_lock',
        'mcp_server_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Pipeline $pipeline) {
            if (! $pipeline->slug && $pipeline->name) {
                $pipeline->slug = Str::slug($pipeline->name);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'project_id' => 'integer',
            'is_active' => 'boolean',
            'model' => 'string',
            'config' => 'array',
            'allow_memory_analysis' => 'boolean',
            'concurrency_lock' => 'boolean',
            'mcp_server_id' => 'integer',
        ];
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasAnyRole(['superadmin', 'admin'])) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhereNull('user_id')
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

    public function steps(): HasMany
    {
        return $this->hasMany(PipelineStep::class)->orderBy('order');
    }

    public function failureStep(): HasMany
    {
        return $this->hasMany(PipelineStep::class)->where('is_failure_handler', true)->orderBy('order');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PipelineRun::class)->latest();
    }

    public function mcpServer(): BelongsTo
    {
        return $this->belongsTo(McpServer::class);
    }

    protected static function newFactory(): PipelineFactory
    {
        return PipelineFactory::new();
    }
}
