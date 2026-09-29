<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Tool extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('agentoom_actionable_methods');
        });

        static::deleted(function () {
            Cache::forget('agentoom_actionable_methods');
        });

        static::deleting(function (Tool $tool) {
            // Clean up agent_skills JSON tools array
            $skills = AgentSkill::whereJsonContains('tools', $tool->class_name)->get();
            foreach ($skills as $skill) {
                $skill->tools = array_values(array_diff($skill->tools ?? [], [$tool->class_name]));
                $skill->save();
            }

            // Clean up skill_presets JSON tools array
            $presets = SkillPreset::whereJsonContains('tools', $tool->class_name)->get();
            foreach ($presets as $preset) {
                $preset->tools = array_values(array_diff($preset->tools ?? [], [$tool->class_name]));
                $preset->save();
            }
        });
    }

    protected $fillable = [
        'name',
        'class_name',
        'description',
        'schema',
        'is_available',
        'is_active',
        'is_public',
        'visibility_group',
        'code',
        'is_db_backed',
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
                ->orWhere('class_name', 'like', '%'.$search.'%');
        });
    }

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'is_available' => 'boolean',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_db_backed' => 'boolean',
        ];
    }

    /**
     * Instantiate the tool.
     */
    public function instantiate(): object
    {
        if ($this->is_db_backed && $this->code && ! class_exists($this->class_name)) {
            $code = $this->code;
            if (! str_starts_with(trim($code), '<?php')) {
                $code = '<?php '.$code;
            }
            eval('?>'.$code);
        }

        return app($this->class_name);
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_tool');
    }

    public function mcpServer()
    {
        return $this->belongsTo(McpServer::class);
    }
}
