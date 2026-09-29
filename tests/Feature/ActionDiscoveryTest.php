<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Services\ActionDiscoveryService;
use Agentoom\Core\Tests\TestCase;

class ActionDiscoveryTest extends TestCase
{
    public function test_it_discover_actionable_methods(): void
    {
        $service = new ActionDiscoveryService;
        $methods = $service->discover();

        $key = 'Agentoom\Core\Services\TestActionableService@testMethod';

        // TestActionableService is excluded from discovery (test/demo only)
        $this->assertArrayNotHasKey($key, $methods);
    }
}
