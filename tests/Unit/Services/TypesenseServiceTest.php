<?php

namespace Agentoom\Core\Tests\Unit\Services;

use Agentoom\Core\Services\TypesenseService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class TypesenseServiceTest extends TestCase
{
    public function test_it_prefixes_collection_names()
    {
        Config::set('scout.prefix', 'test_prefix_');

        $service = new TypesenseService;

        // Use reflection to access the private method for testing
        $reflection = new \ReflectionClass(TypesenseService::class);
        $method = $reflection->getMethod('getPrefixedName');
        $method->setAccessible(true);

        $this->assertEquals('test_prefix_my_collection', $method->invoke($service, 'my_collection'));
        $this->assertEquals('test_prefix_my_collection', $method->invoke($service, 'test_prefix_my_collection'));
    }

    public function test_it_does_not_prefix_if_no_prefix_configured()
    {
        Config::set('scout.prefix', '');

        $service = new TypesenseService;

        $reflection = new \ReflectionClass(TypesenseService::class);
        $method = $reflection->getMethod('getPrefixedName');
        $method->setAccessible(true);

        $this->assertEquals('my_collection', $method->invoke($service, 'my_collection'));
    }
}
