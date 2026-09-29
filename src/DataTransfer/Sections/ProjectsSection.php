<?php

namespace Agentoom\Core\DataTransfer\Sections;

use Agentoom\Core\DataTransfer\AbstractImportExportSection;
use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\Models\Project;
use Illuminate\Support\Collection;

class ProjectsSection extends AbstractImportExportSection
{
    public function key(): string
    {
        return 'projects';
    }

    public function group(): string
    {
        return ImportExportRegistry::GROUP_SYSTEM;
    }

    public function permission(): ?string
    {
        return 'projects.view';
    }

    public function options(): Collection
    {
        return Project::all();
    }

    public function importLevel(): int
    {
        return 0;
    }

    /**
     * @param  array<int, int|string>|array{0: 'all'}  $ids
     * @return array<int, array<string, mixed>>
     */
    public function export(array $ids): array
    {
        $query = Project::query();
        if (($ids = $this->selectedIds($ids)) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function ($project) {
            return [
                'name' => $project->name,
                'description' => $project->description,
                'budget_limit' => $project->budget_limit,
                'budget_limit_starts_at' => $project->budget_limit_starts_at?->toDateString(),
                'is_active' => $project->is_active,
            ];
        })->toArray();
    }

    /**
     * @param  array<int, array<string, mixed>>  $payload
     * @param  array<string, mixed>  $options
     */
    public function import(array $payload, array $options = []): void
    {
        foreach ($payload as $projectData) {
            Project::updateOrCreate(
                ['name' => $projectData['name']],
                [
                    'description' => $projectData['description'] ?? null,
                    'budget_limit' => $projectData['budget_limit'] ?? null,
                    'budget_limit_starts_at' => $projectData['budget_limit_starts_at'] ?? null,
                    'is_active' => $projectData['is_active'] ?? true,
                    'user_id' => $this->ownerId(),
                ]
            );
        }
    }
}
