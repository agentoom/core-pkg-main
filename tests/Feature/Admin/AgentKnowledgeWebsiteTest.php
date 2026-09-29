<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\AgentKnowledgeManager;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentKnowledgeFile;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class AgentKnowledgeWebsiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_website_url_as_knowledge()
    {
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $agent = Agent::factory()->create();

        $url = 'https://example.com/knowledge';
        $content = '<html><body><h1>Knowledge Base</h1><p>This is some knowledge from a website.</p><footer>Footer info</footer></body></html>';

        Http::fake([
            $url => Http::response($content, 200),
        ]);

        Livewire::test(AgentKnowledgeManager::class, ['agent' => $agent])
            ->set('knowledgeInputs.0.type', 'url')
            ->set('knowledgeInputs.0.url', $url)
            ->call('saveKnowledge')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('agent_knowledge_files', [
            'agent_id' => $agent->id,
            'type' => AgentKnowledgeFile::TYPE_URL,
            'path' => $url,
        ]);

        $knowledgeFile = AgentKnowledgeFile::where('path', $url)->first();

        $this->assertDatabaseHas('agent_knowledge', [
            'file_id' => $knowledgeFile->id,
            'agent_id' => $agent->id,
        ]);

        // Verify content was scraped and cleaned
        $knowledge = $knowledgeFile->knowledge()->first();
        $this->assertStringContainsString('Knowledge Base', $knowledge->knowledge);
        $this->assertStringContainsString('This is some knowledge from a website.', $knowledge->knowledge);
        $this->assertStringNotContainsString('Footer info', $knowledge->knowledge);
    }
}
