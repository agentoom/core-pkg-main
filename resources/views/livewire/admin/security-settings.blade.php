<div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
    <div class="space-y-6">
        <!-- Two Factor Authentication -->
        <flux:card>
            <div class="flex justify-between items-center mb-6">
                <div>
                    <flux:heading size="lg">Two Factor Authentication</flux:heading>
                    <flux:subheading>Add additional security to your account using two factor authentication.</flux:subheading>
                </div>
                <flux:badge :color="$user->two_factor_confirmed_at ? 'green' : 'zinc'">
                    {{ $user->two_factor_confirmed_at ? 'Enabled' : 'Disabled' }}
                </flux:badge>
            </div>

            @if (! $user->two_factor_secret)
                <div class="mt-4">
                    <flux:button wire:click="enableTwoFactorAuthentication" variant="primary">
                        Enable Two Factor Authentication
                    </flux:button>
                </div>
            @else
                @if ($showingQrCode)
                    <div class="mt-4 max-w-xl text-sm text-zinc-600 dark:text-zinc-400">
                        <p class="font-semibold">
                            Two factor authentication is now enabled. Scan the following QR code using your phone's authenticator application.
                        </p>
                    </div>

                    <div class="mt-4">
                        <div class="flex justify-center bg-white">
                            <span class="p-2">{!! $user->twoFactorQrCodeSvg() !!}</span>
                        </div>
                    </div>

                    <div class="mt-4 max-w-xl text-sm text-zinc-600 dark:text-zinc-400">
                        <p class="font-semibold">
                            Setup Key: {{ decrypt($user->two_factor_secret) }}
                        </p>
                    </div>

                    @if ($showingConfirmation)
                        <div class="mt-4">
                            <flux:input wire:model="code" label="Code" placeholder="Enter 6-digit code" />
                            <flux:button wire:click="confirmTwoFactorAuthentication" class="mt-2">
                                Confirm
                            </flux:button>
                        </div>
                    @else
                        <div class="mt-4">
                            <flux:button wire:click="$set('showingConfirmation', true)" variant="primary">
                                Confirm Installation
                            </flux:button>
                        </div>
                    @endif
                @endif

                @if ($showingRecoveryCodes)
                    <div class="mt-4 max-w-xl text-sm text-zinc-600 dark:text-zinc-400">
                        <p class="font-semibold">
                            Store these recovery codes in a secure password manager. They can be used to recover access to your account if your two factor authentication device is lost.
                        </p>
                    </div>

                    <div class="grid gap-1 max-w-xl mt-4 px-4 py-4 font-mono text-sm bg-zinc-100 dark:bg-zinc-900 rounded-lg">
                        @foreach (json_decode(decrypt($user->two_factor_recovery_codes), true) as $code)
                            <div>{{ $code }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-6 flex gap-4">
                    @if (! $showingRecoveryCodes)
                        <flux:button wire:click="$set('showingRecoveryCodes', true)">
                            Show Recovery Codes
                        </flux:button>
                    @endif

                    <flux:button wire:click="regenerateRecoveryCodes">
                        Regenerate Recovery Codes
                    </flux:button>

                    <flux:button wire:click="disableTwoFactorAuthentication" variant="danger">
                        Disable Two Factor Authentication
                    </flux:button>
                </div>
            @endif
        </flux:card>

        <!-- Passkeys -->
        <flux:card>
            <div class="flex justify-between items-center mb-6">
                <div>
                    <flux:heading size="lg">Passkeys</flux:heading>
                    <flux:subheading>Passkeys allow for a more secure, passwordless login experience.</flux:subheading>
                </div>
            </div>

            <div class="space-y-4" wire:key="passkeys-list">
                @forelse ($user->passkeys as $passkey)
                    <div class="flex items-center justify-between p-4 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center gap-3">
                            <flux:icon icon="key" class="size-5 text-zinc-400" />
                            <div>
                                <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $passkey->name }}</div>
                                <div class="text-xs text-zinc-500">Added {{ $passkey->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                        <flux:button wire:click="deletePasskey({{ $passkey->id }})" icon="trash" variant="ghost" size="sm" />
                    </div>
                @empty
                    <div class="text-sm text-zinc-500 py-4">
                        You haven't added any passkeys yet.
                    </div>
                @endforelse
            </div>

            <div class="mt-6" x-data="{
                registering: false,
                passkeyName: '',
                async register() {
                    if (! this.passkeyName) return;

                    this.registering = true;
                    try {
                        await window.Passkeys.register({ name: this.passkeyName });
                        this.passkeyName = '';
                        $flux.modal('register-passkey-{{ $this->getId() }}').hide();
                        await $wire.passkeyRegistered();
                    } catch (e) {
                        console.error(e);
                        $dispatch('toast-show', { slots: { text: 'Failed to register passkey: ' + e.message }, dataset: { variant: 'danger' } });
                    } finally {
                        this.registering = false;
                    }
                }
            }">
                <flux:modal name="register-passkey-{{ $this->getId() }}" class="md:w-[25rem]">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">Register Passkey</flux:heading>
                            <flux:subheading>Enter a name for this passkey to help you identify it later.</flux:subheading>
                        </div>

                        <flux:input x-model="passkeyName" label="Passkey Name" placeholder="e.g. My MacBook Pro" />

                        <div class="flex">
                            <flux:spacer />
                            <flux:button x-on:click="$flux.modal('register-passkey-{{ $this->getId() }}').hide()" variant="ghost" class="mr-2">Cancel</flux:button>
                            <flux:button x-on:click="register" x-bind:loading="registering" variant="primary">Register</flux:button>
                        </div>
                    </div>
                </flux:modal>

                <flux:button x-on:click="$flux.modal('register-passkey-{{ $this->getId() }}').show()" variant="primary">
                    Register New Passkey
                </flux:button>
            </div>
        </flux:card>
    </div>
</div>
