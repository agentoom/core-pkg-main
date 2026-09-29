@extends('agentoom-core::auth.layout')

@section('title', __('agentoom-core::auth.two_factor_challenge') . ' — ' . config('app.name'))

@section('content')
    <flux:card class="space-y-6">
        <flux:heading size="lg">{{ __('agentoom-core::auth.two_factor_title') }}</flux:heading>

        @if ($errors->any())
            <flux:callout color="red" icon="exclamation-circle">
                {{ $errors->first() }}
            </flux:callout>
        @endif

        <flux:fieldset legend="{{ __('agentoom-core::auth.authentication_code') }}">
            <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-4 mb-6">
                @csrf
                <flux:input
                    name="code"
                    type="text"
                    label="{{ __('agentoom-core::auth.code') }}"
                    inputmode="numeric"
                    autofocus
                    autocomplete="one-time-code"
                    placeholder="000000"
                />
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('agentoom-core::auth.verify') }}
                </flux:button>
            </form>
        </flux:fieldset>

        <flux:separator />

        <flux:fieldset legend="{{ __('agentoom-core::auth.recovery_code') }}">
            <flux:text class="text-zinc-500 dark:text-zinc-400 mb-4">{{ __('agentoom-core::auth.use_recovery_code') }}</flux:text>
            <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-4">
                @csrf
                <flux:input
                    name="recovery_code"
                    type="text"
                    label="{{ __('agentoom-core::auth.recovery_code') }}"
                    autocomplete="one-time-code"
                    placeholder="xxxxxxxx-xxxxxxxx"
                />
                <flux:button type="submit" variant="outline" class="w-full">
                    {{ __('agentoom-core::auth.verify_with_recovery_code') }}
                </flux:button>
            </form>
        </flux:fieldset>
    </flux:card>
@endsection
