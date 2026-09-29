<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentKnowledge;
use Agentoom\Core\Models\AgentKnowledgeFile;
use Agentoom\Core\Services\TypesenseService;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class ManageAgentKnowledgeTool implements Tool
{
    use HandlesToolActions;

    protected ?Agent $agent = null;

    public function __construct(
        ?Agent $agent = null,
    ) {
        $this->agent = $agent ?: $this->resolveAgent();
    }

    protected function resolveAgent(): ?Agent
    {
        $agentId = app()->bound('current_agent_id') ? app('current_agent_id') : null;

        return $agentId ? Agent::find($agentId) : null;
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return "Manage this agent's knowledge base. Use this tool to add new information (chunks) or modify existing ones when you find useful information to persist.
        CRITICAL: Before adding or modifying knowledge, you MUST explain the need to the user and request for approval/authorization.";
    }

    /**
     * Add a new knowledge chunk.
     */
    #[Sentinelable(
        name: 'Manage Agent Knowledge Tool Add Chunk',
        description: 'Add a knowledge chunk to the agent knowledge base.',
        category: 'Agent Knowledge'
    )]
    protected function addChunk(Request $request): string
    {
        if (! $this->agent) {
            return 'Error: Agent context not found.';
        }

        $content = trim((string) $request->string('content'));
        $summary = trim((string) $request->string('summary', ''));

        if ($content === '') {
            return 'Error: Knowledge content cannot be empty.';
        }

        $file = $this->getOrCreateManualKnowledgeFile();

        if (! $file) {
            return 'Error: Failed to access knowledge storage.';
        }

        $chunk = AgentKnowledge::create([
            'agent_id' => $file->agent_id,
            'project_id' => $file->project_id,
            'file_id' => $file->id,
            'knowledge' => $content,
            'summary' => $summary ?: substr($content, 0, 100).'...',
        ]);

        return "Successfully added new knowledge chunk (ID: {$chunk->id}) to the agent's knowledge base.";
    }

    /**
     * Edit an existing knowledge chunk.
     */
    #[Sentinelable(
        name: 'Manage Agent Knowledge Tool Edit Chunk',
        description: 'Edit an existing knowledge chunk in the agent knowledge base.',
        category: 'Agent Knowledge'
    )]
    protected function editChunk(Request $request): string
    {
        if (! $this->agent) {
            return 'Error: Agent context not found.';
        }

        $chunkId = $request->integer('chunk_id');
        $content = trim((string) $request->string('content'));
        $summary = trim((string) $request->string('summary', ''));

        if ($chunkId <= 0) {
            return 'Error: A valid chunk_id is required.';
        }

        $chunk = AgentKnowledge::query()
            ->where(function ($q) {
                $q->where('agent_id', $this->agent->id);
                if ($this->agent->project_id) {
                    $q->orWhere('project_id', $this->agent->project_id);
                }
            })
            ->find($chunkId);

        if (! $chunk) {
            return "Error: Knowledge chunk with ID {$chunkId} not found for this agent.";
        }

        if ($content !== '') {
            $chunk->knowledge = $content;
        }

        if ($summary !== '') {
            $chunk->summary = $summary;
        }

        $chunk->save();

        return "Successfully updated knowledge chunk (ID: {$chunk->id}).";
    }

    /**
     * Search for knowledge chunks.
     */
    #[Sentinelable(
        name: 'Manage Agent Knowledge Tool Search Chunks',
        description: 'Search knowledge chunks in the agent knowledge base.',
        category: 'Agent Knowledge'
    )]
    protected function searchChunks(Request $request): string
    {
        if (! $this->agent) {
            return 'Error: Agent context not found.';
        }

        $typesense = new TypesenseService;
        $query = trim((string) $request->string('query'));
        $limit = (int) $request->integer('limit', 5);

        if ($query === '') {
            return 'Error: Please provide a non-empty search query.';
        }

        $limit = min(max($limit, 1), 10);

        try {
            $result = $typesense->searchAgentKnowledge($this->agent, $query, $limit);
            $hits = $result['hits'] ?? [];
            $found = $result['found'] ?? 0;

            if ($found === 0) {
                return 'No relevant knowledge found for this query.';
            }

            $parts = [];
            foreach ($hits as $hit) {
                $doc = $hit['document'] ?? $hit;
                $id = $doc['id'] ?? 'unknown';
                $text = $doc['knowledge'] ?? '';
                $summary = $doc['summary'] ?? '';

                $parts[] = "ID: {$id}\nSummary: {$summary}\nContent: {$text}";
            }

            return "Found {$found} relevant chunk(s):\n\n".implode("\n\n---\n\n", $parts);
        } catch (\Throwable $e) {
            return 'Error searching chunks: '.$e->getMessage();
        }
    }

    private function getOrCreateManualKnowledgeFile(): ?AgentKnowledgeFile
    {
        if (! $this->agent) {
            return null;
        }

        // If agent belongs to a project, store manual knowledge at the project level
        // so it's shared with other agents in the same project.
        if ($this->agent->project_id) {
            return AgentKnowledgeFile::firstOrCreate(
                [
                    'project_id' => $this->agent->project_id,
                    'agent_id' => null,
                    'name' => 'Manual Project Knowledge',
                ],
                [
                    'path' => 'manual-project',
                    'type' => AgentKnowledgeFile::TYPE_FILE,
                    'status' => AgentKnowledgeFile::STATUS_COMPLETED,
                ]
            );
        }

        return AgentKnowledgeFile::firstOrCreate(
            [
                'agent_id' => $this->agent->id,
                'name' => 'Manual Knowledge',
            ],
            [
                'path' => 'manual',
                'type' => AgentKnowledgeFile::TYPE_FILE,
                'status' => AgentKnowledgeFile::STATUS_COMPLETED,
            ]
        );
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['add_chunk', 'edit_chunk', 'search_chunks'])
                ->required()
                ->description('The action to perform: add_chunk, edit_chunk, or search_chunks.'),

            'content' => $schema->string()
                ->description('The knowledge content to add or the new content for an existing chunk.'),

            'summary' => $schema->string()
                ->description('A brief summary of the knowledge chunk (optional).'),

            'chunk_id' => $schema->integer()
                ->description('The ID of the chunk to edit (required for edit_chunk).'),

            'query' => $schema->string()
                ->description('The search query to find chunks (required for search_chunks).'),

            'limit' => $schema->integer()
                ->default(5)
                ->description('Maximum number of chunks to return in search (1-10, default 5).'),
        ];
    }
}
