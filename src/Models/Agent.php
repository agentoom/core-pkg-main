<?php

namespace Agentoom\Core\Models;

use Agentoom\Core\Models\Traits\HasProjectOrUser;
use Agentoom\Core\Services\InjectCurrentAiConfigService;
use Agentoom\Core\Services\VisibilityService;
use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Agent extends Model
{
    use HasFactory, HasProjectOrUser;

    protected static function booted(): void
    {
        static::saving(function (Agent $agent) {
            if (empty($agent->slug)) {
                $agent->slug = Str::slug($agent->name);
            }
        });

        static::deleting(function (Agent $agent) {
            if ($agent->system_agent) {
                throw new \Exception('System agents cannot be deleted.');
            }
            if ($agent->default_agent) {
                throw new \Exception('Default agents cannot be deleted.');
            }

            $blockers = $agent->getDeletionBlockers();
            if ($blockers->isNotEmpty()) {
                throw new \Exception('This agent cannot be deleted because it is referenced by other entities: '.
                    $blockers->map(fn (array $b) => $b['type'].': '.$b['name'])->implode(', '));
            }
        });
    }

    protected $attributes = [
        'config' => '[]',
        'prompt_settings' => '{"parts":{"language_policy":false},"tools":[]}',
        'is_active' => false,
        'system_agent' => false,
        'default_agent' => false,
        'allow_human_intervention' => false,
    ];

    protected $fillable = [
        'user_id',
        'project_id',
        'name',
        'slug',
        'instructions',
        'role',
        'objective',
        'prioritize',
        'constraints',
        'conditional_rules',
        'output_format',
        'examples',
        'description',
        'operational_protocol',
        'error_handling',
        'is_cautious_constraint_enabled',
        'is_active',
        'is_public',
        'allow_human_intervention',
        'system_agent',
        'default_agent',
        'config',
        'prompt_settings',
        'max_usd_limit',
        'limit_period',
        'limit_starts_at',
        'is_hard_limit',
        'max_tasks_per_period',
        'task_limit_period',
        'task_limit_starts_at',
        'allow_memory_analysis',
        'anonymization_enabled',
        'mcp_server_id',
        'remote_name',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'project_id' => 'integer',
            'config' => 'array',
            'prompt_settings' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'allow_human_intervention' => 'boolean',
            'system_agent' => 'boolean',
            'default_agent' => 'boolean',
            'prioritize' => 'array',
            'constraints' => 'array',
            'conditional_rules' => 'array',
            'output_format' => 'array',
            'examples' => 'array',
            'max_usd_limit' => 'decimal:6',
            'limit_starts_at' => 'date',
            'is_hard_limit' => 'boolean',
            'is_cautious_constraint_enabled' => 'boolean',
            'task_limit_starts_at' => 'date',
            'allow_memory_analysis' => 'boolean',
            'anonymization_enabled' => 'boolean',
        ];
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasAnyRole(['superadmin', 'admin'])) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('is_public', true)
                ->orWhereHas('project', function ($pq) use ($user) {
                    $pq->where('user_id', $user->id)
                        ->orWhere('system_user_id', $user->id)
                        ->orWhereHas('assignedUsers', fn ($uq) => $uq->where('users.id', $user->id));
                });
        });
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    /**
     * Check if the agent is accessible by the given user.
     */
    public function isAccessibleBy(?User $user): bool
    {
        if ($this->is_public) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return static::query()
            ->whereKey($this->id)
            ->visibleTo($user)
            ->exists();
    }

    public function hasCreditLimit(): bool
    {
        return ! empty($this->max_usd_limit) && ! empty($this->limit_period);
    }

    public function getUsageForPeriod(?string $period = null): float
    {
        $period ??= $this->limit_period;

        if (empty($period)) {
            return 0.0;
        }

        $query = $this->usageLogs();

        switch ($period) {
            case 'daily':
                $query->whereDate('created_at', now()->today());
                break;
            case 'weekly':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'monthly':
                $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
                break;
        }

        if ($this->limit_starts_at) {
            $query->where('created_at', '>=', $this->limit_starts_at);
        }

        return (float) $query->sum('cost');
    }

    public function isLimitReached(): bool
    {
        if (! $this->hasCreditLimit()) {
            return false;
        }

        return $this->getUsageForPeriod() >= $this->max_usd_limit;
    }

    public function aiTasks(): HasMany
    {
        return $this->hasMany(AiTask::class);
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(AiTask::class, 'assigned_by_agent_id');
    }

    public function taskPermissions(): HasMany
    {
        return $this->hasMany(AgentTaskPermission::class);
    }

    public function permittedTaskPermissions(): HasMany
    {
        return $this->hasMany(AgentTaskPermission::class, 'permitted_agent_id');
    }

    public function hasTaskLimit(): bool
    {
        return ! empty($this->max_tasks_per_period) && ! empty($this->task_limit_period);
    }

    public function getTaskUsageForPeriod(?string $period = null): int
    {
        $period ??= $this->task_limit_period;

        if (empty($period)) {
            return 0;
        }

        $query = $this->assignedTasks();

        switch ($period) {
            case 'daily':
                $query->whereDate('created_at', now()->today());
                break;
            case 'weekly':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'monthly':
                $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
                break;
        }

        if ($this->task_limit_starts_at) {
            $query->where('created_at', '>=', $this->task_limit_starts_at);
        }

        return (int) $query->count();
    }

    public function isTaskLimitReached(): bool
    {
        if (! $this->hasTaskLimit()) {
            return false;
        }

        return $this->getTaskUsageForPeriod() >= $this->max_tasks_per_period;
    }

    public function knowledgeFiles(): HasMany
    {
        return $this->hasMany(AgentKnowledgeFile::class);
    }

    public function learnedSkills(): HasMany
    {
        return $this->hasMany(AgentLearnedSkill::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(AgentSkill::class);
    }

    public function skillPresets(): BelongsToMany
    {
        return $this->belongsToMany(SkillPreset::class, 'agent_skill_preset');
    }

    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class, 'agent_tool');
    }

    public function mcpServer()
    {
        return $this->belongsTo(McpServer::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AgentVersion::class)->orderBy('version_number', 'desc');
    }

    /**
     * Return instantiated tool objects from the agent's attached tools.
     *
     * @return array<int, \Laravel\Ai\Contracts\Tool>
     */
    public function getInstantiatedTools(): array
    {
        $visibilityService = app(VisibilityService::class);

        $query = $this->tools()
            ->where('is_available', true)
            ->where('is_active', true);

        if (! $visibilityService->isAllGroupsActive()) {
            $activeGroups = $visibilityService->getActiveGroups();
            $query->where(function ($q) use ($activeGroups) {
                $q->whereNull('visibility_group')
                    ->orWhereIn('visibility_group', $activeGroups->toArray());
            });
        }

        return $query->get()
            ->map(fn (Tool $toolModel) => $toolModel->instantiate())
            ->values()
            ->all();
    }

    public function getConnectionSettings(): array
    {
        return InjectCurrentAiConfigService::inject($this);
    }

    /**
     * Get the pipeline steps that reference this agent.
     */
    public function pipelineSteps(): HasMany
    {
        return $this->hasMany(PipelineStep::class);
    }

    /**
     * Get the endpoints that reference this agent.
     */
    public function endpoints(): HasMany
    {
        return $this->hasMany(Endpoint::class);
    }

    /**
     * Get the webhook subscriptions that reference this agent.
     */
    public function webhookSubscriptions(): HasMany
    {
        return $this->hasMany(WebhookSubscription::class);
    }

    /**
     * Get a collection of entities that prevent this agent from being deleted,
     * with their names and admin URLs for display.
     *
     * @return Collection<int, array{type: string, name: string, url: string}>
     */
    public function getDeletionBlockers(): Collection
    {
        $blockers = collect();

        // Check pipeline steps
        $this->loadMissing('pipelineSteps.pipeline');
        foreach ($this->pipelineSteps as $step) {
            if ($step->pipeline) {
                $blockers->push([
                    'type' => 'pipeline',
                    'name' => $step->pipeline->name,
                    'url' => route('admin.pipelines.edit', $step->pipeline),
                ]);
            }
        }

        // Check endpoints
        $this->loadMissing('endpoints');
        foreach ($this->endpoints as $endpoint) {
            $blockers->push([
                'type' => 'endpoint',
                'name' => $endpoint->name,
                'url' => route('admin.endpoints.edit', $endpoint),
            ]);
        }

        // Check webhook subscriptions
        $this->loadMissing('webhookSubscriptions');
        foreach ($this->webhookSubscriptions as $subscription) {
            $blockers->push([
                'type' => 'webhook',
                'name' => $subscription->url,
                'url' => route('admin.webhook-subscriptions.edit', $subscription),
            ]);
        }

        return $blockers;
    }

    protected static function newFactory(): AgentFactory
    {
        return AgentFactory::new();
    }
}
