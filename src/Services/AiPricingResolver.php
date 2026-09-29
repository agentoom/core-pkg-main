<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Ai\Support\CostCaptureStore;
use Agentoom\Core\Models\AiModelPricing;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

final class AiPricingResolver
{
    public function __construct(private readonly CostCaptureStore $capture) {}

    public function resolve(Usage $usage, Meta $meta): ?AiCostResolution
    {
        $providerName = $meta->provider;
        $model = $meta->model;
        $captured = $this->capture->total();

        if ($captured !== null && $providerName) {
            return new AiCostResolution(
                providerCost: $captured,
                creditCost: $this->creditCost($captured, $providerName),
                source: 'provider_reported',
                pricingId: null,
                provider: $providerName,
                model: $model,
                pricedAt: null,
            );
        }

        if (! $providerName || ! $model) {
            return null;
        }

        $provider = Provider::query()
            ->where('name', $providerName)
            ->orWhere('driver', $providerName)
            ->first();
        $driver = $provider?->driver ?: $provider?->name ?: $providerName;

        foreach (['manual', 'provider_native', 'litellm', 'models_dev'] as $source) {
            $pricing = $this->findPricing($driver, $model, $source);
            if ($pricing && $this->isFresh($pricing, $source)) {
                $providerCost = $this->calculate($pricing, $usage);
                if ($providerCost !== null) {
                    return new AiCostResolution(
                        providerCost: $providerCost,
                        creditCost: $this->creditCost($providerCost, $driver),
                        source: $source,
                        pricingId: $pricing->id,
                        provider: $driver,
                        model: $pricing->model,
                        pricedAt: $pricing->priced_at,
                    );
                }
            }
        }

        // Provider-native observations written before the catalog existed stay
        // readable, but only while the sync that wrote them is still fresh.
        $native = $this->providerModelPricing($provider, $model);
        if ($native !== null) {
            $providerCost = $this->calculateArray($native['rates'], $usage);
            if ($providerCost !== null) {
                return new AiCostResolution(
                    providerCost: $providerCost,
                    creditCost: $this->creditCost($providerCost, $driver),
                    source: 'provider_native',
                    pricingId: null,
                    provider: $driver,
                    model: $model,
                    pricedAt: $native['observedAt'],
                );
            }
        }

        Log::debug('No verified AI price available', [
            'provider' => $providerName,
            'driver' => $driver,
            'model' => $model,
            'usage' => $usage->toArray(),
        ]);

        return null;
    }

