<div class="space-y-6">
    <div>
        <flux:heading size="xl">
            {{ $is_editing ? __('agentoom-core::admin.mcp_servers.edit') : __('agentoom-core::admin.mcp_servers.new') }}
        </flux:heading>
        <flux:subheading>
            {{ $is_editing ? __('agentoom-core::admin.mcp_servers.edit_subtitle') : __('agentoom-core::admin.mcp_servers.create_subtitle') }}
        </flux:subheading>
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
        <form wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 gap-4">
                <flux:input wire:model="name" label="{{ __('agentoom-core::admin.mcp_servers.name') }}" placeholder="My MCP Server" />
                <flux:input wire:model.live.debounce.500ms="url" label="{{ __('agentoom-core::admin.mcp_servers.url') }}" placeholder="https://mcp.example.com/sse" />
                
                <flux:radio.group wire:model="transport_type" label="{{ __('agentoom-core::admin.mcp_servers.transport_type') }}" variant="cards" class="flex-col">
                    <flux:radio value="sse" label="SSE (Server-Sent Events)" description="Connect via HTTP SSE stream. Recommended for long-running connections." />
                    <flux:radio value="http" label="HTTP / JSON-RPC" description="Connect via standard HTTP POST requests." />
                </flux:radio.group>

                <flux:select wire:model.live="auth_type" label="{{ __('agentoom-core::admin.mcp_servers.auth_type') }}">
                    <flux:select.option value="">{{ __('agentoom-core::admin.mcp_servers.auth_none') }}</flux:select.option>
                    <flux:select.option value="bearer">{{ __('agentoom-core::admin.mcp_servers.auth_bearer') }}</flux:select.option>
                    <flux:select.option value="api_key">{{ __('agentoom-core::admin.mcp_servers.auth_api_key') }}</flux:select.option>
                    <flux:select.option value="basic">{{ __('agentoom-core::admin.mcp_servers.auth_basic') }}</flux:select.option>
                </flux:select>

                @if($auth_type === 'bearer')
                    <flux:input wire:model="auth_token" label="{{ __('agentoom-core::admin.mcp_servers.auth_token') }}" type="password" placeholder="sk-..." />
                @endif

                @if($auth_type === 'api_key')
                    <div class="grid grid-cols-3 gap-4">
                        <flux:input wire:model="auth_header_name" label="{{ __('agentoom-core::admin.mcp_servers.auth_header_name') }}" placeholder="X-API-Key" />
                        <div class="col-span-2">
                            <flux:input wire:model="auth_token" label="{{ __('agentoom-core::admin.mcp_servers.auth_api_key_value') }}" type="password" placeholder="{{ __('agentoom-core::admin.mcp_servers.auth_api_key_placeholder') }}" />
                        </div>
                    </div>
                @endif

                @if($auth_type === 'basic')
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="auth_username" label="{{ __('agentoom-core::admin.mcp_servers.auth_username') }}" placeholder="admin" />
                        <flux:input wire:model="auth_password" label="{{ __('agentoom-core::admin.mcp_servers.auth_password') }}" type="password" placeholder="••••••••" />
                    </div>
                @endif

                <flux:select wire:model="status" label="{{ __('agentoom-core::admin.mcp_servers.status') }}">
                    <flux:select.option value="active">Active</flux:select.option>
                    <flux:select.option value="inactive">Inactive</flux:select.option>
                    <flux:select.option value="error">Error</flux:select.option>
                </flux:select>

                <flux:checkbox wire:model="overwrite" label="{{ __('agentoom-core::admin.mcp_servers.overwrite') }}" description="{{ __('agentoom-core::admin.mcp_servers.overwrite_help') }}" />
            </div>

            <div class="flex gap-2 justify-end">
                <flux:button type="button" href="{{ route('admin.mcp-servers.index') }}" wire:navigate>{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('agentoom-core::admin.common.save') }}</flux:button>
            </div>
        </form>
    </flux:card>
    
    @if($is_editing && $server)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Tools ({{ $server->tools->count() }})</flux:heading>
                </div>
                
                <div class="space-y-3 max-h-96 overflow-y-auto pr-2">
                    @forelse($server->tools as $tool)
                        <flux:card class="p-3 bg-zinc-50 dark:bg-zinc-900 border-zinc-200 dark:border-zinc-800">
                            <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $tool->remote_name }}</div>
                            @if($tool->description)
                                <div class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ $tool->description }}</div>
                            @endif
                        </flux:card>
                    @empty
                        <div class="text-zinc-500 italic py-4">No tools found for this server. Try syncing.</div>
                    @endforelse
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Resources ({{ $server->resources->count() }})</flux:heading>
                </div>

                <div class="space-y-3 max-h-96 overflow-y-auto pr-2">
                    @forelse($server->resources as $resource)
                        <flux:card class="p-3 bg-zinc-50 dark:bg-zinc-900 border-zinc-200 dark:border-zinc-800">
                            <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $resource->name }}</div>
                            <div class="text-xs font-mono text-zinc-400 mt-1 break-all">{{ $resource->mcp_uri }}</div>
                            @if($resource->description)
                                <div class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ $resource->description }}</div>
                            @endif
                        </flux:card>
                    @empty
                        <div class="text-zinc-500 italic py-4">No resources found for this server. Try syncing.</div>
                    @endforelse
                </div>
            </flux:card>
        </div>
    @endif
</div>
