<?php

namespace Agentoom\Core\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements PasskeyUser
{
    use HasFactory, HasRoles, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected $guard_name = 'web';

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if (str_contains((string) $user->email, '.system@agentoom.internal')) {
                if (Project::where('system_user_id', $user->id)->exists()) {
                    return false;
                }
            }
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'ai_limit_type',
        'ai_limit_amount',
        'ai_limit_start_date',
        'is_available',
        'agent_name',
        'last_available_at',
        'is_system',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ai_limit_amount' => 'integer',
            'ai_limit_start_date' => 'date',
            'is_available' => 'boolean',
            'last_available_at' => 'datetime',
            'is_system' => 'boolean',
        ];
    }

    /**
     * Projects owned by this user.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Projects assigned to this user.
     */
    public function assignedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user')
            ->withTimestamps();
    }

    /**
     * Get the users that this user can assign elements to.
     */
    public function getAssignableUsers(): Collection
    {
        if ($this->hasRole('superadmin')) {
            return static::where('is_system', false)->orderBy('name')->get();
        }

        if ($this->hasRole('admin')) {
            return static::where('is_system', false)->whereDoesntHave('roles', function ($query): void {
                $query->where('name', 'superadmin');
            })->orderBy('name')->get();
        }

        return static::where('id', $this->id)->get();
    }
}
