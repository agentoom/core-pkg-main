<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('agentoom-core::admin.providers.title') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.providers.subtitle') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('admin.providers.create')" wire:navigate>
            {{ __('agentoom-core::admin.providers.new') }}
        </flux:button>
    </div>
    @if (session('success'))
        <flux:callout variant="success" class="mb-4">{{ session('success') }}</flux:callout>
    @endif
    <flux:card>
        <flux:table :paginate="$providers">
            <flux:table.columns>
                <flux:table.column>{{ __('agentoom-core::admin.providers.name') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.providers.url') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.providers.models') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($providers as $provider)
                    <flux:table.row>
                        <flux:table.cell>{{ $provider->name }}</flux:table.cell>
                        <flux:table.cell>{{ $provider->url ?: '(default)' }}</flux:table.cell>
                        <flux:table.cell>{{ __('agentoom-core::admin.providers.models_count', ['count' => $provider->models_count]) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button wire:click="syncModels({{ $provider->id }})" variant="primary" icon="arrow-path" wire:loading.attr="disabled" wire:target="syncModels({{ $provider->id }})">
                                <span wire:loading.remove wire:target="syncModels({{ $provider->id }})">{{ __('agentoom-core::admin.providers.sync_models') }}</span>
                                <span wire:loading wire:target="syncModels({{ $provider->id }})">{{ __('agentoom-core::admin.providers.syncing_models') }}</span>
                            </flux:button>
                            <flux:button href="{{ route('admin.providers.edit', $provider) }}" variant="primary" color="amber" icon="pencil" wire:navigate>{{ __('agentoom-core::admin.common.edit') }}</flux:button>
                            <flux:button variant="danger" icon="trash" wire:click="showDeleteModal({{ $provider->id }})">{{ __('agentoom-core::admin.common.delete') }}</flux:button>
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
                {{ __('agentoom-core::admin.providers.delete_confirmation_title') }}
            </flux:heading>

            <flux:subheading class="mb-6 text-gray-600">
                {{ __('agentoom-core::admin.providers.delete_confirmation_text') }}
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
