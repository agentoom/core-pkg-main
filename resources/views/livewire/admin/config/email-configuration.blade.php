<div class="space-y-6">
    <flux:heading size="xl" level="1">{{ __('agentoom-core::admin.integrations.title') }}</flux:heading>
    <flux:subheading size="lg" class="mb-6">{{ __('agentoom-core::admin.integrations.subtitle') }}</flux:subheading>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Gmail --}}
        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('agentoom-core::admin.integrations.gmail') }}</flux:heading>
                <flux:badge :color="$gmail['status'] === 'connected' ? 'green' : 'zinc'">
                    {{ __('agentoom-core::admin.integrations.' . $gmail['status']) }}
                </flux:badge>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="gmail.client_id" :label="__('agentoom-core::admin.integrations.client_id')" placeholder="Client ID" autocomplete="off" />
                <flux:input wire:model="gmail.client_secret" type="password" :label="__('agentoom-core::admin.integrations.client_secret')" placeholder="••••••••" autocomplete="new-password" />
                <flux:textarea wire:model="gmail.service_account_json" :label="__('agentoom-core::admin.integrations.service_account_json')" placeholder='{ "type": "service_account", ... }' />

                <div class="space-y-1">
                    <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('agentoom-core::admin.integrations.redirect_uri') }}</p>
                    <div class="p-2 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-md text-xs font-mono break-all select-all text-zinc-600 dark:text-zinc-400">
                        {{ $this->getRedirectUri('gmail') }}
                    </div>
                </div>

                @if($gmail['status'] === 'connected')
                    <div class="text-sm text-zinc-500 space-y-1">
                        <p><strong>{{ __('agentoom-core::admin.integrations.authorized_email') }}:</strong> {{ $gmail['authorized_email'] ?? 'N/A' }}</p>
                        <p><strong>{{ __('agentoom-core::admin.integrations.expires_at') }}:</strong> {{ $gmail['expires_at'] ?? 'N/A' }}</p>
                    </div>
                @endif

                <div class="flex gap-2">
                    <flux:button wire:click="save('gmail')" variant="outline">{{ __('agentoom-core::admin.integrations.save_credentials') }}</flux:button>
                    @if($gmail['status'] === 'connected')
                        <flux:button wire:click="disconnect('gmail')" variant="danger">{{ __('agentoom-core::admin.integrations.disconnect') }}</flux:button>
                    @else
                        <flux:button wire:click="connect('gmail')" variant="primary">{{ __('agentoom-core::admin.integrations.connect') }}</flux:button>
                    @endif
                </div>

                @if (session()->has('gmail_success'))
                    <div class="text-sm text-green-600">{{ session('gmail_success') }}</div>
                @endif
            </div>
        </flux:card>

        {{-- SMTP --}}
        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('agentoom-core::admin.integrations.smtp') }}</flux:heading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="smtp.mail_host" :label="__('agentoom-core::admin.integrations.mail_host')" placeholder="smtp.mailtrap.io" autocomplete="off" />
                <flux:input wire:model="smtp.mail_port" :label="__('agentoom-core::admin.integrations.mail_port')" placeholder="587" autocomplete="off" />
                <flux:input wire:model="smtp.mail_username" :label="__('agentoom-core::admin.integrations.mail_username')" placeholder="username" autocomplete="off" />
                <flux:input wire:model="smtp.mail_password" type="password" :label="__('agentoom-core::admin.integrations.mail_password')" placeholder="••••••••" autocomplete="new-password" />
                <flux:select wire:model="smtp.mail_encryption" :label="__('agentoom-core::admin.integrations.mail_encryption')">
                    <flux:select.option value="tls">{{ __('agentoom-core::admin.configs.encryption_tls') }}</flux:select.option>
                    <flux:select.option value="ssl">{{ __('agentoom-core::admin.configs.encryption_ssl') }}</flux:select.option>
                    <flux:select.option value="none">{{ __('agentoom-core::admin.configs.encryption_none') }}</flux:select.option>
                </flux:select>
                <flux:input wire:model="smtp.mail_from_address" :label="__('agentoom-core::admin.integrations.mail_from_address')" placeholder="no-reply@example.com" autocomplete="off" />
                <flux:input wire:model="smtp.mail_from_name" :label="__('agentoom-core::admin.integrations.mail_from_name')" placeholder="Agentoom" autocomplete="off" />

                <div class="flex gap-2">
                    <flux:button wire:click="save('smtp')" variant="primary">{{ __('agentoom-core::admin.integrations.save_credentials') }}</flux:button>
                </div>

                @if (session()->has('smtp_success'))
                    <div class="text-sm text-green-600">{{ session('smtp_success') }}</div>
                @endif
            </div>
        </flux:card>

        {{-- IMAP --}}
        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('agentoom-core::admin.integrations.imap') }}</flux:heading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="imap.mail_imap_host" :label="__('agentoom-core::admin.integrations.imap_host')" placeholder="imap.mailtrap.io" autocomplete="off" />
                <flux:input wire:model="imap.mail_imap_port" :label="__('agentoom-core::admin.integrations.imap_port')" placeholder="993" autocomplete="off" />
                <flux:input wire:model="imap.mail_imap_username" :label="__('agentoom-core::admin.integrations.imap_username')" placeholder="username" autocomplete="off" />
                <flux:input wire:model="imap.mail_imap_password" type="password" :label="__('agentoom-core::admin.integrations.imap_password')" placeholder="••••••••" autocomplete="new-password" />
                <flux:select wire:model="imap.mail_imap_encryption" :label="__('agentoom-core::admin.integrations.imap_encryption')">
                    <flux:select.option value="ssl">{{ __('agentoom-core::admin.configs.encryption_ssl') }}</flux:select.option>
                    <flux:select.option value="tls">{{ __('agentoom-core::admin.configs.encryption_tls') }}</flux:select.option>
                    <flux:select.option value="none">{{ __('agentoom-core::admin.configs.encryption_none') }}</flux:select.option>
                </flux:select>

                <div class="flex gap-2">
                    <flux:button wire:click="save('imap')" variant="primary">{{ __('agentoom-core::admin.integrations.save_credentials') }}</flux:button>
                </div>

                @if (session()->has('imap_success'))
                    <div class="text-sm text-green-600">{{ session('imap_success') }}</div>
                @endif
            </div>
        </flux:card>
    </div>
</div>
