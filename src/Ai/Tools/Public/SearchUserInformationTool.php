<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Models\UserInformation;
use Agentoom\Core\Services\TypesenseService;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class SearchUserInformationTool implements Tool
{
    use HandlesToolActions;

    public function description(): Stringable|string
    {
        return 'Retrieve active relevant information about the current user, such as interests, capabilities, preferences, and language. Results include memory importance and exclude expired entries.';
    }

    #[Sentinelable(
        name: 'Search User Information Tool Search',
        description: 'Search active stored information about the current user.',
        category: 'User Information'
    )]
    protected function search(Request $request): Stringable|string
    {
        $user = auth()->user();

        if (! $user) {
            return 'Error: No authenticated user found. User information can only be retrieved for authenticated users.';
        }

        $query = trim((string) $request->string('query'));
        $limit = min(max((int) $request->integer('limit', 5), 1), 10);

        try {
            if (empty($query)) {
                $results = UserInformation::where('user_id', $user->id)
                    ->where(function ($query): void {
                        $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->orderByDesc('importance')
                    ->latest('updated_at')
                    ->limit($limit)
                    ->get();
            } else {
                $typesense = new TypesenseService;
                $model = new UserInformation;

                if (! $typesense->checkCollectionExists($model->searchableAs())) {
                    $results = UserInformation::where('user_id', $user->id)
                        ->where(function ($query): void {
                            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        })
                        ->where(function ($q) use ($query) {
                            $q->where('title', 'like', "%{$query}%")
                                ->orWhere('content', 'like', "%{$query}%");
                        })
                        ->orderByDesc('importance')
                        ->latest('updated_at')
                        ->limit($limit)
                        ->get();
                } else {
                    $searchParams = [
                        'q' => $query,
                        'query_by' => 'embedding',
                        'vector_query' => 'embedding:([], k:20, alpha: 0.5)',
                        'exclude_fields' => 'embedding',
                        'filter_by' => 'user_id:='.(string) $user->id,
                        'limit' => $limit,
                    ];

                    $searchResult = $typesense->collection($model->searchableAs())->documents->search($searchParams);
                    $hits = $searchResult['hits'] ?? [];
                    $parts = [];

                    foreach ($hits as $hit) {
                        $doc = $hit['document'] ?? $hit;
                        $memory = UserInformation::query()
                            ->where('user_id', $user->id)
                            ->whereKey($doc['id'] ?? 0)
                            ->where(function ($query): void {
                                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                            })
                            ->first();

                        if (! $memory) {
                            continue;
                        }

                        $parts[] = "### {$memory->title}\n{$memory->content}\nImportance: {$memory->importance}/5";
                    }

                    if (empty($parts)) {
                        return "No relevant information found for query '{$query}'.";
                    }

                    return 'Found '.count($parts)." relevant piece(s) of information for user '{$user->name}':\n\n".implode("\n\n---\n\n", $parts);
                }
            }

            if ($results->isEmpty()) {
                return "No stored information found for user '{$user->name}'.";
            }

            $parts = $results->map(fn ($item) => "### {$item->title}\n{$item->content}\nImportance: {$item->importance}/5")->all();

            return "Stored information for user '{$user->name}':\n\n".implode("\n\n---\n\n", $parts);
        } catch (\Throwable $e) {
            Log::error('SearchUserInformationTool retrieval error: '.$e->getMessage());

            return 'Error retrieving information: '.$e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['search'])
                ->required()
                ->description('The action to perform: search'),
            'query' => $schema->string()
                ->description('Optional. A query to find specific active user information. If omitted, returns the most important entries.'),
            'limit' => $schema->integer()
                ->default(5)
                ->description('Maximum number of entries to return (1-10, default 5).'),
        ];
    }
}
