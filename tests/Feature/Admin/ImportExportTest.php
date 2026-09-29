<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\ChatWidget\Models\AgentWidget;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function createUserWithRole(string $roleName, array $permissions = []): User
    {
        $role = Role::create(['name' => $roleName]);

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission));
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function offeredSections(User $user): array
    {
        return collect(Livewire::actingAs($user)
            ->test('agentoom-core::admin.import-export')
            ->viewData('groups'))
            ->flatMap(fn (array $group): array => $group['sections'])
            ->values()
            ->all();
    }

    public function test_import_export_page_renders_successfully(): void
    {
        $user = $this->createUserWithRole('superadmin');

        $response = $this->actingAs($user)->get(route('admin.import-export'));

        $response->assertStatus(200);
        $response->assertSeeLivewire('agentoom-core::admin.import-export');
    }

    public function test_config_page_renders_successfully(): void
    {
        $user = $this->createUserWithRole('superadmin');

        $response = $this->actingAs($user)->get(route('admin.configs.index'));

        $response->assertStatus(200);
        $response->assertSeeLivewire('agentoom-core::admin.config-index');
    }

    public function test_import_fails_with_invalid_json(): void
    {
        $user = $this->createUserWithRole('superadmin');

        Livewire::actingAs($user)
            ->test('agentoom-core::admin.import-export')
            ->call('import')
            ->assertHasErrors(['import' => __('agentoom-core::admin.import_export.import.invalid_json')]);
    }

    public function test_export_fails_if_no_selection(): void
    {
        $user = $this->createUserWithRole('superadmin');

        Livewire::actingAs($user)
            ->test('agentoom-core::admin.import-export')
            ->call('export')
            ->assertHasErrors(['export' => __('agentoom-core::admin.import_export.export.select_at_least_one')]);
    }

    public function test_offered_sections_come_from_the_registry_including_packages(): void
    {
        $keys = array_column($this->offeredSections($this->createUserWithRole('superadmin')), 'key');

        $this->assertContains('configurations', $keys);
        $this->assertContains('agents', $keys);
        // Sections contributed by optional packages, unknown to core.
        $this->assertContains('widgets', $keys);
        $this->assertContains('accessibility_widgets', $keys);
    }

    public function test_sections_are_limited_by_permission(): void
    {
        $keys = array_column($this->offeredSections($this->createUserWithRole('limited', ['widgets.view'])), 'key');

        $this->assertSame(['widgets'], $keys);
    }

    public function test_select_all_selects_the_whole_section(): void
    {
        $user = $this->createUserWithRole('superadmin');

        Livewire::actingAs($user)
            ->test('agentoom-core::admin.import-export')
            ->set('selectAll.widgets', true)
            ->assertSet('exportOptions.widgets', ['all'])
            ->set('selectAll.widgets', false)
            ->assertSet('exportOptions.widgets', []);
    }

    public function test_export_downloads_only_the_sections_the_user_may_use(): void
    {
        $user = $this->createUserWithRole('limited', ['widgets.view']);
        $agent = Agent::factory()->create();
        $widget = AgentWidget::factory()->create(['agent_id' => $agent->id]);

        $test = Livewire::actingAs($user)
            ->test('agentoom-core::admin.import-export')
            ->set('exportOptions.widgets', [$widget->id])
            ->set('exportOptions.roles', [1]) // not permitted for this user
            ->call('export')
            ->assertFileDownloaded();

        $data = json_decode(base64_decode(data_get($test->effects, 'download.content')), true);

        $this->assertMatchesRegularExpression(
            '/^export-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.json$/',
            data_get($test->effects, 'download.name')
        );
        $this->assertSame($widget->slug, $data['widgets'][0]['slug']);
        $this->assertArrayNotHasKey('roles', $data);
        // The exported widget carries the agent it needs, so a restore is complete.
        $this->assertSame($agent->slug, $data['agents'][0]['slug']);
    }
}
