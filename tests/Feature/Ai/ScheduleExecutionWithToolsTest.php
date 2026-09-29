<?php

namespace Agentoom\Core\Tests\Feature\Ai;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Ai\Tools\Public\ScheduleRepositoryTool;
use Agentoom\Core\Execution\ToolPipelineWrapper;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class ScheduleExecutionWithToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_repository_tool_is_available_and_working_in_context(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $agentModel = Agent::factory()->create();
        $schedule = Schedule::factory()->create([
            'user_id' => $user->id,
        ]);

        $agent = new DynamicAgent($agentModel, null, $schedule->id);
        $tools = $agent->tools();

        // ScheduleRepositoryTool is wrapped in ToolPipelineWrapper
        $repoToolWrapper = collect($tools)->first(function ($t) {
            return $t instanceof ToolPipelineWrapper
                && $t->getTool() instanceof ScheduleRepositoryTool;
        });

        $this->assertNotNull($repoToolWrapper);
        $this->assertEquals('ScheduleRepository', $repoToolWrapper->name());

        // Test update without providing 'id' in request
        $request = new Request([
            'action' => 'update',
            'key' => 'last_email_replied',
            'value' => 'msg-123',
        ]);

        $response = $repoToolWrapper->handle($request);
        $this->assertStringContainsString("Successfully updated repository key 'last_email_replied' for schedule ID {$schedule->id}", $response);

        $this->assertDatabaseHas('schedule_repositories', [
            'schedule_id' => $schedule->id,
            'key' => 'last_email_replied',
            'value' => 'msg-123',
        ]);
    }
}
