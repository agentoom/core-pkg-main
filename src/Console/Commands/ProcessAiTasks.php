<?php

namespace Agentoom\Core\Console\Commands;

use Agentoom\Core\Jobs\ProcessAiTaskJob;
use Agentoom\Core\Models\AiTask;
use Illuminate\Console\Command;

class ProcessAiTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agentoom:process-ai-tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process due AI tasks';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $tasks = AiTask::due()->get();

        foreach ($tasks as $task) {
            ProcessAiTaskJob::dispatch($task->id);
        }

        $this->info('Due AI tasks dispatched.');
    }
}
