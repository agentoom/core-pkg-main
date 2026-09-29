<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Services\ImapService;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class ImapSafeTool implements Tool
{
    use HandlesToolActions;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Access IMAP email accounts for reading messages and creating drafts.';
    }

    /**
     * List all available mailboxes.
     */
    #[Sentinelable(
        name: 'IMAP Safe Tool List Mailboxes',
        description: 'List all available mailboxes in the IMAP email account.',
        category: 'Email'
    )]
    protected function listMailboxes(Request $request): Stringable|string
    {
        try {
            $config = $this->getImapConfig($request);

            return json_encode(app(ImapService::class)->listMailboxes($config), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * List messages in a mailbox.
     */
    #[Sentinelable(
        name: 'IMAP Safe Tool List Messages',
        description: 'List messages in a mailbox of the IMAP email account.',
        category: 'Email'
    )]
    protected function listMessages(Request $request): Stringable|string
    {
        try {
            $config = $this->getImapConfig($request);

            return json_encode(app(ImapService::class)->listMessages(
                $config,
                (string) $request->string('mailbox', 'INBOX'),
                (int) $request->integer('limit', 10)
            ), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * Get a specific message by UID.
     */
    #[Sentinelable(
        name: 'IMAP Safe Tool Get Message',
        description: 'Retrieve a specific email message by UID from the IMAP account.',
        category: 'Email'
    )]
    protected function getMessage(Request $request): Stringable|string
    {
        try {
            $config = $this->getImapConfig($request);

            return json_encode(app(ImapService::class)->getMessage(
                $config,
                (string) $request->string('mailbox', 'INBOX'),
                (int) $request->integer('uid')
            ), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * Create a draft email.
     */
    #[Sentinelable(
        name: 'IMAP Safe Tool Create Draft',
        description: 'Create a draft email in the IMAP account.',
        category: 'Email'
    )]
    protected function createDraft(Request $request): Stringable|string
    {
        if ($error = $this->checkForForbiddenSecurityTerms($request)) {
            return $error;
        }

        try {
            $config = $this->getImapConfig($request);
            $result = app(ImapService::class)->createDraft(
                $config,
                (string) $request->string('to'),
                (string) $request->string('subject'),
                (string) $request->string('body')
            );

            return $result ? 'Draft created successfully.' : 'Failed to create draft.';
        } catch (\Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['list_mailboxes', 'list_messages', 'get_message', 'create_draft'])
                ->required()
                ->description('The action to perform: list_mailboxes, list_messages, get_message, or create_draft'),

            'resource_id' => $schema->string()
                ->description('Optional resource ID for IMAP resources. If omitted, system configuration will be used.'),

            'resource_mapping' => $schema->object()
                ->description('Mapping of resource fields to host, port, username, password, encryption.'),

            'mailbox' => $schema->string()
                ->description('The mailbox to use (default: INBOX).'),

            'limit' => $schema->integer()
                ->description('Number of messages to list (default: 10).'),

            'uid' => $schema->integer()
                ->description('The unique identifier of the message.'),

            'to' => $schema->string()
                ->description('Recipient for create_draft.'),

            'subject' => $schema->string()
                ->description('Subject for create_draft.'),

            'body' => $schema->string()
                ->description('Body for create_draft. You MUST use Markdown or HTML for better formatting and readability. Your response will be automatically converted to clean, professional HTML. Include clear headings, bullet points, and paragraphs. Do not include the email subject line in the body as it is already handled by the subject field.'),
        ];
    }

    /**
     * Resolve IMAP configuration.
     */
    protected function getImapConfig(Request $request): array
    {
        $resource = $this->getResource($request);
        if ($resource) {
            $mapped = $this->mapResourceData($resource, $request);
            if ($error = $this->validateMappedData($mapped, $resource)) {
                throw new \Exception($error);
            }
            $mapped['encryption'] = $mapped['encryption'] ?? 'ssl';
            $mapped['port'] = $mapped['port'] ?? ($mapped['encryption'] === 'ssl' ? '993' : '143');

            return $mapped;
        }

        return app(ImapService::class)->getSystemConfig();
    }

    /**
     * Required fields for mapping.
     */
    protected function requiredMappingFields(): array
    {
        return [
            'host' => 'IMAP Host',
            'username' => 'IMAP Username',
            'password' => 'IMAP Password',
        ];
    }

    /**
     * Resolve the resource from the request and user context.
     */
    protected function getResource(Request $request): ?Resource
    {
        $resourceId = (string) $request->string('resource_id');
        $user = auth()->user();

        if (! $user || trim($resourceId) === '') {
            return null;
        }

        if (str_starts_with($resourceId, 'global:')) {
            $id = substr($resourceId, 7);

            return Resource::visibleTo($user)->with(['resourceType', 'user'])->find($id);
        }

        return Resource::visibleTo($user)->with(['resourceType', 'user'])->find($resourceId);
    }

    /**
     * Map resource data using the schema/mapping provided by the agent.
     */
    protected function mapResourceData(Resource $resource, Request $request): array
    {
        $mapping = $this->getCleanParams($request, 'resource_mapping');
        $mapped = [];
        $context = [
            'data' => $resource->getDecryptedData(),
            'resource' => $resource->toArray(),
            'name' => $resource->name,
            'description' => $resource->description,
        ];

        foreach ($mapping as $targetKey => $sourcePath) {
            if (! is_string($sourcePath)) {
                continue;
            }

            if (preg_match('/\[ENCRYPTED_REFERENCE:([a-zA-Z0-9_\-]+)\]/', $sourcePath, $matches)) {
                $value = $resource->getDecryptedData()[$matches[1]] ?? null;
            } else {
                $value = data_get($context, $sourcePath);
                if ($value === null && ! str_contains($sourcePath, '.')) {
                    $value = data_get($context, "data.{$sourcePath}");
                }
            }

            if ($value !== null) {
                $mapped[$targetKey] = $value;
            }
        }

        return $this->resolveEncryptedReferences($mapped, $resource);
    }

    /**
     * Resolve any encrypted references in an array using the provided resource.
     */
    protected function resolveEncryptedReferences(array $data, Resource $resource): array
    {
        $resourceData = $resource->getDecryptedData();

        array_walk_recursive($data, function (&$value) use ($resourceData) {
            if (is_string($value) && preg_match('/\[ENCRYPTED_REFERENCE:([a-zA-Z0-9_\-]+)\]/', $value, $matches)) {
                if (isset($resourceData[$matches[1]])) {
                    $value = $resourceData[$matches[1]];
                }
            }
        });

        return $data;
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

        $resourceId = (string) $request->string('resource_id');
        if ($key !== 'resource_mapping' && $resourceId !== '') {
            $resource = $this->getResource($request);
            if ($resource) {
                $params = $this->resolveEncryptedReferences($params, $resource);
            }
        }

        return $params;
    }

    /**
     * Validate that all required fields are present in the mapped data.
     */
    protected function validateMappedData(array $mapped, Resource $resource): ?string
    {
        $missingFields = [];
        foreach ($this->requiredMappingFields() as $field => $description) {
            $val = $mapped[$field] ?? null;
            if ($val === null || trim(is_array($val) ? json_encode($val) : (string) $val) === '') {
                $missingFields[] = "{$field} ({$description})";
            }
        }

        if (! empty($missingFields)) {
            return "Error: The resource '{$resource->name}' (ID: {$resource->id}) is missing required information for this tool: ".implode(', ', $missingFields).'. Please check if the resource has these fields or if your resource_mapping is correct.';
        }

        return null;
    }
}
