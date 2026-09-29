<?php

namespace Agentoom\Core\DataTransfer\Sections;

use Agentoom\Core\DataTransfer\AbstractImportExportSection;
use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Illuminate\Support\Collection;

class ResourcesSection extends AbstractImportExportSection
{
    public function key(): string
    {
        return 'resources';
    }

    public function group(): string
    {
        return ImportExportRegistry::GROUP_RESOURCES;
    }

    public function permission(): ?string
    {
        return 'resources.view';
    }

    public function importLevel(): int
    {
        return 4;
    }

    public function options(): Collection
    {
        return Resource::all();
    }

    public function export(array $ids): array
    {
        $query = Resource::query();
        if (($ids = $this->selectedIds($ids)) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function ($resource) {
            return [
                'name' => $resource->name,
                'description' => $resource->description,
                'data' => $resource->data,
                'resource_type' => $resource->resourceType?->name,
                'project_name' => $resource->project?->name,
            ];
        })->toArray();
    }

    public function import(array $payload, array $options = []): void
    {
        foreach ($payload as $resourceData) {
            $resourceType = ResourceType::whereName($resourceData['resource_type'])->first();

            $project = null;
            if (isset($resourceData['project_name'])) {
                $project = Project::where('name', $resourceData['project_name'])->first();
            }

            if ($resourceType) {
                Resource::updateOrCreate(
                    ['name' => $resourceData['name'], 'resource_type_id' => $resourceType->id],
                    [
                        'description' => $resourceData['description'] ?? null,
                        'project_id' => $project?->id ?? $resourceData['project_id'] ?? null,
                        'user_id' => $resourceData['user_id'] ?? $this->ownerId(),
                        'data' => $resourceData['data'] ?? [],
                    ]
                );
            }
        }
    }

    public function projectIds(array $projectIds): ?array
    {
        return Resource::whereIn('project_id', $projectIds)->pluck('id')->toArray();
    }
}
