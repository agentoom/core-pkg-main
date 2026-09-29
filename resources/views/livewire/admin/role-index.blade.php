<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('agentoom-core::admin.roles.title') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.roles.subtitle') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('admin.roles.create')" wire:navigate>
            {{ __('agentoom-core::admin.roles.new') }}
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
        <flux:table :paginate="$roles">
            <flux:table.columns>
                <flux:table.column>
                    <flux:checkbox wire:model.live="selectAll" />
                </flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.id') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.roles.name') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.roles.users_count') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.roles.permissions_count') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($roles as $role)
                    <flux:table.row wire:key="role-{{ $role->id }}">
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selected" :value="$role->id" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="text-zinc-500 tabular-nums">{{ $role->id }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm">{{ $role->name }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $role->users_count }}</flux:table.cell>
                        <flux:table.cell>{{ $role->permissions_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button href="{{ route('admin.roles.edit', $role->id) }}" variant="primary" size="sm" color="amber" icon="pencil" wire:navigate>{{ __('agentoom-core::admin.common.edit') }}</flux:button>
                            @if ($role->name !== 'superadmin')
                                <flux:button variant="danger" icon="trash" wire:click="showDeleteModal({{ $role->id }})" size="sm">{{ __('agentoom-core::admin.common.delete') }}</flux:button>
                            @endif
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
                {{ __('agentoom-core::admin.roles.delete_confirmation_title') }}
            </flux:heading>

            <flux:subheading class="mb-6 text-gray-600">
                {{ __('agentoom-core::admin.roles.delete_confirmation_text') }}
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
