<div class="max-w-4xl mx-auto py-10 px-6">
<div class="space-y-6">
    <flux:heading size="xl">{{ __('agentoom-core::docs.sidebar.features.agent_awareness') }}</flux:heading>

    <flux:text size="lg" class="text-zinc-600 dark:text-zinc-400">
        {{ __('agentoom-core::docs.features.agent_awareness.description') }}
    </flux:text>

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('agentoom-core::docs.features.how_to_use_title', ['title' => __('agentoom-core::docs.sidebar.features.agent_awareness')]) }}</flux:heading>
        <flux:text>
            {{ __('agentoom-core::docs.features.agent_awareness.how_to_use') }}
        </flux:text>
    </flux:card>

    <div class="grid grid-cols-1 gap-6">
        @foreach(__('agentoom-core::docs.features.agent_awareness.sections') as $key => $section)
            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ $section['title'] }}</flux:heading>
                <flux:text>
                    {{ $section['content'] }}
                </flux:text>
            </flux:card>
        @endforeach
    </div>

    <div class="flex justify-between items-center pt-8 border-t border-zinc-200 dark:border-zinc-700">
        <flux:button href="{{ route('docs.index', ['locale' => app()->getLocale()]) }}" icon="arrow-left" variant="ghost">{{ __("agentoom-core::docs.back_to_overview") }}</flux:button>
    </div>
</div>
</div>
