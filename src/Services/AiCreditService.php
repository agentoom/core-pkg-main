<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Ai\Support\CostCaptureStore;
use Agentoom\Core\Mail\AgentCreditLimitNotification;
use Agentoom\Core\Mail\ProjectBudgetLimitNotification;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AiCreditTopup;
use Agentoom\Core\Models\AiUsageLog;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

class AiCreditService
{
    public function hasSufficientCredits(?User $user, ?int $agentId = null, ?int $projectId = null): bool
    {
        if ((float) Configuration::getValue('app_ai_credits', 0.0) <= 0) {
            return false;
        }

        if ($projectId) {
            $project = Project::find($projectId);
            if ($project && (! $project->is_active || $project->hasExceededBudget())) {
                return false;
            }
        }

        if ($agentId) {
            $agent = Agent::find($agentId);
            if ($agent && $agent->is_hard_limit && $agent->isLimitReached()) {
                return false;
            }
        }

        if (! $user) {
            return true;
        }

        if ($user->ai_limit_type && $user->ai_limit_amount && $user->ai_limit_start_date) {
            if (now()->lt($user->ai_limit_start_date)) {
                return true;
            }

            return $this->getUserUsageInCurrentPeriod($user) < $user->ai_limit_amount;
        }

        return true;
    }