    private function findPricing(string $provider, string $model, string $source): ?AiModelPricing
    {
        $rows = AiModelPricing::query()
            ->where('provider', $provider)
            ->where('source', $source)
            ->whereNotNull('model')
            ->get();

        $raw = $rows->first(fn (AiModelPricing $row): bool => $row->model === $model);
        if ($raw) {
            return $raw;
        }

        $lower = mb_strtolower($model);
        $exact = $rows->filter(fn (AiModelPricing $row): bool => mb_strtolower((string) $row->model) === $lower);
        if ($exact->count() === 1) {
            return $exact->first();
        }

        $normalized = $this->normalizedModel($provider, $model);
        $matches = $rows->filter(fn (AiModelPricing $row): bool => $this->normalizedModel($provider, (string) $row->model) === $normalized);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Read the pricing captured on the provider model row. The sync stores
     * those token prices already normalized to per-million units, so they are
     * used exactly as written.
     *
     * @return array{rates: array<string, float>, observedAt: \Illuminate\Support\Carbon}|null
     */
    private function providerModelPricing(?Provider $provider, string $model): ?array
    {
        if (! $provider) {
            return null;
        }

        $rows = ProviderModel::query()
            ->where('provider_id', $provider->id)
            ->where('is_active', true)
            ->where('model_name', $model)
            ->get();

        if ($rows->count() !== 1) {
            $lower = mb_strtolower($model);
            $rows = ProviderModel::query()
                ->where('provider_id', $provider->id)
                ->where('is_active', true)
                ->get()
                ->filter(fn (ProviderModel $row): bool => mb_strtolower($row->model_name) === $lower);
        }

        if ($rows->count() !== 1) {
            $driver = $provider->driver ?: $provider->name;
            $normalized = $this->normalizedModel($driver, $model);
            $rows = $rows->filter(fn (ProviderModel $row): bool => $this->normalizedModel($driver, $row->model_name) === $normalized);
        }

        $row = $rows->count() === 1 ? $rows->first() : null;
        if ($row === null) {
            return null;
        }

        $observedAt = $row->updated_at;
        if ($observedAt === null || $observedAt->lessThan(now()->subHours((int) config('ai-pricing.max_age_hours', 6)))) {
            return null;
        }

        $pricing = $row->model_features['pricing'] ?? null;

        return is_array($pricing)
            ? ['rates' => $this->normalizeNativeArray($pricing), 'observedAt' => $observedAt]
            : null;
    }

    private function normalizedModel(string $provider, string $model): string
    {
        $value = mb_strtolower(trim($model));
        if ($provider === 'openrouter') {
            $value = preg_replace('/:(nitro|floor|online)$/', '', $value) ?? $value;
        }

        // Provider prefixes are routing identity, not a price dimension.
        if (str_contains($value, '/')) {
            $value = substr($value, str_contains($value, '/') + 1);
        }

        return $value;
    }

    private function isFresh(AiModelPricing $pricing, string $source): bool
    {
        if ($source === 'manual') {
            return true;
        }

        $seen = $pricing->last_seen_at ?: $pricing->priced_at;

        return $seen !== null && $seen->greaterThanOrEqualTo(now()->subHours((int) config('ai-pricing.max_age_hours', 6)));
    }

    private function calculate(AiModelPricing $pricing, Usage $usage): ?float
    {
        return $this->calculateArray([
            'input' => $pricing->input_price_per_million,
            'output' => $pricing->output_price_per_million,
            'cache_read' => $pricing->cache_read_price_per_million,
            'cache_write' => $pricing->cache_write_price_per_million,
            'reasoning' => $pricing->reasoning_price_per_million,
            'audio_input' => $pricing->audio_input_price_per_million,
            'audio_output' => $pricing->audio_output_price_per_million,
            'request' => $pricing->request_price,
            'image' => $pricing->image_price_per_million,
        ], $usage);
    }

    private function calculateArray(array $rates, Usage $usage): ?float
    {
        $components = [
            ['tokens' => $usage->promptTokens, 'rate' => $rates['input'] ?? null],
            ['tokens' => $usage->completionTokens, 'rate' => $rates['output'] ?? null],
            ['tokens' => $usage->cacheReadInputTokens, 'rate' => $rates['cache_read'] ?? null],
            ['tokens' => $usage->cacheWriteInputTokens, 'rate' => $rates['cache_write'] ?? null],
            ['tokens' => $usage->reasoningTokens, 'rate' => $rates['reasoning'] ?? null],
        ];

        foreach ($components as $component) {
            if ($component['tokens'] > 0 && $component['rate'] === null) {
                return null;
            }
            if ($component['rate'] !== null && (! is_numeric($component['rate']) || ! is_finite((float) $component['rate']) || (float) $component['rate'] < 0)) {
                return null;
            }
        }

        $cost = 0.0;
        foreach ($components as $component) {
            $cost += ($component['tokens'] / 1_000_000) * (float) ($component['rate'] ?? 0);
        }

        if (array_key_exists('request', $rates) && $rates['request'] !== null) {
            if (! is_numeric($rates['request']) || ! is_finite((float) $rates['request']) || (float) $rates['request'] < 0) {
                return null;
            }
            $cost += (float) $rates['request'];
        }

        return is_finite($cost) && $cost >= 0 ? $cost : null;
    }

    /**
     * Map the stored feature keys onto the resolver's rate keys. The sync
     * already multiplied token prices by one million, so no unit conversion
     * happens here — doing it twice would overcharge by a factor of 1e6.
     */
    private function normalizeNativeArray(array $pricing): array
    {
        $map = [
            'input' => 'prompt', 'output' => 'completion',
            'cache_read' => 'input_cache_read', 'cache_write' => 'input_cache_write',
            'reasoning' => 'internal_reasoning',
            'audio_input' => 'audio', 'audio_output' => 'audio_output',
            'request' => 'request',
        ];

        $normalized = [];
        foreach ($map as $target => $key) {
            if (! array_key_exists($key, $pricing) || ! is_numeric($pricing[$key])) {
                continue;
            }
            $value = (float) $pricing[$key];
            if (! is_finite($value) || $value < 0) {
                continue;
            }
            $normalized[$target] = $value;
        }

        return $normalized;
    }

    private function creditCost(float $providerCost, string $provider): float
    {
        $driver = Provider::query()->where('name', $provider)->orWhere('driver', $provider)->value('driver') ?: $provider;
        $multiplier = $driver === 'openrouter' ? (float) \Agentoom\Core\Models\Configuration::getValue('openrouter_multiplier', 1.25) : 1.0;

        return $providerCost * $multiplier;
    }
}
