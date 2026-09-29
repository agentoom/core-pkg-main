<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\SeoProjectIndex;
use Agentoom\Core\Models\SeoProject;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class SeoProjectIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'superadmin']);
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);
    }

    public function test_seo_project_index_page_is_accessible(): void
    {
        $response = $this->get(route('admin.seo-projects.index'));

        $response->assertStatus(200);
    }

    public function test_it_lists_seo_projects(): void
    {
        $project = SeoProject::create([
            'domain' => 'example.com',
            'business_model' => 'ecommerce',
            'primary_goal' => 'sales',
            'site_type' => 'new',
            'user_id' => auth()->id(),
        ]);

        Livewire::test(SeoProjectIndex::class)
            ->assertSee('example.com')
            ->assertSee('Ecommerce')
            ->assertSee('New');
    }

    public function test_it_can_delete_seo_project(): void
    {
        $project = SeoProject::create([
            'domain' => 'delete-me.com',
            'business_model' => 'saas',
            'primary_goal' => 'leads',
            'site_type' => 'existing',
            'user_id' => auth()->id(),
        ]);

        Livewire::test(SeoProjectIndex::class)
            ->call('delete', $project->id);

        $this->assertDatabaseMissing('seo_projects', ['id' => $project->id]);
    }

    public function test_wizard_modal_is_present_on_index_page(): void
    {
        $response = $this->get(route('admin.seo-projects.index'));

        $response->assertStatus(200);
        $response->assertSee(__('agentoom-core::admin.websites.wizard_subtitle'));
    }
}
