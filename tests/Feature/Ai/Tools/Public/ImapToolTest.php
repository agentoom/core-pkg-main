<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\Public\ImapFullTool;
use Agentoom\Core\Ai\Tools\Public\ImapSafeTool;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Services\ImapService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Mockery;

class ImapToolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup system config
        Configuration::setValue('mail_imap_host', 'imap.system.com');
        Configuration::setValue('mail_imap_port', '993');
        Configuration::setValue('mail_imap_username', 'system_user');
        Configuration::setValue('mail_imap_password', 'system_pass');
        Configuration::setValue('mail_imap_encryption', 'ssl');
    }

    public function test_imap_safe_tool_uses_system_config_when_no_resource_provided(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $mock = Mockery::mock(ImapService::class);
        $mock->shouldReceive('getSystemConfig')->andReturn([
            'host' => 'imap.system.com',
            'port' => '993',
            'username' => 'system_user',
            'password' => 'system_pass',
            'encryption' => 'ssl',
        ]);
        $mock->shouldReceive('listMailboxes')->with([
            'host' => 'imap.system.com',
            'port' => '993',
            'username' => 'system_user',
            'password' => 'system_pass',
            'encryption' => 'ssl',
        ])->once()->andReturn(['INBOX', 'Sent']);

        $this->app->instance(ImapService::class, $mock);

        $tool = new ImapSafeTool;
        $response = $tool->handle(new Request(['action' => 'list_mailboxes']));

        $this->assertStringContainsString('INBOX', $response);
        $this->assertStringContainsString('Sent', $response);
    }

    public function test_imap_safe_tool_uses_resource_when_provided(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create(['name' => 'IMAP Account', 'config' => []]);
        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'name' => 'My IMAP',
            'user_id' => $user->id,
            'data' => [
                'imap_host' => 'imap.resource.com',
                'imap_user' => 'resource_user',
                'imap_pass' => 'resource_pass',
            ],
        ]);

        $mock = Mockery::mock(ImapService::class);
        $mock->shouldReceive('listMailboxes')->with(Mockery::on(function ($config) {
            return $config['host'] === 'imap.resource.com' &&
                   $config['username'] === 'resource_user' &&
                   $config['password'] === 'resource_pass';
        }))->once()->andReturn(['RESOURCE_INBOX']);

        $this->app->instance(ImapService::class, $mock);

        $tool = new ImapSafeTool;
        $response = $tool->handle(new Request([
            'action' => 'list_mailboxes',
            'resource_id' => $resource->id,
            'resource_mapping' => [
                'host' => 'data.imap_host',
                'username' => 'data.imap_user',
                'password' => 'data.imap_pass',
            ],
        ]));

        $this->assertStringContainsString('RESOURCE_INBOX', $response);
    }

    public function test_imap_safe_tool_restricts_actions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tool = new ImapSafeTool;

        // delete_message is not in ImapSafeTool's enum but let's see how it handles unknown action
        $response = $tool->handle(new Request(['action' => 'delete_message']));

        $this->assertStringContainsString('Unknown action', $response);
        $this->assertStringContainsString('Allowed actions', $response);
    }

    public function test_imap_safe_tool_can_create_draft(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $mock = Mockery::mock(ImapService::class);
        $mock->shouldReceive('getSystemConfig')->andReturn([
            'host' => 'imap.system.com',
            'port' => '993',
            'username' => 'system_user',
            'password' => 'system_pass',
            'encryption' => 'ssl',
        ]);
        $mock->shouldReceive('createDraft')->once()->andReturn(true);

        $this->app->instance(ImapService::class, $mock);

        $tool = new ImapSafeTool;
        $response = $tool->handle(new Request([
            'action' => 'create_draft',
            'to' => 'test@example.com',
            'subject' => 'Test Subject',
            'body' => 'Test Body',
        ]));

        $this->assertEquals('Draft created successfully.', $response);
    }

    public function test_imap_full_tool_allows_all_actions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $mock = Mockery::mock(ImapService::class);
        $mock->shouldReceive('getSystemConfig')->andReturn([
            'host' => 'imap.system.com',
            'port' => '993',
            'username' => 'system_user',
            'password' => 'system_pass',
            'encryption' => 'ssl',
        ]);
        $mock->shouldReceive('deleteMessage')->once()->andReturn(true);
        $mock->shouldReceive('moveMessage')->once()->andReturn(true);
        $mock->shouldReceive('markMessage')->twice()->andReturn(true);

        $this->app->instance(ImapService::class, $mock);

        $tool = new ImapFullTool;

        $this->assertEquals('Message deleted successfully.', $tool->handle(new Request(['action' => 'delete_message', 'uid' => 123])));
        $this->assertEquals('Message moved successfully.', $tool->handle(new Request(['action' => 'move_message', 'uid' => 123, 'target_mailbox' => 'Archive'])));
        $this->assertEquals('Message marked as read.', $tool->handle(new Request(['action' => 'mark_as_read', 'uid' => 123])));
        $this->assertEquals('Message marked as unread.', $tool->handle(new Request(['action' => 'mark_as_unread', 'uid' => 123])));
    }
}
