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
class ManageUserInformationTool implements Tool
{
    use HandlesToolActions;

    public function description(): Stringable|string
    {
        return 'Store and retrieve relevant information about the current user,
        such as their interests, hobbies, capabilities, preferences, and language.
        Use importance from 1 to 5 to indicate how strongly a memory should be prioritized.
        Use expires_at for temporary information. Always look for information about the current user to personalize the experience.';
    }

    #[Sentinelable(
        name: 'Manage User Information Tool Store',
        description: 'Store or update user information in the user knowledge base.',
        category: 'User Information'
    )]
    protected function store(Request $request): Stringable|string
    {
        $user = auth()->user();
        $title = trim((string) $request->string('title'));
        $content = trim((string) $request->string('content'));

        if (empty($title) || empty($content)) {
            return 'Error: Both title and content are required when storing user information.';
        }

        try {
            $memory = UserInformation::firstOrNew([
                'user_id' => $user->id,
                'title' => $title,
            ]);

            $memory->content = $content;

            if (! $memory->exists || $request->has('importance')) {
                $importance = (int) $request->integer('importance', 3);
                $memory->importance = min(max($importance, 1), 5);
            }

            if ($request->has('expires_at')) {
                $expiresAt = trim((string) $request->string('expires_at'));
                $memory->expires_at = $expiresAt === '' ? null : $expiresAt;
            }

            $memory->save();

            return "Successfully stored information about '{$title}' for user '{$user->name}' (importance {$memory->importance}/5).";
        } catch (\Throwable $e) {
            Log::error('ManageUserInformationTool storage error: '.$e->getMessage());

            return 'Error storing information: '.$e->getMessage();
        }
    }

    #[Sentinelable(
        name: 'Manage User Information Tool Retrieve',
        description: 'Retrieve active user information from the user knowledge base.',
        category: 'User Information'
    )]
    protected function retrieve(Request $request): Stringable|string
    {
        $user = auth()->user();
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
                return 'No personal information has been recorded for this user yet.';
            }

            $parts = $results->map(fn ($item) => "### {$item->title}\n{$item->content}\nImportance: {$item->importance}/5")->all();

            return "Stored information for user '{$user->name}':\n\n".implode("\n\n---\n\n", $parts);
        } catch (\Throwable $e) {
            Log::error('ManageUserInformationTool retrieval error: '.$e->getMessage());

            return 'Error retrieving information: '.$e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->required()
                ->enum(['store', 'retrieve'])
                ->description('The action to perform: "store" to save or update user information, or "retrieve" to search active information.'),
            'title' => $schema->string()
                ->description('Required for "store". A concise stable key/title (for example, "preferred_name").'),
            'content' => $schema->string()
                ->description('Required for "store". The information to store. Be concise.'),
            'importance' => $schema->integer()
                ->min(1)
                ->max(5)
                ->description('Optional for "store". Importance from 1 (low) to 5 (critical). Existing importance is preserved when omitted.'),
            'expires_at' => $schema->string()
                ->description('Optional for "store". ISO 8601 expiration date/time. Omit or pass an empty value for no expiration.'),
            'query' => $schema->string()
                ->description('Optional for "retrieve". A query to find specific active user information.'),
            'limit' => $schema->integer()
                ->default(5)
                ->description('Maximum number of entries to return (1-10, default 5).'),
        ];
    }
}
