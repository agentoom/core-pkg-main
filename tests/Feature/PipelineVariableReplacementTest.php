<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PipelineVariableReplacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_replaces_output_variables_in_parameters(): void
    {
        $service = new class extends PipelineRunnerService
        {
            public function test_replace(mixed $value, ?string $output, ?array $allPreviousOutputs = null): mixed
            {
                return $this->replaceOutputVariables($value, $output, $allPreviousOutputs);
            }
        };

        $output = json_encode([
            'status' => 'success',
            'data' => [
                'id' => 123,
                'user' => [
                    'name' => 'John Doe',
                ],
            ],
            'items' => [
                ['name' => 'Item 1'],
                ['name' => 'Item 2'],
            ],
        ]);

        // Simple field
        $this->assertEquals('success', $service->test_replace('{status}', $output));

        // Nested field
        $this->assertEquals(123, $service->test_replace('{data.id}', $output));
        $this->assertEquals('John Doe', $service->test_replace('{data.user.name}', $output));

        // Array element
        $this->assertEquals('Item 1', $service->test_replace('{items.0.name}', $output));

        // Multiple placeholders in string
        $this->assertEquals('User John Doe has status success', $service->test_replace('User {data.user.name} has status {status}', $output));

        // Missing field (should return the placeholder)
        $this->assertEquals('{missing}', $service->test_replace('{missing}', $output));

        // Recursive array replacement
        $params = [
            'p1' => '{status}',
            'p2' => [
                'nested' => '{data.id}',
            ],
        ];
        $expected = [
            'p1' => 'success',
            'p2' => [
                'nested' => 123,
            ],
        ];
        $this->assertEquals($expected, $service->test_replace($params, $output));
    }
}
