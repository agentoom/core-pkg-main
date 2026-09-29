<?php

namespace Database\Seeders;

use Agentoom\Core\Services\PermissionRegistry;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $superadminRole = Role::firstOrCreate(['name' => 'superadmin']);

        $allPermissions = app(PermissionRegistry::class)->permissions();

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $adminRole->syncPermissions($allPermissions);
        // superadmin doesn't strictly need them due to Gate::before, but it doesn't hurt
        $superadminRole->syncPermissions($allPermissions);
    }
}
