<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLine extends Model
{
    /** @var array<string, string> */
    protected $guarded = [];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'total' => 'decimal:4',
            'tax' => 'decimal:4',
            'metadata' => 'array',
        ];
    }
}
