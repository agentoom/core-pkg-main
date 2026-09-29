<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Services\MemoryWorkerService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Spatie\Permission\Models\Role;

class LearnedSkillSolutionExtractionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Agent $agent;

    protected Agent $workerAgent;

    protected function setUp(): void
    {
        parent::setUp();
        Configuration::setValue('app_ai_credits', 100.0);

        // Seed roles
        if (! Role::where('name', 'admin')->exists()) {
            Role::create(['name' => 'admin', 'guard_name' => 'web']);
        }
        if (! Role::where('name', 'superadmin')->exists()) {
            Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        }

        $this->user = User::factory()->create();

        // The agent that "learned" something
        $this->agent = Agent::create([
            'name' => 'SEO Expert',
            'slug' => 'seo-expert',
            'instructions' => 'Help with SEO',
            'description' => 'SEO expert agent',
            'config' => [],
        ]);

        // The worker agent that extracts memory
        $this->workerAgent = Agent::create([
            'name' => 'Memory Worker',
            'slug' => 'memory-worker',
            'instructions' => 'Analyze memory',
            'description' => 'Memory worker agent',
            'config' => [],
        ]);

        Configuration::setValue('worker_agent_id', $this->workerAgent->id);
    }

    public function test_memory_worker_extracts_solution_as_learned_skill(): void
    {
        $conversationId = (string) Str::uuid7();

        // Create a conversation record
        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => $this->user->id,
            'agent_id' => $this->agent->id,
            'title' => 'Test Conversation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create messages showing a failure and a fix
        DB::table('agent_conversation_messages')->insert([
            [
                'id' => (string) Str::uuid7(),
                'conversation_id' => $conversationId,
                'user_id' => $this->user->id,
                'agent' => 'seo-expert',
                'role' => 'user',
                'content' => 'Please inspect the URL https://example.com/page-1/ on GSC.',
                'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]', 'is_analyzed' => false,
                'created_at' => now()->subMinutes(5), 'updated_at' => now()->subMinutes(5),
            ],
            [
                'id' => (string) Str::uuid7(),
                'conversation_id' => $conversationId,
                'user_id' => $this->user->id,
                'agent' => 'seo-expert',
                'role' => 'assistant',
                'content' => 'I tried to inspect it but got a 404 error because the URL was encoded.',
                'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]', 'is_analyzed' => false,
                'created_at' => now()->subMinutes(4), 'updated_at' => now()->subMinutes(4),
            ],
            [
                'id' => (string) Str::uuid7(),
                'conversation_id' => $conversationId,
                'user_id' => $this->user->id,
                'agent' => 'seo-expert',
                'role' => 'user',
                'content' => 'Try using the decoded URL instead of the encoded one.',
                'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]', 'is_analyzed' => false,
                'created_at' => now()->subMinutes(3), 'updated_at' => now()->subMinutes(3),
            ],
            [
                'id' => (string) Str::uuid7(),
                'conversation_id' => $conversationId,
                'user_id' => $this->user->id,
                'agent' => 'seo-expert',
                'role' => 'assistant',
                'content' => 'That worked! I successfully inspected the URL https://example.com/page-1/.',
                'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]', 'is_analyzed' => false,
                'created_at' => now()->subMinutes(2), 'updated_at' => now()->subMinutes(2),
            ],
        ]);

        // Mock the memory worker extraction
        Ai::fakeAgent(DynamicAgent::class, [
            '{"identity": [], "skills": [{"title": "GSC URL Inspection Fix", "content": "When encountering 404 errors with Google Search Console URL inspection, ensure the URL is decoded and raw rather than encoded."}]}',
        ]);

        $service = new MemoryWorkerService;
        $service->processUnanalyzedMessages();

        // Verify that the learned skill was saved
        $this->assertDatabaseHas('agent_learned_skills', [
            'agent_id' => $this->agent->id,
            'title' => 'GSC URL Inspection Fix',
        ]);

        $skill = DB::table('agent_learned_skills')
            ->where('agent_id', $this->agent->id)
            ->where('title', 'GSC URL Inspection Fix')
            ->first();

        $this->assertStringContainsString('decoded and raw', $skill->content);
        $this->assertStringContainsString('404 errors', $skill->content);
    }
}
