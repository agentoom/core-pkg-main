<?php

namespace Agentoom\Core\Models;

use Agentoom\Core\Models\Traits\HasProjectOrUser;
use Agentoom\Core\Models\Traits\HasTranslatableName;
use Database\Factories\ResourceTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResourceType extends Model
{
    use HasFactory;
    use HasProjectOrUser;
    use HasTranslatableName;

    protected static function booted(): void
    {
        //
    }

    protected $fillable = ['resource_group_id', 'name', 'description', 'config', 'user_id', 'project_id'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'config' => 'array',
            'resource_group_id' => 'integer',
        ];
    }

    public function resourceGroup(): BelongsTo
    {
        return $this->belongsTo(ResourceGroup::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function scopeVisibleTo($query, ?User $user)
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

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

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(AgentSkill::class, 'resource_type_skill');
    }

    /**
     * Get the fields from the config, handling both flat and 'fields' keyed structures.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFieldsAttribute(): array
    {
        return $this->config['fields'] ?? (isset($this->config[0]) ? $this->config : []);
    }

    protected static function newFactory(): ResourceTypeFactory
    {
        return ResourceTypeFactory::new();
    }
}
