<?php

namespace Agentoom\Core\Models;

use Agentoom\Core\Models\Traits\HasProjectOrUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineRun extends Model
{
    use HasProjectOrUser;

    protected $fillable = [
        'user_id',
        'project_id',
        'pipeline_id',
        'status',
        'input',
        'output',
        'started_at',
        'completed_at',
        'paused_at',
        'resume_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'project_id' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'paused_at' => 'datetime',
            'resume_at' => 'datetime',
        ];
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

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function stepRuns(): HasMany
    {
        return $this->hasMany(PipelineRunStep::class)->orderBy('id');
    }

    public function durationInSeconds(): int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return 0;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }
}
