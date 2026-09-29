<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\ChatWidget\Ai\Tools\SuggestPromptsTool;
use Agentoom\ChatWidget\Models\AgentWidget;
use Agentoom\ChatWidget\Models\Prompt;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;

class WidgetChatPromptTest extends TestCase
{
    use RefreshDatabase;

    protected AgentWidget $widget;

    protected Agent $agent;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Configuration::setValue('app_ai_credits', 1000000);
        $this->user = User::factory()->create();
        $this->agent = Agent::factory()->create(['is_public' => true]);
        $this->widget = AgentWidget::factory()->create([
            'agent_id' => $this->agent->id,
            'slug' => 'test-widget',
        ]);
    }

    public function test_prompts_are_loaded_at_start()
    {
        $prompt = Prompt::factory()->create(['show_at_start' => true]);
        $this->widget->prompts()->attach($prompt);

        Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $this->widget])
            ->assertSet('activePrompts.0.id', $prompt->id)
            ->assertSee($prompt->label);
    }

    public function test_select_prompt_sends_message()
    {
        $prompt = Prompt::factory()->create([
            'label' => 'Help Me',
            'prompt' => 'I need help',
            'parameters' => ['site_id' => 123],
        ]);
        $this->widget->prompts()->attach($prompt);

        Livewire::actingAs($this->user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $this->widget])
            ->call('selectPrompt', $prompt->id)
            ->assertSet('prompt', '')
            ->assertSet('activePrompts', [])
            ->assertSee(__('agentoom-core::front.prompt_selected', ['label' => 'Help Me']));
    }

    public function test_suggest_prompts_tool_updates_active_prompts()
    {
        $prompt = Prompt::factory()->create(['label' => 'Suggested Prompt']);

        $tool = new SuggestPromptsTool;
        $request = new Request(['action' => 'show', 'prompt_ids' => [$prompt->id]]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Suggested Prompt', $result);
        $this->assertStringContainsString('"status":"success"', $result);
    }
}
