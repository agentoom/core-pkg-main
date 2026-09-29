<?php

namespace Agentoom\Core\Console\Commands;

use Agentoom\Core\Models\AgentConversation;
use Agentoom\Core\Models\Configuration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:prune-conversations')]
#[Description('Delete AgentConversation records older than the configured retention period.')]
class PruneAiConversations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) Configuration::getValue('conversation_retention_days', 0);

        if ($days === 0) {
            $this->info('Retention days is 0 — skipping pruning.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $deleted = 0;

        AgentConversation::where('created_at', '<', $cutoff)
            ->chunkById(500, function ($conversations) use (&$deleted) {
                foreach ($conversations as $conversation) {
                    $conversation->delete();
                    $deleted++;
                }
            });

        $this->info("Pruned {$deleted} conversation(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
