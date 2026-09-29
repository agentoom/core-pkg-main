<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('agentoom-core::admin.skills.title') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.skills.subtitle') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('admin.skills.create')" wire:navigate>
            {{ __('agentoom-core::admin.skills.new') }}
        </flux:button>
    </div>

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

    {{-- Filters --}}
    <div class="flex flex-col md:flex-row gap-4">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('agentoom-core::admin.common.search')"
                clearable
            />
        </div>
        <div class="w-full md:w-32">
            <flux:select wire:model.live="status" :placeholder="__('agentoom-core::admin.common.status')">
                <flux:select.option value="">{{ __('agentoom-core::admin.common.all') }}</flux:select.option>
                <flux:select.option value="active">{{ __('agentoom-core::admin.common.active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('agentoom-core::admin.common.inactive') }}</flux:select.option>
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

    {{-- Table card --}}
    <flux:card>
        <flux:table :paginate="$skills">
            <flux:table.columns>
                <flux:table.column>
                    <flux:checkbox wire:model.live="selectAll" />
                </flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.id') }}</flux:table.column>
                <flux:table.column sortable>{{ __('agentoom-core::admin.skills.name') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.skills.slug') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.skills.description') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.skills.is_active') }}</flux:table.column>
                <flux:table.column align="right">{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($skills as $skill)
                    <flux:table.row wire:key="skill-{{ $skill->id }}">
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selected" :value="$skill->id" />
                        </flux:table.cell>

                        <flux:table.cell>
                            <span class="text-zinc-500 tabular-nums">{{ $skill->id }}</span>
                        </flux:table.cell>

                        <flux:table.cell class="font-medium">
                            {{ $skill->name }}
                        </flux:table.cell>

                        <flux:table.cell class="text-zinc-500 dark:text-zinc-400">
                            {{ $skill->slug }}
                        </flux:table.cell>

                        <flux:table.cell class="text-zinc-500 dark:text-zinc-400">
                            {{ $skill->description }}
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($skill->is_active)
                                <flux:badge color="green" size="sm" icon="check-circle">
                                    {{ __('agentoom-core::admin.common.active') }}
                                </flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">
                                    {{ __('agentoom-core::admin.common.inactive') }}
                                </flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="right">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button
                                    href="{{ route('admin.skills.edit', $skill) }}"
                                    icon="pencil"
                                    size="sm"
                                    wire:navigate>
                                    {{ __('agentoom-core::admin.common.edit') }}
                                </flux:button>
                                <flux:button
                                    icon="document-duplicate"
                                    size="sm"
                                    variant="subtle"
                                    wire:click="clone({{ $skill->id }})">
                                    {{ __('agentoom-core::admin.common.clone') }}
                                </flux:button>
                                <flux:button
                                    variant="danger"
                                    icon="trash"
                                    size="sm"
                                    wire:click="showDeleteModal({{ $skill->id }})">
                                    {{ __('agentoom-core::admin.common.delete') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-12 text-center text-zinc-400">
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

    {{-- Delete modal --}}
    <flux:modal wire:model="show_delete_modal" class="max-w-md">
        <div class="text-center space-y-4">
            <div class="mx-auto flex items-center justify-center size-12 rounded-full bg-red-100 dark:bg-red-900/30">
                <flux:icon.exclamation-triangle class="size-6 text-red-600 dark:text-red-400" />
            </div>

            <flux:heading size="lg">
                {{ __('agentoom-core::admin.skills.delete_confirmation_title') }}
            </flux:heading>

            <flux:subheading class="text-zinc-500">
                {{ __('agentoom-core::admin.skills.delete_confirmation_text') }}
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
