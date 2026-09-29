<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Attributes\Actionable;
use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceGroup;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Traits\HasAdvancedFiltering;
use Illuminate\Support\Facades\Log;

#[GroupVisibility('agentoom-core')]
class DataRetrievalService
{
    use HasAdvancedFiltering;

    /**
     * Get resources with filters.
     *
     * @param  int|null  $limit  Maximum number of resources to retrieve. Default: 20.
     * @param  array  $filters  List of filter strings or sub-arrays for grouping. Examples: 'price > 100', 'sku starts with "ABC"', 'OR title contains "company"', 'created_at < "7 days ago"', 'status is empty', 'data.nested_field = "value"', ['type = "A"', 'OR level > 10'].
     * @param  int|null  $resource_type_id  Filter by a specific resource type ID.
     * @param  array  $fields  Optional list of fields to retrieve. If empty, all fields are retrieved.
     * @param  bool  $countOnly  If true, returns the total count of items found instead of the data.
     * @param  array  $order_by  List of [field, direction] pairs to sort results. Direction is 'asc' or 'desc' (default: 'asc'). Examples: [['created_at', 'desc']], [['price', 'asc'], ['id', 'desc']].
     */
    #[Actionable(
        name: 'Get Resources',
        description: 'Retrieve resources based on filters such as limit, type,
        and field conditions.',
        category: 'Data')]
    public function getResources(
        ?int $limit = 20,
        array $filters = [],
        ?int $resource_type_id = null,
        array $fields = [],
        bool $countOnly = false,
        array $order_by = [],
    ): array|int {
        Log::debug('DataRetrievalService::getResources called', [
            'limit' => $limit,
            'filters' => $filters,
            'resource_type_id' => $resource_type_id,
            'countOnly' => $countOnly,
        ]);

        $query = Resource::visibleTo(auth()->user());

        if (! empty($fields)) {
            $query->select($this->resolveSelectFields('resources', $fields));
        }

        if ($resource_type_id) {
            $query->where('resource_type_id', $resource_type_id);
        }

        $this->applyFilters($query, $filters, 'resources');

        if ($countOnly) {
            return $query->count();
        }

        foreach ($order_by as $sorting) {
            $query->orderBy($sorting[0], $sorting[1] ?? 'asc');
        }

        $result = $query->limit($limit ?? 20)->get()->toArray();

        return $result;
    }

    /**
     * Get scraped data with filters.
     *
     * @param  int|null  $limit  Maximum number of items to retrieve. Default: 20.
     * @param  array  $filters  List of filter strings or sub-arrays for grouping.
     * @param  int|null  $scraped_data_type_id  Filter by a specific scraped data type ID.
     * @param  array  $fields  Optional list of fields to retrieve.
     * @param  bool  $countOnly  If true, returns the total count of items found instead of the data.
     * @param  array  $order_by  List of [field, direction] pairs to sort results.
     */
    #[Actionable(name: 'Get Scraped Data', description: 'Retrieve scraped data based on filters such as limit, type, and field conditions.', category: 'Data')]
    public function getScrapedData(
        ?int $limit = 20,
        array $filters = [],
        ?int $scraped_data_type_id = null,
        array $fields = [],
        bool $countOnly = false,
        array $order_by = [],
    ): array|int {
        // Map to getResources as Scraped Data has been migrated to Resources
        return $this->getResources(
            limit: $limit,
            filters: $filters,
            resource_type_id: $scraped_data_type_id,
            fields: $fields,
            countOnly: $countOnly,
            order_by: $order_by
        );
    }

    /**
     * Get resource groups.
     */
    public function getResourceGroups(): array
    {
        return ResourceGroup::visibleTo(auth()->user())->get()->map(function ($group) {
            return [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'icon' => $group->icon,
            ];
        })->toArray();
    }

    /**
     * Get resource types.
     */
    public function getResourceTypes(?int $group_id = null): array
    {
        $query = ResourceType::visibleTo(auth()->user());

        if ($group_id) {
            $query->where('resource_group_id', $group_id);
        }

        return $query->get()->map(function ($type) {
            return [
                'id' => $type->id,
                'resource_group_id' => $type->resource_group_id,
                'name' => $type->name_translated,
                'description' => $type->description,
                'fields' => $type->fields,
            ];
        })->toArray();
    }
}
