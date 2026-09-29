<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools;

use Agentoom\Core\Ai\Tools\Public\ScheduleRepositoryTool;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Models\ScheduleRepository;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class ScheduleRepositoryToolTest extends TestCase
{
    use RefreshDatabase;

    protected ScheduleRepositoryTool $tool;

    protected User $user;

    protected Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new ScheduleRepositoryTool;
        $this->user = User::factory()->create();
        $this->schedule = Schedule::create([
            'name' => 'Test Schedule',
            'minute' => '*',
            'hour' => '*',
            'day' => '*',
            'month' => '*',
            'day_of_week' => '*',
            'user_id' => $this->user->id,
            'model' => 'Agentoom\Core\Models\Agent',
            'active' => true,
        ]);
    }

    public function test_it_can_update_repository_data(): void
    {
        $this->actingAs($this->user);

        $request = new Request([
            'action' => 'update',
            'id' => $this->schedule->id,
            'key' => 'last_email_id',
            'value' => '12345',
        ]);

        $result = $this->tool->handle($request);

        $this->assertStringContainsString('Successfully updated', $result);
        $this->assertDatabaseHas('schedule_repositories', [
            'schedule_id' => $this->schedule->id,
            'key' => 'last_email_id',
            'value' => '12345',
        ]);
    }

    public function test_it_can_get_repository_data(): void
    {
        $this->actingAs($this->user);

        ScheduleRepository::create([
            'schedule_id' => $this->schedule->id,
            'key' => 'last_email_id',
            'value' => '12345',
        ]);

        $request = new Request([
            'action' => 'get',
            'id' => $this->schedule->id,
        ]);

        $result = $this->tool->handle($request);

        $this->assertStringContainsString('Repository data for schedule ID', $result);
        $this->assertStringContainsString('last_email_id', $result);
        $this->assertStringContainsString('12345', $result);
    }

    public function test_it_enforces_permissions(): void
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $request = new Request([
            'action' => 'get',
            'id' => $this->schedule->id,
        ]);

        $result = $this->tool->handle($request);

        $this->assertStringContainsString('Unauthorized', $result);
    }

    public function test_it_uses_schedule_id_from_constructor_if_omitted_in_request(): void
    {
        $this->actingAs($this->user);
        $toolWithContext = new ScheduleRepositoryTool($this->schedule->id);

        $request = new Request([
            'action' => 'update',
            'key' => 'last_email_id',
            'value' => '12345',
        ]);

        $result = $toolWithContext->handle($request);

        $this->assertStringContainsString('Successfully updated', $result);
        $this->assertDatabaseHas('schedule_repositories', [
            'schedule_id' => $this->schedule->id,
            'key' => 'last_email_id',
            'value' => '12345',
        ]);

        $requestGet = new Request([
            'action' => 'get',
        ]);

        $resultGet = $toolWithContext->handle($requestGet);
        $this->assertStringContainsString('last_email_id', $resultGet);
        $this->assertStringContainsString('12345', $resultGet);
    }
}
