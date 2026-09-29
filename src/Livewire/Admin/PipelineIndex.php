<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Livewire\Admin\Traits\WithBulkActions;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineRunStep;
use Agentoom\Core\Services\ExportService;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class PipelineIndex extends Component
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
        $pipeline = Pipeline::findOrFail($id);
        $pipeline->delete();

        $this->clearSelection();
        $this->resetPage();

        \Flux::toast(__('agentoom-core::admin.pipelines.deleted_success'));
    }

    public function clone(int $id): void
    {
        $pipeline = Pipeline::findOrFail($id);
        $newPipeline = $pipeline->replicate();
        $newPipeline->name = $pipeline->name.' (Clone)';
        $newPipeline->slug = null;
        $newPipeline->save();

        foreach ($pipeline->steps as $step) {
            $newStep = $step->replicate();
            $newStep->pipeline_id = $newPipeline->id;
            $newStep->save();
        }

        \Flux::toast(__('agentoom-core::admin.common.cloned_success'));

        $this->redirect(route('admin.pipelines.edit', $newPipeline));
    }

    public function toggleActive(int $id): void
    {
        $pipeline = Pipeline::findOrFail($id);
        $pipeline->is_active = ! $pipeline->is_active;
        $pipeline->save();

        session()->flash('success', __('agentoom-core::admin.pipelines.updated_success'));
    }

    public function truncateAllPipelineLogs(): void
    {
        if (! auth()->user()->hasRole('superadmin')) {
            abort(403);
        }

        Schema::disableForeignKeyConstraints();
        PipelineRunStep::truncate();
        PipelineRun::truncate();
        Schema::enableForeignKeyConstraints();

        $this->resetPage();
        session()->flash('success', __('agentoom-core::admin.pipelines.all_system_runs_deleted'));
    }

    protected function getPageIds(): array
    {
        return Pipeline::visibleTo(auth()->user())
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

        Pipeline::visibleTo(auth()->user())->whereIn('id', $this->selected)->delete();
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
            $pipeline = Pipeline::find($id);
            if ($pipeline) {
                $newPipeline = $pipeline->replicate();
                $newPipeline->name = $pipeline->name.' (Clone)';
                $newPipeline->slug = null;
                $newPipeline->save();

                foreach ($pipeline->steps as $step) {
                    $newStep = $step->replicate();
                    $newStep->pipeline_id = $newPipeline->id;
                    $newStep->save();
                }
            }
        }

        $this->clearSelection();
        $this->resetPage();
        session()->flash('success', __('agentoom-core::admin.common.cloned_success'));
    }

    public function bulkExport()
    {
        if (empty($this->selected)) {
            return null;
        }

        $exportService = app(ExportService::class);
        $data = $exportService->export(['pipelines' => $this->selected]);

        $json = json_encode($data, JSON_PRETTY_PRINT);
        $filename = 'pipelines_export_'.now()->format('Y-m-d_H-i-s').'.json';

        $this->clearSelection();

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename);
    }

    public function render()
    {
        $pipelines = Pipeline::visibleTo(auth()->user())
            ->withCount('steps', 'runs')
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->project_id, fn ($q) => $q->where('project_id', $this->project_id))
            ->latest()
            ->paginate($this->perPage);

        return view('agentoom-core::livewire.admin.pipeline-index', [
            'pipelines' => $pipelines,
        ])->layout('agentoom-core::layouts.admin');
    }
}
