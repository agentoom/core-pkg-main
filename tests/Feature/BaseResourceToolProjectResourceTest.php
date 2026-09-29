<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\SSHTool;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\ProjectResource;
use Agentoom\Core\Models\ProjectResourceType;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Spatie\Permission\Models\Role;

class BaseResourceToolProjectResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Role::where('name', 'superadmin')->exists()) {
            Role::create(['name' => 'superadmin']);
        }
    }

    public function test_it_can_resolve_and_use_project_resource()
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $project = Project::factory()->create(['user_id' => $user->id]);

        $type = ProjectResourceType::create([
            'project_id' => $project->id,
            'name' => ['en' => 'Server'],
            'config' => [
                'fields' => [
                    ['name' => 'ip', 'type' => 'text'],
                    ['name' => 'username', 'type' => 'text'],
                    ['name' => 'password', 'type' => 'password', 'encrypted' => true],
                ],
            ],
        ]);

        $resource = ProjectResource::create([
            'project_id' => $project->id,
            'project_resource_type_id' => $type->id,
            'name' => 'My Server',
            'data' => [
                'ip' => '1.2.3.4',
                'username' => 'root',
                'password' => 'secret123',
            ],
        ]);

        $tool = new SSHTool;

        // Create a mock request with prefix
        $request = new Request([
            'action' => 'execute',
            'resource_id' => "project:{$resource->id}",
            'resource_mapping' => json_encode([
                'ip' => 'data.ip',
                'username' => 'data.username',
                'password' => 'data.password',
            ]),
            'command' => 'ls -la',
        ]);

        // If it resolved the resource AND decrypted the password, it will try to connect and fail with a connection error.
        $response = $tool->handle($request);

        $this->assertStringNotContainsString('Error: Could not resolve the specified server resource', (string) $response);
        $this->assertStringContainsString('Error connecting to server \'My Server\'', (string) $response);
        $this->assertStringContainsString('1.2.3.4', (string) $response);
    }

    public function test_it_can_resolve_and_use_global_resource_with_prefix()
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $type = ResourceType::create([
            'name' => ['en' => 'Server'],
            'config' => [
                'fields' => [
                    ['name' => 'ip', 'type' => 'text'],
                    ['name' => 'username', 'type' => 'text'],
                    ['name' => 'password', 'type' => 'password', 'encrypted' => true],
                ],
            ],
        ]);

        $resource = \Agentoom\Core\Models\Resource::create([
            'user_id' => $user->id,
            'resource_type_id' => $type->id,
            'name' => 'Global Server',
            'data' => [
                'ip' => '5.6.7.8',
                'username' => 'admin',
                'password' => 'global_secret',
            ],
        ]);

        $tool = new SSHTool;

        $request = new Request([
            'action' => 'execute',
            'resource_id' => "global:{$resource->id}",
            'resource_mapping' => json_encode([
                'ip' => 'data.ip',
                'username' => 'data.username',
                'password' => 'data.password',
            ]),
            'command' => 'ls -la',
        ]);

        $response = $tool->handle($request);

        $this->assertStringNotContainsString('Error: Could not resolve the specified server resource', (string) $response);
        $this->assertStringContainsString('Error connecting to server \'Global Server\'', (string) $response);
        $this->assertStringContainsString('5.6.7.8', (string) $response);
    }
}
