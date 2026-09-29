<?php

namespace Agentoom\Core\Tests\Feature\V2;

use Agentoom\Core\Contracts\PrivacyIntegration;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Services\AiCreditService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class ChatSendTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->mock(AiCreditService::class, function ($mock) {
            $mock->shouldReceive('hasSufficientCredits')->andReturn(true);
        });
    }

    private function mockPrivacyServiceEnabled(): void
    {
        $this->mock(PrivacyIntegration::class, function ($mock) {
            $mock->shouldReceive('shouldAnonymize')->andReturn(true);
            $mock->shouldReceive('shouldAnonymizeForAgent')->andReturn(true);
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('anonymize')->andReturn(['text' => 'My name is {{PII_1}} and my email is {{PII_2}}']);
        });
    }

    private function mockPrivacyServiceDisabled(): void
    {
        $this->mock(PrivacyIntegration::class, function ($mock) {
            $mock->shouldReceive('shouldAnonymize')->andReturn(false);
            $mock->shouldReceive('shouldAnonymizeForAgent')->andReturn(false);
            $mock->shouldReceive('isEnabled')->andReturn(true);
        });
    }

    public function test_send_returns_anonymized_content_when_anonymization_enabled(): void
    {
        $this->mockPrivacyServiceEnabled();
        $user = User::factory()->create()->assignRole('admin');
        $agent = Agent::factory()->create(['is_active' => true, 'is_public' => true]);

        $response = $this->actingAs($user)
            ->post(route('admin.v2.chat.send'), [
                'prompt' => 'My name is John Smith and my email is john@example.com',
                'agentId' => $agent->id,
                'anonymize' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'conversationId',
            'messageId',
            'anonymizedContent',
        ]);

        $this->assertNotNull($response->json('anonymizedContent'),
            'anonymizedContent should not be null when anonymization is enabled and PII is detected');
    }

    public function test_send_returns_null_anonymized_content_when_anonymization_disabled(): void
    {
        $this->mockPrivacyServiceDisabled();
        $user = User::factory()->create()->assignRole('admin');
        $agent = Agent::factory()->create(['is_active' => true, 'is_public' => true]);

        $response = $this->actingAs($user)
            ->post(route('admin.v2.chat.send'), [
                'prompt' => 'My name is John Smith and my email is john@example.com',
                'agentId' => $agent->id,
                'anonymize' => false,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'conversationId',
            'messageId',
            'anonymizedContent',
        ]);

        $this->assertNull($response->json('anonymizedContent'),
            'anonymizedContent should be null when anonymization is disabled');
    }

    public function test_send_with_attachment_returns_anonymized_content(): void
    {
        $this->mockPrivacyServiceEnabled();
        $user = User::factory()->create()->assignRole('admin');
        $agent = Agent::factory()->create(['is_active' => true, 'is_public' => true]);

        $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');

        $response = $this->actingAs($user)
            ->post(route('admin.v2.chat.send'), [
                'prompt' => 'My name is John Smith',
                'agentId' => $agent->id,
                'anonymize' => true,
                'attachments' => [$file],
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'conversationId',
            'messageId',
            'anonymizedContent',
        ]);
    }

    public function test_send_replaces_missing_conversation_before_storing_message(): void
    {
        $this->mockPrivacyServiceDisabled();
        $user = User::factory()->create()->assignRole('admin');
        $agent = Agent::factory()->create(['is_active' => true, 'is_public' => true]);
        $staleConversationId = (string) Str::uuid7();

        $response = $this->actingAs($user)
            ->post(route('admin.v2.chat.send'), [
                'prompt' => 'Recover this chat',
                'agentId' => $agent->id,
                'conversationId' => $staleConversationId,
                'anonymize' => false,
            ]);

        $response->assertOk();
        $conversationId = $response->json('conversationId');

        $this->assertNotSame($staleConversationId, $conversationId);
        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversationId,
            'content' => 'Recover this chat',
        ]);
    }
}
