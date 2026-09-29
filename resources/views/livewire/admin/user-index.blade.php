<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('agentoom-core::admin.users.title') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.users.subtitle') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('admin.users.create')" wire:navigate>
            {{ __('agentoom-core::admin.users.new') }}
        </flux:button>
    </div>
    @if (session('success'))
        <flux:callout variant="success" class="mb-4">{{ session('success') }}</flux:callout>
    @endif
    @if (session('error'))
        <flux:callout variant="danger" class="mb-4">{{ session('error') }}</flux:callout>
    @endif

    {{-- Bulk Actions --}}
    @if ($this->isAnySelected)
        <div class="flex items-center gap-4 p-4 bg-zinc-100 dark:bg-zinc-800 rounded-lg mb-4">
            <span class="text-sm font-medium">{{ __('agentoom-core::admin.common.selected_count', ['count' => $this->selectedCount]) }}</span>

            <flux:separator vertical />

            <div class="flex gap-2">
                <flux:button size="sm" icon="trash" variant="danger" wire:click="bulkDelete">
                    {{ __('agentoom-core::admin.common.delete_selected') }}
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
        <flux:table :paginate="$users">
            <flux:table.columns>
                <flux:table.column>
                    <flux:checkbox wire:model.live="selectAll" />
                </flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.id') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.users.name') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.users.email') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.users.memories') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($users as $user)
                    <flux:table.row wire:key="user-{{ $user->id }}">
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selected" :value="$user->id" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="text-zinc-500 tabular-nums">{{ $user->id }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button href="{{ route('admin.users.memories', $user) }}" variant="subtle" size="sm" icon="circle-stack" wire:navigate>
                                {{ __('agentoom-core::admin.users.memories') }}
                            </flux:button>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:button href="{{ route('admin.users.edit', $user) }}" variant="primary" size="sm" color="amber" icon="pencil" wire:navigate>{{ __('agentoom-core::admin.common.edit') }}</flux:button>
                                @if (!str_contains((string) $user->email, '.system@agentoom.internal'))
                                    <flux:button variant="danger" icon="trash" wire:click="showDeleteModal({{ $user->id }})" size="sm">{{ __('agentoom-core::admin.common.delete') }}</flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
    <flux:modal wire:model="show_delete_modal" class="max-w-md">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <flux:icon.exclamation-triangle class="h-6 w-6 text-red-600" />
            </div>

            <flux:heading size="lg" class="mb-2">
                {{ __('agentoom-core::admin.users.delete_confirmation_title') }}
            </flux:heading>

            <flux:subheading class="mb-6 text-gray-600">
                {{ __('agentoom-core::admin.users.delete_confirmation_text') }}
            </flux:subheading>
        </div>

        <div class="flex gap-3 justify-center mt-6">
            <flux:button type="button" wire:click="$set('show_delete_modal', false)">
                {{ __('agentoom-core::admin.common.cancel') }}
            </flux:button>

            <flux:button type="button" variant="danger" wire:click="delete">
                {{ __('agentoom-core::admin.common.delete') }}
            </flux:button>
        </div>
    </flux:modal>
</div>
