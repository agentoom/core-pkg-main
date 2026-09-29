<div class="space-y-6">
    <div>
        <flux:heading size="xl">
            {{ $is_editing ? __('agentoom-core::admin.users.edit') : __('agentoom-core::admin.users.new') }}
        </flux:heading>
        <flux:subheading>
            {{ $is_editing ? __('agentoom-core::admin.users.edit_subtitle') : __('agentoom-core::admin.users.create_subtitle') }}
        </flux:subheading>
    </div>

    {{-- Card --}}
    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input
                    wire:model="name"
                    label="{{ __('agentoom-core::admin.users.name') }}"
                    placeholder="{{ __('agentoom-core::admin.users.name') }}"
                />
                <flux:input
                    wire:model="email"
                    label="{{ __('agentoom-core::admin.users.email') }}"
                    placeholder="{{ __('agentoom-core::admin.users.email') }}"
                />

                <flux:select
                    wire:model="role"
                    variant="listbox"
                    searchable
                    label="{{ __('agentoom-core::admin.users.role') }}"
                    placeholder="{{ __('agentoom-core::admin.users.role') }}"
                    class="md:col-span-2"
                >
                    @foreach ($roles as $roleOption)
                        <flux:select.option value="{{ $roleOption->name }}">
                            {{ ucfirst($roleOption->name) }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                @if ($is_superadmin)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:col-span-2">
                        <flux:select
                            wire:model="ai_limit_type"
                            variant="listbox"
                            label="{{ __('agentoom-core::admin.users.ai_limit_type') }}"
                            placeholder="{{ __('agentoom-core::admin.users.none') }}"
                        >
                            <flux:select.option value="">{{ __('agentoom-core::admin.users.none') }}</flux:select.option>
                            <flux:select.option value="daily">{{ __('agentoom-core::admin.users.daily') }}</flux:select.option>
                            <flux:select.option value="weekly">{{ __('agentoom-core::admin.users.weekly') }}</flux:select.option>
                            <flux:select.option value="monthly">{{ __('agentoom-core::admin.users.monthly') }}</flux:select.option>
                        </flux:select>
                        <flux:input
                            wire:model="ai_limit_amount"
                            label="{{ __('agentoom-core::admin.users.ai_limit_amount') }}"
                            type="number"
                        />
                        <flux:input
                            wire:model="ai_limit_start_date"
                            label="{{ __('agentoom-core::admin.users.ai_limit_start_date') }}"
                            type="date"
                        />
                    </div>
                @endif

                <flux:input
                    wire:model="password"
                    label="{{ __('agentoom-core::admin.users.password') }}"
                    placeholder="{{ __('agentoom-core::admin.users.password') }}"
                    type="password"
                    viewable
                />
                <flux:input
                    wire:model="password_confirmation"
                    label="{{ __('agentoom-core::admin.users.password_confirmation') }}"
                    placeholder="{{ __('agentoom-core::admin.users.password_confirmation') }}"
                    type="password"
                    viewable
                />
            </div>
            <div class="flex gap-2 justify-end">
                <flux:button type="button" href="{{ route('admin.users.index') }}">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('agentoom-core::admin.common.save') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
