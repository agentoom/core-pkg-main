<div class="space-y-6">
    <div>
        <flux:heading size="xl">Monitoring</flux:heading>
    </div>

    <!-- Metrics Cards — always show today's data -->
    <div>
        <flux:text size="sm" class="text-zinc-500 mb-3">Today's Summary</flux:text>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <flux:card>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <flux:icon icon="play-circle" class="text-blue-600 dark:text-blue-400 size-5" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">Executions Today</flux:text>
                    <flux:text class="text-2xl font-bold">{{ number_format($totalExecutionsToday) }}</flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center gap-3">
                <div class="p-2 {{ $failureRate > 5 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-emerald-100 dark:bg-emerald-900/30' }} rounded-lg">
                    <flux:icon icon="{{ $failureRate > 5 ? 'exclamation-triangle' : 'check-circle' }}" class="{{ $failureRate > 5 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }} size-5" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">Failure Rate</flux:text>
                    <div class="flex items-baseline gap-1">
                        <flux:text class="text-2xl font-bold {{ $failureRate > 5 ? 'text-red-600' : '' }}">{{ $failureRate }}%</flux:text>
                    </div>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                    <flux:icon icon="clock" class="text-purple-600 dark:text-purple-400 size-5" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">Avg Response Time</flux:text>
                    <div class="flex items-baseline gap-1">
                        <flux:text class="text-2xl font-bold">{{ number_format($avgResponseTime) }}</flux:text>
                        <flux:text size="sm" class="text-zinc-500">ms</flux:text>
                    </div>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                    <flux:icon icon="currency-dollar" class="text-amber-600 dark:text-amber-400 size-5" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">Daily Cost</flux:text>
                    <flux:text class="text-2xl font-bold">${{ number_format($dailyCost, 2) }}</flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-red-100 dark:bg-red-900/30 rounded-lg"><flux:icon icon="question-mark-circle" class="text-red-600 dark:text-red-400 size-5" /></div>
                <div><flux:text size="sm" class="text-zinc-500">Unpriced Usage</flux:text><flux:text class="text-2xl font-bold">{{ number_format($unpricedCount) }}</flux:text></div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg">
                    <flux:icon icon="queue-list" class="text-indigo-600 dark:text-indigo-400 size-5" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">Queue Depth</flux:text>
                    <flux:text class="text-2xl font-bold">{{ number_format($queueDepth) }}</flux:text>
                </div>
            </div>
        </flux:card>
    </div>
    </div>

    <!-- Timeframe Filter -->
    <div class="flex items-center justify-between">
        <flux:text size="sm" class="text-zinc-500">Chart Timeframe</flux:text>
        <div class="flex gap-2 items-center">
            <flux:button.group>
                <flux:button wire:click="$set('range', '24h')" :variant="$range === '24h' ? 'primary' : 'ghost'" size="sm">24h</flux:button>
                <flux:button wire:click="$set('range', '7d')" :variant="$range === '7d' ? 'primary' : 'ghost'" size="sm">7d</flux:button>
                <flux:button wire:click="$set('range', '30d')" :variant="$range === '30d' ? 'primary' : 'ghost'" size="sm">30d</flux:button>
            </flux:button.group>
            <flux:separator vertical class="!h-5" />
            <flux:input type="date" wire:model.live="startDate" size="sm" class="w-32" placeholder="Start" />
            <flux:text size="sm" class="text-zinc-500">–</flux:text>
            <flux:input type="date" wire:model.live="endDate" size="sm" class="w-32" placeholder="End" />
            <flux:button variant="ghost" size="sm" wire:click="applyCustomRange" :disabled="!$startDate || !$endDate">Apply</flux:button>
        </div>
    </div>

    @if(!$hasSnapshots)
        <div class="flex items-center justify-center h-64 border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-lg">
            <div class="text-center space-y-2">
                <flux:icon icon="chart-bar" class="size-10 text-zinc-300 dark:text-zinc-600 mx-auto" />
                <flux:text class="text-zinc-500">No metrics data available yet. Snapshots are collected hourly.</flux:text>
            </div>
        </div>
    @else
        <!-- Executions Chart -->
        <flux:card>
            <flux:heading size="lg" class="mb-4">Agent Executions</flux:heading>
            <flux:chart :value="$executionsChart" class="w-full">
                <flux:chart.viewport class="aspect-[3/1]">
                    <flux:chart.svg gutter="20">
                        <flux:chart.axis axis="x" field="date" :format="['month' => 'short', 'day' => 'numeric']">
                            <flux:chart.axis.tick class="text-xs text-zinc-400" />
                            <flux:chart.axis.line class="text-zinc-200 dark:text-zinc-700" />
                        </flux:chart.axis>
                        <flux:chart.axis axis="y" field="successful" :format="['notation' => 'compact', 'compactDisplay' => 'short', 'maximumFractionDigits' => 1]">
                            <flux:chart.axis.tick class="text-xs text-zinc-400" />
                        </flux:chart.axis>
                        <flux:chart.line field="successful" class="text-emerald-500 dark:text-emerald-400" />
                        <flux:chart.line field="failed" class="text-red-500 dark:text-red-400" />
                        <flux:chart.cursor />
                    </flux:chart.svg>
                </flux:chart.viewport>
                <div class="flex justify-center gap-4 pt-2">
                    <flux:chart.legend label="Successful">
                        <flux:chart.legend.indicator class="bg-emerald-500" />
                    </flux:chart.legend>
                    <flux:chart.legend label="Failed">
                        <flux:chart.legend.indicator class="bg-red-500" />
                    </flux:chart.legend>
                </div>
                <flux:chart.tooltip>
                    <flux:chart.tooltip.heading field="date" />
                    <flux:chart.tooltip.value label="Successful" field="successful" />
                    <flux:chart.tooltip.value label="Failed" field="failed" />
                </flux:chart.tooltip>
            </flux:chart>
        </flux:card>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Tokens Chart -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Token Consumption</flux:heading>
                <flux:chart :value="$tokensChart" class="aspect-[2/1] w-full">
                    <flux:chart.svg gutter="20">
                        <flux:chart.axis axis="x" field="date" :format="['month' => 'short', 'day' => 'numeric']">
                            <flux:chart.axis.tick class="text-xs text-zinc-400" />
                            <flux:chart.axis.line class="text-zinc-200 dark:text-zinc-700" />
                        </flux:chart.axis>
                        <flux:chart.axis axis="y" field="tokens" position="right" :format="['notation' => 'compact', 'compactDisplay' => 'short', 'maximumFractionDigits' => 1]">
                            <flux:chart.axis.tick class="text-xs text-zinc-400" />
                        </flux:chart.axis>
                        <flux:chart.line field="tokens" class="text-purple-500 dark:text-purple-400" />
                        <flux:chart.area field="tokens" class="text-purple-200/50 dark:text-purple-800/50" />
                        <flux:chart.cursor />
                    </flux:chart.svg>
                    <flux:chart.tooltip>
                        <flux:chart.tooltip.heading field="date" />
                        <flux:chart.tooltip.value label="Tokens" field="tokens" />
                    </flux:chart.tooltip>
                </flux:chart>
            </flux:card>

            <!-- Cost Chart -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Cost Trend</flux:heading>
                <flux:chart :value="$costChart" class="aspect-[2/1] w-full">
                    <flux:chart.svg gutter="20">
                        <flux:chart.axis axis="x" field="date" :format="['month' => 'short', 'day' => 'numeric']">
                            <flux:chart.axis.tick class="text-xs text-zinc-400" />
                            <flux:chart.axis.line class="text-zinc-200 dark:text-zinc-700" />
                        </flux:chart.axis>
                        <flux:chart.axis axis="y" field="cost" position="right" :format="['style' => 'currency', 'currency' => 'USD', 'minimumFractionDigits' => 2, 'maximumFractionDigits' => 4]" tick-prefix="$">
                            <flux:chart.axis.tick class="text-xs text-zinc-400" />
                        </flux:chart.axis>
                        <flux:chart.line field="cost" class="text-amber-500 dark:text-amber-400" />
                        <flux:chart.area field="cost" class="text-amber-200/50 dark:text-amber-800/50" />
                        <flux:chart.cursor />
                    </flux:chart.svg>
                    <flux:chart.tooltip>
                        <flux:chart.tooltip.heading field="date" />
                        <flux:chart.tooltip.value label="Cost" field="cost" prefix="$" />
                    </flux:chart.tooltip>
                </flux:chart>
            </flux:card>

            <!-- Latency Chart -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Avg Response Latency</flux:heading>
                <flux:chart :value="$latencyChart" class="aspect-[2/1] w-full">
                    <flux:chart.svg gutter="20">
                        <flux:chart.axis axis="x" field="date" :format="['month' => 'short', 'day' => 'numeric']">
                            <flux:chart.axis.tick class="text-xs text-zinc-400" />
                            <flux:chart.axis.line class="text-zinc-200 dark:text-zinc-700" />
                        </flux:chart.axis>
                        <flux:chart.axis axis="y" field="latency" position="right" :format="['notation' => 'compact', 'compactDisplay' => 'short', 'maximumFractionDigits' => 0]" tick-suffix=" ms">
                            <flux:chart.axis.tick class="text-xs text-zinc-400" />
                        </flux:chart.axis>
                        <flux:chart.line field="latency" class="text-blue-500 dark:text-blue-400" />
                        <flux:chart.cursor />
                    </flux:chart.svg>
                    <flux:chart.tooltip>
                        <flux:chart.tooltip.heading field="date" />
                        <flux:chart.tooltip.value label="ms" field="latency" />
                    </flux:chart.tooltip>
                </flux:chart>
            </flux:card>

            <!-- Provider Health -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Provider Health</flux:heading>
                @if(!empty($providerMetrics))
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                    <th class="text-left py-2 font-medium text-zinc-500">Provider/Model</th>
                                    <th class="text-right py-2 font-medium text-zinc-500">Requests</th>
                                    <th class="text-right py-2 font-medium text-zinc-500">Avg Latency</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($providerMetrics as $model => $metrics)
                                    <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                        <td class="py-2">
                                            <flux:text class="font-mono text-xs">{{ $model }}</flux:text>
                                        </td>
                                        <td class="text-right py-2">
                                            <flux:badge size="sm" color="blue">{{ $metrics['count'] }}</flux:badge>
                                        </td>
                                        <td class="text-right py-2">
                                            <flux:text size="sm">{{ number_format($metrics['avg_latency_ms']) }} ms</flux:text>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-6">
                        <flux:text size="sm" class="text-zinc-500">No provider data available yet.</flux:text>
                    </div>
                @endif
            </flux:card>
        </div>
    @endif
</div>
