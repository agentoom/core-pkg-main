<?php

namespace Agentoom\Core\Models;

use Database\Factories\AgentSkillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgentSkill extends Model
{
    /** @use HasFactory<AgentSkillFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (AgentSkill $skill) {
            if (empty($skill->slug)) {
                $skill->slug = Str::slug($skill->name);
            }
        });
    }

    protected $attributes = [
        'description' => '',
        'instructions' => '',
        'tools' => '[]',
        'is_active' => true,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'instructions', // General context/mission
        'musts',         // Array: Required steps
        'donts',         // Array: Prohibited actions
        'if_this_then',  // Array: Conditional logic/Event handling
        'tools',
        'is_active',
        'mcp_server_id',
        'remote_name',
    ];

    public function scopeSearch($query, $search)
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $normalizedSearch = str_replace([' ', '-', '_'], '', $search);
            $q->where('name', 'like', '%'.$search.'%')
                ->orWhere(DB::raw("REPLACE(REPLACE(REPLACE(name, ' ', ''), '-', ''), '_', '')"), 'like', '%'.$normalizedSearch.'%')
                ->orWhere('description', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%');
        });
    }

    protected function casts(): array
    {
        return [
            'tools' => 'array',
            'musts' => 'array',
            'donts' => 'array',
            'if_this_then' => 'array', // Stores as key-value pairs
            'is_active' => 'boolean',
        ];
    }

    /**
     * The agents that belong to the skill.
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class);
    }

    public function resourceTypes(): BelongsToMany
    {
        return $this->belongsToMany(ResourceType::class, 'resource_type_skill');
    }

    public function mcpServer()
    {
        return $this->belongsTo(McpServer::class);
    }

    protected static function newFactory(): AgentSkillFactory
    {
        return AgentSkillFactory::new();
    }
}
