<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PipelineParameterAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_input_parameters_are_available_in_custom_method_steps(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $pipeline = Pipeline::factory()->create([
            'name' => 'Param Test Pipeline',
            'config' => [
                'variables' => [
                    ['key' => 'global_param', 'value' => 'global_value'],
                ],
            ],
        ]);

        // Step 1: Custom method using both initial input and global variables
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => ParamTestStub::class,
            'method' => 'checkParams',
            'order' => 1,
            'instructions_override' => json_encode([
                [
                    'name' => 'input_param',
                    'value' => '{input_key}',
                    'type' => 'string',
                ],
                [
                    'name' => 'global_param',
                    'value' => '{{global_param}}',
                    'type' => 'string',
                ],
            ]),
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'pending',
            'input' => json_encode(['input_key' => 'input_value']),
            'user_id' => $user->id,
        ]);

        $service = app(PipelineRunnerService::class);
        $service->executeRun($run, $run->input);

        $run->refresh();
        $this->assertEquals('completed', $run->status, 'Run failed: '.$run->error);

        $this->assertEquals('input_value', ParamTestStub::$lastInputParam);
        $this->assertEquals('global_value', ParamTestStub::$lastGlobalParam);
    }
}

class ParamTestStub
{
    public static $lastInputParam;

    public static $lastGlobalParam;

    public function checkParams($input_param, $global_param)
    {
        self::$lastInputParam = $input_param;
        self::$lastGlobalParam = $global_param;

        return 'ok';
    }
}
