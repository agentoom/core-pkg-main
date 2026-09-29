<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    protected $fillable = [
        'user_id', 'project_id', 'agent_id', 'action_type', 'total_tokens', 'cost',
        'provider_cost', 'pricing_source', 'provider', 'model', 'priced_at',
        'idempotency_key', 'input_tokens', 'output_tokens', 'cache_read_tokens',
        'cache_write_tokens', 'reasoning_tokens',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'float',
            'provider_cost' => 'float',
            'priced_at' => 'datetime',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
