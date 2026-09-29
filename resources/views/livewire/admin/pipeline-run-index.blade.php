<div class="space-y-6" wire:poll.5s.non-blocking>
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $pipeline->name }} — {{ __('agentoom-core::admin.pipelines.runs') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.pipelines.runs_subtitle') }}</flux:subheading>
        </div>
        <flux:button href="{{ route('admin.pipelines.index') }}" variant="ghost" icon="arrow-left" wire:navigate>
            {{ __('agentoom-core::admin.common.back') }}
        </flux:button>
    </div>

    @if (session('success'))
        <flux:callout variant="success">{{ session('success') }}</flux:callout>
    @endif

    {{-- Run trigger --}}
    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('agentoom-core::admin.pipelines.new_run') }}</flux:heading>
        <form wire:submit="startRun" class="flex gap-3 items-end">
            <flux:field class="flex-1">
                <flux:label>{{ __('agentoom-core::admin.pipelines.run_input') }}</flux:label>
                <flux:textarea wire:model="input" id="pipeline-run-input" rows="3"
                    placeholder="{{ __('agentoom-core::admin.pipelines.run_input_placeholder') }}" />
                <flux:error name="input" />
            </flux:field>
            <flux:button type="submit" variant="primary" icon="play">
                <span class="in-data-loading:hidden">{{ __('agentoom-core::admin.pipelines.run_now') }}</span>
                <span class="hidden in-data-loading:flex items-center gap-2">
                    <flux:icon.loading variant="mini" />
                    {{ __('agentoom-core::admin.common.processing') }}
                </span>
            </flux:button>
        </form>
    </flux:card>

    {{-- Run history --}}
    <flux:card class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('agentoom-core::admin.pipelines.run_history') }}</flux:heading>

            <div class="flex gap-2">
                <flux:modal.trigger name="delete-all-logs-{{ $this->getId() }}">
                    <flux:button size="sm" variant="ghost" icon="trash">
                        {{ __('agentoom-core::admin.pipelines.delete_all_logs') }}
                    </flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        <flux:modal name="delete-all-logs-{{ $this->getId() }}" class="min-w-[22rem]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('agentoom-core::admin.pipelines.delete_all_logs_title') }}</flux:heading>
                    <flux:subheading>{{ __('agentoom-core::admin.pipelines.delete_all_logs_text') }}</flux:subheading>
                </div>
                <div class="flex gap-2 justify-end">
                                            <flux:button variant="ghost"
                            x-on:click="$el.closest('dialog').close()">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                    <flux:button wire:click="deleteAllPipelineLogs" variant="danger">
                        {{ __('agentoom-core::admin.common.delete') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>#</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.pipelines.status') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.pipelines.run_by') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.pipelines.started_at') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.pipelines.duration') }}</flux:table.column>
                <flux:table.column align="end">{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($runs as $run)
                    <flux:table.row :key="$run->id">
                        <flux:table.cell>
                            <span class="text-zinc-500 text-sm">#{{ $run->id }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $color = match($run->status) {
                                    'completed' => 'green',
                                    'failed' => 'red',
                                    'running' => 'blue',
                                    'paused' => 'orange',
                                    'pending' => 'zinc',
                                    'stopped' => 'zinc',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge :color="$color" inset="top bottom">{{ $run->status }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $run->user?->name ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $run->started_at?->diffForHumans() ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($run->durationInSeconds() !== null)
                                {{ number_format($run->durationInSeconds(), 1) }}s
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:button
                                    :href="route('admin.pipelines.runs.show', [$pipeline, $run])"
                                    size="sm" variant="ghost" icon="eye" wire:navigate />

                                @if (in_array($run->status, ['running', 'pending', 'paused']))
                                    <flux:modal.trigger name="stop-run-{{ $this->getId() }}-{{ $run->id }}">
                                        <flux:button size="sm" variant="ghost" icon="stop" />
                                    </flux:modal.trigger>
                                @endif

                                <flux:modal.trigger name="delete-run-{{ $this->getId() }}-{{ $run->id }}">
                                    <flux:button size="sm" variant="ghost" icon="trash" />
                                </flux:modal.trigger>

                                <flux:modal name="delete-run-{{ $this->getId() }}-{{ $run->id }}" class="min-w-[22rem]">
                                    <div class="space-y-6">
                                        <div>
                                            <flux:heading size="lg">{{ __('agentoom-core::admin.pipelines.delete_run_title') }}</flux:heading>
                                            <flux:subheading>{{ __('agentoom-core::admin.pipelines.delete_run_text') }}</flux:subheading>
                                        </div>
                                        <div class="flex gap-2 justify-end">
                                                                                            <flux:button variant="ghost"
                                                    x-on:click="$el.closest('dialog').close()">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                                            <flux:button wire:click="deleteRun({{ $run->id }})" variant="danger">
                                                {{ __('agentoom-core::admin.common.delete') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                </flux:modal>

                                @if (in_array($run->status, ['running', 'pending', 'paused']))
                                    <flux:modal name="stop-run-{{ $this->getId() }}-{{ $run->id }}" class="min-w-[22rem]">
                                        <div class="space-y-6">
                                            <div>
                                                <flux:heading size="lg">{{ __('agentoom-core::admin.pipelines.stop_run_title') }}</flux:heading>
                                                <flux:subheading>{{ __('agentoom-core::admin.pipelines.stop_run_text') }}</flux:subheading>
                                            </div>
                                            <div class="flex gap-2 justify-end">
                                                                                                    <flux:button variant="ghost"
                                                        x-on:click="$el.closest('dialog').close()">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                                                <flux:button wire:click="stopRun({{ $run->id }})" variant="danger">
                                                    {{ __('agentoom-core::admin.pipelines.stop') }}
                                                </flux:button>
                                            </div>
                                        </div>
                                    </flux:modal>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center py-8 text-zinc-500">
                            {{ __('agentoom-core::admin.common.no_results') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        @if ($runs->hasPages())
            <div class="mt-4">{{ $runs->links() }}</div>
        @endif
    </flux:card>
</div>
