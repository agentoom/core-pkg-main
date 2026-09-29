<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class ConfigSaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'superadmin']);
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);
    }

    public function test_config_index_saves_ai_values()
    {
        Livewire::test('agentoom-core::admin.config-index')
            ->set('ai_provider', 'openai')
            ->set('ai_api_key', 'sk-123')
            ->set('ai_model', 'gpt-4o')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('openai', Configuration::getValue('ai_provider'));
        $this->assertEquals('sk-123', Configuration::getValue('ai_api_key'));
        $this->assertEquals('gpt-4o', Configuration::getValue('ai_model'));
    }

    public function test_config_index_saves_transcription_values()
    {
        Livewire::test('agentoom-core::admin.config-index')
            ->set('ai_transcription_provider', 'openai')
            ->set('ai_transcription_model', 'whisper-1')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('openai', Configuration::getValue('ai_transcription_provider'));
        $this->assertEquals('whisper-1', Configuration::getValue('ai_transcription_model'));
    }
}
