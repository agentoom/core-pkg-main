<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Services\MemoryWorkerService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Spatie\Permission\Models\Role;

class MemoryWorkerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

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

        // Create a default worker agent
        $worker = Agent::create([
            'name' => 'Memory Worker',
            'slug' => 'memory-worker',
            'instructions' => 'Analyze memory',
            'description' => 'Memory worker agent',
            'config' => [],
        ]);
        Configuration::setValue('worker_agent_id', $worker->id);
    }

    public function test_memory_worker_processes_unanalyzed_messages(): void
    {
        Ai::fakeAgent(DynamicAgent::class, [
            '{"identity": [], "skills": []}',
        ]);
        $conversationId = (string) Str::uuid7();

        // Create the conversation record
        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'session_id' => 'test_session',
            'title' => 'Test Conversation',
            'allow_memory_analysis' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create some unanalyzed messages
        DB::table('agent_conversation_messages')->insert([
            [
                'id' => (string) Str::uuid7(),
                'conversation_id' => $conversationId,
                'user_id' => $this->user->id,
                'agent' => 'test-agent',
                'role' => 'user',
                'content' => 'I like Python and I want to learn tax calculation.',
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '[]',
                'meta' => '[]',
                'is_analyzed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid7(),
                'conversation_id' => $conversationId,
                'user_id' => $this->user->id,
                'agent' => 'test-agent',
                'role' => 'assistant',
                'content' => 'I can help you with that!',
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '[]',
                'meta' => '[]',
                'is_analyzed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Create an analyzed message to ensure it is not re-processed
        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversationId,
            'user_id' => $this->user->id,
            'agent' => 'test-agent',
            'role' => 'user',
            'content' => 'Old message',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'is_analyzed' => true,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $service = new MemoryWorkerService;
        $service->processUnanalyzedMessages();

        // Verify messages in this conversation are now marked as analyzed
        $this->assertEquals(0, DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('is_analyzed', false)
            ->count());

        $this->assertEquals(3, DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('is_analyzed', true)
            ->count());
    }

    public function test_memory_worker_updates_existing_identity_keys(): void
    {
        Ai::fakeAgent(DynamicAgent::class, [
            '{"identity":[{"title":"preferred_name","content":"Giovannone","importance":5,"expires_at":null}],"skills":[]}',
        ]);

        $conversationId = (string) Str::uuid7();

        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => $this->user->id,
            'session_id' => 'test_session_update',
            'title' => 'Test Conversation Update',
            'allow_memory_analysis' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_information')->insert([
            'user_id' => $this->user->id,
            'title' => 'preferred_name',
            'content' => 'Ernesto',
            'importance' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversationId,
            'user_id' => $this->user->id,
            'agent' => 'test-agent',
            'role' => 'user',
            'content' => 'Please call me Giovannone from now on.',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'is_analyzed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new MemoryWorkerService)->processUnanalyzedMessages();

        $this->assertDatabaseHas('user_information', [
            'user_id' => $this->user->id,
            'title' => 'preferred_name',
            'content' => 'Giovannone',
            'importance' => 5,
        ]);
    }

    public function test_analyze_memory_command_runs_successfully(): void
    {
        Ai::fakeAgent(DynamicAgent::class, [
            '{"identity": [], "skills": []}',
        ]);

        $conversationId = (string) Str::uuid7();

        // Create the conversation record
        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'session_id' => 'test_session_command',
            'title' => 'Test Conversation Command',
            'allow_memory_analysis' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create some unanalyzed messages to ensure the command has something to do
        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversationId,
            'user_id' => $this->user->id,
            'agent' => 'test-agent',
            'role' => 'user',
            'content' => 'Hello, my name is Simone.',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'is_analyzed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('agentoom:analyze-memory')
            ->expectsOutput('Starting memory analysis...')
            ->expectsOutput('Found 1 unanalyzed messages.')
            ->expectsOutput('Memory analysis completed.')
            ->assertExitCode(0);
    }

    public function test_config_can_save_worker_agent_id(): void
    {
        $agent = Agent::create([
            'name' => 'Worker Agent',
            'slug' => 'worker-agent',
            'instructions' => 'Analyze memory',
            'description' => 'Worker agent',
            'config' => [],
        ]);

        Configuration::setValue('worker_agent_id', $agent->id);

        $this->assertEquals($agent->id, Configuration::getValue('worker_agent_id'));
    }

    public function test_memory_worker_skips_messages_when_pipeline_and_agent_have_memory_disabled(): void
    {
        // 1. Setup Agent with memory analysis disabled
        $agent = Agent::create([
            'name' => 'Test Agent',
            'slug' => 'test-agent-disabled',
            'instructions' => 'Do things',
            'allow_memory_analysis' => false,
            'config' => [],
        ]);

        // 2. Setup Pipeline with memory analysis disabled
        $pipeline = Pipeline::create([
            'name' => 'Test Pipeline',
            'allow_memory_analysis' => false,
            'type' => 'test',
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'running',
        ]);

        // 3. Instantiate DynamicAgent (which should call withThread and create a conversation)
        $dynamicAgent = new DynamicAgent(
            agentModel: $agent,
            pipelineRunId: $run->id,
            user: $this->user
        );

        // Verify conversation was created with allow_memory_analysis = false
        $conversation = DB::table('agent_conversations')
            ->where('session_id', "pipeline_run_{$run->id}")
            ->first();

        $this->assertNotNull($conversation, 'Conversation should have been created');
        $this->assertEquals(0, $conversation->allow_memory_analysis, 'allow_memory_analysis should be false');

        // 4. Add unanalyzed messages to this conversation
        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversation->id,
            'user_id' => $this->user->id,
            'agent' => 'test-agent',
            'role' => 'user',
            'content' => 'This should not be analyzed',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'is_analyzed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Run MemoryWorkerService
        $service = new MemoryWorkerService;
        $service->processUnanalyzedMessages();

        // 6. Verify messages are STILL unanalyzed
        $this->assertEquals(1, DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversation->id)
            ->where('is_analyzed', false)
            ->count(), 'Message should still be unanalyzed because memory analysis is disabled');
    }

    public function test_memory_worker_skips_messages_with_missing_conversation(): void
    {
        // 1. Create a message with a conversation ID that DOES NOT EXIST in agent_conversations
        $missingConversationId = (string) Str::uuid7();

        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $missingConversationId,
            'user_id' => $this->user->id,
            'agent' => 'test-agent',
            'role' => 'user',
            'content' => 'This has no conversation record',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'is_analyzed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Run MemoryWorkerService
        $service = new MemoryWorkerService;
        $service->processUnanalyzedMessages();

        // 3. Verify message is STILL unanalyzed (it should be skipped if conversation is missing)
        $this->assertEquals(1, DB::table('agent_conversation_messages')
            ->where('conversation_id', $missingConversationId)
            ->where('is_analyzed', false)
            ->count(), 'Message with missing conversation should be skipped');
    }

    public function test_with_thread_updates_existing_conversation_flag(): void
    {
        $threadId = 'test_thread_'.Str::random(8);

        // 1. Create conversation with memory analysis ENABLED
        DB::table('agent_conversations')->insert([
            'id' => (string) Str::uuid(),
            'session_id' => $threadId,
            'title' => 'Test',
            'allow_memory_analysis' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Instantiate DynamicAgent with this thread and memory analysis DISABLED
        $agent = Agent::create([
            'name' => 'Test Agent',
            'slug' => 'test-agent-update-flag',
            'instructions' => 'Do things',
            'allow_memory_analysis' => false,
            'config' => [],
        ]);

        $dynamicAgent = new DynamicAgent(
            agentModel: $agent,
            user: $this->user
        );
        $dynamicAgent->withThread($threadId, false);

        // 3. Verify conversation flag was UPDATED to false
        $conversation = DB::table('agent_conversations')
            ->where('session_id', $threadId)
            ->first();

        $this->assertEquals(0, $conversation->allow_memory_analysis, 'allow_memory_analysis should have been updated to false');
    }
}
