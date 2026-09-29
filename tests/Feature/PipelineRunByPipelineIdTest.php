<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PipelineRunByPipelineIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_by_pipeline_id_works_with_string_data(): void
    {
        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'is_active' => true,
        ]);

        $service = new PipelineRunnerService;
        $run = $service->runByPipelineId($pipeline->id, 'some input');

        $this->assertEquals($pipeline->id, $run->pipeline_id);
        $this->assertEquals('some input', $run->input);
        $this->assertEquals('completed', $run->status);
    }

    public function test_run_by_pipeline_id_works_with_array_data(): void
    {
        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'is_active' => true,
        ]);

        $service = new PipelineRunnerService;
        $run = $service->runByPipelineId($pipeline->id, ['key' => 'value']);

        $this->assertEquals($pipeline->id, $run->pipeline_id);
        $this->assertEquals(json_encode(['key' => 'value']), $run->input);
        $this->assertEquals('completed', $run->status);
    }

    public function test_run_by_pipeline_id_throws_exception_if_pipeline_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $service = new PipelineRunnerService;
        $service->runByPipelineId(999);
    }
}
