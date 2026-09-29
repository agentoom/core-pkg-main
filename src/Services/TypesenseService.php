<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentKnowledge;
use Illuminate\Support\Facades\Config;
use Typesense\Client;

class TypesenseService
{
    public $client;

    public $prefix;

    public function __construct()
    {
        $this->prefix = Config::get('scout.prefix', '');
        $this->client = new Client([
            'nodes' => [
                [
                    'host' => Config::get('scout.typesense.client-settings.nodes.0.host'),
                    'port' => Config::get('scout.typesense.client-settings.nodes.0.port'),
                    'protocol' => Config::get('scout.typesense.client-settings.nodes.0.protocol'),
                ],
            ],
            'api_key' => Config::get('scout.typesense.client-settings.api_key'),
        ]);
    }

    /**
     * Search agent knowledge in Typesense (vector + keyword) scoped to the given agent.
     *
     * @return array{found: int, hits: array<int, array{document: array{id?: string, knowledge?: string, summary?: string}}>}
     */
    public function searchAgentKnowledge(Agent $agent, string $query, int $limit = 5): array
    {
        $model = new AgentKnowledge;
        $collectionName = $this->getPrefixedName($model->searchableAs());

        if (! $this->checkCollectionExists($collectionName)) {
            return ['found' => 0, 'hits' => []];
        }

        $filterBy = 'agent_id:='.(string) $agent->id;

        if ($agent->project_id) {
            $filterBy .= ' OR project_id:='.(string) $agent->project_id;
        }

        $params = [
            'q' => $query,
            'query_by' => 'embedding',
            'vector_query' => 'embedding:([], k:10, alpha: 0.5)',
            'exclude_fields' => 'embedding',
            'filter_by' => $filterBy,
            'limit' => $limit,
        ];

        $result = $this->client->collections[$collectionName]->documents->search($params);

        return [
            'found' => $result['found'] ?? 0,
            'hits' => $result['hits'] ?? [],
        ];
    }

    public function search(string $collectionName, array $query)
    {
        $collectionName = $this->getPrefixedName($collectionName);

        if ($this->checkCollectionExists($collectionName)) {
            return $this->client->collections[$collectionName]->documents->search($query);
        }

        return null;
    }

    /**
     * Check if a collection exists.
     */
    public function checkCollectionExists(string $collectionName): bool
    {
        $collectionName = $this->getPrefixedName($collectionName);

        try {
            $this->client->collections[$collectionName]->retrieve();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete a specific collection.
     */
    public function deleteCollection(string $collectionName): bool
    {
        $collectionName = $this->getPrefixedName($collectionName);

        if ($this->checkCollectionExists($collectionName)) {
            $this->client->collections[$collectionName]->delete();

            return true;
        }

        return false;
    }

    /**
     * Get all collections.
     */
    public function getAllCollections(): array
    {
        return $this->client->collections->retrieve();
    }

    public function getRules()
    {
        return $this->client->analytics->rules()->retrieve();
    }

    public function getSpecificCollections($collection_name)
    {
        $collection_name = $this->getPrefixedName($collection_name);

        if ($this->checkCollectionExists($collection_name)) {
            return $this->client->collections[$collection_name]->retrieve();
        }
    }

    public function deleteSpecificCollections($collection_name)
    {
        $collection_name = $this->getPrefixedName($collection_name);

        return $this->deleteCollection($collection_name);
    }

    public function truncateSpecificCollection($collection_name)
    {
        $prefixedName = $this->getPrefixedName($collection_name);

        if ($this->checkCollectionExists($prefixedName)) {
            return $this->client->collections[$prefixedName]->documents->delete(['truncate' => true, 'filter_by' => 'type:'.$collection_name]);
        }
    }

    public function collection(string $name)
    {
        return $this->client->collections[$this->getPrefixedName($name)];
    }

    private function getPrefixedName(string $name): string
    {
        if (empty($this->prefix)) {
            return $name;
        }

        if (str_starts_with($name, $this->prefix)) {
            return $name;
        }

        return $this->prefix.$name;
    }
}
