<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Contracts\HumanIntervention;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * "Allow Human Intervention" is a live-support capability. It must not be
 * offered on an instance that cannot honour it — and it must appear as soon as
 * a package binds the capability.
 */
class AgentHumanInterventionFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'superadmin']);
        $admin = Role::firstOrCreate(['name' => 'admin']);

        foreach (['agents.view', 'agents.create'] as $permission) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
    }

    public function test_the_capability_defaults_to_unsupported(): void
    {
        $this->assertFalse(app(HumanIntervention::class)->isSupported());
        $this->assertNull(app(HumanIntervention::class)->toolClass());
    }

    public function test_the_field_is_hidden_when_no_package_provides_the_capability(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.agents.create'));

        $response->assertOk();
        $response->assertDontSee(__('agentoom-core::admin.agents.allow_human_intervention'));
    }

    public function test_the_field_appears_once_a_package_provides_the_capability(): void
    {
        $this->mock(HumanIntervention::class, function ($mock) {
            $mock->shouldReceive('isSupported')->andReturn(true);
        });

        $response = $this->actingAs($this->admin())->get(route('admin.agents.create'));

        $response->assertOk();
        $response->assertSee(__('agentoom-core::admin.agents.allow_human_intervention'));
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }
}
