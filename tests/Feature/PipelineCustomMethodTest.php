<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Services\TestActionableService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PipelineCustomMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_executes_custom_method_in_pipeline(): void
    {
        $pipeline = Pipeline::create(['name' => 'Custom Method Pipeline']);
        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => TestActionableService::class,
            'method' => 'testMethod',
            'order' => 0,
            'instructions_override' => json_encode(['param1' => 'hello', 'param2' => 123]),
        ]);

        $service = new PipelineRunnerService;
        $run = $service->run($pipeline, 'initial input');

        $this->assertEquals('completed', $run->status);
        $this->assertEquals('Executed with hello and 123', $run->output);
        $this->assertCount(1, $run->stepRuns);
        $this->assertEquals('completed', $run->stepRuns->first()->status);
        $this->assertEquals('Executed with hello and 123', $run->stepRuns->first()->output);
    }

    public function test_it_executes_custom_method_in_pipeline_with_json_structure_helper_format(): void
    {
        $pipeline = Pipeline::create(['name' => 'Custom Method Pipeline New Format']);
        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => TestActionableService::class,
            'method' => 'testMethod',
            'order' => 0,
            'instructions_override' => json_encode([
                ['name' => 'param1', 'type' => 'string', 'value' => 'world', 'description' => ''],
                ['name' => 'param2', 'type' => 'integer', 'value' => 456, 'description' => ''],
            ]),
        ]);

        $service = new PipelineRunnerService;
        $run = $service->run($pipeline, 'initial input');

        $this->assertEquals('completed', $run->status);
        $this->assertEquals('Executed with world and 456', $run->output);
    }
}
