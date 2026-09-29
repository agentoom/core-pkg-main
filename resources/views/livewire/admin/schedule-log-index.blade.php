<div class="space-y-6">
    @if (session('success'))
        <flux:callout variant="success" class="mb-4">{{ session('success') }}</flux:callout>
    @endif

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $schedule->name }} — {{ __('agentoom-core::admin.schedules.logs') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.schedules.subtitle') }}</flux:subheading>
        </div>
        <flux:button href="{{ route('admin.schedules.index') }}" variant="ghost" icon="arrow-left" wire:navigate>
            {{ __('agentoom-core::admin.common.back') }}
        </flux:button>
    </div>

    {{-- Filter --}}
    <flux:card>
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div class="flex gap-4 items-end">
                <flux:select wire:model.live="status" label="{{ __('agentoom-core::admin.schedules.status') }}">
                    <flux:select.option value="">{{ __('agentoom-core::admin.common.select') }}</flux:select.option>
                    <flux:select.option value="success">{{ __('agentoom-core::admin.endpoints.success') }}</flux:select.option>
                    <flux:select.option value="failed">{{ __('agentoom-core::admin.endpoints.failure') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="flex gap-2">
                @can('clearLogs', $schedule)
                    <flux:modal.trigger name="clear-logs-modal-{{ $this->getId() }}">
                        <flux:button variant="danger" icon="trash">{{ __('agentoom-core::admin.schedules.clear_logs') }}</flux:button>
                    </flux:modal.trigger>
                @endcan
            </div>
        </div>
    </flux:card>

    {{-- Modals --}}
    <flux:modal name="clear-logs-modal-{{ $this->getId() }}" class="max-w-[25rem]">
        <form wire:submit="clearLogs" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('agentoom-core::admin.schedules.clear_logs_confirmation_title') }}</flux:heading>
                <flux:subheading>{{ __('agentoom-core::admin.schedules.clear_logs_confirmation_text') }}</flux:subheading>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                                    <flux:button variant="ghost"
                        x-on:click="$el.closest('dialog').close()">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                <flux:button type="submit" variant="danger">{{ __('agentoom-core::admin.schedules.clear_logs') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Logs list --}}
    <flux:card class="space-y-4">
        <flux:table :paginate="$logs">
            <flux:table.columns>
                <flux:table.column>#</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.schedules.status') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.schedules.executed_at') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.pipelines.duration') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.schedules.output') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($logs as $log)
                    <flux:table.row :key="$log->id">
                        <flux:table.cell>
                            <span class="text-zinc-500 text-sm">#{{ $log->id }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $color = match($log->status) {
                                    'success' => 'green',
                                    'failed' => 'red',
                                    'running' => 'blue',
                                    'paused' => 'orange',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge :color="$color" inset="top bottom">{{ $log->status }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $log->started_at?->diffForHumans() ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($log->durationInSeconds() !== null)
                                {{ number_format($log->durationInSeconds(), 1) }}s
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <div class="max-w-md truncate text-xs font-mono text-zinc-500">
                                    {{ $log->log }}
                                </div>
                                @if($log->log)
                                    <flux:modal.trigger name="view-log-{{ $this->getId() }}-{{ $log->id }}">
                                        <flux:button size="xs" variant="ghost">{{ __('agentoom-core::admin.common.show') }}</flux:button>
                                    </flux:modal.trigger>

                                    <flux:modal name="view-log-{{ $this->getId() }}-{{ $log->id }}" class="max-w-4xl">
                                        <div class="space-y-4">
                                            <flux:heading size="lg">{{ __('agentoom-core::admin.schedules.logs') }} #{{ $log->id }}</flux:heading>
                                            <div class="bg-zinc-50 dark:bg-zinc-900 p-4 rounded border border-zinc-200 dark:border-zinc-800 overflow-auto max-h-[70vh]">
                                                <pre class="text-xs font-mono whitespace-pre-wrap">{{ $log->log }}</pre>
                                            </div>
                                            <div class="flex justify-end">
                                                                                                    <flux:button
                                                        x-on:click="$el.closest('dialog').close()">{{ __('agentoom-core::admin.common.close') }}</flux:button>
                                            </div>
                                        </div>
                                    </flux:modal>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-8 text-zinc-500">
                            {{ __('agentoom-core::admin.common.no_results') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
