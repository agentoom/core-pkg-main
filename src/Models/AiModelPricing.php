<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Model;

class AiModelPricing extends Model
{
    protected $fillable = [
        'provider',
        'model',
        'input_price_per_million',
        'output_price_per_million',
        'cache_read_price_per_million',
        'cache_write_price_per_million',
        'reasoning_price_per_million',
        'audio_input_price_per_million',
        'audio_output_price_per_million',
        'request_price',
        'image_price_per_million',
        'source',
        'source_model_id',
        'source_url',
        'mode',
        'priced_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'input_price_per_million' => 'float',
            'output_price_per_million' => 'float',
            'cache_read_price_per_million' => 'float',
            'cache_write_price_per_million' => 'float',
            'reasoning_price_per_million' => 'float',
            'audio_input_price_per_million' => 'float',
            'audio_output_price_per_million' => 'float',
            'request_price' => 'float',
            'image_price_per_million' => 'float',
            'priced_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
