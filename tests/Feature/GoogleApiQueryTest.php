<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Services\Api\BaseApiService;
use Agentoom\Core\Tests\TestCase;

class GoogleApiQueryTest extends TestCase
{
    public function test_build_google_query_formats_arrays_without_indices()
    {
        $service = new class extends BaseApiService
        {
            public function test_build(array $params)
            {
                return $this->buildGoogleQuery($params);
            }
        };

        $params = [
            'types' => ['SearchEngine', 'Organization'],
            'languages' => ['it', 'en'],
            'query' => 'Uduny',
        ];

        $result = $service->test_build($params);

        $this->assertStringContainsString('types=SearchEngine', $result);
        $this->assertStringContainsString('types=Organization', $result);
        $this->assertStringContainsString('languages=it', $result);
        $this->assertStringContainsString('languages=en', $result);
        $this->assertStringContainsString('query=Uduny', $result);

        // Ensure no numeric indices
        $this->assertStringNotContainsString('types[0]', $result);
        $this->assertStringNotContainsString('languages[0]', $result);
    }
}
