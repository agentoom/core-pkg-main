<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PipelineRunnerLenientParsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_parses_nested_array_parameters_leniently(): void
    {
        $pipeline = Pipeline::create(['name' => 'Test', 'type' => 'standard']);

        $mockClass = new class
        {
            public function test_method(array $filters): array
            {
                return $filters;
            }
        };
        app()->instance('MockClass', $mockClass);

        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'type' => 'custom',
            'class' => 'MockClass',
            'method' => 'test_method',
            'order' => 0,
            'instructions_override' => json_encode([
                [
                    'name' => 'filters',
                    'type' => 'array',
                    'value' => "[('a', 'OR b'), 'AND c']",
                ],
            ]),
        ]);

        $runner = new PipelineRunnerService;
        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'pending']);

        $output = $runner->runStep($run, $step, null, null);

        $decoded = json_decode($output, true);
        $this->assertEquals([['a', 'OR b'], 'AND c'], $decoded);
    }

    public function test_it_still_parses_multi_line_strings_as_arrays(): void
    {
        $pipeline = Pipeline::create(['name' => 'Test', 'type' => 'standard']);

        $mockClass = new class
        {
            public function test_method(array $filters): array
            {
                return $filters;
            }
        };
        app()->instance('MockClass', $mockClass);

        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'type' => 'custom',
            'class' => 'MockClass',
            'method' => 'test_method',
            'order' => 0,
            'instructions_override' => json_encode([
                [
                    'name' => 'filters',
                    'type' => 'array',
                    'value' => "line 1\nline 2",
                ],
            ]),
        ]);

        $runner = new PipelineRunnerService;
        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'pending']);

        $output = $runner->runStep($run, $step, null, null);

        $decoded = json_decode($output, true);
        $this->assertEquals(['line 1', 'line 2'], $decoded);
    }
}
