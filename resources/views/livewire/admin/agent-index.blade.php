<div class="space-y-6">
    {{-- Header --}}
    @if(! $is_embedded)
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div>
                <flux:heading size="xl">{{ __('agentoom-core::admin.agents.title') }}</flux:heading>
                <flux:subheading>{{ __('agentoom-core::admin.agents.subtitle') }}</flux:subheading>
            </div>

            <x-agentoom-core::resource-usage resource="agents" />
        </div>

        <flux:button variant="primary" icon="plus" :href="route('admin.agents.create')" wire:navigate :disabled="app(\Agentoom\Core\Services\ResourceLimitManager::class)->isLimitReached('agents')">
            {{ __('agentoom-core::admin.agents.new') }}
        </flux:button>
    </div>
    @else
    <div class="flex justify-end">
        <flux:button variant="primary" size="sm" icon="plus" :href="route('admin.agents.create', ['project_id' => $project_id])" wire:navigate :disabled="app(\Agentoom\Core\Services\ResourceLimitManager::class)->isLimitReached('agents')">
            {{ __('agentoom-core::admin.agents.new') }}
        </flux:button>
    </div>
    @endif

    {{-- Filters --}}
    <flux:card class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-center">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('agentoom-core::admin.agents.search')" />

            @if(! $is_embedded)
            <flux:select wire:model.live="project_id">
                <flux:select.option value="">{{ __('agentoom-core::admin.agents.all_projects') }}</flux:select.option>
                @foreach($projects as $project)
                    <flux:select.option value="{{ $project->id }}">{{ $project->name }}</flux:select.option>
                @endforeach
            </flux:select>
            @endif

            <div class="flex items-center px-2">
                <flux:checkbox wire:model.live="show_system" :label="__('agentoom-core::admin.agents.show_system_agents')" />
            </div>

            <flux:select wire:model.live="perPage" placeholder="{{ __('agentoom-core::admin.common.per_page') }}">
                <flux:select.option value="15">15</flux:select.option>
                <flux:select.option value="25">25</flux:select.option>
                <flux:select.option value="50">50</flux:select.option>
                <flux:select.option value="100">100</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

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
        <flux:table :paginate="$agents">
            <flux:table.columns>
                <flux:table.column>
                    <flux:checkbox wire:model.live="selectAll" />
                </flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.id') }}</flux:table.column>
                <flux:table.column sortable>{{ __('agentoom-core::admin.agents.name') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.agents.description') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.agents.model') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.agents.is_active') }}</flux:table.column>
                <flux:table.column align="right">{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($agents as $agent)
                    <flux:table.row wire:key="agent-{{ $agent->id }}">
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selected" :value="$agent->id" />
                        </flux:table.cell>

                        <flux:table.cell>
                            <span class="text-zinc-500 tabular-nums">{{ $agent->id }}</span>
                        </flux:table.cell>

                        {{-- Name + description on mobile --}}
                        <flux:table.cell class="font-medium">
                            {{ $agent->name }}
                        </flux:table.cell>

                        <flux:table.cell class="text-zinc-500 dark:text-zinc-400 max-w-xs truncate">
                            {{ $agent->description ?? '—' }}
                        </flux:table.cell>

                        <flux:table.cell>
                            @if (!empty($agent->config['model']))
                                <flux:badge variant="outline" size="sm" icon="cpu-chip">
                                    {{ $agent->config['model'] }}
                                </flux:badge>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($agent->is_active)
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
                                    href="{{ route('admin.agents.knowledge', $agent) }}"
                                    icon="book-open"
                                    size="sm"
                                    variant="subtle"
                                    wire:navigate>
                                    {{ __('agentoom-core::admin.knowledge.manage') }}
                                </flux:button>
                                <flux:button
                                    href="{{ route('admin.agents.learned-skills', $agent) }}"
                                    icon="beaker"
                                    size="sm"
                                    variant="subtle"
                                    wire:navigate>
                                    {{ __('agentoom-core::admin.agents.learned_skills') }}
                                </flux:button>
                                <flux:button
                                    href="{{ route('admin.agents.edit', $agent) }}"
                                    icon="pencil"
                                    size="sm"
                                    wire:navigate>
                                    {{ __('agentoom-core::admin.common.edit') }}
                                </flux:button>
                                <flux:button
                                    icon="document-duplicate"
                                    size="sm"
                                    variant="subtle"
                                    wire:click="clone({{ $agent->id }})">
                                    {{ __('agentoom-core::admin.common.clone') }}
                                </flux:button>
                                @if ($agent->system_agent)
                                    <flux:badge size="sm" color="zinc" inset="top bottom">
                                        {{ __('agentoom-core::admin.agents.system_agent') }}
                                    </flux:badge>
                                @elseif ($agent->default_agent)
                                    <flux:badge size="sm" color="blue" inset="top bottom">
                                        {{ __('agentoom-core::admin.agents.default_agent') }}
                                    </flux:badge>
                                @else
                                    <flux:button
                                        variant="danger"
                                        icon="trash"
                                        size="sm"
                                        wire:click="showDeleteModal({{ $agent->id }})">
                                        {{ __('agentoom-core::admin.common.delete') }}
                                    </flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-12 text-center text-zinc-400">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon.cpu-chip class="size-10 opacity-30" />
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
        @if($this->deletion_blockers)
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg" class="text-red-600 dark:text-red-400">
                        {{ __('agentoom-core::admin.agents.cannot_delete_in_use') }}
                    </flux:heading>
                    <flux:subheading class="text-zinc-500 mt-1">
                        {{ __('agentoom-core::admin.agents.cannot_delete_in_use_text') }}
                    </flux:subheading>
                </div>

                <ul class="space-y-2">
                    @foreach($this->deletion_blockers as $blocker)
                        <li>
                            <a href="{{ $blocker['url'] }}"
                               class="flex items-center gap-2 p-2 rounded-lg bg-zinc-50 dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors no-underline"
                               wire:navigate>
                                @if($blocker['type'] === 'pipeline')
                                    <flux:icon.arrows-right-left class="size-4 text-purple-500 shrink-0" />
                                @elseif($blocker['type'] === 'endpoint')
                                    <flux:icon.link class="size-4 text-blue-500 shrink-0" />
                                @elseif($blocker['type'] === 'webhook')
                                    <flux:icon.signal class="size-4 text-emerald-500 shrink-0" />
                                @endif
                                <span class="text-sm text-zinc-700 dark:text-zinc-300 truncate">
                                    @if($blocker['type'] === 'pipeline')
                                        {{ __('agentoom-core::admin.agents.used_in_pipeline') }}: <strong>{{ $blocker['name'] }}</strong>
                                    @elseif($blocker['type'] === 'endpoint')
                                        {{ __('agentoom-core::admin.agents.used_in_endpoint') }}: <strong>{{ $blocker['name'] }}</strong>
                                    @elseif($blocker['type'] === 'webhook')
                                        {{ __('agentoom-core::admin.agents.used_in_webhook') }}: <strong>{{ $blocker['name'] }}</strong>
                                    @endif
                                </span>
                                <flux:icon.arrow-top-right-on-square class="size-3 text-zinc-400 shrink-0 ml-auto" />
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="flex justify-center pt-2">
                    <flux:button wire:click="$set('show_delete_modal', false)">
                        {{ __('agentoom-core::admin.common.close') }}
                    </flux:button>
                </div>
            </div>
        @else
            <div class="text-center space-y-4">
                <div class="mx-auto flex items-center justify-center size-12 rounded-full bg-red-100 dark:bg-red-900/30">
                    <flux:icon.exclamation-triangle class="size-6 text-red-600 dark:text-red-400" />
                </div>

                <flux:heading size="lg">
                    {{ __('agentoom-core::admin.agents.delete_confirmation_title') }}
                </flux:heading>

                <flux:subheading class="text-zinc-500">
                    {{ __('agentoom-core::admin.agents.delete_confirmation_text') }}
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
        @endif
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
