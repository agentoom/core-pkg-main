<div class="space-y-6">
    <div>
        <flux:heading size="xl">
            {{ $is_editing ? __('agentoom-core::admin.roles.edit') : __('agentoom-core::admin.roles.new') }}
        </flux:heading>
        <flux:subheading>
            {{ $is_editing ? __('agentoom-core::admin.roles.edit_subtitle') : __('agentoom-core::admin.roles.create_subtitle') }}
        </flux:subheading>
    </div>

    @if (session('error'))
        <flux:callout variant="danger" class="mb-4">{{ session('error') }}</flux:callout>
    @endif

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <flux:input
                wire:model="name"
                label="{{ __('agentoom-core::admin.roles.name') }}"
                placeholder="{{ __('agentoom-core::admin.roles.name') }}"
                :disabled="$is_editing && $name === 'superadmin'"
            />

            <div class="space-y-4">
                <flux:label>{{ __('agentoom-core::admin.roles.permissions') }}</flux:label>

                @if ($is_editing && $name === 'superadmin')
                    <flux:callout variant="info">
                        {{ __('agentoom-core::admin.roles.superadmin_permissions_immutable') }}
                    </flux:callout>
                @else
                    <div class="flex gap-2">
                        <flux:button wire:click="selectAll" size="sm" type="button" variant="subtle">{{ __('agentoom-core::admin.common.select_all') }}</flux:button>
                        <flux:button wire:click="deselectAll" size="sm" type="button" variant="subtle">{{ __('agentoom-core::admin.common.deselect_all') }}</flux:button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach ($permissionGroups as $groupName => $groupPermissions)
                            <flux:card class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <flux:heading size="sm">{{ __('agentoom-core::admin.permissions.groups.' . $groupName) }}</flux:heading>
                                    <div class="flex gap-2">
                                        <flux:button wire:click="selectAllGroup('{{ $groupName }}')" size="xs" type="button" variant="ghost">{{ __('agentoom-core::admin.common.all') }}</flux:button>
                                        <flux:button wire:click="deselectAllGroup('{{ $groupName }}')" size="xs" type="button" variant="ghost">{{ __('agentoom-core::admin.common.none') }}</flux:button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach ($groupPermissions as $permission)
                                        <flux:checkbox
                                            wire:model="selectedPermissions"
                                            value="{{ $permission }}"
                                            label="{{ __('agentoom-core::admin.permissions.actions.' . Str::after($permission, '.')) }}"
                                        />
                                    @endforeach
                                </div>
                            </flux:card>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex gap-2 justify-end">
                <flux:button type="button" href="{{ route('admin.roles.index') }}">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('agentoom-core::admin.common.save') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
