<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\SSHTool;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class SSHToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_ssh_tool_with_resource_id_and_mapping()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $resourceType = ResourceType::create([
            'name' => ['en' => 'Server'],
            'config' => [],
        ]);

        $resource = Resource::create([
            'resource_type_id' => $resourceType->id,
            'user_id' => $user->id,
            'name' => 'Test Server',
            'description' => 'A test server',
            'data' => [
                'ip_address' => '1.2.3.4',
                'username' => 'testuser',
                'password' => 'testpass',
            ],
        ]);

        $tool = new SSHTool;
        $request = new Request([
            'resource_id' => (string) $resource->id,
            'resource_mapping' => [
                'ip' => 'data.ip_address',
                'username' => 'data.username',
                'password' => 'data.password',
            ],
            'command' => 'ls',
        ]);

        // Mocking SSH2 might be hard, but we can verify the handle correctly fails
        // when trying to connect to a fake IP, but ONLY after resolving credentials.
        $result = $tool->handle($request);

        // It should try to connect and probably time out or fail with specific error
        $this->assertStringContainsString("Error connecting to server 'Test Server'", (string) $result);
        $this->assertStringContainsString('testuser@1.2.3.4:22', (string) $result);
    }
}
