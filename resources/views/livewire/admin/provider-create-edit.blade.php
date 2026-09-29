<div class="space-y-6">
    <div>
        <flux:heading size="xl">
            {{ $is_editing ? __('agentoom-core::admin.providers.edit') : __('agentoom-core::admin.providers.new') }}
        </flux:heading>
        <flux:subheading>
            {{ $is_editing ? __('agentoom-core::admin.providers.edit_subtitle') : __('agentoom-core::admin.providers.create_subtitle') }}
        </flux:subheading>
    </div>

    @if (session('success'))
        <flux:callout variant="success" class="mb-4">{{ session('success') }}</flux:callout>
    @endif

    @if (session('error'))
        <flux:callout variant="danger" class="mb-4">{{ session('error') }}</flux:callout>
    @endif

    {{-- Card --}}
    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 gap-4">
                <flux:select wire:model="name" label="{{ __('agentoom-core::admin.providers.name') }}" placeholder="{{ __('agentoom-core::admin.providers.name_placeholder') }}" :disabled="$is_editing">
                    @foreach ($availableProviders as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="api_key" label="{{ __('agentoom-core::admin.providers.api_key') }}" placeholder="{{ __('agentoom-core::admin.providers.api_key') }}" type="password" viewable />
                <flux:input wire:model="url" label="{{ __('agentoom-core::admin.providers.url') }}" placeholder="{{ __('agentoom-core::admin.providers.url_placeholder') }}" />
                <flux:text>{{ __('agentoom-core::admin.providers.url_help') }}</flux:text>
                @if ($name === 'custom')
                    <flux:callout>{{ __('agentoom-core::admin.providers.custom_help') }}</flux:callout>
                @endif
            </div>
            <div class="flex gap-2 justify-end">
                <flux:button type="button" href="{{ route('admin.providers.index') }}">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('agentoom-core::admin.common.save') }}</flux:button>
            </div>
        </form>
    </flux:card>

    @if ($is_editing)
        <flux:card>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('agentoom-core::admin.providers.available_models') }}</flux:heading>
                        <flux:subheading>{{ __('agentoom-core::admin.providers.models_count', ['count' => count($models)]) }}</flux:subheading>
                    </div>
                    <flux:button wire:click="syncModels" variant="primary" icon="arrow-path">
                        <span class="in-data-loading:hidden">{{ __('agentoom-core::admin.providers.sync_models') }}</span>
                        <span class="hidden in-data-loading:flex items-center gap-2">
                            <flux:icon.loading variant="mini" />
                            {{ __('agentoom-core::admin.providers.syncing_models') }}
                        </span>
                    </flux:button>
                </div>

                @if (count($models) > 0)
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('agentoom-core::admin.providers.model_name') }}</flux:table.column>
                            <flux:table.column>{{ __('agentoom-core::admin.providers.model_status') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($models as $model)
                                <flux:table.row>
                                    <flux:table.cell>{{ $model->model_name }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if ($model->is_active)
                                            <flux:badge color="green">{{ __('agentoom-core::admin.common.active') }}</flux:badge>
                                        @else
                                            <flux:badge color="gray">{{ __('agentoom-core::admin.common.inactive') }}</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @else
                    <flux:callout>{{ __('agentoom-core::admin.providers.no_models') }}</flux:callout>
                @endif
            </div>
        </flux:card>
    @endif
</div>
