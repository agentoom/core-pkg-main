<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Attributes\Actionable;

class TestActionableService
{
    #[Actionable(name: 'Test Method', description: 'A test actionable method.', category: 'Testing')]
    public function testMethod(string $param1, int $param2): string
    {
        return "Executed with {$param1} and {$param2}";
    }
}
