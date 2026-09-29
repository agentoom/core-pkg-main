<?php

namespace Agentoom\Core\Console\Commands;

use Agentoom\Core\Services\MemoryWorkerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AnalyzeMemoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agentoom:analyze-memory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze user conversations to extract identity and successful task patterns (skills).';

    /**
     * Execute the console command.
     */
    public function handle(MemoryWorkerService $service): void
    {
        $this->info('Starting memory analysis...');

        $unanalyzedCount = DB::table('agent_conversation_messages')
            ->where('is_analyzed', false)
            ->count();

        $this->info("Found {$unanalyzedCount} unanalyzed messages.");

        if ($unanalyzedCount > 0) {
            $service->processUnanalyzedMessages();
            $this->info('Memory analysis completed.');
        } else {
            $this->info('No messages to analyze.');
        }
    }
}
