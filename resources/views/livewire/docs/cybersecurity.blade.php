<div class="max-w-4xl mx-auto py-10 px-6">
            <header class="mb-10">
                <flux:heading size="xl" level="1">{{ __('agentoom-core::docs.sidebar.real_life_examples.cybersecurity.title') }}</flux:heading>
                <flux:subheading size="lg" class="mt-4">
                    <span class="font-bold">{{ __('agentoom-core::docs.sidebar.real_life_examples.cybersecurity.subtitle') }}</span> — {{ __('agentoom-core::docs.sidebar.real_life_examples.cybersecurity.description') }}
                </flux:subheading>
            </header>

            <div class="space-y-12">
                <section>
                    <flux:heading size="lg" level="2" class="mb-6">{{ __('agentoom-core::docs.sidebar.real_life_examples.cybersecurity.incident_response.title') }}</flux:heading>

                    <flux:card class="p-6 space-y-4">
                        <div>
                            <flux:text size="sm" class="font-bold text-zinc-900 dark:text-white uppercase tracking-wider">{{ __("agentoom-core::docs.how_it_works_label") }}:</flux:text>
                            <flux:text size="md" class="mt-1">
                                {{ __('agentoom-core::docs.sidebar.real_life_examples.cybersecurity.incident_response.how_it_works') }}
                            </flux:text>
                        </div>

                        <div>
                            <flux:text size="sm" class="font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">{{ __("agentoom-core::docs.action_label") }}:</flux:text>
                            <flux:text size="md" class="mt-1">
                                {{ __('agentoom-core::docs.sidebar.real_life_examples.cybersecurity.incident_response.action') }}
                            </flux:text>
                        </div>
                    </flux:card>
                </section>

                <section>
                    <flux:heading size="lg" level="2" class="mb-6">{{ __('agentoom-core::docs.sidebar.real_life_examples.cybersecurity.compliance.title') }}</flux:heading>

                    <flux:card class="p-6 space-y-4">
                        <div>
                            <flux:text size="sm" class="font-bold text-zinc-900 dark:text-white uppercase tracking-wider">{{ __("agentoom-core::docs.how_it_works_label") }}:</flux:text>
                            <flux:text size="md" class="mt-1">
                                {{ __('agentoom-core::docs.sidebar.real_life_examples.cybersecurity.compliance.how_it_works') }}
                            </flux:text>
                        </div>

                        <div>
                            <flux:text size="sm" class="font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">{{ __("agentoom-core::docs.action_label") }}:</flux:text>
                            <flux:text size="md" class="mt-1">
                                {{ __('agentoom-core::docs.sidebar.real_life_examples.cybersecurity.compliance.action') }}
                            </flux:text>
                        </div>
                    </flux:card>
                </section>
            </div>

            <div class="flex justify-between items-center pt-8 mt-12 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button href="{{ route('docs.index', ['locale' => app()->getLocale()]) }}" icon="arrow-left" variant="ghost">{{ __("agentoom-core::docs.back_to_overview") }}</flux:button>
                <flux:button href="{{ route('docs.examples', ['locale' => app()->getLocale()]) }}" icon-trailing="arrow-right" variant="ghost">{{ __("agentoom-core::docs.view_examples") }}</flux:button>
            </div>
    </div>
