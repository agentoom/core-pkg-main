<div class="max-w-4xl mx-auto py-10 px-6">
            <header class="mb-10">
                <flux:heading size="xl" level="1">{{ __('agentoom-core::docs.practical_examples.title') }}</flux:heading>
                <flux:subheading size="lg" class="mt-4">
                    {{ __('agentoom-core::docs.practical_examples.introduction') }}
                </flux:subheading>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach(['b2b_sales' => 'b2b-sales', 'logistics' => 'logistics', 'cybersecurity' => 'cybersecurity'] as $sector => $route)
                    <flux:card class="p-6">
                        <flux:heading size="md" level="3" class="mb-2">
                            {{ __('agentoom-core::docs.sidebar.real_life_examples.' . $sector . '.title') }}
                        </flux:heading>
                        <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400 mb-4">
                            {{ __('agentoom-core::docs.sidebar.real_life_examples.' . $sector . '.description') }}
                        </flux:text>
                        <flux:button variant="ghost" size="sm" icon-trailing="chevron-right" href="{{ route('docs.examples.' . $route, ['locale' => app()->getLocale()]) }}">
                            {{ __('agentoom-core::docs.read_more') }}
                        </flux:button>
                    </flux:card>
                @endforeach
            </div>
    </div>
