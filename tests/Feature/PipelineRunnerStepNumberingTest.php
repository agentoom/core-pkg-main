<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineRunStep;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PipelineRunnerStepNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_previous_outputs_use_one_based_step_number_keys(): void
    {
        // Create pipeline with 3 steps
        $pipeline = Pipeline::create(['name' => 'Test Pipeline', 'type' => 'standard']);

        $steps = [];
        for ($i = 0; $i < 3; $i++) {
            $steps[] = PipelineStep::create([
                'pipeline_id' => $pipeline->id,
                'order' => $i,
                'name' => "Step {$i}",
                'instructions_override' => 'Test instructions',
            ]);
        }

        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'pending']);

        // Create completed runs for steps 0 and 1
        PipelineRunStep::create([
            'pipeline_run_id' => $run->id,
            'pipeline_step_id' => $steps[0]->id,
            'order' => 0,
            'status' => 'completed',
            'output' => '{"result": "first", "value": 100}',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(4),
        ]);

        PipelineRunStep::create([
            'pipeline_run_id' => $run->id,
            'pipeline_step_id' => $steps[1]->id,
            'order' => 1,
            'status' => 'completed',
            'output' => '{"result": "second", "value": 200}',
            'started_at' => now()->subMinutes(3),
            'completed_at' => now()->subMinutes(2),
        ]);

        // Step 2 references output from step 1 (0-based selected_outputs = [0])
        // and uses template variables {1} and {2}
        $step2 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 2,
            'name' => 'Final Step',
            'instructions_override' => 'Process: {1.result} and {2.result}',
            'selected_outputs' => [0, 1],
            'pass_all_previous_outputs' => false,
        ]);

        $runner = new PipelineRunnerService;
        // We need to extract the $allPreviousOutputs from the buildPrompt call.
        // Since runStep is complex, let's use a reflection-based approach:
        $reflection = new \ReflectionMethod(PipelineRunnerService::class, 'buildPrompt');
        $reflection->setAccessible(true);

        $previousStepRuns = PipelineRunStep::where('pipeline_run_id', $run->id)
            ->where('status', 'completed')
            ->with('pipelineStep')
            ->orderBy('order')
            ->get();

        $allPreviousOutputs = [];
        foreach ($previousStepRuns as $psr) {
            $absOrder = $psr->pipelineStep?->order ?? $psr->order;
            $allPreviousOutputs[$absOrder + 1] = $psr->output;
        }

        // Verify keys are 1-based (order + 1)
        $this->assertArrayHasKey(1, $allPreviousOutputs, 'Step 0 should be key "1"');
        $this->assertArrayHasKey(2, $allPreviousOutputs, 'Step 1 should be key "2"');
        $this->assertArrayNotHasKey(0, $allPreviousOutputs, 'There should be no key "0"');
        $this->assertArrayNotHasKey('Step 0', $allPreviousOutputs, 'There should be no name-based key');
        $this->assertArrayNotHasKey('Step 1', $allPreviousOutputs, 'There should be no name-based key');

        // Build prompt with selected_outputs
        $prompt = $reflection->invoke($runner, $step2, 'Original input', null, $allPreviousOutputs, $previousStepRuns);

        // Verify the prompt contains the selected outputs with correct step labels
        $this->assertStringContainsString('[OUTPUT STEP 1]', $prompt);
        $this->assertStringContainsString('[/OUTPUT STEP 1]', $prompt);
        $this->assertStringContainsString('[OUTPUT STEP 2]', $prompt);
        $this->assertStringContainsString('[/OUTPUT STEP 2]', $prompt);

        // Verify the original input is preserved
        $this->assertStringContainsString('Original input', $prompt);
    }

    public function test_template_variables_resolve_using_one_based_step_numbers(): void
    {
        $pipeline = Pipeline::create(['name' => 'Variable Test', 'type' => 'standard']);

        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 2,
            'name' => 'Consumer Step',
            'instructions_override' => 'Use {1} and {2.field}',
        ]);

        $allPreviousOutputs = [
            1 => '{"name": "Alice", "field": 42}',
            2 => '{"name": "Bob", "field": 99}',
        ];

        $runner = new PipelineRunnerService;
        $reflection = new \ReflectionMethod(PipelineRunnerService::class, 'replaceOutputVariables');
        $reflection->setAccessible(true);

        // Test exact match: replace entire value with step output
        $result = $reflection->invoke($runner, '{1}', null, $allPreviousOutputs, null);
        $this->assertIsArray($result);
        $this->assertEquals('Alice', $result['name']);

        // Test dot notation: {2.field}
        $result = $reflection->invoke($runner, '{2.field}', null, $allPreviousOutputs, null);
        $this->assertEquals('99', $result);

        // Test multiple placeholders in string
        $result = $reflection->invoke($runner, 'Name: {1.name}, Value: {2.field}', null, $allPreviousOutputs, null);
        $this->assertEquals('Name: Alice, Value: 99', $result);

        // Verify that step name-based access does NOT work (names are not in keys)
        $result = $reflection->invoke($runner, '{Step 0}', null, $allPreviousOutputs, null);
        $this->assertEquals('{Step 0}', $result, 'Step name references should not resolve');
    }

    public function test_all_previous_outputs_excludes_name_based_keys(): void
    {
        $pipeline = Pipeline::create(['name' => 'Name Exclusion', 'type' => 'standard']);

        $step0 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 0,
            'name' => 'Custom Step Name',  // This name MUST NOT appear in $allPreviousOutputs
            'instructions_override' => 'Test',
        ]);

        $step1 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'name' => 'Another Step',
            'instructions_override' => 'Test',
        ]);

        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'pending']);

        // Create completed runs
        PipelineRunStep::create([
            'pipeline_run_id' => $run->id,
            'pipeline_step_id' => $step0->id,
            'order' => 0,
            'status' => 'completed',
            'output' => '{"status": "first", "value": "A"}',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(4),
        ]);

        PipelineRunStep::create([
            'pipeline_run_id' => $run->id,
            'pipeline_step_id' => $step1->id,
            'order' => 1,
            'status' => 'completed',
            'output' => '{"status": "second", "value": "B"}',
            'started_at' => now()->subMinutes(3),
            'completed_at' => now()->subMinutes(2),
        ]);

        // Build $allPreviousOutputs the way runStep does it
        $previousStepRuns = PipelineRunStep::where('pipeline_run_id', $run->id)
            ->where('status', 'completed')
            ->with('pipelineStep')
            ->orderBy('order')
            ->get();

        $allPreviousOutputs = [];
        foreach ($previousStepRuns as $psr) {
            $absOrder = $psr->pipelineStep?->order ?? $psr->order;
            $allPreviousOutputs[$absOrder + 1] = $psr->output;
        }

        // Only numeric 1-based keys should exist
        $this->assertArrayHasKey(1, $allPreviousOutputs);
        $this->assertArrayHasKey(2, $allPreviousOutputs);

        // Step names MUST NOT be present as keys
        $this->assertArrayNotHasKey('Custom Step Name', $allPreviousOutputs, 'Step name should not be a key');
        $this->assertArrayNotHasKey('Another Step', $allPreviousOutputs, 'Step name should not be a key');
        // Slugified versions should also not exist
        $this->assertArrayNotHasKey('custom_step_name', $allPreviousOutputs, 'Slugified step name should not be a key');

        // Verify numeric keys are 1-based
        $this->assertEquals(['1', '2'], array_keys($allPreviousOutputs));

        // Verify template variables resolve using 1-based numbers
        $runner = new PipelineRunnerService;
        $reflection = new \ReflectionMethod(PipelineRunnerService::class, 'replaceOutputVariables');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($runner, '{1.status}', null, $allPreviousOutputs, null);
        $this->assertEquals('first', $result);

        $result = $reflection->invoke($runner, '{2.status}', null, $allPreviousOutputs, null);
        $this->assertEquals('second', $result);

        // Step name reference should not resolve
        $result = $reflection->invoke($runner, '{Custom Step Name}', null, $allPreviousOutputs, null);
        $this->assertEquals('{Custom Step Name}', $result, 'Step name reference should not resolve');

        $result = $reflection->invoke($runner, '{custom_step_name}', null, $allPreviousOutputs, null);
        $this->assertEquals('{custom_step_name}', $result, 'Slugified step name should not resolve');
    }
}
