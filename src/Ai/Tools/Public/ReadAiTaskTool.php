<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Execution\ActionExecution;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AiTask;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class ReadAiTaskTool implements Tool
{
    use HandlesToolActions;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Read and monitor tasks assigned to AI agents. Use this tool to view the tasks you have visibility on, check their status, and read the execution logs to see what has been done and what the results were. You can filter the task list by date using "from_date" and "to_date" (format YYYY-MM-DD); if not specified, it defaults to the last 7 days.';
    }

    /**
     * List tasks the current agent has visibility on.
     */
    #[Sentinelable(
        name: 'Read AI Task Tool List Tasks',
        description: 'List AI tasks with optional filters.',
        category: 'AI Tasks'
    )]
    protected function listTasks(Request $request): string
    {
        $agent = $this->getCurrentAgent();
        if (! $agent) {
            return 'Error: No agent context found.';
        }

        // Get date filters, default to last 7 days
        $fromDate = $request->string('from_date');
        $toDate = $request->string('to_date');

        try {
            $start = $fromDate ? Carbon::parse((string) $fromDate)->startOfDay() : now()->subDays(7)->startOfDay();
            $end = $toDate ? Carbon::parse((string) $toDate)->endOfDay() : now()->endOfDay();
        } catch (\Exception $e) {
            return 'Error: Invalid date format. Please use YYYY-MM-DD.';
        }

        // Agents can see tasks they assigned, tasks assigned to them, and tasks they have explicit view permission for
        $viewableAgentIds = $agent->taskPermissions()
            ->where('can_view', true)
            ->pluck('permitted_agent_id')
            ->toArray();

        $viewableAgentIds[] = $agent->id;

        $user = auth()->user();
        $tasks = AiTask::visibleTo($user)
            ->where(function ($query) use ($agent, $viewableAgentIds) {
                $query->where('assigned_by_agent_id', $agent->id)
                    ->orWhereIn('agent_id', $viewableAgentIds);
            })
            ->whereBetween('last_run_at', [$start, $end])
            ->get();

        if ($tasks->isEmpty()) {
            return 'No tasks found that you have visibility on.';
        }

        $output = "TASKS ACCESSIBLE TO YOU:\n\n";
        foreach ($tasks as $task) {
            $assignedTo = $task->agent->name;
            $assignedBy = $task->assignedByAgent ? $task->assignedByAgent->name : 'System';
            $output .= "ID: {$task->id}, Name: {$task->name}\n";
            $output .= "  Status: {$task->status}, Period: {$task->period}\n";
            $output .= "  Assigned To: {$assignedTo}, Assigned By: {$assignedBy}\n";
            $output .= '  Last Run: '.($task->last_run_at ? $task->last_run_at->toDateTimeString() : 'Never')."\n\n";
        }

        return $output;
    }

    /**
     * Read the logs for a specific task.
     */
    #[Sentinelable(
        name: 'Read AI Task Tool Read Task Logs',
        description: 'Read the execution logs of a specific AI task.',
        category: 'AI Tasks'
    )]
    protected function readTaskLogs(Request $request): string
    {
        $agent = $this->getCurrentAgent();
        if (! $agent) {
            return 'Error: No agent context found.';
        }

        $taskId = $request->integer('id');
        $user = auth()->user();
        $task = AiTask::visibleTo($user)->find($taskId);

        if (! $task) {
            return "Error: Task ID {$taskId} not found or unauthorized.";
        }

        // Check permission
        $canView = $task->assigned_by_agent_id === $agent->id ||
                   $task->agent_id === $agent->id ||
                   $agent->taskPermissions()->where('permitted_agent_id', $task->agent_id)->where('can_view', true)->exists();

        if (! $canView) {
            return "Error: You are not authorized to view logs for task ID {$taskId}.";
        }

        $logs = $task->logs()->latest()->get();

        if ($logs->isEmpty()) {
            return "No logs found for task '{$task->name}' (ID: {$task->id}).";
        }

        $output = "LOGS FOR TASK '{$task->name}' (ID: {$task->id}):\n\n";
        foreach ($logs as $log) {
            $logAgent = $log->agent ? $log->agent->name : 'System';
            $output .= "--- Log Entry [{$log->created_at->toDateTimeString()}] by {$logAgent} ---\n";
            $output .= $log->content."\n";
            if (! empty($log->results)) {
                $output .= 'Results: '.json_encode($log->results)."\n";
            }
            $output .= "---------------------------------------------------\n\n";
        }

        return $output;
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
                ->enum(['list_tasks', 'read_task_logs'])
                ->required()
                ->description('The action to perform.'),
            'id' => $schema->integer()->description('The ID of the task to read logs for (required for read_task_logs)'),
            'from_date' => $schema->string()->description('Filter tasks by last_run_at from this date (YYYY-MM-DD). Defaults to 7 days ago.'),
            'to_date' => $schema->string()->description('Filter tasks by last_run_at to this date (YYYY-MM-DD). Defaults to today.'),
        ];
    }
}
