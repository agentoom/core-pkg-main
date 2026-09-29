<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Services\ActionDiscoveryService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class ScheduleCreateEdit extends Component
{
    public ?Schedule $schedule = null;

    public string $name = '';

    public string $minute = '*';

    public string $hour = '*';

    public string $day = '*';

    public string $month = '*';

    public string $day_of_week = '*';

    public string $timezone = 'UTC';

    public string $model = Agent::class;

    public int|string|null $model_id = null;

    public ?string $method = null;

    public ?string $instructions = null;

    public ?string $repository_instructions = null;

    public int $active = 1;

    public int $allow_memory_analysis = 0;

    public ?int $projectId = null;

    public ?int $user_id = null;

    public string $timeframe = ''; // preset selector that prefills cron fields

    public bool $is_editing = false;

    public string $target_type = 'agent'; // 'agent', 'pipeline', 'custom'

    public string $selectedMethodKey = '';

    public string $currentTime = '';

    public string $appTimezone = '';

    public function mount(?Schedule $schedule = null): void
    {
        $this->schedule = $schedule;
        $this->is_editing = $schedule && $schedule->exists;
        $this->appTimezone = config('app.timezone');
        $this->currentTime = now()->toDateTimeString();

        if ($this->is_editing) {
            $this->authorize('view', $this->schedule);

            $this->name = $schedule->name;
            $this->minute = $schedule->minute;
            $this->hour = $schedule->hour;
            $this->day = $schedule->day;
            $this->month = $schedule->month;
            $this->day_of_week = $schedule->day_of_week;
            $this->timezone = $schedule->timezone ?? 'UTC';
            $this->model = $schedule->model;
            $this->model_id = $schedule->model_id;
            $this->method = $schedule->method;
            $this->instructions = $schedule->instructions ?? '';
            $this->repository_instructions = $schedule->repository_instructions ?? '';
            $this->active = $schedule->active ? 1 : 0;
            $this->allow_memory_analysis = ($schedule->allow_memory_analysis ?? false) ? 1 : 0;
            $this->projectId = $schedule->project_id;
            $this->user_id = $schedule->user_id;

            if ($this->model === Agent::class && ($this->method === null || $this->method === 'execute')) {
                $this->target_type = 'agent';
                $this->method = null;
            } elseif ($this->model === Pipeline::class && ! $this->method) {
                $this->target_type = 'pipeline';
            } elseif ($this->method) {
                $this->target_type = 'custom';
                $this->selectedMethodKey = "{$this->model}@{$this->method}";
            } else {
                $this->target_type = 'agent';
            }
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
    }

    public function updatedTargetType($value): void
    {
        if ($value === 'agent') {
            $this->model = Agent::class;
            $this->method = null;
        } elseif ($value === 'pipeline') {
            $this->model = Pipeline::class;
            $this->method = null;
        } else {
            $this->model = '';
            $this->method = null;
            $this->model_id = null;
        }
    }

    public function updatedSelectedMethodKey($value): void
    {
        if (! $value) {
            $this->model = '';
            $this->method = null;

            return;
        }

        $discoveryService = app(ActionDiscoveryService::class);
        $methods = $discoveryService->discover();

        if (isset($methods[$value])) {
            $methodData = $methods[$value];
            $this->model = $methodData['class'];
            $this->method = $methodData['method'];

            // Prefill instructions with JSON of parameters in the format expected by JsonStructureHelper
            $prefilled = [];
            foreach ($methodData['parameters'] as $name => $paramData) {
                $prefilled[] = [
                    'name' => $name,
                    'type' => $paramData['type'],
                    'value' => "({$paramData['type']})",
                    'description' => $paramData['description'],
                ];
            }
            $this->instructions = json_encode($prefilled);
        }
    }

    #[On('json-structure-updated')]
    public function updateInstructions(array $fields, ?string $identifier = null): void
    {
        $this->instructions = json_encode($fields);
    }

    /**
     * Get instructions formatted for JsonStructureHelper.
     */
    public function getInstructionFields(): array
    {
        $data = json_decode($this->instructions, true);
        if (! is_array($data)) {
            return [];
        }

        // If it's already in the new format (array of objects with 'name')
        if (! empty($data) && isset($data[0]) && is_array($data[0]) && isset($data[0]['name'])) {
            return $data;
        }

        // Convert old simple key-value format to new format
        $fields = [];
        foreach ($data as $name => $value) {
            if (is_int($name)) {
                // If it's already an array but not our format, just skip or handle as needed
                continue;
            }
            $fields[] = [
                'name' => (string) $name,
                'type' => is_int($value) ? 'integer' : (is_bool($value) ? 'boolean' : 'string'),
                'value' => (string) $value,
                'description' => '',
            ];
        }

        return $fields;
    }

    public function save(bool $stay = false)
    {
        if ($this->is_editing) {
            $this->authorize('update', $this->schedule);
        } else {
            $this->authorize('create', Schedule::class);
        }

        if ($this->model_id === '') {
            $this->model_id = null;
        }

        $this->validate([
            'name' => 'required|string|max:255',
            'minute' => 'required|string|max:30',
            'hour' => 'required|string|max:30',
            'day' => 'required|string|max:30',
            'month' => 'required|string|max:30',
            'day_of_week' => 'required|string|max:30',
            'timezone' => 'required|string|max:255',
            'model' => 'required|string',
            'model_id' => $this->target_type === 'custom' ? 'nullable|integer' : 'required|integer',
            'method' => 'nullable|string',
            'instructions' => 'nullable|string',
            'repository_instructions' => 'nullable|string',
            'active' => 'boolean',
            'user_id' => [
                'nullable',
                'integer',
                Rule::in(auth()->user()->getAssignableUsers()->pluck('id')->toArray()),
                Rule::requiredIf(fn () => empty($this->projectId)),
            ],
            'projectId' => [
                'nullable',
                'exists:projects,id',
                Rule::requiredIf(fn () => empty($this->user_id)),
            ],
        ], [
            'user_id.required_if' => __('agentoom-core::models.errors.project_or_user_required'),
            'projectId.required_if' => __('agentoom-core::models.errors.project_or_user_required'),
        ]);

        $data = [
            'name' => $this->name,
            'minute' => $this->minute,
            'hour' => $this->hour,
            'day' => $this->day,
            'month' => $this->month,
            'day_of_week' => $this->day_of_week,
            'timezone' => $this->timezone,
            'model' => $this->model,
            'model_id' => $this->model_id,
            'method' => $this->method,
            'instructions' => $this->instructions,
            'repository_instructions' => $this->repository_instructions,
            'active' => $this->active,
            'allow_memory_analysis' => $this->allow_memory_analysis,
            'project_id' => $this->projectId,
            'user_id' => $this->user_id,
        ];

        if ($this->is_editing) {
            $this->schedule->update($data);
            \Flux::toast(__('agentoom-core::admin.schedules.updated_success'));
        } else {
            $this->schedule = Schedule::create($data);
            \Flux::toast(__('agentoom-core::admin.schedules.created_success'));
        }

        if ($stay) {
            return $this->redirect(route('admin.schedules.edit', $this->schedule), navigate: true);
        }

        return $this->redirect(route('admin.schedules.index'), navigate: true);
    }

    public function clone(): void
    {
        if (! $this->schedule || ! $this->schedule->exists) {
            return;
        }

        $newSchedule = $this->schedule->replicate();
        $newSchedule->name = $this->schedule->name.' (Clone)';
        $newSchedule->slug = null;
        $newSchedule->save();

        \Flux::toast(__('agentoom-core::admin.common.cloned_success'));

        $this->redirect(route('admin.schedules.edit', $newSchedule), navigate: true);
    }

    public function updatedModelId($value): void
    {
        if ($value && ! $this->projectId) {
            if ($this->model === Agent::class) {
                $agent = Agent::find($value);
                if ($agent && $agent->project_id) {
                    $this->projectId = $agent->project_id;
                }
            } elseif ($this->model === Pipeline::class) {
                $pipeline = Pipeline::find($value);
                if ($pipeline && $pipeline->project_id) {
                    $this->projectId = $pipeline->project_id;
                }
            }
        }
    }

    public function openMethodSelector(): void
    {
        $this->dispatch('open-method-selector');
    }

    #[On('method-selected')]
    public function onMethodSelected(string $key, ?string $identifier = null): void
    {
        $this->selectedMethodKey = $key;
        $this->updatedSelectedMethodKey($key);
    }

    public function render(ActionDiscoveryService $discoveryService)
    {
        $agentsQuery = Agent::query();
        $pipelinesQuery = Pipeline::query();

        if ($this->projectId) {
            $agentsQuery->where('project_id', $this->projectId);
            $pipelinesQuery->where('project_id', $this->projectId);
        }

        $agents = $agentsQuery->orderBy('name')->get();
        $pipelines = $pipelinesQuery->orderBy('name')->get();
        $timezones = \DateTimeZone::listIdentifiers();

        $actionableMethods = $discoveryService->discover();

        $users = auth()->user()->getAssignableUsers();
        $projects = Project::orderBy('name')->get();

        return view('agentoom-core::livewire.admin.schedule-create-edit', [
            'agents' => $agents,
            'pipelines' => $pipelines,
            'timezones' => $timezones,
            'actionableMethods' => $actionableMethods,
            'users' => $users,
            'projects' => $projects,
        ])->layout('agentoom-core::layouts.admin');
    }
}
