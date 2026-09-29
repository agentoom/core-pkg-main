<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class AgentFileTool implements Tool
{
    use HandlesToolActions;

    public function description(): Stringable|string
    {
        return 'Store, retrieve, and manage files or images in a persistent storage. Files are isolated by project or agent context, allowing data to be shared between agents in the same project or persisted across executions.';
    }

    #[Sentinelable(
        name: 'Agent File Tool GetStoragePath',
        description: 'Store, retrieve, and manage files or images in a persistent storage. Files are isolated by project or agent context, allowing data to be shared between agents in the same project or persisted across executions.',
        category: 'File Management'
    )]
    protected function getStoragePath(Request $request): ?string
    {
        $currentProjectId = app()->bound('current_project_id') ? app('current_project_id') : null;
        $projectId = $request->integer('project_id') ?: $currentProjectId;
        $agentId = app()->bound('current_agent_id') ? app('current_agent_id') : null;

        $user = auth()->user();

        if ($projectId) {
            // Verify project access if it's not the current context or if we want to be safe
            if ($user && ! $user->hasAnyRole(['superadmin', 'admin'])) {
                $project = Project::find($projectId);
                $hasAccess = $project && (
                    $project->user_id === $user->id ||
                    $project->system_user_id === $user->id ||
                    $project->assignedUsers()->where('users.id', $user->id)->exists()
                );

                if (! $hasAccess) {
                    return null;
                }
            }

            return "projects/{$projectId}/files";
        }

        if ($agentId) {
            return "agents/{$agentId}/files";
        }

        return 'general/files';
    }

    #[Sentinelable(
        name: 'Agent File Tool Save',
        description: 'Save a file or image in a persistent storage. Files are isolated by project or agent context, allowing data to be shared between agents in the same project or persisted across executions.',
        category: 'File Management'
    )]
    public function saveFile(Request $request): string
    {
        $filename = (string) $request->string('filename');
        $content = (string) $request->string('content');
        $base64 = $request->boolean('base64', false);

        if (empty($filename)) {
            return 'Error: filename is required.';
        }

        if ($base64) {
            $content = base64_decode($content);
        }

        $path = $this->getStoragePath($request);
        if ($path === null) {
            return 'Error: Unauthorized to access this project context.';
        }

        $fullPath = $path.'/'.$filename;
        Storage::disk('local')->put($fullPath, $content);

        return "File '{$filename}' saved successfully.";
    }

    #[Sentinelable(
        name: 'Agent File Tool Read',
        description: 'Read a file or image from a persistent storage. Files are isolated by project or agent context, allowing data to be shared between agents in the same project or persisted across executions.',
        category: 'File Management'
    )]
    public function readFile(Request $request): string
    {
        $filename = (string) $request->string('filename');
        $path = $this->getStoragePath($request);
        if ($path === null) {
            return 'Error: Unauthorized to access this project context.';
        }

        $fullPath = $path.'/'.$filename;

        if (! Storage::disk('local')->exists($fullPath)) {
            return "Error: File '{$filename}' not found.";
        }

        return (string) Storage::disk('local')->get($fullPath);
    }

    #[Sentinelable(
        name: 'Agent File Tool ListFiles',
        description: 'List files or images in a persistent storage. Files are isolated by project or agent context, allowing data to be shared between agents in the same project or persisted across executions.',
        category: 'File Management'
    )]
    public function listFiles(Request $request): string
    {
        $path = $this->getStoragePath($request);
        if ($path === null) {
            return 'Error: Unauthorized to access this project context.';
        }

        $files = Storage::disk('local')->files($path);

        if (empty($files)) {
            return 'No files found in the current context.';
        }

        $filenames = array_map(fn ($f) => basename($f), $files);

        return 'Files found: '.implode(', ', $filenames);
    }

    #[Sentinelable(
        name: 'Agent File Tool Delete',
        description: 'Delete a file or image from a persistent storage. Files are isolated by project or agent context, allowing data to be shared between agents in the same project or persisted across executions.',
        category: 'File Management'
    )]
    public function deleteFile(Request $request): string
    {
        $filename = (string) $request->string('filename');
        $path = $this->getStoragePath($request);
        if ($path === null) {
            return 'Error: Unauthorized to access this project context.';
        }

        $fullPath = $path.'/'.$filename;

        if (! Storage::disk('local')->exists($fullPath)) {
            return "Error: File '{$filename}' not found.";
        }

        Storage::disk('local')->delete($fullPath);

        return "File '{$filename}' deleted successfully.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['save_file', 'read_file', 'list_files', 'delete_file'])
                ->required()
                ->description('The action to perform'),
            'filename' => $schema->string()
                ->description('The name of the file (including extension)'),
            'content' => $schema->string()
                ->description('The content to save (required for save_file)'),
            'base64' => $schema->boolean()
                ->description('Set to true if content is base64 encoded'),
            'project_id' => $schema->integer()
                ->description('Optional project ID to override the current context'),
        ];
    }
}
