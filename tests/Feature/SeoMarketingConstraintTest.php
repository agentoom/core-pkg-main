<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\Seo\SeoCreateMarketingConstraintTool;
use Agentoom\Core\Ai\Tools\Public\Seo\SeoEvolveMarketingConstraintTool;
use Agentoom\Core\Ai\Tools\Public\Seo\SeoGetMarketingConstraintsTool;
use Agentoom\Core\Models\SeoMarketingConstraint;
use Agentoom\Core\Models\SeoProject;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class SeoMarketingConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected SeoProject $seoProject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->seoProject = SeoProject::create([
            'domain' => 'example.com',
            'business_model' => 'saas',
            'primary_goal' => 'leads',
            'site_type' => 'existing',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_marketing_constraint_workflow()
    {
        // 1. Create Constraint
        $createTool = app(SeoCreateMarketingConstraintTool::class);
        $createResponse = $createTool->handle(new Request([
            'seo_project_id' => $this->seoProject->id,
            'name' => 'High-intent Blog Post',
            'goal' => 'Convert visitors to leads',
            'audience' => 'CTOs and IT Managers',
            'message' => 'Our product solves your scaling issues',
            'style' => 'Professional and data-driven',
            'format' => 'Blog Post',
            'variants' => json_encode(['variant A', 'variant B']),
        ]));

        $this->assertStringContainsString('Marketing Constraint created', $createResponse);
        $this->assertDatabaseHas('seo_marketing_constraints', [
            'seo_project_id' => $this->seoProject->id,
            'name' => 'High-intent Blog Post',
            'version' => 1,
        ]);
        $constraint = SeoMarketingConstraint::first();
        $this->assertEquals(['variant A', 'variant B'], $constraint->variants);

        // 2. Get Constraints
        $getTool = app(SeoGetMarketingConstraintsTool::class);
        $getResponse = $getTool->handle(new Request([
            'seo_project_id' => $this->seoProject->id,
        ]));

        $this->assertStringContainsString('High-intent Blog Post', $getResponse);
        $this->assertStringContainsString('Version: 1', $getResponse);

        // 3. Evolve Constraint
        $evolveTool = app(SeoEvolveMarketingConstraintTool::class);
        $evolveResponse = $evolveTool->handle(new Request([
            'constraint_id' => $constraint->id,
            'message' => 'New message for version 2',
        ]));

        $this->assertStringContainsString('New version of Marketing Constraint created', $evolveResponse);
        $this->assertDatabaseHas('seo_marketing_constraints', [
            'based_on_constraint_id' => $constraint->id,
            'message' => 'New message for version 2',
            'version' => 2,
        ]);

        $newConstraint = SeoMarketingConstraint::where('version', 2)->first();
        // Check that other fields were copied
        $this->assertEquals('High-intent Blog Post', $newConstraint->name);
        $this->assertEquals('Blog Post', $newConstraint->format);
    }

    public function test_global_marketing_constraints()
    {
        $createTool = app(SeoCreateMarketingConstraintTool::class);
        $createTool->handle(new Request([
            'name' => 'Global Style Guide',
            'goal' => 'Consistency',
            'audience' => 'All',
            'message' => 'Standard branding',
            'style' => 'Clean',
            'format' => 'Mixed',
        ]));

        $this->assertDatabaseHas('seo_marketing_constraints', [
            'seo_project_id' => null,
            'name' => 'Global Style Guide',
        ]);

        $getTool = app(SeoGetMarketingConstraintsTool::class);
        $getResponse = $getTool->handle(new Request([
            'seo_project_id' => $this->seoProject->id,
        ]));

        $this->assertStringContainsString('Global Style Guide', $getResponse);
    }
}
