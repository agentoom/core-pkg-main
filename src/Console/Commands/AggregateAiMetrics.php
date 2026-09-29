<?php

namespace Agentoom\Core\Console\Commands;

use Agentoom\Core\Services\MetricsAggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AggregateAiMetrics extends Command
{
    protected $signature = 'ai:aggregate-metrics {--hour= : Specific hour to aggregate (Y-m-d H:00:00)}';

    protected $description = 'Aggregate AI metrics for the previous hour into a snapshot';

    public function handle(MetricsAggregationService $service): int
    {
        $hour = $this->option('hour')
            ? Carbon::parse($this->option('hour'))
            : now()->subHour()->startOfHour();

        $snapshot = $service->aggregateHour($hour);

        $this->info(sprintf(
            'Metrics snapshot created for %s: %d executions, %d tokens, $%.4f cost',
            $snapshot->snapshot_date->format('Y-m-d H:i'),
            $snapshot->total_executions,
            $snapshot->total_tokens,
            $snapshot->total_cost
        ));

        return self::SUCCESS;
    }
}
