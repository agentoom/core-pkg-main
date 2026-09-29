<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\ChatWidget\Models\AgentWidget;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Ai;
use Livewire\Livewire;

class WidgetChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_file_to_widget_chat(): void
    {
        Storage::fake('local');
        Ai::fakeAgent(DynamicAgent::class);

        $user = User::factory()->create();
        Configuration::setValue('app_ai_credits', 1000000);

        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => true,
        ]);
        $widget = new AgentWidget([
            'agent_id' => $agent->id,
            'title' => 'Test Widget',
            'position' => 'bottom-right',
        ]);
        $widget->save();

        $file = UploadedFile::fake()->create('document.pdf', 500);

        Livewire::actingAs($user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->set('attachments', [$file])
            ->set('prompt', 'Check this file')
            ->call('send')
            ->assertSet('streaming', true)
            ->assertHasNoErrors(['attachments'])
            ->assertSet('messages.0.role', 'user')
            ->assertSet('messages.0.content', '<p>Check this file</p>')
            ->assertSet('messages.0.attachments.0.name', 'document.pdf')
            ->assertSet('messages.0.attachments.0.mime_type', 'application/pdf')
            ->assertSet('messages.0.attachments.0.type', 'document');
    }

    public function test_can_upload_audio_to_widget_chat(): void
    {
        Storage::fake('local');
        Ai::fakeAgent(DynamicAgent::class);

        $user = User::factory()->create();
        Configuration::setValue('app_ai_credits', 1000000);

        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => true,
        ]);
        $widget = new AgentWidget([
            'agent_id' => $agent->id,
            'title' => 'Test Widget',
            'position' => 'bottom-right',
        ]);
        $widget->save();

        $audio = UploadedFile::fake()->create('audio.mp3', 100);

        Livewire::actingAs($user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->set('audioUpload', $audio)
            ->call('send')
            ->assertSet('streaming', true)
            ->assertHasNoErrors(['audioUpload'])
            ->assertSet('messages.0.role', 'user')
            ->assertSet('messages.0.content', '')
            ->assertSet('messages.0.attachments.0.name', 'audio_recording.webm')
            ->assertSet('messages.0.attachments.0.mime_type', 'audio/mpeg')
            ->assertSet('messages.0.attachments.0.type', 'audio');
    }

    public function test_stale_conversation_id_is_replaced_before_audio_message_storage(): void
    {
        Storage::fake('local');
        Ai::fakeAgent(DynamicAgent::class);

        $user = User::factory()->create();
        Configuration::setValue('app_ai_credits', 1000000);

        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => true,
        ]);
        $widget = new AgentWidget([
            'agent_id' => $agent->id,
            'title' => 'Test Widget',
            'position' => 'bottom-right',
        ]);
        $widget->save();

        $staleConversationId = 'missing-conversation';
        $audio = UploadedFile::fake()->create('audio.mp3', 100);

        Livewire::actingAs($user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->set('conversationId', $staleConversationId)
            ->set('audioUpload', $audio)
            ->call('send')
            ->assertSet('streaming', true)
            ->assertSet('conversationId', fn (string $conversationId): bool => $conversationId !== $staleConversationId);
    }

    public function test_can_upload_image_to_widget_chat(): void
    {
        Storage::fake('public');
        Ai::fakeAgent(DynamicAgent::class);

        $user = User::factory()->create();
        Configuration::setValue('app_ai_credits', 1000000);

        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => true,
        ]);
        $widget = new AgentWidget([
            'agent_id' => $agent->id,
            'title' => 'Test Widget',
            'position' => 'bottom-right',
        ]);
        $widget->save();

        $image = UploadedFile::fake()->image('photo.jpg');

        Livewire::actingAs($user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->set('imageUploads', [$image])
            ->call('send')
            ->assertSet('streaming', true)
            ->assertHasNoErrors(['imageUploads'])
            ->assertSet('messages.0.role', 'user')
            ->assertSet('messages.0.content', '<p>What is in these images?</p>')
            ->assertSet('messages.0.attachments.0.name', 'photo.jpg')
            ->assertSet('messages.0.attachments.0.mime_type', 'image/jpeg')
            ->assertSet('messages.0.attachments.0.type', 'image');
    }

    public function test_can_interrupt_generation(): void
    {
        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => true,
        ]);
        $widget = new AgentWidget([
            'agent_id' => $agent->id,
            'title' => 'Test Widget',
            'position' => 'bottom-right',
        ]);
        $widget->save();

        Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->set('conversationId', 'conv_123')
            ->call('interrupt')
            ->assertSet('streaming', false);

        $this->assertTrue(Cache::has('interrupt_conv_123'));
    }

    public function test_file_upload_validation(): void
    {
        Storage::fake('local');

        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => true,
        ]);
        $widget = new AgentWidget([
            'agent_id' => $agent->id,
            'title' => 'Test Widget',
            'position' => 'bottom-right',
        ]);
        $widget->save();

        // Large file
        $file = UploadedFile::fake()->create('large.pdf', 11000); // 11MB

        $user = User::factory()->create();
        Configuration::setValue('app_ai_credits', 1000000);

        Livewire::actingAs($user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->set('attachments', [$file])
            ->call('send')
            ->assertHasErrors(['attachments.0' => 'max']);

        // Invalid type
        $file = UploadedFile::fake()->create('script.exe', 100);

        Livewire::actingAs($user)
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->set('attachments', [$file])
            ->call('send')
            ->assertHasErrors(['attachments.0' => 'mimes']);
    }
}
