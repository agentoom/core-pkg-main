<?php

namespace Agentoom\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class AiPricingSyncService
{
    public function sync(string $source, bool $force = false): array
    {
        $feed = config("ai-pricing.feeds.{$source}");
        if (! is_array($feed) || empty($feed['url'])) {
            return ['status' => 'failed', 'entry_count' => 0, 'error' => "Unknown pricing source: {$source}"];
        }

        $sync = DB::table('ai_pricing_syncs')->where('source', $source)->first();
        $headers = [];
        if (! $force && $sync?->etag) {
            $headers['If-None-Match'] = $sync->etag;
        }

        try {
            $response = Http::timeout((int) config('ai-pricing.timeout', 60))
                ->retry((int) config('ai-pricing.retry_times', 3), (int) config('ai-pricing.retry_sleep_ms', 5000))
                ->withHeaders($headers)
                ->get($feed['url']);
            $now = now();

            if ($response->status() === 304) {
                DB::table('ai_pricing_syncs')->updateOrInsert(
                    ['source' => $source],
                    [
                        'status' => 'not_modified', 'http_status' => 304,
                        'etag' => $response->header('ETag') ?: $sync?->etag,
                        'fetched_at' => $now, 'last_success_at' => $sync?->last_success_at ?: $now,
                        'error' => null, 'updated_at' => $now, 'created_at' => $sync?->created_at ?: $now,
                    ]
                );
                DB::table('ai_model_pricings')->where('source', $source)->update(['last_seen_at' => $now]);

                return ['status' => 'not_modified', 'entry_count' => (int) ($sync?->entry_count ?? 0)];
            }

            $payload = $response->json();
            $entries = $this->extractEntries($source, $payload);
            if (collect($entries)->contains(fn (array $entry): bool => ($entry['invalid'] ?? false) === true)) {
                throw new \RuntimeException('Pricing feed contains a negative or non-finite price');
            }
            $usable = array_values(array_filter($entries, fn (array $entry): bool => $this->isUsable($entry)));
            $previousCount = (int) ($sync?->entry_count ?? 0);
            if ($previousCount > 0 && count($usable) < $previousCount * 0.5) {
                throw new \RuntimeException('Pricing feed rejected because its usable entry count is suspiciously low');
            }
            if ($payload === null || ! is_array($payload) || $entries === []) {
                throw new \RuntimeException('Pricing feed has an invalid JSON shape');
            }

            DB::transaction(function () use ($source, $usable, $response, $now): void {
                foreach ($usable as $entry) {
                    $values = [
                        'input_price_per_million' => $entry['input'],
                        'output_price_per_million' => $entry['output'],
                        'cache_read_price_per_million' => $entry['cache_read'],
                        'cache_write_price_per_million' => $entry['cache_write'],
                        'reasoning_price_per_million' => $entry['reasoning'],
                        'audio_input_price_per_million' => $entry['audio_input'],
                        'audio_output_price_per_million' => $entry['audio_output'],
                        'request_price' => $entry['request'],
                        'image_price_per_million' => $entry['image'],
                        'source_model_id' => $entry['source_model_id'],
                        'source_url' => $entry['source_url'],
                        'mode' => 'base',
                        'priced_at' => $now,
                        'last_seen_at' => $now,
                    ];
                    DB::table('ai_model_pricings')->updateOrInsert(
                        ['provider' => $entry['provider'], 'model' => $entry['model'], 'source' => $source],
                        array_merge($values, ['updated_at' => $now, 'created_at' => $now])
                    );
                }

                DB::table('ai_pricing_syncs')->updateOrInsert(
                    ['source' => $source],
                    [
                        'status' => 'success', 'http_status' => $response->status(),
                        'etag' => $response->header('ETag'),
                        'content_hash' => hash('sha256', $response->body()),
                        'entry_count' => count($usable), 'fetched_at' => $now,
                        'last_success_at' => $now, 'error' => null,
                        'updated_at' => $now, 'created_at' => $now,
                    ]
                );
            });

            return ['status' => 'success', 'entry_count' => count($usable)];
        } catch (\Throwable $e) {
            Log::warning('AI pricing feed sync failed', ['source' => $source, 'error' => $e->getMessage()]);
            $now = now();
            DB::table('ai_pricing_syncs')->updateOrInsert(
                ['source' => $source],
                [
                    'status' => 'failed', 'http_status' => isset($response) ? $response->status() : null,
                    'fetched_at' => $now, 'error' => $e->getMessage(),
                    'updated_at' => $now, 'created_at' => $sync?->created_at ?: $now,
                ]
            );

            return ['status' => 'failed', 'entry_count' => 0, 'error' => $e->getMessage()];
        }
    }

