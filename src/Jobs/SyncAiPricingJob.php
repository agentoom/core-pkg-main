<?php

namespace Agentoom\Core\Jobs;

use Agentoom\Core\Services\AiPricingSyncService;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncAiPricingJob implements ShouldQueue
{
    use Queueable;

    public function handle(AiPricingSyncService $sync): void
    {
        $sync->sync('litellm');
        $sync->sync('models_dev');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('ai-pricing-sync'))->expireAfter(1800)];
    }
}
