<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class PipelineRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_allows_starting_pipeline_run_with_empty_input(): void
    {
        $user = User::factory()->create();
        Role::where('name', 'superadmin')->exists() ?: Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        Configuration::setValue('app_ai_credits', 10000);

        $agent = Agent::factory()->create(['instructions' => 'Test instructions']);
        $pipeline = Pipeline::factory()->create(['name' => 'Test Pipeline']);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 1,
        ]);

        Livewire::test('agentoom-core::admin.pipeline-run-index', ['pipeline' => $pipeline])
            ->set('input', '')
            ->call('startRun')
            ->assertHasNoErrors()
            ->assertSet('input', '');

        $this->assertDatabaseHas('pipeline_runs', [
            'pipeline_id' => $pipeline->id,
            'input' => '',
        ]);
    }

    public function test_it_allows_stopping_a_run(): void
    {
        $user = User::factory()->create();
        Role::where('name', 'superadmin')->exists() ?: Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $pipeline = Pipeline::factory()->create();
        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'running',
            'input' => 'Test input',
        ]);

        Livewire::test('agentoom-core::admin.pipeline-run-index', ['pipeline' => $pipeline])
            ->call('stopRun', $run->id)
            ->assertHasNoErrors();

        $run->refresh();
        $this->assertEquals('stopped', $run->status);
        $this->assertEquals('Stopped by user', $run->output);
    }

    public function test_execute_run_detects_stopped_status_between_steps(): void
    {
        $agentModel = Agent::factory()->create();
        $pipeline = Pipeline::factory()->create();

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => StopActionStub::class,
            'method' => 'stoppingAction',
            'order' => 1,
        ]);

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agentModel->id,
            'order' => 2,
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'pending',
            'input' => 'Test input',
        ]);

        DynamicAgent::fake(['Step 2 should not run']);

        $service = app(PipelineRunnerService::class);
        $service->executeRun($run);

        $run->refresh();
        $this->assertEquals('stopped', $run->status, 'Run failed with error: '.$run->error);
        $this->assertNotNull($run->completed_at);
        $this->assertCount(1, $run->stepRuns); // Only step 1 should have run

        DynamicAgent::assertNeverPrompted();
    }
}

class StopActionStub
{
    public function stoppingAction()
    {
        $runId = app('current_pipeline_run_id');
        PipelineRun::where('id', $runId)->update(['status' => 'stopped']);

        return 'Stopping...';
    }
}
