<?php

namespace Agentoom\Core\DataTransfer\Sections;

use Agentoom\Core\DataTransfer\AbstractImportExportSection;
use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\Project;
use Illuminate\Support\Collection;

class PipelinesSection extends AbstractImportExportSection
{
    public function key(): string
    {
        return 'pipelines';
    }

    public function group(): string
    {
        return ImportExportRegistry::GROUP_AUTOMATION;
    }

    public function permission(): ?string
    {
        return 'pipelines.view';
    }

    public function importLevel(): int
    {
        return 4;
    }

    public function options(): Collection
    {
        return Pipeline::all();
    }

    public function export(array $ids): array
    {
        $query = Pipeline::query();
        if (($ids = $this->selectedIds($ids)) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function ($pipeline) {
            return [
                'name' => $pipeline->name,
                'description' => $pipeline->description,
                'type' => $pipeline->type,
                'is_active' => $pipeline->is_active,
                'config' => $pipeline->config,
                'project_name' => $pipeline->project?->name,
                'steps' => $pipeline->steps->map(function ($step) {
                    return [
                        'order' => $step->order,
                        'name' => $step->name,
                        'instructions_override' => $step->instructions_override,
                        'pass_output_as_input' => $step->pass_output_as_input,
                        'pass_all_previous_outputs' => $step->pass_all_previous_outputs,
                        'selected_outputs' => $step->selected_outputs,
                        'agent_slug' => $step->agent?->slug,
                        'agent_name' => $step->agent?->name,
                        'class' => $step->class,
                        'method' => $step->method,
                        'condition_if' => $step->condition_if,
                        'next_step_on_true' => $step->next_step_on_true,
                        'next_step_on_false' => $step->next_step_on_false,
                    ];
                })->toArray(),
            ];
        })->toArray();
    }

    public function import(array $payload, array $options = []): void
    {
        foreach ($payload as $pipelineData) {
            $project = null;
            if (isset($pipelineData['project_name'])) {
                $project = Project::where('name', $pipelineData['project_name'])->first();
            }

            $pipeline = Pipeline::updateOrCreate(
                ['name' => $pipelineData['name']],
                [
                    'description' => $pipelineData['description'] ?? null,
                    'type' => $pipelineData['type'] ?? 'standard',
                    'is_active' => $pipelineData['is_active'] ?? true,
                    'project_id' => $project?->id ?? $pipelineData['project_id'] ?? null,
                    'user_id' => $pipelineData['user_id'] ?? $this->ownerId(),
                    'config' => $pipelineData['config'] ?? [],
                ]
            );

            if (isset($pipelineData['steps'])) {
                $pipeline->steps()->delete();
                foreach ($pipelineData['steps'] as $stepData) {
                    $agent = null;
                    if (isset($stepData['agent_slug'])) {
                        $agent = Agent::where('slug', $stepData['agent_slug'])->first();
                    } elseif (isset($stepData['agent_name'])) {
                        $agent = Agent::where('name', $stepData['agent_name'])->first();
                    }

                    $pipeline->steps()->create([
                        'agent_id' => $agent?->id,
                        'class' => $stepData['class'] ?? null,
                        'method' => $stepData['method'] ?? null,
                        'order' => $stepData['order'],
                        'name' => $stepData['name'] ?? null,
                        'instructions_override' => $stepData['instructions_override'] ?? null,
                        'pass_output_as_input' => $stepData['pass_output_as_input'] ?? false,
                        'pass_all_previous_outputs' => $stepData['pass_all_previous_outputs'] ?? false,
                        'selected_outputs' => $stepData['selected_outputs'] ?? [],
                        'condition_if' => $stepData['condition_if'] ?? null,
                        'next_step_on_true' => $stepData['next_step_on_true'] ?? null,
                        'next_step_on_false' => $stepData['next_step_on_false'] ?? null,
                    ]);
                }
            }
        }
    }

    public function projectIds(array $projectIds): ?array
    {
        return Pipeline::whereIn('project_id', $projectIds)->pluck('id')->toArray();
    }
}
