<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PipelineConditionalRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_evaluates_json_conditional_rules(): void
    {
        $pipeline = Pipeline::create(['name' => 'Conditional Pipeline']);

        // Step 1: Returns JSON
        $step1 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'name' => 'Step 1',
            'class' => TestActions::class,
            'method' => 'returnJson',
            'instructions_override' => json_encode([['name' => 'data', 'value' => '{"status": "success", "data": "some data"}', 'type' => 'string']]),
        ]);

        // Step 2: Conditional step (branching)
        // If status is 'success', go to step 3, else go to step 4
        $step2 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 2,
            'name' => 'Condition',
            'condition_if' => json_encode([
                'field' => 'status',
                'operator' => 'equals',
                'value' => 'success',
            ]),
            'next_step_on_true' => 3,
            'next_step_on_false' => 4,
        ]);

        $step3 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 3,
            'name' => 'True Step',
            'class' => TestActions::class,
            'method' => 'returnString',
            'instructions_override' => json_encode([['name' => 'value', 'value' => 'Condition was true', 'type' => 'string']]),
        ]);

        $step4 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 4,
            'name' => 'False Step',
            'class' => TestActions::class,
            'method' => 'returnString',
            'instructions_override' => json_encode([['name' => 'value', 'value' => 'Condition was false', 'type' => 'string']]),
        ]);

        $runner = new PipelineRunnerService;

        // Case 1: Success
        $run1 = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'running']);

        $runner->executeRun($run1, 'input');

        $run1->refresh();
        $this->assertEquals('completed', $run1->status);
        // Should have executed Step 3
        $step3Run = $run1->stepRuns()->where('pipeline_step_id', $step3->id)->first();
        $this->assertNotNull($step3Run, 'Step 3 should have been executed');
        $this->assertStringContainsString('Condition was true', $step3Run->output);

        // In Case 1, it will also execute Step 4 because it follows Step 3 in order
        $this->assertTrue($run1->stepRuns()->where('pipeline_step_id', $step4->id)->exists());

        // Case 2: Failure
        $run2 = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'running']);
        $step1->update(['instructions_override' => json_encode([['name' => 'data', 'value' => '{"status": "error", "data": "some error"}', 'type' => 'string']])]);

        $runner->executeRun($run2, 'input');

        $run2->refresh();
        $this->assertEquals('completed', $run2->status);
        // Should have executed Step 4
        $this->assertFalse($run2->stepRuns()->where('pipeline_step_id', $step3->id)->exists(), 'Step 3 should NOT have been executed');
        $this->assertTrue($run2->stepRuns()->where('pipeline_step_id', $step4->id)->exists(), 'Step 4 should have been executed');
        $this->assertStringContainsString('Condition was false', $run2->output);
    }

    public function test_it_supports_greater_than_operator(): void
    {
        $pipeline = Pipeline::create(['name' => 'GT Pipeline']);

        $step0 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'class' => TestActions::class,
            'method' => 'returnJson',
            'instructions_override' => json_encode([['name' => 'data', 'value' => '{"value": 15}', 'type' => 'string']]),
        ]);

        $step1 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 2,
            'condition_if' => json_encode([
                'field' => 'value',
                'operator' => '>',
                'value' => 10,
            ]),
            'next_step_on_true' => 3,
            'next_step_on_false' => 4,
        ]);

        $step2 = PipelineStep::create(['pipeline_id' => $pipeline->id, 'order' => 3, 'name' => 'True']);
        $step3 = PipelineStep::create(['pipeline_id' => $pipeline->id, 'order' => 4, 'name' => 'False']);

        $runner = new PipelineRunnerService;

        // 15 > 10 => true
        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'running']);
        $runner->executeRun($run, 'input');
        $this->assertTrue($run->stepRuns()->where('pipeline_step_id', $step2->id)->exists());

        // 5 > 10 => false
        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'running']);
        $step0->update(['instructions_override' => json_encode([['name' => 'data', 'value' => '{"value": 5}', 'type' => 'string']])]);
        $runner->executeRun($run, 'input');
        $this->assertTrue($run->stepRuns()->where('pipeline_step_id', $step3->id)->exists());
    }

    public function test_it_supports_contains_operator(): void
    {
        $pipeline = Pipeline::create(['name' => 'Contains Pipeline']);

        $step0 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'class' => TestActions::class,
            'method' => 'returnJson',
            'instructions_override' => json_encode([['name' => 'data', 'value' => '{"text": "hello world"}', 'type' => 'string']]),
        ]);

        $step1 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 2,
            'condition_if' => json_encode([
                'field' => 'text',
                'operator' => 'contains',
                'value' => 'hello',
            ]),
            'next_step_on_true' => 3,
            'next_step_on_false' => 4,
        ]);

        $step2 = PipelineStep::create(['pipeline_id' => $pipeline->id, 'order' => 3, 'name' => 'True']);
        $step3 = PipelineStep::create(['pipeline_id' => $pipeline->id, 'order' => 4, 'name' => 'False']);

        $runner = new PipelineRunnerService;

        // "hello world" contains "hello" => true
        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'running']);
        $runner->executeRun($run, 'input');
        $this->assertTrue($run->stepRuns()->where('pipeline_step_id', $step2->id)->exists());

        // "bye world" contains "hello" => false
        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'running']);
        $step0->update(['instructions_override' => json_encode([['name' => 'data', 'value' => '{"text": "bye world"}', 'type' => 'string']])]);
        $runner->executeRun($run, 'input');
        $this->assertTrue($run->stepRuns()->where('pipeline_step_id', $step3->id)->exists());
    }

    public function test_it_supports_does_not_contain_operator(): void
    {
        $pipeline = Pipeline::create(['name' => 'Not Contains Pipeline']);

        $step0 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'class' => TestActions::class,
            'method' => 'returnJson',
            'instructions_override' => json_encode([['name' => 'data', 'value' => '{"text": "hello world"}', 'type' => 'string']]),
        ]);

        $step1 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 2,
            'condition_if' => json_encode([
                'field' => 'text',
                'operator' => 'does_not_contain',
                'value' => 'bye',
            ]),
            'next_step_on_true' => 3,
            'next_step_on_false' => 4,
        ]);

        $step2 = PipelineStep::create(['pipeline_id' => $pipeline->id, 'order' => 3, 'name' => 'True']);
        $step3 = PipelineStep::create(['pipeline_id' => $pipeline->id, 'order' => 4, 'name' => 'False']);

        $runner = new PipelineRunnerService;

        // "hello world" does not contain "bye" => true
        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'running']);
        $runner->executeRun($run, 'input');
        $this->assertTrue($run->stepRuns()->where('pipeline_step_id', $step2->id)->exists());

        // "bye world" does not contain "bye" => false
        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'running']);
        $step0->update(['instructions_override' => json_encode([['name' => 'data', 'value' => '{"text": "bye world"}', 'type' => 'string']])]);
        $runner->executeRun($run, 'input');
        $this->assertTrue($run->stepRuns()->where('pipeline_step_id', $step3->id)->exists());
    }

    public function test_it_supports_fallback_string_contains(): void
    {
        $pipeline = Pipeline::create(['name' => 'Fallback Pipeline']);

        $step0 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'class' => TestActions::class,
            'method' => 'returnString',
            'instructions_override' => json_encode([['name' => 'value', 'value' => 'The quick brown fox', 'type' => 'string']]),
        ]);

        $step1 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 2,
            'condition_if' => 'quick brown', // Simple string
            'next_step_on_true' => 3,
            'next_step_on_false' => 4,
        ]);

        $step2 = PipelineStep::create(['pipeline_id' => $pipeline->id, 'order' => 3, 'name' => 'True']);
        $step3 = PipelineStep::create(['pipeline_id' => $pipeline->id, 'order' => 4, 'name' => 'False']);

        $runner = new PipelineRunnerService;

        // "The quick brown fox" contains "quick brown" => true
        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'running']);
        $runner->executeRun($run, 'input');
        $this->assertTrue($run->stepRuns()->where('pipeline_step_id', $step2->id)->exists());

        // "The slow blue dog" contains "quick brown" => false
        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'running']);
        $step0->update(['instructions_override' => json_encode([['name' => 'value', 'value' => 'The slow blue dog', 'type' => 'string']])]);
        $runner->executeRun($run, 'input');
        $this->assertTrue($run->stepRuns()->where('pipeline_step_id', $step3->id)->exists());
    }

    public function test_it_supports_whole_output_check(): void
    {
        $pipeline = Pipeline::create(['name' => 'Whole Output Pipeline']);

        $step0 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'class' => TestActions::class,
            'method' => 'returnString',
            'instructions_override' => json_encode([['name' => 'value', 'value' => 'success', 'type' => 'string']]),
        ]);

        $step1 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 2,
            'condition_if' => json_encode([
                'field' => '', // Empty field means whole output
                'operator' => 'equals',
                'value' => 'success',
            ]),
            'next_step_on_true' => 3,
            'next_step_on_false' => 4,
        ]);

        $step2 = PipelineStep::create(['pipeline_id' => $pipeline->id, 'order' => 3, 'name' => 'True']);
        $step3 = PipelineStep::create(['pipeline_id' => $pipeline->id, 'order' => 4, 'name' => 'False']);

        $runner = new PipelineRunnerService;

        // "success" equals "success" => true
        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'running']);
        $runner->executeRun($run, 'input');
        $this->assertTrue($run->stepRuns()->where('pipeline_step_id', $step2->id)->exists());

        // "error" equals "success" => false
        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'running']);
        $step0->update(['instructions_override' => json_encode([['name' => 'value', 'value' => 'error', 'type' => 'string']])]);
        $runner->executeRun($run, 'input');
        $this->assertTrue($run->stepRuns()->where('pipeline_step_id', $step3->id)->exists());
    }

    public function test_it_handles_array_to_string_conversion_in_conditions(): void
    {
        $pipeline = Pipeline::create(['name' => 'Array Condition Pipeline']);

        $step0 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'class' => TestActions::class,
            'method' => 'returnJson',
            'instructions_override' => json_encode([['name' => 'data', 'value' => '{"tags": ["php", "laravel"]}', 'type' => 'string']]),
        ]);

        $step1 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 2,
            'condition_if' => json_encode([
                'field' => 'tags',
                'operator' => 'contains',
                'value' => 'php',
            ]),
            'next_step_on_true' => 3,
            'next_step_on_false' => 4,
        ]);

        $step2 = PipelineStep::create(['pipeline_id' => $pipeline->id, 'order' => 3, 'name' => 'True']);
        $step3 = PipelineStep::create(['pipeline_id' => $pipeline->id, 'order' => 4, 'name' => 'False']);

        $runner = new PipelineRunnerService;

        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'running']);
        $runner->executeRun($run, 'input');

        // ["php", "laravel"] json_encoded is ["php","laravel"]. Contains "php" => true.
        $this->assertTrue($run->stepRuns()->where('pipeline_step_id', $step2->id)->exists());
    }

    public function test_it_correctly_evaluates_field_in_array_of_objects(): void
    {
        $pipeline = Pipeline::create(['name' => 'Repro Pipeline']);

        // Step 1: Returns an array of objects (typical getResources output)
        $step1 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'class' => TestActions::class,
            'method' => 'returnJson',
            'instructions_override' => json_encode([['name' => 'data', 'value' => '[{"id": 5, "name": "Resource 1"}]', 'type' => 'string']]),
        ]);

        // Step 2: Condition check on "id"
        $step2 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 2,
            'condition_if' => json_encode([
                'field' => 'id',
                'operator' => 'is_greater',
                'value' => '0',
            ]),
            'next_step_on_true' => 3,
            'next_step_on_false' => 4,
        ]);

        $step3 = PipelineStep::create(['pipeline_id' => $pipeline->id, 'order' => 3, 'name' => 'True']);
        $step4 = PipelineStep::create(['pipeline_id' => $pipeline->id, 'order' => 4, 'name' => 'False']);

        $runner = new PipelineRunnerService;
        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'running']);

        $runner->executeRun($run, 'input');

        // WITH FIX: It should go to True step because it now looks into the first element of the array.
        $this->assertTrue($run->stepRuns()->where('pipeline_step_id', $step3->id)->exists(), 'It should have gone to True step');

        // Note: In this system, sequential execution continues after a jump if not specified otherwise.
        $this->assertTrue($run->stepRuns()->where('pipeline_step_id', $step4->id)->exists());
    }

    public function test_it_should_support_exists_operator(): void
    {
        $pipeline = Pipeline::create(['name' => 'Exists Pipeline']);

        $step1 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'class' => TestActions::class,
            'method' => 'returnJson',
            'instructions_override' => json_encode([['name' => 'data', 'value' => '{"id": 5}', 'type' => 'string']]),
        ]);

        $step2 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 2,
            'condition_if' => json_encode([
                'field' => 'id',
                'operator' => 'exists',
            ]),
            'next_step_on_true' => 3,
            'next_step_on_false' => 4,
        ]);

        $step3 = PipelineStep::create(['pipeline_id' => $pipeline->id, 'order' => 3, 'name' => 'True']);
        $step4 = PipelineStep::create(['pipeline_id' => $pipeline->id, 'order' => 4, 'name' => 'False']);

        $runner = new PipelineRunnerService;

        // Case 1: Exists
        $run = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'running']);
        $runner->executeRun($run, 'input');
        $this->assertTrue($run->stepRuns()->where('pipeline_step_id', $step3->id)->exists(), 'It should have gone to True step');

        // Case 2: Does not exist
        $run2 = PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'running']);
        $step1->update(['instructions_override' => json_encode([['name' => 'data', 'value' => '{"other": 5}', 'type' => 'string']])]);
        $runner->executeRun($run2, 'input');
        $this->assertTrue($run2->stepRuns()->where('pipeline_step_id', $step4->id)->exists(), 'It should have gone to False step');
        $this->assertFalse($run2->stepRuns()->where('pipeline_step_id', $step3->id)->exists(), 'It should NOT have gone to True step');
    }
}

class TestActions
{
    public function returnString(string $value): string
    {
        return $value;
    }

    public function returnJson(string $data): array
    {
        return json_decode($data, true);
    }
}
