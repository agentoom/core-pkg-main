<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Stringable;
use Throwable;

#[GroupVisibility('agentoom-core')]
class SSHTool implements Tool
{
    use HandlesToolActions;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        $fields = implode(', ', array_keys($this->requiredMappingFields()));

        return "Connect to a remote server via SSH and execute commands. You must provide a resource_id and a resource_mapping containing: {$fields}.";
    }

    /**
     * Execute a command via SSH on a remote server.
     */
    #[Sentinelable(
        name: 'SSH Tool Execute Command',
        description: 'Connect to a remote server via SSH and execute a shell command.',
        category: 'Server Management'
    )]
    protected function execute(Request $request): Stringable|string
    {
        $command = $request->string('command');

        Log::debug('SSHTool handle', [
            'command' => $command,
            'user_id' => auth()->id(),
        ]);

        $credential = $this->getResource($request);
        if (! $credential) {
            return 'Error: Could not resolve the specified server resource. Ensure you provided a valid resource_id and have permission to access it.';
        }

        $mapped = $this->mapResourceData($credential, $request);

        if ($error = $this->validateMappedData($mapped, $credential)) {
            return $error;
        }

        $ip = $mapped['ip'];
        $username = $mapped['username'] ?? 'root';
        $password = $mapped['password'] ?? null;
        $sshKey = $mapped['ssh_key'] ?? null;
        $port = (int) ($request->has('port') ? $request->integer('port') : ($mapped['port'] ?? 22));

        try {
            $ssh = new SSH2($ip, $port);

            // Set timeout for connection
            $ssh->setTimeout(10);

            if (! empty($sshKey)) {
                try {
                    $key = PublicKeyLoader::load($sshKey, $password);
                    if (! $ssh->login($username, $key)) {
                        return "Error: SSH authentication failed using key for server '{$credential->name}' ({$username}@{$ip}:{$port}). Check your SSH key and credentials.";
                    }
                } catch (Throwable $e) {
                    return "Error: Failed to load SSH key for server '{$credential->name}': ".$e->getMessage();
                }
            } elseif (! empty($password)) {
                if (! $ssh->login($username, $password)) {
                    return "Error: SSH authentication failed using password for server '{$credential->name}' ({$username}@{$ip}:{$port}). Check your credentials.";
                }
            } else {
                return "Error: No authentication method (password or SSH key) found for server '{$credential->name}'.";
            }

            // Connection and login successful, now execute command
            $ssh->enableQuietMode();
            $ssh->setTimeout(30);

            // Handle sudo if the command starts with it and we have a password
            if (str_starts_with(trim($command), 'sudo ') && ! empty($password)) {
                // We use sudo -S to read from stdin, and we use a pipe to send the password
                $fullCommand = 'echo '.escapeshellarg($password).' | sudo -S '.substr(trim($command), 5);
                $output = $ssh->exec($fullCommand);
            } else {
                $output = $ssh->exec($command);
            }

            $exitStatus = $ssh->getExitStatus();

            if ($exitStatus !== 0 && empty($output)) {
                $errorOutput = "Command execution failed with exit status {$exitStatus}.";
                Log::error('SSHTool command failed', [
                    'server_name' => $credential->name,
                    'command' => $command,
                    'exit_status' => $exitStatus,
                ]);

                return "Error executing command on '{$credential->name}': {$errorOutput}";
            }

            $response = "Successfully executed command on '{$credential->name}' ({$username}@{$ip}:{$port}):\n\nOutput:\n{$output}";

            Log::debug('SSHTool command successful', [
                'server_name' => $credential->name,
                'port' => $port,
                'exit_status' => $exitStatus,
                'output_length' => strlen($output),
            ]);

            return $response;

        } catch (Throwable $e) {
            Log::error("SSH Error for server '{$credential->name}': ".$e->getMessage(), [
                'exception' => $e,
                'server_name' => $credential->name,
                'ip' => $ip,
                'port' => $port,
                'username' => $username,
            ]);

            return "Error connecting to server '{$credential->name}' ({$username}@{$ip}:{$port}): ".$e->getMessage();
        }
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        $fields = implode(', ', array_keys($this->requiredMappingFields()));

        return [
            'action' => $schema->string()
                ->enum(['execute'])
                ->required()
                ->description('The action to perform: execute'),

            'resource_id' => $schema->string()
                ->required()
                ->description('The ID of the server resource to connect to.'),
            'resource_mapping' => $schema->string()
                ->required()
                ->description("A JSON mapping object for: {$fields}. Required fields: {$fields}. Optional: username, password, ssh_key, port. Example: {\"ip\": \"data.ip_address\", \"username\": \"data.username\"}"),
            'command' => $schema->string()
                ->required()
                ->description('The shell command to execute on the remote server.'),
            'port' => $schema->integer()
                ->description('The SSH port to connect to (overrides mapping if provided).'),
        ];
    }

    /**
     * Get the fields required in the resource_mapping.
     */
    protected function requiredMappingFields(): array
    {
        return [
            'ip' => 'The IP address or hostname of the server.',
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
