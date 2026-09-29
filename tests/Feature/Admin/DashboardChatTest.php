<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\DashboardChat;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Services\AiCreditService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

class DashboardChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AiCreditService::class, function ($mock) {
            $mock->shouldReceive('hasSufficientCredits')->andReturn(true);
        });
    }

    public function test_can_mount_dashboard_chat(): void
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['is_active' => true, 'is_public' => true]);

        Livewire::actingAs($user)
            ->test(DashboardChat::class)
            ->assertStatus(200)
            ->assertSet('agentId', $agent->id)
            ->assertSet('statusContent', '');
    }

    public function test_stop_method_sets_cache_and_streaming_flag(): void
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['is_active' => true, 'is_public' => true]);

        $component = new DashboardChat;
        $component->currentInvocationId = 'test-id';

        $component->stop();

        $this->assertTrue(Cache::has('stop-ai-test-id'));
        $this->assertTrue($component->stopRequested);
        $this->assertFalse($component->streaming);
    }
}
