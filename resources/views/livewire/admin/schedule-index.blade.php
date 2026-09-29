<div class="space-y-6">
    {{-- Header --}}
    @if(! $is_embedded)
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('agentoom-core::admin.schedules.title') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.schedules.subtitle') }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            @can('truncateLogs', \Agentoom\Core\Models\Schedule::class)
                <flux:modal.trigger name="truncate-logs-modal-{{ $this->getId() }}">
                    <flux:button variant="danger" icon="fire">{{ __('agentoom-core::admin.schedules.truncate_logs') }}</flux:button>
                </flux:modal.trigger>
            @endcan

            <div class="flex items-center gap-4">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('agentoom-core::admin.common.search') }}..." icon="magnifying-glass" size="sm" />
                <div class="w-32">
                    <flux:select wire:model.live="perPage" placeholder="{{ __('agentoom-core::admin.common.per_page') }}" size="sm">
                        <flux:select.option value="15">15</flux:select.option>
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                        <flux:select.option value="100">100</flux:select.option>
                    </flux:select>
                </div>
                <flux:button variant="primary" icon="plus" size="sm" :href="route('admin.schedules.create')" wire:navigate>
                    {{ __('agentoom-core::admin.schedules.new') }}
                </flux:button>
            </div>
        </div>
    </div>
    @else
    <div class="flex justify-end">
        <flux:button variant="primary" size="sm" icon="plus" :href="route('admin.schedules.create', ['project_id' => $project_id])" wire:navigate>
            {{ __('agentoom-core::admin.schedules.new') }}
        </flux:button>
    </div>
    @endif

    {{-- Success flash --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    {{-- Bulk Actions --}}
    @if ($this->isAnySelected)
        <div class="flex items-center gap-4 p-4 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
            <span class="text-sm font-medium">{{ __('agentoom-core::admin.common.selected_count', ['count' => $this->selectedCount]) }}</span>

            <flux:separator vertical />

            <div class="flex gap-2">
                <flux:button size="sm" icon="trash" variant="danger" wire:click="bulkDelete">
                    {{ __('agentoom-core::admin.common.delete_selected') }}
                </flux:button>
                <flux:button size="sm" icon="arrow-down-tray" variant="subtle" wire:click="bulkExport">
                    {{ __('agentoom-core::admin.common.export_selected') }}
                </flux:button>
                <flux:button size="sm" icon="document-duplicate" variant="subtle" wire:click="bulkClone">
                    {{ __('agentoom-core::admin.common.clone_selected') }}
                </flux:button>
            </div>

            <flux:spacer />

            <flux:button size="sm" variant="ghost" wire:click="clearSelection">
                {{ __('agentoom-core::admin.common.clear_selection') }}
            </flux:button>
        </div>
    @endif

    {{-- Table card --}}
    <flux:card>
        <flux:table :paginate="$schedules">
            <flux:table.columns>
                <flux:table.column>
                    <flux:checkbox wire:model.live="selectAll" />
                </flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.id') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.schedules.name') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.schedules.cron') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.schedules.target') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.schedules.active') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.schedules.owner') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.schedules.last_execution') }}</flux:table.column>
                <flux:table.column align="right">{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($schedules as $schedule)
                    <flux:table.row>
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selected" :value="$schedule->id" />
                        </flux:table.cell>

                        <flux:table.cell>
                            <span class="text-zinc-500 tabular-nums">{{ $schedule->id }}</span>
                        </flux:table.cell>

                        <flux:table.cell class="font-medium">
                            {{ $schedule->name }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge variant="outline" size="sm" icon="clock">
                                {{ $schedule->toCronExpression() }}
                            </flux:badge>
                            @if($schedule->timezone)
                                <span class="ml-1 text-xs text-zinc-400">({{ $schedule->timezone }})</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @php
                                $target = null;
                                try {
                                    $target = $schedule->resolveModel();
                                } catch (\Throwable $e) {}
                            @endphp
                            @if($target)
                                <div class="flex items-center gap-2">
                                    @if($schedule->model === \Agentoom\Core\Models\Agent::class)
                                        <flux:icon.briefcase class="size-4 text-zinc-400" />
                                        <span>{{ $target->name }}</span>
                                        <flux:badge size="sm" color="blue">{{ __('agentoom-core::admin.schedules.agent') }}</flux:badge>
                                    @else
                                        <flux:icon.queue-list class="size-4 text-zinc-400" />
                                        <span>{{ $target->name }}</span>
                                        <flux:badge size="sm" color="purple">{{ __('agentoom-core::admin.schedules.pipeline') }}</flux:badge>
                                    @endif
                                </div>
                            @elseif($schedule->method)
                                <div class="flex items-center gap-2">
                                    <flux:icon.command-line class="size-4 text-zinc-400" />
                                    <span class="text-xs font-mono text-zinc-500">{{ class_basename($schedule->model) }}@</span><span class="text-xs font-mono">{{ $schedule->method }}</span>
                                    <flux:badge size="sm" color="zinc" variant="outline">{{ __('agentoom-core::admin.schedules.custom_method') }}</flux:badge>
                                </div>
                            @else
                                <span class="text-red-500 text-xs">{{ __('agentoom-core::admin.schedules.target_not_found') }}</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:switch wire:click="toggleActive({{ $schedule->id }})" :checked="$schedule->active" />
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($schedule->user)
                                <div class="flex items-center gap-1.5 text-xs text-zinc-500">
                                    <flux:icon.user class="size-3.5" />
                                    <span>{{ $schedule->user->name }}</span>
                                </div>
                            @elseif($schedule->role)
                                <div class="flex items-center gap-1.5 text-xs text-zinc-500">
                                    <flux:icon.shield-check class="size-3.5" />
                                    <span>{{ $schedule->role->name }}</span>
                                </div>
                            @else
                                <div class="flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400">
                                    <flux:icon.globe-alt class="size-3.5" />
                                    <span>{{ __('agentoom-core::admin.schedules.public_anyone') }}</span>
                                </div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($schedule->last_execution)
                                <span class="text-sm">{{ $schedule->last_execution->diffForHumans() }}</span>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="right">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button
                                    href="{{ route('admin.schedules.logs', $schedule) }}"
                                    icon="list-bullet"
                                    size="sm"
                                    wire:navigate>
                                    {{ __('agentoom-core::admin.schedules.logs') }}
                                </flux:button>
                                <flux:button
                                    href="{{ route('admin.schedules.edit', $schedule) }}"
                                    icon="pencil"
                                    size="sm"
                                    wire:navigate>
                                    {{ __('agentoom-core::admin.common.edit') }}
                                </flux:button>
                                <flux:button
                                    variant="ghost"
                                    icon="document-duplicate"
                                    size="sm"
                                    wire:click="clone({{ $schedule->id }})">
                                    {{ __('agentoom-core::admin.common.clone') }}
                                </flux:button>
                                <flux:button
                                    variant="danger"
                                    icon="trash"
                                    size="sm"
                                    wire:click="showDeleteModal({{ $schedule->id }})">
                                    {{ __('agentoom-core::admin.common.delete') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9" class="py-12 text-center text-zinc-400">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon.clock class="size-10 opacity-30" />
                                <span>{{ __('agentoom-core::admin.common.no_results') }}</span>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Delete modal --}}
    <flux:modal wire:model="show_delete_modal" class="max-w-md">
        <div class="text-center space-y-4">
            <div class="mx-auto flex items-center justify-center size-12 rounded-full bg-red-100 dark:bg-red-900/30">
                <flux:icon.exclamation-triangle class="size-6 text-red-600 dark:text-red-400" />
            </div>

            <flux:heading size="lg">
                {{ __('agentoom-core::admin.schedules.delete_confirmation_title') }}
            </flux:heading>

            <flux:subheading class="text-zinc-500">
                {{ __('agentoom-core::admin.schedules.delete_confirmation_text') }}
            </flux:subheading>

            <div class="flex gap-3 justify-center pt-2">
                <flux:button wire:click="$set('show_delete_modal', false)">
                    {{ __('agentoom-core::admin.common.cancel') }}
                </flux:button>
                <flux:button variant="danger" icon="trash" wire:click="delete">
                    {{ __('agentoom-core::admin.common.delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    @can('truncateLogs', \Agentoom\Core\Models\Schedule::class)
        <flux:modal name="truncate-logs-modal-{{ $this->getId() }}" class="max-w-[25rem]">
            <form wire:submit="truncateLogs" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('agentoom-core::admin.schedules.truncate_logs_confirmation_title') }}</flux:heading>
                    <flux:subheading>{{ __('agentoom-core::admin.schedules.truncate_logs_confirmation_text') }}</flux:subheading>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                                            <flux:button variant="ghost"
                            x-on:click="$el.closest('dialog').close()">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                    <flux:button type="submit" variant="danger">{{ __('agentoom-core::admin.schedules.truncate_logs') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan

    <flux:modal wire:model="show_bulk_delete_modal" class="max-w-md">
        <div class="text-center space-y-4">
            <div class="mx-auto flex items-center justify-center size-12 rounded-full bg-red-100 dark:bg-red-900/30">
                <flux:icon.exclamation-triangle class="size-6 text-red-600 dark:text-red-400" />
            </div>

            <flux:heading size="lg">
                {{ __('agentoom-core::admin.common.delete_selected_confirmation_title', ['count' => $this->selectedCount]) }}
            </flux:heading>

            <flux:subheading class="text-zinc-500">
                {{ __('agentoom-core::admin.common.delete_selected_confirmation_text') }}
            </flux:subheading>

            <div class="flex gap-3 justify-center pt-2">
                <flux:button wire:click="$set('show_bulk_delete_modal', false)">
                    {{ __('agentoom-core::admin.common.cancel') }}
                </flux:button>
                <flux:button variant="danger" icon="trash" wire:click="confirmBulkDelete">
                    {{ __('agentoom-core::admin.common.delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
