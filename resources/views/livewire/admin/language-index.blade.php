
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('agentoom-core::admin.languages.title') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.languages.subtitle') }}</flux:subheading>
        </div>

        <flux:button href="{{ route('admin.languages.create') }}" variant="primary" icon="plus" wire:navigate>
            {{ __('agentoom-core::admin.languages.new') }}
        </flux:button>
    </div>

    @if (session('success'))
        <flux:callout variant="success">
            {{ session('success') }}
        </flux:callout>
    @endif

    <flux:card class="space-y-4">
        <div class="flex gap-4">
            <flux:input wire:model.live.debounce.300ms="search"
                placeholder="{{ __('agentoom-core::admin.common.search') }}"
                icon="magnifying-glass"
                clearable />
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('agentoom-core::admin.languages.name') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.languages.code') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.languages.is_active') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.languages.is_default') }}</flux:table.column>
                <flux:table.column align="end">{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($languages as $li => $language)
                    <flux:table.row :key="$language->id">
                        <flux:table.cell>
                            <span class="font-medium text-zinc-900 dark:text-white">
                                {{ $language->name }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <code>{{ $language->code }}</code>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:switch wire:click="toggleActive({{ $language->id }})"
                                         :checked="$language->is_active" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:field>
                                <flux:switch wire:click="toggleDefault({{ $language->id }})"
                                             :checked="$language->is_default" />
                            </flux:field>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:button :href="route('admin.languages.edit', $language)" size="sm" variant="ghost" icon="pencil" wire:navigate />

                                <flux:modal.trigger name="delete-modal-{{ $this->getId() }}-{{ $language->id }}">
                                    <flux:button size="sm" variant="ghost" icon="trash" />
                                </flux:modal.trigger>

                                <flux:modal name="delete-modal-{{ $this->getId() }}-{{ $language->id }}" class="min-w-[22rem]">
                                    <div class="space-y-6">
                                        <div>
                                            <flux:heading size="lg">{{ __('agentoom-core::admin.languages.delete_confirmation_title') }}</flux:heading>
                                            <flux:subheading>{{ __('agentoom-core::admin.languages.delete_confirmation_text') }}</flux:subheading>
                                        </div>

                                        <div class="flex gap-2 justify-end">
                                                                                            <flux:button variant="ghost"
                                                    x-on:click="$el.closest('dialog').close()">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                                            <flux:button wire:click="delete({{ $language->id }})" variant="danger">{{ __('agentoom-core::admin.common.delete') }}</flux:button>
                                        </div>
                                    </div>
                                </flux:modal>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center py-8 text-zinc-500">
                            {{ __('agentoom-core::admin.common.no_results') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        @if ($languages->hasPages())
            <div class="mt-4">
                {{ $languages->links() }}
            </div>
        @endif
    </flux:card>
</div>
