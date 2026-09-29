<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\Seo\SeoCreateActionTool;
use Agentoom\Core\Ai\Tools\Public\Seo\SeoCreateHypothesisTool;
use Agentoom\Core\Ai\Tools\Public\Seo\SeoCreateProjectTool;
use Agentoom\Core\Ai\Tools\Public\Seo\SeoCreateSnapshotTool;
use Agentoom\Core\Ai\Tools\Public\Seo\SeoCreateStrategyTool;
use Agentoom\Core\Ai\Tools\Public\Seo\SeoEvaluateHypothesisTool;
use Agentoom\Core\Ai\Tools\Public\Seo\SeoGetProjectStateTool;
use Agentoom\Core\Ai\Tools\Public\Seo\SeoReviseStrategyTool;
use Agentoom\Core\Ai\Tools\Public\Seo\SeoStoreMetricsTool;
use Agentoom\Core\Models\SeoAction;
use Agentoom\Core\Models\SeoAssessmentSnapshot;
use Agentoom\Core\Models\SeoHypothesis;
use Agentoom\Core\Models\SeoProject;
use Agentoom\Core\Models\SeoStrategy;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class SeoToolchainTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_full_seo_tool_workflow()
    {
        // 1. Create Project
        $createProjectTool = app(SeoCreateProjectTool::class);
        $projectResponse = $createProjectTool->handle(new Request([
            'domain' => 'example.com',
            'business_model' => 'saas',
            'primary_goal' => 'leads',
            'site_type' => 'existing',
        ]));

        $this->assertStringContainsString('SEO Project created successfully', $projectResponse);
        $this->assertDatabaseHas('seo_projects', ['domain' => 'example.com']);
        $projectId = SeoProject::where('domain', 'example.com')->first()->id;

        // 2. Create Snapshot
        $createSnapshotTool = app(SeoCreateSnapshotTool::class);
        $snapshotResponse = $createSnapshotTool->handle(new Request([
            'seo_project_id' => $projectId,
            'snapshot_type' => 'initial',
            'technical_score' => 85,
            'indexed_pages' => 100,
        ]));

        $this->assertStringContainsString('SEO Snapshot created', $snapshotResponse);
        $this->assertDatabaseHas('seo_assessment_snapshots', ['seo_project_id' => $projectId, 'technical_score' => 85]);
        $snapshotId = SeoAssessmentSnapshot::latest()->first()->id;

        // 3. Get Project State
        $getStateTool = app(SeoGetProjectStateTool::class);
        $stateResponse = $getStateTool->handle(new Request(['seo_project_id' => $projectId]));

        $this->assertStringContainsString('example.com', $stateResponse);
        $this->assertStringContainsString('Tech Score: 85', $stateResponse);
        $this->assertStringContainsString('No strategies found', $stateResponse);

        // 4. Create Strategy
        $createStrategyTool = app(SeoCreateStrategyTool::class);
        $strategyResponse = $createStrategyTool->handle(new Request([
            'seo_project_id' => $projectId,
            'detected_state' => 'low_rankings',
            'strategy_summary' => 'Focus on keyword optimization.',
            'confidence_score' => 0.8,
        ]));

        $this->assertStringContainsString('SEO Strategy created', $strategyResponse);
        $this->assertDatabaseHas('seo_strategies', ['seo_project_id' => $projectId, 'detected_state' => 'low_rankings']);
        $strategyId = SeoStrategy::latest()->first()->id;

        // 5. Create Hypothesis
        $createHypothesisTool = app(SeoCreateHypothesisTool::class);
        $hypothesisResponse = $createHypothesisTool->handle(new Request([
            'strategy_id' => $strategyId,
            'description' => 'Better meta titles will increase CTR.',
            'expected_metric' => 'ctr',
            'expected_change_percent' => 10.0,
            'evaluation_window_days' => 30,
        ]));

        $this->assertStringContainsString('SEO Hypothesis created', $hypothesisResponse);
        $this->assertDatabaseHas('seo_hypotheses', ['strategy_id' => $strategyId, 'expected_metric' => 'ctr']);
        $hypothesisId = SeoHypothesis::latest()->first()->id;

        // 6. Create Action
        $createActionTool = app(SeoCreateActionTool::class);
        $actionResponse = $createActionTool->handle(new Request([
            'seo_project_id' => $projectId,
            'hypothesis_id' => $hypothesisId,
            'action_type' => 'on-page',
            'description' => 'Update meta titles for top 10 pages.',
        ]));

        $this->assertStringContainsString('SEO Action registered', $actionResponse);
        $this->assertDatabaseHas('seo_actions', ['seo_project_id' => $projectId, 'hypothesis_id' => $hypothesisId]);
        $actionId = SeoAction::latest()->first()->id;

        // 7. Store Metrics
        $storeMetricsTool = app(SeoStoreMetricsTool::class);
        $metricsResponse = $storeMetricsTool->handle(new Request([
            'seo_project_id' => $projectId,
            'clicks' => 500,
            'impressions' => 10000,
            'source' => 'gsc',
        ]));

        $this->assertStringContainsString('SEO Metrics stored', $metricsResponse);
        $this->assertDatabaseHas('seo_metrics_timeseries', ['seo_project_id' => $projectId, 'clicks' => 500]);

        // 8. Evaluate Hypothesis
        $evaluateTool = app(SeoEvaluateHypothesisTool::class);
        $evaluationResponse = $evaluateTool->handle(new Request([
            'hypothesis_id' => $hypothesisId,
            'expected_change_percent' => 10.0,
            'actual_change_percent' => 12.5,
            'success' => true,
            'confidence_delta' => 0.05,
        ]));

        $this->assertStringContainsString('SEO Hypothesis evaluated', $evaluationResponse);
        $this->assertDatabaseHas('seo_evaluations', ['hypothesis_id' => $hypothesisId, 'success' => true]);

        // 9. Revise Strategy
        $reviseTool = app(SeoReviseStrategyTool::class);
        // First create a new strategy to revise to
        $newStrategyResult = SeoStrategy::create([
            'seo_project_id' => $projectId,
            'based_on_snapshot_id' => $snapshotId,
            'detected_state' => 'scaling',
            'strategy_summary' => 'Scale content production.',
            'confidence_score' => 0.9,
        ]);
        $newStrategyId = $newStrategyResult->id;

        $revisionResponse = $reviseTool->handle(new Request([
            'seo_project_id' => $projectId,
            'previous_strategy_id' => $strategyId,
            'new_strategy_id' => $newStrategyId,
            'reason' => 'Initial success allows for scaling.',
        ]));

        $this->assertStringContainsString('SEO Strategy revised', $revisionResponse);
        $this->assertDatabaseHas('seo_strategy_revisions', [
            'seo_project_id' => $projectId,
            'previous_strategy_id' => $strategyId,
            'new_strategy_id' => $newStrategyId,
        ]);
    }
}
