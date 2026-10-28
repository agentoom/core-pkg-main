<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AiCreditTopup;
use Agentoom\Core\Models\AiUsageLog;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Services\AiCreditService;
use Carbon\Carbon;
use Flux\Flux;
use Livewire\Component;
use Livewire\WithPagination;

class AiCreditIndex extends Component
{
    use WithPagination;

    public $userId = null;

    public $agentId = null;

    public $dateFrom = null;

    public $dateTo = null;

    public $totalRequests = 0;

    public $totalTokensFiltered = 0;

    public $totalCostFiltered = 0.0;

    public $unpricedCount = 0;

    public $providerCostFiltered = 0.0;

    public $chartData = [];

    public $topupAmount = 10.0;

    public $removeAmount = 10.0;

    public string $activeTab = 'usage';

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(7)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');

        $active_tab = array_find(['usage', 'buy', 'history'],
            fn ($tab) => request()->query('tab') === $tab) ?? 'usage';

        if ($active_tab) {
            $this->activeTab = $active_tab;
        }
    }

    public function buyPackage(string $packageId): void
    {
        if (! auth()->user()->hasRole(['admin', 'superadmin'])) {
            return;
        }

        $package = collect(config('ai-credits.packages'))->firstWhere('id', $packageId);

        if (! $package) {
            Flux::toast('Invalid package selected.', variant: 'danger');

            return;
        }

        try {
            $topup = AiCreditTopup::create([
                'user_id' => auth()->id(),
                'uuid' => (string) str()->ulid(),
                'token' => str()->random(32),
                'status' => AiCreditTopup::STATUS_AWAITING_PAYMENT,
                'user_email' => auth()->user()->email,
                'amount' => $package['price'],
                'type' => 'add',
                'currency' => $package['currency'] ?? 'USD',
            ]);

            \Log::info('Purchase initiated', [
                'purchase_uuid' => $topup->uuid,
                'user_id' => $topup->user_id,
                'amount' => $topup->amount,
            ]);

            $this->dispatch('initiate-purchase', [
                'url' => 'https://agentoom.com/buy/ai-credits',
                'data' => [
                    'purchase_uuid' => $topup->uuid,
                    'domain' => request()->getHost(),
                    'email' => $topup->user_email,
                    'amount' => $topup->amount,
                    'token' => $topup->token,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Purchase Initiation Error: '.$e->getMessage());
            Flux::toast('Could not initiate purchase.', variant: 'danger');
        }
    }

    public function updated($propertyName): void
    {
        $this->resetPage('usageLogsPage');
        $this->resetPage('topupHistoryPage');
    }

    public function topup(): void
    {
        if (! auth()->user()->hasRole('superadmin')) {
            return;
        }

        $this->validate([
            'topupAmount' => 'required|numeric|min:0.01',
        ]);

        app(AiCreditService::class)->addCredits(auth()->user(), $this->topupAmount);

        Flux::toast(__('agentoom-core::admin.ai_credits.topup_success'));
        Flux::modal('topup-modal')->close();
    }

    public function remove(): void
    {
        if (! auth()->user()->hasRole('superadmin')) {
            return;
        }

        $this->validate([
            'removeAmount' => 'required|numeric|min:0.01',
        ]);

        app(AiCreditService::class)->removeCredits(auth()->user(), $this->removeAmount);

        Flux::toast(__('agentoom-core::admin.ai_credits.remove_success'));
        Flux::modal('remove-modal')->close();
    }

    public function render()
    {
        $usageQuery = AiUsageLog::query()->with(['user', 'agent']);

        if ($this->userId) {
            $usageQuery->where('user_id', $this->userId);
        }

        if ($this->agentId) {
            $usageQuery->where('agent_id', $this->agentId);
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
        $this->totalTokensFiltered = $statsQuery->sum('total_tokens');
        $this->totalCostFiltered = (float) $statsQuery->sum('cost');
        $this->unpricedCount = (clone $statsQuery)->where('pricing_source', 'unpriced')->count();
        $this->providerCostFiltered = (float) $statsQuery->sum('provider_cost');

        // Clone query for chart data
        $chartQuery = clone $usageQuery;
        $rawChartData = $chartQuery->selectRaw('DATE(created_at) as date, SUM(cost) as cost')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('cost', 'date')
            ->toArray();

        $this->chartData = [];
        $start = Carbon::parse($this->dateFrom);
        $end = Carbon::parse($this->dateTo);

        for ($date = $start->copy(); $date->lte($end); $date = $date->addDay()) {
            $dateString = $date->format('Y-m-d');
            $this->chartData[] = [
                'date' => $dateString,
                'credits' => (float) ($rawChartData[$dateString] ?? 0.0),
            ];
        }

        $topupQuery = AiCreditTopup::with('user');

        return view('agentoom-core::livewire.admin.ai-credit-index', [
            'totalCredits' => (float) Configuration::getValue('app_ai_credits', 0.0),
            'usageLogs' => $usageQuery->latest()->paginate(25, ['*'], 'usageLogsPage'),
            'topupHistory' => $topupQuery->latest()->paginate(25, ['*'], 'topupHistoryPage'),
            'users' => auth()->user()->getAssignableUsers(),
            'agents' => Agent::orderBy('name')->get(),
        ])->layout('agentoom-core::layouts.admin');
    }
}
