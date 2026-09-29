@extends('agentoom-core::auth.layout')

@section('title', __('agentoom-core::auth.forgot_password_title') . ' — ' . config('app.name'))

@section('content')
    <flux:card class="space-y-6">
        <flux:heading size="lg">{{ __('agentoom-core::auth.forgot_password_title') }}</flux:heading>
        <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('agentoom-core::auth.forgot_password_text') }}</flux:text>

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

        <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
            @csrf
            <flux:fieldset>
                <flux:input
                    name="email"
                    type="email"
                    label="{{ __('agentoom-core::auth.email') }}"
                    value="{{ old('email') }}"
                    required
                    autofocus
                />
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('agentoom-core::auth.email_password_reset_link') }}
                </flux:button>
            </flux:fieldset>
        </form>

        <flux:text class="text-center">
            <flux:link href="{{ route('login') }}">{{ __('agentoom-core::auth.back_to_login') }}</flux:link>
        </flux:text>
    </flux:card>
@endsection
