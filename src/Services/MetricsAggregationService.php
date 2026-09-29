<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Models\AiMetricsSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MetricsAggregationService
{
    /**
     * Aggregate metrics for a given hour into an AiMetricsSnapshot.
     */
    public function aggregateHour(Carbon $hour): AiMetricsSnapshot
    {
        $start = $hour->copy()->startOfHour();
        $end = $hour->copy()->endOfHour();

        // Aggregate from AiAgentTrace (records only exist for successful runs).
        // The traces table belongs to the optional compliance package; when
        // absent, execution metrics degrade to zero without SQL errors.
        if (Schema::hasTable('ai_agent_traces')) {
            $traceMetrics = DB::table('ai_agent_traces')
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('
                    COUNT(*) as successful_executions,
                    COALESCE(SUM(total_tokens), 0) as total_tokens,
                    COALESCE(AVG(execution_time_ms), 0) as avg_response_time_ms
                ')
                ->first();

            $successfulExecutions = (int) $traceMetrics->successful_executions;
            $totalTokens = (int) $traceMetrics->total_tokens;
            $avgResponseTimeMs = (int) round($traceMetrics->avg_response_time_ms);

            // Per-provider metrics from AiAgentTrace
            $providerMetrics = DB::table('ai_agent_traces')
                ->whereBetween('created_at', [$start, $end])
                ->select('model', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(AVG(execution_time_ms), 0) as avg_latency_ms'))
                ->groupBy('model')
                ->get()
                ->mapWithKeys(function ($row) {
                    return [$row->model => [
                        'count' => (int) $row->count,
                        'avg_latency_ms' => (int) round($row->avg_latency_ms),
                    ]];
                })
                ->toArray();
        } else {
            $successfulExecutions = 0;
            $totalTokens = 0;
            $avgResponseTimeMs = 0;
            $providerMetrics = [];
        }

        // Failed executions from usage logs (traced on failure)
        $failedExecutions = (int) DB::table('ai_usage_logs')
            ->whereBetween('created_at', [$start, $end])
            ->where('action_type', 'agent_interaction_failure')
            ->count();

        // Cost totals intentionally exclude NULL unpriced rows; keep their count visible.
        $usageQuery = DB::table('ai_usage_logs')->whereBetween('created_at', [$start, $end]);
        $totalCost = (float) (clone $usageQuery)->sum('cost');
        $unpricedCount = (int) (clone $usageQuery)->where('pricing_source', 'unpriced')->count();
        $totalExecutions = $successfulExecutions + $failedExecutions;

        // Queue depth: count of pending + reserved jobs
        $queueDepth = DB::table('jobs')->count();

        return AiMetricsSnapshot::updateOrCreate(
            ['snapshot_date' => $start],
            [
                'total_executions' => $totalExecutions,
                'successful_executions' => $successfulExecutions,
                'failed_executions' => $failedExecutions,
                'paused_executions' => 0,
                'total_tokens' => $totalTokens,
                'total_cost' => $totalCost,
                'unpriced_count' => $unpricedCount,
                'avg_response_time_ms' => $avgResponseTimeMs,
                'avg_queue_wait_ms' => 0,
                'provider_metrics' => $providerMetrics,
                'queue_depth' => $queueDepth,
            ]
        );
    }
}
