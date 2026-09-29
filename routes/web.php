<?php

use Agentoom\Core\Http\Controllers\Admin\V2\ChatController;
use Agentoom\Core\Http\Controllers\Admin\V2\PipelineController;
use Agentoom\Core\Http\Controllers\AiCreditTopupController;
use Agentoom\Core\Http\Controllers\EndpointController;
use Agentoom\Core\Http\Controllers\OAuthController;

Route::post('/api/ai-credit-topup/callback', [AiCreditTopupController::class, 'callback'])->name('api.ai-credits.callback');

Route::get('/images/{path}', function (string $path) {
    $basePath = realpath(__DIR__.'/../public/images');
    $filePath = realpath($basePath.'/'.$path);

    if (! $filePath || ! str_starts_with($filePath, $basePath) || ! is_file($filePath)) {
        return response('Image not found', 404);
    }

    return response()->file($filePath);
})->where('path', '.*')->name('agentoom.images');

use Agentoom\Core\Http\Controllers\McpCostController;
use Agentoom\Core\Http\Controllers\McpResourcesController;
use Agentoom\Core\Livewire\Admin\OnboardingWizard;

Route::post('/api/mcp/costs', [McpCostController::class, 'store'])->name('api.mcp.costs.store')->middleware('mcp.api.token');
Route::get('/api/mcp/resources', [McpResourcesController::class, 'index'])->name('api.mcp.resources.index')->middleware('mcp.api.token');
Route::post('/api/mcp/resources', [McpResourcesController::class, 'store'])->name('api.mcp.resources.store')->middleware('mcp.api.token');

