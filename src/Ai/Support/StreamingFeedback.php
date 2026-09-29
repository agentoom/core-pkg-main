<?php

namespace Agentoom\Core\Ai\Support;

use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;

class StreamingFeedback
{
    /**
     * @return array{state: string, message?: string, tool?: string}|null
     */
    public static function forEvent(mixed $event): ?array
    {
        if ($event instanceof ToolCall) {
            return [
                'state' => 'using_tool',
                'tool' => $event->toolCall->name,
                'message' => 'Using tool: '.$event->toolCall->name,
            ];
        }

        if ($event instanceof ToolResult) {
            $message = $event->successful
                ? 'Processing tool result…'
                : 'Tool failed, recovering…';

            return [
                'state' => 'thinking',
                'tool' => $event->toolResult->name,
                'message' => $message,
            ];
        }

        if ($event instanceof TextDelta) {
            return [
                'state' => 'streaming',
            ];
        }

        return null;
    }
}
