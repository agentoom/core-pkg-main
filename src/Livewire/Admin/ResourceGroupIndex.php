<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\ResourceGroup;
use Livewire\Component;
use Livewire\WithPagination;

class ResourceGroupIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public function delete(int $id): void
    {
        $group = ResourceGroup::visibleTo(auth()->user())->findOrFail($id);
        $group->delete();

        session()->flash('success', __('Resource group deleted successfully.'));
    }

    public function render()
    {
        $groups = ResourceGroup::visibleTo(auth()->user())
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%');
            })
            ->latest()
            ->paginate(10);

        return view('agentoom-core::livewire.admin.resource-group-index', [
            'groups' => $groups,
        ])->layout('agentoom-core::layouts.admin');
    }
}
