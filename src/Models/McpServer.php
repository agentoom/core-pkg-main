<?php

namespace Agentoom\Core\Models;

use Agentoom\Core\Services\ResourceLimitManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class McpServer extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::creating(function ($model) {
            if (app(ResourceLimitManager::class)->isLimitReached('mcp_servers')) {
                throw new \Exception(__('agentoom-core::admin.resource_limits.usage.limit_reached'));
            }

            $model->ensureProject();
        });

        static::updating(function ($model) {
            $model->ensureProject();
        });
    }

    /**
     * Auto-bind to a project if none is already set.
     */
    protected function ensureProject(): void
    {
        if (! empty($this->project_id)) {
            return;
        }

        $userId = auth()->id() ?? User::firstOrCreate(
            ['email' => 'system@agentoom.internal'],
            [
                'name' => 'System',
                'password' => bcrypt(Str::random(32)),
                'is_system' => true,
            ]
        )->id;

        $project = Project::create([
            'name' => "MCP: {$this->name}",
            'is_active' => true,
            'user_id' => $userId,
        ]);
        $this->project_id = $project->id;
    }

    protected $fillable = [
        'name',
        'url',
        'transport_type',
        'auth_type',
        'auth_token',
        'auth_header_name',
        'auth_username',
        'auth_password',
        'status',
        'last_synced_at',
        'error_message',
        'metadata',
        'project_id',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'metadata' => 'array',
        'auth_token' => 'encrypted',
        'auth_password' => 'encrypted',
    ];

    protected $hidden = [
        'auth_token',
        'auth_password',
    ];

    public function tools()
    {
        return $this->hasMany(Tool::class);
    }

    public function resources()
    {
        return $this->hasMany(Resource::class);
    }

    public function agents()
    {
        return $this->hasMany(Agent::class);
    }

    public function skills()
    {
        return $this->hasMany(AgentSkill::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
