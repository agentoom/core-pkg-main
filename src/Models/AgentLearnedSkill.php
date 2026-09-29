<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class AgentLearnedSkill extends Model
{
    use Searchable;

    protected $fillable = [
        'agent_id',
        'title',
        'content',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'agent_id' => (string) $this->agent_id,
            'title' => (string) $this->title,
            'content' => (string) $this->content,
        ];
    }
}
