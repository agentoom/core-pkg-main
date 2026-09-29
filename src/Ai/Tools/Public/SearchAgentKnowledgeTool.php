<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Services\TypesenseService;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class SearchAgentKnowledgeTool implements Tool
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
        return 'Search this agent\'s knowledge base (uploaded files and documents) to find information relevant to the user\'s question. Use this tool when the user asks something that might be answered by the agent\'s stored knowledge. Call it with the user question or a clear search query.';
    }

    /**
     * Search the agent's knowledge base.
     */
    #[Sentinelable(
        name: 'Search Agent Knowledge Tool Search',
        description: 'Search the agent knowledge base for relevant information.',
        category: 'Agent Knowledge'
    )]
    protected function search(Request $request): Stringable|string
    {
        if (! $this->agent) {
            return 'Error: Agent context not found.';
        }

        $typesense = new TypesenseService;
        $query = trim((string) $request->string('query'));
        $limit = (int) $request->integer('limit', 5);

        Log::debug('SearchAgentKnowledgeTool handle', [
            'query' => $query,
            'limit' => $limit,
            'agent_id' => $this->agent->id,
        ]);

        if ($query === '') {
            return 'Error: Please provide a non-empty search query.';
        }

        $limit = min(max($limit, 1), 10);

        try {
            $result = $typesense->searchAgentKnowledge($this->agent, $query, $limit);
            $hits = $result['hits'] ?? [];
            $found = $result['found'] ?? 0;

            if ($found === 0) {
                Log::debug('SearchAgentKnowledgeTool: No results found', ['query' => $query]);

                return 'No relevant knowledge found for this query in the agent\'s knowledge base.';
            }

            $parts = [];
            foreach ($hits as $i => $hit) {
                $doc = $hit['document'] ?? $hit;
                $text = $doc['knowledge'];
                if ($text !== '') {
                    $parts[] = '['.($i + 1)."]\n".$text;
                }
            }

            if (empty($parts)) {
                return 'No readable content in the search results.';
            }

            $response = "Found {$found} relevant passage(s) from the knowledge base:\n\n".implode("\n\n---\n\n", $parts);

            Log::debug('SearchAgentKnowledgeTool response', [
                'found' => $found,
                'parts_count' => count($parts),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::error('SearchAgentKnowledgeTool error: '.$e->getMessage(), [
                'query' => $query,
                'exception' => $e,
            ]);

            return 'Error searching knowledge base: '.$e->getMessage();
        }
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['search'])
                ->required()
                ->description('The action to perform: search'),

            'query' => $schema->string()
                ->required()
                ->description('The search query (e.g. the user\'s question or keywords to find in the knowledge base).'),
            'limit' => $schema->integer()
                ->default(5)
                ->description('Maximum number of passages to return (1-10, default 5).'),
        ];
    }
}
