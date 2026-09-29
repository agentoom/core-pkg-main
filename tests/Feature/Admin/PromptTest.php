<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\ChatWidget\Models\Prompt;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class PromptTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('superadmin');
    }

    public function test_can_list_prompts()
    {
        $prompt = Prompt::factory()->create(['label' => ['en' => 'Unique Label']]);

        $this->actingAs($this->admin)
            ->get(route('admin.prompt.index'))
            ->assertStatus(200)
            ->assertSee('Unique Label');
    }

    public function test_can_create_prompt()
    {
        Livewire::actingAs($this->admin)
            ->test('agentoom-chat-widget::admin.prompt-create-edit')
            ->set('buttons.0.label.en', 'Test Prompt')
            ->set('buttons.0.prompt.en', 'Help me with something')
            ->set('buttons.0.parameters', [['name' => 'site_id', 'type' => 'number', 'description' => 'Site ID']])
            ->call('save')
            ->assertRedirect(route('admin.prompt.index'));

        $this->assertDatabaseHas('prompts', [
            'label->en' => 'Test Prompt',
            'prompt->en' => 'Help me with something',
        ]);
    }

    public function test_can_edit_prompt()
    {
        $prompt = Prompt::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('agentoom-chat-widget::admin.prompt-create-edit', ['prompt' => $prompt])
            ->set('buttons.0.label.en', 'Updated Label')
            ->call('save')
            ->assertRedirect(route('admin.prompt.index'));

        $this->assertEquals('Updated Label', $prompt->fresh()->label['en']);
    }

    public function test_can_delete_prompt()
    {
        $prompt = Prompt::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('agentoom-chat-widget::admin.prompt-index')
            ->call('showDeleteModal', $prompt->id)
            ->call('delete')
            ->assertStatus(200);

        $this->assertDatabaseMissing('prompts', ['id' => $prompt->id]);
    }
}
