<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\ChatWidget\Models\AgentWidget;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class AgentWidgetPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'superadmin']);
        Role::create(['name' => 'user']);
    }

    public function test_superadmin_can_assign_users_to_widget(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        $user = User::factory()->create();
        $agent = Agent::factory()->create(['is_public' => true]);

        $this->actingAs($admin);

        $test = Livewire::test('agentoom-chat-widget::admin.agent-widget-create-edit')
            ->set('agent_id', $agent->id)
            ->set('title', 'My Widget')
            ->set('position', 'bottom-right')
            ->set('selected_users', [$user->id])
            ->call('submit');

        $test->assertHasNoErrors();

        $widget = AgentWidget::where('title', 'My Widget')->first();
        $this->assertNotNull($widget);
        $this->assertTrue($widget->users()->whereKey($user->id)->exists());
    }

    public function test_compatibility_check_detects_mismatch(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        $user = User::factory()->create();
        $agent = Agent::factory()->create([
            'is_public' => false,
        ]);
        // Agent is not assigned to $user

        $this->actingAs($admin);

        Livewire::test('agentoom-chat-widget::admin.agent-widget-create-edit')
            ->set('agent_id', $agent->id)
            ->set('selected_users', [$user->id])
            ->assertSet('show_permission_warning', true)
            ->assertSet('can_save', false)
            ->call('syncAgentPermissions')
            ->assertSet('show_permission_warning', false)
            ->assertSet('can_save', true);

        // Verify agent is no longer public
        $this->assertFalse($agent->fresh()->is_public);
    }

    public function test_standard_user_sees_only_assigned_widgets(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $agent = Agent::factory()->create(['is_public' => true]);

        $widget1 = AgentWidget::create([
            'agent_id' => $agent->id,
            'title' => 'Assigned Widget',
            'position' => 'bottom-right',
            'slug' => 'assigned-widget',
        ]);
        $widget1->users()->attach($user);

        $widget2 = AgentWidget::create([
            'agent_id' => $agent->id,
            'title' => 'Other Widget',
            'position' => 'bottom-right',
            'slug' => 'other-widget',
        ]);
        $otherUser = User::factory()->create();
        $widget2->users()->attach($otherUser);

        $widget3 = AgentWidget::create([
            'agent_id' => $agent->id,
            'title' => 'Public Widget',
            'position' => 'bottom-right',
            'slug' => 'public-widget',
        ]);
        // No specific users = everyone

        $this->actingAs($user);

        Livewire::test('agentoom-chat-widget::admin.agent-widget-index')
            ->assertSee('Assigned Widget')
            ->assertSee('Public Widget')
            ->assertDontSee('Other Widget');
    }

    public function test_widget_chat_enforces_user_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $agent = Agent::factory()->create(['is_public' => true, 'is_active' => true]);
        $widget = AgentWidget::create([
            'agent_id' => $agent->id,
            'title' => 'Restricted Widget',
            'position' => 'bottom-right',
            'slug' => 'restricted-widget',
        ]);
        $otherUser = User::factory()->create();
        $widget->users()->attach($otherUser);

        // Try as guest
        Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->assertSet('requiresAuth', true);

        // Try as unauthorized user
        $this->actingAs($user);
        Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->assertSet('accessDenied', true);

        // Try as authorized user
        $this->actingAs($otherUser);
        Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->assertSet('accessDenied', false)
            ->assertSet('requiresAuth', false);
    }

    public function test_standard_user_redirect_after_login(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user);

        $response = $this->get('/');
        $response->assertRedirect(route('admin.widgets.index'));
    }
}
