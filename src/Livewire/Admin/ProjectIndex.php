<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Livewire\Admin\Traits\WithBulkActions;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Services\ExportService;
use Flux;
use Livewire\Component;
use Livewire\WithPagination;

class ProjectIndex extends Component
{
    use WithBulkActions;
    use WithPagination;

    public string $search = '';

    public int $perPage = 15;

    public bool $show_bulk_delete_modal = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $project = Project::findOrFail($id);
        $project->delete();

        $this->clearSelection();
        $this->resetPage();

        Flux::toast(__('agentoom-core::admin.projects.deleted_success'));
    }

    public function clone(int $id): void
    {
        $project = Project::findOrFail($id);
        $newProject = $project->replicate();
        $newProject->name = $project->name.' (Clone)';
        $newProject->save();

        Flux::toast(__('agentoom-core::admin.common.cloned_success'));

        $this->redirect(route('admin.projects.edit', $newProject), navigate: true);
    }

    protected function getPageIds(): array
    {
        $user = auth()->user();

        return Project::query()
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                });
            })
            ->when(! $user->hasAnyRole(['superadmin', 'admin']), function ($q) use ($user) {
                $q->where(function ($sq) use ($user) {
                    $sq->where('user_id', $user->id)
                        ->orWhereHas('assignedUsers', fn ($aq) => $aq->where('users.id', $user->id));
                });
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

        $user = auth()->user();
        Project::query()
            ->when(! $user->hasAnyRole(['superadmin', 'admin']), function ($q) use ($user) {
                $q->where(function ($sq) use ($user) {
                    $sq->where('user_id', $user->id)
                        ->orWhereHas('assignedUsers', fn ($aq) => $aq->where('users.id', $user->id));
                });
            })
            ->whereIn('id', $this->selected)
            ->delete();

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
            $project = Project::find($id);
            if ($project) {
                $newProject = $project->replicate();
                $newProject->name = $project->name.' (Clone)';
                $newProject->save();
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
        $data = $exportService->export(['projects' => $this->selected]);

        $json = json_encode($data, JSON_PRETTY_PRINT);
        $filename = 'projects_export_'.now()->format('Y-m-d_H-i-s').'.json';

        $this->clearSelection();

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename);
    }

    public function render()
    {
        $user = auth()->user();

        $query = Project::query()
            ->with('user')
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                });
            })
            ->when(! $user->hasAnyRole(['superadmin', 'admin']), function ($q) use ($user) {
                $q->where(function ($sq) use ($user) {
                    $sq->where('user_id', $user->id)
                        ->orWhereHas('assignedUsers', fn ($aq) => $aq->where('users.id', $user->id));
                });
            })
            ->latest();

        return view('agentoom-core::livewire.admin.project-index', [
            'projects' => $query->paginate($this->perPage),
        ])->layout('agentoom-core::layouts.admin');
    }
}
