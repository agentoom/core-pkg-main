<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Exceptions\ActionPausedException;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Models\EndpointLog;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Spatie\Permission\Models\Role;

class EndpointPauseResumeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Configuration::setValue('app_ai_credits', 100.0);

        // Seed roles
        if (! Role::where('name', 'admin')->exists()) {
            Role::create(['name' => 'admin', 'guard_name' => 'web']);
        }
        if (! Role::where('name', 'superadmin')->exists()) {
            Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        }
    }

    public function test_endpoint_with_agent_can_pause_and_resume(): void
    {
        // 1. Initial call - Pauses
        Ai::fakeAgent(DynamicAgent::class, function () {
            throw new ActionPausedException('Authorization required');
        });

        $agent = Agent::factory()->create(['is_active' => true]);
        $endpoint = Endpoint::create([
            'agent_id' => $agent->id,
            'name' => 'Pause Test',
            'slug' => 'pause-test',
            'is_active' => true,
        ]);

        $response = $this->postJson('/e/pause-test', ['foo' => 'bar']);

        if ($response->status() !== 202) {
            dump($response->getContent());
        }

        $response->assertStatus(202);
        $response->assertJson(['status' => 'paused']);

        $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();
        $this->assertEquals('paused', $log->status);

        // 2. Resume call - Succeeds
        Ai::fakeAgent(DynamicAgent::class, function ($prompt) {
            $this->assertStringContainsString('Security authorization successful', $prompt);

            return 'task successful';
        });

        $response = $this->postJson("/e/resume/{$log->id}");
        $response->assertStatus(200);
        $response->assertJson(['status' => 'success', 'message' => 'Endpoint agent resumed']);

        $log->refresh();
        $this->assertEquals('success', $log->status);
    }

    public function test_endpoint_with_pipeline_can_pause_and_resume(): void
    {
        // 1. Initial call - Pauses
        Ai::fakeAgent(DynamicAgent::class, function () {
            throw new ActionPausedException('Authorization required');
        });

        $agent = Agent::factory()->create(['is_active' => true]);
        $pipeline = Pipeline::create([
            'name' => 'Pause Pipeline',
            'is_active' => true,
            'type' => Pipeline::TYPE_ENDPOINT,
        ]);

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 0,
            'name' => 'Step 1',
        ]);

        $endpoint = Endpoint::create([
            'pipeline_id' => $pipeline->id,
            'name' => 'Pipeline Pause Test',
            'slug' => 'pipeline-pause',
            'is_active' => true,
        ]);

        $response = $this->postJson('/e/pipeline-pause', ['foo' => 'bar']);
        $response->assertStatus(202);

        $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();
        $this->assertEquals('paused', $log->status);

        $run = PipelineRun::where('pipeline_id', $pipeline->id)->first();
        $this->assertEquals('paused', $run->status);

        // 2. Resume call - Succeeds
        Ai::fakeAgent(DynamicAgent::class, function ($prompt) {
            // If this was a resume, it should have the resume message
            $this->assertStringContainsString('Security authorization successful', $prompt);

            return 'task successful';
        });

        $response = $this->postJson("/e/resume/{$log->id}");
        $response->assertStatus(200);

        $log->refresh();
        $this->assertEquals('success', $log->status);

        $run->refresh();
        $this->assertEquals('completed', $run->status);

        // Verify we didn't create multiple runs
        $this->assertEquals(1, PipelineRun::count());
    }
}
