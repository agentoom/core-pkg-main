<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\SkillPreset;
use Agentoom\Core\Models\User;
use Agentoom\Core\Services\ImportService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ImportHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email' => 'superadmin@unusualdope.com']);
    }

    public function test_import_projects_before_agents(): void
    {
        // Even if agents are before projects in the JSON, they should be imported after
        $data = [
            'agents' => [
                [
                    'name' => 'Agent 1',
                    'slug' => 'agent-1',
                    'project_name' => 'Project A',
                ],
            ],
            'projects' => [
                [
                    'name' => 'Project A',
                    'description' => 'Description A',
                ],
            ],
        ];

        $importService = app(ImportService::class);
        $importService->import($data);

        $this->assertDatabaseHas('projects', ['name' => 'Project A']);
        $project = Project::where('name', 'Project A')->first();

        $this->assertDatabaseHas('agents', [
            'name' => 'Agent 1',
            'project_id' => $project->id,
        ]);
    }

    public function test_import_agents_before_pipelines(): void
    {
        $data = [
            'pipelines' => [
                [
                    'name' => 'Pipeline A',
                    'project_name' => 'Project A',
                    'steps' => [
                        [
                            'name' => 'Step 1',
                            'agent_slug' => 'agent-1',
                            'order' => 1,
                        ],
                    ],
                ],
            ],
            'agents' => [
                [
                    'name' => 'Agent 1',
                    'slug' => 'agent-1',
                    'project_name' => 'Project A',
                ],
            ],
            'projects' => [
                [
                    'name' => 'Project A',
                ],
            ],
        ];

        $importService = app(ImportService::class);
        $importService->import($data);

        $agent = Agent::where('slug', 'agent-1')->first();
        $pipeline = Pipeline::where('name', 'Pipeline A')->first();

        $this->assertNotNull($pipeline);
        $this->assertCount(1, $pipeline->steps);
        $this->assertEquals($agent->id, $pipeline->steps[0]->agent_id);
    }

    public function test_import_skill_presets(): void
    {
        $data = [
            'skill_presets' => [
                [
                    'name' => 'Preset A',
                    'description' => 'Desc A',
                    'agents' => ['agent-1'],
                ],
            ],
            'agents' => [
                [
                    'name' => 'Agent 1',
                    'slug' => 'agent-1',
                ],
            ],
        ];

        $importService = app(ImportService::class);
        $importService->import($data);

        $this->assertDatabaseHas('skill_presets', ['name' => 'Preset A']);
        $preset = SkillPreset::where('name', 'Preset A')->first();
        $agent = Agent::where('slug', 'agent-1')->first();

        $this->assertTrue($preset->agents->contains($agent->id));
    }
}
