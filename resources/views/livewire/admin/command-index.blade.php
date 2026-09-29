<div class="space-y-6">
    {{-- Header --}}
    <div>
        <flux:heading size="xl">{{ __('agentoom-core::admin.configs.section_console_commands') }}</flux:heading>
        <flux:subheading>{{ __('agentoom-core::admin.configs.console_commands_description') }}</flux:subheading>
    </div>

    {{-- Flash --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif

    <flux:card class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach(__('agentoom-core::admin.configs.console_commands') as $command => $details)
                <div class="p-4 border border-zinc-200 dark:border-zinc-700 rounded-lg flex flex-col justify-between gap-4">
                    <div class="space-y-1">
                        <flux:heading size="sm">{{ $details['name'] }}</flux:heading>
                        <flux:subheading>{{ $details['description'] }}</flux:subheading>
                    </div>
                    <div class="flex justify-end">
                        <flux:button
                            wire:click="executeCommand('{{ $command }}')"
                            wire:loading.attr="disabled"
                            variant="outline"
                            icon="play"
                            size="sm">
                            {{ __('agentoom-core::admin.common.execute') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    </flux:card>
</div>
