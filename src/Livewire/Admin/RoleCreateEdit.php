<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Services\PermissionRegistry;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleCreateEdit extends Component
{
    public ?Role $role = null;

    public string $name = '';

    public array $selectedPermissions = [];

    public bool $is_editing = false;

    public function mount(?Role $role = null): void
    {
        $this->role = $role;
        $this->is_editing = $role !== null;
        if ($role) {
            $this->name = $role->name;
            $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        }
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255|unique:roles,name,'.($this->role->id ?? 'NULL'),
            'selectedPermissions' => 'array',
        ];

        $this->validate($rules);

        if ($this->role && $this->role->name === 'superadmin') {
            $this->name = 'superadmin'; // Ensure name doesn't change
            // For superadmin, we don't sync permissions, they always have everything via Gate::before
            session()->flash('success', __('agentoom-core::admin.roles.updated_success'));

            return $this->redirect(route('admin.roles.index'), navigate: true);
        }

        // Ensure permissions exist
        foreach ($this->selectedPermissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName]);
        }

        if ($this->role) {
            $this->role->update(['name' => $this->name]);
            $this->role->syncPermissions($this->selectedPermissions);
            session()->flash('success', __('agentoom-core::admin.roles.updated_success'));
        } else {
            $role = Role::create(['name' => $this->name]);
            $role->syncPermissions($this->selectedPermissions);
            session()->flash('success', __('agentoom-core::admin.roles.created_success'));
        }

        return $this->redirect(route('admin.roles.index'), navigate: true);
    }

    /**
     * Every permission group contributed by core and installed packages.
     *
     * @return array<string, array<int, string>>
     */
    protected function permissionGroups(): array
    {
        return app(PermissionRegistry::class)->all();
    }

    public function selectAll(): void
    {
        $allPermissions = [];
        foreach ($this->permissionGroups() as $group) {
            $allPermissions = array_merge($allPermissions, $group);
        }
        $this->selectedPermissions = array_values(array_unique($allPermissions));
    }

    public function deselectAll(): void
    {
        $this->selectedPermissions = [];
    }

    public function selectAllGroup(string $groupName): void
    {
        $groupPermissions = $this->permissionGroups()[$groupName] ?? [];
        $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $groupPermissions)));
    }

    public function deselectAllGroup(string $groupName): void
    {
        $groupPermissions = $this->permissionGroups()[$groupName] ?? [];
        $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $groupPermissions));
    }

    public function render()
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->hasRole('superadmin');

        if ($isSuperAdmin) {
            $visibleGroups = $this->permissionGroups();
        } else {
            $userPermissions = $user ? $user->getAllPermissions()->pluck('name')->toArray() : [];
            $visibleGroups = array_filter($this->permissionGroups(), function (array $groupPermissions) use ($userPermissions) {
                return count(array_intersect($groupPermissions, $userPermissions)) > 0;
            });
        }

        return view('agentoom-core::livewire.admin.role-create-edit', [
            'permissionGroups' => $visibleGroups,
        ])->layout('agentoom-core::layouts.admin');
    }
}
