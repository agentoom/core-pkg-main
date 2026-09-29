<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTaskLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_task_id',
        'agent_id',
        'content',
        'results',
    ];

    protected function casts(): array
    {
        return [
            'results' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(AiTask::class, 'ai_task_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
