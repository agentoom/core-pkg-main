<div class="space-y-6">
    <flux:heading size="xl" level="1">{{ __('agentoom-core::admin.integrations.my_integrations') }}</flux:heading>
    <flux:subheading size="lg" class="mb-6">{{ __('agentoom-core::admin.integrations.my_integrations_subtitle') }}</flux:subheading>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($supported_services as $key => $service)
            @php
                $isEnabled = $this->isEnabled($key);
                $integration = $this->getIntegration($key);
                $isConnected = $integration && ($integration->data['status'] ?? '') === 'connected';
            @endphp

            <flux:card class="flex flex-col justify-between space-y-4">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <flux:icon :name="$service['icon']" class="text-zinc-500" />
                            <flux:heading size="lg">{{ $service['name'] }}</flux:heading>
                        </div>
                        <flux:badge :color="$isConnected ? 'green' : ($isEnabled ? 'gray' : 'red')">
                            {{ $isConnected ? __('agentoom-core::admin.integrations.connected') : ($isEnabled ? __('agentoom-core::admin.integrations.not_connected') : __('agentoom-core::admin.integrations.disabled')) }}
                        </flux:badge>
                    </div>

                    @if(! $isEnabled)
                        <p class="text-sm text-zinc-500 italic">
                            {{ __('agentoom-core::admin.integrations.not_enabled_by_admin') }}
                        </p>
                    @elseif($isConnected)
                        <div class="text-sm text-zinc-500 space-y-1">
                            <p><strong>{{ __('agentoom-core::admin.integrations.authorized_email') }}:</strong> {{ $integration->data['authorized_email'] ?? 'N/A' }}</p>
                            <p><strong>{{ __('agentoom-core::admin.integrations.expires_at') }}:</strong> {{ $integration->data['expires_at'] ?? 'N/A' }}</p>
                        </div>
                    @else
                        <p class="text-sm text-zinc-500">
                            {{ __('agentoom-core::admin.integrations.connect_to_enable', ['service' => $service['name']]) }}
                        </p>
                    @endif
                </div>

                <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    @if($isConnected)
                        <flux:button wire:click="disconnect('{{ $key }}')" variant="danger" class="w-full">
                            {{ __('agentoom-core::admin.integrations.disconnect') }}
                        </flux:button>
                    @elseif($isEnabled)
                        <flux:button wire:click="connect('{{ $key }}')" variant="primary" class="w-full">
                            {{ __('agentoom-core::admin.integrations.connect') }}
                        </flux:button>
                    @else
                        <flux:button disabled variant="filled" class="w-full">
                            {{ __('agentoom-core::admin.integrations.request_activation') }}
                        </flux:button>
                    @endif
                </div>
            </flux:card>
        @endforeach
    </div>

    {{-- Zapier --}}
    <flux:card class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('agentoom-core::admin.integrations.zapier') }}</flux:heading>
        </div>

        <div class="space-y-4 text-sm text-zinc-600 dark:text-zinc-400">
            <p>{{ __('agentoom-core::admin.integrations.zapier_description') }}</p>

            <div>
                <flux:heading size="sm" class="mb-1">{{ __('agentoom-core::admin.integrations.zapier_inbound') }}</flux:heading>
                <p>{!! __('agentoom-core::admin.integrations.zapier_inbound_description', ['url' => route('admin.endpoints.index')]) !!}</p>
                <div class="mt-2 p-2 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-md text-xs font-mono break-all select-all">
                    {{ $zapier['endpoint_base_url'] }}
                </div>
            </div>

            <div>
                <flux:heading size="sm" class="mb-1">{{ __('agentoom-core::admin.integrations.zapier_outbound') }}</flux:heading>
                <p>{!! __('agentoom-core::admin.integrations.zapier_outbound_description', ['url' => route('admin.webhook-subscriptions.index')]) !!}</p>
            </div>
        </div>
    </flux:card>

    @if (session()->has('success'))
        <div class="text-sm text-green-600 mt-4">{{ session('success') }}</div>
    @endif
</div>
