<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Gateway\OpenRouter\OpenRouterGateway;
use Laravel\Ai\Providers\OpenRouterProvider;

class OpenRouterToolCallTest extends TestCase
{
    public function test_it_no_longer_fails_when_arguments_are_not_valid_json(): void
    {
        $gateway = new class(Event::fake()) extends OpenRouterGateway
        {
            public function testParse(array $data, $provider)
            {
                return $this->parseTextResponse($data, $provider, false);
            }
        };

        $provider = new OpenRouterProvider(['name' => 'openrouter'], Event::fake());

        $data = [
            'id' => 'gen-123',
            'model' => 'test-model',
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'hello',
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'function' => [
                                    'name' => 'test_tool',
                                    'arguments' => 'invalid json',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => [],
        ];

        $response = $gateway->testParse($data, $provider);

        $this->assertCount(1, $response->steps[0]->toolCalls);
        // Laravel\Ai decodes invalid JSON to [] instead of keeping the string
        $this->assertEquals([], $response->steps[0]->toolCalls[0]->arguments);
    }

    public function test_it_no_longer_fails_when_arguments_are_empty(): void
    {
        $gateway = new class(Event::fake()) extends OpenRouterGateway
        {
            public function test_parse(array $data, $provider)
            {
                return $this->parseTextResponse($data, $provider, false);
            }
        };

        $provider = new OpenRouterProvider(['name' => 'openrouter'], Event::fake());

        $data = [
            'id' => 'gen-456',
            'model' => 'test-model',
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'hello',
                        'tool_calls' => [
                            [
                                'id' => 'call_456',
                                'function' => [
                                    'name' => 'test_tool',
                                    'arguments' => '',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => [],
        ];

        $response = $gateway->testParse($data, $provider);

        $this->assertCount(1, $response->steps[0]->toolCalls);
        $this->assertEquals([], $response->steps[0]->toolCalls[0]->arguments);
    }
}
