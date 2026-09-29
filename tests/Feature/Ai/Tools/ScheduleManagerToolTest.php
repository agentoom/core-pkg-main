<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools;

use Agentoom\Core\Ai\Tools\Public\ScheduleManagerTool;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Services\TestActionableService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Spatie\Permission\Models\Role;

class ScheduleManagerToolTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $user;

    protected ScheduleManagerTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Role::create(['name' => 'superadmin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('superadmin');

        $this->user = User::factory()->create();

        $this->tool = new ScheduleManagerTool;
    }

    public function test_it_lists_available_actions_for_admin(): void
    {
        $this->actingAs($this->admin);

        // Create some targets
        Agent::factory()->create(['name' => 'Agent A', 'is_active' => true, 'is_public' => false]);
        Pipeline::create(['name' => 'Pipeline A', 'is_active' => true]);

        $request = new Request(['action' => 'list_available_actions']);
        $response = $this->tool->handle($request);

        $this->assertStringContainsString('Agent A', $response);
        $this->assertStringContainsString('Pipeline A', $response);
        $this->assertStringContainsString('TestActionableService@testMethod', $response);
    }

    public function test_it_lists_available_actions_for_user_respecting_access(): void
    {
        $this->actingAs($this->user);

        // Public agent
        Agent::factory()->create(['name' => 'Public Agent', 'is_active' => true, 'is_public' => true]);
        // Private agent
        Agent::factory()->create(['name' => 'Private Agent', 'is_active' => true, 'is_public' => false]);

        $request = new Request(['action' => 'list_available_actions']);
        $response = $this->tool->handle($request);

        $this->assertStringContainsString('Public Agent', $response);
        $this->assertStringNotContainsString('Private Agent', $response);
    }

    public function test_it_lists_schedules_respecting_policies(): void
    {
        // Public schedule
        Schedule::create([
            'name' => 'Public Schedule',
            'minute' => '*', 'hour' => '*', 'day' => '*', 'month' => '*', 'day_of_week' => '*',
            'model' => Agent::class, 'model_id' => 1, 'active' => true,
        ]);

        // Private schedule for admin
        Schedule::create([
            'name' => 'Admin Schedule',
            'minute' => '*', 'hour' => '*', 'day' => '*', 'month' => '*', 'day_of_week' => '*',
            'model' => Agent::class, 'model_id' => 1, 'active' => true,
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->user);
        $request = new Request(['action' => 'list_schedules']);
        $response = $this->tool->handle($request);

        $this->assertStringContainsString('Public Schedule', $response);
        $this->assertStringNotContainsString('Admin Schedule', $response);

        $this->actingAs($this->admin);
        $response = $this->tool->handle(new Request(['action' => 'list_schedules']));
        $this->assertStringContainsString('Public Schedule', $response);
        $this->assertStringContainsString('Admin Schedule', $response);
    }

    public function test_it_lists_schedules_with_correct_format_and_no_json_params(): void
    {
        $this->actingAs($this->admin);

        // Schedule with JSON instructions (parameters)
        Schedule::create([
            'name' => 'JSON Param Schedule',
            'minute' => '0', 'hour' => '*/5', 'day' => '*', 'month' => '*', 'day_of_week' => '*',
            'model' => 'Agentoom\Core\Services\Api\PrestaShopService', 'method' => 'sync', 'active' => true,
            'instructions' => json_encode(['resourceId' => 18, 'entity' => 'orders']),
        ]);

        // Schedule with string instructions
        Schedule::create([
            'name' => 'String Param Schedule',
            'minute' => '0', 'hour' => '9', 'day' => '*', 'month' => '*', 'day_of_week' => '*',
            'model' => Agent::class, 'model_id' => 1, 'active' => false,
            'instructions' => 'Helpful prompt',
        ]);

        $request = new Request(['action' => 'list_schedules']);
        $response = $this->tool->handle($request);

        // Check format
        $this->assertStringContainsString('name: JSON Param Schedule', $response);
        $this->assertStringContainsString('status: active', $response);
        $this->assertStringContainsString('cron: 0 */5 * * *', $response);
        $this->assertStringContainsString('target_type: method', $response);
        $this->assertStringContainsString('target_id: Agentoom\Core\Services\Api\PrestaShopService@sync', $response);

        // Check JSON param exclusion
        $this->assertStringNotContainsString('resourceId', $response);
        $this->assertStringNotContainsString('entity', $response);

        // Check string param inclusion
        $this->assertStringContainsString('name: String Param Schedule', $response);
        $this->assertStringContainsString('status: inactive', $response);
        $this->assertStringContainsString('instructions: Helpful prompt', $response);
    }

    public function test_it_creates_an_agent_schedule(): void
    {
        $this->actingAs($this->user);
        $agent = Agent::factory()->create(['name' => 'Test Agent']);

        $request = new Request([
            'action' => 'create_schedule',
            'name' => 'New Agent Schedule',
            'type' => 'agent',
            'target_id' => $agent->id,
            'minute' => '0',
            'hour' => '9',
            'instructions' => 'Run every morning',
        ]);

        $response = $this->tool->handle($request);

        $this->assertStringContainsString('Successfully created schedule', $response);
        $this->assertDatabaseHas('schedules', [
            'name' => 'New Agent Schedule',
            'model' => Agent::class,
            'model_id' => $agent->id,
            'minute' => '0',
            'hour' => '9',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_it_creates_a_method_schedule(): void
    {
        $this->actingAs($this->user);

        $request = new Request([
            'action' => 'create_schedule',
            'name' => 'Method Schedule',
            'type' => 'method',
            'target_id' => TestActionableService::class.'@testMethod',
            'instructions' => json_encode(['param1' => 'hello', 'param2' => 123]),
        ]);

        $response = $this->tool->handle($request);

        $this->assertStringContainsString('Successfully created schedule', $response);
        $this->assertDatabaseHas('schedules', [
            'name' => 'Method Schedule',
            'model' => TestActionableService::class,
            'method' => 'testMethod',
            'instructions' => json_encode(['param1' => 'hello', 'param2' => 123]),
        ]);
    }

    public function test_it_updates_a_schedule(): void
    {
        $this->actingAs($this->user);
        $schedule = Schedule::create([
            'name' => 'Old Name',
            'minute' => '*', 'hour' => '*', 'day' => '*', 'month' => '*', 'day_of_week' => '*',
            'model' => Agent::class, 'model_id' => 1, 'active' => true,
            'user_id' => $this->user->id,
        ]);

        $request = new Request([
            'action' => 'update_schedule',
            'id' => $schedule->id,
            'name' => 'Updated Name',
            'active' => false,
        ]);

        $response = $this->tool->handle($request);

        $this->assertStringContainsString('Successfully updated schedule', $response);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'name' => 'Updated Name',
            'active' => 0,
        ]);
    }

    public function test_it_deletes_a_schedule(): void
    {
        $this->actingAs($this->user);
        $schedule = Schedule::create([
            'name' => 'To Delete',
            'minute' => '*', 'hour' => '*', 'day' => '*', 'month' => '*', 'day_of_week' => '*',
            'model' => Agent::class, 'model_id' => 1, 'active' => true,
            'user_id' => $this->user->id,
        ]);

        $request = new Request([
            'action' => 'delete_schedule',
            'id' => $schedule->id,
        ]);

        $response = $this->tool->handle($request);

        $this->assertStringContainsString('Successfully deleted schedule', $response);
        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_it_prevents_unauthorized_update(): void
    {
        $otherUser = User::factory()->create();
        $schedule = Schedule::create([
            'name' => 'Other User Schedule',
            'minute' => '*', 'hour' => '*', 'day' => '*', 'month' => '*', 'day_of_week' => '*',
            'model' => Agent::class, 'model_id' => 1, 'active' => true,
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($this->user);
        $request = new Request([
            'action' => 'update_schedule',
            'id' => $schedule->id,
            'name' => 'Hacked',
        ]);

        $response = $this->tool->handle($request);

        $this->assertStringContainsString('Unauthorized', $response);
        $this->assertDatabaseHas('schedules', ['id' => $schedule->id, 'name' => 'Other User Schedule']);
    }
}
