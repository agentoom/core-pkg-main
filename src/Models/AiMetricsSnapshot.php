<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Model;

class AiMetricsSnapshot extends Model
{
    protected $fillable = [
        'snapshot_date', 'total_executions', 'successful_executions', 'failed_executions',
        'paused_executions', 'total_tokens', 'total_cost', 'unpriced_count',
        'avg_response_time_ms', 'avg_queue_wait_ms', 'provider_metrics', 'queue_depth',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'datetime',
            'total_executions' => 'integer',
            'successful_executions' => 'integer',
            'failed_executions' => 'integer',
            'paused_executions' => 'integer',
            'total_tokens' => 'integer',
            'total_cost' => 'decimal:8',
            'unpriced_count' => 'integer',
            'avg_response_time_ms' => 'integer',
            'avg_queue_wait_ms' => 'integer',
            'provider_metrics' => 'array',
            'queue_depth' => 'integer',
        ];
    }
}
