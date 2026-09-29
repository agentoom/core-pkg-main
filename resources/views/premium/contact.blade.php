@extends('agentoom-core::layouts.admin')

@section('title', __('agentoom-core::admin.premium.contact_title'))
@section('headerTitle', __('agentoom-core::admin.premium.contact_title'))


@section('content')
    <div class="mx-auto max-w-2xl px-4 py-16 text-center">
        <div class="mx-auto mb-6 flex size-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
            <flux:icon.sparkles class="size-8 text-amber-500" />
        </div>

        <flux:heading size="xl" level="1" class="mb-3">
            {{ __('agentoom-core::admin.premium.contact_title') }}
        </flux:heading>

        <flux:text size="lg" class="mb-8 text-zinc-600 dark:text-zinc-400">
            {{ __('agentoom-core::admin.premium.contact_body') }}
        </flux:text>

        <div class="flex flex-col items-center justify-center gap-3 sm:flex-row">
            <flux:button href="https://agentoom.com" target="_blank" variant="primary" color="cyan" icon="arrow-top-right-on-square">
                {{ __('agentoom-core::admin.premium.visit_website') }}
            </flux:button>

            <flux:button href="mailto:contact@agentoom.com" variant="outline" icon="envelope">
                {{ __('agentoom-core::admin.premium.email_us') }}
            </flux:button>
        </div>

        <flux:text size="sm" class="mt-8 text-zinc-500 dark:text-zinc-500">
            <a href="https://agentoom.com" target="_blank" class="hover:underline">agentoom.com</a>
            &middot;
            <a href="mailto:contact@agentoom.com" class="hover:underline">contact@agentoom.com</a>
        </flux:text>
    </div>
@endsection
