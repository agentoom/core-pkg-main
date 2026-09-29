<?php

namespace Agentoom\Core\Jobs;

use Agentoom\Core\Services\ProviderModelSyncService;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class SyncProviderModelsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(ProviderModelSyncService $syncService): void
    {
        $syncService->syncAllProviders();
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('ai-provider-model-sync'))->expireAfter(1800)];
    }
}
