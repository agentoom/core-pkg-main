<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Execution\ActionExecution;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ForgeToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_model_decrypts_data_even_during_action_execution(): void
    {
        $user = User::factory()->create();
        $type = ResourceType::create([
            'name' => ['en' => 'Laravel Forge'],
            'config' => [
                'fields' => [
                    ['name' => 'api_token', 'type' => 'string', 'encrypted' => true],
                ],
            ],
        ]);

        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'user_id' => $user->id,
            'name' => 'My Forge',
            'data' => [
                'api_token' => 'secret-forge-token',
            ],
        ]);

        // When ActionExecution is bound, the standard accessor should redact
        $agent = $this->createMock(Agent::class);
        $tool = $this->createMock(Tool::class);
        $request = new Request([]);
        app()->instance(ActionExecution::class, new ActionExecution($agent, $tool, $request));

        $this->assertEquals('[ENCRYPTED_REFERENCE:api_token]', $resource->data['api_token']);

        // But getDecryptedData() should return the real value
        $this->assertEquals('secret-forge-token', $resource->getDecryptedData()['api_token']);
    }
}
