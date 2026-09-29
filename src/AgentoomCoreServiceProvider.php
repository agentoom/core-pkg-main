<?php

namespace Agentoom\Core;

use Agentoom\Core\Ai\Support\CostCaptureStore;
use Agentoom\Core\Ai\Support\CostCapturingStream;
use Agentoom\Core\Ai\Tools\Public\AssignAiTaskTool;
use Agentoom\Core\Ai\Tools\Public\ReadAiTaskTool;
use Agentoom\Core\Console\Commands\AgentoomInstall;
use Agentoom\Core\Console\Commands\AggregateAiMetrics;
use Agentoom\Core\Console\Commands\AnalyzeMemoryCommand;
use Agentoom\Core\Console\Commands\CreateMissingProjectSystemUsers;
use Agentoom\Core\Console\Commands\CreateSystemResources;
use Agentoom\Core\Console\Commands\DiscoverAiTools;
use Agentoom\Core\Console\Commands\DispatchSchedules;
use Agentoom\Core\Console\Commands\ProcessAiTasks;
use Agentoom\Core\Console\Commands\PruneAiConversations;
use Agentoom\Core\Console\Commands\ResumeWaits;
use Agentoom\Core\Console\Commands\SyncAiPricing;
use Agentoom\Core\Console\Commands\SyncProviderModels;
use Agentoom\Core\Contracts\HumanIntervention;
use Agentoom\Core\Contracts\PrivacyIntegration;
use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\DataTransfer\Sections\AgentsSection;
use Agentoom\Core\DataTransfer\Sections\AiTasksSection;
use Agentoom\Core\DataTransfer\Sections\ConfigurationsSection;
use Agentoom\Core\DataTransfer\Sections\EndpointsSection;
use Agentoom\Core\DataTransfer\Sections\LearnedSkillsSection;
use Agentoom\Core\DataTransfer\Sections\PipelinesSection;
use Agentoom\Core\DataTransfer\Sections\ProjectsSection;
use Agentoom\Core\DataTransfer\Sections\ResourcesSection;
use Agentoom\Core\DataTransfer\Sections\ResourceTypesSection;
use Agentoom\Core\DataTransfer\Sections\RolesSection;
use Agentoom\Core\DataTransfer\Sections\SchedulesSection;
use Agentoom\Core\DataTransfer\Sections\SkillPresetsSection;
use Agentoom\Core\DataTransfer\Sections\SkillsSection;
use Agentoom\Core\Execution\ActionExecutor;
use Agentoom\Core\Execution\ExecutionPipeRegistry;
use Agentoom\Core\Http\Middleware\CheckOnboarding;
use Agentoom\Core\Http\Middleware\EndpointRateLimitMiddleware;
use Agentoom\Core\Inertia\PackageAwareViewFinder;
use Agentoom\Core\Listeners\WebhookEventSubscriber;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Models\Pipeline as PipelineModel;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Policies\SchedulePolicy;
use Agentoom\Core\Realtime\Listeners\ReverbEventListener;
use Agentoom\Core\Realtime\RealtimeManager;
use Agentoom\Core\Services\MenuRegistry;
use Agentoom\Core\Services\NoopComplianceIntegration;
use Agentoom\Core\Services\NoopHumanIntervention;
use Agentoom\Core\Services\NoopPrivacyIntegration;
use Agentoom\Core\Services\PermissionRegistry;
use Agentoom\Core\Services\PipelineExecutionService;
use Agentoom\Core\Services\ResourceLimitManager;
use Agentoom\Core\Services\ResourceUsageRegistry;
use Agentoom\Core\Services\ToolRegistry;
use Agentoom\Core\Services\VisibilityService;
use Agentoom\Core\Storage\AgentAwareConversationStore;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Reverb\Events\ChannelCreated;
use Laravel\Reverb\Events\ChannelRemoved;
use Laravel\Reverb\Events\MessageReceived;
use Laravel\Reverb\Events\MessageSent;
use Livewire\Livewire;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class AgentoomCoreServiceProvider extends ServiceProvider
{
    /**
     * Hosts of the AI providers this application talks to directly, so the
     * common case never queries the providers table per response.
     */
    private const KNOWN_AI_HOSTS = [
        'openrouter.ai',
        'api.openai.com',
        'api.anthropic.com',
        'generativelanguage.googleapis.com',
        'api.groq.com',
        'api.mistral.ai',
        'api.deepseek.com',
        'api.x.ai',
        'api.cohere.ai',
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/agentoom.php', 'agentoom');
        $this->mergeConfigFrom(__DIR__.'/../config/ai-pricing.php', 'ai-pricing');
        $this->mergeConfigFrom(__DIR__.'/../config/system-resources.php', 'agentoom-system-resources');

        $this->app->scoped(CostCaptureStore::class);
        $this->app->singleton(ActionExecutor::class, function ($app) {
            return new ActionExecutor($app->make(Pipeline::class));
        });

        $this->app->singleton(MenuRegistry::class, function ($app) {
            return new MenuRegistry;
        });

        $this->app->singleton(VisibilityService::class);

        $this->app->singleton(ToolRegistry::class, function ($app) {
            $registry = new ToolRegistry;
            $registry->register([
                AssignAiTaskTool::class,
                ReadAiTaskTool::class,
            ]);

            return $registry;
        });

        $this->app->singleton(RealtimeManager::class, function ($app) {
            return new RealtimeManager;
        });

        $this->app->singleton(ExecutionPipeRegistry::class);

        // Optional-capability defaults. Installed packages rebind these
        // contracts to their real implementations in their own providers.
        // Provider registration order is not guaranteed (package discovery
        // is alphabetical), so only bind the default when no package has
        // already bound the contract.
        if (! $this->app->bound(ComplianceIntegration::class)) {
            $this->app->singleton(ComplianceIntegration::class, NoopComplianceIntegration::class);
        }
        if (! $this->app->bound(PrivacyIntegration::class)) {
            $this->app->singleton(PrivacyIntegration::class, NoopPrivacyIntegration::class);
        }
        if (! $this->app->bound(HumanIntervention::class)) {
            $this->app->singleton(HumanIntervention::class, NoopHumanIntervention::class);
        }

        $this->app->singleton(PipelineExecutionService::class);

        // Optional packages register their own permission groups from boot().
        $this->app->singleton(PermissionRegistry::class);
        $this->registerPermissionGroups();

        // Optional packages register their own usage counters from boot().
        $this->app->singleton(ResourceUsageRegistry::class);
        $this->registerResourceUsageCounters();

        // Optional packages register their own import/export sections from boot().
        $this->app->singleton(ImportExportRegistry::class);

        $this->registerImportExportSections();
    }

    /**
     * Register core's own usage counters, in the same shape optional packages
     * use: the key is the `agentoom.limits.{key}` cap and the language key
     * `admin.dashboard_view.resources.{key}`.
     */
    protected function registerResourceUsageCounters(): void
    {
        $registry = $this->app->make(ResourceUsageRegistry::class);

        $registry->register('agents', fn (): int => Agent::where('system_agent', false)->where('default_agent', false)->count());
        $registry->register('pipelines', fn (): int => PipelineModel::count());
        $registry->register('endpoints', fn (): int => Endpoint::count());
        $registry->register('resource_types', fn (): int => ResourceType::count());
        $registry->register('projects', fn (): int => Project::count());
        $registry->register('mcp_servers', fn (): int => McpServer::count());
    }

    /**
     * Register core's import/export sections, in import execution order.
     *
     * Packages append theirs from boot(); every other registered section is
     * listed here, which is what keeps the Import/Export page and its payload
     * free of any reference to an optional package.
     */
    protected function registerImportExportSections(): void
    {
        $registry = $this->app->make(ImportExportRegistry::class);

        foreach ([
            new ProjectsSection,
            new ConfigurationsSection,
            new ResourceTypesSection,
            new AgentsSection,
            new SkillsSection,
            new SkillPresetsSection,
            new LearnedSkillsSection,
            new PipelinesSection,
            new ResourcesSection,
            new EndpointsSection,
            new SchedulesSection,
            new AiTasksSection,
            new RolesSection,
        ] as $section) {
            $registry->register($section);
        }
    }

    /**
     * Register core's own permission groups.
     *
     * Every other group is contributed by the package that owns its feature:
     * `widgets.*` by chat-widget, `openapi-tokens.*` by openapi-v1, and so on.
     * Doing it that way means an instance without a package never offers — or
     * seeds — that package's permissions.
     */
    protected function registerPermissionGroups(): void
    {
        $registry = $this->app->make(PermissionRegistry::class);

        foreach ([
            'dashboard' => ['dashboard.view'],
            'agents' => ['agents.view', 'agents.create', 'agents.edit', 'agents.delete'],
            'ai_tasks' => ['ai-tasks.view', 'ai-tasks.create', 'ai-tasks.edit', 'ai-tasks.delete'],
            'skills' => ['skills.view', 'skills.create', 'skills.edit', 'skills.delete'],
            'chat' => ['chat.view'],
            'pipelines' => ['pipelines.view', 'pipelines.create', 'pipelines.edit', 'pipelines.delete'],
            'endpoints' => ['endpoints.view', 'endpoints.create', 'endpoints.edit', 'endpoints.delete'],
            'schedules' => ['schedules.view', 'schedules.create', 'schedules.edit', 'schedules.delete'],
            'resource_groups' => ['resource-groups.view', 'resource-groups.create', 'resource-groups.edit', 'resource-groups.delete'],
            'resource_types' => ['resource-types.view', 'resource-types.create', 'resource-types.edit', 'resource-types.delete'],
            'resources' => ['resources.view', 'resources.create', 'resources.edit', 'resources.delete'],
            'integrations' => ['integrations.view', 'integrations.create', 'integrations.edit', 'integrations.delete'],
            'projects' => ['projects.view', 'projects.create', 'projects.edit', 'projects.delete'],
            'project_expenses' => ['project-expenses.view'],
            'ai_credits' => ['ai-credits.view', 'ai-credits.create'],
            'providers' => ['providers.view', 'providers.create', 'providers.edit', 'providers.delete'],
            'mcp_servers' => ['mcp-servers.view', 'mcp-servers.create', 'mcp-servers.edit', 'mcp-servers.delete'],
            'languages' => ['languages.view', 'languages.create', 'languages.edit', 'languages.delete'],
            'configs' => ['configs.view', 'configs.edit'],
            'commands' => ['commands.view', 'commands.execute'],
            'system_workers' => ['system-workers.view', 'system-workers.edit'],
            'skill_presets' => ['skill-presets.view', 'skill-presets.create', 'skill-presets.edit', 'skill-presets.delete'],
            'import_export' => ['import-export.view', 'import-export.execute'],
            'users' => ['users.view', 'users.create', 'users.edit', 'users.delete'],
            'roles' => ['roles.view', 'roles.create', 'roles.edit', 'roles.delete'],
        ] as $group => $permissions) {
            $registry->register($group, $permissions);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole('superadmin') ? true : null;
        });

        Gate::policy(Schedule::class, SchedulePolicy::class);

        Http::globalMiddleware(function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                return $handler($request, $options)->then(function (ResponseInterface $response) use ($request) {
                    $store = app(CostCaptureStore::class);
                    if (! $store->isActive() || ! $this->isAiCostResponse($request, $response)) {
                        return $response;
                    }

                    return $response->withBody(new CostCapturingStream($response->getBody(), $store));
                });
            };
        });

        Http::globalRequestMiddleware(function (RequestInterface $request) {
            $url = (string) $request->getUri();
            $disableCache = config('ai.openrouter_disable_cache');

            if (! $disableCache && app()->bound('current_agent_id')) {
                try {
                    $agent = Agent::find(app('current_agent_id'));
                    $disableCache = (bool) ($agent?->config['openrouter_disable_cache'] ?? false);
                } catch (\Throwable) {
                    // Cache hints must never break an AI request.
                }
            }

            if (str_contains($url, 'openrouter.ai')) {
                if ($disableCache) {
                    $request = $request->withHeader('X-OpenRouter-Cache', 'false');
                }
                Log::debug('OpenRouter Request:', [
                    'url' => $url,
                    'headers' => array_map(fn ($value) => is_array($value) && count($value) === 1 ? $value[0] : $value, $request->getHeaders()),
                    'disable_cache_setting' => $disableCache,
                ]);
            }

            return $request;
        });

        Event::listen([
            MessageReceived::class,
            MessageSent::class,
            ChannelCreated::class,
            ChannelRemoved::class,
        ], ReverbEventListener::class);

        $this->app->make(RealtimeManager::class)->channel('conversations.{conversationId}', function ($user, $conversationId) {
            // Basic authorization: check if user has access to conversation
            return DB::table('agent_conversations')
                ->where('id', $conversationId)
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->orWhereExists(function ($q) use ($user) {
                            $q->select(DB::raw(1))
                                ->from('model_has_roles')
                                ->where('model_id', $user->id)
                                ->whereIn('role_id', function ($sub) {
                                    $sub->select('id')->from('roles')->whereIn('name', ['superadmin', 'admin']);
                                });
                        });
                })->exists();
        });

        $this->app->singleton(ConversationStore::class, AgentAwareConversationStore::class);
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'agentoom-core');
        $this->loadViewsFrom(__DIR__.'/../resources/views/flux', 'flux');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'agentoom-core');

        Inertia::setRootView('agentoom-core::inertia');

        // Inertia's page finder locates components on disk (assertions, dev
        // tools). Core pages live in this package; package pages resolve by
        // their "package::Page" name in the package itself.
        config([
            'inertia.pages.paths' => array_values(array_unique(array_merge(
                [__DIR__.'/../resources/js/Pages'],
                (array) config('inertia.pages.paths', []),
            ))),
        ]);

        $this->app->bind('inertia.view-finder', function ($app) {
            return new PackageAwareViewFinder(
                $app['files'],
                $app['config']->get('inertia.pages.paths'),
                $app['config']->get('inertia.pages.extensions'),
            );
        });

        $this->registerRoutes();
        $this->app['router']->aliasMiddleware('endpoint.rate.limit', EndpointRateLimitMiddleware::class);
        $this->app['router']->aliasMiddleware('check.onboarding', CheckOnboarding::class);
        Livewire::addNamespace('agentoom-core', null, 'Agentoom\\Core\\Livewire');

        $this->configureHorizon();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/agentoom.php' => config_path('agentoom.php'),
                __DIR__.'/../config/ai-pricing.php' => config_path('ai-pricing.php'),
            ], 'agentoom-config');

            $this->publishes([
                __DIR__.'/../public/images' => public_path('images'),
            ], 'agentoom-assets');
        }

        Event::subscribe(WebhookEventSubscriber::class);

        // Intercept destructive database queries at the query level to prevent
        // accidental data loss. Logs every attempt with full forensic context
        // so the source of the wipe can be tracked.
        $this->app->booted(function () {
            $protectedTables = [
                'agent_conversations',
                'agent_conversation_messages',
                'agents',
                'agent_webhook_subscriptions',
                'endpoints',
                'endpoint_logs',
                'users',
            ];

            DB::connection()->beforeExecuting(function (string $query, array $bindings) use ($protectedTables) {
                // Only protect the primary 'laravel' database. Tests and other
                // environments use separate databases (e.g., 'testing') so they
                // don't need — and shouldn't trigger — this protection.
                try {
                    $currentDatabase = DB::connection()->getDatabaseName();
                } catch (\Throwable $e) {
                    return;
                }

                if ($currentDatabase !== 'laravel') {
                    return;
                }

                // Skip if explicitly allowed
                if (env('AGENTOOM_ALLOW_DB_RESET', false)) {
                    return;
                }

                $normalized = strtoupper(trim((string) $query));

                // --- DROP TABLE detection ---
                // Matches: DROP TABLE tablename, DROP TABLE IF EXISTS tablename,
                //          DROP TABLE "schema"."tablename", ... CASCADE (Postgres bulk drop)
                if (preg_match('/^DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?/i', $query)) {
                    // Extract table names from the comma-separated list after DROP TABLE ...
                    $tableList = preg_replace('/^DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?/i', '', $query);
                    // Remove CASCADE / RESTRICT suffix
                    $tableList = preg_replace('/\s+CASCADE\s*$/i', '', $tableList);
                    $tableList = preg_replace('/\s+RESTRICT\s*$/i', '', $tableList);

                    // Match each table name: "schema"."tablename", schema.tablename, or tablename
                    if (preg_match_all('/["`]?(\w+)["`]?\s*\.\s*["`]?(\w+)["`]?|\b(["`]?\w+["`]?)\b/', $tableList, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $m) {
                            // Priority: schema-qualified (group 1=schema, group 2=table)
                            // Fallback: unqualified (group 3=table)
                            $table = strtolower(! empty($m[2]) ? $m[2] : $m[3]);

                            if (in_array($table, $protectedTables, true)) {
                                $this->logAndBlockDestructiveQuery('DROP TABLE', $table, $query, $bindings);
                            }
                        }
                    }
                }

                // --- TRUNCATE detection ---
                if (preg_match_all('/TRUNCATE\s+(?:TABLE\s+)?["`]?(?:[\w.]+\.["`]?)?(\w+)["`]?/i', $query, $matches)) {
                    foreach ($matches[1] as $candidate) {
                        $table = strtolower($candidate);
                        if (in_array($table, $protectedTables, true)) {
                            $this->logAndBlockDestructiveQuery('TRUNCATE', $table, $query, $bindings);
                        }
                    }
                }

                // --- DELETE FROM without WHERE on protected tables ---
                if (preg_match('/DELETE\s+FROM\s+["`]?(\w+)["`]?/i', $query, $m)) {
                    $table = strtolower($m[1]);

                    if (in_array($table, $protectedTables, true) && ! stripos($normalized, 'WHERE')) {
                        $this->logAndBlockDestructiveQuery('DELETE FROM (no WHERE)', $table, $query, $bindings);
                    }
                }
            });
        });

        $this->commands([
            AgentoomInstall::class,
            AggregateAiMetrics::class,
            DiscoverAiTools::class,
            AnalyzeMemoryCommand::class,
            CreateSystemResources::class,
            DispatchSchedules::class,
            SyncProviderModels::class,
            SyncAiPricing::class,
            ProcessAiTasks::class,
            CreateMissingProjectSystemUsers::class,
            ResumeWaits::class,
            PruneAiConversations::class,
        ]);
    }

    /**
     * Only a billed inference call can carry a usage cost, and only a response
     * from a configured AI provider may be inspected for one.
     */
    private function isAiCostResponse(RequestInterface $request, ResponseInterface $response): bool
    {
        $uri = $request->getUri();
        $host = strtolower($uri->getHost());
        $path = strtolower($uri->getPath());
        if ($host === '') {
            return false;
        }

        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        if ($contentType !== '' && ! str_contains($contentType, 'json') && ! str_contains($contentType, 'event-stream')) {
            return false;
        }

        $isInferenceEndpoint = (bool) preg_match(
            '#/(chat/completions|completions|responses|messages|embeddings|images/generations|images/edits|audio/transcriptions|transcriptions)$#',
            $path
        ) || (bool) preg_match('#:(stream)?generatecontent$#', $path);

        if (! $isInferenceEndpoint) {
            return false;
        }

        // Well-known hosts resolve without touching the database; only custom
        // and self-hosted providers need their configured URLs consulted.
        return in_array($host, self::KNOWN_AI_HOSTS, true) || in_array($host, $this->configuredAiHosts(), true);
    }

    /**
     * Hosts of the AI providers this application talks to directly.
     *
     * @return array<int, string>
     */
    private function configuredAiHosts(): array
    {
        $hosts = [];

        foreach (Provider::query()->pluck('url')->all() as $url) {
            if ($configuredHost = parse_url((string) $url, PHP_URL_HOST)) {
                $hosts[] = strtolower($configuredHost);
            }
        }

        foreach ((array) config('ai.providers', []) as $provider) {
            if (is_array($provider) && ! empty($provider['url']) && ($configuredHost = parse_url($provider['url'], PHP_URL_HOST))) {
                $hosts[] = strtolower($configuredHost);
            }
        }

        return array_values(array_unique($hosts));
    }

    protected function registerRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../routes/web.php');

        if ($this->app->runningInConsole()) {
            require __DIR__.'/../routes/console.php';
        }
    }

    /**
     * Log a destructive database query attempt with full forensic context,
     * then throw an exception to block execution.
     *
     * @throws \RuntimeException
     */
    private function logAndBlockDestructiveQuery(string $operation, string $table, string $query, array $bindings): void
    {
        $context = [
            'operation' => $operation,
            'table' => $table,
            'query' => $query,
            'bindings' => $bindings,
            'working_directory' => getcwd(),
            'user' => posix_getpwuid(posix_geteuid())['name'] ?? 'unknown',
            'hostname' => gethostname(),
            'pid' => getmypid(),
            'php_sapi' => PHP_SAPI,
            'trace' => (new \Exception('Destructive query attempt detected'))->getTraceAsString(),
        ];

        Log::critical('Destructive database query blocked', $context);

        throw new \RuntimeException(
            "Destructive database operation '{$operation}' on table '{$table}' has been blocked to prevent data loss. ".
            'This attempt has been logged with full forensic context. '.
            'To proceed, set AGENTOOM_ALLOW_DB_RESET=true in your .env file.'
        );
    }

    protected function configureHorizon(): void
    {
        $maxProcesses = app(ResourceLimitManager::class)->limitFor('pipeline_workers');

        if ($maxProcesses === null) {
            return;
        }

        config([
            'horizon.environments.production.pipeline-supervisor.maxProcesses' => $maxProcesses,
            'horizon.environments.local.supervisor-1.maxProcesses' => $maxProcesses,
        ]);
    }
}
