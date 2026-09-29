<?php

namespace Agentoom\Core\Models;

use Agentoom\Core\Models\Traits\HasDynamicData;
use Agentoom\Core\Models\Traits\HasProjectOrUser;
use Database\Factories\ResourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resource extends Model
{
    use HasDynamicData;
    use HasFactory;
    use HasProjectOrUser;

    protected $fillable = ['resource_type_id', 'user_id', 'project_id', 'name', 'description', 'data', 'mcp_server_id', 'mcp_uri'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'project_id' => 'integer',
        ];
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    protected static function booted(): void
    {
        static::saving(function (Resource $resource) {
            // Inherit project_id from ResourceType if not set
            if (empty($resource->project_id) && $resource->resource_type_id) {
                $resourceType = ResourceType::find($resource->resource_type_id);
                if ($resourceType && $resourceType->project_id) {
                    $resource->project_id = $resourceType->project_id;
                }
            }

            if ($resource->isDirty('data')) {
                $resource->encryptSensitiveData();
            }
        });
    }

    protected function getDynamicType()
    {
        return $this->resourceType;
    }

    public function resourceType(): BelongsTo
    {
        return $this->belongsTo(ResourceType::class);
    }

    protected static function newFactory(): ResourceFactory
    {
        return ResourceFactory::new();
    }
}