use Agentoom\Core\Livewire\Admin\AgentCreateEdit;
use Agentoom\Core\Livewire\Admin\AgentIndex;
use Agentoom\Core\Livewire\Admin\AgentKnowledgeManager;
use Agentoom\Core\Livewire\Admin\AgentLearnedSkillsManager;
use Agentoom\Core\Livewire\Admin\AgentToolIndex;
use Agentoom\Core\Livewire\Admin\AiCreditIndex;
use Agentoom\Core\Livewire\Admin\AiTaskCreateEdit;
use Agentoom\Core\Livewire\Admin\AiTaskIndex;
use Agentoom\Core\Livewire\Admin\CommandIndex;
use Agentoom\Core\Livewire\Admin\Config\EmailConfiguration;
use Agentoom\Core\Livewire\Admin\Config\SkillPresets;
use Agentoom\Core\Livewire\Admin\Config\SystemWorkers;
use Agentoom\Core\Livewire\Admin\ConfigIndex;
use Agentoom\Core\Livewire\Admin\Dashboard;
use Agentoom\Core\Livewire\Admin\EndpointCreateEdit;
use Agentoom\Core\Livewire\Admin\EndpointIndex;
use Agentoom\Core\Livewire\Admin\EndpointLogIndex;
use Agentoom\Core\Livewire\Admin\ImportExport;
use Agentoom\Core\Livewire\Admin\LanguageCreateEdit;
use Agentoom\Core\Livewire\Admin\LanguageIndex;
use Agentoom\Core\Livewire\Admin\McpServerCreateEdit;
use Agentoom\Core\Livewire\Admin\McpServerIndex;
use Agentoom\Core\Livewire\Admin\MonitoringIndex;
use Agentoom\Core\Livewire\Admin\PipelineCreateEdit;
use Agentoom\Core\Livewire\Admin\PipelineIndex;
use Agentoom\Core\Livewire\Admin\PipelineRunIndex;
use Agentoom\Core\Livewire\Admin\PipelineRunShow;
use Agentoom\Core\Livewire\Admin\ProjectCreateEdit;
use Agentoom\Core\Livewire\Admin\ProjectExpensesIndex;
use Agentoom\Core\Livewire\Admin\ProjectIndex;
use Agentoom\Core\Livewire\Admin\ProjectKnowledgeManager;
use Agentoom\Core\Livewire\Admin\ProviderCreateEdit;
use Agentoom\Core\Livewire\Admin\ProviderIndex;
use Agentoom\Core\Livewire\Admin\ResourceCreateEdit;
use Agentoom\Core\Livewire\Admin\ResourceGroupCreateEdit;
use Agentoom\Core\Livewire\Admin\ResourceGroupIndex;
use Agentoom\Core\Livewire\Admin\ResourceIndex;
use Agentoom\Core\Livewire\Admin\ResourceTypeCreateEdit;
use Agentoom\Core\Livewire\Admin\ResourceTypeIndex;
use Agentoom\Core\Livewire\Admin\RoleCreateEdit;
use Agentoom\Core\Livewire\Admin\RoleIndex;
use Agentoom\Core\Livewire\Admin\ScheduleCreateEdit;
use Agentoom\Core\Livewire\Admin\ScheduleIndex;
use Agentoom\Core\Livewire\Admin\ScheduleLogIndex;
use Agentoom\Core\Livewire\Admin\SecuritySettings;
use Agentoom\Core\Livewire\Admin\SkillCreateEdit;
use Agentoom\Core\Livewire\Admin\SkillIndex;
use Agentoom\Core\Livewire\Admin\UserCreateEdit;
use Agentoom\Core\Livewire\Admin\UserIndex;
use Agentoom\Core\Livewire\Admin\UserMemoryIndex;
use Agentoom\Core\Livewire\Admin\WebhookSubscriptionCreateEdit;
use Agentoom\Core\Livewire\Admin\WebhookSubscriptionIndex;
use Agentoom\Core\Livewire\Docs\Features\AgentAwareness as DocsFeaturesAgentAwareness;
use Agentoom\Core\Livewire\Docs\Features\Agents as DocsFeaturesAgents;
use Agentoom\Core\Livewire\Docs\Features\AgentSkills as DocsFeaturesAgentSkills;
use Agentoom\Core\Livewire\Docs\Features\AiCredits as DocsFeaturesAiCredits;
use Agentoom\Core\Livewire\Docs\Features\Chat as DocsFeaturesChat;
use Agentoom\Core\Livewire\Docs\Features\Dashboard as DocsFeaturesDashboard;
use Agentoom\Core\Livewire\Docs\Features\Endpoints as DocsFeaturesEndpoints;
use Agentoom\Core\Livewire\Docs\Features\EuAiAct as DocsFeaturesEuAiAct;
use Agentoom\Core\Livewire\Docs\Features\Expenses as DocsFeaturesExpenses;
use Agentoom\Core\Livewire\Docs\Features\InstantMessaging as DocsFeaturesInstantMessaging;
use Agentoom\Core\Livewire\Docs\Features\Integrations as DocsFeaturesIntegrations;
use Agentoom\Core\Livewire\Docs\Features\KnowledgeBase as DocsFeaturesKnowledgeBase;
use Agentoom\Core\Livewire\Docs\Features\Learning as DocsFeaturesLearning;
use Agentoom\Core\Livewire\Docs\Features\LiveSupport as DocsFeaturesLiveSupport;
use Agentoom\Core\Livewire\Docs\Features\MyIntegrations as DocsFeaturesMyIntegrations;
use Agentoom\Core\Livewire\Docs\Features\Pipelines as DocsFeaturesPipelines;
use Agentoom\Core\Livewire\Docs\Features\Projects as DocsFeaturesProjects;
use Agentoom\Core\Livewire\Docs\Features\Resources as DocsFeaturesResources;
use Agentoom\Core\Livewire\Docs\Features\Schedules as DocsFeaturesSchedules;
use Agentoom\Core\Livewire\Docs\Features\Security as DocsFeaturesSecurity;
use Agentoom\Core\Livewire\Docs\Features\Users as DocsFeaturesUsers;
use Agentoom\Core\Livewire\Docs\Features\Widgets as DocsFeaturesWidgets;
use Agentoom\Core\Livewire\Docs\Index as DocsIndex;
use Agentoom\Core\Livewire\User\IntegrationIndex as UserIntegrationIndex;
use Agentoom\Core\Models\Language;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

