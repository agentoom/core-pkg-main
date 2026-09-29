<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Livewire\Admin\Traits\WithBulkActions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class RoleIndex extends Component
{
    use WithBulkActions;
    use WithPagination;

    public $show_delete_modal = false;

    public $role_id_to_delete = null;

    public function showDeleteModal($roleId)
    {
        $this->role_id_to_delete = $roleId;
        $this->show_delete_modal = true;
    }

    public function delete()
    {
        Gate::authorize('roles.delete');

        $role = Role::find($this->role_id_to_delete);

        if ($role->name === 'superadmin') {
            session()->flash('error', __('agentoom-core::admin.roles.cannot_delete_superadmin'));
            $this->show_delete_modal = false;

            return;
        }

        $role->delete();
        $this->show_delete_modal = false;
        $this->role_id_to_delete = null;
        $this->clearSelection();
        $this->resetPage();
        session()->flash('success', __('agentoom-core::admin.roles.deleted_success'));
    }

    protected function getPageIds(): array
    {
        return Role::query()
            ->pluck('id')
            ->toArray();
    }

    public function bulkDelete(): void
    {
        Gate::authorize('roles.delete');

        if (empty($this->selected)) {
            return;
        }

        $roles = Role::whereIn('id', $this->selected)->get();

        foreach ($roles as $role) {
            if (in_array($role->name, ['superadmin', 'admin', 'user'])) {
                continue;
            }
            $role->delete();
        }

        $this->clearSelection();
        session()->flash('success', __('agentoom-core::admin.common.deleted_success'));
    }

    public function bulkClone(): void
    {
        Gate::authorize('roles.create');

        if (empty($this->selected)) {
            return;
        }

        foreach ($this->selected as $id) {
            $role = Role::find($id);
            if ($role) {
                $newRole = $role->replicate();
                $newRole->name = $role->name.'-clone-'.Str::random(5);
                $newRole->save();
            }
        }

        $this->clearSelection();
        session()->flash('success', __('agentoom-core::admin.common.cloned_success'));
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.role-index', [
            'roles' => Role::withCount('users', 'permissions')->orderBy('name')->paginate(15),
        ])->layout('agentoom-core::layouts.admin');
    }
}
