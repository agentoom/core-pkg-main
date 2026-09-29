<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class DateTimeTool implements Tool
{
    use HandlesToolActions;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Retrieve the current date, time, and day of the week. Useful for scheduling, time-relative queries, and general awareness of the current time.';
    }

    /**
     * Get the current date and time.
     */
    #[Sentinelable(
        name: 'Date Time Tool Get Current DateTime',
        description: 'Get the current date and time.',
        category: 'Utilities'
    )]
    protected function getCurrentDateTime(Request $request): string
    {
        $timezone = $request->string('timezone') ?: config('app.timezone');

        try {
            $now = now()->timezone($timezone);
        } catch (\Exception $e) {
            return "Error: Invalid timezone '{$timezone}'.";
        }

        return 'Current Date and Time: '.$now->toDateTimeString()."\n".
               'Timezone: '.$timezone."\n".
               'Day of Week: '.$now->dayName;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['get_current_date_time'])
                ->required()
                ->description('The action to perform.'),
            'timezone' => $schema->string()->description('The timezone to retrieve the date and time for (e.g., "UTC", "America/New_York"). Defaults to the application timezone.'),
        ];
    }
}
