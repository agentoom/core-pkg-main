<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'superadmin']);
        Role::create(['name' => 'admin']);
    }

    public function test_superadmin_can_access_all_config_routes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $routes = [
            'admin.users.index',
            'admin.roles.index',
            'admin.import-export',
            'admin.configs.index',
        ];

        foreach ($routes as $route) {
            $this->actingAs($user)->get(route($route))->assertStatus(200);
        }
    }

    public function test_admin_can_access_users_but_not_others(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)->get(route('admin.users.index'))->assertStatus(200);

        $restrictedRoutes = [
            'admin.roles.index',
            'admin.import-export',
            'admin.configs.index',
        ];

        foreach ($restrictedRoutes as $route) {
            $this->actingAs($user)->get(route($route))->assertStatus(403);
        }
    }

    public function test_guest_cannot_access_any_config_routes(): void
    {
        $routes = [
            'admin.users.index',
            'admin.roles.index',
            'admin.import-export',
            'admin.configs.index',
        ];

        foreach ($routes as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_user_with_commands_permission_can_access_commands_route(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'commands.view']);
        $user->givePermissionTo('commands.view');

        $this->actingAs($user)->get(route('admin.configs.commands'))->assertStatus(200);
    }

    public function test_user_with_system_workers_permission_can_access_system_workers_route(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'system-workers.view']);
        $user->givePermissionTo('system-workers.view');

        $this->actingAs($user)->get(route('admin.configs.system-workers'))->assertStatus(200);
    }

    public function test_user_with_skill_presets_permission_can_access_skill_presets_route(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'skill-presets.view']);
        $user->givePermissionTo('skill-presets.view');

        $this->actingAs($user)->get(route('admin.configs.skill_presets'))->assertStatus(200);
    }

    public function test_user_with_import_export_permission_can_access_import_export_route(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'import-export.view']);
        $user->givePermissionTo('import-export.view');

        $this->actingAs($user)->get(route('admin.import-export'))->assertStatus(200);
    }

    public function test_user_without_permission_cannot_access_commands_route(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'commands.view']);

        $this->actingAs($user)->get(route('admin.configs.commands'))->assertStatus(403);
    }

    public function test_user_without_permission_cannot_access_import_export_route(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'import-export.view']);

        $this->actingAs($user)->get(route('admin.import-export'))->assertStatus(403);
    }
}
