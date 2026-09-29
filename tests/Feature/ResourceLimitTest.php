<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Services\ResourceLimitManager;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ResourceLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_is_reported_for_every_registered_resource()
    {
        Agent::factory()->count(3)->create();

        $usage = app(ResourceLimitManager::class)->getUsage();

        $this->assertEquals(3, $usage['agents']);
        $this->assertArrayHasKey('pipelines', $usage);
        $this->assertArrayHasKey('endpoints', $usage);
        $this->assertArrayHasKey('resource_types', $usage);
        $this->assertArrayHasKey('projects', $usage);
        $this->assertArrayHasKey('mcp_servers', $usage);
    }

    public function test_a_resource_without_a_configured_cap_is_never_capped()
    {
        config(['agentoom.limits.agents' => null]);

        Agent::factory()->count(100)->create();

        $manager = app(ResourceLimitManager::class);

        $this->assertNull($manager->limitFor('agents'));
        $this->assertFalse($manager->isLimitReached('agents'));
        $this->assertSame(['used' => 100, 'total' => null], $manager->getSummary()['agents']);
    }

    public function test_usage_at_the_configured_cap_is_reported_as_reached()
    {
        config(['agentoom.limits.agents' => 2]);

        $manager = app(ResourceLimitManager::class);

        $this->assertFalse($manager->isLimitReached('agents'));

        Agent::factory()->count(2)->create();

        $this->assertTrue($manager->isLimitReached('agents'));
        $this->assertSame(['used' => 2, 'total' => 2], $manager->getSummary()['agents']);
    }

    public function test_resources_per_type_cap_is_applied_per_resource_type()
    {
        config(['agentoom.limits.resources_per_type' => 1]);

        $manager = app(ResourceLimitManager::class);
        $cappedType = ResourceType::factory()->create();
        $otherType = ResourceType::factory()->create();

        Resource::factory()->create(['resource_type_id' => $cappedType->id]);

        $this->assertTrue($manager->isLimitReached('resources_per_type', $cappedType->id));
        $this->assertFalse($manager->isLimitReached('resources_per_type', $otherType->id));
    }
}
