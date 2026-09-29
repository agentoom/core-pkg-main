<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Jobs\RunPipelineJob;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class PipelineBackgroundRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_run_pipeline_job_on_start(): void
    {
        Bus::fake();

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
            ->set('input', 'test input')
            ->call('startRun')
            ->assertHasNoErrors();

        Bus::assertDispatched(RunPipelineJob::class, function ($job) use ($pipeline) {
            $run = PipelineRun::find($job->pipelineRunId);

            return $run->pipeline_id === $pipeline->id && $run->input === 'test input';
        });
    }

    public function test_pipeline_run_show_has_polling(): void
    {
        $user = User::factory()->create();
        Role::where('name', 'superadmin')->exists() ?: Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $pipeline = Pipeline::factory()->create(['name' => 'Test Pipeline']);
        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'user_id' => $user->id,
            'status' => 'running',
            'input' => 'test',
            'started_at' => now(),
        ]);

        Livewire::test('agentoom-core::admin.pipeline-run-show', ['pipeline' => $pipeline, 'run' => $run])
            ->assertSeeHtml('wire:poll.5s="refresh"');
    }
}
