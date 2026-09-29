@extends('agentoom-core::auth.layout')

@section('title', __('agentoom-core::auth.confirm_password') . ' — ' . config('app.name'))

@section('content')
    <flux:card class="space-y-6">
        <flux:heading size="lg">{{ __('agentoom-core::auth.confirm_password') }}</flux:heading>
        <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('agentoom-core::auth.confirm_password_to_continue') }}</flux:text>

        @if ($errors->any())
            <flux:callout color="red" icon="exclamation-circle">
                {{ $errors->first() }}
            </flux:callout>
        @endif

        <form method="POST" action="{{ route('password.confirm') }}" class="space-y-6">
            @csrf
            <flux:fieldset>
                <flux:input
                    name="password"
                    type="password"
                    label="{{ __('agentoom-core::auth.password') }}"
                    required
                    autocomplete="current-password"
                />
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('agentoom-core::auth.confirm') }}
                </flux:button>
            </flux:fieldset>
        </form>

        @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::passkeys()))
            <div x-data="{ 
                authenticating: false,
                async authenticate() {
                    this.authenticating = true;
                    try {
                        const response = await window.Passkeys.verify({
                            routes: {
                                options: '/passkeys/confirm/options',
                                submit: '/passkeys/confirm',
                            },
                        });
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        }
                    } catch (e) {
                        console.error(e);
                    } finally {
                        this.authenticating = false;
                    }
                }
            }">
                <flux:separator content="OR" />
                <flux:button x-on:click="authenticate" x-bind:loading="authenticating" class="w-full mt-4" icon="finger-print">
                    Confirm with a passkey
                </flux:button>
            </div>
        @endif
    </flux:card>
@endsection
