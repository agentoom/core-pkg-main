<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class ScheduleRepositoryTool implements Tool
{
    use HandlesToolActions;

    /**
     * @param  int|null  $scheduleId  The current schedule ID, if executing a schedule.
     */
    public function __construct(protected ?int $scheduleId = null) {}

    /**
     * Get the tool's name.
     */
    public function name(): string
    {
        return 'ScheduleRepository';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Retrieve and update persistent repository data for a schedule. This allows you to store data between executions.';
    }

    /**
     * Get the repository data for a schedule.
     */
    #[Sentinelable(
        name: 'Schedule Repository Tool Get',
        description: 'Get the repository data for the current schedule.',
        category: 'Scheduling'
    )]
    protected function get(Request $request): string
    {
        $id = $request->integer('id') ?: $this->scheduleId;
        if (! $id || $id <= 0) {
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
        name: 'Schedule Repository Tool Update',
        description: 'Update the repository data for the current schedule.',
        category: 'Scheduling'
    )]
    protected function update(Request $request): string
    {
        $id = $request->integer('id') ?: $this->scheduleId;
        $key = (string) $request->string('key');
        $value = $request->all()['value'] ?? null;
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        } else {
            $value = (string) $value;
        }

        if (! $id || $id <= 0 || empty($key)) {
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
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['get', 'update'])
                ->required()
                ->description('The action to perform: "get" to retrieve all data, or "update" to set a value.'),
            'id' => $schema->integer()
                ->description('The ID of the schedule. Optional if you are currently executing as part of a schedule.'),
            'key' => $schema->string()
                ->description('The key for the repository data (REQUIRED for "update" action).'),
            'value' => $schema->string()
                ->description('The value for the repository data (REQUIRED for "update" action).'),
        ];
    }
}
