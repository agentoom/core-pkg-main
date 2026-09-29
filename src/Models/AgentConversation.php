<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentConversation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'session_id',
        'agent_id',
        'widget_id',
        'project_id',
        'title',
        'is_human_intervention',
        'intervention_at',
        'allow_memory_analysis',
        'is_private',
    ];

    protected $casts = [
        'is_human_intervention' => 'boolean',
        'intervention_at' => 'datetime',
        'allow_memory_analysis' => 'boolean',
        'is_private' => 'boolean',
    ];

    /**
     * Determine if the conversation should have a title generated.
     */
    public function shouldGenerateTitle(): bool
    {
        $defaultTitles = [
            'New Chat',
            'Untitled Chat',
            'Background Task:',
            __('agentoom-core::front.new_chat'),
            __('agentoom-core::front.untitled_chat'),
        ];

        if (empty($this->title)) {
            return true;
        }

        foreach ($defaultTitles as $default) {
            if (str_starts_with($this->title, $default)) {
                return true;
            }
        }

        return false;
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentConversationMessage::class, 'conversation_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(AgentConversationShare::class, 'conversation_id');
    }

    public function sharedWithUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agent_conversation_shares', 'conversation_id', 'shared_with_user_id')
            ->withPivot('shared_by_user_id')
            ->withTimestamps();
    }
}
