<div class="max-w-4xl mx-auto py-10 px-6">
<div class="space-y-6">
    <flux:heading size="xl">{{ __("agentoom-core::docs.features.pipelines.title") }}</flux:heading>

    <flux:text size="lg" class="text-zinc-600 dark:text-zinc-400">
        {!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e(__("agentoom-core::docs.features.pipelines.intro"))) !!}
    </flux:text>

    <flux:separator />

    <section class="space-y-4">
        <flux:heading size="lg">{{ __("agentoom-core::docs.features.pipelines.what_is.title") }}</flux:heading>
        <flux:text>
            {{ __("agentoom-core::docs.features.pipelines.what_is.content") }}
        </flux:text>
    </section>

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __("agentoom-core::docs.features.pipelines.when_to_use.title") }}</flux:heading>
        <flux:text>
            {{ __("agentoom-core::docs.features.pipelines.when_to_use.content") }}
        </flux:text>
        <ul class="list-disc list-inside space-y-2 text-zinc-600 dark:text-zinc-400">
            @foreach(__("agentoom-core::docs.features.pipelines.when_to_use.items") as $item)
                <li>{!! preg_replace(['/\*\*(.*?)\*\*/', '/→/'], ['<strong>$1</strong>', '&rarr;'], e($item)) !!}</li>
            @endforeach
        </ul>
    </flux:card>

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __("agentoom-core::docs.features.pipelines.instructions.title") }}</flux:heading>

        <div class="space-y-6">
            <div class="space-y-2">
                <flux:text weight="semibold" class="text-zinc-900 dark:text-white">{{ __("agentoom-core::docs.features.pipelines.instructions.creating.title") }}</flux:text>
                <ol class="list-decimal list-inside space-y-1 text-zinc-600 dark:text-zinc-400">
                    @foreach(__("agentoom-core::docs.features.pipelines.instructions.creating.steps") as $step)
                        <li>{!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($step)) !!}</li>
                    @endforeach
                </ol>
            </div>

            <div class="space-y-2">
                <flux:text weight="semibold" class="text-zinc-900 dark:text-white">{{ __("agentoom-core::docs.features.pipelines.instructions.running.title") }}</flux:text>
                <ol class="list-decimal list-inside space-y-1 text-zinc-600 dark:text-zinc-400">
                    @foreach(__("agentoom-core::docs.features.pipelines.instructions.running.steps") as $step)
                        <li>{!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($step)) !!}</li>
                    @endforeach
                </ol>
            </div>
        </div>
    </flux:card>

    <flux:card class="space-y-6">
        <flux:heading size="lg">{{ __("agentoom-core::docs.features.pipelines.options.title") }}</flux:heading>

        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="md">{{ __("agentoom-core::docs.features.pipelines.options.general.title") }}</flux:heading>
                <ul class="list-disc list-inside space-y-2 text-zinc-600 dark:text-zinc-400">
                    @foreach(__("agentoom-core::docs.features.pipelines.options.general.items") as $key => $item)
                        @if(is_array($item))
                            <li>{!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($item['title'])) !!}
                                <ul class="pl-6 list-disc">
                                    @foreach(Illuminate\Support\Arr::except($item, ['title']) as $subitem)
                                        <li>{!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($subitem)) !!}</li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            <li>{!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($item)) !!}</li>
                        @endif
                    @endforeach
                </ul>
            </div>

            <div class="space-y-2">
                <flux:heading size="md">{{ __("agentoom-core::docs.features.pipelines.options.variables.title") }}</flux:heading>
                <flux:text class="mb-2">{{ __("agentoom-core::docs.features.pipelines.options.variables.content") }}</flux:text>
                <ul class="list-disc list-inside space-y-1 text-zinc-600 dark:text-zinc-400">
                    @foreach(__("agentoom-core::docs.features.pipelines.options.variables.items") as $item)
                        <li>{!! preg_replace(['/\*\*(.*?)\*\*/', '/`(.*?)`/'], ['<strong>$1</strong>', '<code>$1</code>'], e($item)) !!}</li>
                    @endforeach
                </ul>
            </div>

            <div class="space-y-2">
                <flux:heading size="md">{{ __("agentoom-core::docs.features.pipelines.options.steps.title") }}</flux:heading>
                <ul class="list-disc list-inside space-y-2 text-zinc-600 dark:text-zinc-400">
                    @foreach(__("agentoom-core::docs.features.pipelines.options.steps.items") as $item)
                        @if(is_array($item))
                            <li>{!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($item['title'])) !!}
                                <ul class="pl-6 list-disc">
                                    @foreach(Illuminate\Support\Arr::except($item, ['title']) as $subitem)
                                        <li>{!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($subitem)) !!}</li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            <li>{!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($item)) !!}</li>
                        @endif
                    @endforeach
                </ul>
            </div>

            <div class="space-y-2">
                <flux:heading size="md">{{ __("agentoom-core::docs.features.pipelines.options.placeholders.title") }}</flux:heading>
                <flux:text class="mb-2">{{ __("agentoom-core::docs.features.pipelines.options.placeholders.content") }}</flux:text>
                <ul class="list-disc list-inside space-y-1 text-zinc-600 dark:text-zinc-400">
                    @foreach(__("agentoom-core::docs.features.pipelines.options.placeholders.items") as $item)
                        <li>{!! preg_replace(['/\*\*(.*?)\*\*/', '/`(.*?)`/'], ['<strong>$1</strong>', '<code>$1</code>'], e($item)) !!}</li>
                    @endforeach
                </ul>
            </div>

            <div class="space-y-2">
                <flux:heading size="md">{{ __("agentoom-core::docs.features.pipelines.options.failure.title") }}</flux:heading>
                <flux:text>{{ __("agentoom-core::docs.features.pipelines.options.failure.content") }}</flux:text>
            </div>
        </div>
    </flux:card>

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __("agentoom-core::docs.features.pipelines.use_cases.title") }}</flux:heading>
        <div class="space-y-2">
            <flux:text weight="semibold" class="text-zinc-900 dark:text-white">{{ __("agentoom-core::docs.features.pipelines.use_cases.seo.title") }}</flux:text>
            <ol class="list-decimal list-inside space-y-1 text-zinc-600 dark:text-zinc-400">
                @foreach(__("agentoom-core::docs.features.pipelines.use_cases.seo.steps") as $step)
                    <li>{!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($step)) !!}</li>
                @endforeach
            </ol>
        </div>
    </flux:card>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __("agentoom-core::docs.features.pipelines.mistakes.title") }}</flux:heading>
            <ul class="list-disc list-inside space-y-2 text-zinc-600 dark:text-zinc-400">
                @foreach(__("agentoom-core::docs.features.pipelines.mistakes.items") as $item)
                    <li>{!! preg_replace(['/\*\*(.*?)\*\*/', '/`(.*?)`/'], ['<strong>$1</strong>', '<code>$1</code>'], e($item)) !!}</li>
                @endforeach
            </ul>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __("agentoom-core::docs.features.pipelines.troubleshooting.title") }}</flux:heading>
            <ul class="list-disc list-inside space-y-2 text-zinc-600 dark:text-zinc-400">
                @foreach(__("agentoom-core::docs.features.pipelines.troubleshooting.items") as $item)
                    <li>{!! preg_replace(['/\*\*(.*?)\*\*/', '/`(.*?)`/'], ['<strong>$1</strong>', '<code>$1</code>'], e($item)) !!}</li>
                @endforeach
            </ul>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __("agentoom-core::docs.features.pipelines.faq.title") }}</flux:heading>
        <div class="space-y-4">
            @foreach(__("agentoom-core::docs.features.pipelines.faq.items") as $item)
                <div>
                    <flux:text weight="semibold" class="text-zinc-900 dark:text-white">{{ $item['q'] }}</flux:text>
                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">{{ $item['a'] }}</flux:text>
                </div>
            @endforeach
        </div>
    </flux:card>

    <flux:card class="space-y-4 bg-zinc-50 dark:bg-zinc-900/50">
        <flux:heading size="lg">{{ __("agentoom-core::docs.features.pipelines.best_practices.title") }}</flux:heading>
        <ul class="list-disc list-inside space-y-2 text-zinc-600 dark:text-zinc-400">
            @foreach(__("agentoom-core::docs.features.pipelines.best_practices.items") as $item)
                <li>{!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($item)) !!}</li>
            @endforeach
        </ul>
    </flux:card>

    <div class="flex items-center pt-8 border-t border-zinc-200 dark:border-zinc-700">
        <flux:button href="{{ route('docs.index', ['locale' => app()->getLocale()]) }}" icon="arrow-left" variant="ghost">{{ __("agentoom-core::docs.back_to_overview") }}</flux:button>
    </div>
</div>
</div>
