<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Jobs\RunScheduleJob;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ScheduleLenientParsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_parses_nested_array_parameters_leniently_in_schedules(): void
    {
        // Mock a class with a method that accepts an array
        $mockClass = new class
        {
            public function test_method(array $filters): array
            {
                return $filters;
            }
        };
        app()->instance('MockClass', $mockClass);

        $schedule = Schedule::create([
            'name' => 'Test',
            'active' => true,
            'model' => 'MockClass',
            'method' => 'testMethod',
            'instructions' => json_encode([
                [
                    'name' => 'filters',
                    'type' => 'array',
                    'value' => "[('a', 'OR b'), 'AND c']",
                ],
            ]),
        ]);

        $job = new RunScheduleJob($schedule->id);
        $runner = app(PipelineRunnerService::class);

        // We need to bypass the actual execution return if possible or just check the log
        $job->handle($runner);

        $log = $schedule->logs()->first();
        $this->assertEquals('success', $log->status);
        $decoded = json_decode($log->log, true);
        $this->assertEquals([['a', 'OR b'], 'AND c'], $decoded);
    }
}
