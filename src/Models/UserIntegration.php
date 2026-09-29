<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Model;

class UserIntegration extends Model
{
    protected $fillable = [
        'user_id',
        'service',
        'data',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
