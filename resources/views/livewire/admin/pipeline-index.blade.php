<div class="space-y-6">
    @if(! $is_embedded)
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div>
                <flux:heading size="xl">{{ __('agentoom-core::admin.pipelines.title') }}</flux:heading>
                <flux:subheading>{{ __('agentoom-core::admin.pipelines.subtitle') }}</flux:subheading>
            </div>

            <x-agentoom-core::resource-usage resource="pipelines" />
        </div>

        <div class="flex items-center gap-2">
            @role('superadmin')
                <flux:modal.trigger name="truncate-all-logs-{{ $this->getId() }}">
                    <flux:button variant="danger" icon="fire">
                        {{ __('agentoom-core::admin.pipelines.truncate_all_logs') }}
                    </flux:button>
                </flux:modal.trigger>
            @endrole

            @can('pipelines.create')
                <flux:button href="{{ route('admin.pipelines.create') }}" variant="primary" icon="plus" :disabled="app(\Agentoom\Core\Services\ResourceLimitManager::class)->isLimitReached('pipelines')">
                    {{ __('agentoom-core::admin.pipelines.new') }}
                </flux:button>
            @endcan
        </div>
    </div>
    @else
    <div class="flex justify-end">
        @can('pipelines.create')
            <flux:button href="{{ route('admin.pipelines.create', ['project_id' => $project_id]) }}" variant="primary" size="sm" icon="plus" :disabled="app(\Agentoom\Core\Services\ResourceLimitManager::class)->isLimitReached('pipelines')">
                {{ __('agentoom-core::admin.pipelines.new') }}
            </flux:button>
        @endcan
    </div>
    @endif

    @if (session('success'))
        <flux:callout variant="success">{{ session('success') }}</flux:callout>
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

    <flux:card class="space-y-4">
        <div class="flex gap-4">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('agentoom-core::admin.common.search') }}"
                    icon="magnifying-glass" clearable />
            </div>
            <div class="w-32">
                <flux:select wire:model.live="perPage" placeholder="{{ __('agentoom-core::admin.common.per_page') }}">
                    <flux:select.option value="15">15</flux:select.option>
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                    <flux:select.option value="100">100</flux:select.option>
                </flux:select>
            </div>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>
                    <flux:checkbox wire:model.live="selectAll" />
                </flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.id') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.pipelines.name') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.pipelines.steps') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.pipelines.runs') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.pipelines.is_active') }}</flux:table.column>
                <flux:table.column align="end">{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($pipelines as $pipeline)
                    <flux:table.row wire:key="pipeline-{{ $pipeline->id }}">
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selected" :value="$pipeline->id" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="text-zinc-500 tabular-nums">{{ $pipeline->id }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div>
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $pipeline->name }}</span>
                                @if ($pipeline->description)
                                    <p class="text-xs text-zinc-500 mt-0.5 truncate max-w-xs">{{ $pipeline->description }}</p>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge variant="outline">{{ $pipeline->steps_count }} {{ __('agentoom-core::admin.pipelines.steps') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge variant="outline">{{ $pipeline->runs_count }} {{ __('agentoom-core::admin.pipelines.runs') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @role('superadmin')
                                <flux:switch wire:click="toggleActive({{ $pipeline->id }})"
                                    :checked="$pipeline->is_active" />
                            @else
                                <flux:badge :color="$pipeline->is_active ? 'green' : 'zinc'">{{ $pipeline->is_active ? __('agentoom-core::admin.common.active') : __('agentoom-core::admin.common.inactive') }}</flux:badge>
                            @endrole
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:button :href="route('admin.pipelines.runs', $pipeline)"
                                    size="sm" variant="ghost" icon="play" wire:navigate />

                                @role('superadmin')
                                    <flux:button :href="route('admin.pipelines.edit', $pipeline)"
                                        size="sm" variant="ghost" icon="pencil" />

                                    <flux:button
                                        variant="ghost"
                                        icon="document-duplicate"
                                        size="sm"
                                        wire:click="clone({{ $pipeline->id }})" />

                                    <flux:modal.trigger name="delete-pipeline-{{ $this->getId() }}-{{ $pipeline->id }}">
                                        <flux:button size="sm" variant="ghost" icon="trash" />
                                    </flux:modal.trigger>

                                    <flux:modal name="delete-pipeline-{{ $this->getId() }}-{{ $pipeline->id }}" class="min-w-[22rem]">
                                        <div class="space-y-6">
                                            <div>
                                                <flux:heading size="lg">{{ __('agentoom-core::admin.pipelines.delete_confirmation_title') }}</flux:heading>
                                                <flux:subheading>{{ __('agentoom-core::admin.pipelines.delete_confirmation_text') }}</flux:subheading>
                                            </div>
                                            <div class="flex gap-2 justify-end">
                                                                                                    <flux:button variant="ghost"
                                                        x-on:click="$el.closest('dialog').close()">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                                                <flux:button wire:click="delete({{ $pipeline->id }})" variant="danger">
                                                    {{ __('agentoom-core::admin.common.delete') }}
                                                </flux:button>
                                            </div>
                                        </div>
                                    </flux:modal>
                                @endrole
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center py-8 text-zinc-500">
                            {{ __('agentoom-core::admin.common.no_results') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        @if ($pipelines->hasPages())
            <div class="mt-4">{{ $pipelines->links() }}</div>
        @endif
    </flux:card>

    @role('superadmin')
        <flux:modal name="truncate-all-logs-{{ $this->getId() }}" class="min-w-[22rem]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('agentoom-core::admin.pipelines.truncate_all_logs_title') }}</flux:heading>
                    <flux:subheading>{{ __('agentoom-core::admin.pipelines.truncate_all_logs_text') }}</flux:subheading>
                </div>
                <div class="flex gap-2 justify-end">
                                            <flux:button variant="ghost"
                            x-on:click="$el.closest('dialog').close()">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                    <flux:button wire:click="truncateAllPipelineLogs" variant="danger">
                        {{ __('agentoom-core::admin.pipelines.truncate_all_logs') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endrole

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
