<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('agentoom-core::admin.projects.expenses') }}</flux:heading>
            <flux:subheading>Analyze and monitor AI spending across projects, agents and task types.</flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <flux:card class="flex items-center gap-4">
            <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                <flux:icon icon="currency-dollar" class="text-blue-600 dark:text-blue-400 size-6" />
            </div>
            <div>
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('agentoom-core::admin.projects.total_cost_filtered') }}</div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-white">${{ number_format($totalCostFiltered, 2) }}</div>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                <flux:icon icon="bolt" class="text-green-600 dark:text-green-400 size-6" />
            </div>
            <div>
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Requests</div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($totalRequests) }}</div>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                <flux:icon icon="folder" class="text-purple-600 dark:text-purple-400 size-6" />
            </div>
            <div>
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Active Projects</div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $projects->where('is_active', true)->count() }}</div>
            </div>
        </flux:card>
        <flux:card class="flex items-center gap-4">
            <flux:icon icon="question-mark-circle" class="text-red-600 dark:text-red-400 size-6" />
            <div><div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('agentoom-core::admin.ai_usage_logs.unpriced') }}</div><div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($unpricedCount) }}</div></div>
        </flux:card>
    </div>

    @if(!empty($chartData))
        <flux:card>
            <div class="flex items-center justify-between mb-6">
                <flux:heading size="lg">{{ __('agentoom-core::admin.projects.spending_over_time') }}</flux:heading>
            </div>

            <flux:chart :value="$chartData" class="aspect-[4/1] w-full">
                <flux:chart.svg gutter="20">
                    <flux:chart.axis axis="x" field="date" />
                    <flux:chart.axis axis="y" field="cost" position="right" :format="['style' => 'currency', 'currency' => 'USD']" />
                    <flux:chart.line field="cost" class="text-blue-500 dark:text-blue-400" />
                    <flux:chart.area field="cost" class="text-blue-200/50 dark:text-blue-800/50" />
                    <flux:chart.cursor />
                </flux:chart.svg>

                <flux:chart.tooltip>
                    <flux:chart.tooltip.heading field="date" />
                    <flux:chart.tooltip.value label="{{ __('agentoom-core::admin.ai_usage_logs.cost') }}" field="cost" :format="['style' => 'currency', 'currency' => 'USD']" />
                </flux:chart.tooltip>
            </flux:chart>
        </flux:card>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('agentoom-core::admin.projects.by_agent') }}</flux:heading>
            <div class="space-y-3">
                @foreach($agentsBreakdown->sortByDesc('total_cost')->take(10) as $breakdown)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" variant="outline">{{ $breakdown->agent->name ?? 'Unknown' }}</flux:badge>
                        </div>
                        <div class="font-medium">${{ number_format($breakdown->total_cost, 2) }}</div>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('agentoom-core::admin.projects.by_type') }}</flux:heading>
            <div class="space-y-3">
                @foreach($typesBreakdown->sortByDesc('total_cost')->take(10) as $breakdown)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            @php $toolInfo = \Agentoom\Core\Helpers\Helpers::getToolInfo($breakdown->action_type); @endphp
                            <flux:badge size="sm" variant="ghost" :title="$toolInfo['description']">{{ $toolInfo['name'] }}</flux:badge>
                        </div>
                        <div class="font-medium">${{ number_format($breakdown->total_cost, 2) }}</div>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-4">By Project</flux:heading>
            <div class="space-y-3">
                @foreach($projectsBreakdown->sortByDesc('total_cost')->take(10) as $breakdown)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-sm font-medium">
                            {{ $breakdown->project->name ?? 'No Project' }}
                        </div>
                        <div class="font-medium">${{ number_format($breakdown->total_cost, 2) }}</div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </div>

    <flux:card>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <flux:select wire:model.live="projectId" label="{{ __('agentoom-core::admin.projects.filter_project') }}" placeholder="{{ __('agentoom-core::admin.projects.all_projects') }}">
                <flux:select.option value="">{{ __('agentoom-core::admin.projects.all_projects') }}</flux:select.option>
                @foreach($projects as $project)
                    <flux:select.option value="{{ $project->id }}">{{ $project->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="agentId" label="{{ __('agentoom-core::admin.ai_credits.filter_agent') }}" placeholder="{{ __('agentoom-core::admin.ai_credits.all_agents') }}">
                <flux:select.option value="">{{ __('agentoom-core::admin.ai_credits.all_agents') }}</flux:select.option>
                @foreach($agents as $agent)
                    <flux:select.option value="{{ $agent->id }}">{{ $agent->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model.live="dateFrom" type="date" label="{{ __('agentoom-core::admin.ai_usage_logs.date_from') }}" />
            <flux:input wire:model.live="dateTo" type="date" label="{{ __('agentoom-core::admin.ai_usage_logs.date_to') }}" />
        </div>

        <flux:table :paginate="$usageLogs">
            <flux:table.columns>
                <flux:table.column>{{ __('agentoom-core::admin.projects.title') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.agents.title') }} / {{ __('agentoom-core::admin.ai_usage_logs.action_type') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.ai_usage_logs.tokens') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.ai_usage_logs.cost') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.ai_usage_logs.date') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($usageLogs as $log)
                    <flux:table.row>
                        <flux:table.cell>
                            @if($log->project)
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $log->project->name }}</span>
                            @else
                                <span class="text-zinc-400">None</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($log->agent)
                                <flux:badge size="sm" variant="outline">{{ $log->agent->name }}</flux:badge>
                            @else
                                @php $toolInfo = \Agentoom\Core\Helpers\Helpers::getToolInfo($log->action_type); @endphp
                                <flux:badge size="sm" variant="ghost" :title="$toolInfo['description']">{{ $toolInfo['name'] }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ number_format($log->total_tokens) }}</flux:table.cell>
                        <flux:table.cell class="font-medium text-zinc-900 dark:text-white">
                            {{ $log->cost === null ? '—' : '$'.number_format($log->cost, 4) }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $log->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
