<?php

namespace Agentoom\Core\Console\Commands;

use Agentoom\Core\Models\Provider;
use Agentoom\Core\Services\ProviderModelSyncService;
use Illuminate\Console\Command;

class SyncProviderModels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'providers:sync-models {provider? : The name or ID of the provider to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync available models from AI providers';

    /**
     * Execute the console command.
     */
    public function handle(ProviderModelSyncService $syncService)
    {
        $providerIdentifier = $this->argument('provider');

        if ($providerIdentifier) {
            // Sync specific provider
            $provider = is_numeric($providerIdentifier)
                ? Provider::find($providerIdentifier)
                : Provider::where('name', $providerIdentifier)->first();

            if (! $provider) {
                $this->error("Provider not found: {$providerIdentifier}");

                return Command::FAILURE;
            }

            $this->info("Syncing models for provider: {$provider->name}");
            $result = $syncService->syncProvider($provider);

            if ($result['success']) {
                $this->info("✓ Successfully synced {$result['synced_count']} models");
                if ($result['deleted_count'] > 0) {
                    $this->warn("⚠ Removed {$result['deleted_count']} models that are no longer available");
                }

                return Command::SUCCESS;
            } else {
                $this->error("✗ Error: {$result['error']}");

                return Command::FAILURE;
            }
        }

        // Sync all providers
        $this->info('Syncing models for all providers...');
        $providers = Provider::all();

        if ($providers->isEmpty()) {
            $this->warn('No providers found.');

            return Command::SUCCESS;
        }

        $totalSynced = 0;
        $totalDeleted = 0;
        $errors = [];

        foreach ($providers as $provider) {
            $this->line("Processing: {$provider->name}");
            $result = $syncService->syncProvider($provider);

            if ($result['success']) {
                $totalSynced += $result['synced_count'];
                $totalDeleted += $result['deleted_count'];
                $this->info("  ✓ Synced {$result['synced_count']} models");
            } else {
                $errors[] = "{$provider->name}: {$result['error']}";
                $this->error("  ✗ Error: {$result['error']}");
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->info("  Total models synced: {$totalSynced}");
        if ($totalDeleted > 0) {
            $this->warn("  Total models removed: {$totalDeleted}");
        }

        if (! empty($errors)) {
            $this->newLine();
            $this->error('Errors occurred:');
            foreach ($errors as $error) {
                $this->error("  • {$error}");
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
