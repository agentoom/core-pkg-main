<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Livewire\Admin\Traits\WithBulkActions;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Services\ExportService;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class SkillIndex extends Component
{
    use WithBulkActions;
    use WithPagination;

    public $show_delete_modal = false;

    public $skill_id_to_delete = null;

    public int $perPage = 15;

    public bool $show_bulk_delete_modal = false;

    public $search = '';

    public $status = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function showDeleteModal($skillId)
    {
        $this->skill_id_to_delete = $skillId;
        $this->show_delete_modal = true;
    }

    public function delete()
    {
        AgentSkill::find($this->skill_id_to_delete)->delete();
        $this->show_delete_modal = false;
        $this->skill_id_to_delete = null;
        $this->clearSelection();
        $this->resetPage();
        session()->flash('success', __('agentoom-core::admin.skills.deleted_success'));
    }

    public function clone($id): void
    {
        $skill = AgentSkill::findOrFail($id);
        $newSkill = $skill->replicate();
        $newSkill->name = $skill->name.' (Clone)';
        $newSkill->slug = Str::slug($newSkill->name);
        $newSkill->save();

        // Sync resource types
        $newSkill->resourceTypes()->sync($skill->resourceTypes()->pluck('resource_types.id'));

        session()->flash('success', __('agentoom-core::admin.common.cloned_success'));

        $this->redirect(route('admin.skills.edit', $newSkill), navigate: true);
    }

    protected function getPageIds(): array
    {
        return AgentSkill::query()
            ->search($this->search)
            ->when($this->status !== '', function ($query) {
                $query->where('is_active', $this->status === 'active');
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

        AgentSkill::whereIn('id', $this->selected)->delete();
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
            $skill = AgentSkill::find($id);
            if ($skill) {
                $newSkill = $skill->replicate();
                $newSkill->name = $skill->name.' (Clone)';
                $newSkill->slug = Str::slug($newSkill->name).'-'.Str::random(5);
                $newSkill->save();
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
        $data = $exportService->export(['skills' => $this->selected]);

        $json = json_encode($data, JSON_PRETTY_PRINT);
        $filename = 'skills_export_'.now()->format('Y-m-d_H-i-s').'.json';

        $this->clearSelection();

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename);
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.skill-index', [
            'skills' => AgentSkill::query()
                ->search($this->search)
                ->when($this->status !== '', function ($query) {
                    $query->where('is_active', $this->status === 'active');
                })
                ->orderBy('name')
                ->paginate($this->perPage),
        ])->layout('agentoom-core::layouts.admin');
    }
}
