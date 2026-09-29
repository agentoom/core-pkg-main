<div>
    <flux:sidebar sticky stashable class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" variant="ghost" inset="left" />

        <a href="{{ route('home') }}" class="flex items-center gap-2 px-2 lg:hidden">
            <flux:icon.brain variant="mini" class="size-6 text-indigo-600" />
            <span class="text-lg font-bold text-zinc-900 dark:text-white">{{ config('app.name') }}</span>
        </a>

        <flux:navlist variant="outline">
            {{-- Getting Started --}}
            <flux:navlist.group heading="{{ __('agentoom-core::docs.sidebar.getting_started') }}">
                <flux:navlist.item href="{{ route('docs.index', ['locale' => app()->getLocale()]) }}" icon="book-open" :current="request()->routeIs('docs.index')">{{ __('agentoom-core::docs.sidebar.what_is_agentoom') }}</flux:navlist.item>
            </flux:navlist.group>

            {{-- AI Core --}}
            <flux:navlist.group heading="{{ __('agentoom-core::docs.sidebar.ai_core') }}">
                <flux:navlist.item href="{{ route('docs.features.agents', ['locale' => app()->getLocale()]) }}" icon="users" :current="request()->routeIs('docs.features.agents')">{{ __('agentoom-core::docs.sidebar.features.agents') }}</flux:navlist.item>
                <flux:navlist.item href="{{ route('docs.features.agent-skills', ['locale' => app()->getLocale()]) }}" icon="sparkles" :current="request()->routeIs('docs.features.agent-skills')">{{ __('agentoom-core::docs.sidebar.features.agent_skills') }}</flux:navlist.item>
                <flux:navlist.item href="{{ route('docs.features.knowledge-base', ['locale' => app()->getLocale()]) }}" icon="academic-cap" :current="request()->routeIs('docs.features.knowledge-base')">{{ __('agentoom-core::docs.sidebar.features.knowledge_base') }}</flux:navlist.item>
                <flux:navlist.item href="{{ route('docs.features.prompts', ['locale' => app()->getLocale()]) }}" icon="command-line" :current="request()->routeIs('docs.features.prompts')">{{ __('agentoom-core::docs.sidebar.features.prompts') }}</flux:navlist.item>
                <flux:navlist.item href="{{ route('docs.features.learning', ['locale' => app()->getLocale()]) }}" icon="bolt" :current="request()->routeIs('docs.features.learning')">{{ __('agentoom-core::docs.sidebar.features.learning') }}</flux:navlist.item>
            </flux:navlist.group>

            {{-- Collaboration & Channels --}}
            <flux:navlist.group heading="{{ __('agentoom-core::docs.sidebar.channels') }}">
                <flux:navlist.item href="{{ route('docs.features.chat', ['locale' => app()->getLocale()]) }}" icon="chat-bubble-bottom-center-text" :current="request()->routeIs('docs.features.chat')">{{ __('agentoom-core::docs.sidebar.features.chat') }}</flux:navlist.item>
                @if(\Illuminate\Support\Facades\Route::has('admin.widgets.index'))
                    <flux:navlist.item href="{{ route('docs.features.widgets', ['locale' => app()->getLocale()]) }}" icon="chat-bubble-left-right" :current="request()->routeIs('docs.features.widgets')">{{ __('agentoom-core::docs.sidebar.features.widgets') }}</flux:navlist.item>
                @endif
                @if(\Illuminate\Support\Facades\Route::has('admin.live-support'))
                    <flux:navlist.item href="{{ route('docs.features.live-support', ['locale' => app()->getLocale()]) }}" icon="user-group" :current="request()->routeIs('docs.features.live-support')">{{ __('agentoom-core::docs.sidebar.features.live_support') }}</flux:navlist.item>
                @endif
                @if(\Illuminate\Support\Facades\Route::has('admin.instant-messaging.settings'))
                    <flux:navlist.item href="{{ route('docs.features.instant-messaging', ['locale' => app()->getLocale()]) }}" icon="chat-bubble-left" :current="request()->routeIs('docs.features.instant-messaging')">{{ __('agentoom-core::docs.sidebar.features.instant_messaging') }}</flux:navlist.item>
                @endif
            </flux:navlist.group>

            {{-- Automation & Orchestration --}}
            <flux:navlist.group heading="{{ __('agentoom-core::docs.sidebar.automation') }}">
                <flux:navlist.item href="{{ route('docs.features.pipelines', ['locale' => app()->getLocale()]) }}" icon="queue-list" :current="request()->routeIs('docs.features.pipelines')">{{ __('agentoom-core::docs.sidebar.features.pipelines') }}</flux:navlist.item>
                <flux:navlist.item href="{{ route('docs.features.endpoints', ['locale' => app()->getLocale()]) }}" icon="globe-alt" :current="request()->routeIs('docs.features.endpoints')">{{ __('agentoom-core::docs.sidebar.features.endpoints') }}</flux:navlist.item>
                <flux:navlist.item href="{{ route('docs.features.schedules', ['locale' => app()->getLocale()]) }}" icon="calendar" :current="request()->routeIs('docs.features.schedules')">{{ __('agentoom-core::docs.sidebar.features.schedules') }}</flux:navlist.item>
                <flux:navlist.item href="{{ route('docs.features.agent-awareness', ['locale' => app()->getLocale()]) }}" icon="eye" :current="request()->routeIs('docs.features.agent-awareness')">{{ __('agentoom-core::docs.sidebar.features.agent_awareness') }}</flux:navlist.item>
            </flux:navlist.group>

            {{-- Infrastructure & Integrations --}}
            <flux:navlist.group heading="{{ __('agentoom-core::docs.sidebar.infrastructure') }}">
                <flux:navlist.item href="{{ route('docs.features.resources', ['locale' => app()->getLocale()]) }}" icon="cpu-chip" :current="request()->routeIs('docs.features.resources')">{{ __('agentoom-core::docs.sidebar.features.resources') }}</flux:navlist.item>
                <flux:navlist.item href="{{ route('docs.features.integrations', ['locale' => app()->getLocale()]) }}" icon="arrows-right-left" :current="request()->routeIs('docs.features.integrations')">{{ __('agentoom-core::docs.sidebar.features.integrations') }}</flux:navlist.item>
                <flux:navlist.item href="{{ route('docs.features.my-integrations', ['locale' => app()->getLocale()]) }}" icon="link" :current="request()->routeIs('docs.features.my-integrations')">{{ __('agentoom-core::docs.sidebar.features.my_integrations') }}</flux:navlist.item>
            </flux:navlist.group>

            {{-- Analytics & Finance --}}
            <flux:navlist.group heading="{{ __('agentoom-core::docs.sidebar.analytics') }}">
                <flux:navlist.item href="{{ route('docs.features.dashboard', ['locale' => app()->getLocale()]) }}" icon="squares-2x2" :current="request()->routeIs('docs.features.dashboard')">{{ __('agentoom-core::docs.sidebar.features.dashboard') }}</flux:navlist.item>
                <flux:navlist.item href="{{ route('docs.features.projects', ['locale' => app()->getLocale()]) }}" icon="folder" :current="request()->routeIs('docs.features.projects')">{{ __('agentoom-core::docs.sidebar.features.projects') }}</flux:navlist.item>
                <flux:navlist.item href="{{ route('docs.features.expenses', ['locale' => app()->getLocale()]) }}" icon="credit-card" :current="request()->routeIs('docs.features.expenses')">{{ __('agentoom-core::docs.sidebar.features.expenses') }}</flux:navlist.item>
                <flux:navlist.item href="{{ route('docs.features.ai-credits', ['locale' => app()->getLocale()]) }}" icon="banknotes" :current="request()->routeIs('docs.features.ai-credits')">{{ __('agentoom-core::docs.sidebar.features.ai_credits') }}</flux:navlist.item>
            </flux:navlist.group>

            {{-- Governance & Security --}}
            <flux:navlist.group heading="{{ __('agentoom-core::docs.sidebar.governance') }}">
                <flux:navlist.item href="{{ route('docs.features.security', ['locale' => app()->getLocale()]) }}" icon="lock-closed" :current="request()->routeIs('docs.features.security')">{{ __('agentoom-core::docs.sidebar.features.security') }}</flux:navlist.item>
                <flux:navlist.item href="{{ route('docs.features.users', ['locale' => app()->getLocale()]) }}" icon="user-circle" :current="request()->routeIs('docs.features.users')">{{ __('agentoom-core::docs.sidebar.features.users') }}</flux:navlist.item>
                <flux:navlist.item href="{{ route('docs.eu-ai-act', ['locale' => app()->getLocale()]) }}" icon="shield-check" :current="request()->routeIs('docs.eu-ai-act')">{{ __('agentoom-core::docs.sidebar.features.eu_ai_act') }}</flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>

        <flux:spacer />
    </flux:sidebar>
</div>

