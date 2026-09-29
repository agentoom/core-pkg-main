<flux:navbar class="lg:hidden w-full">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
    <flux:spacer />
    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
        <img src="{{ asset('images/agentoom_logo.svg') }}" class="size-6" alt="{{ config('app.name') }}" />
        <span class="text-lg font-bold font-inter tracking-tight dark:text-white">{{ config('app.name') }}</span>
    </a>
    <flux:spacer />
    <flux:dropdown position="top" align="start">
        @auth
            <flux:profile name="{{ auth()->user()->name ?? auth()->user()->email }}" />
            <flux:menu>
                <flux:menu.item icon="shield-check" href="{{ route('admin.security') }}">
                    Security Settings
                </flux:menu.item>
                <flux:menu.item icon="book-open" href="{{ route('docs.eu-ai-act', ['locale' => app()->getLocale()]) }}" target="_blank">
                    EU AI Act Compliance
                </flux:menu.item>
                <flux:menu.separator />
                <form method="POST" action="{{ route('logout') }}" class="contents">
                    @csrf
                    <button type="submit" class="flex items-center gap-2 px-2 py-1.5 w-full text-start text-sm font-medium rounded-md text-zinc-800 hover:text-red-600 hover:bg-red-50 dark:text-white dark:hover:bg-red-400/20 dark:hover:text-red-400">
                        <flux:icon icon="arrow-right-start-on-rectangle" variant="mini" class="size-5" />
                        {{ __('agentoom-core::admin.common.logout') }}
                    </button>
                </form>
            </flux:menu>
        @endauth
    </flux:dropdown>
</flux:navbar>

{{-- Desktop header nav --}}
<flux:navbar scrollable class="hidden lg:flex">
    @php
        $canSeeAgents = auth()->user()->can('agents.view') || auth()->user()->can('ai-tasks.view') || auth()->user()->can('skills.view') || auth()->user()->can('skill-presets.view') || auth()->user()->can('agent-tools.view');
        $hasAnyAgentPerm = $canSeeAgents;
    @endphp

    @if($hasAnyAgentPerm)
        <flux:dropdown>
            <flux:navbar.item icon:trailing="chevron-down" :current="request()->routeIs(['admin.agents.*', 'admin.agent-tools.*', 'admin.skills.*', 'admin.configs.skill_presets'])">
                {{ __('agentoom-core::admin.agents.title') }}
            </flux:navbar.item>
            <flux:navmenu>
                @can('agents.view')
                    <flux:navmenu.item href="{{ route('admin.agents.index') }}" icon="briefcase">{{ __('agentoom-core::admin.agents.title') }}</flux:navmenu.item>
                @endcan
                @can('ai-tasks.view')
                    <flux:navmenu.item href="{{ route('admin.agents.ai-tasks.index') }}" icon="list-bullet">{{ __('agentoom-core::admin.ai_tasks.title') }}</flux:navmenu.item>
                @endcan
                @can('skills.view')
                    <flux:navmenu.item href="{{ route('admin.skills.index') }}" icon="sparkles">{{ __('agentoom-core::admin.skills.title') }}</flux:navmenu.item>
                @endcan
                @can('skill-presets.view')
                    <flux:navmenu.item href="{{ route('admin.configs.skill_presets') }}" icon="sparkles">{{ __('agentoom-core::admin.skill_presets.title') }}</flux:navmenu.item>
                @endcan
                @can('agent-tools.view')
                    <flux:navmenu.item href="{{ route('admin.agent-tools.index') }}" icon="wrench-screwdriver">{{ __('agentoom-core::admin.agent_tools.title') }}</flux:navmenu.item>
                @endcan
            </flux:navmenu>
        </flux:dropdown>
    @endif

    @can('chat.view')
        @php
            $hasAgents = \Agentoom\Core\Models\Agent::where('is_active', true)
                ->where('system_agent', false)
                ->visibleTo(auth()->user())
                ->exists();
        @endphp
        @if($hasAgents)
            <flux:navbar.item
                href="{{ route('admin.chat') }}"
                :current="request()->routeIs('admin.chat')"
                icon="chat-bubble-left-right"
            >
                {{ __('agentoom-core::admin.chat') }}
            </flux:navbar.item>
        @endif
    @endcan

    @can('pipelines.view')
        <flux:navbar.item href="{{ route('admin.pipelines.index') }}" :current="request()->routeIs('admin.pipelines.*')" icon="cpu-chip">
            {{ __('agentoom-core::admin.pipelines.title') }}
        </flux:navbar.item>
    @endcan

    @can('endpoints.view')
        <flux:navbar.item href="{{ route('admin.endpoints.index') }}" :current="request()->routeIs('admin.endpoints.*')" icon="server">
            {{ __('agentoom-core::admin.endpoints.title') }}
        </flux:navbar.item>
    @endcan

    @can('schedules.view')
        <flux:navbar.item href="{{ route('admin.schedules.index') }}" :current="request()->routeIs('admin.schedules.*')" icon="clock">
            {{ __('agentoom-core::admin.schedules.title') }}
        </flux:navbar.item>
    @endcan

    <flux:spacer />

    {{-- AI Credits --}}
    @can('ai-credits.view')
        <flux:navbar.item href="{{ route('admin.ai-credits.index') }}" :current="request()->routeIs('admin.ai-credits.index')" icon="bolt">
            {{ __('agentoom-core::admin.ai_credits.title') }}
        </flux:navbar.item>
    @endcan

    {{-- Profile dropdown --}}
    <flux:dropdown position="top" align="start">
        @auth
            <flux:profile name="{{ auth()->user()->name ?? auth()->user()->email }}" />
            <flux:menu>
                <flux:menu.item icon="shield-check" href="{{ route('admin.security') }}">
                    Security Settings
                </flux:menu.item>
                <flux:menu.item icon="book-open" href="{{ route('docs.eu-ai-act', ['locale' => app()->getLocale()]) }}" target="_blank">
                    EU AI Act Compliance
                </flux:menu.item>
                <flux:menu.separator />
                <form method="POST" action="{{ route('logout') }}" class="contents">
                    @csrf
                    <button type="submit" class="flex items-center gap-2 px-2 py-1.5 w-full text-start text-sm font-medium rounded-md text-zinc-800 hover:text-red-600 hover:bg-red-50 dark:text-white dark:hover:bg-red-400/20 dark:hover:text-red-400">
                        <flux:icon icon="arrow-right-start-on-rectangle" variant="mini" class="size-5" />
                        {{ __('agentoom-core::admin.common.logout') }}
                    </button>
                </form>
            </flux:menu>
        @endauth
    </flux:dropdown>
</flux:navbar>
