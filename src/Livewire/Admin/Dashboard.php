<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\AiUsageLog;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Services\ResourceLimitManager;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class Dashboard extends Component
{
    public function getResourceIcon(string $resource): string
    {
        return match ($resource) {
            'agents' => 'user-circle',
            'pipelines' => 'queue-list',
            'endpoints' => 'bolt',
            'resource_types' => 'rectangle-stack',
            'chat_widgets' => 'chat-bubble-left-right',
            'accessibility_widgets' => 'sparkles',
            'forms' => 'document-text',
            'prompts' => 'chat-bubble-bottom-center-text',
            'scraped_data' => 'globe-alt',
            'projects' => 'folder',
            default => 'rectangle-group',
        };
    }

    public function getResourceRoute(string $resource): ?string
    {
        $route = match ($resource) {
            'agents' => 'admin.agents.index',
            'pipelines' => 'admin.pipelines.index',
            'endpoints' => 'admin.endpoints.index',
            'resource_types' => 'admin.resource-types.index',
            'chat_widgets' => 'admin.widgets.index',
            'accessibility_widgets' => 'admin.accessibility-widgets.index',
            'forms' => 'admin.form.index',
            'prompts' => 'admin.prompt.index',
            'scraped_data' => 'admin.scraped-data.index',
            'projects' => 'admin.projects.index',
            default => 'admin.dashboard',
        };

        // Routes owned by an optional package are absent when it is uninstalled.
        return Route::has($route) ? route($route) : null;
    }

    public function render()
    {
        Gate::authorize('dashboard.view');

        if (session()->has('success')) {
            Flux::toast(session('success'), variant: 'success');
        }

        if (session()->has('error')) {
            Flux::toast(session('error'), variant: 'danger');
        }

        $limitManager = app(ResourceLimitManager::class);
        $usageSummary = $limitManager->getSummary();

        // Capabilities whose package is not installed have no landing route.
        $usageSummary = array_filter(
            $usageSummary,
            fn (string $resource): bool => $this->getResourceRoute($resource) !== null,
            ARRAY_FILTER_USE_KEY
        );

        // Credit consumption last 7 days
        $dateFrom = now()->subDays(6)->startOfDay();
        $dateTo = now()->endOfDay();

        $rawChartData = AiUsageLog::query()
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw('DATE(created_at) as date, SUM(total_tokens) as tokens')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('tokens', 'date')
            ->toArray();

        $chartData = [];
        for ($date = $dateFrom->copy(); $date->lte($dateTo); $date = $date->addDay()) {
            $dateString = $date->format('Y-m-d');
            $chartData[] = [
                'date' => $dateString,
                'tokens' => (int) ($rawChartData[$dateString] ?? 0),
            ];
        }

        return view('agentoom-core::livewire.admin.dashboard', [
            'usageSummary' => $usageSummary,
            'chartData' => $chartData,
            'totalCredits' => (int) Configuration::getValue('app_ai_credits', 0),
        ])->layout('agentoom-core::layouts.admin');
    }
}
