<div class="space-y-6">
    {{-- Header --}}
    <div>
        <flux:heading size="xl">{{ __('agentoom-core::admin.system_workers.title') }}</flux:heading>
        <flux:subheading>{{ __('agentoom-core::admin.system_workers.subtitle') }}</flux:subheading>
    </div>

    {{-- Flash --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-4">
        {{-- Memory Worker --}}
        <flux:card class="space-y-4">
            <flux:heading size="sm">{{ __('agentoom-core::admin.configs.section_memory_worker') }}</flux:heading>
            <flux:description>{{ __('agentoom-core::admin.configs.memory_worker_description') }}</flux:description>

            <flux:select
                variant="listbox"
                wire:model="worker_agent_id"
                label="{{ __('agentoom-core::admin.configs.worker_agent') }}"
                placeholder="{{ __('agentoom-core::admin.configs.worker_agent_placeholder') }}"
                searchable
                icon="cpu-chip">
                <flux:select.option value="">{{ __('agentoom-core::admin.common.none') }}</flux:select.option>
                @foreach($agents as $id => $name)
                    <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:card>

        {{-- Specialized Agents --}}
        @foreach($specialized_agents_available as $slug => $specialized_agent)
            <flux:card class="space-y-4">
                <flux:heading size="sm">{{ $specialized_agent['name'] }}</flux:heading>
                <flux:description>{{ $specialized_agent['description'] }}</flux:description>

                <flux:select
                    variant="listbox"
                    wire:model="ai_specialized_agents.{{ $slug }}.agent_id"
                    label="{{ __('agentoom-core::admin.configs.worker_agent') }}"
                    placeholder="{{ __('agentoom-core::admin.configs.worker_agent_placeholder') }}"
                    searchable
                    icon="cpu-chip">
                    <flux:select.option value="">{{ __('agentoom-core::admin.common.none') }}</flux:select.option>
                    @foreach($agents as $id => $name)
                        <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:card>
        @endforeach

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" icon="check">
                {{ __('agentoom-core::admin.common.save') }}
            </flux:button>
        </div>
    </form>
</div>
