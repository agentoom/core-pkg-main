<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Livewire\Admin\Traits\WithBulkActions;
use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Services\ExportService;
use Flux;
use Livewire\Component;
use Livewire\WithPagination;

class EndpointIndex extends Component
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

    public $endpoint_id_to_delete = null;

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function showDeleteModal($endpointId)
    {
        $this->endpoint_id_to_delete = $endpointId;
        $this->show_delete_modal = true;
    }

    public function delete()
    {
        Endpoint::find($this->endpoint_id_to_delete)->delete();
        $this->show_delete_modal = false;
        $this->endpoint_id_to_delete = null;
        $this->clearSelection();
        $this->resetPage();
        Flux::toast(__('agentoom-core::admin.endpoints.deleted_success'));
    }

    public function clone(int $id): void
    {
        $endpoint = Endpoint::findOrFail($id);
        $newEndpoint = $endpoint->replicate();
        $newEndpoint->name = $endpoint->name.' (Clone)';
        $newEndpoint->slug = null;
        $newEndpoint->save();

        Flux::toast(__('agentoom-core::admin.common.cloned_success'));

        $this->redirect(route('admin.endpoints.edit', $newEndpoint), navigate: true);
    }

    protected function getPageIds(): array
    {
        return Endpoint::query()
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->project_id, function ($q) {
                $q->where('project_id', $this->project_id);
            })
            ->orderBy('name')
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

        Endpoint::whereIn('id', $this->selected)->delete();
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
            $endpoint = Endpoint::find($id);
            if ($endpoint) {
                $newEndpoint = $endpoint->replicate();
                $newEndpoint->name = $endpoint->name.' (Clone)';
                $newEndpoint->slug = null;
                $newEndpoint->save();
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
        $data = $exportService->export(['endpoints' => $this->selected]);

        $json = json_encode($data, JSON_PRETTY_PRINT);
        $filename = 'endpoints_export_'.now()->format('Y-m-d_H-i-s').'.json';

        $this->clearSelection();

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename);
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.endpoint-index', [
            'endpoints' => Endpoint::with('agent')
                ->when($this->search, function ($q) {
                    $q->where(function ($sq) {
                        $sq->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('description', 'like', '%'.$this->search.'%');
                    });
                })
                ->when($this->project_id, fn ($q) => $q->where('project_id', $this->project_id))
                ->orderBy('name')
                ->paginate($this->perPage),
        ])->layout('agentoom-core::layouts.admin');
    }
}
