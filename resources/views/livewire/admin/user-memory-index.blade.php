<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-3">
            <flux:button variant="subtle" icon="arrow-left" :href="route('admin.users.index')" wire:navigate />
            <div>
                <flux:heading size="xl">{{ __('agentoom-core::admin.users.memories_title') }}</flux:heading>
                <flux:subheading>
                    {{ __('agentoom-core::admin.users.memories_subtitle', ['name' => $user->name]) }}
                </flux:subheading>
            </div>
        </div>
        @can('users.edit')
            <flux:button variant="primary" icon="plus" wire:click="createMemory">
                {{ __('agentoom-core::admin.users.add_memory') }}
            </flux:button>
        @endcan
    </div>

    @if (session('success'))
        <flux:callout variant="success">{{ session('success') }}</flux:callout>
    @endif

    <flux:card class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="lg">{{ __('agentoom-core::admin.users.stored_memories') }}</flux:heading>
                <flux:subheading>{{ __('agentoom-core::admin.users.stored_memories_hint') }}</flux:subheading>
            </div>
            <div class="w-full sm:max-w-sm">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    :placeholder="__('agentoom-core::admin.users.search_memories')"
                    clearable
                />
            </div>
        </div>

        <flux:table :paginate="$memories">
            <flux:table.columns>
                <flux:table.column>{{ __('agentoom-core::admin.users.memory_title') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.users.memory_content') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.users.memory_importance') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.users.memory_expires') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.users.memory_updated') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($memories as $memory)
                    <flux:table.row wire:key="user-memory-{{ $memory->id }}">
                        <flux:table.cell>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $memory->title }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-xl">
                            <span class="line-clamp-2 text-sm text-zinc-600 dark:text-zinc-400" title="{{ $memory->content }}">
                                {{ $memory->content }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge color="indigo">{{ $memory->importance }}/5</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="whitespace-nowrap text-sm text-zinc-500">
                                {{ $memory->expires_at?->format('Y-m-d') ?? __('agentoom-core::admin.users.memory_never_expires') }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="whitespace-nowrap text-sm text-zinc-500">{{ $memory->updated_at->diffForHumans() }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @can('users.edit')
                                <div class="flex items-center gap-2">
                                    <flux:button size="sm" variant="primary" color="amber" icon="pencil" wire:click="editMemory({{ $memory->id }})">
                                        {{ __('agentoom-core::admin.common.edit') }}
                                    </flux:button>
                                    <flux:button size="sm" variant="danger" icon="trash" wire:click="confirmDeleteMemory({{ $memory->id }})">
                                        {{ __('agentoom-core::admin.common.delete') }}
                                    </flux:button>
                                </div>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <div class="flex flex-col items-center justify-center gap-2 py-12 text-center">
                                <flux:icon.circle-stack class="size-10 text-zinc-400" />
                                <flux:heading size="sm">{{ __('agentoom-core::admin.users.no_memories') }}</flux:heading>
                                <flux:subheading>{{ __('agentoom-core::admin.users.no_memories_hint') }}</flux:subheading>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="show_memory_modal" class="max-w-2xl">
        <form wire:submit="saveMemory" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editing_memory_id ? __('agentoom-core::admin.users.edit_memory') : __('agentoom-core::admin.users.add_memory') }}
                </flux:heading>
                <flux:subheading>{{ __('agentoom-core::admin.users.memory_form_hint') }}</flux:subheading>
            </div>

            <flux:field>
                <flux:label>{{ __('agentoom-core::admin.users.memory_title') }}</flux:label>
                <flux:input wire:model="title" :placeholder="__('agentoom-core::admin.users.memory_title_placeholder')" />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('agentoom-core::admin.users.memory_content') }}</flux:label>
                <flux:textarea wire:model="content" rows="6" :placeholder="__('agentoom-core::admin.users.memory_content_placeholder')" />
                <flux:error name="content" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('agentoom-core::admin.users.memory_importance') }}</flux:label>
                <flux:select wire:model="importance">
                    @foreach (range(1, 5) as $rating)
                        <flux:select.option value="{{ $rating }}">{{ $rating }}/5</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="importance" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('agentoom-core::admin.users.memory_expires') }}</flux:label>
                <flux:input type="date" wire:model="expires_at" />
                <flux:description>{{ __('agentoom-core::admin.users.memory_expires_hint') }}</flux:description>
                <flux:error name="expires_at" />
            </flux:field>

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="subtle" wire:click="$set('show_memory_modal', false)">
                    {{ __('agentoom-core::admin.common.cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('agentoom-core::admin.common.save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="show_delete_modal" class="max-w-md">
        <div class="space-y-6 text-center">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                <flux:icon.exclamation-triangle class="size-6 text-red-600 dark:text-red-400" />
            </div>
            <div>
                <flux:heading size="lg">{{ __('agentoom-core::admin.users.delete_memory_title') }}</flux:heading>
                <flux:subheading class="mt-2">{{ __('agentoom-core::admin.users.delete_memory_text') }}</flux:subheading>
            </div>
            <div class="flex justify-center gap-3">
                <flux:button type="button" variant="subtle" wire:click="$set('show_delete_modal', false)">
                    {{ __('agentoom-core::admin.common.cancel') }}
                </flux:button>
                <flux:button type="button" variant="danger" wire:click="deleteMemory">
                    {{ __('agentoom-core::admin.common.delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
