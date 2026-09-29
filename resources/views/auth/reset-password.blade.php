@extends('agentoom-core::auth.layout')

@section('title', __('agentoom-core::auth.reset_password') . ' — ' . config('app.name'))

@section('content')
    <flux:card class="space-y-6">
        <flux:heading size="lg">{{ __('agentoom-core::auth.reset_password') }}</flux:heading>

        @if ($errors->any())
            <flux:callout color="red" icon="exclamation-circle">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </flux:callout>
        @endif

        <form method="POST" action="{{ route('password.update') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">
            <flux:fieldset>
                <flux:input
                    name="email"
                    type="email"
                    label="{{ __('agentoom-core::auth.email') }}"
                    value="{{ old('email', request()->email) }}"
                    required
                    autofocus
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
                    {{ __('agentoom-core::auth.reset_password') }}
                </flux:button>
            </flux:fieldset>
        </form>
    </flux:card>
@endsection
