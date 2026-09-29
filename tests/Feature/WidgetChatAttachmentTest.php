<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\ChatWidget\Livewire\WidgetChat;
use Agentoom\ChatWidget\Models\AgentWidget;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

class WidgetChatAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_remove_attachment(): void
    {
        Storage::fake('public');
        $agent = Agent::factory()->create(['is_active' => true, 'is_public' => true]);
        $widget = AgentWidget::factory()->create(['agent_id' => $agent->id, 'is_active' => true]);

        $file1 = UploadedFile::fake()->create('test1.pdf', 100);
        $file2 = UploadedFile::fake()->create('test2.pdf', 100);

        Livewire::test(WidgetChat::class, ['widget' => $widget])
            ->set('attachments', [$file1, $file2])
            ->call('removeAttachment', 'attachment', 0)
            ->assertCount('attachments', 1);
    }

    public function test_can_remove_image(): void
    {
        Storage::fake('public');
        $agent = Agent::factory()->create(['is_active' => true, 'is_public' => true]);
        $widget = AgentWidget::factory()->create(['agent_id' => $agent->id, 'is_active' => true]);

        $image1 = UploadedFile::fake()->image('image1.jpg');
        $image2 = UploadedFile::fake()->image('image2.jpg');

        Livewire::test(WidgetChat::class, ['widget' => $widget])
            ->set('imageUploads', [$image1, $image2])
            ->call('removeImage', 0)
            ->assertCount('imageUploads', 1);
    }

    public function test_can_remove_audio(): void
    {
        Storage::fake('public');
        $agent = Agent::factory()->create(['is_active' => true, 'is_public' => true]);
        $widget = AgentWidget::factory()->create(['agent_id' => $agent->id, 'is_active' => true]);

        $audio = UploadedFile::fake()->create('audio.webm', 100);

        Livewire::test(WidgetChat::class, ['widget' => $widget])
            ->set('audioUpload', $audio)
            ->call('removeAudio')
            ->assertSet('audioUpload', null);
    }
}
