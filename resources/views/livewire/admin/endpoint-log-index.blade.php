<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <flux:button variant="subtle" icon="chevron-left" :href="route('admin.endpoints.index')" wire:navigate />
            <div>
                <flux:heading size="xl">{{ __('agentoom-core::admin.endpoints.log_title', ['name' => $endpoint->name]) }}</flux:heading>
                <flux:subheading>{{ __('agentoom-core::admin.endpoints.log_subtitle') }}</flux:subheading>
            </div>
        </div>
    </div>

    {{-- Table card --}}
    <flux:card>
        <flux:table :paginate="$logs">
            <flux:table.columns>
                <flux:table.column>{{ __('agentoom-core::admin.endpoints.status') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.endpoints.request_data') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.endpoints.response_data') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.endpoints.error_message') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.endpoints.executed_at') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($logs as $log)
                    <flux:table.row>
                        <flux:table.cell>
                            @if ($log->status === 'success')
                                <flux:badge color="green" size="sm" icon="check-circle">
                                    {{ __('agentoom-core::admin.endpoints.success') }}
                                </flux:badge>
                            @elseif ($log->status === 'paused')
                                <flux:badge color="orange" size="sm" icon="clock">
                                    {{ __('agentoom-core::admin.endpoints.paused') }}
                                </flux:badge>
                            @else
                                <flux:badge color="red" size="sm" icon="x-circle">
                                    {{ __('agentoom-core::admin.endpoints.failure') }}
                                </flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="max-w-xs">
                            <flux:modal.trigger name="request-data-{{ $this->getId() }}-{{ $log->id }}">
                                <flux:button variant="subtle" size="sm" icon="eye">
                                    {{ __('agentoom-core::admin.common.show') }}
                                </flux:button>
                            </flux:modal.trigger>
                            <flux:modal name="request-data-{{ $this->getId() }}-{{ $log->id }}" class="max-w-2xl">
                                <div class="space-y-4">
                                    <flux:heading size="lg">{{ __('agentoom-core::admin.endpoints.request_data') }}</flux:heading>
                                    <pre class="p-4 bg-zinc-100 dark:bg-zinc-900 rounded-lg overflow-auto text-xs"><code>{{ json_encode($log->request_data, JSON_PRETTY_PRINT) }}</code></pre>
                                </div>
                            </flux:modal>
                        </flux:table.cell>

                        <flux:table.cell class="max-w-xs">
                            @if ($log->response_data)
                                <flux:modal.trigger name="response-data-{{ $this->getId() }}-{{ $log->id }}">
                                    <flux:button variant="subtle" size="sm" icon="eye">
                                        {{ __('agentoom-core::admin.common.show') }}
                                    </flux:button>
                                </flux:modal.trigger>
                                <flux:modal name="response-data-{{ $this->getId() }}-{{ $log->id }}" class="max-w-2xl">
                                    <div class="space-y-4">
                                        <flux:heading size="lg">{{ __('agentoom-core::admin.endpoints.response_data') }}</flux:heading>
                                        <pre class="p-4 bg-zinc-100 dark:bg-zinc-900 rounded-lg overflow-auto text-xs"><code>{{ json_encode($log->response_data, JSON_PRETTY_PRINT) }}</code></pre>
                                    </div>
                                </flux:modal>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="max-w-xs truncate">
                            <span class="text-red-500">{{ $log->error_message ?? '—' }}</span>
                        </flux:table.cell>

                        <flux:table.cell>
                            {{ $log->created_at->format('Y-m-d H:i:s') }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-12 text-center text-zinc-400">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon.list-bullet class="size-10 opacity-30" />
                                <span>{{ __('agentoom-core::admin.common.no_results') }}</span>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
