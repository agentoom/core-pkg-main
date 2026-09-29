<?php

namespace Agentoom\Core\Models;

use Agentoom\Core\Models\Traits\HasProjectOrUser;
use Database\Factories\ResourceGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResourceGroup extends Model
{
    use HasFactory;
    use HasProjectOrUser;

    protected $fillable = ['name', 'icon', 'description', 'user_id', 'project_id'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'project_id' => 'integer',
        ];
    }

    public function resourceTypes(): HasMany
    {
        return $this->hasMany(ResourceType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

    protected static function newFactory(): ResourceGroupFactory
    {
        return ResourceGroupFactory::new();
    }
}
