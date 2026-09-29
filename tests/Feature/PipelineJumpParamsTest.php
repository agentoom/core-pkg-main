<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Ai\Tools\PipelineExecutionTool;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineExecutionService;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;
use Spatie\Permission\Models\Role;

class PipelineJumpParamsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_jump_passes_params(): void
    {
        $user = User::factory()->create();
        foreach (['admin', 'superadmin'] as $role) {
            if (! Role::where('name', $role)->where('guard_name', 'web')->exists()) {
                Role::create(['name' => $role, 'guard_name' => 'web']);
            }
        }
        $user->assignRole('superadmin');
        $this->actingAs($user);

        Configuration::setValue('app_ai_credits', 10000);
        JumpParamsActionStub::$runs = 0;

        $agent = Agent::factory()->create(['name' => 'Test Agent']);
        $pipeline = Pipeline::factory()->create(['name' => 'Jump Params Pipeline']);

        // Step 1: Agent step
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 1,
        ]);

        // Step 2: Jump action with params
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => JumpParamsActionStub::class,
            'method' => 'jumpWithParams',
            'order' => 2,
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'pending',
            'input' => 'Start',
            'user_id' => $user->id,
        ]);

        DynamicAgent::fake(function ($prompt) {
            if (str_contains($prompt, '{"last_entry":"entry1"}')) {
                return 'Received params!';
            }

            return 'Started';
        });

        $service = app(PipelineRunnerService::class);
        $service->executeRun($run);

        $run->refresh();
        $this->assertEquals('completed', $run->status, 'Run failed with error: '.$run->error);

        // Check Step 1 second run input
        $step1Runs = $run->stepRuns()->where('order', 1)->get();
        $this->assertCount(2, $step1Runs);

        $secondRun = $step1Runs[1];
        $this->assertStringContainsString('{"last_entry":"entry1"}', $secondRun->input);
    }

    public function test_pipeline_execution_tool_passes_params(): void
    {
        $service = new PipelineExecutionService;
        app()->instance(PipelineExecutionService::class, $service);

        $tool = new PipelineExecutionTool;
        $schema = new JsonSchemaTypeFactory;

        $request = new Request([
            'action' => 'jump',
            'step_order' => 1,
            'reason' => 'Testing tool',
            'params' => ['key' => 'value'],
        ]);

        $tool->handle($request);

        $this->assertTrue($service->hasJumped());
        $this->assertEquals(0, $service->getJumpOrder());
        $this->assertEquals(['key' => 'value'], $service->getJumpParams());

        // Also verify schema doesn't throw
        $schemaResult = $tool->schema($schema);
        $this->assertArrayHasKey('params', $schemaResult);
    }
}

class JumpParamsActionStub
{
    public static int $runs = 0;

    public function jumpWithParams()
    {
        self::$runs++;
        if (self::$runs === 1) {
            app(PipelineExecutionService::class)->jumpToStep(1, 'Looping back', ['last_entry' => 'entry1']);

            return 'Jumping back...';
        }

        return 'Continuing...';
    }
}
