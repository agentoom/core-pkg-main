<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div>
                <flux:heading size="xl">{{ __('agentoom-core::admin.resource_types.title') }}</flux:heading>
                <flux:subheading>{{ __('agentoom-core::admin.resource_types.subtitle') }}</flux:subheading>
            </div>

            <x-agentoom-core::resource-usage resource="resource_types" />
        </div>

        <div class="flex items-center gap-4">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('agentoom-core::admin.common.search') }}..." icon="magnifying-glass" size="sm" />
            <flux:button variant="primary" icon="plus" :href="route('admin.resource-types.create')" wire:navigate :disabled="app(\Agentoom\Core\Services\ResourceLimitManager::class)->isLimitReached('resource_types')">
                {{ __('agentoom-core::admin.resource_types.new') }}
            </flux:button>
        </div>
    </div>

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

    <flux:card>
        <flux:table :paginate="$resourceTypes">
            <flux:table.columns>
                <flux:table.column>
                    <flux:checkbox wire:model.live="selectAll" />
                </flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.id') }}</flux:table.column>
                <flux:table.column sortable>{{ __('agentoom-core::admin.resource_types.name') }}</flux:table.column>
                <flux:table.column>{{ __('Group') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.resource_types.description') }}</flux:table.column>
                <flux:table.column align="right">{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($resourceTypes as $type)
                    <flux:table.row>
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selected" :value="$type->id" />
                        </flux:table.cell>

                        <flux:table.cell>
                            <span class="text-zinc-500 tabular-nums">{{ $type->id }}</span>
                        </flux:table.cell>

                        <flux:table.cell class="font-medium">
                            {{ $type->name_translated }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" :icon="$type->resourceGroup?->icon ?: 'cube'">{{ $type->resourceGroup?->name ?? 'No Group' }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell class="text-zinc-500 dark:text-zinc-400 max-w-xs truncate">
                            {{ $type->description }}
                        </flux:table.cell>

                        <flux:table.cell align="right">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button
                                    href="{{ route('admin.resource-types.edit', $type) }}"
                                    icon="pencil"
                                    size="sm"
                                    wire:navigate>
                                    {{ __('agentoom-core::admin.common.edit') }}
                                </flux:button>
                                <flux:button
                                    variant="subtle"
                                    icon="document-duplicate"
                                    size="sm"
                                    wire:click="clone({{ $type->id }})">
                                    {{ __('agentoom-core::admin.common.clone') }}
                                </flux:button>
                                <flux:button
                                    variant="danger"
                                    icon="trash"
                                    size="sm"
                                    wire:click="showDeleteModal({{ $type->id }})">
                                    {{ __('agentoom-core::admin.common.delete') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-12 text-center text-zinc-400">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon.bolt class="size-10 opacity-30" />
                                <span>{{ __('agentoom-core::admin.common.no_results') }}</span>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="show_delete_modal" class="max-w-md">
        <div class="text-center space-y-4">
            <div class="mx-auto flex items-center justify-center size-12 rounded-full bg-red-100 dark:bg-red-900/30">
                <flux:icon.exclamation-triangle class="size-6 text-red-600 dark:text-red-400" />
            </div>

            <flux:heading size="lg">
                {{ __('agentoom-core::admin.resource_types.delete_confirmation_title') }}
            </flux:heading>

            <flux:subheading class="text-zinc-500">
                {{ __('agentoom-core::admin.resource_types.delete_confirmation_text') }}
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
</div>
