<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Jobs\RunScheduleJob;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Services\TestActionableService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class RunScheduleCustomMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_runs_custom_actionable_method(): void
    {
        Log::shouldReceive('info')->atLeast()->once();

        $schedule = Schedule::create([
            'name' => 'Test Schedule',
            'model' => TestActionableService::class,
            'method' => 'testMethod',
            'instructions' => json_encode(['param1' => 'hello', 'param2' => 42]),
            'active' => true,
        ]);

        $job = new RunScheduleJob($schedule->id);
        $job->handle(app(PipelineRunnerService::class));

        $this->assertDatabaseHas('schedule_logs', [
            'schedule_id' => $schedule->id,
            'status' => 'success',
            'log' => 'Executed with hello and 42',
        ]);

        $schedule->refresh();
        $this->assertNotNull($schedule->last_execution);
    }
}
