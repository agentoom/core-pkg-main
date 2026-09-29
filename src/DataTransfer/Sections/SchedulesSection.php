<?php

namespace Agentoom\Core\DataTransfer\Sections;

use Agentoom\Core\DataTransfer\AbstractImportExportSection;
use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\Schedule;
use Illuminate\Support\Collection;

class SchedulesSection extends AbstractImportExportSection
{
    public function key(): string
    {
        return 'schedules';
    }

    public function group(): string
    {
        return ImportExportRegistry::GROUP_AUTOMATION;
    }

    public function permission(): ?string
    {
        return 'schedules.view';
    }

    public function importLevel(): int
    {
        return 5;
    }

    public function options(): Collection
    {
        return Schedule::all();
    }

    public function export(array $ids): array
    {
        $query = Schedule::query();
        if (($ids = $this->selectedIds($ids)) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function ($schedule) {
            $modelName = null;
            if ($schedule->model === Agent::class) {
                $modelName = $schedule->resolveModel()?->slug ?? $schedule->resolveModel()?->name;
            } elseif ($schedule->model === Pipeline::class) {
                $modelName = $schedule->resolveModel()?->name;
            }

            return [
                'name' => $schedule->name,
                'minute' => $schedule->minute,
                'hour' => $schedule->hour,
                'day' => $schedule->day,
                'month' => $schedule->month,
                'day_of_week' => $schedule->day_of_week,
                'timezone' => $schedule->timezone,
                'model' => $schedule->model,
                'model_identifier' => $modelName,
                'method' => $schedule->method,
                'instructions' => $schedule->instructions,
                'repository_instructions' => $schedule->repository_instructions,
                'active' => $schedule->active,
                'project_name' => $schedule->project?->name,
            ];
        })->toArray();
    }

    public function import(array $payload, array $options = []): void
    {
        foreach ($payload as $scheduleData) {
            $modelId = null;
            if (isset($scheduleData['model']) && isset($scheduleData['model_identifier'])) {
                if ($scheduleData['model'] === Agent::class) {
                    $modelId = Agent::where('slug', $scheduleData['model_identifier'])
                        ->orWhere('name', $scheduleData['model_identifier'])
                        ->first()?->id;
                } elseif ($scheduleData['model'] === Pipeline::class) {
                    $modelId = Pipeline::where('name', $scheduleData['model_identifier'])->first()?->id;
                }
            }

            $project = null;
            if (isset($scheduleData['project_name'])) {
                $project = Project::where('name', $scheduleData['project_name'])->first();
            }

            Schedule::updateOrCreate(
                ['name' => $scheduleData['name']],
                [
                    'minute' => $scheduleData['minute'] ?? '*',
                    'hour' => $scheduleData['hour'] ?? '*',
                    'day' => $scheduleData['day'] ?? '*',
                    'month' => $scheduleData['month'] ?? '*',
                    'day_of_week' => $scheduleData['day_of_week'] ?? '*',
                    'timezone' => $scheduleData['timezone'] ?? 'UTC',
                    'model' => $scheduleData['model'] ?? null,
                    'model_id' => $modelId,
                    'method' => $scheduleData['method'] ?? null,
                    'instructions' => $scheduleData['instructions'] ?? null,
                    'repository_instructions' => $scheduleData['repository_instructions'] ?? null,
                    'active' => $scheduleData['active'] ?? true,
                    'project_id' => $project?->id,
                    'user_id' => $this->ownerId(),
                ]
            );
        }
    }

    public function projectIds(array $projectIds): ?array
    {
        return Schedule::whereIn('project_id', $projectIds)->pluck('id')->toArray();
    }
}
