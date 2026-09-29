<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentConversation;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Services\ConversationTitleService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Ai;

class ConversationTitleGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_generate_a_title_for_a_conversation(): void
    {
        $user = User::factory()->create();

        $provider = Provider::create([
            'name' => 'openrouter',
            'api_key' => 'sk-fake-key',
            'url' => 'https://openrouter.ai/api/v1',
            'is_active' => true,
        ]);

        $model = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'anthropic/claude-3-opus',
            'is_active' => true,
            'model_features' => [],
        ]);

        Configuration::setValue('ai_provider', $provider->id);
        Configuration::setValue('ai_model', $model->id);
        Configuration::setValue('app_ai_credits', 1000000);

        $this->assertEquals(1000000, Configuration::getValue('app_ai_credits'));

        $agent = Agent::factory()->create([
            'slug' => 'summarizer',
            'is_active' => true,
            'system_agent' => true,
            'project_id' => null,
        ]);

        $conversation = AgentConversation::create([
            'id' => 'conv-123',
            'user_id' => $user->id,
            'agent_id' => $agent->id,
            'title' => 'New Chat',
        ]);

        DB::table('agent_conversation_messages')->insert([
            [
                'id' => 'msg-1',
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => 'Hello, I want to talk about Laravel development.',
                'agent' => DynamicAgent::class,
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '[]',
                'meta' => '[]',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'msg-2',
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => 'Sure! What specifically about Laravel would you like to know?',
                'agent' => DynamicAgent::class,
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '[]',
                'meta' => '[]',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Ai::fakeAgent(DynamicAgent::class, ['Laravel Development']);

        $service = new ConversationTitleService;
        $service->generateTitle($conversation->id);

        $this->assertEquals('Laravel Development', $conversation->fresh()->title);
    }

    public function test_it_does_not_overwrite_existing_titles(): void
    {
        $user = User::factory()->create();

        $conversation = AgentConversation::create([
            'id' => 'conv-123',
            'user_id' => $user->id,
            'title' => 'My Custom Title',
        ]);

        $service = new ConversationTitleService;
        $service->generateTitle($conversation->id);

        $this->assertEquals('My Custom Title', $conversation->fresh()->title);
    }
}
