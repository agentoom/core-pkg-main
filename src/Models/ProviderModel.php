<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'model_name',
        'model_features',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'model_features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the provider that owns this model.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