Route::get('/install', function () {
    $superadminEmail = 'superadmin@unusualdope.com';
    $superadminRole = 'superadmin';

    $isInitialized = false;

    try {
        $isInitialized = User::query()->where('email', $superadminEmail)->exists()
            && Role::query()->where('name', $superadminRole)->exists();
    } catch (Throwable $e) {
        // Table probably doesn't exist, so not initialized
    }

    if ($isInitialized) {
        abort(404);
    }

    Artisan::call('agentoom:install');

    return 'Installation completed successfully. You can now login with '.$superadminEmail;
});

Route::get('/docs', function () {
    return redirect('/'.app()->getLocale().'/docs');
});

use Agentoom\Core\Http\Controllers\DocsExportController;
use Agentoom\Core\Models\Configuration;

Route::prefix('{locale}')->where(['locale' => '[a-z]{2}'])->group(function () {
    Route::get('/docs/export/{format}', [DocsExportController::class, 'export'])->name('docs.export');
    Route::get('/docs/eu-ai-act', DocsFeaturesEuAiAct::class)->name('docs.eu-ai-act');
    Route::get('/docs', DocsIndex::class)->name('docs.index');

    Route::prefix('/docs/features')->name('docs.features.')->group(function () {
        Route::get('/agents', DocsFeaturesAgents::class)->name('agents');
        Route::get('/agent-awareness', DocsFeaturesAgentAwareness::class)->name('agent-awareness');
        Route::get('/agent-skills', DocsFeaturesAgentSkills::class)->name('agent-skills');

        Route::get('/knowledge-base', DocsFeaturesKnowledgeBase::class)->name('knowledge-base');
        Route::get('/integrations', DocsFeaturesIntegrations::class)->name('integrations');
        Route::get('/pipelines', DocsFeaturesPipelines::class)->name('pipelines');
        Route::get('/widgets', DocsFeaturesWidgets::class)->name('widgets');
        Route::get('/endpoints', DocsFeaturesEndpoints::class)->name('endpoints');
        Route::get('/schedules', DocsFeaturesSchedules::class)->name('schedules');
        Route::get('/resources', DocsFeaturesResources::class)->name('resources');
        Route::get('/projects', DocsFeaturesProjects::class)->name('projects');
        Route::get('/expenses', DocsFeaturesExpenses::class)->name('expenses');
        Route::get('/ai-credits', DocsFeaturesAiCredits::class)->name('ai-credits');
        Route::get('/users', DocsFeaturesUsers::class)->name('users');
        Route::get('/instant-messaging', DocsFeaturesInstantMessaging::class)->name('instant-messaging');
        Route::get('/my-integrations', DocsFeaturesMyIntegrations::class)->name('my-integrations');
        Route::get('/dashboard', DocsFeaturesDashboard::class)->name('dashboard');
        Route::get('/chat', DocsFeaturesChat::class)->name('chat');
        Route::get('/live-support', DocsFeaturesLiveSupport::class)->name('live-support');
        Route::get('/learning', DocsFeaturesLearning::class)->name('learning');
        Route::get('/security', DocsFeaturesSecurity::class)->name('security');
    });

});

Route::match(['post', 'get'], '/e/{slug}', [EndpointController::class, 'process'])->name('endpoint.process')->middleware('endpoint.rate.limit');
Route::match(['post', 'get'], '/e/resume/{log}', [EndpointController::class, 'resume'])->name('endpoint.resume')->middleware('endpoint.rate.limit');

