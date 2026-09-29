<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('agentoom-core::admin.ai_credits.title') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.ai_credits.subtitle') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            @if(auth()->user()->hasRole('superadmin'))
                <flux:modal.trigger name="topup-modal-{{ $this->getId() }}">
                    <flux:button icon="plus" variant="primary">{{ __('agentoom-core::admin.ai_credits.topup') }}</flux:button>
                </flux:modal.trigger>

                <flux:modal.trigger name="remove-modal-{{ $this->getId() }}">
                    <flux:button icon="ban" variant="danger">{{ __('agentoom-core::admin.ai_credits.remove') }}</flux:button>
                </flux:modal.trigger>
            @endif
        </div>
    </div>

    <div class="flex items-center p-4 rounded-xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                <flux:icon icon="bolt" class="text-purple-600 dark:text-purple-400 size-6" />
            </div>
            <div>
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('agentoom-core::admin.ai_credits.total_available') }}</div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-white">${{ number_format($totalCredits, 2) }}</div>
            </div>
        </div>
    </div>
    <flux:tab.group wire:model="activeTab">
        <flux:tabs variant="segmented">
            <flux:tab name="usage" :selected="$activeTab === 'usage'">{{ __('agentoom-core::admin.ai_credits.usage') }}</flux:tab>
            <flux:tab name="buy" :selected="$activeTab === 'buy'" >{{ __('agentoom-core::admin.ai_credits.buy') }}</flux:tab>
            <flux:tab name="history" :selected="$activeTab === 'history'">{{ __('agentoom-core::admin.ai_credits.history') }}</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="usage" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                <flux:card class="flex items-center gap-4">
                    <flux:icon icon="file-text" class="text-blue-600 dark:text-blue-400 size-6" />
                    <div><div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('agentoom-core::admin.ai_credits.total_requests') }}</div><div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($totalRequests) }}</div></div>
                </flux:card>
                <flux:card class="flex items-center gap-4">
                    <flux:icon icon="currency-dollar" class="text-amber-600 dark:text-amber-400 size-6" />
                    <div><div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('agentoom-core::admin.ai_usage_logs.credits_charged') }}</div><div class="text-2xl font-bold text-zinc-900 dark:text-white">${{ number_format($totalCostFiltered, 2) }}</div></div>
                </flux:card>
                <flux:card class="flex items-center gap-4">
                    <flux:icon icon="banknotes" class="text-zinc-600 dark:text-zinc-400 size-6" />
                    <div><div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('agentoom-core::admin.ai_usage_logs.provider_cost') }}</div><div class="text-2xl font-bold text-zinc-900 dark:text-white">${{ number_format($providerCostFiltered, 2) }}</div></div>
                </flux:card>
                <flux:card class="flex items-center gap-4">
                    <flux:icon icon="question-mark-circle" class="text-red-600 dark:text-red-400 size-6" />
                    <div><div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('agentoom-core::admin.ai_usage_logs.unpriced') }}</div><div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($unpricedCount) }}</div></div>
                </flux:card>
            </div>

            @if(!empty($chartData))
                <flux:card>
                    <div class="flex items-center justify-between mb-6">
                        <flux:heading size="lg">{{ __('agentoom-core::admin.ai_credits.consumption_chart') }}</flux:heading>
                    </div>

                    <flux:chart :value="$chartData" class="aspect-[4/1] w-full">
                        <flux:chart.svg gutter="20">
                            <flux:chart.axis axis="x" field="date" />
                            <flux:chart.axis axis="y" field="credits" position="right" :format="['style' => 'currency', 'currency' => 'USD', 'notation' => 'compact', 'compactDisplay' => 'short']" />
                            <flux:chart.line field="credits" class="text-blue-500 dark:text-blue-400" />
                            <flux:chart.area field="credits" class="text-blue-200/50 dark:text-blue-800/50" />
                            <flux:chart.cursor />
                        </flux:chart.svg>

                        <flux:chart.tooltip>
                            <flux:chart.tooltip.heading field="date" />
                            <flux:chart.tooltip.value label="{{ __('agentoom-core::admin.ai_credits.credits') }} ($)" field="credits" :format="['style' => 'currency', 'currency' => 'USD']" />
                        </flux:chart.tooltip>
                    </flux:chart>
                </flux:card>
            @endif

            <flux:card class="mt-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <flux:select wire:model.live="userId" label="{{ __('agentoom-core::admin.ai_credits.filter_user') }}" placeholder="{{ __('agentoom-core::admin.ai_usage_logs.all_users') }}">
                        <flux:select.option value="">{{ __('agentoom-core::admin.ai_usage_logs.all_users') }}</flux:select.option>
                        @foreach($users as $user)
                            <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
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
                        <flux:table.column>{{ __('agentoom-core::admin.ai_usage_logs.user') }}</flux:table.column>
                        <flux:table.column>{{ __('agentoom-core::admin.agents.title') }}</flux:table.column>
                        <flux:table.column>{{ __('agentoom-core::admin.ai_usage_logs.tokens') }}</flux:table.column>
                        <flux:table.column>{{ __('agentoom-core::admin.ai_usage_logs.credits_charged') }}</flux:table.column>
                        <flux:table.column>{{ __('agentoom-core::admin.ai_usage_logs.source') }}</flux:table.column>
                        <flux:table.column>{{ __('agentoom-core::admin.ai_usage_logs.date') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($usageLogs as $log)
                            <flux:table.row>
                                <flux:table.cell>{{ $log->user->name ?? __('agentoom-core::admin.ai_credits.system') }}</flux:table.cell>
                                <flux:table.cell>
                                    @if($log->agent)
                                        <flux:badge size="sm" variant="outline">{{ $log->agent->name }}</flux:badge>
                                    @else
                                        @php $toolInfo = \Agentoom\Core\Helpers\Helpers::getToolInfo($log->action_type); @endphp
                                        <flux:badge size="sm" variant="ghost" :title="$toolInfo['description']">{{ $toolInfo['name'] }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ number_format($log->total_tokens) }}</flux:table.cell>
                                <flux:table.cell>{{ $log->cost === null ? '—' : '$'.number_format($log->cost, 4) }}</flux:table.cell>
                                <flux:table.cell>{{ $log->pricing_source }}</flux:table.cell>
                                <flux:table.cell>{{ $log->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </flux:tab.panel>

        <flux:tab.panel name="buy" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                @foreach(config('ai-credits.packages') as $package)
                    <flux:card class="flex flex-col items-center text-center p-6">
                        <flux:heading size="lg">{{ $package['name'] }}</flux:heading>
                        <div class="mt-2 text-3xl font-bold text-purple-600 dark:text-purple-400">
                            ${{ number_format($package['price'], 2) }}
                            <span class="text-sm font-normal text-zinc-500 dark:text-zinc-400">Credits</span>
                        </div>
                        <flux:button
                            variant="primary"
                            class="mt-6 w-full"
                            wire:click="buyPackage('{{ $package['id'] }}')"
                            wire:loading.attr="disabled"
                        >
                            {{ __('agentoom-core::admin.ai_credits.buy') }}
                        </flux:button>
                    </flux:card>
                @endforeach
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="history" class="space-y-6">
            <flux:card class="mt-4">
                <flux:table :paginate="$topupHistory">
                    <flux:table.columns>
                        <flux:table.column>{{ __('agentoom-core::admin.ai_credits.admin') }}</flux:table.column>
                        <flux:table.column>{{ __('agentoom-core::admin.ai_credits.type') }}</flux:table.column>
                        <flux:table.column>{{ __('agentoom-core::admin.ai_credits.method') }}</flux:table.column>
                        <flux:table.column>{{ __('agentoom-core::admin.ai_credits.status') }}</flux:table.column>
                        <flux:table.column>{{ __('agentoom-core::admin.ai_credits.amount') }}</flux:table.column>
                        <flux:table.column>{{ __('agentoom-core::admin.ai_credits.date') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($topupHistory as $history)
                            <flux:table.row>
                                <flux:table.cell>{{ $history->user->name ?? __('agentoom-core::admin.ai_credits.unknown') }}</flux:table.cell>
                                <flux:table.cell>
                                    @if($history->type === 'add')
                                        <flux:badge size="sm" color="green" icon="plus">{{ __('agentoom-core::admin.ai_credits.add') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="red" icon="ban">{{ __('agentoom-core::admin.ai_credits.remove_action') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($history->payment_method === 'stripe')
                                        <flux:badge size="sm" variant="outline" color="blue">{{ __('agentoom-core::admin.ai_credits.stripe') }}</flux:badge>
                                    @elseif($history->payment_method === 'centralized')
                                        <flux:badge size="sm" variant="outline" color="indigo">Agentoom</flux:badge>
                                    @else
                                        <flux:badge size="sm" variant="ghost">{{ __('agentoom-core::admin.ai_credits.manual') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($history->status === 'completed')
                                        <flux:badge size="sm" color="green" icon="check-circle">{{ __('agentoom-core::admin.ai_credits.completed') }}</flux:badge>
                                    @elseif($history->status === 'awaiting_payment')
                                        <flux:badge size="sm" color="amber" icon="clock">{{ __('agentoom-core::admin.ai_credits.awaiting_payment') }}</flux:badge>
                                    @elseif($history->status === 'failed')
                                        <flux:badge size="sm" color="red" icon="x-circle">{{ __('agentoom-core::admin.ai_credits.failed') }}</flux:badge>
                                    @elseif($history->status === 'expired')
                                        <flux:badge size="sm" color="zinc" icon="clock">{{ __('agentoom-core::admin.ai_credits.expired') }}</flux:badge>
                                    @else
                                        -
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div>${{ number_format($history->amount, 2) }}</div>
                                    @if($history->amount_paid)
                                        <div class="text-[10px] text-zinc-400">{{ number_format($history->amount_paid, 2) }} {{ $history->currency }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $history->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </flux:tab.panel>
    </flux:tab.group>

    @if(auth()->user()->hasRole('superadmin'))
        <flux:modal name="topup-modal-{{ $this->getId() }}" class="md:w-[24rem]">
            <form wire:submit="topup" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('agentoom-core::admin.ai_credits.topup') }}</flux:heading>
                </div>

                <flux:input wire:model="topupAmount" type="number" step="0.01" label="{{ __('agentoom-core::admin.ai_credits.amount') }} ($)" />

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ __('agentoom-core::admin.ai_credits.topup') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="remove-modal-{{ $this->getId() }}" class="md:w-[24rem]">
            <form wire:submit="remove" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('agentoom-core::admin.ai_credits.remove') }}</flux:heading>
                </div>

                <flux:input wire:model="removeAmount" type="number" step="0.01" label="{{ __('agentoom-core::admin.ai_credits.amount') }} ($)" />

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="danger">{{ __('agentoom-core::admin.ai_credits.remove') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    @push('scripts')
        <script>
            document.addEventListener('initiate-purchase', event => {
                const { url, data } = event.detail[0];
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url;

                for (const key in data) {
                    if (data.hasOwnProperty(key)) {
                        const hiddenField = document.createElement('input');
                        hiddenField.type = 'hidden';
                        hiddenField.name = key;
                        hiddenField.value = data[key];
                        form.appendChild(hiddenField);
                    }
                }

                document.body.appendChild(form);
                form.submit();
            });
        </script>
    @endpush
</div>
