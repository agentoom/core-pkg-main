<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16x16.png') }}">
    <link rel="shortcut icon" href="{{ asset('images/favicon.ico') }}">
    <link rel="manifest" href="{{ asset('images/site.webmanifest') }}">

    <title>@yield('title', __('agentoom-core::admin.dashboard')) — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance

    <!-- TOAST UI Editor -->
    <link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css" />
    <link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/theme/toastui-editor-dark.min.css" />
    <script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800 antialiased">
    @include('agentoom-core::layouts.sidebar')

    {{-- Header — shared navbar partial --}}
    <flux:header sticky class="block! bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
        @include('agentoom-core::layouts.navbar')
    </flux:header>
    <flux:main>
        <livewire:agentoom-core::admin.ai-credit-warning-banner />
        @isset($slot)
            {{ $slot }}
        @else
            @yield('content')
        @endisset
    </flux:main>

    <flux:toast />
    @fluxScripts

    @if (session('success'))
        <div x-data x-init="$dispatch('toast-show', { slots: { text: @js(session('success')) }, dataset: { variant: 'success' } })"></div>
    @endif

    @if (session('error'))
        <div x-data x-init="$dispatch('toast-show', { slots: { text: @js(session('error')) }, dataset: { variant: 'danger' } })"></div>
    @endif
</body>
</html>
