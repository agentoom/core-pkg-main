<?php

namespace Agentoom\Core\Console\Commands;

use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateMissingProjectSystemUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agentoom:create-missing-project-system-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create system users for projects that do not have one yet';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $projects = Project::whereNull('system_user_id')->get();

        if ($projects->isEmpty()) {
            $this->info('No projects missing system users.');

            return;
        }

        $this->info("Found {$projects->count()} projects missing system users. Creating them now...");

        foreach ($projects as $project) {
            $email = "project.{$project->id}.system@agentoom.internal";

            $user = User::where('email', $email)->first();

            if (! $user) {
                $user = User::create([
                    'name' => "System: {$project->name}",
                    'email' => $email,
                    'password' => bcrypt(Str::random(32)),
                    'is_system' => true,
                ]);
            }

            $project->update(['system_user_id' => $user->id]);

            $this->line("Created system user for project: {$project->name} ({$project->id})");
        }

        $this->info('Finished creating missing system users.');
    }
}
