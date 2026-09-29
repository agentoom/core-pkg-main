@extends('agentoom-core::auth.layout')

@section('title', __('agentoom-core::auth.register') . ' — ' . config('app.name'))

@section('content')
    <flux:card class="space-y-6">
        <flux:heading size="lg">{{ __('agentoom-core::auth.register') }}</flux:heading>

        @if ($errors->any())
            <flux:callout color="red" icon="exclamation-circle">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </flux:callout>
        @endif

        <form method="POST" action="{{ route('register') }}" class="space-y-6">
            @csrf
            <flux:fieldset>
                <flux:input
                    name="name"
                    type="text"
                    label="{{ __('agentoom-core::auth.name') }}"
                    value="{{ old('name') }}"
                    required
                    autofocus
                    autocomplete="name"
                />
                <flux:input
                    name="email"
                    type="email"
                    label="{{ __('agentoom-core::auth.email') }}"
                    value="{{ old('email') }}"
                    required
                    autocomplete="username"
                />
                <flux:input
                    name="password"
                    type="password"
                    label="{{ __('agentoom-core::auth.password') }}"
                    required
                    autocomplete="new-password"
                />
                <flux:input
                    name="password_confirmation"
                    type="password"
                    label="{{ __('agentoom-core::auth.confirm_password') }}"
                    required
                    autocomplete="new-password"
                />
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('agentoom-core::auth.register') }}
                </flux:button>
            </flux:fieldset>
        </form>

        @if (Route::has('login'))
            <flux:text class="text-center text-zinc-500 dark:text-zinc-400">
                {{ __('agentoom-core::auth.already_have_account') }} <flux:link href="{{ route('login') }}">{{ __('agentoom-core::auth.login') }}</flux:link>
            </flux:text>
        @endif
    </flux:card>
@endsection
