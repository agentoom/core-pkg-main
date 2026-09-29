<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\ChatWidget\Models\AgentWidget;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChatWidgetBudgetLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_js_not_loaded_when_agent_limit_reached(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $agent = Agent::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'max_usd_limit' => 10.00,
            'limit_period' => 'monthly',
        ]);

        // Mock usage log to reach limit
        $agent->usageLogs()->create([
            'cost' => 11.00,
            'model' => 'test',
            'action_type' => 'chat',
            'total_tokens' => 100,
        ]);

        $widget = AgentWidget::factory()->create([
            'agent_id' => $agent->id,
            'is_active' => true,
        ]);

        $response = $this->get(route('js.agentoom-vars', ['id' => $widget->slug]));

        $response->assertStatus(200);
        $response->assertSee('// Chat widget budget limit reached');
        $response->assertDontSee('window.AgentoomOs.id');
    }

    public function test_widget_js_not_loaded_when_project_limit_reached(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Instead of mocking total_spent attribute (which is computed from usage logs),
        // we create usage logs that exceed the budget.
        $project->update(['budget_limit' => 50.00]);

        $agent = Agent::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
        ]);

        $project->aiUsageLogs()->create([
            'cost' => 51.00,
            'model' => 'test',
            'agent_id' => $agent->id,
            'action_type' => 'chat',
            'total_tokens' => 100,
        ]);

        $widget = AgentWidget::factory()->create([
            'agent_id' => $agent->id,
            'is_active' => true,
        ]);

        $response = $this->get(route('js.agentoom-vars', ['id' => $widget->slug]));

        $response->assertStatus(200);
        $response->assertSee('// Chat widget budget limit reached');
    }
}
