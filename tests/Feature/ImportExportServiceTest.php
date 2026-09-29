<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Models\SkillPreset;
use Agentoom\Core\Models\Tool;
use Agentoom\Core\Services\ExportService;
use Agentoom\Core\Services\ImportService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ImportExportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_export_and_import_agents(): void
    {
        // 1. Create an agent
        $agent = Agent::create([
            'name' => 'Test Agent',
            'slug' => 'test-agent',
            'description' => 'Test Description',
            'instructions' => 'Test Instructions',
            'config' => ['foo' => 'bar'],
        ]);

        $exportService = app(ExportService::class);
        $data = $exportService->export(['agents' => [$agent->id]]);

        $this->assertCount(1, $data['agents']);
        $this->assertEquals('Test Agent', $data['agents'][0]['name']);

        // 2. Delete the agent
        $agent->delete();
        $this->assertDatabaseMissing('agents', ['name' => 'Test Agent']);

        // 3. Import back
        $importService = app(ImportService::class);
        $importService->import($data);

        $this->assertDatabaseHas('agents', ['name' => 'Test Agent', 'slug' => 'test-agent']);
    }

    public function test_can_export_and_import_skills(): void
    {
        $skill = AgentSkill::create([
            'name' => 'Test Skill',
            'slug' => 'test-skill',
            'description' => 'Test Skill Description',
            'instructions' => 'Test Skill Instructions',
            'musts' => ['must 1'],
            'donts' => ['dont 1'],
            'if_this_then' => [['if' => 'foo', 'then' => 'bar']],
            'tools' => ['test-tool'],
            'is_active' => true,
        ]);

        $exportService = app(ExportService::class);
        $data = $exportService->export(['skills' => [$skill->id]]);

        $this->assertCount(1, $data['skills']);

        $skill->delete();
        $this->assertDatabaseMissing('agent_skills', ['name' => 'Test Skill']);

        $importService = app(ImportService::class);
        $importService->import($data);

        $this->assertDatabaseHas('agent_skills', ['name' => 'Test Skill', 'slug' => 'test-skill']);
    }

    public function test_can_export_and_import_configurations(): void
    {
        Configuration::setValue('test_key', 'test_value');
        Configuration::setValue('test_json', ['foo' => 'bar']);

        $config1 = Configuration::where('key', 'test_key')->first();
        $config2 = Configuration::where('key', 'test_json')->first();

        $exportService = app(ExportService::class);
        $data = $exportService->export(['configurations' => [$config1->id, $config2->id]]);

        $this->assertCount(2, $data['configurations']);

        Configuration::where('key', 'test_key')->delete();
        Configuration::where('key', 'test_json')->delete();

        $this->assertDatabaseMissing('configurations', ['key' => 'test_key']);
        $this->assertDatabaseMissing('configurations', ['key' => 'test_json']);

        $importService = app(ImportService::class);
        $importService->import($data);

        $this->assertDatabaseHas('configurations', ['key' => 'test_key', 'value' => 'test_value']);
        $this->assertEquals(['foo' => 'bar'], Configuration::getValue('test_json'));
    }

    public function test_can_export_and_import_pipelines_with_custom_steps(): void
    {
        // 1. Create a pipeline with a custom step
        $pipeline = Pipeline::create([
            'name' => 'Custom Pipeline',
            'type' => 'endpoint',
            'user_id' => $this->user->id,
        ]);

        $pipeline->steps()->create([
            'order' => 0,
            'name' => 'Custom Step',
            'class' => 'App\Tools\MyTool',
            'method' => 'run',
            'instructions_override' => 'some instructions',
            'pass_output_as_input' => true,
        ]);

        $exportService = app(ExportService::class);
        $data = $exportService->export(['pipelines' => [$pipeline->id]]);

        $this->assertCount(1, $data['pipelines']);
        $this->assertCount(1, $data['pipelines'][0]['steps']);
        $this->assertEquals('Custom Step', $data['pipelines'][0]['steps'][0]['name']);

        // These will likely fail currently
        $this->assertEquals('App\Tools\MyTool', $data['pipelines'][0]['steps'][0]['class'] ?? null);
        $this->assertEquals('run', $data['pipelines'][0]['steps'][0]['method'] ?? null);

        // 2. Delete and import
        $pipeline->delete();

        $importService = app(ImportService::class);
        $importService->import($data);

        $this->assertDatabaseHas('pipelines', ['name' => 'Custom Pipeline']);
        $importedPipeline = Pipeline::where('name', 'Custom Pipeline')->first();
        $this->assertCount(1, $importedPipeline->steps);
        $this->assertEquals('App\Tools\MyTool', $importedPipeline->steps[0]->class);
        $this->assertEquals('run', $importedPipeline->steps[0]->method);
    }

    public function test_can_export_and_import_project_resource_types(): void
    {
        $project = Project::factory()->create(['name' => 'Test Project']);
        $type = ResourceType::create([
            'name' => 'Strategy & Roadmap',
            'project_id' => $project->id,
            'description' => 'Test Description',
            'config' => ['foo' => 'bar'],
        ]);

        $exportService = app(ExportService::class);
        $data = $exportService->export(['resource_types' => [$type->id]]);

        $this->assertCount(1, $data['resource_types']);

        $type->delete();
        $importService = app(ImportService::class);
        $importService->import($data);

        $this->assertDatabaseHas('resource_types', [
            'project_id' => $project->id,
            'description' => 'Test Description',
        ]);
        $importedType = ResourceType::where('project_id', $project->id)->first();
        $this->assertEquals('Strategy & Roadmap', $importedType->name_translated);
    }

    public function test_agent_export_includes_skills_tools_and_model_names(): void
    {
        // 1. Setup
        $provider = Provider::create(['name' => 'openai', 'api_key' => 'test-key', 'url' => 'https://api.openai.com/v1']);
        $model = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'gpt-4o',
            'is_active' => true,
            'model_features' => [],
        ]);

        $skill = AgentSkill::create([
            'name' => 'Test Skill for Agent',
            'slug' => 'test-skill-agent',
            'is_active' => true,
        ]);

        $tool = Tool::create([
            'name' => 'Test Tool for Agent',
            'class_name' => 'Agentoom\Core\Ai\Tools\Public\ManageResourcesTool',
            'is_active' => true,
            'description' => 'Test Tool Description',
        ]);

        $agent = Agent::create([
            'name' => 'Test Agent with Skills',
            'slug' => 'test-agent-skills',
            'config' => [
                'provider_id' => $provider->id,
                'provider_model_id' => $model->id,
            ],
        ]);

        $agent->skills()->attach($skill->id);
        $agent->tools()->attach($tool->id);

        // 2. Export
        $exportService = app(ExportService::class);
        $data = $exportService->export(['agents' => [$agent->id]]);

        $agentData = $data['agents'][0];

        // 3. Assertions for Export
        $this->assertEquals('openai', $agentData['config']['provider_id']);
        $this->assertEquals('gpt-4o', $agentData['config']['provider_model_id']);
        $this->assertContains('test-skill-agent', $agentData['skills']);
        $this->assertContains('Agentoom\Core\Ai\Tools\Public\ManageResourcesTool', $agentData['tools']);

        // 4. Delete and Import
        $agent->delete();

        $importService = app(ImportService::class);
        $importService->import($data);

        // 5. Assertions for Import
        $importedAgent = Agent::where('slug', 'test-agent-skills')->first();
        $this->assertNotNull($importedAgent);

        // Check relationships
        $this->assertTrue($importedAgent->skills->contains('slug', 'test-skill-agent'));
        $this->assertTrue($importedAgent->tools->contains('class_name', 'Agentoom\Core\Ai\Tools\Public\ManageResourcesTool'));

        // Check config - they should be resolved back to IDs for UI compatibility
        $this->assertEquals($provider->id, $importedAgent->config['provider_id']);
        $this->assertEquals($model->id, $importedAgent->config['provider_model_id']);
    }

    public function test_skill_preset_export_includes_resolved_skills_and_tools(): void
    {
        $skill = AgentSkill::create([
            'name' => 'Test Skill for Preset',
            'slug' => 'test-skill-preset',
            'is_active' => true,
        ]);

        $tool = Tool::create([
            'name' => 'Test Tool for Preset',
            'class_name' => 'Agentoom\Core\Ai\Tools\Public\ProjectRepositoryTool',
            'is_active' => true,
            'description' => 'Test Tool 2 Description',
        ]);

        $preset = SkillPreset::create([
            'name' => 'Test Preset',
            'skills' => [$skill->id],
            'tools' => [$tool->id],
            'is_active' => true,
        ]);

        $exportService = app(ExportService::class);
        $data = $exportService->export(['skill_presets' => [$preset->id]]);

        $presetData = $data['skill_presets'][0];
        $this->assertContains('test-skill-preset', $presetData['skills']);
        $this->assertContains('Agentoom\Core\Ai\Tools\Public\ProjectRepositoryTool', $presetData['tools']);

        $preset->delete();
        $importService = app(ImportService::class);
        $importService->import($data);

        $importedPreset = SkillPreset::where('name', 'Test Preset')->first();
        $this->assertNotNull($importedPreset);
        $this->assertContains($skill->id, $importedPreset->skills);
        $this->assertContains($tool->id, $importedPreset->tools);
    }
}