    private function extractEntries(string $source, mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $entries = [];
        if ($source === 'litellm') {
            foreach ($payload as $model => $data) {
                if (! is_array($data)) {
                    continue;
                }
                $providerTag = strtolower((string) ($data['litellm_provider'] ?? $data['provider'] ?? ''));
                $provider = $this->mapProvider($providerTag, $source);
                if (! $provider) {
                    continue;
                }
                $entries[] = $this->normalize($provider, (string) $model, $data, $source);
            }

            return $entries;
        }

        $walk = function (mixed $node, ?string $providerTag = null, ?string $model = null) use (&$walk, &$entries, $source): void {
            if (! is_array($node)) {
                return;
            }
            if (isset($node['cost']) && $model !== null) {
                $rawProvider = $node['provider'] ?? $providerTag ?? '';
                if (is_array($rawProvider)) {
                    $rawProvider = $rawProvider['id'] ?? $rawProvider['name'] ?? $providerTag ?? '';
                }
                $provider = $this->mapProvider(is_string($rawProvider) ? $rawProvider : '', $source);
                if ($provider) {
                    $entries[] = $this->normalize($provider, $model, $node, $source);
                }
            }
            foreach ($node as $key => $value) {
                if (is_array($value)) {
                    $nextProvider = $providerTag ?: (is_string($key) ? $key : null);
                    $nextModel = isset($value['cost']) ? ($model ?: (is_string($key) ? $key : null)) : $model;
                    $walk($value, $nextProvider, $nextModel);
                }
            }
        };
        $walk($payload);

        return $entries;
    }

    private function normalize(string $provider, string $model, array $data, string $source): array
    {
        $cost = $source === 'models_dev' ? ($data['cost'] ?? $data) : $data;
        $invalid = false;
        $value = function (array $keys) use ($cost, $source, &$invalid): ?float {
            foreach ($keys as $key) {
                if (! array_key_exists($key, $cost)) {
                    continue;
                }
                if (! is_numeric($cost[$key])) {
                    $invalid = true;

                    return null;
                }
                $number = (float) $cost[$key];
                if (! is_finite($number) || $number < 0) {
                    $invalid = true;

                    return null;
                }

                return $source === 'litellm' && str_contains($key, 'per_token') ? $number * 1_000_000 : $number;
            }

            return null;
        };

        return [
            'provider' => $provider, 'model' => $model, 'source_model_id' => $model,
            'source_url' => config("ai-pricing.feeds.{$source}.url"),
            'input' => $value($source === 'litellm' ? ['input_cost_per_token'] : ['input', 'input_per_million']),
            'output' => $value($source === 'litellm' ? ['output_cost_per_token'] : ['output', 'output_per_million']),
            'cache_read' => $value(['cache_read_input_token_cost', 'cache_read']),
            'cache_write' => $value(['cache_write_input_token_cost', 'cache_write']),
            'reasoning' => $value(['reasoning_cost_per_token', 'reasoning']),
            'audio_input' => $value(['input_cost_per_audio_token', 'audio_input']),
            'audio_output' => $value(['output_cost_per_audio_token', 'audio_output']),
            'request' => $value(['request_cost', 'request']),
            'image' => $value(['input_cost_per_image', 'image']),
            'invalid' => $invalid,
        ];
    }

    private function isUsable(array $entry): bool
    {
        if ($entry['model'] === '' || $entry['provider'] === '') {
            return false;
        }

        return $entry['input'] !== null && $entry['output'] !== null;
    }

    private function mapProvider(string $tag, string $source): ?string
    {
        $tag = strtolower(trim($tag));
        foreach ((array) config('ai-pricing.provider_maps', []) as $driver => $maps) {
            if (in_array($tag, (array) ($maps[$source] ?? []), true)) {
                return $driver;
            }
        }

        return null;
    }
}
