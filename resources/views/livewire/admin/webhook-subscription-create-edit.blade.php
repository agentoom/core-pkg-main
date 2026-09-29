<div class="space-y-6">
    <flux:heading size="xl">{{ $is_editing ? __('agentoom-core::admin.webhooks.edit') : __('agentoom-core::admin.webhooks.create') }}</flux:heading>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            {{-- Channel selector --}}
            <flux:field>
                <flux:label>{{ __('agentoom-core::admin.webhooks.channel') }}</flux:label>
                <flux:select wire:model.live="channel">
                    <flux:select.option value="">{{ __('agentoom-core::admin.webhooks.channel_webhook') }}</flux:select.option>
                    <flux:select.option value="telegram">{{ __('agentoom-core::admin.webhooks.channel_telegram') }}</flux:select.option>
                    <flux:select.option value="whatsapp">{{ __('agentoom-core::admin.webhooks.channel_whatsapp') }}</flux:select.option>
                </flux:select>
                <flux:description>{{ __('agentoom-core::admin.webhooks.channel_help') }}</flux:description>
            </flux:field>

            {{-- HTTP Webhook URL (only when channel is webhook) --}}
            @if(!$channel)
            <flux:input wire:model="url" label="{{ __('agentoom-core::admin.webhooks.url') }}" placeholder="https://example.com/webhook" required />
            @endif

            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <flux:input wire:model="secret" label="{{ __('agentoom-core::admin.webhooks.secret') }}" type="{{ $is_editing ? 'password' : 'text' }}" required />
                </div>
                <flux:button wire:click="generateSecret" variant="ghost" size="sm" icon="arrow-path">{{ __('agentoom-core::admin.webhooks.regenerate') }}</flux:button>
            </div>

            {{-- Telegram config --}}
            @if($channel === 'telegram')
            <flux:fieldset>
                <flux:legend>{{ __('agentoom-core::admin.webhooks.telegram_config') }}</flux:legend>
                <div class="space-y-4">
                    <flux:input wire:model="channelConfig.chat_id" label="{{ __('agentoom-core::admin.webhooks.telegram_chat_id') }}" placeholder="-1001234567890" />
                    <flux:description>{{ __('agentoom-core::admin.webhooks.telegram_chat_id_help') }}</flux:description>
                </div>
            </flux:fieldset>
            @endif

            {{-- WhatsApp config --}}
            @if($channel === 'whatsapp')
            <flux:fieldset>
                <flux:legend>{{ __('agentoom-core::admin.webhooks.whatsapp_config') }}</flux:legend>
                <div class="space-y-4">
                    <flux:input wire:model="channelConfig.phone_number" label="{{ __('agentoom-core::admin.webhooks.whatsapp_phone') }}" placeholder="+1234567890" />
                    <flux:description>{{ __('agentoom-core::admin.webhooks.whatsapp_phone_help') }}</flux:description>
                </div>
            </flux:fieldset>
            @endif

            <fieldset>
                <legend class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">{{ __('agentoom-core::admin.webhooks.events') }}</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach($availableEvents as $eventKey => $eventLabel)
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-zinc-200 dark:border-zinc-700 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <input type="checkbox" wire:model="selectedEvents" value="{{ $eventKey }}" class="rounded" />
                            <span class="text-sm">{{ $eventLabel }}</span>
                            <span class="text-xs text-zinc-400 ml-auto font-mono">{{ $eventKey }}</span>
                        </label>
                    @endforeach
                </div>
                @error('selectedEvents')
                    <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </fieldset>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <flux:field>
                    <flux:label>{{ __('agentoom-core::admin.webhooks.scope_to_agent') }}</flux:label>
                    <flux:select variant="listbox" searchable wire:model.live="agentId" wire:click="excludeAgents = false">
                        <flux:select.option value="">{{ __('agentoom-core::admin.webhooks.any_agent') }}</flux:select.option>
                        <flux:select.option value="0">{{ __('agentoom-core::admin.webhooks.no_agent') }}</flux:select.option>
                        @foreach($agents as $agent)
                            <flux:select.option value="{{ $agent->id }}">{{ $agent->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('agentoom-core::admin.webhooks.scope_to_agent_help') }}</flux:description>
                    <flux:error name="agentId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('agentoom-core::admin.webhooks.scope_to_pipeline') }}</flux:label>
                    <flux:select variant="listbox" searchable wire:model.live="pipelineId" wire:click="excludePipelines = false">
                        <flux:select.option value="">{{ __('agentoom-core::admin.webhooks.any_pipeline') }}</flux:select.option>
                        <flux:select.option value="0">{{ __('agentoom-core::admin.webhooks.no_pipeline') }}</flux:select.option>
                        @foreach($pipelines as $pipeline)
                            <flux:select.option value="{{ $pipeline->id }}">{{ $pipeline->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('agentoom-core::admin.webhooks.scope_to_pipeline_help') }}</flux:description>
                    <flux:error name="pipelineId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('agentoom-core::admin.webhooks.scope_to_endpoint') }}</flux:label>
                    <flux:select variant="listbox" searchable wire:model.live="endpointId">
                        <flux:select.option value="">{{ __('agentoom-core::admin.webhooks.any_endpoint') }}</flux:select.option>
                        @foreach($endpoints as $endpoint)
                            <flux:select.option value="{{ $endpoint->id }}">{{ $endpoint->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('agentoom-core::admin.webhooks.scope_to_endpoint_help') }}</flux:description>
                    <flux:error name="endpointId" />
                </flux:field>
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model="is_active" class="rounded" />
                <span class="text-sm">{{ __('agentoom-core::admin.webhooks.active') }}</span>
            </label>

            <div class="flex items-center justify-between pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button href="{{ route('admin.webhook-subscriptions.index') }}" variant="ghost">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                <div class="flex gap-2">
                    <flux:button wire:click="save(true)" variant="filled">{{ __('agentoom-core::admin.common.save_and_stay') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ $is_editing ? __('agentoom-core::admin.common.update') : __('agentoom-core::admin.common.create') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:card>
</div>