    public function logUsage(
        ?User $user,
        string $actionType,
        Usage|int $usage,
        Meta|string|null $meta = null,
        ?int $agentId = null,
        ?int $projectId = null,
        ?string $idempotencyKey = null,
    ): void {
        if (is_int($usage)) {
            $usageObj = new Usage($usage, 0);
            $metaObj = is_string($meta) ? new Meta($meta) : ($meta ?? new Meta);
        } else {
            $usageObj = $usage;
            $metaObj = is_string($meta) ? new Meta($meta) : ($meta ?? new Meta);
        }

        $resolution = $this->resolveCost($usageObj, $metaObj);
        $tokens = [
            'total_tokens' => $usageObj->promptTokens + $usageObj->completionTokens + $usageObj->cacheWriteInputTokens + $usageObj->cacheReadInputTokens + $usageObj->reasoningTokens,
            'input_tokens' => $usageObj->promptTokens,
            'output_tokens' => $usageObj->completionTokens,
            'cache_read_tokens' => $usageObj->cacheReadInputTokens,
            'cache_write_tokens' => $usageObj->cacheWriteInputTokens,
            'reasoning_tokens' => $usageObj->reasoningTokens,
        ];

        $log = DB::transaction(function () use ($resolution, $user, $actionType, $metaObj, $tokens, $agentId, $projectId, $idempotencyKey): AiUsageLog {
            if ($idempotencyKey) {
                $existing = AiUsageLog::query()
                    ->where('action_type', $actionType)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            if ($resolution) {
                Configuration::adjustNumeric('app_ai_credits', -$resolution->creditCost);
            }

            return AiUsageLog::create(array_merge($tokens, [
                'user_id' => $user?->id,
                'project_id' => $projectId,
                'agent_id' => $agentId,
                'action_type' => $actionType,
                'cost' => $resolution?->creditCost,
                'provider_cost' => $resolution?->providerCost,
                'pricing_source' => $resolution?->source ?? 'unpriced',
                'provider' => $resolution?->provider ?: $metaObj->provider,
                'model' => $resolution?->model ?: $metaObj->model,
                'priced_at' => $resolution?->pricedAt,
                'idempotency_key' => $idempotencyKey,
            ]));
        });

        if (! $resolution) {
            Log::warning('AI usage recorded without a verified price; no credits deducted', [
                'provider' => $metaObj->provider,
                'model' => $metaObj->model,
                'usage' => $usageObj->toArray(),
                'action_type' => $actionType,
                'pricing_source' => 'unpriced',
            ]);

            return;
        }

        if ($projectId) {
            $project = Project::find($projectId);
            if ($project && $project->hasExceededBudget()) {
                $this->notifyProjectBudgetLimitReached($project);
            }
        }

        if ($agentId) {
            $agent = Agent::find($agentId);
            if ($agent && $agent->hasCreditLimit() && $agent->isLimitReached()) {
                $this->notifyCreditLimitReached($agent);
            }
        }
    }

    public function resolveCost(Usage $usage, Meta $meta): ?AiCostResolution
    {
        return app(AiPricingResolver::class)->resolve($usage, $meta);
    }

    /**
     * Bill a provider-reported cost that was captured before its stream was
     * abandoned (user stop, job hand-over, mid-stream failure). Streams that
     * reported no cost stay unbilled, because their token usage is unknown.
     */
    public function flushCapturedUsage(
        ?User $user,
        string $actionType,
        ?string $provider,
        ?string $model,
        ?int $agentId = null,
        ?int $projectId = null,
    ): bool {
        $capture = app(CostCaptureStore::class);

        if (! $capture->isActive()) {
            return false;
        }

        if ($capture->total() === null) {
            $capture->end();

            return false;
        }

        $this->logUsage($user, $actionType, new Usage(0, 0, 0), new Meta($provider, $model), $agentId, $projectId);
        $capture->end();

        return true;
    }

    public function logGenericUsage(
        ?User $user,
        string $actionType,
        float $cost,
        ?int $agentId = null,
        ?int $projectId = null,
        ?string $idempotencyKey = null,
        string $currency = 'USD',
        ?string $provider = null,
    ): AiUsageLog {
        if (strtoupper($currency) !== 'USD') {
            throw new \InvalidArgumentException('Only USD costs can be recorded without an FX source.');
        }
        if (! is_finite($cost) || $cost < 0) {
            throw new \InvalidArgumentException('Cost must be a finite non-negative USD amount.');
        }

        $log = DB::transaction(function () use ($user, $actionType, $cost, $agentId, $projectId, $idempotencyKey, $provider): AiUsageLog {
            if ($idempotencyKey) {
                $existing = AiUsageLog::query()
                    ->where('action_type', $actionType)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            Configuration::adjustNumeric('app_ai_credits', -$cost);

            return AiUsageLog::create([
                'user_id' => $user?->id,
                'project_id' => $projectId,
                'agent_id' => $agentId,
                'action_type' => $actionType,
                'total_tokens' => 0,
                'cost' => $cost,
                'provider_cost' => $cost,
                'pricing_source' => 'mcp_reported',
                'provider' => $provider,
                'idempotency_key' => $idempotencyKey,
            ]);
        });

        if ($projectId) {
            $project = Project::find($projectId);
            if ($project && $project->hasExceededBudget()) {
                $this->notifyProjectBudgetLimitReached($project);
            }
        }

        if ($agentId) {
            $agent = Agent::find($agentId);
            if ($agent && $agent->hasCreditLimit() && $agent->isLimitReached()) {
                $this->notifyCreditLimitReached($agent);
            }
        }

        return $log;
    }

    protected function notifyCreditLimitReached(Agent $agent): void
    {
        $cacheKey = "agent_credit_limit_notified_{$agent->id}_{$agent->limit_period}";
        if (Cache::has($cacheKey)) {
            return;
        }

        $emails = Configuration::getValue('credit_limit_notification_emails', '');
        $emailList = array_map('trim', explode(',', (string) $emails));
        if ($agent->project?->user) {
            $emailList[] = $agent->project->user->email;
        }
        $emailList = array_unique(array_filter($emailList));

        foreach ($emailList as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($email)->send(new AgentCreditLimitNotification($agent));
                } catch (\Throwable $e) {
                    Log::error("Failed to send credit limit notification to {$email}: {$e->getMessage()}");
                }
            }
        }

        if ($emailList) {
            Cache::put($cacheKey, true, now()->endOfDay());
        }
    }

