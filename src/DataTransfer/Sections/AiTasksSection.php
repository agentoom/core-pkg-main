<?php

namespace Agentoom\Core\DataTransfer\Sections;

use Agentoom\Core\DataTransfer\AbstractImportExportSection;
use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AiTask;
use Agentoom\Core\Models\Project;
use Illuminate\Support\Collection;

class AiTasksSection extends AbstractImportExportSection
{
    public function key(): string
    {
        return 'ai_tasks';
    }

    public function group(): string
    {
        return ImportExportRegistry::GROUP_SYSTEM;
    }

    public function allLabel(): string
    {
        return __('agentoom-core::admin.import_export.export.all_ai_tasks');
    }

    public function permission(): ?string
    {
        return 'ai-tasks.view';
    }

    public function importLevel(): int
    {
        return 5;
    }

    public function options(): Collection
    {
        return AiTask::all();
    }

    public function export(array $ids): array
    {
        $query = AiTask::query();
        if (($ids = $this->selectedIds($ids)) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function ($task) {
            return [
                'name' => $task->name,
                'description' => $task->description,
                'parameters' => $task->parameters,
                'period' => $task->period,
                'cron' => $task->cron,
                'is_active' => $task->is_active,
                'allow_memory_analysis' => $task->allow_memory_analysis,
                'status' => $task->status,
                'agent_name' => $task->agent?->name,
                'project_name' => $task->project?->name,
            ];
        })->toArray();
    }

    public function import(array $payload, array $options = []): void
    {
        foreach ($payload as $taskData) {
            $project = null;
            if (isset($taskData['project_name'])) {
                $project = Project::where('name', $taskData['project_name'])->first();
            }

            $agent = null;
            if (isset($taskData['agent_name'])) {
                $agent = Agent::where('name', $taskData['agent_name'])->first();
            }

            $values = $taskData;
            unset($values['project_name'], $values['agent_name']);
            $values['project_id'] = $project?->id ?? $taskData['project_id'] ?? null;
            $values['agent_id'] = $agent?->id ?? $taskData['agent_id'] ?? null;
            $values['user_id'] = $this->ownerId();

            AiTask::updateOrCreate(
                ['name' => $taskData['name'], 'project_id' => $values['project_id']],
                $values
            );
        }
    }

    public function projectIds(array $projectIds): ?array
    {
        return AiTask::whereIn('project_id', $projectIds)->pluck('id')->toArray();
    }
}
