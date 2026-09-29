<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\ChatWidget\Models\AgentWidget;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class WidgetSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Agent $agent;

    protected AgentWidget $widget;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'superadmin']);
        $this->user = User::factory()->create();
        $this->agent = Agent::factory()->create(['is_active' => true, 'is_public' => true]);
        $this->widget = AgentWidget::create([
            'agent_id' => $this->agent->id,
            'title' => 'Test Widget',
            'slug' => 'test-widget',
            'position' => 'bottom-right',
            'settings' => [
                'allow_audio' => true,
                'allow_attachments' => true,
            ],
        ]);
        Configuration::setValue('app_ai_credits', 1000000);
    }

    public function test_widget_allows_audio_when_enabled(): void
    {
        $audio = UploadedFile::fake()->create('recording.webm', 100, 'audio/webm');

        Livewire::actingAs($this->user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $this->widget])
            ->set('audioUpload', $audio)
            ->call('send')
            ->assertHasNoErrors(['audioUpload']);
    }

    public function test_widget_prohibits_audio_when_disabled(): void
    {
        $this->widget->update([
            'settings' => array_merge($this->widget->settings, ['allow_audio' => false]),
        ]);

        $audio = UploadedFile::fake()->create('recording.webm', 100, 'audio/webm');

        Livewire::actingAs($this->user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $this->widget])
            ->set('audioUpload', $audio)
            ->call('send')
            ->assertHasErrors(['audioUpload' => 'prohibited']);
    }

    public function test_widget_allows_attachments_when_enabled(): void
    {
        $doc = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        Livewire::actingAs($this->user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $this->widget])
            ->set('attachments', [$doc])
            ->call('send')
            ->assertHasNoErrors(['attachments']);
    }

    public function test_widget_prohibits_attachments_when_disabled(): void
    {
        $this->widget->update([
            'settings' => array_merge($this->widget->settings, ['allow_attachments' => false]),
        ]);

        $doc = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        Livewire::actingAs($this->user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $this->widget])
            ->set('attachments', [$doc])
            ->call('send')
            ->assertHasErrors(['attachments' => 'prohibited']);
    }

    public function test_admin_can_toggle_widget_upload_settings(): void
    {
        $this->user->assignRole('superadmin'); // Assuming roles exist from seeders

        Livewire::actingAs($this->user)
            ->test('agentoom-chat-widget::admin.agent-widget-create-edit', ['widget' => $this->widget])
            ->assertSet('allow_audio', true)
            ->assertSet('allow_attachments', true)
            ->set('allow_audio', false)
            ->set('allow_attachments', false)
            ->call('submit')
            ->assertHasNoErrors();

        $this->widget->refresh();
        $this->assertFalse($this->widget->settings['allow_audio']);
        $this->assertFalse($this->widget->settings['allow_attachments']);
    }
}
