<?php

namespace Agentoom\Core\Console\Commands;

use Agentoom\Core\Services\ImportService;
use Illuminate\Console\Command;

class CreateSystemResources extends Command
{
    protected $signature = 'agentoom:create-resources {--agents : Import only agents} {--pipelines : Import only pipelines} {--skills : Import only skills}';

    protected $description = 'Create system agents, pipelines and skills';

    public function handle(ImportService $importService): int
    {
        $importAgents = $this->option('agents');
        $importPipelines = $this->option('pipelines');
        $importSkills = $this->option('skills');

        // If no specific option is provided, import all
        if (! $importAgents && ! $importPipelines && ! $importSkills) {
            $importAgents = true;
            $importPipelines = true;
            $importSkills = true;
        }

        $resources = [];

        if ($importAgents) {
            $this->info('Creating System Agents...');
            $resources['agents'] = config('agentoom-system-resources.agents', []);
            $resources['resource_types'] = config('agentoom-system-resources.resource_types', []);
            $resources['configurations'] = config('agentoom-system-resources.configurations', []);
        }

        if ($importPipelines) {
            $this->info('Creating System Pipelines...');
            $resources['pipelines'] = config('agentoom-system-resources.pipelines', []);
        }

        if ($importSkills) {
            $this->info('Creating System Skills...');
            $resources['skills'] = config('agentoom-system-resources.skills', []);
        }

        $importService->import($resources, [
            'agents' => ['only_if_config_empty' => true],
        ]);

        $this->info('Resources created successfully!');

        return self::SUCCESS;
    }
}
