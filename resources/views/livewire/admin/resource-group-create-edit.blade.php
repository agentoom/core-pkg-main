<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">
                {{ $resourceGroup?->exists ? __('Edit Resource Group') : __('Create Resource Group') }}
            </flux:heading>
            <flux:subheading>{{ $is_editing ? ($resourceGroup->name ?? 'Untitled') : __('New Group') }}</flux:subheading>
        </div>
        <flux:button href="{{ route('admin.resource-groups.index') }}" variant="ghost" icon="arrow-left" wire:navigate>
            {{ __('agentoom-core::admin.common.back') }}
        </flux:button>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" />
                <flux:error name="name" />
            </flux:field>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Icon') }}</flux:label>
                    <div class="flex gap-2">
                        <flux:input wire:model="icon" placeholder="cube, key, globe-alt, etc." class="flex-1" />
                        <flux:modal.trigger name="icon-picker-{{ $this->getId() }}">
                            <flux:button icon="swatch" title="{{ __('Choose Icon') }}" />
                        </flux:modal.trigger>
                    </div>
                    <flux:error name="icon" />
                </flux:field>

                <flux:modal name="icon-picker-{{ $this->getId() }}" class="min-w-[40rem] lg:min-w-[60rem]" x-on:icon-selected.window="$flux.modal('icon-picker-{{ $this->getId() }}').close()">
                    <div class="space-y-6">
                        <flux:heading size="lg">{{ __('Select Icon') }}</flux:heading>
                        <livewire:agentoom-core::admin.icon-picker />
                    </div>
                </flux:modal>

                <flux:field>
                    <flux:label>{{ __('agentoom-core::admin.projects.title') }}</flux:label>
                    <flux:select variant="listbox" searchable wire:model.live="projectId">
                        <flux:select.option value="">No Project</flux:select.option>
                        @foreach($projects as $p)
                            <flux:select.option value="{{ $p->id }}">{{ $p->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="projectId" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('agentoom-core::admin.resource_types.description') }}</flux:label>
                <flux:textarea wire:model="description" rows="3" />
                <flux:error name="description" />
            </flux:field>
        </flux:card>

        <div class="flex justify-end gap-3">
            <flux:button href="{{ route('admin.resource-groups.index') }}" variant="ghost" wire:navigate>
                {{ __('agentoom-core::admin.common.cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                {{ __('agentoom-core::admin.common.save') }}
            </flux:button>
        </div>
    </form>
</div>
