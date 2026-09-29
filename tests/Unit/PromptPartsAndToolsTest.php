<?php

namespace Agentoom\Core\Tests\Unit;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PromptPartsAndToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_specific_prompt_parts_are_always_enabled(): void
    {
        $agentModel = Agent::factory()->create([
            'prompt_settings' => [
                'parts' => [
                    'role' => false,
                    'objective' => false,
                    'instructions' => false,
                    'user_context' => false,
                ],
            ],
        ]);

        $dynamicAgent = new DynamicAgent($agentModel);

        $reflection = new \ReflectionClass($dynamicAgent);
        $method = $reflection->getMethod('isPartEnabled');
        $method->setAccessible(true);

        // Always enabled
        $this->assertTrue($method->invoke($dynamicAgent, 'role'));
        $this->assertTrue($method->invoke($dynamicAgent, 'objective'));
        $this->assertTrue($method->invoke($dynamicAgent, 'instructions'));

        // Controlled by settings
        $this->assertFalse($method->invoke($dynamicAgent, 'user_context'));
    }

    public function test_verify_security_code_tool_is_always_enabled(): void
    {
        $agentModel = Agent::factory()->create([
            'prompt_settings' => [
                'tools' => [
                    'Agentoom_Sentinel_Ai_Tools_VerifySecurityCode' => false,
                    'Some_Other_Tool' => false,
                ],
            ],
        ]);

        $dynamicAgent = new DynamicAgent($agentModel);

        $reflection = new \ReflectionClass($dynamicAgent);
        $method = $reflection->getMethod('isToolEnabled');
        $method->setAccessible(true);

        // Always enabled
        $this->assertTrue($method->invoke($dynamicAgent, 'Agentoom\Sentinel\Ai\Tools\VerifySecurityCode'));

        // Controlled by settings
        $this->assertFalse($method->invoke($dynamicAgent, 'Some\Other\Tool'));
    }
}
