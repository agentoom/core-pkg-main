<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('agentoom-core::admin.mcp_servers.title') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.mcp_servers.subtitle') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-4">
            <x-agentoom-core::resource-usage resource="mcp_servers" />

            <flux:button variant="primary" icon="plus" :href="route('admin.mcp-servers.create')" wire:navigate :disabled="app(\Agentoom\Core\Services\ResourceLimitManager::class)->isLimitReached('mcp_servers')">
                {{ __('agentoom-core::admin.mcp_servers.new') }}
            </flux:button>
        </div>
    </div>

    @if (session('success'))
        <flux:callout variant="success" class="mb-4">{{ session('success') }}</flux:callout>
    @endif

    @if (session('error'))
        <flux:callout variant="danger" class="mb-4">{{ session('error') }}</flux:callout>
    @endif

    @if (session('warning'))
        <flux:callout variant="warning" class="mb-4">{{ session('warning') }}</flux:callout>
    @endif

    <flux:card>
        <flux:table :paginate="$servers">
            <flux:table.columns>
                <flux:table.column>{{ __('agentoom-core::admin.mcp_servers.name') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.mcp_servers.url') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.mcp_servers.transport_type') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.mcp_servers.status') }}</flux:table.column>
                <flux:table.column>Tools</flux:table.column>
                <flux:table.column>Resources</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.mcp_servers.last_synced_at') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($servers as $server)
                    <flux:table.row>
                        <flux:table.cell>{{ $server->name }}</flux:table.cell>
                        <flux:table.cell>{{ $server->url }}</flux:table.cell>
                        <flux:table.cell>{{ strtoupper($server->transport_type) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="match($server->status) { 'active' => 'green', 'error' => 'red', default => 'zinc' }">
                                {{ ucfirst($server->status) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge variant="outline">{{ $server->tools_count }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge variant="outline">{{ $server->resources_count }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $server->last_synced_at?->diffForHumans() ?: 'Never' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button wire:click="showDetails({{ $server->id }})" variant="ghost" icon="eye"></flux:button>
                            <flux:button wire:click="sync({{ $server->id }})" variant="primary" icon="arrow-path" wire:loading.attr="disabled" wire:target="sync({{ $server->id }})">
                                <span wire:loading.remove wire:target="sync({{ $server->id }})">{{ __('agentoom-core::admin.mcp_servers.sync') }}</span>
                                <span wire:loading wire:target="sync({{ $server->id }})">{{ __('agentoom-core::admin.mcp_servers.syncing') }}</span>
                            </flux:button>
                            <flux:button href="{{ route('admin.mcp-servers.edit', $server) }}" variant="primary" color="amber" icon="pencil" wire:navigate>{{ __('agentoom-core::admin.common.edit') }}</flux:button>
                            <flux:button variant="danger" icon="trash" wire:click="showDeleteModal({{ $server->id }})">{{ __('agentoom-core::admin.common.delete') }}</flux:button>
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
                {{ __('agentoom-core::admin.mcp_servers.delete_confirmation_title') }}
            </flux:heading>

            <flux:subheading class="mb-6 text-gray-600">
                {{ __('agentoom-core::admin.mcp_servers.delete_confirmation_text') }}
            </flux:subheading>

            <div class="space-y-4 text-left mb-6">
                <flux:checkbox wire:model="delete_agents" :label="__('agentoom-core::admin.mcp_servers.delete_agents')" />
                <flux:checkbox wire:model="delete_skills" :label="__('agentoom-core::admin.mcp_servers.delete_skills')" />
                
                <flux:text size="sm" class="flex items-start gap-2 italic text-zinc-500 dark:text-zinc-400">
                    <flux:icon.information-circle class="h-4 w-4 mt-0.5" />
                    {{ __('agentoom-core::admin.mcp_servers.delete_tools_warning') }}
                </flux:text>
            </div>
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

    <flux:modal wire:model="show_details_modal" class="max-w-4xl">
        @if($selected_server)
            <div class="space-y-6">
                <div>
                    <flux:heading size="xl">{{ $selected_server->name }}</flux:heading>
                    <flux:subheading>{{ $selected_server->url }}</flux:subheading>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <flux:heading size="lg" class="mb-4">Tools ({{ $selected_server->tools->count() }})</flux:heading>
                        <div class="space-y-3 max-h-96 overflow-y-auto pr-2">
                            @forelse($selected_server->tools as $tool)
                                <flux:card class="p-3 bg-zinc-50 dark:bg-zinc-900 border-zinc-200 dark:border-zinc-800">
                                    <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $tool->remote_name }}</div>
                                    @if($tool->description)
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ $tool->description }}</div>
                                    @endif
                                </flux:card>
                            @empty
                                <div class="text-zinc-500 italic py-4">No tools found for this server.</div>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <flux:heading size="lg" class="mb-4">Resources ({{ $selected_server->resources->count() }})</flux:heading>
                        <div class="space-y-3 max-h-96 overflow-y-auto pr-2">
                            @forelse($selected_server->resources as $resource)
                                <flux:card class="p-3 bg-zinc-50 dark:bg-zinc-900 border-zinc-200 dark:border-zinc-800">
                                    <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $resource->name }}</div>
                                    <div class="text-xs font-mono text-zinc-400 mt-1 break-all">{{ $resource->mcp_uri }}</div>
                                    @if($resource->description)
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ $resource->description }}</div>
                                    @endif
                                </flux:card>
                            @empty
                                <div class="text-zinc-500 italic py-4">No resources found for this server.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <flux:button type="button" wire:click="$set('show_details_modal', false)">
                        Close
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:card class="mt-6">
        <flux:heading size="lg" class="mb-4">{{ __('agentoom-core::admin.mcp_servers.mcp_api_section') }}</flux:heading>

        <div class="space-y-6">
            <div>
                <div class="flex items-center gap-4 mb-2">
                    <div class="flex-1">
                        <flux:label>{{ __('agentoom-core::admin.mcp_servers.mcp_api_token') }}</flux:label>
                        <flux:description>{{ __('agentoom-core::admin.mcp_servers.mcp_api_token_description') }}</flux:description>
                    </div>
                    <flux:button wire:click="regenerateMcpApiToken" variant="primary" icon="arrow-path" wire:loading.attr="disabled">
                        {{ __('agentoom-core::admin.mcp_servers.regenerate_token') }}
                    </flux:button>
                </div>
                @if($mcp_api_token)
                    <div class="flex items-center gap-2 mt-2">
                        <code class="bg-zinc-100 dark:bg-zinc-800 px-3 py-1.5 rounded text-sm font-mono break-all">{{ $mcp_api_token }}</code>
                        <flux:button
                            x-data="{ copied: false }"
                            x-on:click="navigator.clipboard.writeText('{{ $mcp_api_token }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            variant="ghost"
                            icon="clipboard"
                            size="sm"
                        >
                            <span x-show="!copied">Copy</span>
                            <span x-show="copied" x-cloak>Copied!</span>
                        </flux:button>
                    </div>
                @else
                    <flux:text class="text-zinc-500 dark:text-zinc-400 italic mt-2">
                        No token generated yet. Click "Regenerate Token" to create one.
                    </flux:text>
                @endif
            </div>

            <div>
                <flux:heading size="base" class="mb-2">{{ __('agentoom-core::admin.mcp_servers.endpoint_info') }}</flux:heading>
                <flux:description class="mb-3">{{ __('agentoom-core::admin.mcp_servers.endpoint_info_description') }}</flux:description>

                <div class="space-y-3">
                    <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-3 border border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center gap-2 mb-1">
                            <flux:badge variant="outline" color="green">POST</flux:badge>
                            <flux:text class="font-medium">{{ __('agentoom-core::admin.mcp_servers.endpoint_costs') }}</flux:text>
                        </div>
                        <code class="text-xs font-mono break-all text-zinc-600 dark:text-zinc-400">{{ url('/api/mcp/costs') }}</code>
                        <flux:description class="mt-1">{{ __('agentoom-core::admin.mcp_servers.endpoint_costs_description') }}</flux:description>
                    </div>

                    <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-3 border border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center gap-2 mb-1">
                            <flux:badge variant="outline" color="blue">GET / POST</flux:badge>
                            <flux:text class="font-medium">{{ __('agentoom-core::admin.mcp_servers.endpoint_resources') }}</flux:text>
                        </div>
                        <code class="text-xs font-mono break-all text-zinc-600 dark:text-zinc-400">{{ url('/api/mcp/resources') }}</code>
                        <flux:description class="mt-1">{{ __('agentoom-core::admin.mcp_servers.endpoint_resources_description') }}</flux:description>
                    </div>
                </div>
            </div>
        </div>
    </flux:card>
</div>
