<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class InjectCurrentAiConfigService
{
    public static function inject(Agent $agentModel): array
    {
        $agentConfig = $agentModel->config ?? [];
        $globalConfig = Configuration::getValues(['ai_provider', 'ai_model', 'ai_api_key', 'ai_temperature', 'ai_top_p', 'ai_model_suffix']);

        if (app()->isLocal()) {
            Log::info('[DIAG] InjectCurrentAiConfigService::inject called', [
                'agent_id' => $agentModel->id,
                'agent_provider_id' => $agentConfig['provider_id'] ?? null,
                'agent_provider_model_id' => $agentConfig['provider_model_id'] ?? null,
                'global_ai_provider' => $globalConfig['ai_provider'] ?? null,
                'global_ai_api_key_set' => ! empty($globalConfig['ai_api_key']),
                'log_level_env' => env('LOG_LEVEL'),
                'config_ai_default_before' => config('ai.default'),
            ]);
        }

        if (empty($agentConfig['provider_id'])) {
            $providerId = $globalConfig['ai_provider'];
            $provider = is_numeric($providerId)
                ? Provider::find($providerId)
                : Provider::where('name', $providerId)->first();

            // If no provider is configured globally, fall back to the single provider in DB
            if (! $provider) {
                $allProviders = Provider::all();
                if ($allProviders->count() === 1) {
                    $provider = $allProviders->first();
                }
            }

            $apiKey = ($provider ? static::resolveProviderApiKey($provider) : null) ?: $globalConfig['ai_api_key'];
            $temperature = $agentConfig['temperature'] ?? $globalConfig['ai_temperature'] ?? 0.4;
            $topP = $agentConfig['top_p'] ?? $globalConfig['ai_top_p'] ?? 0.95;
        } else {
            $providerId = $agentConfig['provider_id'];
            $provider = is_numeric($providerId)
                ? Provider::find($providerId)
                : Provider::where('name', $providerId)->first();

            // If the agent's provider_id no longer resolves, fall back to the single provider in DB
            if (! $provider) {
                $allProviders = Provider::all();
                if ($allProviders->count() === 1) {
                    $provider = $allProviders->first();
                }
            }

            $apiKey = ($provider ? static::resolveProviderApiKey($provider) : null) ?: $globalConfig['ai_api_key'];
            $temperature = $agentConfig['temperature'] ?? 0.4;
            $topP = $agentConfig['top_p'] ?? 0.95;
        }
        $provider_name = $provider?->name;
        $provider_driver = $provider?->driver ?: $provider_name;
        $provider_url = static::validProviderUrl($provider?->url);

        if (! $apiKey) {
            if (app()->isLocal()) {
                Log::info('[DIAG] InjectCurrentAiConfigService: no API key resolved', [
                    'agent_id' => $agentModel->id,
                    'provider' => $provider_name,
                    'provider_id_in_config' => $agentConfig['provider_id'] ?? null,
                ]);
            }
        }

        if ($provider) {
            Config::set("ai.providers.{$provider_name}.driver", $provider_driver);
            Config::set("ai.providers.{$provider_name}.key", $apiKey ?? '');
            if ($provider_url) {
                Config::set("ai.providers.{$provider_name}.url", $provider_url);
            }
            Config::set('ai.default', $provider_name);
            Config::set("ai.providers.{$provider_name}.options.temperature", (float) $temperature);
            Config::set("ai.providers.{$provider_name}.options.top_p", (float) $topP);
            Config::set('ai.openrouter_disable_cache', (bool) ($agentConfig['openrouter_disable_cache'] ?? false));

            static::forgetAiManagerInstance($provider_name);
        }

        if (
            isset($agentConfig['provider_model_id'])
            && $agentConfig['provider_model_id']
        ) {
            $modelId = $agentConfig['provider_model_id'];
            $modelRecord = is_numeric($modelId)
                ? ProviderModel::find($modelId)
                : ProviderModel::where('model_name', $modelId)
                    ->where('provider_id', $provider?->id)
                    ->first();
            $model = $modelRecord?->model_name;
            $suffix = $agentConfig['provider_model_suffix'] ?? '';
        } else {
            $modelId = $globalConfig['ai_model'];
            $modelRecord = is_numeric($modelId)
                ? ProviderModel::find($modelId)
                : ProviderModel::where('model_name', $modelId)
                    ->where('provider_id', $provider?->id)
                    ->first();
            $model = $modelRecord?->model_name;
            $suffix = $globalConfig['ai_model_suffix'] ?? '';
        }

        if ($provider_name === 'openrouter' && $model && ! empty($suffix)) {
            $model .= ':'.$suffix;
        }

        if (app()->isLocal()) {
            Log::info('[DIAG] InjectCurrentAiConfigService::inject resolved', [
                'agent_id' => $agentModel->id,
                'provider_name' => $provider_name,
                'provider_url' => $provider_url,
                'model' => $model ?? null,
                'api_key_len' => is_string($apiKey) ? strlen($apiKey) : 0,
                'config_ai_default' => config('ai.default'),
                'config_provider_key_len' => $provider_name ? strlen((string) config("ai.providers.{$provider_name}.key")) : 0,
            ]);
        }

        return [
            'provider_name' => $provider_name,
            'provider_url' => $provider_url,
            'temperature' => $temperature,
            'top_p' => $topP,
            'api_key' => $apiKey,
            'model' => $model ?? null,
        ];
    }

    /**
     * Force the Laravel AI manager to forget cached provider instances so that
     * subsequent resolutions pick up the freshly mutated config (api key, url,
     * options). This is critical inside long-running queue workers (Horizon)
     * where the AiManager is a singleton and caches resolved providers across
     * jobs — without this, stale credentials persist for the worker's lifetime.
     */
    private static function forgetAiManagerInstance(?string $providerName = null): void
    {
        try {
            if (! app()->bound('ai')) {
                return;
            }

            $manager = app('ai');

            if (method_exists($manager, 'forgetInstance')) {
                if ($providerName) {
                    $manager->forgetInstance($providerName);
                }
                // Also drop the default-resolved instance (null key) just in case.
                $manager->forgetInstance();
            }
        } catch (\Throwable $e) {
            Log::debug('InjectCurrentAiConfigService: failed to forget AiManager instance', [
                'provider' => $providerName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Safely resolve the API key from a provider, catching decryption errors.
     */
    private static function resolveProviderApiKey(Provider $provider): ?string
    {
        try {
            return $provider->api_key ?: null;
        } catch (DecryptException $e) {
            Log::error('InjectCurrentAiConfigService: failed to decrypt API key for provider', [
                'provider_id' => $provider->id,
                'provider_name' => $provider->name,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if the given provider and model support audio input.
     */
    public static function supportsAudioInput(string $provider, ?string $model = null): bool
    {
        $provider = strtolower($provider);

        return $provider === 'gemini' || $provider === 'google';
    }

    /**
     * Get the transcription configuration.
     */
    public static function getTranscriptionConfig(): array
    {
        $providerId = Configuration::getValue('ai_transcription_provider');
        $modelId = Configuration::getValue('ai_transcription_model');

        if (! $providerId) {
            return [];
        }

        $provider = is_numeric($providerId)
            ? Provider::find($providerId)
            : Provider::where('name', $providerId)->first();

        $model = null;
        if ($modelId) {
            $modelRecord = is_numeric($modelId)
                ? ProviderModel::find($modelId)
                : ProviderModel::where('model_name', $modelId)
                    ->where('provider_id', $provider?->id)
                    ->first();
            $model = $modelRecord?->model_name;
        }

        return [
            'provider' => $provider?->name,
            'model' => $model,
            'api_key' => $provider ? static::resolveProviderApiKey($provider) : null,
            'url' => static::validProviderUrl($provider?->url),
        ];

    }

    /**
     * Return only absolute HTTP(S) provider URLs accepted by the HTTP client.
     */
    private static function validProviderUrl(?string $url): ?string
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);

        return in_array($scheme, ['http', 'https'], true) && is_string($host) && $host !== ''
            ? $url
            : null;
    }

    /**
     * Inject config for a specialized agent by its slug.
     */
    public static function injectSpecializedAgent(string $slug): array
    {
        $aiSpecializedAgents = Configuration::getValue('ai_specialized_agents', []);
        $agentId = $aiSpecializedAgents[$slug]['agent_id'] ?? null;

        if ($agentId) {
            $agentModel = Agent::find($agentId);
            if ($agentModel) {
                return static::inject($agentModel);
            }
        }

        // Fallback to global config if no specialized agent is configured
        $globalConfig = Configuration::getValues(['ai_provider', 'ai_model', 'ai_api_key', 'ai_temperature', 'ai_top_p', 'ai_model_suffix']);
        $providerId = $globalConfig['ai_provider'];
        $provider = is_numeric($providerId)
            ? Provider::find($providerId)
            : Provider::where('name', $providerId)->first();

        // If no provider is configured globally, fall back to the single provider in DB
        if (! $provider) {
            $allProviders = Provider::all();
            if ($allProviders->count() === 1) {
                $provider = $allProviders->first();
            }
        }

        $apiKey = ($provider ? static::resolveProviderApiKey($provider) : null) ?: $globalConfig['ai_api_key'];
        $temperature = $globalConfig['ai_temperature'] ?? 0.4;
        $topP = $globalConfig['ai_top_p'] ?? 0.95;

        $provider_name = $provider?->name;
        $provider_driver = $provider?->driver ?: $provider_name;
        $provider_url = static::validProviderUrl($provider?->url);

        if (! $apiKey) {
            Log::warning('InjectCurrentAiConfigService: no API key resolved for specialized agent', [
                'slug' => $slug,
                'provider' => $provider_name,
            ]);
        }

        if ($provider) {
            Config::set("ai.providers.{$provider_name}.driver", $provider_driver);
            Config::set("ai.providers.{$provider_name}.key", $apiKey ?? '');
            if ($provider_url) {
                Config::set("ai.providers.{$provider_name}.url", $provider_url);
            }
            Config::set('ai.default', $provider_name);
            Config::set("ai.providers.{$provider_name}.options.temperature", (float) $temperature);
            Config::set("ai.providers.{$provider_name}.options.top_p", (float) $topP);
            Config::set('ai.openrouter_disable_cache', config('ai.openrouter_disable_cache') ?: false);

            static::forgetAiManagerInstance($provider_name);
        }

        $modelId = $globalConfig['ai_model'];
        $model = null;

        if ($modelId) {
            $modelRecord = is_numeric($modelId)
                ? ProviderModel::find($modelId)
                : ProviderModel::where('model_name', $modelId)
                    ->where('provider_id', $provider?->id)
                    ->first();
            $model = $modelRecord?->model_name;
            $suffix = $globalConfig['ai_model_suffix'] ?? '';

            if ($provider_name === 'openrouter' && $model && ! empty($suffix)) {
                $model .= ':'.$suffix;
            }
        }

        return [
            'provider_name' => $provider_name,
            'provider_url' => $provider_url,
            'temperature' => $temperature,
            'top_p' => $topP,
            'api_key' => $apiKey,
            'model' => $model,
        ];
    }
}
