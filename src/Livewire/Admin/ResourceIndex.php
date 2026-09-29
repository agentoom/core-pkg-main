<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Livewire\Admin\Traits\WithBulkActions;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Services\ExportService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ResourceIndex extends Component
{
    use WithBulkActions;
    use WithPagination;

    #[Url]
    public $resource_type_id = '';

    #[Url]
    public $search = '';

    #[Url]
    public $sortBy = 'name';

    #[Url]
    public $sortDirection = 'asc';

    #[Url]
    public $perPage = 15;

    public $show_delete_modal = false;

    public $show_bulk_delete_modal = false;

    public $resource_id_to_delete = null;

    public function updatedResourceTypeId()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function sort($column)
    {
        $validColumns = ['name', 'resource_type', 'owner', 'description', 'created_at', 'updated_at'];
        if (! in_array($column, $validColumns)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function showDeleteModal($resourceId)
    {
        $this->resource_id_to_delete = $resourceId;
        $this->show_delete_modal = true;
    }

    public function delete()
    {
        $resource = Resource::visibleTo(auth()->user())->find($this->resource_id_to_delete);

        if ($resource) {
            $resource->delete();
            session()->flash('success', __('agentoom-core::admin.resources.deleted_success'));
        }

        $this->show_delete_modal = false;
        $this->resource_id_to_delete = null;
        $this->clearSelection();
        $this->resetPage();
    }

    public function clone($id): void
    {
        $resource = Resource::visibleTo(auth()->user())->findOrFail($id);
        $newResource = $resource->replicate();
        $newResource->name = $resource->name.' (Clone)';
        $newResource->save();

        session()->flash('success', __('agentoom-core::admin.common.cloned_success'));

        $this->redirect(route('admin.resources.edit', $newResource), navigate: true);
    }

    protected function getPageIds(): array
    {
        return Resource::visibleTo(auth()->user())
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->resource_type_id, function ($q) {
                $q->where('resource_type_id', $this->resource_type_id);
            })
            ->orderBy($this->sortBy, $this->sortDirection)
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

        Resource::visibleTo(auth()->user())->whereIn('id', $this->selected)->delete();
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
            $resource = Resource::find($id);
            if ($resource) {
                $newResource = $resource->replicate();
                $newResource->name = $resource->name.' (Clone)';
                $newResource->save();
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
        $data = $exportService->export(['resources' => $this->selected]);

        $json = json_encode($data, JSON_PRETTY_PRINT);
        $filename = 'resources_export_'.now()->format('Y-m-d_H-i-s').'.json';

        $this->clearSelection();

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename);
    }

    public function render()
    {
        $query = Resource::visibleTo(auth()->user())->with(['resourceType', 'user']);

        if ($this->resource_type_id) {
            $query->where('resource_type_id', $this->resource_type_id);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('resources.name', 'like', '%'.$this->search.'%')
                    ->orWhere('resources.description', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->sortBy === 'resource_type') {
            $locale = app()->getLocale();
            $query->leftJoin('resource_types', 'resources.resource_type_id', '=', 'resource_types.id')
                ->select('resources.*')
                ->orderBy('resource_types.name->'.$locale, $this->sortDirection);
        } elseif ($this->sortBy === 'owner') {
            $query->leftJoin('users', 'resources.user_id', '=', 'users.id')
                ->select('resources.*')
                ->orderBy('users.name', $this->sortDirection);
        } else {
            $query->orderBy('resources.'.$this->sortBy, $this->sortDirection);
        }

        return view('agentoom-core::livewire.admin.resource-index', [
            'resources' => $query->paginate($this->perPage),
            'resourceTypes' => ResourceType::visibleTo(auth()->user())->get()->sortBy(fn ($t) => $t->name_translated),
        ])->layout('agentoom-core::layouts.admin');
    }
}
