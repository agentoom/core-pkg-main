<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Credit Card -->
        <flux:card href="{{ route('admin.ai-credits.index') }}">
            <flux:heading size="lg" class="mb-4">{{ __('agentoom-core::admin.ai_credits.title') }}</flux:heading>
            <div class="flex flex-col items-center justify-center py-2">
                <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-full mb-3">
                    <flux:icon icon="bolt" class="text-purple-600 dark:text-purple-400 size-8" />
                </div>
                <flux:text class="text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($totalCredits) }}</flux:text>
                <flux:text size="sm" class="text-zinc-500">{{ __('agentoom-core::admin.ai_credits.total_available') }}</flux:text>
            </div>
        </flux:card>

        <!-- Welcome Card -->
        <flux:card class="md:col-span-1 lg:col-span-3 flex flex-col justify-center">
             <flux:heading size="lg">{{ __('agentoom-core::admin.common.welcome_back') }}</flux:heading>
             <flux:text class="mt-2">
                {{ __('agentoom-core::admin.common.logged_in_as', ['email' => auth()->user()->email]) }}
             </flux:text>
             <div class="mt-4 flex flex-wrap gap-2">
                <flux:button variant="ghost" size="sm" icon="chat-bubble-left-right" href="{{ route('admin.chat') }}">{{ __('agentoom-core::admin.chat') }}</flux:button>
                <flux:button variant="ghost" size="sm" icon="user-circle" href="{{ route('admin.agents.index') }}">{{ __('agentoom-core::admin.dashboard_view.resources.agents') }}</flux:button>
                @if($formsRoute = $this->getResourceRoute('forms'))
                    <flux:button variant="ghost" size="sm" icon="document-text" href="{{ $formsRoute }}">{{ __('agentoom-core::admin.dashboard_view.resources.forms') }}</flux:button>
                @endif
                <flux:button variant="ghost" size="sm" icon="folder" href="{{ route('admin.projects.index') }}">{{ __('agentoom-core::admin.dashboard_view.resources.projects') }}</flux:button>
{{--                <flux:button variant="ghost" size="sm" icon="key" href="{{ route('admin.credentials.index') }}">{{ __('agentoom-core::admin.dashboard_view.resources.credentials') }}</flux:button>--}}
             </div>
        </flux:card>
    </div>

    <!-- Resource Usage Grid -->
    <div>
        <flux:heading size="lg" class="mb-4">{{ __('agentoom-core::admin.dashboard_view.resource_usage') }}</flux:heading>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($usageSummary as $resource => $data)
                <flux:card class="space-y-3" href="{{ $this->getResourceRoute($resource) }}">
                    <div class="flex items-center justify-between">
                        <flux:text class="font-medium">{{ __('agentoom-core::admin.dashboard_view.resources.' . $resource) }}</flux:text>
                        <flux:icon icon="{{ $this->getResourceIcon($resource) }}" class="text-zinc-400 size-5" />
                    </div>

                    <div class="flex items-end gap-1">
                        <span class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $data['used'] }}</span>
                        <span class="text-sm text-zinc-500 pb-1">/ {{ $data['total'] ?? __('agentoom-core::admin.dashboard_view.unlimited') }}</span>
                    </div>

                    @if($data['total'])
                        @php
                            $percentage = min(100, ($data['used'] / $data['total']) * 100);
                            $colorClass = $percentage > 90 ? 'bg-red-500' : ($percentage > 70 ? 'bg-amber-500' : 'bg-emerald-500');
                        @endphp
                        <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-1.5 overflow-hidden">
                            <div class="{{ $colorClass }} h-full transition-all duration-500" style="width: {{ $percentage }}%"></div>
                        </div>
                    @else
                         <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-1.5 overflow-hidden">
                            <div class="bg-blue-500 h-full w-full opacity-30"></div>
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </div>
    </div>

    <!-- Credit Consumption Graph -->
    <flux:card>
        <div class="flex items-center justify-between mb-6">
            <flux:heading size="lg">{{ __('agentoom-core::admin.ai_credits.consumption_chart') }}</flux:heading>
            <flux:button variant="ghost" size="sm" icon="link" href="{{ route('admin.ai-credits.index') }}">
                {{ __('agentoom-core::admin.dashboard_view.view_all_credits') }}
            </flux:button>
        </div>

        @if(!empty($chartData))
            <flux:chart :value="$chartData" class="aspect-[4/1] w-full">
                <flux:chart.svg gutter="20">
                    <flux:chart.axis axis="x" field="date" />
                    <flux:chart.axis axis="y" field="tokens" position="right" :format="['notation' => 'compact', 'compactDisplay' => 'short', 'maximumFractionDigits' => 1]" />
                    <flux:chart.line field="tokens" class="text-purple-500 dark:text-purple-400" />
                    <flux:chart.area field="tokens" class="text-purple-200/50 dark:text-purple-800/50" />
                    <flux:chart.cursor />
                </flux:chart.svg>

                <flux:chart.tooltip>
                    <flux:chart.tooltip.heading field="date" />
                    <flux:chart.tooltip.value label="{{ __('agentoom-core::admin.ai_usage_logs.tokens') }}" field="tokens" />
                </flux:chart.tooltip>
            </flux:chart>
        @else
            <div class="flex items-center justify-center h-48 border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-lg">
                <flux:text class="text-zinc-500">{{ __('agentoom-core::admin.common.no_data_available') }}</flux:text>
            </div>
        @endif
    </flux:card>
</div>
