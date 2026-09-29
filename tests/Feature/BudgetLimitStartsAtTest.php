<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AiTask;
use Agentoom\Core\Models\AiUsageLog;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BudgetLimitStartsAtTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_budget_limit_respects_starts_at_date()
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Test Project',
            'budget_limit' => 100.0,
            'budget_limit_starts_at' => Carbon::now()->addDay()->format('Y-m-d'), // Tomorrow
        ]);

        // Create usage for today
        AiUsageLog::create([
            'project_id' => $project->id,
            'action_type' => 'test',
            'total_tokens' => 1000,
            'cost' => 50.0,
        ]);

        $this->assertEquals(0.0, (float) $project->total_spent, 'Total spent should be 0 because all logs are before starts_at');

        // Change starts_at to yesterday
        $project->update(['budget_limit_starts_at' => Carbon::now()->subDay()->format('Y-m-d')]);
        $project->refresh();
        $this->assertEquals(50.0, (float) $project->total_spent, 'Total spent should be 50 because logs are after starts_at');
    }

    public function test_agent_limit_respects_starts_at_date()
    {
        $agent = Agent::create([
            'name' => 'Test Agent',
            'max_usd_limit' => 100.0,
            'limit_period' => 'monthly',
            'limit_starts_at' => Carbon::now()->addDay()->format('Y-m-d'), // Tomorrow
        ]);

        AiUsageLog::create([
            'agent_id' => $agent->id,
            'action_type' => 'test',
            'total_tokens' => 1000,
            'cost' => 50.0,
        ]);

        $this->assertEquals(0.0, (float) $agent->getUsageForPeriod(), 'Usage should be 0 because all logs are before starts_at');

        $agent->update(['limit_starts_at' => Carbon::now()->subDay()->format('Y-m-d')]);
        $agent->refresh();
        $this->assertEquals(50.0, (float) $agent->getUsageForPeriod(), 'Usage should be 50 because logs are after starts_at');
    }

    public function test_agent_task_limit_respects_starts_at_date()
    {
        $agent = Agent::create([
            'name' => 'Test Agent',
            'max_tasks_per_period' => 10,
            'task_limit_period' => 'monthly',
            'task_limit_starts_at' => Carbon::now()->addDay()->format('Y-m-d'), // Tomorrow
        ]);

        $task = AiTask::create([
            'agent_id' => $agent->id, // Who executes (not used in getTaskUsageForPeriod)
            'assigned_by_agent_id' => $agent->id, // Who assigned (used in getTaskUsageForPeriod)
            'name' => 'Test Task',
            'description' => 'Test Description',
            'status' => 'completed',
        ]);

        $this->assertEquals(0, $agent->getTaskUsageForPeriod(), 'Task usage should be 0 because all tasks are before starts_at');

        $agent->update(['task_limit_starts_at' => Carbon::now()->subDay()->format('Y-m-d')]);
        $agent->refresh();
        $this->assertEquals(1, $agent->getTaskUsageForPeriod(), 'Task usage should be 1 because tasks are after starts_at');
    }
}
