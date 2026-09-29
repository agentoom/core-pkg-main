<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Services\AgentManagementService;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool as ToolContract;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class ManageAgentTool implements ToolContract
{
    use HandlesToolActions;

    public function __construct(private readonly AgentManagementService $service = new AgentManagementService) {}

    public function description(): Stringable|string
    {
        return 'Manage AI agents using the same structure as the Agent create/edit UI. '
            .'Supports discovery of available agents, tools, skills, models, and projects, agent creation and updates, '
            .'and task-permission management between connected agents (permissions and instructions).';
    }

    // Thin delegators with snake_case names to match `action` values in schema.
    #[Sentinelable(
        name: 'Manage Agent Tool Discover Agents',
        description: 'Discover available AI agents.',
        category: 'Agent Management'
    )]
    public function discover_agents(Request $request): string
    {
        return $this->service->discoverAgents();
    }

    #[Sentinelable(
        name: 'Manage Agent Tool Discover Projects',
        description: 'Discover available projects.',
        category: 'Agent Management'
    )]
    public function discover_projects(Request $request): string
    {
        return $this->service->discoverProjects();
    }

    #[Sentinelable(
        name: 'Manage Agent Tool Discover Tools',
        description: 'Discover available tools.',
        category: 'Agent Management'
    )]
    public function discover_tools(Request $request): string
    {
        return $this->service->discoverTools($request->string('search'));
    }

    #[Sentinelable(
        name: 'Manage Agent Tool Discover Skills',
        description: 'Discover available skills.',
        category: 'Agent Management'
    )]
    public function discover_skills(Request $request): string
    {
        return $this->service->discoverSkills($request->string('search'));
    }

    #[Sentinelable(
        name: 'Manage Agent Tool Discover Models',
        description: 'Discover available AI models.',
        category: 'Agent Management'
    )]
    public function discover_models(Request $request): string
    {
        return $this->service->discoverModels();
    }

    #[Sentinelable(
        name: 'Manage Agent Tool Create Agent',
        description: 'Create a new AI agent.',
        category: 'Agent Management'
    )]
    public function create_agent(Request $request): string
    {
        return $this->service->createAgent($request);
    }

    #[Sentinelable(
        name: 'Manage Agent Tool Update Agent',
        description: 'Update an existing AI agent.',
        category: 'Agent Management'
    )]
    public function update_agent(Request $request): string
    {
        Log::debug('Update agent request: '.json_encode($request->all()));

        return $this->service->updateAgent($request);
    }

    #[Sentinelable(
        name: 'Manage Agent Tool Manage Task Permission',
        description: 'Manage task permissions between AI agents.',
        category: 'Agent Management'
    )]
    public function manage_agent_task_permission(Request $request): string
    {
        return $this->service->manageAgentTaskPermission($request);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['discover_agents', 'discover_tools', 'discover_skills', 'discover_models', 'discover_projects', 'create_agent', 'update_agent', 'manage_agent_task_permission'])
                ->required()
                ->description('Action to perform.'),
            'search' => $schema->string()->description('Optional search term to filter tools or skills.'),
            'agent_id' => $schema->integer()->description('Agent ID (required for update_agent and manage_agent_task_permission).'),
            'connected_agent_id' => $schema->integer()->description('Connected/permitted agent ID for manage_agent_task_permission.'),
            'name' => $schema->string()->description('Agent name (required for create_agent).'),
            'slug' => $schema->string()->description('Optional custom slug. If missing, generated from the name.'),
            'description' => $schema->string()->description('Optional agent description.'),
            'role' => $schema->string()->description('Prompt [ROLE] section (required for create_agent).'),
            'objective' => $schema->string()->description('Prompt [OBJECTIVE] section.'),
            'instructions' => $schema->string()->description('Prompt [INSTRUCTIONS] section (required for create_agent).'),
            'operational_protocol' => $schema->string()->description('Prompt [OPERATIONAL PROTOCOL] section.'),
            'prioritize' => $schema->array()->description('Prompt [PRIORITIZE] section as list.')->items($schema->string()),
            'constraints' => $schema->array()->description('Prompt [CONSTRAINTS] section as list.')->items($schema->string()),
            'conditional_rules' => $schema->array()->description('Prompt [CONDITIONAL RULES] entries.')->items(
                $schema->object([
                    'condition' => $schema->string()->required(),
                    'if_true' => $schema->string(),
                    'if_false' => $schema->string(),
                ])
            ),
            'error_handling' => $schema->string()->description('Prompt [ERROR HANDLING] section.'),
            'output_format' => $schema->array()->description('Prompt [OUTPUT FORMAT] section as list.')->items($schema->string()),
            'examples' => $schema->array()->description('Prompt [EXAMPLES] entries.')->items(
                $schema->object([
                    'input' => $schema->string()->required(),
                    'output' => $schema->string(),
                ])
            ),
            'tool_ids' => $schema->array()->description('List of tool identifiers (numeric IDs only). Providing this list replaces all assigned tools.')->items($schema->integer()),
            'tools' => $schema->array()->description('Alternative to tool_ids.')->items($schema->integer()),
            'skill_ids' => $schema->array()->description('List of skill identifiers (numeric IDs only). Providing this list replaces all assigned skills.')->items($schema->integer()),
            'skills' => $schema->array()->description('Alternative to skill_ids.')->items($schema->integer()),
            'provider_id' => $schema->integer()->description('Optional provider ID. Defaults to configured ai_provider.'),
            'provider_name' => $schema->string()->description('Optional provider name alternative to provider_id.'),
            'provider_model_id' => $schema->integer()->description('Optional model ID. Defaults to configured ai_model.'),
            'provider_model_name' => $schema->string()->description('Optional model name alternative to provider_model_id.'),
            'temperature' => $schema->number()->description('Optional temperature between 0 and 2.')->default(0),
            'top_p' => $schema->number()->description('Optional top_p between 0 and 1.')->default(0),
            'provider_model_suffix' => $schema->string()->description('Optional model suffix.'),
            'project_id' => $schema->integer()->description('Optional project ID. Use discover_projects to retrieve current projects.'),
            'is_public' => $schema->boolean()->description('Whether agent is public. Default true.'),
            'is_active' => $schema->boolean()->description('Whether agent is active. Default true.'),
            'allow_human_intervention' => $schema->boolean()->description('Allow human intervention. Default false.'),
            'allow_memory_analysis' => $schema->boolean()->description('Allow memory analysis. Default true.'),
            'is_cautious_constraint_enabled' => $schema->boolean()->description('Enable cautious constraint. Default true.'),
            'language_policy_enabled' => $schema->boolean()->description('Enable language policy in prompt settings. Default false.'),
            'max_usd_limit' => $schema->number()->description('Optional usage limit in USD.'),
            'limit_period' => $schema->string()->description('Optional usage period: daily, weekly, monthly.'),
            'is_hard_limit' => $schema->boolean()->description('Optional hard-limit flag.'),
            'max_tasks_per_period' => $schema->integer()->description('Optional task limit count.'),
            'task_limit_period' => $schema->string()->description('Optional task period: daily, weekly, monthly.'),
            'limit_starts_at' => $schema->string()->description('Optional limit start date (YYYY-MM-DD).'),
            'task_limit_starts_at' => $schema->string()->description('Optional task limit start date (YYYY-MM-DD).'),
            'can_assign' => $schema->boolean()->description('Permission flag for assigning tasks (manage_agent_task_permission).'),
            'can_view' => $schema->boolean()->description('Permission flag for viewing tasks (manage_agent_task_permission).'),
            'assignment_explanation' => $schema->string()->description('Instructions/explanation for permitted task assignments (manage_agent_task_permission).'),
        ];
    }
}
