<div class="space-y-6">
    {{-- Header --}}
    @if(! $is_embedded)
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div>
                <flux:heading size="xl">{{ __('agentoom-core::admin.endpoints.title') }}</flux:heading>
                <flux:subheading>{{ __('agentoom-core::admin.endpoints.subtitle') }}</flux:subheading>
            </div>

            <x-agentoom-core::resource-usage resource="endpoints" />
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
            <flux:button variant="primary" icon="plus" size="sm" :href="route('admin.endpoints.create')" wire:navigate :disabled="app(\Agentoom\Core\Services\ResourceLimitManager::class)->isLimitReached('endpoints')">
                {{ __('agentoom-core::admin.endpoints.new') }}
            </flux:button>
        </div>
    </div>
    @else
    <div class="flex justify-end">
        <flux:button variant="primary" size="sm" icon="plus" :href="route('admin.endpoints.create', ['project_id' => $project_id])" wire:navigate :disabled="app(\Agentoom\Core\Services\ResourceLimitManager::class)->isLimitReached('endpoints')">
            {{ __('agentoom-core::admin.endpoints.new') }}
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
        <flux:table :paginate="$endpoints">
            <flux:table.columns>
                <flux:table.column>
                    <flux:checkbox wire:model.live="selectAll" />
                </flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.id') }}</flux:table.column>
                <flux:table.column sortable>{{ __('agentoom-core::admin.endpoints.name') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.endpoints.target') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.endpoints.url') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.endpoints.is_active') }}</flux:table.column>
                <flux:table.column align="right">{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($endpoints as $endpoint)
                    <flux:table.row wire:key="endpoint-{{ $endpoint->id }}">
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selected" :value="$endpoint->id" />
                        </flux:table.cell>

                        <flux:table.cell>
                            <span class="text-zinc-500 tabular-nums">{{ $endpoint->id }}</span>
                        </flux:table.cell>

                        <flux:table.cell class="font-medium">
                            {{ $endpoint->name }}
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($endpoint->agent_id)
                                <div class="flex items-center gap-2">
                                    <flux:icon.briefcase class="size-4 opacity-50" />
                                    <span>{{ $endpoint->agent->name }}</span>
                                </div>
                            @elseif ($endpoint->pipeline_id)
                                <div class="flex items-center gap-2">
                                    <flux:icon.queue-list class="size-4 opacity-50" />
                                    <span>{{ $endpoint->pipeline->name }}</span>
                                </div>
                            @else
                                <span class="text-zinc-400 italic">{{ __('agentoom-core::admin.common.none') }}</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex items-center gap-1.5">
                                <code class="text-xs bg-zinc-100 dark:bg-zinc-800 p-1 rounded">{{ url('/e/'.$endpoint->slug) }}</code>
                                <button
                                    x-data
                                    x-on:click="navigator.clipboard.writeText('{{ url('/e/'.$endpoint->slug) }}'); $el.nextElementSibling.classList.remove('hidden'); setTimeout(() => $el.nextElementSibling.classList.add('hidden'), 1500)"
                                    class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors flex-shrink-0 cursor-pointer"
                                    title="{{ __('agentoom-core::admin.common.copy') }}"
                                >
                                    <flux:icon.clipboard-document class="size-3.5" />
                                </button>
                                <span class="hidden text-xs text-green-600 dark:text-green-400 whitespace-nowrap">{{ __('agentoom-core::admin.common.copied') }}</span>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($endpoint->is_active)
                                <flux:badge size="sm" icon="check-circle" color="green">
                                    {{ __('agentoom-core::admin.common.active') }}
                                </flux:badge>
                            @else
                                <flux:badge color="red" size="sm" icon="x-circle">
                                    {{ __('agentoom-core::admin.common.inactive') }}
                                </flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="right">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button
                                    href="{{ route('admin.endpoints.logs', $endpoint) }}"
                                    icon="list-bullet"
                                    size="sm"
                                    variant="subtle"
                                    wire:navigate>
                                    {{ __('agentoom-core::admin.endpoints.logs') }}
                                </flux:button>
                                <flux:button
                                    href="{{ route('admin.endpoints.edit', $endpoint) }}"
                                    icon="pencil"
                                    size="sm"
                                    wire:navigate>
                                    {{ __('agentoom-core::admin.common.edit') }}
                                </flux:button>
                                <flux:button
                                    variant="ghost"
                                    icon="document-duplicate"
                                    size="sm"
                                    wire:click="clone({{ $endpoint->id }})">
                                    {{ __('agentoom-core::admin.common.clone') }}
                                </flux:button>
                                <flux:button
                                    variant="danger"
                                    icon="trash"
                                    size="sm"
                                    wire:click="showDeleteModal({{ $endpoint->id }})">
                                    {{ __('agentoom-core::admin.common.delete') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-12 text-center text-zinc-400">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon.server-stack class="size-10 opacity-30" />
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
                {{ __('agentoom-core::admin.endpoints.delete_confirmation_title') }}
            </flux:heading>

            <flux:subheading class="text-zinc-500">
                {{ __('agentoom-core::admin.endpoints.delete_confirmation_text') }}
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
