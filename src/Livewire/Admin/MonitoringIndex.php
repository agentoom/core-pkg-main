<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\AiMetricsSnapshot;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class MonitoringIndex extends Component
{
    public string $range = '24h';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public function mount(): void
    {
        Gate::authorize('dashboard.view');
    }

    public function updatedRange(string $value): void
    {
        $this->range = $value;
        $this->startDate = null;
        $this->endDate = null;
    }

    public function updatedStartDate(?string $value): void
    {
        $this->startDate = $value;
        if ($this->startDate && $this->endDate) {
            $this->range = 'custom';
        }
    }

    public function updatedEndDate(?string $value): void
    {
        $this->endDate = $value;
        if ($this->startDate && $this->endDate) {
            $this->range = 'custom';
        }
    }

    public function applyCustomRange(): void
    {
        if ($this->startDate && $this->endDate) {
            $this->range = 'custom';
        }
    }

    protected function querySnapshots()
    {
        if ($this->range === 'custom' && $this->startDate && $this->endDate) {
            return AiMetricsSnapshot::query()
                ->where('snapshot_date', '>=', Carbon::parse($this->startDate)->startOfDay())
                ->where('snapshot_date', '<=', Carbon::parse($this->endDate)->endOfDay())
                ->orderBy('snapshot_date', 'asc')
                ->get();
        }

        return AiMetricsSnapshot::query()
            ->orderBy('snapshot_date', 'desc')
            ->limit(match ($this->range) {
                '7d' => 168,
                '30d' => 720,
                default => 24,
            })
            ->get()
            ->reverse()
            ->values();
    }

    public function render()
    {
        $snapshots = $this->querySnapshots();

        // Compute summary stats from today's snapshots only
        $todaySnapshots = AiMetricsSnapshot::query()
            ->whereDate('snapshot_date', now()->toDateString())
            ->orderBy('snapshot_date', 'asc')
            ->get();

        $totalExecutionsToday = $todaySnapshots->sum('total_executions');
        $totalFailedToday = $todaySnapshots->sum('failed_executions');
        $failureRate = $totalExecutionsToday > 0
            ? round(($totalFailedToday / $totalExecutionsToday) * 100, 1)
            : 0;
        $avgResponseTime = $todaySnapshots->avg('avg_response_time_ms')
            ? round($todaySnapshots->avg('avg_response_time_ms'))
            : 0;
        $dailyCost = $todaySnapshots->sum('total_cost');
        $dailyTokens = $todaySnapshots->sum('total_tokens');
        $unpricedCountToday = $todaySnapshots->sum('unpriced_count');

        // Queue depth — latest snapshot
        $latestSnapshot = AiMetricsSnapshot::query()
            ->orderBy('snapshot_date', 'desc')
            ->first();
        $queueDepth = $latestSnapshot?->queue_depth ?? 0;

        // Provider health — aggregate across all snapshots in the selected timeframe
        $providerMetrics = [];
        foreach ($snapshots as $snapshot) {
            $metrics = $snapshot->provider_metrics;
            if (empty($metrics)) {
                continue;
            }
            foreach ($metrics as $model => $data) {
                if (! isset($providerMetrics[$model])) {
                    $providerMetrics[$model] = [
                        'count' => 0,
                        'total_latency_ms' => 0,
                    ];
                }
                $providerMetrics[$model]['count'] += $data['count'] ?? 0;
                $providerMetrics[$model]['total_latency_ms'] += ($data['count'] ?? 0) * ($data['avg_latency_ms'] ?? 0);
            }
        }

        // Calculate average latency per model
        foreach ($providerMetrics as $model => &$data) {
            $data['avg_latency_ms'] = $data['count'] > 0
                ? (int) round($data['total_latency_ms'] / $data['count'])
                : 0;
            unset($data['total_latency_ms']);
        }

        // Chart data for executions (success/failure)
        $executionsChart = $snapshots->map(fn ($s) => [
            'date' => $s->snapshot_date->format('Y-m-d\TH:i:s'),
            'successful' => $s->successful_executions,
            'failed' => $s->failed_executions,
        ])->toArray();

        // Chart data for tokens
        $tokensChart = $snapshots->map(fn ($s) => [
            'date' => $s->snapshot_date->format('Y-m-d\TH:i:s'),
            'tokens' => $s->total_tokens,
        ])->toArray();

        // Chart data for cost
        $costChart = $snapshots->map(fn ($s) => [
            'date' => $s->snapshot_date->format('Y-m-d\TH:i:s'),
            'cost' => (float) $s->total_cost,
        ])->toArray();

        // Chart data for latency
        $latencyChart = $snapshots->map(fn ($s) => [
            'date' => $s->snapshot_date->format('Y-m-d\TH:i:s'),
            'latency' => $s->avg_response_time_ms,
        ])->toArray();

        if (session()->has('success')) {
            Flux::toast(session('success'), variant: 'success');
        }

        if (session()->has('error')) {
            Flux::toast(session('error'), variant: 'danger');
        }

        return view('agentoom-core::livewire.admin.monitoring-index', [
            'totalExecutionsToday' => $totalExecutionsToday,
            'failureRate' => $failureRate,
            'avgResponseTime' => $avgResponseTime,
            'dailyCost' => $dailyCost,
            'dailyTokens' => $dailyTokens,
            'unpricedCount' => $unpricedCountToday,
            'queueDepth' => $queueDepth,
            'providerMetrics' => $providerMetrics,
            'executionsChart' => $executionsChart,
            'tokensChart' => $tokensChart,
            'costChart' => $costChart,
            'latencyChart' => $latencyChart,
            'hasSnapshots' => $snapshots->isNotEmpty(),
        ])->layout('agentoom-core::layouts.admin');
    }
}
