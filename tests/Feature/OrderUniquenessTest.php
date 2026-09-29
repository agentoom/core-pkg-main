<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\SeoOrder;
use Agentoom\Core\Models\SeoProject;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderUniquenessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that (seo_project_id, external_id) must be unique.
     */
    public function test_external_id_and_seo_project_id_combination_must_be_unique(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Test Project', 'user_id' => $user->id]);
        $seoProject = SeoProject::create([
            'project_id' => $project->id,
            'domain' => 'example.com',
            'user_id' => $user->id,
            'business_model' => 'ecommerce',
            'primary_goal' => 'sales',
            'site_type' => 'new',
        ]);

        // Create first order
        SeoOrder::create([
            'seo_project_id' => $seoProject->id,
            'external_id' => 'EXT-1',
            'total_amount' => 100,
            'currency' => 'USD',
            'status' => 'completed',
        ]);

        // Try to create another order with same external_id for SAME seo_project
        $this->expectException(QueryException::class);

        SeoOrder::create([
            'seo_project_id' => $seoProject->id,
            'external_id' => 'EXT-1',
            'total_amount' => 150,
            'currency' => 'USD',
            'status' => 'pending',
        ]);
    }

    /**
     * Test that same external id is allowed on different SEO projects.
     */
    public function test_same_external_id_on_different_seo_projects_is_allowed(): void
    {
        $user = User::factory()->create();
        $project1 = Project::create(['name' => 'Project 1', 'user_id' => $user->id]);
        $project2 = Project::create(['name' => 'Project 2', 'user_id' => $user->id]);

        $seoProject1 = SeoProject::create([
            'project_id' => $project1->id,
            'domain' => 'example1.com',
            'user_id' => $user->id,
            'business_model' => 'ecommerce',
            'primary_goal' => 'sales',
            'site_type' => 'new',
        ]);

        $seoProject2 = SeoProject::create([
            'project_id' => $project2->id,
            'domain' => 'example2.com',
            'user_id' => $user->id,
            'business_model' => 'ecommerce',
            'primary_goal' => 'sales',
            'site_type' => 'new',
        ]);

        SeoOrder::create([
            'seo_project_id' => $seoProject1->id,
            'external_id' => 'EXT-COMMON',
            'total_amount' => 100,
            'currency' => 'USD',
        ]);

        // This should NOT fail because seo_project_id is different
        $order2 = SeoOrder::create([
            'seo_project_id' => $seoProject2->id,
            'external_id' => 'EXT-COMMON',
            'total_amount' => 150,
            'currency' => 'USD',
        ]);

        $this->assertDatabaseHas('seo_orders', ['id' => $order2->id]);
    }
}
