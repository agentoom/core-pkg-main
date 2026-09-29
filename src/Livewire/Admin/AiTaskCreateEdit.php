<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AiTask;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Services\AiTaskService;
use Cron\CronExpression;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AiTaskCreateEdit extends Component
{
    public ?AiTask $task = null;

    public bool $is_editing = false;

    public string $name = '';

    public string $description = '';

    public string $period = 'once';

    public string $minute = '*';

    public string $hour = '*';

    public string $day = '*';

    public string $month = '*';

    public string $day_of_week = '*';

    public ?string $cron = null;

    public string $timeframe = '';

    public int $is_active = 1;

    public int $allow_memory_analysis = 0;

    public ?int $projectId = null;

    public ?int $agent_id = null;

    public string $currentTime = '';

    public string $appTimezone = '';

    public function mount(?AiTask $task = null): void
    {
        $this->task = $task;
        $this->is_editing = $task && $task->exists;
        $this->appTimezone = config('app.timezone');
        $this->currentTime = now()->toDateTimeString();

        if ($this->is_editing) {
            $this->name = $task->name;
            $this->description = $task->description;
            $this->period = $task->period;
            $this->cron = $task->cron;
            if ($this->cron) {
                $parts = explode(' ', $this->cron);
                if (count($parts) === 5) {
                    $this->minute = $parts[0];
                    $this->hour = $parts[1];
                    $this->day = $parts[2];
                    $this->month = $parts[3];
                    $this->day_of_week = $parts[4];
                }
            }
            $this->is_active = $task->is_active ? 1 : 0;
            $this->allow_memory_analysis = ($task->allow_memory_analysis ?? false) ? 1 : 0;
            $this->projectId = $task->project_id;
            $this->agent_id = $task->agent_id;
        }
    }

    public function updatedPeriod($value): void
    {
        if ($value !== 'once') {
            $this->syncCron();
        }
    }

    public function updatedTimeframe($value): void
    {
        switch ($value) {
            case 'every_minute':
                $this->minute = '*';
                $this->hour = '*';
                $this->day = '*';
                $this->month = '*';
                $this->day_of_week = '*';
                break;
            case 'every_5_minutes':
                $this->minute = '*/5';
                $this->hour = '*';
                $this->day = '*';
                $this->month = '*';
                $this->day_of_week = '*';
                break;
            case 'every_30_minutes':
                $this->minute = '*/30';
                $this->hour = '*';
                $this->day = '*';
                $this->month = '*';
                $this->day_of_week = '*';
                break;
            case 'every_hour':
                $this->minute = '0';
                $this->hour = '*';
                $this->day = '*';
                $this->month = '*';
                $this->day_of_week = '*';
                break;
            case 'every_3_hours':
                $this->minute = '0';
                $this->hour = '*/3';
                $this->day = '*';
                $this->month = '*';
                $this->day_of_week = '*';
                break;
            case 'twice_a_day':
                $this->minute = '0';
                $this->hour = '0,12';
                $this->day = '*';
                $this->month = '*';
                $this->day_of_week = '*';
                break;
            case 'everyday':
                $this->minute = '0';
                $this->hour = '0';
                $this->day = '*';
                $this->month = '*';
                $this->day_of_week = '*';
                break;
            case 'every_3_days':
                $this->minute = '0';
                $this->hour = '0';
                $this->day = '*/3';
                $this->month = '*';
                $this->day_of_week = '*';
                break;
            case 'every_week':
                $this->minute = '0';
                $this->hour = '0';
                $this->day = '*';
                $this->month = '*';
                $this->day_of_week = '0'; // Sunday
                break;
            case 'every_month':
                $this->minute = '0';
                $this->hour = '0';
                $this->day = '1';
                $this->month = '*';
                $this->day_of_week = '*';
                break;
        }

        $this->syncCron();
    }

    protected function syncCron(): void
    {
        $this->cron = "{$this->minute} {$this->hour} {$this->day} {$this->month} {$this->day_of_week}";
    }

    public function updated($property): void
    {
        if (in_array($property, ['minute', 'hour', 'day', 'month', 'day_of_week'])) {
            $this->syncCron();
        }
    }

    public function save(bool $stay = false, ?AiTaskService $taskService = null)
    {
        $taskService ??= app(AiTaskService::class);

        if ($this->period !== 'once') {
            $this->syncCron();
        }

        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'period' => 'required|in:once,custom',
            'cron' => [
                Rule::requiredIf($this->period === 'custom'),
                'nullable',
                'string',
            ],
            'projectId' => 'nullable|exists:projects,id',
            'is_active' => 'boolean',
            'agent_id' => 'required|exists:agents,id',
        ]);

        if ($this->period !== 'once' && $this->cron) {
            try {
                new CronExpression($this->cron);
            } catch (\Exception $e) {
                $this->addError('cron', 'Invalid cron expression');

                return null;
            }
        }

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'period' => $this->period,
            'cron' => $this->period !== 'once' ? $this->cron : null,
            'project_id' => $this->projectId,
            'is_active' => $this->is_active,
            'allow_memory_analysis' => $this->allow_memory_analysis,
            'agent_id' => $this->agent_id,
        ];

        if ($this->is_editing) {
            $this->task->update($data);
            if ($this->task->wasChanged('period') || $this->task->wasChanged('cron')) {
                $this->task->update(['next_run_at' => $taskService->calculateNextRunAt($this->period, $this->cron)]);
            }
            session()->flash('success', __('agentoom-core::admin.ai_tasks.updated_success'));
        } else {
            $this->task = AiTask::create(array_merge($data, [
                'user_id' => auth()->id(),
                'status' => 'pending',
                'next_run_at' => $taskService->calculateNextRunAt($this->period, $this->cron),
            ]));
            session()->flash('success', __('agentoom-core::admin.ai_tasks.created_success'));
            $this->is_editing = true;
        }

        if ($stay) {
            return $this->redirect(route('admin.agents.ai-tasks.edit', $this->task), navigate: true);
        }

        return $this->redirect(route('admin.agents.ai-tasks.index'), navigate: true);
    }

    public function clone(AiTaskService $taskService): void
    {
        if (! $this->task || ! $this->task->exists) {
            return;
        }

        $newTask = $this->task->replicate();
        $newTask->name = $this->task->name.' (Clone)';
        $newTask->status = 'pending';
        $newTask->last_run_at = null;
        $newTask->next_run_at = $taskService->calculateNextRunAt($newTask->period, $newTask->cron);
        $newTask->save();

        session()->flash('success', __('agentoom-core::admin.common.cloned_success'));

        $this->redirect(route('admin.agents.ai-tasks.edit', $newTask), navigate: true);
    }

    public function updatedAgentId($value): void
    {
        if ($value && ! $this->projectId) {
            $agent = Agent::find($value);
            if ($agent && $agent->project_id) {
                $this->projectId = $agent->project_id;
            }
        }
    }

    public function render()
    {
        $agentsQuery = Agent::where('is_active', true);

        if ($this->projectId) {
            $agentsQuery->where('project_id', $this->projectId);
        }

        $agents = $agentsQuery->orderBy('name')->get();
        $projects = Project::orderBy('name')->get();

        return view('agentoom-core::livewire.admin.ai-task-create-edit', [
            'agents' => $agents,
            'projects' => $projects,
        ])->layout('agentoom-core::layouts.admin');
    }
}
