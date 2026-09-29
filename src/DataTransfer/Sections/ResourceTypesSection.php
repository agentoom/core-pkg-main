<?php

namespace Agentoom\Core\DataTransfer\Sections;

use Agentoom\Core\DataTransfer\AbstractImportExportSection;
use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\ResourceType;
use Illuminate\Support\Collection;

class ResourceTypesSection extends AbstractImportExportSection
{
    public function key(): string
    {
        return 'resource_types';
    }

    public function group(): string
    {
        return ImportExportRegistry::GROUP_RESOURCES;
    }

    public function permission(): ?string
    {
        return 'resource-types.view';
    }

    public function importLevel(): int
    {
        return 1;
    }

    public function options(): Collection
    {
        return ResourceType::all();
    }

    public function optionLabel(mixed $option): string
    {
        return $option->name_translated;
    }

    public function export(array $ids): array
    {
        $query = ResourceType::query();
        if (($ids = $this->selectedIds($ids)) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function ($type) {
            return [
                'name' => $type->name,
                'description' => $type->description,
                'config' => $type->config,
                'project_name' => $type->project?->name,
            ];
        })->toArray();
    }

    public function import(array $payload, array $options = []): void
    {
        foreach ($payload as $resourceTypeData) {
            $resourceType = ResourceType::whereName($resourceTypeData['name'])->first();

            $project = null;
            if (isset($resourceTypeData['project_name'])) {
                $project = Project::where('name', $resourceTypeData['project_name'])->first();
            }

            $values = [
                'slug' => $resourceTypeData['slug'] ?? null,
                'description' => $resourceTypeData['description'] ?? null,
                'icon' => $resourceTypeData['icon'] ?? null,
                'is_active' => $resourceTypeData['is_active'] ?? true,
                'project_id' => $project?->id ?? $resourceTypeData['project_id'] ?? null,
                'user_id' => $resourceTypeData['user_id'] ?? $this->ownerId(),
                'config' => $resourceTypeData['config'] ?? [],
            ];

            if ($resourceType) {
                $resourceType->update($values);
            } else {
                $values['name'] = $resourceTypeData['name'];
                ResourceType::create($values);
            }
        }
    }

    public function projectIds(array $projectIds): ?array
    {
        return ResourceType::whereIn('project_id', $projectIds)->pluck('id')->toArray();
    }
}
