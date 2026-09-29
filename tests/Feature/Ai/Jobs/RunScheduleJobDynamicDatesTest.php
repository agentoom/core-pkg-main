<?php

namespace Agentoom\Core\Tests\Feature\Ai\Jobs;

use Agentoom\Core\Jobs\RunScheduleJob;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Services\TestActionableService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

class RunScheduleJobDynamicDatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_dynamic_dates_in_method_parameters()
    {
        Carbon::setTestNow('2026-04-08 12:00:00');

        $schedule = Schedule::create([
            'name' => 'Test Schedule',
            'minute' => '*',
            'hour' => '*',
            'day' => '*',
            'month' => '*',
            'day_of_week' => '*',
            'model' => TestActionableService::class,
            'method' => 'testMethod',
            'instructions' => json_encode(['-5 hours', 123]), // 123 is int for param2
            'active' => true,
        ]);

        // Mock the service to capture call
        $this->mock(TestActionableService::class, function ($mock) {
            $mock->shouldReceive('testMethod')
                ->once()
                ->withArgs(function ($arg1, $arg2) {
                    // Check if dates are resolved
                    // "-5 hours" -> 2026-04-08 07:00:00
                    $expected1 = Carbon::parse('2026-04-08 07:00:00')->toIso8601String();

                    return $arg1 === $expected1 && $arg2 === 123;
                })
                ->andReturn('success');
        });

        $job = new RunScheduleJob($schedule->id);
        $job->handle(app(PipelineRunnerService::class));
    }

    public function test_it_resolves_ranges_in_instructions_string()
    {
        Carbon::setTestNow('2026-04-08 12:00:00');

        // This applies to Agent or Pipeline targets
        $agent = Agent::factory()->create();

        $schedule = Schedule::create([
            'name' => 'Test Schedule String',
            'minute' => '*',
            'hour' => '*',
            'day' => '*',
            'month' => '*',
            'day_of_week' => '*',
            'model' => Agent::class,
            'model_id' => $agent->id,
            'instructions' => 'Fetch data from [5 hours before now, 1 hour before now]',
            'active' => true,
        ]);

        $pipeline = Pipeline::create([
            'name' => 'Test Pipeline',
            'is_active' => true,
        ]);

        $schedule->update([
            'model' => Pipeline::class,
            'model_id' => $pipeline->id,
        ]);

        $mockRunner = $this->mock(PipelineRunnerService::class, function ($mock) {
            $mock->shouldReceive('run')
                ->once()
                ->withArgs(function ($p, $input) {
                    // "5 hours before now" -> 2026-04-08 07:00:00
                    // "1 hour before now" -> 2026-04-08 11:00:00
                    $expected = 'Fetch data from [2026-04-08 07:00:00,2026-04-08 11:00:00]';

                    return $input === $expected;
                })
                ->andReturn(new PipelineRun(['id' => 1, 'output' => 'ok']));
        });

        $job = new RunScheduleJob($schedule->id);
        $job->handle($mockRunner);
    }

    public function test_it_resolves_ranges_in_json_parameters()
    {
        Carbon::setTestNow('2026-04-08 12:00:00');

        $schedule = Schedule::create([
            'name' => 'Test Range JSON',
            'minute' => '*',
            'hour' => '*',
            'day' => '*',
            'month' => '*',
            'day_of_week' => '*',
            'model' => TestActionableService::class,
            'method' => 'testMethod',
            'instructions' => json_encode(['[5 hours before now, 1 hour before now]', 123]),
            'active' => true,
        ]);

        $this->mock(TestActionableService::class, function ($mock) {
            $mock->shouldReceive('testMethod')
                ->once()
                ->withArgs(function ($arg1, $arg2) {
                    // PrestaShop format: [YYYY-MM-DD HH:MM:SS,YYYY-MM-DD HH:MM:SS]
                    $expected1 = '[2026-04-08 07:00:00,2026-04-08 11:00:00]';

                    return $arg1 === $expected1 && $arg2 === 123;
                })
                ->andReturn('success');
        });

        $job = new RunScheduleJob($schedule->id);
        $job->handle(app(PipelineRunnerService::class));
    }
}
