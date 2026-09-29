<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentTaskPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'permitted_agent_id',
        'can_assign',
        'can_view',
        'assignment_explanation',
    ];

    protected function casts(): array
    {
        return [
            'can_assign' => 'boolean',
            'can_view' => 'boolean',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function permittedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'permitted_agent_id');
    }
}
