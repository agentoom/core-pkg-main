<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentLearnedSkill;
use Agentoom\Core\Services\TypesenseService;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class SearchAgentLearnedSkillsTool implements Tool
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
        return 'Retrieve concise instructions about how to achieve specific tasks that have been learned through experience. Use this to find quick references for complex procedures previously figured out.';
    }

    /**
     * Search for learned skills/procedures.
     */
    #[Sentinelable(
        name: 'Search Agent Learned Skills Tool Search',
        description: 'Search the agent learned skills for relevant procedures.',
        category: 'Agent Skills'
    )]
    protected function search(Request $request): Stringable|string
    {
        if (! $this->agent) {
            return 'Error: Agent context not found.';
        }

        $query = trim((string) $request->string('query'));
        $limit = (int) $request->integer('limit', 5);
        $limit = min(max($limit, 1), 10);

        try {
            if (empty($query)) {
                $results = AgentLearnedSkill::where('agent_id', $this->agent->id)
                    ->latest()
                    ->limit($limit)
                    ->get();
            } else {
                $typesense = new TypesenseService;
                $model = new AgentLearnedSkill;

                // Fallback to basic search if Typesense is not available or collection doesn't exist
                if (! $typesense->checkCollectionExists($model->searchableAs())) {
                    $results = AgentLearnedSkill::where('agent_id', $this->agent->id)
                        ->where(function ($q) use ($query) {
                            $q->where('title', 'like', "%{$query}%")
                                ->orWhere('content', 'like', "%{$query}%");
                        })
                        ->limit($limit)
                        ->get();
                } else {
                    $searchParams = [
                        'q' => $query,
                        'query_by' => 'embedding',
                        'vector_query' => 'embedding:([], k:20, alpha: 0.5)',
                        'exclude_fields' => 'embedding',
                        'filter_by' => 'agent_id:='.(string) $this->agent->id,
                        'limit' => $limit,
                    ];

                    $searchResult = $typesense->collection($model->searchableAs())->documents->search($searchParams);

                    $hits = $searchResult['hits'] ?? [];
                    if (empty($hits)) {
                        return "No relevant learned skills found for query '{$query}'. This agent hasn't recorded any skills matching this topic yet.";
                    }

                    $parts = [];
                    foreach ($hits as $i => $hit) {
                        $doc = $hit['document'] ?? $hit;
                        $parts[] = '### Learned Skill: '.($doc['title'] ?? 'Untitled')."\n".($doc['content'] ?? '');
                    }

                    return 'Found '.count($parts)." relevant learned skill(s) for agent '{$this->agent->name}':\n\n".implode("\n\n---\n\n", $parts);
                }
            }

            if ($results->isEmpty()) {
                return 'No learned skills or procedures have been recorded for this agent yet.';
            }

            $parts = $results->map(fn ($item) => "### Learned Skill: {$item->title}\n{$item->content}")->all();

            return "Learned skills for agent '{$this->agent->name}':\n\n".implode("\n\n---\n\n", $parts);
        } catch (\Throwable $e) {
            Log::error('SearchAgentLearnedSkillsTool retrieval error: '.$e->getMessage());

            return 'Error retrieving learned skills: '.$e->getMessage();
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
                ->description('Optional. A search query to find specific learned skills. If omitted, returns the most recent entries.'),
            'limit' => $schema->integer()
                ->default(5)
                ->description('Maximum number of entries to return (1-10, default 5).'),
        ];
    }
}