    protected function notifyProjectBudgetLimitReached(Project $project): void
    {
        $cacheKey = "project_budget_limit_notified_{$project->id}";
        if (Cache::has($cacheKey)) {
            return;
        }

        $emails = Configuration::getValue('credit_limit_notification_emails', '');
        $emailList = array_map('trim', explode(',', (string) $emails));
        if ($project->user) {
            $emailList[] = $project->user->email;
        }
        $emailList = array_unique(array_filter($emailList));

        foreach ($emailList as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($email)->send(new ProjectBudgetLimitNotification($project));
                } catch (\Throwable $e) {
                    Log::error("Failed to send project budget notification to {$email}: {$e->getMessage()}");
                }
            }
        }

        if ($emailList) {
            Cache::put($cacheKey, true, now()->endOfDay());
        }
    }

    public function addCreditsFromPayment(User $admin, float|int $amount, string $paymentMethod, string $paymentId, float $amountPaid, string $currency): void
    {
        DB::transaction(function () use ($admin, $amount, $paymentMethod, $paymentId, $amountPaid, $currency): void {
            if (AiCreditTopup::query()->where('payment_method', $paymentMethod)->where('payment_id', $paymentId)->lockForUpdate()->exists()) {
                Log::info("Payment already processed: {$paymentMethod} - {$paymentId}");

                return;
            }

            Configuration::adjustNumeric('app_ai_credits', (float) $amount, false);
            AiCreditTopup::create([
                'user_id' => $admin->id, 'amount' => $amount, 'type' => 'add',
                'payment_method' => $paymentMethod, 'payment_id' => $paymentId,
                'amount_paid' => $amountPaid, 'currency' => $currency,
            ]);
        });
    }

    public function fulfillTopup(AiCreditTopup $topup): void
    {
        DB::transaction(function () use ($topup): void {
            $locked = AiCreditTopup::query()->lockForUpdate()->find($topup->id);
            if (! $locked || $locked->status === AiCreditTopup::STATUS_COMPLETED) {
                return;
            }
            Configuration::adjustNumeric('app_ai_credits', (float) $locked->amount, false);
            $locked->update([
                'status' => AiCreditTopup::STATUS_COMPLETED,
                'payment_received_at' => now(), 'completed_at' => now(), 'payment_method' => 'centralized',
            ]);
        });
    }

    public function addCredits(User $admin, float|int $amount): void
    {
        DB::transaction(function () use ($admin, $amount): void {
            Configuration::adjustNumeric('app_ai_credits', (float) $amount, false);
            AiCreditTopup::create([
                'user_id' => $admin->id, 'amount' => $amount, 'type' => 'add', 'payment_method' => 'manual',
            ]);
        });
    }

    public function removeCredits(User $admin, float|int $amount): void
    {
        DB::transaction(function () use ($admin, $amount): void {
            Configuration::adjustNumeric('app_ai_credits', -((float) $amount));
            AiCreditTopup::create([
                'user_id' => $admin->id, 'amount' => $amount, 'type' => 'remove', 'payment_method' => 'manual',
            ]);
        });
    }

    public function getUserUsageInCurrentPeriod(User $user): float
    {
        if (! $user->ai_limit_type || ! $user->ai_limit_start_date) {
            return 0.0;
        }
        $now = now();
        $anchor = $user->ai_limit_start_date;
        if ($now->lt($anchor)) {
            return 0.0;
        }
        $periodStart = match ($user->ai_limit_type) {
            'daily' => $now->copy()->startOfDay(),
            'weekly' => $now->copy()->startOfWeek(),
            'monthly' => $now->copy()->startOfMonth(),
            default => $anchor,
        };
        if ($periodStart->lt($anchor)) {
            $periodStart = $anchor;
        }

        return (float) AiUsageLog::query()->where('user_id', $user->id)->where('created_at', '>=', $periodStart)->sum('cost');
    }

    public function getExhaustedCreditsResponse(): array
    {
        return [
            'status' => 'credits_exhausted',
            'message' => __('agentoom-core::admin.ai_usage_logs.exhausted_message'),
        ];
    }

    public function isLowCreditThresholdReached(): bool
    {
        return (float) Configuration::getValue('app_ai_credits', 0.0) < (float) Configuration::getValue('low_credit_threshold', 10.0);
    }
}
