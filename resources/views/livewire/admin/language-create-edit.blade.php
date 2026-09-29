<div class="space-y-6">
    <div>
        <flux:heading size="xl">
            {{ $is_editing ? __('agentoom-core::admin.languages.edit') : __('agentoom-core::admin.languages.new') }}
        </flux:heading>
        <flux:subheading>
            {{ $is_editing ? __('agentoom-core::admin.languages.edit_subtitle') : __('agentoom-core::admin.languages.create_subtitle') }}
        </flux:subheading>
    </div>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <div class="flex justify-end gap-4">
                <flux:switch wire:model="is_active" label="{{ __('agentoom-core::admin.languages.is_active') }}" />
                <flux:switch wire:model="is_default" label="{{ __('agentoom-core::admin.languages.is_default') }}" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="name" label="{{ __('agentoom-core::admin.languages.name') }}" placeholder="{{ __('agentoom-core::admin.languages.name_placeholder') }}" />
                <flux:input wire:model="code" label="{{ __('agentoom-core::admin.languages.code') }}" placeholder="{{ __('agentoom-core::admin.languages.code_placeholder') }}" />
            </div>

            <div class="flex gap-2 justify-end">
                <flux:button type="button" href="{{ route('admin.languages.index') }}" wire:navigate>{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('agentoom-core::admin.common.save') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
