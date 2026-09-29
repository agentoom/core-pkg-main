<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Ai\Tools\Public\GmailTool;
use Agentoom\Core\Ai\Tools\Public\SSHTool;
use Agentoom\Core\Execution\ActionExecutor;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Tests\TestCase;
use Agentoom\Sentinel\Ai\Tools\VerifySecurityCode;
use Agentoom\Sentinel\Mail\SecurityOtpMail;
use Agentoom\Sentinel\Models\AiToolAuthorization;
use Agentoom\Sentinel\Models\AiToolDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Tools\Request;

class ToolSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected SSHTool $tool;

    protected DynamicAgent $agent;

    protected ActionExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->tool = new SSHTool;
        $agentModel = Agent::factory()->create();
        $this->agent = new DynamicAgent($agentModel);
        $this->executor = app(ActionExecutor::class);
    }

    public function test_tool_executes_normally_without_definition(): void
    {
        $this->actingAs($this->user);
        $request = new Request(['action' => 'execute', 'command' => 'ls', 'resource_id' => '1', 'resource_mapping' => ['ip' => '127.0.0.1']]);

        // Since we don't have a real resource, it should fail at resource resolution, not security
        $response = $this->executor->execute($this->agent, $this->tool, $request);

        $this->assertStringContainsString('Error: Could not resolve the specified server credential', (string) $response);
    }

    public function test_tool_requires_verbal_confirmation(): void
    {
        AiToolDefinition::create([
            'class_name' => SSHTool::class,
            'method_name' => 'execute',
            'requires_confirmation' => true,
            'requires_otp' => false,
        ]);

        $this->actingAs($this->user);
        $request = new Request(['action' => 'execute', 'command' => 'ls']);

        $response = $this->executor->execute($this->agent, $this->tool, $request);

        $this->assertStringContainsString('requires explicit verbal confirmation from the user', (string) $response);
        $this->assertStringContainsString('VerifySecurityCode', (string) $response);
        $this->assertDatabaseHas('ai_tool_authorizations', [
            'user_id' => $this->user->id,
            'is_confirmed' => false,
        ]);
    }

    public function test_tool_requires_otp_confirmation(): void
    {
        Mail::fake();

        AiToolDefinition::create([
            'class_name' => SSHTool::class,
            'method_name' => 'execute',
            'requires_confirmation' => true,
            'requires_otp' => true,
        ]);

        $this->actingAs($this->user);
        $request = new Request(['action' => 'execute', 'command' => 'ls']);

        $response = $this->executor->execute($this->agent, $this->tool, $request);

        $this->assertStringContainsString('Security code required', (string) $response);
        $this->assertStringContainsString('VerifySecurityCode', (string) $response);
        $this->assertDatabaseHas('ai_tool_authorizations', [
            'user_id' => $this->user->id,
            'is_confirmed' => false,
        ]);

        $authorization = AiToolAuthorization::first();
        $this->assertNotNull($authorization->otp_code);

        Mail::assertSent(function (SecurityOtpMail $mail) {
            return $mail->hasTo($this->user->email);
        });
    }

    public function test_verify_security_code_with_valid_otp(): void
    {
        $definition = AiToolDefinition::create([
            'class_name' => SSHTool::class,
            'method_name' => 'execute',
            'requires_confirmation' => true,
            'requires_otp' => true,
        ]);

        $authorization = AiToolAuthorization::create([
            'user_id' => $this->user->id,
            'tool_definition_id' => $definition->id,
            'otp_code' => '123456',
            'expires_at' => now()->addMinutes(5),
            'is_confirmed' => false,
        ]);

        $this->actingAs($this->user);
        $verifyTool = new VerifySecurityCode;
        $request = new Request(['action' => 'verify', 'pin' => '123456', 'tool_class_name' => SSHTool::class, 'tool_action' => 'execute']);

        $response = $verifyTool->handle($request);

        $this->assertStringContainsString('Security check successful', (string) $response);
        $this->assertTrue($authorization->fresh()->is_confirmed);
    }

    public function test_verify_verbal_confirmation(): void
    {
        $definition = AiToolDefinition::create([
            'class_name' => SSHTool::class,
            'method_name' => 'execute',
            'requires_confirmation' => true,
            'requires_otp' => false,
        ]);

        $authorization = AiToolAuthorization::create([
            'user_id' => $this->user->id,
            'tool_definition_id' => $definition->id,
            'otp_code' => null,
            'expires_at' => now()->addMinutes(5),
            'is_confirmed' => false,
        ]);

        $this->actingAs($this->user);
        $verifyTool = new VerifySecurityCode;
        $request = new Request(['action' => 'verify', 'tool_class_name' => SSHTool::class, 'tool_action' => 'execute']);

        $response = $verifyTool->handle($request);

        $this->assertStringContainsString('Security check successful', (string) $response);
        $this->assertTrue($authorization->fresh()->is_confirmed);
    }

    public function test_verify_verbal_confirmation_with_short_class_name(): void
    {
        $definition = AiToolDefinition::create([
            'class_name' => SSHTool::class,
            'method_name' => 'execute',
            'requires_confirmation' => true,
            'requires_otp' => false,
        ]);

        $authorization = AiToolAuthorization::create([
            'user_id' => $this->user->id,
            'tool_definition_id' => $definition->id,
            'otp_code' => null,
            'expires_at' => now()->addMinutes(5),
            'is_confirmed' => false,
        ]);

        $this->actingAs($this->user);
        $verifyTool = new VerifySecurityCode;
        // Using short class name
        $request = new Request(['action' => 'verify', 'tool_class_name' => 'SSHTool', 'tool_action' => 'execute']);

        $response = $verifyTool->handle($request);

        $this->assertStringContainsString('Security check successful', (string) $response);
        $this->assertTrue($authorization->fresh()->is_confirmed);
    }

    public function test_tool_executes_after_confirmation(): void
    {
        $definition = AiToolDefinition::create([
            'class_name' => SSHTool::class,
            'method_name' => 'execute',
            'requires_confirmation' => true,
            'requires_otp' => false,
        ]);

        AiToolAuthorization::create([
            'user_id' => $this->user->id,
            'tool_definition_id' => $definition->id,
            'is_confirmed' => true,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->actingAs($this->user);
        $request = new Request(['action' => 'execute', 'command' => 'ls', 'resource_id' => '1', 'resource_mapping' => ['ip' => '127.0.0.1']]);

        $response = $this->executor->execute($this->agent, $this->tool, $request);

        // Should bypass security and fail at resource resolution
        $this->assertStringContainsString('Error: Could not resolve the specified server credential', (string) $response);
    }

    public function test_prevents_duplicate_otp_emails_within_short_timeframe(): void
    {
        Mail::fake();

        AiToolDefinition::create([
            'class_name' => SSHTool::class,
            'method_name' => 'execute',
            'requires_confirmation' => true,
            'requires_otp' => true,
        ]);

        $this->actingAs($this->user);
        $request = new Request(['action' => 'execute', 'command' => 'ls']);

        // First call
        $this->executor->execute($this->agent, $this->tool, $request);

        // Second call immediately after
        $this->executor->execute($this->agent, $this->tool, $request);

        // Should only have sent one email
        Mail::assertSent(SecurityOtpMail::class, 1);
    }

    public function test_verify_security_code_with_naming_mismatch_works(): void
    {
        // Define tool with snake_case method name (common in DB)
        $definition = AiToolDefinition::create([
            'class_name' => 'Agentoom\Core\Ai\Tools\Public\GmailTool',
            'method_name' => 'send_message',
            'requires_confirmation' => true,
            'requires_otp' => true,
        ]);

        $authorization = AiToolAuthorization::create([
            'user_id' => $this->user->id,
            'tool_definition_id' => $definition->id,
            'otp_code' => '123456',
            'expires_at' => now()->addMinutes(5),
            'is_confirmed' => false,
        ]);

        $this->actingAs($this->user);

        // LLM uses camelCase 'sendMessage' as suggested by SecuredTool
        $verifyTool = new VerifySecurityCode;
        $request = new Request([
            'action' => 'verify',
            'pin' => '123456',
            'tool_class_name' => 'GmailTool',
            'tool_action' => 'sendMessage',
        ]);

        $response = (string) $verifyTool->handle($request);

        $this->assertStringContainsString('Security check successful', $response);
        $this->assertTrue($authorization->fresh()->is_confirmed);
    }

    public function test_secured_tool_finds_definition_with_camel_cased_action_for_snake_db_method(): void
    {
        // Define tool with snake_case method name (common in DB)
        $definition = AiToolDefinition::create([
            'class_name' => 'Agentoom\Core\Ai\Tools\Public\GmailTool',
            'method_name' => 'send_message',
            'requires_confirmation' => true,
            'requires_otp' => true,
        ]);

        $this->actingAs($this->user);

        // Use camelCase action 'sendMessage' in the request
        $request = new Request([
            'action' => 'sendMessage',
            'to' => 'test@example.com',
            'subject' => 'test',
            'body' => 'test',
        ]);

        $response = (string) $this->executor->execute($this->agent, new GmailTool, $request);

        // Should find the definition and ask for OTP, not execute immediately
        $this->assertStringContainsString('Security code required', $response);
        $this->assertDatabaseHas('ai_tool_authorizations', [
            'user_id' => $this->user->id,
            'tool_definition_id' => $definition->id,
        ]);
    }
}
