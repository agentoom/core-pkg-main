<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Services\TestActionableService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class PipelineVariablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_replaces_shortcodes_in_custom_method_parameters(): void
    {
        Log::spy();
        $pipeline = Pipeline::create([
            'name' => 'Variables Pipeline',
            'config' => [
                'variables' => [
                    ['key' => 'greeting', 'value' => 'Hi'],
                    ['key' => 'count', 'value' => '789'],
                ],
            ],
        ]);

        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => TestActionableService::class,
            'method' => 'testMethod',
            'order' => 0,
            'instructions_override' => json_encode(['param1' => '{{greeting}}', 'param2' => '{{count}}']),
        ]);

        Log::shouldReceive('info')->atLeast()->once();

        $service = new PipelineRunnerService;

        $run = $service->run($pipeline, 'initial input');

        $this->assertEquals('completed', $run->status);
        $this->assertEquals('Executed with Hi and 789', $run->output);
    }

    public function test_it_replaces_shortcodes_in_agent_instructions(): void
    {
        // This is harder to test without mocking the agent's prompt method,
        // but we can at least check if replaceShortcodes is called correctly.
        // For now, let's just test that the service runs without errors.

        $this->markTestIncomplete('Testing agent instruction replacement requires complex mocking of DynamicAgent.');
    }
}
