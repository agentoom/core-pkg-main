<?php

namespace Agentoom\Core\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (Project $project) {
            $user = User::create([
                'name' => "System: {$project->name}",
                'email' => "project.{$project->id}.system@agentoom.internal",
                'password' => bcrypt(Str::random(32)),
                'is_system' => true,
            ]);
            $project->update(['system_user_id' => $user->id]);
        });

        static::deleting(function (Project $project) {
            // Cascade delete the system user
            $systemUser = $project->systemUser;
            if ($systemUser) {
                $project->system_user_id = null;
                $project->saveQuietly();
                $systemUser->delete();
            }
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'budget_limit',
        'budget_limit_starts_at',
        'is_active',
        'system_user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'budget_limit' => 'decimal:6',
            'budget_limit_starts_at' => 'date',
            'is_active' => 'boolean',
            'system_user_id' => 'integer',
        ];
    }

    /**
     * The user that owns the project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The system user that performs background tasks for the project.
     */
    public function systemUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'system_user_id');
    }

    /**
     * Users assigned to this project.
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withTimestamps();
    }

    /**
     * AI usage logs associated with this project.
     */
    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    /**
     * Agent conversations associated with this project.
     */
    public function agentConversations(): HasMany
    {
        return $this->hasMany(AgentConversation::class);
    }

    /**
     * AI tasks associated with this project.
     */
    public function aiTasks(): HasMany
    {
        return $this->hasMany(AiTask::class);
    }

    /**
     * Agents associated with this project.
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /**
     * Schedules associated with this project.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Pipelines associated with this project.
     */
    public function pipelines(): HasMany
    {
        return $this->hasMany(Pipeline::class);
    }

    /**
     * Endpoints associated with this project.
     */
    public function endpoints(): HasMany
    {
        return $this->hasMany(Endpoint::class);
    }

    /**
     * Knowledge files associated with this project.
     */
    public function knowledgeFiles(): HasMany
    {
        return $this->hasMany(AgentKnowledgeFile::class);
    }

    /**
     * Knowledge chunks associated with this project.
     */
    public function knowledge(): HasMany
    {
        return $this->hasMany(AgentKnowledge::class);
    }

    /**
     * Get the total amount spent on this project.
     */
    public function getTotalSpentAttribute(): float
    {
        $query = $this->aiUsageLogs();

        if ($this->budget_limit_starts_at) {
            $query->where('created_at', '>=', $this->budget_limit_starts_at);
        }

        return (float) $query->sum('cost');
    }

    /**
     * Check if the project has exceeded its budget.
     */
    public function hasExceededBudget(): bool
    {
        if ($this->budget_limit === null || $this->budget_limit <= 0) {
            return false;
        }

        return $this->total_spent >= $this->budget_limit;
    }
}
