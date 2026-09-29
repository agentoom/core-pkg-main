<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('agentoom-core::admin.resources.title') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.resources.subtitle') }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <livewire:agentoom-core::admin.resource-export :resource-type-id="$resource_type_id" />
            <livewire:agentoom-core::admin.resource-import />
            <flux:button variant="primary" icon="plus" :href="route('admin.resources.create', ['resource_type_id' => $resource_type_id, 'search' => $search])" wire:navigate>
                {{ __('agentoom-core::admin.resources.new') }}
            </flux:button>
        </div>
    </div>

    <div class="flex flex-wrap gap-4 items-center">
        <div class="w-full md:w-64">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('agentoom-core::admin.common.search') }}" clearable />
        </div>
        <div class="w-full md:w-64">
            <flux:select wire:model.live="resource_type_id" placeholder="{{ __('agentoom-core::admin.resources.filter_by_type') }}">
                <flux:select.option value="">{{ __('agentoom-core::admin.common.all') }}</flux:select.option>
                @foreach ($resourceTypes as $type)
                    <flux:select.option value="{{ $type->id }}">{{ $type->name_translated }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="w-full md:w-32">
            <flux:select wire:model.live="perPage" placeholder="{{ __('agentoom-core::admin.common.per_page') }}">
                <flux:select.option value="15">15</flux:select.option>
                <flux:select.option value="25">25</flux:select.option>
                <flux:select.option value="50">50</flux:select.option>
                <flux:select.option value="100">100</flux:select.option>
            </flux:select>
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
        <flux:table :paginate="$resources">
            <flux:table.columns>
                <flux:table.column>
                    <flux:checkbox wire:model.live="selectAll" />
                </flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.id') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('agentoom-core::admin.resources.name') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'resource_type'" :direction="$sortDirection" wire:click="sort('resource_type')">{{ __('agentoom-core::admin.resources.resource_type') }}</flux:table.column>
                @role('superadmin|admin')
                <flux:table.column sortable :sorted="$sortBy === 'owner'" :direction="$sortDirection" wire:click="sort('owner')">{{ __('agentoom-core::admin.resources.owner') }}</flux:table.column>
                @endrole
                <flux:table.column sortable :sorted="$sortBy === 'description'" :direction="$sortDirection" wire:click="sort('description')">{{ __('agentoom-core::admin.resources.description') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">{{ __('agentoom-core::admin.common.created_at') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'updated_at'" :direction="$sortDirection" wire:click="sort('updated_at')">{{ __('agentoom-core::admin.common.updated_at') }}</flux:table.column>
                <flux:table.column align="right">{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($resources as $resource)
                    <flux:table.row wire:key="resource-{{ $resource->id }}">
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selected" :value="$resource->id" />
                        </flux:table.cell>

                        <flux:table.cell>
                            <span class="text-zinc-500 tabular-nums">{{ $resource->id }}</span>
                        </flux:table.cell>

                        <flux:table.cell class="font-medium">
                            {{ $resource->name }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div>{{ $resource->resourceType->name_translated }}</div>
                            @if(($resource->resourceType->name['en'] ?? '') === 'Gmail Connection')
                                <flux:badge size="sm" :color="($resource->data['status'] ?? '') === 'connected' ? 'green' : 'zinc'">
                                    {{ ($resource->data['status'] ?? '') === 'connected' ? __('agentoom-core::admin.integrations.connected') : __('agentoom-core::admin.integrations.not_connected') }}
                                </flux:badge>
                            @endif
                        </flux:table.cell>

                        @role('superadmin|admin')
                        <flux:table.cell>
                            @if($resource->user)
                                {{ $resource->user->name }}
                            @else
                                <span class="text-zinc-400 italic">{{ __('agentoom-core::admin.common.none') }}</span>
                            @endif
                        </flux:table.cell>
                        @endrole

                        <flux:table.cell class="text-zinc-500 dark:text-zinc-400 max-w-xs truncate">
                            {{ $resource->description }}
                        </flux:table.cell>

                        <flux:table.cell class="text-zinc-500 dark:text-zinc-400">
                            {{ $resource->created_at->format('Y-m-d H:i') }}
                        </flux:table.cell>

                        <flux:table.cell class="text-zinc-500 dark:text-zinc-400">
                            {{ $resource->updated_at->format('Y-m-d H:i') }}
                        </flux:table.cell>

                        <flux:table.cell align="right">
                            <div class="flex items-center justify-end gap-2">
                                @if(($resource->resourceType->name['en'] ?? '') === 'Gmail Connection')
                                    <flux:button
                                        href="{{ route('admin.oauth.redirect', ['service' => 'gmail', 'resource_id' => $resource->id]) }}"
                                        variant="outline"
                                        icon="bolt"
                                        size="sm">
                                        {{ __('agentoom-core::admin.integrations.connect') }}
                                    </flux:button>
                                @endif
                                <flux:button
                                    href="{{ route('admin.resources.edit', [$resource, 'resource_type_id' => $resource_type_id, 'search' => $search]) }}"
                                    icon="pencil"
                                    size="sm"
                                    wire:navigate>
                                    {{ __('agentoom-core::admin.common.edit') }}
                                </flux:button>
                                <flux:button
                                    icon="document-duplicate"
                                    size="sm"
                                    variant="subtle"
                                    wire:click="clone({{ $resource->id }})">
                                    {{ __('agentoom-core::admin.common.clone') }}
                                </flux:button>
                                <flux:button
                                    variant="danger"
                                    icon="trash"
                                    size="sm"
                                    wire:click="showDeleteModal({{ $resource->id }})">
                                    {{ __('agentoom-core::admin.common.delete') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="10" class="py-12 text-center text-zinc-400">
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
                {{ __('agentoom-core::admin.resources.delete_confirmation_title') }}
            </flux:heading>

            <flux:subheading class="text-zinc-500">
                {{ __('agentoom-core::admin.resources.delete_confirmation_text') }}
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

    <flux:modal wire:model="show_bulk_delete_modal" class="max-w-md">
        <div class="text-center space-y-4">
            <div class="mx-auto flex items-center justify-center size-12 rounded-full bg-red-100 dark:bg-red-900/30">
                <flux:icon.exclamation-triangle class="size-6 text-red-600 dark:text-red-400" />
            </div>

            <flux:heading size="lg">
                {{ __('agentoom-core::admin.common.delete_selected_confirmation_title') }}
            </flux:heading>

            <flux:subheading class="text-zinc-500">
                {{ __('agentoom-core::admin.common.delete_selected_confirmation_text', ['count' => $this->selectedCount]) }}
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
