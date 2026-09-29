<div class="flex flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('agentoom-core::admin.agent_tools.title') }}</flux:heading>
        <flux:subheading>{{ __('agentoom-core::admin.agent_tools.subtitle') }}</flux:subheading>
    </div>

    <div class="flex items-center gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('agentoom-core::admin.common.search') }}" class="flex-1" />
    </div>

    <flux:card>
        <flux:table :paginate="$tools">
            <flux:table.columns>
                <flux:table.column>{{ __('agentoom-core::admin.common.id') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.agent_tools.name') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.agent_tools.description') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($tools as $tool)
                    <flux:table.row :key="$tool->id">
                        <flux:table.cell>
                            <span class="text-zinc-500 tabular-nums">{{ $tool->id }}</span>
                        </flux:table.cell>

                        <flux:table.cell class="font-medium">
                            {{ $tool->name }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:dropdown hover position="bottom" align="center" offset="0" gap="10">
                                <button type="button" class="flex items-center gap-3">
                                    <span class="line-clamp-2 text-zinc-500 dark:text-zinc-400 text-sm max-w-xs truncate">{{ $tool->description }}</span>
                                </button>
                                <flux:popover class="flex flex-col gap-4">
                                    <p class="text-zinc-500 dark:text-zinc-400 text-sm max-w-[320px]">{{ $tool->description }}</p>
                                </flux:popover>
                            </flux:dropdown>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($tool->is_active)
                                <flux:badge color="green" size="sm">{{ __('agentoom-core::admin.common.active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('agentoom-core::admin.common.inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @role('superadmin')
                                <flux:switch wire:click="toggleStatus({{ $tool->id }})" :checked="$tool->is_active" />
                            @endrole
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-12 text-center text-zinc-400">
                            {{ __('agentoom-core::admin.common.no_results') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showConfirmationModal" class="min-w-[400px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('agentoom-core::admin.agent_tools.deactivate_confirmation_title') }}</flux:heading>
                <flux:subheading>
                    {{ __('agentoom-core::admin.agent_tools.deactivate_confirmation_text') }}
                </flux:subheading>
            </div>

            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-4 rounded-lg">
                <p class="text-sm font-medium text-amber-800 dark:text-amber-200 mb-2">
                    {{ __('agentoom-core::admin.agent_tools.agents_using_tool') }}:
                </p>
                <ul class="list-disc list-inside text-sm text-amber-700 dark:text-amber-300">
                    @foreach ($agentsUsingTool as $agentData)
                        <li>{{ $agentData['name'] }} (ID: {{ $agentData['id'] }})</li>
                    @endforeach
                </ul>
            </div>

            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" wire:click="resetConfirmation">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="confirmDeactivate">{{ __('agentoom-core::admin.agent_tools.confirm_disconnect_and_hide') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
