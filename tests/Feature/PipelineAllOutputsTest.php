<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineRunStep;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PipelineAllOutputsTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_prompt_includes_all_previous_outputs_when_enabled(): void
    {
        $service = new PipelineRunnerService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildPrompt');
        $method->setAccessible(true);

        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);
        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'pass_all_previous_outputs' => true,
        ]);

        $allPreviousOutputs = [
            'Step 1' => 'Output 1',
            'Step 2' => 'Output 2',
        ];

        $prompt = $method->invoke($service, $step, 'My Task', 'Last Output', $allPreviousOutputs);

        $this->assertStringContainsString('## All Previous Steps Outputs (JSON)', $prompt);
        $this->assertStringContainsString('"Step 1": "Output 1"', $prompt);
        $this->assertStringContainsString('"Step 2": "Output 2"', $prompt);
        $this->assertStringContainsString('## Your Task', $prompt);
        $this->assertStringContainsString('My Task', $prompt);

        // It should NOT contain "## Previous Step Output" if all outputs are passed
        $this->assertStringNotContainsString('## Previous Step Output', $prompt);
    }

    public function test_build_prompt_uses_single_previous_output_when_all_outputs_disabled(): void
    {
        $service = new PipelineRunnerService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildPrompt');
        $method->setAccessible(true);

        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);
        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'pass_output_as_input' => true,
            'pass_all_previous_outputs' => false,
        ]);

        $prompt = $method->invoke($service, $step, 'My Task', 'Last Output', null);

        $this->assertStringNotContainsString('## All Previous Steps Outputs (JSON)', $prompt);
        $this->assertStringContainsString('## Previous Step Output', $prompt);
        $this->assertStringContainsString('Last Output', $prompt);
    }

    public function test_run_step_fetches_all_previous_outputs(): void
    {
        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);
        $step1 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 0,
            'name' => 'First Step',
        ]);

        // Create an agent for the second step
        $agent = Agent::create([
            'name' => 'Test Agent',
            'type' => 'openai',
            'config' => ['model' => 'gpt-4o'],
        ]);

        $step2 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'agent_id' => $agent->id,
            'name' => 'Second Step',
            'pass_all_previous_outputs' => true,
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'running',
        ]);

        PipelineRunStep::create([
            'pipeline_run_id' => $run->id,
            'pipeline_step_id' => $step1->id,
            'order' => 0,
            'status' => 'completed',
            'output' => 'Output from step 1',
        ]);

        $service = new PipelineRunnerService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('runStep');
        $method->setAccessible(true);

        try {
            $method->invoke($service, $run, $step2, 'Initial Input', 'Previous Output');
        } catch (\Throwable $e) {
            // It will likely fail because of missing AI config or provider,
            // but the stepRun should have been updated with the prompt by then.
        }

        $stepRun = PipelineRunStep::where('pipeline_step_id', $step2->id)->first();
        $this->assertNotNull($stepRun);
        $this->assertStringContainsString('## All Previous Steps Outputs (JSON)', $stepRun->input);
        $this->assertStringContainsString('"First Step": "Output from step 1"', $stepRun->input);
    }
}
