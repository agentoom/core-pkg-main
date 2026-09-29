<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Services\ActionDiscoveryService;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class ScheduleManagerTool implements Tool
{
    use HandlesToolActions;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Manage scheduled actions (schedules). Use this tool to discover available agents, pipelines, and Direct Actions that can be scheduled, and to view, create, update, or delete schedules.
        CRITICAL: You MUST ask the user for confirmation before creating a schedule, clearly stating all parameters and the schedule details.
        If scheduling a Direct Action, ensure you have obtained all required parameters from the user.';
    }

    /**
     * List agents, pipelines, and Direct Actions that can be scheduled.
     */
    #[Sentinelable(
        name: 'Schedule Manager Tool List Available Actions',
        description: 'List all available actions that can be scheduled.',
        category: 'Scheduling'
    )]
    protected function listAvailableActions(): string
    {
        $user = auth()->user();
        if (! $user) {
            return 'Error: Unauthenticated.';
        }

        // Discovery for Agents
        $agents = Agent::visibleTo($user)->where('is_active', true)->get();

        // Discovery for Pipelines
        $pipelines = Pipeline::visibleTo($user)->where('is_active', true)->get();

        // Discovery for Actionable Methods
        $discoveryService = app(ActionDiscoveryService::class);
        $methods = $discoveryService->discover();

        $output = "AVAILABLE TARGETS FOR SCHEDULING:\n\n";

        $output .= "1. AGENTS (type: 'agent'):\n";
        if ($agents->isEmpty()) {
            $output .= "   No agents available.\n";
        }
        foreach ($agents as $agent) {
            $output .= "   - ID: {$agent->id}, Name: {$agent->name}, Description: {$agent->description}\n";
        }

        $output .= "\n2. PIPELINES (type: 'pipeline'):\n";
        if ($pipelines->isEmpty()) {
            $output .= "   No pipelines available.\n";
        }
        foreach ($pipelines as $pipeline) {
            $output .= "   - ID: {$pipeline->id}, Name: {$pipeline->name}, Description: {$pipeline->description}\n";
        }

        $output .= "\n3. DIRECT ACTIONS (type: 'method'):\n";
        if (empty($methods)) {
            $output .= "   No Direct Actions available.\n";
        }
        foreach ($methods as $key => $data) {
            $params = [];
            foreach ($data['parameters'] as $name => $param) {
                $type = $param['type'] ?? 'any';
                $params[] = "{$name} ({$type})";
            }
            $paramsStr = implode(', ', $params);
            $output .= "   - Key: {$key}, Name: {$data['name']}, Description: {$data['description']}, Parameters: [{$paramsStr}]\n";
        }

        $output .= "\nTo create a schedule, use 'create_schedule' with the 'type' and 'target_id' (ID for agent/pipeline, Key for action).\n";

        return $output;
    }

    /**
     * List schedules accessible to the current user.
     */
    #[Sentinelable(
        name: 'Schedule Manager Tool List Schedules',
        description: 'List all existing schedules.',
        category: 'Scheduling'
    )]
    protected function listSchedules(): string
    {
        $user = auth()->user();
        if (! $user) {
            return 'Error: Unauthenticated.';
        }

        $schedules = Schedule::visibleTo($user)->get();

        if ($schedules->isEmpty()) {
            return 'No schedules were found.';
        }

        $output = "[[ SYSTEM: LIST OF REGISTERED SCHEDULES ]]\n\n";

        foreach ($schedules as $s) {
            $targetType = $this->getTargetType($s);
            $output .= "--- SCHEDULE ---\n";
            $output .= "id: {$s->id}\n";
            $output .= "name: {$s->name}\n";
            $output .= 'status: '.($s->active ? 'active' : 'inactive')."\n";
            $output .= "cron: {$s->toCronExpression()}\n";
            $output .= "timezone: {$s->timezone}\n";
            $output .= "target_type: {$targetType}\n";

            if ($targetType === 'method') {
                $output .= "target_id: {$s->model}@{$s->method}\n";
            } else {
                $output .= "target_id: {$s->model_id}\n";
            }

            if ($s->instructions && ! $this->isJson($s->instructions)) {
                $output .= "instructions: {$s->instructions}\n";
            }
            $output .= "----------------\n\n";
        }

        return $output;
    }

    /**
     * Check if a string is valid JSON.
     */
    private function isJson(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Create a new schedule.
     */
    #[Sentinelable(
        name: 'Schedule Manager Tool Create Schedule',
        description: 'Create a new schedule for an action.',
        category: 'Scheduling'
    )]
    protected function createSchedule(Request $request): string
    {
        $user = auth()->user();
        if (! $user) {
            return 'Error: Unauthenticated.';
        }

        if (! Gate::allows('create', Schedule::class)) {
            return 'Error: Unauthorized to create schedules.';
        }

        $type = (string) $request->string('type');
        $targetId = (string) $request->string('target_id');
        $name = (string) $request->string('name');

        if (empty($name) || empty($type) || empty($targetId)) {
            return 'Error: name, type, and target_id are required.';
        }

        $scheduleData = [
            'name' => $name,
            'minute' => (string) $request->string('minute', '*'),
            'hour' => (string) $request->string('hour', '*'),
            'day' => (string) $request->string('day', '*'),
            'month' => (string) $request->string('month', '*'),
            'day_of_week' => (string) $request->string('day_of_week', '*'),
            'timezone' => (string) $request->string('timezone', config('app.timezone')),
            'active' => (bool) $request->boolean('active', true),
            'project_id' => app()->bound('current_project_id') ? app('current_project_id') : null,
            'user_id' => $user->id,
        ];

        $instructions = (string) $request->string('instructions');

        if ($type === 'method') {
            $discoveryService = app(ActionDiscoveryService::class);
            $methods = $discoveryService->discover();

            // Try direct match first
            if (isset($methods[$targetId])) {
                $found = $methods[$targetId];
                $scheduleData['model'] = $found['class'];
                $scheduleData['method'] = $found['method'];
            } else {
                // Try fuzzy/partial match or resolution for short names
                $matches = [];
                $normalizedTargetId = strtolower(str_replace('\\', '', $targetId));
                foreach ($methods as $key => $data) {
                    $normalizedKey = strtolower(str_replace('\\', '', $key));
                    if (str_contains($normalizedKey, $normalizedTargetId)) {
                        $matches[$key] = $data;
                    }
                }

                if (count($matches) === 1) {
                    $found = reset($matches);
                    $scheduleData['model'] = $found['class'];
                    $scheduleData['method'] = $found['method'];
                } elseif (count($matches) > 1) {
                    return "Error: Multiple actions matched '{$targetId}'. Please use the full Key from the list of available actions (e.g., 'Agentoom\Core\Services\Api\PrestaShopService@sync').";
                } else {
                    return "Error: Could not find action '{$targetId}'. Ensure you are using the correct Key from the list of available actions.";
                }
            }

            // Validate JSON instructions for actions
            if (! empty($instructions)) {
                $decoded = json_decode($instructions, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return 'Error: instructions for actions must be a valid JSON string of parameters.';
                }
                if (! is_array($decoded)) {
                    return 'Error: instructions for actions must decode to a JSON array/object of parameters.';
                }
            } else {
                $instructions = '[]';
            }
        } elseif ($type === 'agent') {
            $scheduleData['model'] = Agent::class;
            $scheduleData['model_id'] = (int) $targetId;
        } elseif ($type === 'pipeline') {
            $scheduleData['model'] = Pipeline::class;
            $scheduleData['model_id'] = (int) $targetId;
        } else {
            return "Error: Invalid type '{$type}'. Must be agent, pipeline, or method.";
        }

        $scheduleData['instructions'] = $instructions;

        $schedule = Schedule::create($scheduleData);

        return "Successfully created schedule: '{$schedule->name}' (ID: {$schedule->id}).";
    }

    /**
     * Update an existing schedule.
     */
    #[Sentinelable(
        name: 'Schedule Manager Tool Update Schedule',
        description: 'Update an existing schedule.',
        category: 'Scheduling'
    )]
    protected function updateSchedule(Request $request): string
    {
        $id = $request->integer('id');
        if ($id <= 0) {
            return 'Error: A valid schedule ID is required.';
        }

        $schedule = Schedule::find($id);
        if (! $schedule) {
            return "Error: Schedule with ID {$id} not found.";
        }

        if (! Gate::allows('update', $schedule)) {
            return 'Error: Unauthorized to update this schedule.';
        }

        $fields = ['name', 'minute', 'hour', 'day', 'month', 'day_of_week', 'timezone', 'instructions', 'active'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                if ($field === 'active') {
                    $schedule->active = (bool) $request->boolean('active');
                } else {
                    $schedule->{$field} = (string) $request->string($field);
                }
            }
        }

        if ($request->has('type') && $request->has('target_id')) {
            $type = (string) $request->string('type');
            $targetId = (string) $request->string('target_id');

            if ($type === 'agent') {
                $schedule->model = Agent::class;
                $schedule->model_id = (int) $targetId;
                $schedule->method = null;
            } elseif ($type === 'pipeline') {
                $schedule->model = Pipeline::class;
                $schedule->model_id = (int) $targetId;
                $schedule->method = null;
            } elseif ($type === 'method') {
                $discoveryService = app(ActionDiscoveryService::class);
                $methods = $discoveryService->discover();

                if (isset($methods[$targetId])) {
                    $found = $methods[$targetId];
                    $schedule->model = $found['class'];
                    $schedule->method = $found['method'];
                    $schedule->model_id = null;
                } else {
                    // Fuzzy match for updates too
                    $matches = [];
                    $normalizedTargetId = strtolower(str_replace('\\', '', $targetId));
                    foreach ($methods as $key => $data) {
                        $normalizedKey = strtolower(str_replace('\\', '', $key));
                        if (str_contains($normalizedKey, $normalizedTargetId)) {
                            $matches[$key] = $data;
                        }
                    }

                    if (count($matches) === 1) {
                        $found = reset($matches);
                        $schedule->model = $found['class'];
                        $schedule->method = $found['method'];
                        $schedule->model_id = null;
                    } elseif (count($matches) > 1) {
                        return "Error: Multiple actions matched '{$targetId}'.";
                    } else {
                        return "Error: Could not find action '{$targetId}'.";
                    }
                }
            }
        }

        $schedule->save();

        return "Successfully updated schedule: '{$schedule->name}' (ID: {$schedule->id}).";
    }

    /**
     * Delete an existing schedule.
     */
    #[Sentinelable(
        name: 'Schedule Manager Tool Delete Schedule',
        description: 'Delete an existing schedule.',
        category: 'Scheduling'
    )]
    protected function deleteSchedule(Request $request): string
    {
        $id = $request->integer('id');
        if ($id <= 0) {
            return 'Error: A valid schedule ID is required.';
        }

        $schedule = Schedule::find($id);
        if (! $schedule) {
            return "Error: Schedule with ID {$id} not found.";
        }

        if (! Gate::allows('delete', $schedule)) {
            return 'Error: Unauthorized to delete this schedule.';
        }

        $name = $schedule->name;
        $schedule->delete();

        return "Successfully deleted schedule: '{$name}' (ID: {$id}).";
    }

    /**
     * Get the repository data for a schedule.
     */
    #[Sentinelable(
        name: 'Schedule Manager Tool Get Schedule Repository',
        description: 'Get the repository data for a specific schedule.',
        category: 'Scheduling'
    )]
    protected function getScheduleRepository(Request $request): string
    {
        $id = $request->integer('id');
        if ($id <= 0) {
            return 'Error: A valid schedule ID is required.';
        }

        $schedule = Schedule::find($id);
        if (! $schedule) {
            return "Error: Schedule with ID {$id} not found.";
        }

        if (! Gate::allows('view', $schedule)) {
            return 'Error: Unauthorized to view this schedule repository.';
        }

        if (! method_exists($schedule, 'repositories')) {
            return "Error: Schedule model is missing the 'repositories' relationship method.";
        }

        $data = $schedule->repositories()->get(['key', 'value'])->pluck('value', 'key')->toArray();

        if (empty($data)) {
            return "The repository for schedule ID {$id} is empty.";
        }

        return "Repository data for schedule ID {$id}:\n".json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Update a value in the schedule repository.
     */
    #[Sentinelable(
        name: 'Schedule Manager Tool Update Schedule Repository',
        description: 'Update the repository data for a specific schedule.',
        category: 'Scheduling'
    )]
    protected function updateScheduleRepository(Request $request): string
    {
        $id = $request->integer('id');
        $key = (string) $request->string('key');
        $value = (string) $request->string('value');

        if ($id <= 0 || empty($key)) {
            return 'Error: schedule ID and key are required.';
        }

        $schedule = Schedule::find($id);
        if (! $schedule) {
            return "Error: Schedule with ID {$id} not found.";
        }

        if (! Gate::allows('update', $schedule)) {
            return 'Error: Unauthorized to update this schedule repository.';
        }

        if (! method_exists($schedule, 'repositories')) {
            return "Error: Schedule model is missing the 'repositories' relationship method.";
        }

        $schedule->repositories()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        return "Successfully updated repository key '{$key}' for schedule ID {$id}.";
    }

    /**
     * Helper to get target type string.
     */
    private function getTargetType(Schedule $s): string
    {
        if ($s->model === Agent::class) {
            return 'agent';
        }
        if ($s->model === Pipeline::class) {
            return 'pipeline';
        }
        if ($s->method) {
            return 'method';
        }

        return 'unknown';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['list_available_actions', 'list_schedules', 'create_schedule', 'update_schedule', 'delete_schedule', 'get_schedule_repository', 'update_schedule_repository'])
                ->required()
                ->description('The action to perform. Use "list_schedules" to get the list of your registered background automation schedules, and "get_schedule_repository" to retrieve persistent data for a schedule.'),
            'id' => $schema->integer()->description('The ID of the schedule (required for update_schedule, delete_schedule, get_schedule_repository, and update_schedule_repository)'),
            'key' => $schema->string()->description('The key for the repository data (required for update_schedule_repository)'),
            'value' => $schema->string()->description('The value for the repository data (required for update_schedule_repository)'),
            'name' => $schema->string()->description('Name of the schedule'),
            'minute' => $schema->string()->description('Minute part of cron expression (0-59 or *)'),
            'hour' => $schema->string()->description('Hour part of cron expression (0-23 or *)'),
            'day' => $schema->string()->description('Day of month part of cron expression (1-31 or *)'),
            'month' => $schema->string()->description('Month part of cron expression (1-12 or *)'),
            'day_of_week' => $schema->string()->description('Day of week part of cron expression (0-6 or *)'),
            'timezone' => $schema->string()->description('Timezone for the schedule (e.g. UTC, America/New_York)'),
            'type' => $schema->string()
                ->enum(['agent', 'pipeline', 'method'])
                ->description('The type of target to schedule'),
            'target_id' => $schema->string()
                ->description('The ID of the Agent/Pipeline OR the Key for Direct Actions (e.g., "Agentoom\Core\Services\Api\PrestaShopService@sync"). You can find these in the list of available actions.'),
            'instructions' => $schema->string()
                ->description('For agents/pipelines: the prompt or instructions. For Direct Actions: a JSON string containing the parameters.
                NEW: You can use dynamic date parameters like "5 hours ago", "1 day ago", "yesterday", or "now - 5 hours".
                The system will resolve these to actual dates when the schedule runs.
                For PrestaShop ranges, use format: "[5 hours before now, 1 hour before now]".'),
            'active' => $schema->boolean()->description('Whether the schedule should be active'),
        ];
    }
}
