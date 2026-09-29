<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\ResourceGroup;
use Livewire\Attributes\On;
use Livewire\Component;

class ResourceGroupCreateEdit extends Component
{
    public ?ResourceGroup $resourceGroup = null;

    public string $name = '';

    public string $icon = 'cube';

    public string $description = '';

    public ?int $projectId = null;

    public bool $is_editing = false;

    #[On('icon-selected')]
    public function updateIcon(string $icon): void
    {
        $this->icon = $icon;
    }

    public function mount(?ResourceGroup $resourceGroup = null): void
    {
        $this->resourceGroup = $resourceGroup;
        $this->is_editing = $resourceGroup !== null && $resourceGroup->exists;

        if ($this->is_editing) {
            $this->name = $resourceGroup->name ?? '';
            $this->icon = $resourceGroup->icon ?? 'cube';
            $this->description = $resourceGroup->description ?? '';
            $this->projectId = $resourceGroup->project_id;
        }
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'projectId' => 'nullable|exists:projects,id',
        ]);

        $data = [
            'name' => $this->name,
            'icon' => $this->icon,
            'description' => $this->description,
            'project_id' => $this->projectId,
            'user_id' => $this->resourceGroup?->user_id ?? auth()->id(),
        ];

        if ($this->is_editing) {
            $this->resourceGroup->update($data);
            session()->flash('success', __('Resource group updated successfully.'));
        } else {
            ResourceGroup::create($data);
            session()->flash('success', __('Resource group created successfully.'));
        }

        return $this->redirect(route('admin.resource-groups.index'), navigate: true);
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.resource-group-create-edit', [
            'projects' => Project::orderBy('name')->get(),
        ])->layout('agentoom-core::layouts.admin');
    }
}
