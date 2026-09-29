<div class="max-w-4xl mx-auto py-10 px-6">
<div class="space-y-6">
    <flux:heading size="xl">{{ __('agentoom-core::docs.features.security.title') }}</flux:heading>

    <flux:text size="lg" class="text-zinc-600 dark:text-zinc-400">
        {{ __('agentoom-core::docs.features.security.description') }}
    </flux:text>

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('agentoom-core::docs.features.how_to_use_title', ['title' => __('agentoom-core::docs.features.security.title')]) }}</flux:heading>
        <flux:text>
            {{ __('agentoom-core::docs.features.security.how_to_use') }}
        </flux:text>
    </flux:card>

    <flux:card class="space-y-4 bg-zinc-50 dark:bg-zinc-900/50">
        <flux:heading size="lg">{{ __('agentoom-core::docs.features.how_it_works_title', ['title' => __('agentoom-core::docs.features.security.title')]) }}</flux:heading>
        <flux:text>
            {{ __('agentoom-core::docs.features.security.how_it_works') }}
        </flux:text>
    </flux:card>

    <flux:card class="space-y-4 border-l-4 border-blue-500 bg-blue-50/10 dark:bg-blue-900/10">
        <flux:heading size="lg">{{ __('agentoom-core::docs.features.hitl_title') }}</flux:heading>
        <flux:text>
            {{ __('agentoom-core::docs.features.security.human_in_the_loop') }}
        </flux:text>
    </flux:card>

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('agentoom-core::docs.features.configuration_title') }}</flux:heading>
        <flux:text>
            {{ __('agentoom-core::docs.features.security.configuration') }}
        </flux:text>
    </flux:card>

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('agentoom-core::docs.features.options_title') }}</flux:heading>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach(__('agentoom-core::docs.features.security.options') as $option => $description)
                <div class="space-y-1">
                    <flux:text weight="semibold" class="text-zinc-900 dark:text-white">{{ $option }}</flux:text>
                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">{{ $description }}</flux:text>
                </div>
            @endforeach
        </div>
    </flux:card>

    <div class="flex items-center pt-8 border-t border-zinc-200 dark:border-zinc-700">
        <flux:button href="{{ route('docs.index', ['locale' => app()->getLocale()]) }}" icon="arrow-left" variant="ghost">{{ __("agentoom-core::docs.back_to_overview") }}</flux:button>
    </div>
</div>

</div>
