@extends('agentoom-core::layouts.admin')

@section('title', __('agentoom-core::admin.dashboard'))
@section('headerTitle', __('agentoom-core::admin.dashboard'))

@section('content')
    <flux:card class="space-y-4">
        <flux:heading size="base">{{ __('agentoom-core::admin.common.welcome_back') }}</flux:heading>
        <flux:text>
            {{ __('agentoom-core::admin.common.logged_in_as', ['email' => auth()->user()->email]) }}
        </flux:text>
    </flux:card>
@endsection
