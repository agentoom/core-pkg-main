@extends('agentoom-core::auth.layout')

@section('title', __('agentoom-core::auth.login') . ' — ' . config('app.name'))

@section('content')
    <flux:card class="space-y-6">
        <flux:heading size="lg">{{ __('agentoom-core::auth.login') }}</flux:heading>

        @if (session('status'))
            <flux:callout color="green" icon="check-circle">
                {{ session('status') }}
            </flux:callout>
        @endif

        @if ($errors->any())
            <flux:callout color="red" icon="exclamation-circle">
                {{ $errors->first() }}
            </flux:callout>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-6">
            @csrf
            <flux:fieldset>
                <flux:input
                    name="email"
                    type="email"
                    label="{{ __('agentoom-core::auth.email') }}"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                />
                <flux:input
                    name="password"
                    type="password"
                    label="{{ __('agentoom-core::auth.password') }}"
                    required
                    autocomplete="current-password"
                />
                <flux:checkbox name="remember" label="{{ __('agentoom-core::auth.remember_me') }}" />
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('agentoom-core::auth.login') }}
                </flux:button>
            </flux:fieldset>
        </form>

        @if (Route::has('password.request'))
            <flux:text class="text-center">
                <flux:link href="{{ route('password.request') }}">{{ __('agentoom-core::auth.forgot_password') }}</flux:link>
            </flux:text>
        @endif
        @if (Route::has('register'))
            <flux:text class="text-center text-zinc-500 dark:text-zinc-400">
                {{ __('agentoom-core::auth.dont_have_account') }} <flux:link href="{{ route('register') }}">{{ __('agentoom-core::auth.register') }}</flux:link>
            </flux:text>
        @endif

        @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::passkeys()))
            <div x-data="{ 
                authenticating: false,
                async authenticate() {
                    this.authenticating = true;
                    try {
                        const response = await window.Passkeys.verify();
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
                    Sign in with a passkey
                </flux:button>
            </div>
        @endif
    </flux:card>
@endsection
