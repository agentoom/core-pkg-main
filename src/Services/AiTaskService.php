<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AiTask;
use Agentoom\Core\Models\AiTaskLog;
use Carbon\Carbon;
use Cron\CronExpression;

class AiTaskService
{
    /**
     * Create a new AI task.
     */
    public function createTask(array $data): AiTask
    {
        $agent = Agent::findOrFail($data['assigned_by_agent_id']);

        if ($agent->isTaskLimitReached()) {
            throw new \Exception('Task limit reached for agent: '.$agent->name);
        }

        $task = AiTask::create([
            'user_id' => $data['user_id'],
            'project_id' => $data['project_id'] ?? null,
            'agent_id' => $data['agent_id'],
            'assigned_by_agent_id' => $data['assigned_by_agent_id'],
            'name' => $data['name'],
            'description' => $data['description'],
            'parameters' => $data['parameters'] ?? null,
            'period' => $data['period'] ?? 'once',
            'cron' => $data['cron'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'next_run_at' => $this->calculateNextRunAt($data['period'] ?? 'once', $data['cron'] ?? null),
            'status' => 'pending',
        ]);

        return $task;
    }

    /**
     * Calculate the next run time based on period.
     */
    public function calculateNextRunAt(string $period, ?string $cron = null): ?Carbon
    {
        if ($cron) {
            try {
                return Carbon::instance((new CronExpression($cron))->getNextRunDate());
            } catch (\Exception $e) {
                // Fallback to period-based calculation
            }
        }

        return match ($period) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
            'once' => null,
            default => now(),
        };
    }

    /**
     * Add a log entry to a task.
     */
    public function addLog(int $taskId, string $content, $results = null, ?int $agentId = null): AiTaskLog
    {
        return AiTaskLog::create([
            'ai_task_id' => $taskId,
            'agent_id' => $agentId,
            'content' => $content,
            'results' => $results,
        ]);
    }
}
