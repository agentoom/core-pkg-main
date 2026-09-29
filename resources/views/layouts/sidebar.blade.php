
<flux:sidebar sticky collapsible="mobile" class="w-72 bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700 text-[13px]">
    <flux:sidebar.header>
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 px-2">
            <img src="{{ asset('images/agentoom_logo.svg') }}" class="size-8 fill-current text-cyan-neon"  alt="Agentoom"/>
            <span class="text-xl font-bold font-inter tracking-tight dark:text-white">{{ config('app.name') }}</span>
        </a>
        <flux:sidebar.collapse class="lg:hidden" />
    </flux:sidebar.header>
    @php
        $sidebarUserKey = (string) auth()->id();
        $isGovernActive = request()->routeIs([
            'admin.ai-tool-definitions.*',
            'admin.tool-authorization-logs.*',
            'admin.privacy-recognizers.*',
            'admin.pii-masking-rules.*',
            'admin.external-privacy-settings',
            'admin.compliance.*',
        ]);
        $isBuildActive = request()->routeIs([
            'admin.resources.*',
            'admin.resource-types.*',
            'admin.resource-groups.*',
            'admin.widgets.*',
            'admin.form.*',
            'admin.prompt.*',
            'admin.accessibility-widgets.*',
            'admin.instant-messaging.settings',
            'admin.my-integrations.*',
            'admin.mcp-servers.*',
            'admin.providers.*',
        ]);
        $isManageActive = request()->routeIs([
            'admin.projects.*',
            'admin.monitoring',
            'admin.webhook-subscriptions.*',
            'admin.live-support',
            'admin.live-support.settings',
        ]);
        $isSettingsActive = request()->routeIs([
            'admin.users.*',
            'admin.roles.*',
            'admin.configs.index',
            'admin.configs.email',
            'admin.languages.*',
            'admin.configs.system-workers',
            'admin.configs.commands',
            'admin.import-export',
        ]);

        $showResources = auth()->user()->can('resources.view') || auth()->user()->can('resource-groups.view') || auth()->user()->can('resource-types.view');
        // Optional packages: their entry is only rendered when the package
        // (and therefore its routes) is installed.
        $hasAccessibilityWidgets = \Illuminate\Support\Facades\Route::has('admin.accessibility-widgets.index');
        $hasLiveSupport = \Illuminate\Support\Facades\Route::has('admin.live-support');
        $hasChatWidget = \Illuminate\Support\Facades\Route::has('admin.widgets.index');
        $hasInstantMessaging = \Illuminate\Support\Facades\Route::has('admin.instant-messaging.settings');
        $hasOpenApiTokens = \Illuminate\Support\Facades\Route::has('admin.openapi-tokens.index');
        $hasSentinel = \Illuminate\Support\Facades\Route::has('admin.ai-tool-definitions.index');
        $hasTrustLayer = \Illuminate\Support\Facades\Route::has('admin.privacy-recognizers.index');
        $hasCompliance = \Illuminate\Support\Facades\Route::has('admin.compliance.dashboard');
        // Remote privacy engine ships in the optional agentoom/privacy-pro package.
        $hasExternalPrivacySettings = \Illuminate\Support\Facades\Route::has('admin.external-privacy-settings');
        $showWidgets = ($hasChatWidget || $hasAccessibilityWidgets) && (auth()->user()->can('widgets.view') || auth()->user()->can('forms.view') || auth()->user()->can('prompts.view') || ($hasAccessibilityWidgets && auth()->user()->can('accessibility-widgets.view')));
        $showBuild = $showResources || $showWidgets || auth()->user()->can('integrations.view') || auth()->user()->can('mcp-servers.view') || auth()->user()->can('providers.view') || auth()->user()->can('openapi-tokens.view') || ($hasInstantMessaging && auth()->user()->can('instant-messaging.view'));
        $showProjects = auth()->user()->can('projects.view') || auth()->user()->can('project-expenses.view');
        $showManage = auth()->user()->can('dashboard.view') || ($hasLiveSupport && auth()->user()->can('live-support.view')) || $showProjects;
        $showAccess = auth()->user()->can('users.view') || auth()->user()->can('roles.view');
        $showPlatform = auth()->user()->can('configs.view') || auth()->user()->can('integrations.view') || auth()->user()->can('languages.view');
        $showSystem = auth()->user()->can('system-workers.view') || auth()->user()->can('commands.view') || auth()->user()->can('import-export.view') || auth()->user()->can('configs.view');
        $showSettings = $showAccess || $showPlatform || $showSystem;
    @endphp

    <flux:sidebar.nav>
        @can('dashboard.view')
            <flux:sidebar.item icon="home" href="{{ route('admin.dashboard') }}" :current="request()->routeIs('admin.dashboard')">{{ __('agentoom-core::admin.dashboard') }}</flux:sidebar.item>
        @endcan

        {{-- Mobile-only: top navigation entries. The desktop navbar is hidden
             below lg, so its entries are mirrored here as the first section,
             right after Dashboard. Hidden on desktop to avoid duplication. --}}
        <div class="lg:hidden">
            @php
                $canSeeAgents = auth()->user()->can('agents.view') || auth()->user()->can('ai-tasks.view') || auth()->user()->can('skills.view') || auth()->user()->can('skill-presets.view') || auth()->user()->can('agent-tools.view');
            @endphp

            @if($canSeeAgents)
                <flux:sidebar.group
                    heading="{{ __('agentoom-core::admin.agents.title') }}"
                    icon="briefcase"
                    expandable
                    :expanded="request()->routeIs(['admin.agents.*', 'admin.agent-tools.*', 'admin.skills.*', 'admin.configs.skill_presets'])"
                >
                    @can('agents.view')
                        <flux:sidebar.item icon="briefcase" href="{{ route('admin.agents.index') }}" :current="request()->routeIs('admin.agents.index')">{{ __('agentoom-core::admin.agents.title') }}</flux:sidebar.item>
                    @endcan
                    @can('ai-tasks.view')
                        <flux:sidebar.item icon="list-bullet" href="{{ route('admin.agents.ai-tasks.index') }}" :current="request()->routeIs('admin.agents.ai-tasks.*')">{{ __('agentoom-core::admin.ai_tasks.title') }}</flux:sidebar.item>
                    @endcan
                    @can('skills.view')
                        <flux:sidebar.item icon="sparkles" href="{{ route('admin.skills.index') }}" :current="request()->routeIs('admin.skills.*')">{{ __('agentoom-core::admin.skills.title') }}</flux:sidebar.item>
                    @endcan
                    @can('skill-presets.view')
                        <flux:sidebar.item icon="sparkles" href="{{ route('admin.configs.skill_presets') }}" :current="request()->routeIs('admin.configs.skill_presets')">{{ __('agentoom-core::admin.skill_presets.title') }}</flux:sidebar.item>
                    @endcan
                    @can('agent-tools.view')
                        <flux:sidebar.item icon="wrench-screwdriver" href="{{ route('admin.agent-tools.index') }}" :current="request()->routeIs('admin.agent-tools.*')">{{ __('agentoom-core::admin.agent_tools.title') }}</flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endif

            @can('chat.view')
                @php
                    $hasAgents = \Agentoom\Core\Models\Agent::where('is_active', true)
                        ->where('system_agent', false)
                        ->visibleTo(auth()->user())
                        ->exists();
                @endphp
                @if($hasAgents)
                    <flux:sidebar.item icon="chat-bubble-left-right" href="{{ route('admin.chat') }}" :current="request()->routeIs('admin.chat')">
                        {{ __('agentoom-core::admin.chat') }}
                    </flux:sidebar.item>
                @endif
            @endcan

            @can('pipelines.view')
                <flux:sidebar.item icon="cpu-chip" href="{{ route('admin.pipelines.index') }}" :current="request()->routeIs('admin.pipelines.*')">
                    {{ __('agentoom-core::admin.pipelines.title') }}
                </flux:sidebar.item>
            @endcan

            @can('endpoints.view')
                <flux:sidebar.item icon="server" href="{{ route('admin.endpoints.index') }}" :current="request()->routeIs('admin.endpoints.*')">
                    {{ __('agentoom-core::admin.endpoints.title') }}
                </flux:sidebar.item>
            @endcan

            @can('schedules.view')
                <flux:sidebar.item icon="clock" href="{{ route('admin.schedules.index') }}" :current="request()->routeIs('admin.schedules.*')">
                    {{ __('agentoom-core::admin.schedules.title') }}
                </flux:sidebar.item>
            @endcan

            @can('ai-credits.view')
                <flux:sidebar.item icon="bolt" href="{{ route('admin.ai-credits.index') }}" :current="request()->routeIs('admin.ai-credits.index')">
                    {{ __('agentoom-core::admin.ai_credits.title') }}
                </flux:sidebar.item>
            @endcan
        </div>

            <flux:sidebar.group
                heading="Govern"
                icon="shield-check"
                expandable
                :expanded="$isGovernActive"
                x-init="const key = 'agentoom.sidebar.{{ $sidebarUserKey }}.govern'; const saved = localStorage.getItem(key); $el.open = {{ $isGovernActive ? 'true' : '(saved === null ? false : saved === \'1\')' }}; $el.addEventListener('toggle', () => localStorage.setItem(key, $el.open ? '1' : '0'));"
            >
                @if($hasSentinel)
                    @can('ai-tool-definitions.view')
                        <flux:sidebar.item href="{{ route('admin.ai-tool-definitions.index') }}" :current="request()->routeIs('admin.ai-tool-definitions.*')" badge="Secure" badge:color="indigo" class="animate-glow font-bold">
                            {{ __('agentoom-sentinel::admin.ai_tool_definitions.sentinel') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item href="{{ route('admin.tool-authorization-logs.index') }}" :current="request()->routeIs('admin.tool-authorization-logs.*')">
                            {{ __('agentoom-sentinel::admin.ai_tool_definitions.logs') }}
                        </flux:sidebar.item>
                    @endcan
                @endif

                @php
                    $canSeeTrustLayer = auth()->user()->can('privacy-recognizers.view') || auth()->user()->can('pii-masking-rules.view');
                @endphp

                {{-- Free capability: always shown when the package is installed.
                     Only the remote engine (agentoom/privacy-pro) is optional. --}}
                @if($hasTrustLayer && $canSeeTrustLayer)
                    <flux:sidebar.group heading="{{ __('agentoom-privacy::admin.trust_layer.title') }}" icon="finger-print" expandable :expanded="request()->routeIs(['admin.privacy-recognizers.*', 'admin.pii-masking-rules.*', 'admin.external-privacy-settings'])">
                        @can('privacy-recognizers.view')
                            <flux:sidebar.item href="{{ route('admin.privacy-recognizers.index') }}" :current="request()->routeIs('admin.privacy-recognizers.*')">{{ __('agentoom-privacy::admin.privacy_recognizers.title') }}</flux:sidebar.item>
                        @endcan
                        @can('pii-masking-rules.view')
                            <flux:sidebar.item href="{{ route('admin.pii-masking-rules.index') }}" :current="request()->routeIs('admin.pii-masking-rules.*')">{{ __('agentoom-privacy::admin.pii_masking_rules.title') }}</flux:sidebar.item>
                        @endcan
                        @can('privacy-recognizers.view')
                            @if($hasExternalPrivacySettings)
                                <flux:sidebar.item href="{{ route('admin.external-privacy-settings') }}" :current="request()->routeIs('admin.external-privacy-settings')">{{ __('agentoom-privacy::admin.trust_layer.external_privacy') }}</flux:sidebar.item>
                            @endif
                        @endcan
                    </flux:sidebar.group>
                @endif

                @if($hasCompliance && auth()->user()->can('admin'))
                    <flux:sidebar.group heading="{{ __('agentoom-compliance::admin.compliance.title') }}" icon="shield-exclamation" expandable :expanded="request()->routeIs('admin.compliance.*')">
                        <flux:sidebar.item icon="chart-bar" href="{{ route('admin.compliance.dashboard') }}" :current="request()->routeIs('admin.compliance.dashboard')">{{ __('agentoom-compliance::admin.compliance.dashboard') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" href="{{ route('admin.compliance.audit-log.index') }}" :current="request()->routeIs('admin.compliance.audit-log.*')">{{ __('agentoom-compliance::admin.compliance.audit_log') }}</flux:sidebar.item>
                        @can('agent-traces.view')
                            <flux:sidebar.item icon="magnifying-glass-circle" href="{{ route('admin.compliance.agent-traces.index') }}" :current="request()->routeIs('admin.compliance.agent-traces.*')">{{ __('agentoom-compliance::admin.compliance.agent_traces') }}</flux:sidebar.item>
                        @endcan
                        <flux:sidebar.item icon="hand-raised" href="{{ route('admin.compliance.pending-decisions.index') }}" :current="request()->routeIs('admin.compliance.pending-decisions.*')">{{ __('agentoom-compliance::admin.compliance.pending_decisions') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="stop-circle" href="{{ route('admin.compliance.emergency-stops.index') }}" :current="request()->routeIs('admin.compliance.emergency-stops.*')">{{ __('agentoom-compliance::admin.compliance.emergency_stops') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="exclamation-triangle" href="{{ route('admin.compliance.incidents.index') }}" :current="request()->routeIs('admin.compliance.incidents.*')">{{ __('agentoom-compliance::admin.compliance.incidents') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="shield-check" href="{{ route('admin.compliance.risk-assessments.index') }}" :current="request()->routeIs('admin.compliance.risk-assessments.*')">{{ __('agentoom-compliance::admin.compliance.risk_assessments') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="bell-alert" href="{{ route('admin.compliance.alerts.index') }}" :current="request()->routeIs('admin.compliance.alerts.*')">{{ __('agentoom-compliance::admin.compliance.alerts') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="beaker" href="{{ route('admin.compliance.evaluations.index') }}" :current="request()->routeIs('admin.compliance.evaluations.*')">{{ __('agentoom-compliance::admin.compliance.evaluations') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="document-chart-bar" href="{{ route('admin.compliance.reports.index') }}" :current="request()->routeIs('admin.compliance.reports.*')">{{ __('agentoom-compliance::admin.compliance.reports') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="server-stack" href="{{ route('admin.compliance.governed-deployments.index') }}" :current="request()->routeIs('admin.compliance.governed-deployments.*')">{{ __('agentoom-compliance::admin.compliance.governed_deployments') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-check" href="{{ route('admin.compliance.assessment-wizard') }}" :current="request()->routeIs('admin.compliance.assessment-wizard')">{{ __('agentoom-compliance::admin.compliance.assessment_wizard') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="book-open" href="{{ route('admin.compliance.governance-controls.index') }}" :current="request()->routeIs('admin.compliance.governance-controls.*')">{{ __('agentoom-compliance::admin.compliance.control_library') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="adjustments-horizontal" href="{{ route('admin.compliance.governance-profiles.index') }}" :current="request()->routeIs('admin.compliance.governance-profiles.*')">{{ __('agentoom-compliance::admin.compliance.governance_profiles') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.group>

        @if($showBuild)
            <flux:sidebar.group
                heading="Build & Extend"
                icon="squares-2x2"
                expandable
                :expanded="$isBuildActive"
                x-init="const key = 'agentoom.sidebar.{{ $sidebarUserKey }}.build'; const saved = localStorage.getItem(key); $el.open = {{ $isBuildActive ? 'true' : '(saved === null ? false : saved === \'1\')' }}; $el.addEventListener('toggle', () => localStorage.setItem(key, $el.open ? '1' : '0'));"
            >
                @if($showResources)
                    @php
                        $resourceGroups = \Agentoom\Core\Models\ResourceGroup::visibleTo(auth()->user())
                            ->with(['resourceTypes' => function($query) {
                                $query->visibleTo(auth()->user())->withCount('resources');
                            }])->get();
                        $isResourceActive = request()->routeIs('admin.resources.*') || request()->routeIs('admin.resource-types.*') || request()->routeIs('admin.resource-groups.*');
                    @endphp
                    <flux:sidebar.group heading="{{ __('agentoom-core::admin.resources.title') }}" icon="cube" expandable :expanded="$isResourceActive">
                        @foreach($resourceGroups as $group)
                            <flux:sidebar.group heading="{{ $group->name }}" icon="{{ $group->icon ?: 'cube' }}" expandable :expanded="request()->routeIs('admin.resources.*') && collect($group->resourceTypes)->pluck('id')->contains(request('resource_type_id'))">
                                @foreach($group->resourceTypes as $type)
                                    @can('resources.view')
                                        <flux:sidebar.item href="{{ route('admin.resources.index', ['resource_type_id' => $type->id]) }}" :current="request()->routeIs('admin.resources.*') && request('resource_type_id') == $type->id">
                                            {{ $type->name_translated }}
                                            <flux:badge size="sm" class="ml-auto">{{ $type->resources_count }}</flux:badge>
                                        </flux:sidebar.item>
                                    @endcan
                                @endforeach
                            </flux:sidebar.group>
                        @endforeach
                        @can('resources.view')
                            <flux:sidebar.item icon="magnifying-glass" href="{{ route('admin.resources.index') }}" :current="request()->routeIs('admin.resources.index') && !request('resource_type_id')">{{ __('agentoom-core::admin.resources.all_resources') }}</flux:sidebar.item>
                        @endcan
                        @if(auth()->user()->can('resource-groups.view') || auth()->user()->can('resource-types.view'))
                            <flux:sidebar.group heading="Types & Groups" icon="rectangle-group" expandable :expanded="request()->routeIs(['admin.resource-types.*', 'admin.resource-groups.*'])">
                                @can('resource-groups.view')
                                    <flux:sidebar.item href="{{ route('admin.resource-groups.index') }}" :current="request()->routeIs('admin.resource-groups.*')">{{ __('Groups') }}</flux:sidebar.item>
                                @endcan
                                @can('resource-types.view')
                                    <flux:sidebar.item href="{{ route('admin.resource-types.index') }}" :current="request()->routeIs('admin.resource-types.*')">{{ __('Types') }}</flux:sidebar.item>
                                @endcan
                            </flux:sidebar.group>
                        @endif
                    </flux:sidebar.group>
                @endif

                @if($showWidgets)
                    <flux:sidebar.group heading="{{ __('agentoom-core::admin.dashboard_view.resources.widgets') }}" icon="squares-2x2" expandable :expanded="request()->routeIs(['admin.widgets.*', 'admin.form.*', 'admin.prompt.*', 'admin.accessibility-widgets.*'])">
                        @if($hasChatWidget)
                            @can('widgets.view')
                                <flux:sidebar.item href="{{ route('admin.widgets.index') }}" :current="request()->routeIs('admin.widgets.*')">{{ __('agentoom-core::admin.dashboard_view.resources.chat_widgets') }}</flux:sidebar.item>
                            @endcan
                            @can('forms.view')
                                <flux:sidebar.item href="{{ route('admin.form.index') }}" :current="request()->routeIs('admin.form.*')">{{ __('agentoom-chat-widget::admin.forms.index_title') }}</flux:sidebar.item>
                            @endcan
                            @can('prompts.view')
                                <flux:sidebar.item href="{{ route('admin.prompt.index') }}" :current="request()->routeIs('admin.prompt.*')">{{ __('agentoom-chat-widget::admin.prompts.index_title') }}</flux:sidebar.item>
                            @endcan
                        @endif
                        @if($hasAccessibilityWidgets)
                            @can('accessibility-widgets.view')
                                <flux:sidebar.item href="{{ route('admin.accessibility-widgets.index') }}" :current="request()->routeIs('admin.accessibility-widgets.*')">{{ __('accessibility-widget::widget.admin.index_title') }}</flux:sidebar.item>
                            @endcan
                        @endif
                    </flux:sidebar.group>
                @endif

                @if($hasInstantMessaging)
                    @can('instant-messaging.view')
                        <flux:sidebar.item icon="chat-bubble-left-right" href="{{ route('admin.instant-messaging.settings') }}" :current="request()->routeIs('admin.instant-messaging.settings')">{{ __('agentoom-core::admin.instant_messaging') }}</flux:sidebar.item>
                    @endcan
                @endif
                @can('integrations.view')
                    <flux:sidebar.item icon="puzzle-piece" href="{{ route('admin.my-integrations.index') }}" :current="request()->routeIs('admin.my-integrations.*')">{{ __('agentoom-core::admin.integrations.my_integrations') }}</flux:sidebar.item>
                @endcan
                @can('mcp-servers.view')
                    <flux:sidebar.item icon="share" href="{{ route('admin.mcp-servers.index') }}" :current="request()->routeIs('admin.mcp-servers.*')">{{ __('agentoom-core::admin.mcp_servers.title') }}</flux:sidebar.item>
                @endcan
                @if($hasOpenApiTokens)
                    @can('openapi-tokens.view')
                        <flux:sidebar.item icon="key" href="{{ route('admin.openapi-tokens.index') }}" :current="request()->routeIs('admin.openapi-tokens.*')">{{ __('openapi-v1::admin.index_title') }}</flux:sidebar.item>
                    @endcan
                @endif
                @can('providers.view')
                    <flux:sidebar.item icon="server-stack" href="{{ route('admin.providers.index') }}" :current="request()->routeIs('admin.providers.*')">{{ __('agentoom-core::admin.providers.title') }}</flux:sidebar.item>
                @endcan
            </flux:sidebar.group>
        @endif

        @if($showManage)
            <flux:sidebar.group
                heading="Manage"
                icon="chart-bar"
                expandable
                :expanded="$isManageActive"
                x-init="const key = 'agentoom.sidebar.{{ $sidebarUserKey }}.manage'; const saved = localStorage.getItem(key); $el.open = {{ $isManageActive ? 'true' : '(saved === null ? false : saved === \'1\')' }}; $el.addEventListener('toggle', () => localStorage.setItem(key, $el.open ? '1' : '0'));"
            >
                @if($showProjects)
                    @can('projects.view')
                        <flux:sidebar.item icon="folder" href="{{ route('admin.projects.index') }}" :current="request()->routeIs('admin.projects.index') || request()->routeIs('admin.projects.create') || request()->routeIs('admin.projects.edit')">{{ __('agentoom-core::admin.projects.title') }}</flux:sidebar.item>
                    @endcan
                    @can('project-expenses.view')
                        <flux:sidebar.item icon="currency-dollar" href="{{ route('admin.projects.expenses') }}" :current="request()->routeIs('admin.projects.expenses')">{{ __('agentoom-core::admin.projects.expenses') }}</flux:sidebar.item>
                    @endcan
                @endif
                @can('dashboard.view')
                    <flux:sidebar.item icon="chart-bar" href="{{ route('admin.monitoring') }}" :current="request()->routeIs('admin.monitoring')">Monitoring</flux:sidebar.item>
                    <flux:sidebar.item icon="webhook" href="{{ route('admin.webhook-subscriptions.index') }}" :current="request()->routeIs('admin.webhook-subscriptions.*')">Webhooks</flux:sidebar.item>
                @endcan
                @if($hasLiveSupport)
                    @can('live-support.view')
                        @if(\Illuminate\Support\Facades\Route::has('admin.live-support.settings'))
                            <flux:sidebar.group heading="{{ __('agentoom-core::admin.live_support') }}" icon="user-group" expandable :expanded="request()->routeIs(['admin.live-support', 'admin.live-support.settings'])">
                                <flux:sidebar.item href="{{ route('admin.live-support') }}" :current="request()->routeIs('admin.live-support')">
                                    {{ __('agentoom-core::admin.live_support') }}
                                    <flux:badge size="sm" color="lime" class="ml-auto">Beta</flux:badge>
                                </flux:sidebar.item>
                                <flux:sidebar.item href="{{ route('admin.live-support.settings') }}" :current="request()->routeIs('admin.live-support.settings')">
                                    {{ __('agentoom-core::admin.live_support_settings') }}
                                </flux:sidebar.item>
                            </flux:sidebar.group>
                        @else
                            <flux:sidebar.item icon="user-group" href="{{ route('admin.live-support') }}" :current="request()->routeIs('admin.live-support')">
                                {{ __('agentoom-core::admin.live_support') }}
                                <flux:badge size="sm" color="lime" class="ml-auto">Beta</flux:badge>
                            </flux:sidebar.item>
                        @endif
                    @endcan
                @endif
            </flux:sidebar.group>
        @endif

        @if($showSettings)
            <flux:sidebar.group
                heading="Settings"
                icon="cog-6-tooth"
                expandable
                :expanded="$isSettingsActive"
                x-init="const key = 'agentoom.sidebar.{{ $sidebarUserKey }}.settings'; const saved = localStorage.getItem(key) ?? localStorage.getItem('agentoom.sidebar.{{ $sidebarUserKey }}.configuration'); $el.open = {{ $isSettingsActive ? 'true' : '(saved === null ? false : saved === \'1\')' }}; $el.addEventListener('toggle', () => localStorage.setItem(key, $el.open ? '1' : '0'));"
            >
                @if($showAccess)
                    <flux:sidebar.group heading="Access Control" icon="lock" expandable :expanded="request()->routeIs(['admin.users.*', 'admin.roles.*'])">
                        @can('users.view')
                            <flux:sidebar.item icon="users" href="{{ route('admin.users.index') }}" :current="request()->routeIs('admin.users.*')">{{ __('agentoom-core::admin.users.title') }}</flux:sidebar.item>
                        @endcan
                        @can('roles.view')
                            <flux:sidebar.item icon="key" href="{{ route('admin.roles.index') }}" :current="request()->routeIs('admin.roles.*')">{{ __('agentoom-core::admin.roles.title') }}</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endif

                @if($showPlatform)
                    <flux:sidebar.group heading="Platform" icon="adjustments-horizontal" expandable :expanded="request()->routeIs(['admin.configs.index', 'admin.configs.email', 'admin.languages.*'])">
                        @can('configs.view')
                            <flux:sidebar.item icon="cog-6-tooth" href="{{ route('admin.configs.index') }}" :current="request()->routeIs('admin.configs.index')">General Settings</flux:sidebar.item>
                        @endcan
                        @can('integrations.view')
                            <flux:sidebar.item icon="envelope" href="{{ route('admin.configs.email') }}" :current="request()->routeIs('admin.configs.email')">{{ __('agentoom-core::admin.configs.email_configuration') }}</flux:sidebar.item>
                        @endcan
                        @can('languages.view')
                            <flux:sidebar.item icon="language" href="{{ route('admin.languages.index') }}" :current="request()->routeIs('admin.languages.*')">{{ __('agentoom-core::admin.languages.title') }}</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endif

                @if($showSystem)
                    <flux:sidebar.group heading="System" icon="server" expandable :expanded="request()->routeIs(['admin.configs.system-workers', 'admin.configs.commands', 'admin.import-export'])">
                        @can('system-workers.view')
                            <flux:sidebar.item icon="cpu-chip" href="{{ route('admin.configs.system-workers') }}" :current="request()->routeIs('admin.configs.system-workers')">{{ __('agentoom-core::admin.system_workers.title') }}</flux:sidebar.item>
                        @endcan
                        @can('commands.view')
                            <flux:sidebar.item icon="command-line" href="{{ route('admin.configs.commands') }}" :current="request()->routeIs('admin.configs.commands')">{{ __('agentoom-core::admin.configs.section_console_commands') }}</flux:sidebar.item>
                        @endcan
                        @can('import-export.view')
                            <flux:sidebar.item icon="arrow-path" href="{{ route('admin.import-export') }}" :current="request()->routeIs('admin.import-export')">{{ __('agentoom-core::admin.import_export.title') }}</flux:sidebar.item>
                        @endcan
                        @can('configs.view')
                            <flux:sidebar.item icon="sparkles" href="#" wire:click.prevent="$dispatch('')" onclick="event.preventDefault(); document.getElementById('rerun-onboarding-form').submit();">{{ __('agentoom-core::admin.onboarding.rerun') }}</flux:sidebar.item>
                            <form id="rerun-onboarding-form" method="POST" action="{{ route('admin.onboarding.rerun') }}" class="hidden">
                                @csrf
                            </form>
                        @endcan
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.group>
        @endif
    </flux:sidebar.nav>

    <flux:sidebar.spacer />

    <flux:sidebar.nav>

    <div class="px-3 py-2">
        @php($langs = \Agentoom\Core\Models\Language::where('is_active', true)->orderBy('is_default', 'desc')->get())
        @if($langs->count() > 1)
            <form method="POST" action="{{ route('admin.set-locale') }}" class="flex items-center gap-2">
                @csrf
                <label for="admin-locale" class="text-[11px] text-zinc-500">{{ __('agentoom-core::admin.common.locale') }}</label>
                <select id="admin-locale" name="code" class="text-xs px-2 py-1 rounded-md border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-700 dark:text-zinc-200"
                        onchange="this.form.submit()">
                    @foreach($langs as $lang)
                        <option value="{{ $lang->code }}" @selected(app()->getLocale() === $lang->code)>
                            {{ $lang->name }} ({{ strtoupper($lang->code) }})
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    <flux:sidebar.item icon="book-open" href="{{ route('docs.index', ['locale' => app()->getLocale()]) }}" target="_blank">{{ __('agentoom-core::docs.title') }}</flux:sidebar.item>

    </flux:sidebar.nav>
</flux:sidebar>

