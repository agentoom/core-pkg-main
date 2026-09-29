<?php

namespace Agentoom\Core\Tests\Feature\Ai\Agents;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Ai\Tools\Public\ScheduleRepositoryTool;
use Agentoom\Core\Execution\ToolPipelineWrapper;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DynamicAgentScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_includes_schedule_repository_tool_when_executing_schedule(): void
    {
        $agentModel = Agent::factory()->create();
        $user = User::factory()->create();
        $schedule = Schedule::create([
            'name' => 'Test Schedule',
            'minute' => '*',
            'hour' => '*',
            'day' => '*',
            'month' => '*',
            'day_of_week' => '*',
            'user_id' => $user->id,
            'model' => Agent::class,
            'model_id' => $agentModel->id,
            'repository_instructions' => 'Save the last processed ID',
            'active' => true,
        ]);

        $agent = new DynamicAgent($agentModel, scheduleId: $schedule->id);

        $tools = $agent->tools();

        $hasRepoTool = collect($tools)->contains(function ($t) {
            return $t instanceof ToolPipelineWrapper
                && $t->getTool() instanceof ScheduleRepositoryTool;
        });

        $this->assertTrue($hasRepoTool, 'ScheduleRepositoryTool should be present wrapped in ToolPipelineWrapper');

        $instructions = $agent->instructions();
        $this->assertStringContainsString('Schedule Context', $instructions);
        $this->assertStringContainsString('Save the last processed ID', $instructions);
        $this->assertStringContainsString((string) $schedule->id, $instructions);
    }
}
