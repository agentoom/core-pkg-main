<?php

namespace Agentoom\Core\Ai\Tools;

use Agentoom\Core\Services\Api\CloudflareService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

abstract class BaseCloudflareTool extends BaseResourceTool
{
    /**
     * Get the fields required in the resource_mapping.
     */
    protected function requiredMappingFields(): array
    {
        return [
            'api_token' => 'Cloudflare API Token',
        ];
    }

    /**
     * Execute a Cloudflare action via the CloudflareService.
     */
    protected function executeCloudflareAction(Request $request, string $endpoint, string $method, array|string $data = []): Stringable|string
    {
        $service = app(CloudflareService::class);

        $resourceId = $request->string('resource_id');

        return $service->execute(
            $endpoint,
            $method,
            $data,
            $resourceId ?: null,
            $this->getCleanParams($request, 'resource_mapping') ?: []
        );
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'resource_id' => $schema->string()
                ->description('Only needed if using a specific Cloudflare Resource connection. The globally saved Cloudflare integration resources are used by default.'),

            'resource_mapping' => $schema->object([])
                ->description('Only needed if providing a resource_id. Maps fields from the Resource to the API parameters (e.g., {"api_token": "data.api_token"}).'),

            'endpoint' => $schema->string()
                ->required()
                ->description('The Cloudflare API endpoint (e.g., "zones", "zones/{zone_id}/dns_records").'),

            'params' => $schema->object([])
                ->description('Query parameters for GET requests or request body for POST/PUT/PATCH requests.'),
        ];
    }
}
