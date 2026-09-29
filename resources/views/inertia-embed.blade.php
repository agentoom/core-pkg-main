<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|inter:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
    @inertiaHead

    {{-- Fix the height chain: Inertia's #app wrapper needs explicit height --}}
    <style>
        #app {
            height: 100%;
        }
    </style>
</head>
<body class="h-dvh bg-zinc-50 dark:bg-zinc-950 antialiased">
    <div id="embed-app" class="h-full p-3">
        @inertia
    </div>

    @fluxScripts
</body>
</html>
