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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;

class MemoryWorkerWidgetExclusionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Configuration::setValue('app_ai_credits', 100.0);
        $this->user = User::factory()->create();

        $worker = Agent::create([
            'name' => 'Memory Worker',
            'slug' => 'memory-worker',
            'instructions' => 'Analyze memory',
            'description' => 'Memory worker agent',
            'config' => [],
        ]);
        Configuration::setValue('worker_agent_id', $worker->id);
    }

    public function test_memory_worker_skips_conversations_from_excluded_widgets(): void
    {
        if (! Schema::hasTable('agent_widgets')) {
            $this->markTestSkipped('agent_widgets table does not exist.');
        }

        $agent = Agent::create([
            'name' => 'Test Agent',
            'slug' => 'test-agent',
            'instructions' => 'test',
            'config' => [],
        ]);

        // Create an excluded widget
        $widgetId = DB::table('agent_widgets')->insertGetId([
            'agent_id' => $agent->id,
            'title' => 'Excluded Widget',
            'slug' => 'excluded-widget',
            'position' => 'bottom-right',
            'is_active' => true,
            'allow_memory_analysis' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $conversationId = (string) Str::uuid7();

        // Create conversation linked to widget
        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => $this->user->id,
            'agent_id' => $agent->id,
            'widget_id' => $widgetId,
            'title' => 'Excluded Chat',
            'allow_memory_analysis' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create unanalyzed message
        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversationId,
            'user_id' => $this->user->id,
            'agent' => 'test-agent',
            'role' => 'user',
            'content' => 'This should be ignored.',
            'is_analyzed' => false,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new MemoryWorkerService;
        $service->processUnanalyzedMessages();

        // Verify message is STILL NOT analyzed
        $this->assertEquals(1, DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('is_analyzed', false)
            ->count());
    }

    public function test_memory_worker_processes_conversations_from_included_widgets(): void
    {
        if (! Schema::hasTable('agent_widgets')) {
            $this->markTestSkipped('agent_widgets table does not exist.');
        }

        $agent = Agent::create([
            'name' => 'Test Agent 2',
            'slug' => 'test-agent-2',
            'instructions' => 'test',
            'config' => [],
        ]);

        // Create an included widget
        $widgetId = DB::table('agent_widgets')->insertGetId([
            'agent_id' => $agent->id,
            'title' => 'Included Widget',
            'slug' => 'included-widget',
            'position' => 'bottom-right',
            'is_active' => true,
            'exclude_from_memory' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $conversationId = (string) Str::uuid7();

        // Create conversation linked to widget
        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => $this->user->id,
            'agent_id' => $agent->id,
            'widget_id' => $widgetId,
            'title' => 'Included Chat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create unanalyzed message
        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversationId,
            'user_id' => $this->user->id,
            'agent' => 'test-agent',
            'role' => 'user',
            'content' => 'This should be analyzed.',
            'is_analyzed' => false,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mock AI response to avoid actual AI call
        Ai::fakeAgent(DynamicAgent::class, [
            '{"identity": [], "skills": []}',
        ]);

        $service = new MemoryWorkerService;
        $service->processUnanalyzedMessages();

        // Verify message is now analyzed
        $this->assertEquals(0, DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('is_analyzed', false)
            ->count());
    }
}
