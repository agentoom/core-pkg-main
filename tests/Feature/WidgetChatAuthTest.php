<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\ChatWidget\Models\AgentWidget;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

class WidgetChatAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_auth_when_agent_is_not_public(): void
    {
        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => false,
        ]);

        $widget = AgentWidget::create([
            'agent_id' => $agent->id,
            'title' => 'Private Widget',
            'position' => 'bottom-right',
            'slug' => 'private-widget',
        ]);

        Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->assertSet('requiresAuth', true)
            ->assertSee(__('agentoom-core::front.sign_in_to_chat'));
    }

    public function test_can_login_to_private_agent_via_widget(): void
    {
        $password = 'password123';
        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => false,
        ]);
        $agent->update(['user_id' => $user->id]);

        $widget = AgentWidget::create([
            'agent_id' => $agent->id,
            'title' => 'Private Widget',
            'position' => 'bottom-right',
            'slug' => 'private-widget',
        ]);

        Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->assertSet('requiresAuth', true)
            ->set('loginEmail', $user->email)
            ->set('loginPassword', $password)
            ->call('login')
            ->assertSet('requiresAuth', false)
            ->assertHasNoErrors();

        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
    }

    public function test_denies_access_if_user_is_not_authorized_for_private_agent(): void
    {
        $password = 'password123';
        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => false,
        ]);
        // No user attached to agent

        $widget = AgentWidget::create([
            'agent_id' => $agent->id,
            'title' => 'Private Widget',
            'position' => 'bottom-right',
            'slug' => 'private-widget',
        ]);

        Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->set('loginEmail', $user->email)
            ->set('loginPassword', $password)
            ->call('login')
            ->assertHasErrors(['login' => __('You do not have access to this agent.')]);

        $this->assertFalse(Auth::check());
    }

    public function test_shows_access_denied_if_logged_in_user_not_authorized(): void
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => false,
        ]);

        $widget = AgentWidget::create([
            'agent_id' => $agent->id,
            'title' => 'Private Widget',
            'position' => 'bottom-right',
            'slug' => 'private-widget',
        ]);

        $this->actingAs($user);

        Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->assertSet('accessDenied', true)
            ->assertSee(__('agentoom-core::front.access_denied'));
    }

    public function test_can_logout_and_switch_account_from_access_denied_screen(): void
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => false,
        ]);

        $widget = AgentWidget::create([
            'agent_id' => $agent->id,
            'title' => 'Private Widget',
            'position' => 'bottom-right',
            'slug' => 'private-widget',
        ]);

        $this->actingAs($user);

        Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->assertSet('accessDenied', true)
            ->call('logout')
            ->assertSet('requiresAuth', true)
            ->assertSet('accessDenied', false);

        $this->assertFalse(Auth::check());
    }

    public function test_can_logout_from_chat_header(): void
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => true,
        ]);

        $widget = AgentWidget::create([
            'agent_id' => $agent->id,
            'title' => 'Public Widget',
            'position' => 'bottom-right',
            'slug' => 'public-widget',
        ]);

        $this->actingAs($user);

        Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->assertSet('requiresAuth', false)
            ->call('logout')
            ->assertSet('requiresAuth', false); // Since agent is public, logout doesn't force auth

        $this->assertFalse(Auth::check());
    }

    public function test_can_trigger_login_on_public_agent(): void
    {
        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => true,
        ]);

        $widget = AgentWidget::create([
            'agent_id' => $agent->id,
            'title' => 'Public Widget',
            'position' => 'bottom-right',
            'slug' => 'public-widget',
        ]);

        Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->assertSet('requiresAuth', false)
            ->call('showLoginForm')
            ->assertSet('requiresAuth', true);
    }

    public function test_hides_login_button_if_configured(): void
    {
        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => true,
        ]);

        $widget = AgentWidget::create([
            'agent_id' => $agent->id,
            'title' => 'Public Widget No Login',
            'position' => 'bottom-right',
            'slug' => 'public-widget-no-login',
            'settings' => [
                'show_login_button' => false,
            ],
        ]);

        Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->assertDontSee(__('agentoom-core::front.sign_in'));
    }

    public function test_shows_login_button_if_configured(): void
    {
        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => true,
        ]);

        $widget = AgentWidget::create([
            'agent_id' => $agent->id,
            'title' => 'Public Widget With Login',
            'position' => 'bottom-right',
            'slug' => 'public-widget-with-login',
            'settings' => [
                'show_login_button' => true,
            ],
        ]);

        Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->assertSee(__('agentoom-core::front.sign_in'));
    }
}