Route::middleware(['auth'])->group(function () {

    Route::get('/premium/contact', function () {
        return view('agentoom-core::premium.contact');
    })->name('premium.contact');
    Route::get('/', function () {
        $user = auth()->user();
        if ($user->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->route('admin.dashboard');
        }

        if (Route::has('admin.widgets.index')) {
            return redirect()->route('admin.widgets.index');
        }

        return redirect()->route('admin.dashboard');
    });

    Route::get('/home', function () {
        $user = auth()->user();
        if ($user->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->route('admin.dashboard');
        }

        if (Route::has('admin.widgets.index')) {
            return redirect()->route('admin.widgets.index');
        }

        return redirect()->route('admin.dashboard');
    })->name('home');

    Route::get('/onboarding', OnboardingWizard::class)->name('admin.onboarding');
    Route::post('/onboarding/rerun', function () {
        Configuration::setValue('onboarding_complete', '0');

        return redirect()->route('admin.onboarding');
    })->name('admin.onboarding.rerun');

    Route::prefix('admin')->name('admin.')->middleware('check.onboarding')->group(function () {
        Route::middleware(['admin'])->group(function () {
            Route::get('/dashboard', Dashboard::class)->name('dashboard');
            Route::get('/monitoring', MonitoringIndex::class)->name('monitoring');

            // Admin locale switcher (Admin + Superadmin)
            Route::post('/locale', function (Request $request) {
                $code = (string) $request->input('code');
                $exists = Language::where('is_active', true)->where('code', $code)->exists();
                if ($exists) {
                    session(['admin_locale' => $code]);
                    app()->setLocale($code);
                }

                return back();
            })->name('set-locale');

            Route::get('ai-credits', AiCreditIndex::class)->name('ai-credits.index');

            Route::get('users', UserIndex::class)->name('users.index');
            Route::get('users/create', UserCreateEdit::class)->name('users.create');
            Route::get('users/{user}/memories', UserMemoryIndex::class)->name('users.memories');
            Route::get('users/{user}/edit', UserCreateEdit::class)->name('users.edit');
            Route::get('security', SecuritySettings::class)->name('security')->middleware('password.confirm');

            Route::get('chat/embed', [ChatController::class, 'embed'])->name('chat.embed');
            Route::get('chat/{conversationId?}', [ChatController::class, 'index'])->name('chat');
            Route::get('v2/chat', [ChatController::class, 'index'])->name('v2.chat.index');
            Route::post('v2/chat/send', [ChatController::class, 'send'])->name('v2.chat.send');
            Route::post('v2/chat/ask/{conversationId}', [ChatController::class, 'ask'])->name('v2.chat.ask');
            Route::post('v2/chat/stop/{conversationId}', [ChatController::class, 'stop'])->name('v2.chat.stop');
            Route::delete('v2/chat/{id}', [ChatController::class, 'destroy'])->name('v2.chat.destroy');
            Route::get('v2/chat/message/{messageId}', [ChatController::class, 'fetchMessage'])->name('v2.chat.fetch-message');
            Route::post('v2/chat/{conversationId}/share', [ChatController::class, 'share'])->name('v2.chat.share');
            Route::delete('v2/chat/{conversationId}/share', [ChatController::class, 'unshare'])->name('v2.chat.unshare');

            Route::get('projects', ProjectIndex::class)->name('projects.index');
            Route::get('projects/create', ProjectCreateEdit::class)->name('projects.create');
            Route::get('projects/expenses', ProjectExpensesIndex::class)->name('projects.expenses');
            Route::get('projects/{project}/edit', ProjectCreateEdit::class)->name('projects.edit');
            Route::get('projects/{project}/knowledge', ProjectKnowledgeManager::class)->name('projects.knowledge-base');

            Route::get('pipelines', PipelineIndex::class)->name('pipelines.index');
            Route::get('pipelines/{pipeline}/runs', PipelineRunIndex::class)->name('pipelines.runs');
            Route::get('pipelines/{pipeline}/runs/{run}', PipelineRunShow::class)->name('pipelines.runs.show');
        });

        Route::middleware(['auth'])->group(function () {
            Route::get('agent-tools', AgentToolIndex::class)->name('agent-tools.index');
            Route::get('my-integrations', UserIntegrationIndex::class)->name('my-integrations.index');

            Route::get('resources', ResourceIndex::class)->name('resources.index');
            Route::get('resources/create', ResourceCreateEdit::class)->name('resources.create');
            Route::get('resources/{resource}/edit', ResourceCreateEdit::class)->name('resources.edit');

            Route::get('oauth/redirect/{service}', [OAuthController::class, 'redirect'])->name('oauth.redirect');
            Route::get('oauth/callback/{service}', [OAuthController::class, 'callback'])->name('oauth.callback');
        });

        Route::middleware(['admin'])->group(function () {
            Route::middleware('permission:agents.view')->group(function () {
                Route::get('agents', AgentIndex::class)->name('agents.index');
            });
            Route::get('agents/create', AgentCreateEdit::class)->name('agents.create')->middleware('permission:agents.create');
            Route::get('agents/{agent}/edit', AgentCreateEdit::class)->name('agents.edit')->middleware('permission:agents.edit');
            Route::get('agents/{agent}/knowledge', AgentKnowledgeManager::class)->name('agents.knowledge')->middleware('permission:agents.edit');
            Route::get('agents/{agent}/learned-skills', AgentLearnedSkillsManager::class)->name('agents.learned-skills')->middleware('permission:agents.edit');

            Route::middleware('permission:ai-tasks.view')->group(function () {
                Route::get('ai-tasks', AiTaskIndex::class)->name('agents.ai-tasks.index');
            });
            Route::get('ai-tasks/create', AiTaskCreateEdit::class)->name('agents.ai-tasks.create')->middleware('permission:ai-tasks.create');
            Route::get('ai-tasks/{task}/edit', AiTaskCreateEdit::class)->name('agents.ai-tasks.edit')->middleware('permission:ai-tasks.edit');

            Route::middleware('permission:skills.view')->group(function () {
                Route::get('skills', SkillIndex::class)->name('skills.index');
            });
            Route::get('skills/create', SkillCreateEdit::class)->name('skills.create')->middleware('permission:skills.create');
            Route::get('skills/{skill}/edit', SkillCreateEdit::class)->name('skills.edit')->middleware('permission:skills.edit');

            Route::middleware('permission:resource-groups.view')->group(function () {
                Route::get('resource-groups', ResourceGroupIndex::class)->name('resource-groups.index');
            });
            Route::get('resource-groups/create', ResourceGroupCreateEdit::class)->name('resource-groups.create')->middleware('permission:resource-groups.create');
            Route::get('resource-groups/{resourceGroup}/edit', ResourceGroupCreateEdit::class)->name('resource-groups.edit')->middleware('permission:resource-groups.edit');

            Route::middleware('permission:resource-types.view')->group(function () {
                Route::get('resource-types', ResourceTypeIndex::class)->name('resource-types.index');
            });
            Route::get('resource-types/create', ResourceTypeCreateEdit::class)->name('resource-types.create')->middleware('permission:resource-types.create');
            Route::get('resource-types/{resourceType}/edit', ResourceTypeCreateEdit::class)->name('resource-types.edit')->middleware('permission:resource-types.edit');

            Route::middleware('permission:roles.view')->group(function () {
                Route::get('roles', RoleIndex::class)->name('roles.index');
            });
            Route::get('roles/create', RoleCreateEdit::class)->name('roles.create')->middleware('permission:roles.create');
            Route::get('roles/{role}/edit', RoleCreateEdit::class)->name('roles.edit')->middleware('permission:roles.edit');

            Route::middleware('permission:providers.view')->group(function () {
                Route::get('providers', ProviderIndex::class)->name('providers.index');
            });
            Route::get('providers/create', ProviderCreateEdit::class)->name('providers.create')->middleware('permission:providers.create');
            Route::get('providers/{provider}/edit', ProviderCreateEdit::class)->name('providers.edit')->middleware('permission:providers.edit');

            Route::middleware('permission:mcp-servers.view')->group(function () {
                Route::get('mcp-servers', McpServerIndex::class)->name('mcp-servers.index');
            });
            Route::get('mcp-servers/create', McpServerCreateEdit::class)->name('mcp-servers.create')->middleware('permission:mcp-servers.create');
            Route::get('mcp-servers/{server}/edit', McpServerCreateEdit::class)->name('mcp-servers.edit')->middleware('permission:mcp-servers.edit');

            Route::middleware('permission:languages.view')->group(function () {
                Route::get('languages', LanguageIndex::class)->name('languages.index');
            });
            Route::get('languages/create', LanguageCreateEdit::class)->name('languages.create')->middleware('permission:languages.create');
            Route::get('languages/{language}/edit', LanguageCreateEdit::class)->name('languages.edit')->middleware('permission:languages.edit');

            Route::middleware('permission:configs.view')->group(function () {
                Route::get('configs', ConfigIndex::class)->name('configs.index');
            });
            Route::get('configs/commands', CommandIndex::class)->name('configs.commands')->middleware('permission:commands.view');
            Route::get('configs/system-workers', SystemWorkers::class)->name('configs.system-workers')->middleware('permission:system-workers.view');
            Route::get('configs/email', EmailConfiguration::class)->name('configs.email')->middleware('permission:integrations.view');
            Route::get('configs/skill-presets', SkillPresets::class)->name('configs.skill_presets')->middleware('permission:skill-presets.view');

            Route::get('import-export', ImportExport::class)->name('import-export')->middleware('permission:import-export.view');

            Route::get('pipelines/legacy/create', PipelineCreateEdit::class)->name('pipelines.legacy.create')->middleware(['permission:pipelines.create']);
            Route::get('pipelines/legacy/{pipeline}/edit', PipelineCreateEdit::class)->name('pipelines.legacy.edit')->middleware(['permission:pipelines.edit']);

            Route::get('pipelines/create', [PipelineController::class, 'create'])->name('pipelines.create')->middleware(['permission:pipelines.create']);
            Route::get('pipelines/{pipeline}/edit', [PipelineController::class, 'edit'])->name('pipelines.edit')->middleware(['permission:pipelines.edit']);
            Route::post('pipelines', [PipelineController::class, 'store'])->name('pipelines.store')->middleware(['permission:pipelines.create']);
            Route::put('pipelines/{pipeline}', [PipelineController::class, 'update'])->name('pipelines.update')->middleware(['permission:pipelines.edit']);
            Route::post('pipelines/{pipeline}/clone', [PipelineController::class, 'clone'])->name('pipelines.clone');

            Route::middleware('permission:endpoints.view')->group(function () {
                Route::get('endpoints', EndpointIndex::class)->name('endpoints.index');
                Route::get('endpoints/{endpoint}/logs', EndpointLogIndex::class)->name('endpoints.logs');
            });
            Route::get('endpoints/create', EndpointCreateEdit::class)->name('endpoints.create')->middleware('permission:endpoints.create');
            Route::get('endpoints/{endpoint}/edit', EndpointCreateEdit::class)->name('endpoints.edit')->middleware('permission:endpoints.edit');

            // Webhook Subscriptions
            Route::middleware('permission:dashboard.view')->group(function () {
                Route::get('webhook-subscriptions', WebhookSubscriptionIndex::class)->name('webhook-subscriptions.index');
                Route::get('webhook-subscriptions/create', WebhookSubscriptionCreateEdit::class)->name('webhook-subscriptions.create');
                Route::get('webhook-subscriptions/{subscription}/edit', WebhookSubscriptionCreateEdit::class)->name('webhook-subscriptions.edit');
            });
        });

        Route::get('schedules', ScheduleIndex::class)->name('schedules.index');
        Route::get('schedules/create', ScheduleCreateEdit::class)->name('schedules.create');
        Route::get('schedules/{schedule}/edit', ScheduleCreateEdit::class)->name('schedules.edit');
        Route::get('schedules/{schedule}/logs', ScheduleLogIndex::class)->name('schedules.logs');

    });
});
