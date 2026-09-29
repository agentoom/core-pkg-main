<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Jobs\RunScheduleJob;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Services\TestActionableService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RunScheduleJobJsonStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_handles_json_structure_helper_format_in_instructions(): void
    {
        $instructions = json_encode([
            ['name' => 'param1', 'type' => 'string', 'value' => 'value1', 'description' => ''],
            ['name' => 'param2', 'type' => 'integer', 'value' => '123', 'description' => ''],
        ]);

        $schedule = Schedule::create([
            'name' => 'Test Schedule',
            'minute' => '*',
            'hour' => '*',
            'day' => '*',
            'month' => '*',
            'day_of_week' => '*',
            'model' => TestActionableService::class,
            'method' => 'testMethod',
            'instructions' => $instructions,
            'active' => true,
        ]);

        $this->mock(TestActionableService::class, function ($mock) {
            $mock->shouldReceive('testMethod')
                ->once()
                ->with('value1', '123')
                ->andReturn('success');
        });

        $job = new RunScheduleJob($schedule->id);
        $job->handle(app(PipelineRunnerService::class));
    }

    public function test_it_handles_old_format_in_instructions(): void
    {
        $instructions = json_encode(['param1' => 'value1', 'param2' => 123]);

        $schedule = Schedule::create([
            'name' => 'Test Schedule Old',
            'minute' => '*',
            'hour' => '*',
            'day' => '*',
            'month' => '*',
            'day_of_week' => '*',
            'model' => TestActionableService::class,
            'method' => 'testMethod',
            'instructions' => $instructions,
            'active' => true,
        ]);

        $this->mock(TestActionableService::class, function ($mock) {
            $mock->shouldReceive('testMethod')
                ->once()
                ->with('value1', 123)
                ->andReturn('success');
        });

        $job = new RunScheduleJob($schedule->id);
        $job->handle(app(PipelineRunnerService::class));
    }
}
