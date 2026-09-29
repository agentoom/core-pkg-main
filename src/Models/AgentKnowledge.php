<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class AgentKnowledge extends Model
{
    use Searchable;

    protected $fillable = [
        'file_id',
        'agent_id',
        'project_id',
        'knowledge',
        'summary',
    ];

    public function toSearchableArray()
    {
        return array_merge($this->toArray(), [
            'id' => (string) $this->id,
            'agent_id' => (string) $this->agent_id,
            'project_id' => (string) $this->project_id,
            'knowledge' => (string) $this->knowledge,
            'summary' => (string) $this->summary,
        ]);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(AgentKnowledgeFile::class, 'file_id');
    }
}
