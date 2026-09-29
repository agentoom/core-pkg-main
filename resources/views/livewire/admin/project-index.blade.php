<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('agentoom-core::admin.projects.title') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.projects.subtitle') }}</flux:subheading>
        </div>

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
            <flux:button icon="plus" variant="primary" size="sm" :href="route('admin.projects.create')">{{ __('agentoom-core::admin.projects.new') }}</flux:button>
        </div>
    </div>

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

    <flux:card>
        <flux:table :paginate="$projects">
            <flux:table.columns>
                <flux:table.column>
                    <flux:checkbox wire:model.live="selectAll" />
                </flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.id') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.projects.name') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.projects.budget_limit') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.projects.total_spent') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.projects.is_active') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($projects as $project)
                    <flux:table.row wire:key="project-{{ $project->id }}">
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selected" :value="$project->id" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="text-zinc-500 tabular-nums">{{ $project->id }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $project->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $project->user->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $project->budget_limit ? '$' . number_format($project->budget_limit, 2) : __('agentoom-core::admin.common.none') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="{{ $project->hasExceededBudget() ? 'text-red-600 font-bold' : '' }}">
                                ${{ number_format($project->total_spent, 2) }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$project->is_active ? 'green' : 'zinc'">
                                {{ $project->is_active ? __('agentoom-core::admin.common.active') : __('agentoom-core::admin.common.inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:button size="sm" variant="ghost" icon="book-open" :href="route('admin.projects.knowledge-base', $project->id)" />
                                <flux:button size="sm" variant="ghost" icon="pencil" :href="route('admin.projects.edit', $project->id)" />
                                <flux:button size="sm" variant="ghost" icon="document-duplicate" wire:click="clone({{ $project->id }})" />
                                <flux:modal.trigger name="delete-modal-{{ $this->getId() }}-{{ $project->id }}">
                                    <flux:button size="sm" variant="ghost" icon="trash" color="red" />
                                </flux:modal.trigger>

                                <flux:modal name="delete-modal-{{ $this->getId() }}-{{ $project->id }}" class="md:w-[24rem]">
                                    <div class="space-y-6">
                                        <div>
                                            <flux:heading size="lg">{{ __('agentoom-core::admin.projects.delete') }}</flux:heading>
                                            <flux:subheading>Are you sure you want to delete this project? All associated logs will lose their project association.</flux:subheading>
                                        </div>

                                        <div class="flex gap-2">
                                            <flux:spacer />
                                                                                            <flux:button variant="ghost"
                                                    x-on:click="$el.closest('dialog').close()">Cancel</flux:button>
                                            <flux:button variant="danger" wire:click="delete({{ $project->id }})">Delete</flux:button>
                                        </div>
                                    </div>
                                </flux:modal>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

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
