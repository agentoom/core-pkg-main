<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\Seo\SeoCreateSnapshotTool;
use Agentoom\Core\Ai\Tools\Public\Seo\SeoCreateStrategyTool;
use Agentoom\Core\Ai\Tools\Public\Seo\SeoGetProjectStateTool;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class SeoToolValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_seo_create_snapshot_tool_handles_invalid_project_id_gracefully()
    {
        $tool = app(SeoCreateSnapshotTool::class);

        // ID 999 does not exist
        $response = $tool->handle(new Request([
            'seo_project_id' => 999,
            'snapshot_type' => 'initial',
        ]));

        $this->assertStringContainsString('Error creating SEO Snapshot', $response);
        $this->assertStringContainsString('SEO Project with ID 999 not found', $response);
    }

    public function test_seo_get_project_state_tool_handles_invalid_project_id_gracefully()
    {
        $tool = app(SeoGetProjectStateTool::class);
        $response = $tool->handle(new Request(['seo_project_id' => 999]));

        $this->assertStringContainsString('Error retrieving SEO project state', $response);
    }

    public function test_seo_create_strategy_tool_handles_invalid_project_id_gracefully()
    {
        $tool = app(SeoCreateStrategyTool::class);
        $response = $tool->handle(new Request([
            'seo_project_id' => 999,
            'detected_state' => 'scaling',
            'strategy_summary' => 'Test',
        ]));

        $this->assertStringContainsString('Error creating SEO Strategy', $response);
    }
}
