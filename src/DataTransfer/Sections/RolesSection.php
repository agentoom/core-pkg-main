<?php

namespace Agentoom\Core\DataTransfer\Sections;

use Agentoom\Core\DataTransfer\AbstractImportExportSection;
use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesSection extends AbstractImportExportSection
{
    public function key(): string
    {
        return 'roles';
    }

    public function group(): string
    {
        return ImportExportRegistry::GROUP_ACCESS;
    }

    public function options(): Collection
    {
        return Role::where('name', '!=', 'superadmin')->get();
    }

    public function importLevel(): int
    {
        return 7;
    }

    /**
     * @param  array<int, int|string>|array{0: 'all'}  $ids
     * @return array<int, array<string, mixed>>
     */
    public function export(array $ids): array
    {
        $query = Role::query()->where('name', '!=', 'superadmin');
        if (($ids = $this->selectedIds($ids)) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function ($role) {
            return [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ];
        })->toArray();
    }

    /**
     * @param  array<int, array<string, mixed>>  $payload
     * @param  array<string, mixed>  $options
     */
    public function import(array $payload, array $options = []): void
    {
        foreach ($payload as $roleData) {
            if (empty($roleData['name']) || $roleData['name'] === 'superadmin') {
                continue;
            }

            $role = Role::firstOrCreate(['name' => $roleData['name']]);

            $permissions = [];
            foreach ($roleData['permissions'] ?? [] as $permissionName) {
                $permissions[] = Permission::firstOrCreate(['name' => $permissionName])->name;
            }

            $role->syncPermissions($permissions);
        }
    }
}
