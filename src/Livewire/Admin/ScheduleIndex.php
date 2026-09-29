<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Livewire\Admin\Traits\WithBulkActions;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Models\ScheduleLog;
use Agentoom\Core\Services\ExportService;
use Flux;
use Livewire\Component;
use Livewire\WithPagination;

class ScheduleIndex extends Component
{
    use WithBulkActions;
    use WithPagination;

    public string $search = '';

    public int $perPage = 15;

    public bool $show_bulk_delete_modal = false;

    public $show_delete_modal = false;

    public ?int $project_id = null;

    public bool $is_embedded = false;

    public function mount(?int $project_id = null, bool $is_embedded = false): void
    {
        $this->project_id = $project_id;
        $this->is_embedded = $is_embedded;
    }

    public $schedule_id_to_delete = null;

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function showDeleteModal($scheduleId): void
    {
        $this->schedule_id_to_delete = $scheduleId;
        $this->show_delete_modal = true;
    }

    public function delete(): void
    {
        $schedule = Schedule::findOrFail($this->schedule_id_to_delete);
        $this->authorize('delete', $schedule);

        $schedule->delete();
        $this->show_delete_modal = false;
        $this->schedule_id_to_delete = null;
        Flux::toast(__('agentoom-core::admin.schedules.deleted_success'));
    }

    public function clone(int $id): void
    {
        $schedule = Schedule::findOrFail($id);
        $this->authorize('view', $schedule);

        $newSchedule = $schedule->replicate();
        $newSchedule->name = $schedule->name.' (Clone)';
        $newSchedule->slug = null;
        $newSchedule->save();

        Flux::toast(__('agentoom-core::admin.common.cloned_success'));

        $this->redirect(route('admin.schedules.edit', $newSchedule), navigate: true);
    }

    public function toggleActive($scheduleId): void
    {
        $schedule = Schedule::findOrFail($scheduleId);
        $this->authorize('update', $schedule);

        $schedule->update(['active' => ! $schedule->active]);
    }

    public function truncateLogs(): void
    {
        $this->authorize('truncateLogs', Schedule::class);

        ScheduleLog::truncate();

        $this->resetPage();

        session()->flash('success', __('agentoom-core::admin.schedules.logs_truncated_success'));
    }

    protected function getPageIds(): array
    {
        return Schedule::visibleTo(auth()->user())
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('instructions', 'like', '%'.$this->search.'%');
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

        Schedule::visibleTo(auth()->user())->whereIn('id', $this->selected)->delete();
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
            $schedule = Schedule::find($id);
            if ($schedule) {
                $newSchedule = $schedule->replicate();
                $newSchedule->name = $schedule->name.' (Clone)';
                $newSchedule->slug = null;
                $newSchedule->save();
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
        $data = $exportService->export(['schedules' => $this->selected]);

        $json = json_encode($data, JSON_PRETTY_PRINT);
        $filename = 'schedules_export_'.now()->format('Y-m-d_H-i-s').'.json';

        $this->clearSelection();

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename);
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.schedule-index', [
            'schedules' => Schedule::visibleTo(auth()->user())
                ->when($this->search, function ($q) {
                    $q->where(function ($sq) {
                        $sq->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('instructions', 'like', '%'.$this->search.'%');
                    });
                })
                ->when($this->project_id, fn ($q) => $q->where('project_id', $this->project_id))
                ->latest()
                ->paginate($this->perPage),
        ])->layout('agentoom-core::layouts.admin');
    }
}
