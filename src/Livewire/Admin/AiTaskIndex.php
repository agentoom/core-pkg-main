<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Jobs\ProcessAiTaskJob;
use Agentoom\Core\Livewire\Admin\Traits\WithBulkActions;
use Agentoom\Core\Models\AiTask;
use Agentoom\Core\Services\ExportService;
use Livewire\Component;
use Livewire\WithPagination;

class AiTaskIndex extends Component
{
    use WithBulkActions;
    use WithPagination;

    public string $search = '';

    public ?int $project_id = null;

    public bool $is_embedded = false;

    public int $perPage = 15;

    public bool $show_bulk_delete_modal = false;

    public function mount(?int $project_id = null, bool $is_embedded = false): void
    {
        $this->project_id = $project_id;
        $this->is_embedded = $is_embedded;
    }

    public bool $show_task_modal = false;

    public ?AiTask $selectedTask = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function runManually(int $id): void
    {
        ProcessAiTaskJob::dispatch($id);
        session()->flash('success', __('agentoom-core::admin.ai_tasks.run_manually_success'));
    }

    public function toggleActive(int $id): void
    {
        $task = AiTask::findOrFail($id);
        $task->is_active = ! $task->is_active;
        $task->save();
    }

    public function delete(int $id): void
    {
        AiTask::destroy($id);
        session()->flash('success', __('agentoom-core::admin.ai_tasks.deleted_success'));
    }

    public function clone(int $id): void
    {
        $taskService = app(AiTaskService::class);
        $task = AiTask::findOrFail($id);
        $newTask = $task->replicate();
        $newTask->name = $task->name.' (Clone)';
        $newTask->status = 'pending';
        $newTask->last_run_at = null;
        $newTask->next_run_at = $taskService->calculateNextRunAt($newTask->period, $newTask->cron);
        $newTask->save();

        session()->flash('success', __('agentoom-core::admin.common.cloned_success'));

        $this->redirect(route('admin.agents.ai-tasks.edit', $newTask), navigate: true);
    }

    public function showTask(int $id): void
    {
        $this->selectedTask = AiTask::with(['agent', 'assignedByAgent', 'logs' => function ($query) {
            $query->latest()->limit(5);
        }])->findOrFail($id);

        $this->show_task_modal = true;
    }

    protected function getPageIds(): array
    {
        return AiTask::query()
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->project_id, function ($q) {
                $q->where('project_id', $this->project_id);
            })
            ->latest()
            ->paginate($this->perPage)
            ->pluck('id')
            ->toArray();
    }

    public function bulkDelete(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $this->show_bulk_delete_modal = true;
    }

    public function confirmBulkDelete(): void
    {
        if (empty($this->selected)) {
            return;
        }

        AiTask::whereIn('id', $this->selected)->delete();
        $this->clearSelection();
        $this->resetPage();
        $this->show_bulk_delete_modal = false;
        session()->flash('success', __('agentoom-core::admin.common.deleted_success'));
    }

    public function bulkClone(): void
    {
        if (empty($this->selected)) {
            return;
        }

        foreach ($this->selected as $id) {
            $task = AiTask::find($id);
            if ($task) {
                $newTask = $task->replicate();
                $newTask->name = $task->name.' (Clone)';
                $newTask->save();
            }
        }

        $this->clearSelection();
        session()->flash('success', __('agentoom-core::admin.common.cloned_success'));
    }

    public function bulkExport()
    {
        if (empty($this->selected)) {
            return null;
        }

        $exportService = app(ExportService::class);
        $data = $exportService->export(['ai_tasks' => $this->selected]);

        $json = json_encode($data, JSON_PRETTY_PRINT);
        $filename = 'ai_tasks_export_'.now()->format('Y-m-d_H-i-s').'.json';

        $this->clearSelection();

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename);
    }

    public function render()
    {
        $tasks = AiTask::with(['agent', 'assignedByAgent'])
            ->where('name', 'like', "%{$this->search}%")
            ->when($this->project_id, fn ($q) => $q->where('project_id', $this->project_id))
            ->latest()
            ->paginate($this->perPage);

        return view('agentoom-core::livewire.admin.ai-task-index', [
            'tasks' => $tasks,
        ])->layout('agentoom-core::layouts.admin');
    }
}
