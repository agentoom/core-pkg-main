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

    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|inter:400,500,600,700" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @fluxAppearance
</head>
<body class="bg-zinc-50 dark:bg-zinc-950 text-zinc-800 dark:text-white min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md">
        <div class="flex items-center justify-center gap-4 mb-8">
            <img src="{{ asset('images/agentoom_logo.svg') }}" class="size-12" alt="{{ config('app.name') }}">
            <span class="text-4xl font-bold font-inter tracking-tight">{{ config('app.name') }}</span>
        </div>
        @yield('content')
    </div>
    @fluxScripts
</body>
</html>
