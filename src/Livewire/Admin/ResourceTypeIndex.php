<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Livewire\Admin\Traits\WithBulkActions;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Services\ExportService;
use Livewire\Component;
use Livewire\WithPagination;

class ResourceTypeIndex extends Component
{
    use WithBulkActions;
    use WithPagination;

    public string $search = '';

    public $show_delete_modal = false;

    public $resource_type_id_to_delete = null;

    public function showDeleteModal($resourceTypeId)
    {
        $this->resource_type_id_to_delete = $resourceTypeId;
        $this->show_delete_modal = true;
    }

    public function delete()
    {
        ResourceType::find($this->resource_type_id_to_delete)->delete();
        $this->show_delete_modal = false;
        $this->resource_type_id_to_delete = null;
        session()->flash('success', __('agentoom-core::admin.resource_types.deleted_success'));
    }

    protected function getPageIds(): array
    {
        return ResourceType::query()
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                });
            })
            ->pluck('id')
            ->toArray();
    }

    public function bulkDelete(): void
    {
        if (empty($this->selected)) {
            return;
        }

        ResourceType::whereIn('id', $this->selected)->delete();
        $this->clearSelection();
        session()->flash('success', __('agentoom-core::admin.common.deleted_success'));
    }

    public function bulkClone(): void
    {
        if (empty($this->selected)) {
            return;
        }

        foreach ($this->selected as $id) {
            $this->clone($id);
        }

        $this->clearSelection();
        session()->flash('success', __('agentoom-core::admin.common.cloned_success'));
    }

    public function clone(int $id): void
    {
        $type = ResourceType::find($id);
        if ($type) {
            $newType = $type->replicate();
            $name = $type->name;
            // Add (Clone) to name which is a translatable array
            if (is_array($name)) {
                foreach ($name as $lang => $val) {
                    $name[$lang] = $val.' (Clone)';
                }
            }
            $newType->name = $name;
            $newType->save();

            if (! $this->isAnySelected) {
                session()->flash('success', __('agentoom-core::admin.common.cloned_success'));
            }
        }
    }

    public function bulkExport()
    {
        if (empty($this->selected)) {
            return null;
        }

        $exportService = app(ExportService::class);
        $data = $exportService->export(['resource_types' => $this->selected]);

        $json = json_encode($data, JSON_PRETTY_PRINT);
        $filename = 'resource_types_export_'.now()->format('Y-m-d_H-i-s').'.json';

        $this->clearSelection();

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename);
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.resource-type-index', [
            'resourceTypes' => ResourceType::visibleTo(auth()->user())
                ->with('resourceGroup')
                ->when($this->search, function ($q) {
                    $q->where(function ($sq) {
                        $sq->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('description', 'like', '%'.$this->search.'%');
                    });
                })
                ->latest()
                ->paginate(15),
        ])->layout('agentoom-core::layouts.admin');
    }
}
