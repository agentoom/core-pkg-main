<?php

namespace Agentoom\Core\Console\Commands;

use Agentoom\Core\Services\AiPricingSyncService;
use Illuminate\Console\Command;

class SyncAiPricing extends Command
{
    protected $signature = 'ai:sync-pricing {source? : litellm or models_dev} {--force : Ignore conditional request cache}';

    protected $description = 'Synchronize verified AI model prices from maintained catalogs';

    public function handle(AiPricingSyncService $sync): int
    {
        $source = $this->argument('source');
        $sources = $source ? [$source] : ['litellm', 'models_dev'];

        foreach ($sources as $name) {
            if (! in_array($name, ['litellm', 'models_dev'], true)) {
                $this->error("Unsupported pricing source: {$name}");

                return self::INVALID;
            }

            $result = $sync->sync($name, (bool) $this->option('force'));
            $this->line(sprintf('%s: %s (%d entries)', $name, $result['status'], $result['entry_count'] ?? 0));
            if (! empty($result['error'])) {
                $this->warn($result['error']);
            }
        }

        return self::SUCCESS;
    }
}
