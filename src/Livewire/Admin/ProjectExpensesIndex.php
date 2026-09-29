<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AiUsageLog;
use Agentoom\Core\Models\Project;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class ProjectExpensesIndex extends Component
{
    use WithPagination;

    public $projectId = null;

    public $agentId = null;

    public $dateFrom = null;

    public $dateTo = null;

    public $actionType = null;

    public $totalCostFiltered = 0.0;

    public $totalRequests = 0;
    public $unpricedCount = 0;

    public $chartData = [];

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updated($propertyName): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $usageQuery = AiUsageLog::query()->with(['user', 'agent', 'project']);

        if ($this->projectId) {
            $usageQuery->where('project_id', $this->projectId);
        }

        if ($this->agentId) {
            $usageQuery->where('agent_id', $this->agentId);
        }

        if ($this->actionType) {
            $usageQuery->where('action_type', 'like', '%'.$this->actionType.'%');
        }

        if ($this->dateFrom) {
            $usageQuery->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $usageQuery->whereDate('created_at', '<=', $this->dateTo);
        }

        // Clone query for stats
        $statsQuery = clone $usageQuery;
        $this->totalRequests = $statsQuery->count();
        $this->totalCostFiltered = (float) $statsQuery->sum('cost');
        $this->unpricedCount = (clone $statsQuery)->where('pricing_source', 'unpriced')->count();

        // Breakdown by project
        $projectsBreakdown = (clone $usageQuery)
            ->selectRaw('project_id, SUM(cost) as total_cost')
            ->groupBy('project_id')
            ->with('project')
            ->get();

        // Breakdown by agent
        $agentsBreakdown = (clone $usageQuery)
            ->selectRaw('agent_id, SUM(cost) as total_cost')
            ->groupBy('agent_id')
            ->with('agent')
            ->get();

        // Breakdown by action type
        $typesBreakdown = (clone $usageQuery)
            ->selectRaw('action_type, SUM(cost) as total_cost')
            ->groupBy('action_type')
            ->get();

        // Chart data
        $rawChartData = (clone $usageQuery)
            ->selectRaw('DATE(created_at) as date, SUM(cost) as cost')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('cost', 'date')
            ->toArray();

        $this->chartData = [];
        $start = Carbon::parse($this->dateFrom);
        $end = Carbon::parse($this->dateTo);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateString = $date->format('Y-m-d');
            $this->chartData[] = [
                'date' => $dateString,
                'cost' => (float) ($rawChartData[$dateString] ?? 0.0),
            ];
        }

        return view('agentoom-core::livewire.admin.project-expenses-index', [
            'usageLogs' => $usageQuery->latest()->paginate(25),
            'projects' => Project::orderBy('name')->get(),
            'agents' => Agent::orderBy('name')->get(),
            'projectsBreakdown' => $projectsBreakdown,
            'agentsBreakdown' => $agentsBreakdown,
            'typesBreakdown' => $typesBreakdown,
            'actionTypes' => AiUsageLog::distinct()->pluck('action_type'),
        ])->layout('agentoom-core::layouts.admin');
    }
}
