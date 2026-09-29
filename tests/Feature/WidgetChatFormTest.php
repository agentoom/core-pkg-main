<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\ChatWidget\Models\AgentWidget;
use Agentoom\ChatWidget\Models\Form;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class WidgetChatFormTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $agent;

    protected $widget;

    protected $form;

    protected function setUp(): void
    {
        parent::setUp();

        Configuration::setValue('app_ai_credits', 1000000);

        $this->user = User::factory()->create();
        $this->agent = Agent::factory()->create(['is_public' => true]);
        $this->widget = AgentWidget::factory()->create(['agent_id' => $this->agent->id]);
        $this->form = Form::factory()->create([
            'name' => 'User Details',
            'fields' => [
                ['name' => 'full_name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
                ['name' => 'newsletter', 'label' => 'Newsletter', 'type' => 'checkbox', 'options' => ['Yes', 'No']],
            ],
        ]);

        $this->widget->forms()->attach($this->form->id);
    }

    public function test_can_trigger_form_display()
    {
        Livewire::actingAs($this->user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $this->widget])
            ->call('showForm', $this->form->id)
            ->assertSet('activeForm.id', $this->form->id)
            ->assertSet('formData.full_name', '')
            ->assertSee('User Details')
            ->assertSee('Full Name');
    }

    public function test_can_submit_form()
    {
        Livewire::actingAs($this->user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $this->widget])
            ->call('showForm', $this->form->id)
            ->set('formData.full_name', 'John Doe')
            ->set('formData.newsletter', ['Yes'])
            ->call('submitForm')
            ->assertHasNoErrors()
            ->assertSet('activeForm', null)
            ->assertSee('Form submitted: User Details');
    }

    public function test_form_validation_works()
    {
        Livewire::actingAs($this->user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $this->widget])
            ->call('showForm', $this->form->id)
            ->set('formData.full_name', '')
            ->call('submitForm')
            ->assertHasErrors(['formData.full_name' => 'required']);
    }

    public function test_cannot_cancel_form_when_open()
    {
        Livewire::actingAs($this->user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $this->widget])
            ->call('showForm', $this->form->id)
            ->call('cancelForm')
            ->assertSet('activeForm.id', $this->form->id)
            ->assertHasErrors('prompt');
    }

    public function test_keyword_trigger_opens_form_and_blocks_send()
    {
        // Add trigger to form
        $this->form->update(['triggers' => ['support', 'help']]);

        Livewire::actingAs($this->user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $this->widget])
            ->set('prompt', 'Please support help now')
            ->call('send')
            ->assertSet('activeForm.id', $this->form->id)
            ->assertSet('prompt', '') // Assert prompt is cleared
            ->set('prompt', 'Another message')
            ->call('send')
            ->assertHasErrors('prompt');
    }

    public function test_external_message_trigger_clears_prompt()
    {
        $this->form->update(['triggers' => ['/schedule_demo']]);

        Livewire::actingAs($this->user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $this->widget])
            ->set('consentAccepted', true)
            ->call('submitExternalMessage', '/schedule_demo')
            ->assertSet('activeForm.id', $this->form->id)
            ->assertSet('prompt', '');
    }

    public function test_form_marked_as_show_at_start_opens_automatically(): void
    {
        $startForm = Form::factory()->create([
            'name' => 'Start Form',
            'show_at_start' => true,
            'fields' => [['name' => 'field1', 'type' => 'text', 'label' => 'Field 1']],
        ]);
        $this->widget->forms()->attach($startForm->id, ['order' => 1]);

        Livewire::actingAs($this->user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $this->widget])
            ->assertSet('activeForm.id', $startForm->id)
            ->assertSet('activeFormButtons', []);
    }

    public function test_clicking_form_button_opens_the_form(): void
    {
        $startForm = Form::factory()->create([
            'name' => 'Start Form',
            'show_at_start' => true,
        ]);
        $this->widget->forms()->attach($startForm->id, ['order' => 1]);

        Livewire::actingAs($this->user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $this->widget])
            ->call('selectFormButton', $startForm->id)
            ->assertSet('activeForm.id', $startForm->id)
            ->assertSet('activeFormButtons', []);
    }
}
