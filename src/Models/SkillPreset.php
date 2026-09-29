<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SkillPreset extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'skills',
        'tools',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'tools' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_skill_preset');
    }
}
