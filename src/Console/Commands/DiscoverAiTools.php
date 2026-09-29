<?php

namespace Agentoom\Core\Console\Commands;

use Agentoom\Core\Services\McpDiscoveryService;
use Illuminate\Console\Command;

class DiscoverAiTools extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agentoom:discover-tools';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover and register all AI tools from the Agentoom\Core\Ai\Tools namespace';

    /**
     * Execute the console command.
     */
    public function handle(McpDiscoveryService $discoveryService): int
    {
        $this->info('Discovering AI tools...');

        $tools = $discoveryService->discoverAndRegister(function ($tool) {
            $this->line("  - {$tool->name} ({$tool->class_name})");
        });

        if (empty($tools)) {
            $this->warn('No tools were discovered.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('✓ Discovered '.count($tools).' tool(s).');
        $this->info('✓ Tool discovery completed successfully!');

        return self::SUCCESS;
    }
}
