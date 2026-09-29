<?php

namespace Agentoom\Core\DataTransfer\Sections;

use Agentoom\Core\DataTransfer\AbstractImportExportSection;
use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\Project;
use Illuminate\Support\Collection;

class EndpointsSection extends AbstractImportExportSection
{
    public function key(): string
    {
        return 'endpoints';
    }

    public function group(): string
    {
        return ImportExportRegistry::GROUP_AUTOMATION;
    }

    public function permission(): ?string
    {
        return 'endpoints.view';
    }

    public function importLevel(): int
    {
        return 5;
    }

    public function options(): Collection
    {
        return Endpoint::all();
    }

    public function export(array $ids): array
    {
        $query = Endpoint::query();
        if (($ids = $this->selectedIds($ids)) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function ($endpoint) {
            return [
                'name' => $endpoint->name,
                'slug' => $endpoint->slug,
                'description' => $endpoint->description,
                'prompt' => $endpoint->prompt,
                'is_active' => $endpoint->is_active,
                'data_structure' => $endpoint->data_structure,
                'allowed_sources' => $endpoint->allowed_sources,
                'auth_token_enabled' => $endpoint->auth_token_enabled,
                'auth_token' => $endpoint->auth_token,
                'auth_token_header' => $endpoint->auth_token_header,
                'agent_slug' => $endpoint->agent?->slug,
                'pipeline_name' => $endpoint->pipeline?->name,
                'project_name' => $endpoint->project?->name,
            ];
        })->toArray();
    }

    public function import(array $payload, array $options = []): void
    {
        foreach ($payload as $endpointData) {
            $agent = null;
            if (isset($endpointData['agent_slug'])) {
                $agent = Agent::where('slug', $endpointData['agent_slug'])->first();
            }

            $pipeline = null;
            if (isset($endpointData['pipeline_name'])) {
                $pipeline = Pipeline::where('name', $endpointData['pipeline_name'])->first();
            }

            $project = null;
            if (isset($endpointData['project_name'])) {
                $project = Project::where('name', $endpointData['project_name'])->first();
            }

            Endpoint::updateOrCreate(
                ['slug' => $endpointData['slug']],
                [
                    'name' => $endpointData['name'],
                    'description' => $endpointData['description'] ?? null,
                    'prompt' => $endpointData['prompt'] ?? null,
                    'is_active' => $endpointData['is_active'] ?? true,
                    'data_structure' => $endpointData['data_structure'] ?? [],
                    'allowed_sources' => $endpointData['allowed_sources'] ?? [],
                    'auth_token_enabled' => $endpointData['auth_token_enabled'] ?? false,
                    'auth_token' => $endpointData['auth_token'] ?? null,
                    'auth_token_header' => $endpointData['auth_token_header'] ?? 'X-Agentoom-Signature',
                    'agent_id' => $agent?->id,
                    'pipeline_id' => $pipeline?->id,
                    'project_id' => $project?->id,
                    'user_id' => $this->ownerId(),
                ]
            );
        }
    }

    public function projectIds(array $projectIds): ?array
    {
        return Endpoint::whereIn('project_id', $projectIds)->pluck('id')->toArray();
    }

    public function agentIds(array $ids): ?array
    {
        $query = Endpoint::query();
        if (($ids = $this->selectedIds($ids)) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->pluck('agent_id')->filter()->unique()->values()->all();
    }
}
