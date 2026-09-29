<?php

namespace Agentoom\Core\Models;

use Agentoom\Core\Models\Traits\HasProjectOrUser;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Schedule extends Model
{
    use HasFactory, HasProjectOrUser;

    protected $fillable = [
        'name',
        'slug',
        'minute',
        'hour',
        'day',
        'month',
        'day_of_week',
        'timezone',
        'model',
        'model_id',
        'method',
        'instructions',
        'repository_instructions',
        'active',
        'last_execution',
        'user_id',
        'project_id',
        'allow_memory_analysis',
        'mcp_server_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Schedule $schedule) {
            if (! $schedule->slug && $schedule->name) {
                $schedule->slug = Str::slug($schedule->name);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'project_id' => 'integer',
            'active' => 'boolean',
            'last_execution' => 'datetime',
            'allow_memory_analysis' => 'boolean',
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

    /**
     * Get all execution logs for this schedule.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ScheduleLog::class)->latest();
    }

    /**
     * Get the repository data for this schedule.
     */
    public function repositories(): HasMany
    {
        return $this->hasMany(ScheduleRepository::class);
    }

    /**
     * Get the user that owns the schedule.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Build the full cron expression from the five individual fields.
     * Example: "0 9 * * 1" (every Monday at 9:00am)
     */
    public function toCronExpression(): string
    {
        return "{$this->minute} {$this->hour} {$this->day} {$this->month} {$this->day_of_week}";
    }

    /**
     * Resolve and return the actual Agent or Pipeline model instance.
     */
    public function resolveModel(): ?Model
    {
        $model = $this->model;

        if ($model === 'agentoom-core::docs.features.pipeline') {
            $model = Pipeline::class;
        }

        if (! $model || ! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            return null;
        }

        if (! $this->model_id) {
            return null;
        }

        return app($model)->find($this->model_id);
    }

    /**
     * Count how many consecutive failed logs exist from the most recent entry backwards.
     * Stops counting as soon as a 'success' log is encountered.
     */
    public function consecutiveFailuresCount(): int
    {
        return $this->logs()
            ->get()
            ->takeWhile(fn (ScheduleLog $log) => $log->status === 'failed')
            ->count();
    }

    public function mcpServer(): BelongsTo
    {
        return $this->belongsTo(McpServer::class);
    }

    protected static function newFactory(): ScheduleFactory
    {
        return ScheduleFactory::new();
    }
}
