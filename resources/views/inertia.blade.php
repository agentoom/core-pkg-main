<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16x16.png') }}">
    <link rel="shortcut icon" href="{{ asset('images/favicon.ico') }}">
    <link rel="manifest" href="{{ asset('images/site.webmanifest') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|inter:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
    @inertiaHead
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800 antialiased">
    @include('agentoom-core::layouts.sidebar')

    {{-- Header — shared navbar partial --}}
    <flux:header sticky class="block! bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
        @include('agentoom-core::layouts.navbar')
    </flux:header>
    <flux:main>
        <livewire:agentoom-core::admin.ai-credit-warning-banner />
        @inertia
    </flux:main>

    <flux:toast />

    <script>
        // Patch Flux UIClose to gracefully handle missing button child
        (() => {
            const origDefine = customElements.define.bind(customElements);
            customElements.define = function(name, constructor, options) {
                if (name === 'ui-close' && constructor.prototype.mount) {
                    const origMount = constructor.prototype.mount;
                    constructor.prototype.mount = function() {
                        if (!this.querySelector('button,ui-button')) {
                            return;
                        }
                        origMount.call(this);
                    };
                }
                return origDefine(name, constructor, options);
            };
        })();
    </script>

    @fluxScripts

    @if (session('success'))
        <div x-data x-init="$dispatch('toast-show', { slots: { text: @js(session('success')) }, dataset: { variant: 'success' } })"></div>
    @endif

    @if (session('error'))
        <div x-data x-init="$dispatch('toast-show', { slots: { text: @js(session('error')) }, dataset: { variant: 'danger' } })"></div>
    @endif
</body>
</html>
