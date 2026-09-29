<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class AiTaskIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
    }

    public function test_it_shows_create_button(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Livewire::actingAs($user)
            ->test('agentoom-core::admin.ai-task-index')
            ->assertSeeHtml(route('admin.agents.ai-tasks.create'))
            ->assertSee(__('agentoom-core::admin.ai_tasks.new'));
    }
}
