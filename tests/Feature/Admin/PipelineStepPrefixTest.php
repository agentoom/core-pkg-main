<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class PipelineStepPrefixTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        Role::where('name', 'superadmin')->exists() ?: Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        $this->agent = Agent::create([
            'mcp_server_id' => $server->id,
            'name' => 'Test Agent',
            'slug' => 'test-agent',
            'is_active' => true,
        ]);
    }

    public function test_it_strips_prefix_when_loading_agent_steps_in_livewire(): void
    {
        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);
        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $this->agent->id,
            'order' => 0,
            'instructions_override' => 'Actual instructions',
        ]);

        // The model hook should have added the prefix for agent steps
        $this->assertStringStartsWith('[STEP NUMBER 1 saved on ', $step->instructions_override);

        Livewire::test('agentoom-core::admin.pipeline-create-edit', ['pipeline' => $pipeline])
            ->assertSet('steps.0.instructions_override', 'Actual instructions');
    }

    public function test_it_does_not_pile_up_prefixes_on_multiple_saves(): void
    {
        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);

        $lw = Livewire::test('agentoom-core::admin.pipeline-create-edit', ['pipeline' => $pipeline])
            ->call('addStep')
            ->set('steps.0.name', 'Step 1')
            ->set('steps.0.agent_id', $this->agent->id)
            ->set('steps.0.instructions_override', 'My instructions')
            ->call('save');

        $step = PipelineStep::where('pipeline_id', $pipeline->id)->firstOrFail();
        $this->assertStringStartsWith('[STEP NUMBER 1 saved on ', $step->instructions_override);
        $this->assertStringContainsString('My instructions', $step->instructions_override);
        $this->assertEquals(1, substr_count($step->instructions_override, '[STEP NUMBER 1 saved on '));

        // Second save - the Livewire component will now have it stripped in mount (if it redirects)
        // In the test, we need to re-load the component or simulate what happens after redirect
        $lw = Livewire::test('agentoom-core::admin.pipeline-create-edit', ['pipeline' => $pipeline]);
        $lw->call('save');

        $step = PipelineStep::where('pipeline_id', $pipeline->id)->firstOrFail();
        $this->assertEquals(1, substr_count($step->instructions_override, '[STEP NUMBER 1 saved on '), 'Prefix piled up on second save!');

        // Third save
        $lw->call('save');
        $step = PipelineStep::where('pipeline_id', $pipeline->id)->firstOrFail();
        $this->assertEquals(1, substr_count($step->instructions_override, '[STEP NUMBER 1 saved on '), 'Prefix piled up on third save!');
    }

    public function test_it_robustly_strips_multiple_prefixes_if_they_exist(): void
    {
        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);
        // Manually create a step with piled up prefixes
        $piledInstructions = '[STEP NUMBER 1 saved on 2026-05-24 10:00:00] [STEP NUMBER 1 saved on 2026-05-24 11:00:00] Actual instructions';

        // We bypass the model saving hook for a moment to inject the bad data,
        // OR we just know that our new regex should handle it when we load it in Livewire.

        $step = new PipelineStep;
        $step->pipeline_id = $pipeline->id;
        $step->order = 0;
        // Setting it directly to DB to simulate legacy bad data
        \DB::table('pipeline_steps')->insert([
            'pipeline_id' => $pipeline->id,
            'order' => 0,
            'instructions_override' => $piledInstructions,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::test('agentoom-core::admin.pipeline-create-edit', ['pipeline' => $pipeline])
            ->assertSet('steps.0.instructions_override', 'Actual instructions');
    }
}
