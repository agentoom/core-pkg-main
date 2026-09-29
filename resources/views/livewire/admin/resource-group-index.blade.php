<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Resource Groups') }}</flux:heading>
            <flux:subheading>{{ __('Manage groups for your resource types') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" :href="route('admin.resource-groups.create')" wire:navigate>
            {{ __('New Group') }}
        </flux:button>
    </div>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    <flux:card>
        <flux:table :paginate="$groups">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Icon') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column align="right">{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($groups as $group)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">
                            {{ $group->name }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:icon :icon="$group->icon ?: 'cube'" variant="mini" />
                            <span class="ml-2">{{ $group->icon }}</span>
                        </flux:table.cell>

                        <flux:table.cell class="text-zinc-500 dark:text-zinc-400 max-w-xs truncate">
                            {{ $group->description }}
                        </flux:table.cell>

                        <flux:table.cell align="right">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button
                                    href="{{ route('admin.resource-groups.edit', $group) }}"
                                    icon="pencil"
                                    size="sm"
                                    wire:navigate>
                                    {{ __('agentoom-core::admin.common.edit') }}
                                </flux:button>
                                <flux:button
                                    variant="danger"
                                    icon="trash"
                                    size="sm"
                                    wire:click="delete({{ $group->id }})">
                                    {{ __('agentoom-core::admin.common.delete') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-12 text-center text-zinc-400">
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
</div>
