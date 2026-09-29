<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\PipelineExecutionTool;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineExecutionService;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Services\TestActionableService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class RetrieveExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_retrieve_execution_from_service(): void
    {
        $pipeline = Pipeline::create(['name' => 'Retrieve Execution Pipeline']);
        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => TestActionableService::class,
            'method' => 'retrieveExecution',
            'order' => 0,
        ]);

        $service = new PipelineRunnerService;
        $run = $service->run($pipeline, 'initial input');

        $this->assertEquals('completed', $run->status);
        $this->assertEquals("Retrieved run #{$run->id}", $run->output);
    }

    public function test_it_can_retrieve_execution_from_container(): void
    {
        $pipeline = Pipeline::create(['name' => 'Retrieve Execution Pipeline Container']);
        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => TestActionableService::class,
            'method' => 'retrieveExecutionFromContainer',
            'order' => 0,
        ]);

        $service = new PipelineRunnerService;
        $run = $service->run($pipeline, 'initial input');

        $this->assertEquals('completed', $run->status);
        $this->assertEquals("Retrieved run #{$run->id} from container", $run->output);
    }

    public function test_tool_can_retrieve_execution_details(): void
    {
        $pipeline = Pipeline::create(['name' => 'Tool Retrieve Execution']);
        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'running',
            'input' => 'tool-input',
        ]);

        app(PipelineExecutionService::class)->setCurrentRun($run);

        $tool = new PipelineExecutionTool;
        $request = new Request(['action' => 'get_details']);
        $result = $tool->handle($request);

        $details = json_decode((string) $result, true);
        $this->assertEquals($run->id, $details['id']);
        $this->assertEquals('tool-input', $details['input']);
        $this->assertEquals('Tool Retrieve Execution', $details['pipeline_name']);
    }
}
