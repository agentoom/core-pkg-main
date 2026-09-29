<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Models\AiModelPricing;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProviderModelSyncService
{
    public function syncProvider(Provider $provider): array
    {
        try {
            $models = $this->fetchModelsForProvider($provider);
            if ($models === []) {
                return ['success' => false, 'error' => 'No valid models returned from provider API'];
            }

            $syncedCount = 0;
            $existingModelNames = [];
            foreach ($models as $model) {
                $modelName = is_string($model) ? $model : ($model['id'] ?? $model['name'] ?? null);
                if (! is_string($modelName) || trim($modelName) === '') {
                    continue;
                }
                $modelName = trim($modelName);
                $existingModelNames[] = $modelName;
                $modelData = is_array($model) ? $model : [];
                if (($provider->driver ?: $provider->name) === 'openrouter' && isset($modelData['pricing']) && is_array($modelData['pricing'])) {
                    $modelData['pricing'] = $this->normalizeOpenRouterPricing($modelData['pricing']);
                }

                ProviderModel::updateOrCreate(
                    ['provider_id' => $provider->id, 'model_name' => $modelName],
                    ['model_features' => $modelData, 'is_active' => true]
                );
                $this->updateAiModelPricing($provider, $modelName, $modelData);
                $syncedCount++;
            }

            if ($syncedCount === 0) {
                return ['success' => false, 'error' => 'Provider response contained no usable model identifiers'];
            }

            $inactiveCount = ProviderModel::query()
                ->where('provider_id', $provider->id)
                ->whereNotIn('model_name', array_unique($existingModelNames))
                ->update(['is_active' => false]);

            return ['success' => true, 'synced_count' => $syncedCount, 'inactive_count' => $inactiveCount, 'deleted_count' => 0];
        } catch (\Throwable $e) {
            Log::error("Error syncing models for provider {$provider->name}: ".$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function fetchModelsForProvider(Provider $provider): array
    {
        $url = $this->getModelsEndpoint($provider);
        $response = Http::timeout(30)->withHeaders($this->getHeaders($provider))->get($url);
        if (! $response->successful()) {
            throw new \RuntimeException("Failed to fetch models (HTTP {$response->status()}): ".substr($response->body(), 0, 200));
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new \RuntimeException('Invalid JSON response from provider API');
        }

        return $this->normalizeModelsResponse($provider, $data);
    }

    protected function getHeaders(Provider $provider): array
    {
        $driver = $provider->driver ?: $provider->name;

        return match ($driver) {
            'anthropic' => ['x-api-key' => $provider->api_key, 'anthropic-version' => '2023-06-01'],
            'ollama' => [],
            default => $provider->api_key ? ['Authorization' => 'Bearer '.$provider->api_key] : [],
        };
    }

    protected function normalizeModelsResponse(Provider $provider, array $data): array
    {
        $driver = $provider->driver ?: $provider->name;
        $models = match ($driver) {
            'ollama' => $data['models'] ?? [],
            default => $data['data'] ?? $data['models'] ?? $data,
        };

        return is_array($models) ? array_values($models) : [];
    }

    protected function getModelsEndpoint(Provider $provider): string
    {
        $customUrl = $provider->url;
        $driver = $provider->driver ?: $provider->name;
        if ($customUrl && (str_ends_with($customUrl, '/models') || str_contains($customUrl, '/models?'))) {
            return $customUrl;
        }

        if ($customUrl) {
            $baseUrl = rtrim($customUrl, '/');
            return match ($driver) {
                'openrouter' => $baseUrl.'/models',
                'ollama' => $baseUrl.'/api/tags',
                default => $baseUrl.(str_ends_with($baseUrl, '/v1') ? '/models' : '/v1/models'),
            };
        }

        return match ($driver) {
            'openai' => 'https://api.openai.com/v1/models',
            'anthropic' => 'https://api.anthropic.com/v1/models',
            'openrouter' => 'https://openrouter.ai/api/v1/models',
            'groq' => 'https://api.groq.com/openai/v1/models',
            'mistral' => 'https://api.mistral.ai/v1/models',
            'deepseek' => 'https://api.deepseek.com/v1/models',
            'xai' => 'https://api.x.ai/v1/models',
            'ollama' => 'http://localhost:11434/api/tags',
            default => throw new \RuntimeException("Provider {$provider->name} does not expose a supported models endpoint"),
        };
    }

    protected function updateAiModelPricing(Provider $provider, string $modelName, array|string $modelData): void
    {
        if (! is_array($modelData) || ! isset($modelData['pricing']) || ! is_array($modelData['pricing'])) {
            return;
        }
        $pricing = $modelData['pricing'];
        $input = $pricing['prompt'] ?? $pricing['input'] ?? null;
        $output = $pricing['completion'] ?? $pricing['output'] ?? null;
        if (! is_numeric($input) || ! is_numeric($output)) {
            return;
        }

        $driver = $provider->driver ?: $provider->name;
        $values = [
            'input_price_per_million' => (float) $input,
            'output_price_per_million' => (float) $output,
            'cache_read_price_per_million' => $this->number($pricing['input_cache_read'] ?? null),
            'cache_write_price_per_million' => $this->number($pricing['input_cache_write'] ?? null),
            'reasoning_price_per_million' => $this->number($pricing['internal_reasoning'] ?? null),
            'audio_input_price_per_million' => $this->number($pricing['audio'] ?? null),
            'audio_output_price_per_million' => $this->number($pricing['audio_output'] ?? null),
            'request_price' => $this->number($pricing['request'] ?? null),
            'source_model_id' => $modelName,
            'source_url' => $provider->url,
            'mode' => 'base',
            'priced_at' => now(),
            'last_seen_at' => now(),
        ];

        AiModelPricing::updateOrCreate(
            ['provider' => $driver, 'model' => $modelName, 'source' => 'provider_native'],
            $values
        );
    }

    protected function normalizeOpenRouterPricing(array $pricing): array
    {
        $tokenFields = [
            'prompt', 'completion', 'audio', 'audio_output', 'image', 'image_output', 'image_token',
            'input_audio_cache', 'input_cache_read', 'input_cache_write', 'internal_reasoning',
        ];
        $normalized = [];
        foreach ($pricing as $key => $value) {
            if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) {
                continue;
            }
            $number = (float) $value;
            $normalized[$key] = in_array($key, $tokenFields, true) ? $number * 1_000_000 : $number;
        }

        return $normalized;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value >= 0 ? (float) $value : null;
    }

    public function syncAllProviders(): array
    {
        $results = [];
        foreach (Provider::all() as $provider) {
            $results[$provider->name] = $this->syncProvider($provider);
        }

        return $results;
    }
}
