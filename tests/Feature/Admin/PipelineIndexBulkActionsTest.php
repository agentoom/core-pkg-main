<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class PipelineIndexBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        Role::where('name', 'superadmin')->exists() ?: Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);
    }

    public function test_it_can_bulk_delete_pipelines(): void
    {
        Pipeline::factory()->count(3)->create();

        $ids = Pipeline::pluck('id')->toArray();

        Livewire::test('agentoom-core::admin.pipeline-index')
            ->set('selected', $ids)
            ->call('bulkDelete')
            ->assertSet('selected', [])
            ->assertSet('selectAll', false);

        $this->assertEquals(0, Pipeline::count());
    }

    public function test_it_can_bulk_clone_pipelines(): void
    {
        Pipeline::factory()->count(2)->create();

        $ids = Pipeline::pluck('id')->toArray();

        Livewire::test('agentoom-core::admin.pipeline-index')
            ->set('selected', $ids)
            ->call('bulkClone')
            ->assertSet('selected', [])
            ->assertSet('selectAll', false);

        $this->assertEquals(4, Pipeline::count());
    }

    public function test_it_can_select_all_and_unselect_all(): void
    {
        Pipeline::factory()->count(15)->create(); // More than 10

        $lw = Livewire::test('agentoom-core::admin.pipeline-index');

        $lw->set('selectAll', true)
            ->assertCount('selected', 10); // Only 10 on page 1

        $lw->set('selectAll', false)
            ->assertCount('selected', 0);
    }

    public function test_pagination_clears_selection(): void
    {
        Pipeline::factory()->count(15)->create();

        Livewire::test('agentoom-core::admin.pipeline-index')
            ->set('selected', [1])
            ->call('gotoPage', 2)
            ->assertSet('selected', []);
    }

    public function test_bulk_export_resets_selection_in_theory_but_download_prevents_ui_update(): void
    {
        Pipeline::factory()->count(2)->create();
        $ids = Pipeline::pluck('id')->toArray();

        $lw = Livewire::test('agentoom-core::admin.pipeline-index')
            ->set('selected', $ids)
            ->call('bulkExport')
            ->assertFileDownloaded();

        // In Livewire test, it might still show as cleared on server side
        $lw->assertSet('selected', []);
    }
}
