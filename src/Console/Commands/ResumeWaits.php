<?php

namespace Agentoom\Core\Console\Commands;

use Agentoom\Core\Jobs\ResumePipelineJob;
use Agentoom\Core\Models\PipelineRun;
use Illuminate\Console\Command;

class ResumeWaits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agentoom:resume-waits';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resume paused pipeline runs that have reached their resume_at time.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $now = now();
        $runs = PipelineRun::where('status', 'paused')
            ->whereNotNull('resume_at')
            ->where('resume_at', '<=', $now)
            ->get();

        if ($runs->isEmpty()) {
            return;
        }

        $this->info("Found {$runs->count()} pipeline runs to resume.");

        foreach ($runs as $run) {
            $this->info("Resuming pipeline run #{$run->id} (Scheduled for: {$run->resume_at})...");

            // Clear resume_at first to avoid duplicate resumes if the job takes time.
            // Using query update to avoid potential stale model issues.
            PipelineRun::where('id', $run->id)->update(['resume_at' => null]);

            // Dispatch the resume job
            dispatch(new ResumePipelineJob($run->id))->afterCommit();
        }

        $this->info('Finished resuming pipeline runs.');
    }
}
