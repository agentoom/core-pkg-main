<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('agentoom-core::admin.webhooks.title') }}</flux:heading>
        <flux:button href="{{ route('admin.webhook-subscriptions.create') }}" icon="plus" variant="primary">{{ __('agentoom-core::admin.webhooks.new') }}</flux:button>
    </div>

    <flux:card>
        <div class="mb-4">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('agentoom-core::admin.common.search') }}..." icon="magnifying-glass" />
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="text-left py-2 font-medium text-zinc-500">{{ __('agentoom-core::admin.webhooks.url') }}</th>
                        <th class="text-left py-2 font-medium text-zinc-500">{{ __('agentoom-core::admin.webhooks.events') }}</th>
                        <th class="text-left py-2 font-medium text-zinc-500">{{ __('agentoom-core::admin.common.scope') }}</th>
                        <th class="text-left py-2 font-medium text-zinc-500">{{ __('agentoom-core::admin.webhooks.channel') }}</th>
                        <th class="text-center py-2 font-medium text-zinc-500">{{ __('agentoom-core::admin.common.status') }}</th>
                        <th class="text-right py-2 font-medium text-zinc-500">{{ __('agentoom-core::admin.common.last_delivered') }}</th>
                        <th class="text-right py-2 font-medium text-zinc-500">{{ __('agentoom-core::admin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscriptions as $subscription)
                        <tr class="border-b border-zinc-100 dark:border-zinc-800">
                            <td class="py-2">
                                <flux:text class="font-mono text-xs truncate max-w-[200px] block">{{ $subscription->url ?: $subscription->channel_config['chat_id'] ?? $subscription->channel_config['phone_number'] ?? '—' }}</flux:text>
                            </td>
                            <td class="py-2">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($subscription->events as $event)
                                        <flux:badge size="sm" color="blue">{{ $event }}</flux:badge>
                                    @endforeach
                                </div>
                            </td>
                            <td class="py-2">
                                @if($subscription->exclude_agents && $subscription->exclude_pipelines)
                                    <flux:badge size="sm" color="zinc">{{ __('agentoom-core::admin.common.all') }}</flux:badge>
                                @elseif($subscription->agent)
                                    <flux:badge size="sm" color="indigo">{{ $subscription->agent->name }}</flux:badge>
                                @elseif($subscription->exclude_agents)
                                    <flux:badge size="sm" color="amber">{{ __('agentoom-core::admin.webhooks.no_agent') }}</flux:badge>
                                @elseif($subscription->pipeline)
                                    <flux:badge size="sm" color="purple">{{ $subscription->pipeline->name }}</flux:badge>
                                @elseif($subscription->exclude_pipelines)
                                    <flux:badge size="sm" color="amber">{{ __('agentoom-core::admin.webhooks.no_pipeline') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">{{ __('agentoom-core::admin.common.all') }}</flux:badge>
                                @endif
                            </td>
                            <td class="py-2">
                                @if($subscription->channel === 'telegram')
                                    <flux:badge size="sm" color="sky">Telegram</flux:badge>
                                @elseif($subscription->channel === 'whatsapp')
                                    <flux:badge size="sm" color="emerald">WhatsApp</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">HTTP</flux:badge>
                                @endif
                            </td>
                            <td class="py-2 text-center">
                                <flux:badge size="sm" :color="$subscription->is_active ? 'emerald' : 'zinc'">
                                    {{ $subscription->is_active ? __('agentoom-core::admin.common.active') : __('agentoom-core::admin.common.inactive') }}
                                </flux:badge>
                            </td>
                            <td class="py-2 text-right">
                                <flux:text size="sm" class="text-zinc-500">
                                    {{ $subscription->last_delivered_at ? $subscription->last_delivered_at->diffForHumans() : __('agentoom-core::admin.common.never') }}
                                </flux:text>
                            </td>
                            <td class="py-2 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button href="{{ route('admin.webhook-subscriptions.edit', $subscription) }}" size="sm" variant="ghost" icon="pencil" />
                                    <flux:button wire:click="showDeleteModal({{ $subscription->id }})" size="sm" variant="ghost" icon="trash" class="text-red-500" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center">
                                <flux:text class="text-zinc-500">{{ __('agentoom-core::admin.common.no_entries') }}</flux:text>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $subscriptions->links() }}
        </div>
    </flux:card>

    <flux:modal name="delete-modal" wire:model="show_delete_modal">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('agentoom-core::admin.webhooks.delete_confirmation_title') }}</flux:heading>
            <flux:text>{{ __('agentoom-core::admin.webhooks.delete_confirmation_text') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('show_delete_modal', false)">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="delete">{{ __('agentoom-core::admin.common.delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
