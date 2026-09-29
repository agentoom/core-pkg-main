<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    /** @var array<string, string> */
    protected $guarded = [];

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    protected function casts(): array
    {
        return [
            'total_bought' => 'decimal:4',
            'metadata' => 'array',
        ];
    }
}
