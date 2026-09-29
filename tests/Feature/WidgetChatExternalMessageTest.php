<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\ChatWidget\Models\AgentWidget;
use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Livewire\Livewire;

class WidgetChatExternalMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_message_is_held_until_consent_accepted(): void
    {
        Ai::fakeAgent(DynamicAgent::class);
        Configuration::setValue('app_ai_credits', 1000000);

        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => true,
        ]);
        $widget = AgentWidget::create([
            'agent_id' => $agent->id,
            'title' => 'Test Widget',
            'position' => 'bottom-right',
            'is_active' => true,
        ]);

        Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->set('consentAccepted', false) // Force false for this test
            ->call('submitExternalMessage', 'Hello World')
            ->assertSet('pendingExternalMessage', 'Hello World')
            ->assertSet('prompt', 'Hello World')
            ->assertSet('streaming', false)
            ->call('acceptConsent')
            ->assertSet('consentAccepted', true)
            ->assertSet('pendingExternalMessage', null)
            ->assertSet('streaming', true);
    }
}
