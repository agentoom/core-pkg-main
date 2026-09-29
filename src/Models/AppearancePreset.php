<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Model;

class AppearancePreset extends Model
{
    protected $fillable = [
        'name',
        'type',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}
