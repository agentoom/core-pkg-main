<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\Public\ManageAgentTool;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\AgentTaskPermission;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Models\Tool;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class ManageAgentToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_agent_with_ui_prompt_structure_and_assignments(): void
    {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $provider = Provider::create([
            'name' => 'openai',
            'api_key' => 'test-key',
            'url' => 'https://api.openai.com/v1',
        ]);
        $providerModel = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'gpt-4o-mini',
            'model_features' => [],
            'is_active' => true,
        ]);

        Configuration::create(['key' => 'ai_provider', 'value' => (string) $provider->id]);
        Configuration::create(['key' => 'ai_model', 'value' => (string) $providerModel->id]);

        $manualTool = Tool::create([
            'name' => 'Manual Tool',
            'class_name' => 'Tests\\ManualTool',
            'description' => 'Manual test tool',
            'is_available' => true,
        ]);

        $skillTool = Tool::create([
            'name' => 'Skill Tool',
            'class_name' => 'Tests\\SkillTool',
            'description' => 'Skill test tool',
            'is_available' => true,
        ]);

        $skill = AgentSkill::create([
            'name' => 'Planning Skill',
            'slug' => 'planning-skill',
            'instructions' => 'Plan correctly',
            'tools' => ['Tests\\SkillTool'],
            'is_active' => true,
        ]);

        $tool = new ManageAgentTool;

        $response = $tool->handle(new Request([
            'action' => 'create_agent',
            'name' => 'Prompt Architect',
            'role' => 'You are a prompt architect.',
            'objective' => 'Create clear prompts.',
            'instructions' => 'Always remove ambiguity.',
            'operational_protocol' => 'Follow a checklist.',
            'prioritize' => ['Clarity', 'Correctness'],
            'constraints' => ['Do not hallucinate.'],
            'conditional_rules' => [
                ['condition' => 'missing context', 'if_true' => 'ask questions', 'if_false' => 'continue'],
            ],
            'error_handling' => 'Explain uncertainty.',
            'output_format' => ['Bullet points'],
            'examples' => [
                ['input' => 'Task', 'output' => 'Plan'],
            ],
            'tool_ids' => [$manualTool->id],
            'skill_ids' => [$skill->id],
        ]));

        $this->assertStringContainsString('Successfully created agent', $response);
        $this->assertStringContainsString('[ROLE]', $response);
        $this->assertStringContainsString('[INSTRUCTIONS]', $response);
        $this->assertStringContainsString('[PRIORITIZE]', $response);

        $agent = Agent::query()->where('slug', 'prompt-architect')->first();
        $this->assertNotNull($agent);
        $this->assertSame('You are a prompt architect.', $agent->role);
        $this->assertSame('Always remove ambiguity.', $agent->instructions);
        $this->assertSame($provider->id, $agent->config['provider_id'] ?? null);
        $this->assertSame($providerModel->id, $agent->config['provider_model_id'] ?? null);

        $this->assertEqualsCanonicalizing(
            [$manualTool->id, $skillTool->id],
            $agent->tools()->pluck('tools.id')->map(fn ($id) => (int) $id)->toArray()
        );

        $this->assertSame([$skill->id], $agent->skills()->pluck('agent_skills.id')->map(fn ($id) => (int) $id)->toArray());
    }

    public function test_it_rejects_missing_required_prompt_sections(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tool = new ManageAgentTool;

        $response = $tool->handle(new Request([
            'action' => 'create_agent',
            'name' => 'Invalid Agent',
            'instructions' => 'Only instructions provided',
        ]));

        $this->assertStringContainsString('"role" is required', $response);
        $this->assertDatabaseMissing('agents', ['name' => 'Invalid Agent']);
    }

    public function test_it_rejects_invalid_tool_and_skill_references(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tool = new ManageAgentTool;

        $response = $tool->handle(new Request([
            'action' => 'create_agent',
            'name' => 'Invalid References Agent',
            'role' => 'role',
            'instructions' => 'instructions',
            'tool_ids' => [999999],
            'skill_ids' => [999999],
        ]));

        $this->assertStringContainsString('tool_ids', $response);
        $this->assertDatabaseMissing('agents', ['name' => 'Invalid References Agent']);
    }

    public function test_it_discovers_available_agents(): void
    {
        $project = Project::factory()->create(['name' => 'Prompting']);

        $firstAgent = Agent::create([
            'user_id' => User::factory()->create()->id,
            'project_id' => $project->id,
            'name' => 'Architect Agent',
            'slug' => 'architect-agent',
            'description' => 'Creates structured prompts',
            'role' => 'Role',
            'instructions' => 'Instructions',
            'is_active' => true,
            'is_public' => true,
        ]);

        $secondAgent = Agent::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Reviewer Agent',
            'slug' => 'reviewer-agent',
            'role' => 'Role',
            'instructions' => 'Instructions',
            'is_active' => false,
            'is_public' => false,
        ]);

        $tool = new ManageAgentTool;

        $response = $tool->handle(new Request([
            'action' => 'discover_agents',
        ]));

        $this->assertStringContainsString('AVAILABLE AGENTS', $response);
        $this->assertStringContainsString((string) $firstAgent->id, $response);
        $this->assertStringContainsString('Architect Agent', $response);
        $this->assertStringContainsString('architect-agent', $response);
        $this->assertStringContainsString('Project ID: '.$project->id, $response);
        $this->assertStringContainsString('Creates structured prompts', $response);
        $this->assertStringContainsString((string) $secondAgent->id, $response);
        $this->assertStringContainsString('Reviewer Agent', $response);
        $this->assertStringContainsString('Active: no | Public: no', $response);
    }

    public function test_it_returns_empty_message_when_no_agents_exist(): void
    {
        $tool = new ManageAgentTool;

        $response = $tool->handle(new Request([
            'action' => 'discover_agents',
        ]));

        $this->assertSame('No agents found.', $response);
    }

    public function test_it_discovers_current_projects(): void
    {
        $projectA = Project::factory()->create([
            'name' => 'Alpha Project',
            'description' => 'Primary project context',
        ]);
        $projectB = Project::factory()->create([
            'name' => 'Beta Project',
            'description' => null,
        ]);

        $tool = new ManageAgentTool;

        $response = $tool->handle(new Request([
            'action' => 'discover_projects',
        ]));

        $this->assertStringContainsString('AVAILABLE PROJECTS', $response);
        $this->assertStringContainsString((string) $projectA->id, $response);
        $this->assertStringContainsString('Alpha Project', $response);
        $this->assertStringContainsString('Primary project context', $response);
        $this->assertStringContainsString((string) $projectB->id, $response);
        $this->assertStringContainsString('Beta Project', $response);
    }

    public function test_it_returns_empty_message_when_no_projects_exist(): void
    {
        $tool = new ManageAgentTool;

        $response = $tool->handle(new Request([
            'action' => 'discover_projects',
        ]));

        $this->assertSame('No projects found.', $response);
    }

    public function test_it_updates_existing_agent_and_syncs_assignments(): void
    {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $provider = Provider::create([
            'name' => 'openai',
            'api_key' => 'test-key',
            'url' => 'https://api.openai.com/v1',
        ]);
        $providerModel = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'gpt-4o-mini',
            'model_features' => [],
            'is_active' => true,
        ]);

        $manualTool = Tool::create([
            'name' => 'Manual Tool',
            'class_name' => 'Tests\\ManualTool',
            'description' => 'Manual test tool',
            'is_available' => true,
        ]);
        $skillTool = Tool::create([
            'name' => 'Skill Tool',
            'class_name' => 'Tests\\SkillTool',
            'description' => 'Skill test tool',
            'is_available' => true,
        ]);

        $skill = AgentSkill::create([
            'name' => 'Planning Skill',
            'slug' => 'planning-skill',
            'instructions' => 'Plan correctly',
            'tools' => ['Tests\\SkillTool'],
            'is_active' => true,
        ]);

        $agent = Agent::create([
            'user_id' => $user->id,
            'name' => 'Original Agent',
            'slug' => 'original-agent',
            'role' => 'Original role',
            'instructions' => 'Original instructions',
            'config' => [],
            'prompt_settings' => ['parts' => ['language_policy' => true], 'tools' => []],
        ]);

        $tool = new ManageAgentTool;

        $response = $tool->handle(new Request([
            'action' => 'update_agent',
            'agent_id' => $agent->id,
            'name' => 'Updated Agent',
            'slug' => 'updated-agent',
            'role' => 'Updated role',
            'instructions' => 'Updated instructions',
            'provider_id' => $provider->id,
            'provider_model_id' => $providerModel->id,
            'tool_ids' => [$manualTool->id],
            'skill_ids' => [$skill->id],
        ]));

        $this->assertStringContainsString('Successfully updated agent', $response);

        $agent->refresh();
        $this->assertSame('Updated Agent', $agent->name);
        $this->assertSame('updated-agent', $agent->slug);
        $this->assertSame('Updated role', $agent->role);
        $this->assertSame('Updated instructions', $agent->instructions);
        $this->assertSame($provider->id, $agent->config['provider_id'] ?? null);
        $this->assertSame($providerModel->id, $agent->config['provider_model_id'] ?? null);

        $this->assertEqualsCanonicalizing(
            [$manualTool->id, $skillTool->id],
            $agent->tools()->pluck('tools.id')->map(fn ($id) => (int) $id)->toArray()
        );
        $this->assertSame([$skill->id], $agent->skills()->pluck('agent_skills.id')->map(fn ($id) => (int) $id)->toArray());
    }

    public function test_it_rejects_update_when_agent_does_not_exist(): void
    {
        $tool = new ManageAgentTool;

        $response = $tool->handle(new Request([
            'action' => 'update_agent',
            'agent_id' => 999999,
            'name' => 'Updated Agent',
            'role' => 'Updated role',
            'instructions' => 'Updated instructions',
        ]));

        $this->assertStringContainsString('does not exist', $response);
    }

    public function test_it_creates_or_updates_task_permission_between_agents(): void
    {
        $owner = Agent::factory()->create();
        $connected = Agent::factory()->create();

        $tool = new ManageAgentTool;

        $response = $tool->handle(new Request([
            'action' => 'manage_agent_task_permission',
            'agent_id' => $owner->id,
            'connected_agent_id' => $connected->id,
            'can_assign' => true,
            'can_view' => false,
            'assignment_explanation' => 'Handle SEO content generation tasks only.',
        ]));

        $this->assertStringContainsString('Successfully saved task permission', $response);
        $this->assertDatabaseHas('agent_task_permissions', [
            'agent_id' => $owner->id,
            'permitted_agent_id' => $connected->id,
            'can_assign' => true,
            'can_view' => false,
        ]);

        $response = $tool->handle(new Request([
            'action' => 'manage_agent_task_permission',
            'agent_id' => $owner->id,
            'connected_agent_id' => $connected->id,
            'can_assign' => false,
            'can_view' => true,
            'assignment_explanation' => 'Read-only analytics support.',
        ]));

        $this->assertStringContainsString('Successfully saved task permission', $response);
        $permission = AgentTaskPermission::query()
            ->where('agent_id', $owner->id)
            ->where('permitted_agent_id', $connected->id)
            ->first();

        $this->assertNotNull($permission);
        $this->assertFalse($permission->can_assign);
        $this->assertTrue($permission->can_view);
        $this->assertSame('Read-only analytics support.', $permission->assignment_explanation);
    }

    public function test_it_rejects_invalid_task_permission_inputs(): void
    {
        $owner = Agent::factory()->create();
        $connected = Agent::factory()->create();

        $tool = new ManageAgentTool;

        $response = $tool->handle(new Request([
            'action' => 'manage_agent_task_permission',
            'agent_id' => $owner->id,
            'connected_agent_id' => $connected->id,
            'can_assign' => false,
            'can_view' => false,
            'assignment_explanation' => 'Missing permission flags.',
        ]));

        $this->assertStringContainsString('At least one permission must be true', $response);

        $response = $tool->handle(new Request([
            'action' => 'manage_agent_task_permission',
            'agent_id' => $owner->id,
            'connected_agent_id' => $connected->id,
            'can_assign' => true,
            'can_view' => false,
        ]));

        $this->assertStringContainsString('"assignment_explanation" is required', $response);
    }

    public function test_it_rejects_invalid_project_id_during_creation(): void
    {
        $tool = new ManageAgentTool;

        $response = $tool->handle(new Request([
            'action' => 'create_agent',
            'name' => 'Test Agent',
            'role' => 'Test Role',
            'instructions' => 'Test Instructions',
            'project_id' => 999999,
        ]));

        $this->assertStringContainsString('Project with ID 999999 does not exist', $response);
    }
}
