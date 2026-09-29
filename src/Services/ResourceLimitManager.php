<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Models\Resource;

/**
 * Enforces the instance-wide resource caps configured in `agentoom.limits`.
 *
 * Caps come from environment variables (AGENTOOM_MAX_*), so they are a
 * deployment concern and not something the admin UI manages. A cap that is
 * unset is not applied: the resource is uncapped and usage is only reported.
 */
class ResourceLimitManager
{
    public function __construct(protected ResourceUsageRegistry $usageRegistry) {}

    /**
     * Current usage of every registered resource, keyed by resource name.
     *
     * @return array<string, int>
     */
    public function getUsage(): array
    {
        return $this->usageRegistry->usage();
    }

    /**
     * Cap configured for the given resource, or null when uncapped.
     */
    public function limitFor(string $resource): ?int
    {
        $limit = config("agentoom.limits.{$resource}");

        return is_numeric($limit) ? (int) $limit : null;
    }

    /**
     * Check if the resource has reached its configured cap.
     */
    public function isLimitReached(string $resource, ?int $resourceTypeId = null): bool
    {
        if ($resource === 'resources_per_type') {
            if (! $resourceTypeId) {
                return false;
            }

            $limit = $this->limitFor($resource);

            if ($limit === null) {
                return false;
            }

            return Resource::where('resource_type_id', $resourceTypeId)->count() >= $limit;
        }

        $limit = $this->limitFor($resource);

        if ($limit === null) {
            return false;
        }

        return ($this->getUsage()[$resource] ?? 0) >= $limit;
    }

    /**
     * Usage and cap of every registered resource, for the dashboard.
     *
     * Only resources that registered a counter are reported, so an uninstalled
     * optional package never appears in the dashboard.
     *
     * @return array<string, array{used: int, total: int|null}>
     */
    public function getSummary(): array
    {
        $summary = [];

        foreach ($this->getUsage() as $resource => $used) {
            $summary[$resource] = [
                'used' => $used,
                'total' => $this->limitFor($resource),
            ];
        }

        return $summary;
    }
}
