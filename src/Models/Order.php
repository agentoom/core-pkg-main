<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @var array<string, string> */
    protected $guarded = [];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    protected function casts(): array
    {
        return [
            'total' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'tax' => 'decimal:4',
            'shipping' => 'decimal:4',
            'discount' => 'decimal:4',
            'ordered_at' => 'datetime',
            'creation_date' => 'datetime',
            'last_updated_date' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
