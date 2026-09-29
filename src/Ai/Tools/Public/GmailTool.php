<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Services\Api\GmailService;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class GmailTool implements Tool
{
    use HandlesToolActions;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Connect to Gmail to list, read, send, trash, and mark emails as spam.
        You can filter emails by date or query, and limit the number of results.';
    }

    /**
     * List Gmail messages.
     */
    #[Sentinelable(
        name: 'Gmail Tool List Messages',
        description: 'List Gmail messages with optional query filters.',
        category: 'Email'
    )]
    protected function listMessages(Request $request): Stringable|string
    {
        return app(GmailService::class)->listMessages(
            query: $request->string('query'),
            limit: $request->integer('limit', 0),
            resourceId: $request->string('resource_id'),
            resourceMapping: $this->getCleanParams($request, 'resource_mapping')
        );
    }

    /**
     * Get a specific Gmail message content.
     */
    #[Sentinelable(
        name: 'Gmail Tool Get Message',
        description: 'Get the content of a specific Gmail message.',
        category: 'Email'
    )]
    protected function getMessage(Request $request): Stringable|string
    {
        return app(GmailService::class)->getMessage(
            messageId: (string) $request->string('message_id'),
            resourceId: $request->string('resource_id'),
            resourceMapping: $this->getCleanParams($request, 'resource_mapping')
        );
    }

    /**
     * Send an email.
     */
    #[Sentinelable(
        name: 'Gmail Tool Send Message',
        description: 'Send an email via Gmail.',
        category: 'Email'
    )]
    protected function sendMessage(Request $request): Stringable|string
    {
        if ($error = $this->checkForForbiddenSecurityTerms($request)) {
            return $error;
        }

        return app(GmailService::class)->sendMessage(
            to: (string) $request->string('to'),
            subject: (string) $request->string('subject'),
            body: (string) $request->string('body'),
            resourceId: $request->string('resource_id'),
            resourceMapping: $this->getCleanParams($request, 'resource_mapping')
        );
    }

    /**
     * Move a message to trash.
     */
    #[Sentinelable(
        name: 'Gmail Tool Trash Message',
        description: 'Move a Gmail message to trash.',
        category: 'Email'
    )]
    protected function trashMessage(Request $request): Stringable|string
    {
        return app(GmailService::class)->trashMessage(
            messageId: (string) $request->string('message_id'),
            resourceId: $request->string('resource_id'),
            resourceMapping: $this->getCleanParams($request, 'resource_mapping')
        );
    }

    /**
     * Mark a message as spam.
     */
    #[Sentinelable(
        name: 'Gmail Tool Mark As Spam',
        description: 'Mark a Gmail message as spam.',
        category: 'Email'
    )]
    protected function markAsSpam(Request $request): Stringable|string
    {
        return app(GmailService::class)->markAsSpam(
            messageId: (string) $request->string('message_id'),
            resourceId: $request->string('resource_id'),
            resourceMapping: $this->getCleanParams($request, 'resource_mapping')
        );
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['list_messages', 'get_message', 'send_message', 'trash_message', 'mark_as_spam'])
                ->required()
                ->description('The action to perform.'),
            'resource_id' => $schema->string()
                ->description('Optional. The ID of the Gmail resource if using a specific connection.'),
            'resource_mapping' => $schema->string()
                ->description('Optional. JSON mapping for resource fields.'),
            'query' => $schema->string()
                ->description('Optional for "list_messages". Gmail search query (e.g., "after:2023/01/01", "from:example@test.com").'),
            'limit' => $schema->integer()
                ->default(0)
                ->description('Optional for "list_messages". Maximum number of messages to return.'),
            'message_id' => $schema->string()
                ->description('Required for "get_message", "trash_message", and "mark_as_spam". The ID of the Gmail message.'),
            'to' => $schema->string()
                ->description('Required for "send_message". The recipient email address.'),
            'subject' => $schema->string()
                ->description('Required for "send_message". The email subject.'),
            'body' => $schema->string()
                ->description('Required for "send_message". The email body content. You MUST use Markdown or HTML for better formatting and readability. Your response will be automatically converted to clean, professional HTML. Include clear headings, bullet points, and paragraphs to ensure the email is easy to read. Do not include the email subject line in the body as it is already handled by the subject field. Avoid sending large blocks of unformatted text.'),
        ];
    }

    /**
     * Get and clean parameters from the request, handling various common LLM input formats.
     */
    protected function getCleanParams(Request $request, string $key = 'params', bool $mergeObjects = true): array
    {
        $params = $request->all($key)[$key] ?? [];

        if (is_string($params)) {
            $decoded = json_decode($params, true);
            if (is_array($decoded)) {
                $params = $decoded;
            }
        }

        if (! is_array($params)) {
            $params = $request->array($key);
        }

        if (count($params) === 1 && isset($params[0]) && is_string($params[0])) {
            $decoded = json_decode($params[0], true);
            if (is_array($decoded)) {
                $params = $decoded;
            }
        }

        if ($mergeObjects && array_is_list($params) && count($params) > 0 && is_array($params[0])) {
            $merged = [];
            foreach ($params as $p) {
                if (is_array($p)) {
                    $merged = array_merge($merged, $p);
                }
            }
            $params = $merged;
        }

        return $params;
    }
}
