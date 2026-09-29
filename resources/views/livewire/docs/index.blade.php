<div class="max-w-4xl mx-auto py-10 px-6">
            <header class="mb-10">
                <flux:heading size="xl" level="1">{{ __('agentoom-core::docs.what_is_agentoom.title') }}</flux:heading>
                <flux:subheading size="lg" class="mt-4">
                    {{ __('agentoom-core::docs.what_is_agentoom.description') }}
                </flux:subheading>
            </header>

            <section>
                <flux:heading size="lg" level="2" class="mb-6">{{ __('agentoom-core::docs.what_is_agentoom.features.title') }}</flux:heading>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach(['eu_ai_act', 'agents', 'projects', 'agent_awareness', 'agent_skills', 'knowledge_base', 'integrations', 'pipelines', 'widgets', 'endpoints', 'schedules', 'resources', 'learning', 'security', 'prompts'] as $feature)
                        <a href="{{ $feature === 'eu_ai_act' ? route('docs.eu-ai-act', ['locale' => app()->getLocale()]) : route('docs.features.' . str_replace('_', '-', $feature), ['locale' => app()->getLocale()]) }}" class="block group">
                            <flux:card class="p-6 h-full transition-colors group-hover:bg-zinc-50 dark:group-hover:bg-zinc-800/50">
                                <div class="flex items-center justify-between mb-2">
                                    <flux:heading size="md" level="3">
                                        {{ __('agentoom-core::docs.what_is_agentoom.features.' . $feature . '.title') }}
                                    </flux:heading>
                                    <flux:icon.arrow-right variant="mini" class="size-4 text-zinc-400 opacity-0 group-hover:opacity-100 transition-opacity" />
                                </div>
                                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">
                                    {{ __('agentoom-core::docs.what_is_agentoom.features.' . $feature . '.description') }}
                                </flux:text>
                            </flux:card>
                        </a>
                    @endforeach
                </div>
            </section>
    </div>
