<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Execution\ActionExecution;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Services\AiTaskService;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class AssignAiTaskTool implements Tool
{
    use HandlesToolActions;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Manage and assign tasks to other AI agents. Use this tool to discover which agents you can assign tasks to and to create new tasks for them.
        Tasks can be executed once or periodically using a cron expression.
        You should clearly state what the agent should do in the task description.';
    }

    /**
     * List agents the current agent can assign tasks to.
     */
    #[Sentinelable(
        name: 'Assign AI Task Tool List Permitted Agents',
        description: 'List agents that are permitted to receive task assignments.',
        category: 'Agent Management'
    )]
    protected function listPermittedAgents(): string
    {
        $agent = $this->getCurrentAgent();
        if (! $agent) {
            return 'Error: No agent context found.';
        }

        $permissions = $agent->taskPermissions()->where('can_assign', true)->get();

        if ($permissions->isEmpty()) {
            return 'You do not have permission to assign tasks to any agents.';
        }

        $output = "AGENTS YOU CAN ASSIGN TASKS TO:\n\n";
        foreach ($permissions as $p) {
            $permitted = $p->permittedAgent;
            $output .= "- ID: {$permitted->id}, Name: {$permitted->name}\n";
            if ($p->assignment_explanation) {
                $output .= "  Explanation: {$p->assignment_explanation}\n";
            }
        }

        return $output;
    }

    /**
     * Create a new task for another agent.
     */
    #[Sentinelable(
        name: 'Assign AI Task Tool Assign Task',
        description: 'Assign a task to an AI agent for execution.',
        category: 'Agent Management'
    )]
    protected function assignTask(Request $request): string
    {
        $agent = $this->getCurrentAgent();
        if (! $agent) {
            return 'Error: No agent context found.';
        }

        $targetAgentId = $request->integer('agent_id');
        $name = (string) $request->string('name');
        $description = (string) $request->string('description');
        $cron = (string) $request->string('cron');
        $period = $cron ? 'custom' : 'once';
        $parameters = $request->array('parameters', []);

        $targetAgent = Agent::find($targetAgentId);
        if (! $targetAgent) {
            return "Error: Agent with ID {$targetAgentId} does not exist. Use the 'list_permitted_agents' action to see the IDs of available agents.";
        }

        // Check permission
        $permission = $agent->taskPermissions()
            ->where('permitted_agent_id', $targetAgentId)
            ->where('can_assign', true)
            ->first();

        if (! $permission) {
            return "Error: You are not authorized to assign tasks to agent '{$targetAgent->name}' (ID: {$targetAgentId}). Use the 'list_permitted_agents' action to verify which agents you can assign tasks to.";
        }

        if ($agent->isTaskLimitReached()) {
            return "Error: You have reached your task creation limit for the current period ({$agent->task_limit_period}).";
        }

        try {
            $projectId = app()->bound('current_project_id') ? app('current_project_id') : $agent->project_id;
            $userId = auth()->id() ?: $agent->user_id;

            if (! $userId && $projectId) {
                $userId = Project::find($projectId)?->system_user_id;
            }

            $taskService = app(AiTaskService::class);
            $task = $taskService->createTask([
                'user_id' => $userId,
                'project_id' => $projectId,
                'agent_id' => $targetAgentId,
                'assigned_by_agent_id' => $agent->id,
                'name' => $name,
                'description' => $description,
                'period' => $period,
                'cron' => $cron ?: null,
                'parameters' => $parameters,
            ]);

            return "Successfully assigned task '{$task->name}' (ID: {$task->id}) to agent ID {$targetAgentId}. Period: {$period}.";
        } catch (\Exception $e) {
            return 'Error creating task: '.$e->getMessage();
        }
    }

    /**
     * Get the current agent from the context.
     */
    private function getCurrentAgent(): ?Agent
    {
        if (app()->bound(ActionExecution::class)) {
            $execution = app(ActionExecution::class);
            $agent = $execution->agent;

            if ($agent instanceof DynamicAgent) {
                return $agent->getAgentModel();
            }
        }

        return null;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['list_permitted_agents', 'assign_task'])
                ->required()
                ->description('The action to perform.'),
            'agent_id' => $schema->integer()->description('The ID of the agent to assign the task to (required for assign_task)'),
            'name' => $schema->string()->description('A short, descriptive name for the task'),
            'description' => $schema->string()->description('Detailed instructions for the task'),
            'cron' => $schema->string()->description('A standard cron expression for recurring tasks (e.g., "0 0 * * *"). Leave empty for one-time tasks.'),
            'parameters' => $schema->object()->description('Optional key-value pairs of parameters for the task'),
        ];
    }
}
