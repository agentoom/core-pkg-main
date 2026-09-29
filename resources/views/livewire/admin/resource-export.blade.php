<div>
    <flux:button variant="outline" icon="arrow-down-tray" wire:click="$set('show_modal', true)">
        {{ __('agentoom-core::admin.resources.export') }}
    </flux:button>

    <flux:modal wire:model="show_modal" class="max-w-md">
        <form wire:submit="export" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('agentoom-core::admin.resources.export_title') }}</flux:heading>
                <flux:subheading>{{ __('agentoom-core::admin.resources.export_subtitle') }}</flux:subheading>
            </div>

            <flux:select wire:model="resource_type_id" label="{{ __('agentoom-core::admin.resources.resource_type') }}">
                <flux:select.option value="">{{ __('agentoom-core::admin.common.select') }}</flux:select.option>
                @foreach ($resourceTypes as $type)
                    <flux:select.option value="{{ $type->id }}">{{ $type->name_translated }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:radio.group wire:model="format" label="{{ __('agentoom-core::admin.resources.export_format') }}">
                <flux:radio value="csv" label="CSV" />
                <flux:radio value="xls" label="XLS" />
                <flux:radio value="xlsx" label="XLSX" />
            </flux:radio.group>

            <div class="flex gap-3 justify-end">
                <flux:button wire:click="$set('show_modal', false)">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('agentoom-core::admin.common.export') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
