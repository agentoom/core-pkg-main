<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\ChatWidget\Ai\Tools\SuggestFormsTool;
use Agentoom\ChatWidget\Models\AgentWidget;
use Agentoom\ChatWidget\Models\Form;
use Agentoom\ChatWidget\Models\Prompt;
use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class AgentWidgetAwarenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_is_aware_of_forms_and_prompts_via_instructions()
    {
        $agentModel = Agent::factory()->create();
        $widget = AgentWidget::factory()->create(['agent_id' => $agentModel->id]);

        $form = Form::factory()->create(['name' => ['en' => 'Test Form'], 'instructions' => 'Trigger for test form']);
        $prompt = Prompt::factory()->create(['label' => ['en' => 'Test Prompt']]);

        $widget->forms()->attach($form);
        $widget->prompts()->attach($prompt);

        $agent = new DynamicAgent($agentModel, $widget);
        $instructions = $agent->instructions();

        $this->assertStringContainsString('## Available Interaction Forms', $instructions);
        $this->assertStringContainsString('Test Form', $instructions);
        $this->assertStringContainsString('Trigger for test form', $instructions);

        $this->assertStringContainsString('## Available Interaction Prompts', $instructions);
        $this->assertStringContainsString('Test Prompt', $instructions);
    }

    public function test_suggest_forms_tool_can_list_forms_with_widget_context()
    {
        $widget = AgentWidget::factory()->create();
        $form = Form::factory()->create(['name' => ['en' => 'Website Form']]);
        $widget->forms()->attach($form);

        // Bind the widget ID to the container as WidgetChat would do
        app()->instance('current_widget_id', $widget->id);

        $tool = new SuggestFormsTool;
        $request = new Request(['action' => 'list']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('AVAILABLE INTERACTION FORMS FOR THIS WIDGET', $result);
        $this->assertStringContainsString('Website Form', $result);
        $this->assertStringContainsString('ID: '.$form->id, $result);
    }

    public function test_suggest_forms_tool_shows_error_without_widget_context()
    {
        $tool = new SuggestFormsTool;
        $request = new Request(['action' => 'list']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('No widget context found', $result);
    }
}
