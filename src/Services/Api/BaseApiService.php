<?php

namespace Agentoom\Core\Services\Api;

use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\UserIntegration;
use Google\Client as GoogleClient;
use Illuminate\Support\Carbon;

abstract class BaseApiService
{
    protected function resolveResource(?string $resourceId): ?Resource
    {
        if (! $resourceId) {
            return null;
        }

        return Resource::find($resourceId);
    }

    /**
     * Safely convert a value to a string, handling arrays.
     */
    protected function toString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return is_array($value) ? json_encode($value) : (string) $value;
    }

    /**
     * Resolve resource data, handling mapping if provided.
     * If resourceId is null, it tries to fetch from global integration config.
     */
    protected function resolveResourceData(?string $resourceId, array $mapping = [], ?string $service = null): array
    {
        $user = auth()->user();
        $mapped = [];

        if (! $resourceId) {
            // 1. Try UserIntegration first if service is provided
            if ($service && $user) {
                $userIntegration = UserIntegration::where('user_id', $user->id)
                    ->where('service', $service)
                    ->where('is_active', true)
                    ->first();

                if ($userIntegration) {
                    $data = $userIntegration->data;

                    // Fallback to global keys for common Google fields if missing in user data
                    if ($service === 'gmail') {
                        $global = Configuration::getValue('gmail_integration', []);

                        $data['api_key'] = $data['api_key'] ?? $global['api_key'] ?? null;
                        $data['client_id'] = $data['client_id'] ?? $global['client_id'] ?? null;
                        $data['client_secret'] = $data['client_secret'] ?? $global['client_secret'] ?? null;
                        $data['service_account_json'] = $data['service_account_json'] ?? $global['service_account_json'] ?? null;
                    }

                    return $data;
                }
            }

            // 2. Fallback to global config for admins
            if ($service && $user && $user->hasAnyRole(['superadmin', 'admin'])) {
                return Configuration::getValue("{$service}_integration", []);
            }

            // 3. Last resort fallback for common fields even if user is not set (only if no user is authenticated)
            if (! $user && ($service === 'gmail')) {
                $global = Configuration::getValue('gmail_integration', []);

                if (! empty($global)) {
                    $mapped = $global;
                }
            }

            // Merge mapping if it's literal (the agent sometimes passes direct values)
            foreach ($mapping as $targetKey => $value) {
                if (is_string($value) && ! str_contains($value, '[ENCRYPTED_REFERENCE:')) {
                    $mapped[$targetKey] = $value;
                }
            }

            return $mapped;
        }

        $resource = Resource::find($resourceId);
        if (! $resource) {
            return [];
        }

        if (empty($mapping)) {
            $mapped = $resource->getDecryptedData();
        } else {
            $mapped = [];
            $context = [
                'data' => $resource->getDecryptedData(),
                'resource' => $resource->toArray(),
                'name' => $resource->name,
                'description' => $resource->description,
            ];

            foreach ($mapping as $targetKey => $sourcePath) {
                if (! is_string($sourcePath)) {
                    continue;
                }

                // Try standard paths first
                $value = data_get($context, $sourcePath);

                // Fallback: if they omitted 'data.' prefix, try adding it
                if ($value === null && ! str_contains($sourcePath, '.')) {
                    $value = data_get($context, "data.{$sourcePath}");
                }

                if ($value !== null) {
                    $mapped[$targetKey] = $value;
                }
            }
        }

        // Fallback to global keys for common fields if missing in mapped data
        if ($service === 'gmail') {
            $global = Configuration::getValue('gmail_integration', []);

            $mapped['api_key'] = $mapped['api_key'] ?? $global['api_key'] ?? null;
            $mapped['client_id'] = $mapped['client_id'] ?? $global['client_id'] ?? null;
            $mapped['client_secret'] = $mapped['client_secret'] ?? $global['client_secret'] ?? null;
            $mapped['service_account_json'] = $mapped['service_account_json'] ?? $global['service_account_json'] ?? null;
            $mapped['access_token'] = $mapped['access_token'] ?? $global['access_token'] ?? null;
            $mapped['refresh_token'] = $mapped['refresh_token'] ?? $global['refresh_token'] ?? null;
            $mapped['expires_at'] = $mapped['expires_at'] ?? $global['expires_at'] ?? null;
        }

        return $mapped;
    }

    /**
     * Get and clean parameters, handling various common LLM input formats.
     * Useful when data is passed as a string or nested array.
     */
    protected function cleanParams(mixed $params, bool $mergeObjects = true): array
    {
        if (is_string($params)) {
            $decoded = json_decode($params, true);
            if (is_array($decoded)) {
                $params = $decoded;
            }
        }

        if (! is_array($params)) {
            return [];
        }

        // Case 1: Array with one stringified JSON element (common LLM mistake)
        if (count($params) === 1 && isset($params[0]) && is_string($params[0])) {
            $decoded = json_decode($params[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Case 2: List of objects (common LLM mistake when sending associative arrays)
        if ($mergeObjects && array_is_list($params) && count($params) > 0 && is_array($params[0])) {
            $merged = [];
            foreach ($params as $p) {
                if (is_array($p)) {
                    $merged = array_merge($merged, $p);
                }
            }

            return $merged;
        }

        return $params;
    }

    /**
     * Validate that all required fields are present in the mapped data.
     */
    protected function validateMappedData(array $mapped, array $requiredFields): ?string
    {
        $missingFields = [];
        $isServiceAccount = ! empty($mapped['service_account_json']);

        foreach ($requiredFields as $field => $description) {
            // If using service account, skip OAuth specific fields
            if ($isServiceAccount && in_array($field, ['client_id', 'client_secret', 'refresh_token', 'access_token'])) {
                continue;
            }

            $val = $mapped[$field] ?? null;
            if ($val === null || trim(is_array($val) ? json_encode($val) : (string) $val) === '') {
                $missingFields[] = "{$field} ({$description})";
            }
        }

        if (! empty($missingFields)) {
            $fieldsList = implode(', ', $missingFields);

            return "Error: Missing required information: {$fieldsList}. Please check if the resource has these fields or if your resource_mapping is correct.";
        }

        return null;
    }

    /**
     * Build a query string that Google APIs like (repeating keys for arrays, no indices).
     */
    protected function buildGoogleQuery(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            $keyStr = is_array($key) ? json_encode($key) : (string) $key;
            if (is_array($value)) {
                foreach ($value as $v) {
                    $vStr = is_array($v) ? json_encode($v) : (string) $v;
                    $parts[] = urlencode($keyStr).'='.urlencode($vStr);
                }
            } else {
                $valueStr = is_array($value) ? json_encode($value) : (string) $value;
                $parts[] = urlencode($keyStr).'='.urlencode($valueStr);
            }
        }

        return implode('&', $parts);
    }

    /**
     * Get the required scopes for the service.
     */
    public static function getScopes(string $service): array
    {
        return match ($service) {
            'gmail' => [
                'https://www.googleapis.com/auth/gmail.modify',
                'https://www.googleapis.com/auth/gmail.send',
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile',
                'openid',
            ],
            default => ['email'],
        };
    }

    /**
     * Get a configured Google Client instance.
     */
    protected function getGoogleClient(array $config, array $scopes = []): GoogleClient
    {
        $client = new GoogleClient;

        $serviceAccountJson = $config['service_account_json'] ?? null;
        if ($serviceAccountJson) {
            try {
                $client->setAuthConfig(json_decode($serviceAccountJson, true));

                if (! empty($scopes)) {
                    // Filter out scopes that are not valid for service accounts without domain-wide delegation
                    $filteredScopes = array_filter($scopes, function ($scope) {
                        return ! in_array($scope, ['openid', 'https://www.googleapis.com/auth/userinfo.email', 'https://www.googleapis.com/auth/userinfo.profile']);
                    });

                    $client->setScopes($filteredScopes);
                }

                // Support impersonation if requested
                if (! empty($config['subject'])) {
                    $client->setSubject($config['subject']);
                }

                return $client;
            } catch (\Exception $e) {
                // Fallback to normal auth if JSON is invalid
            }
        }

        $clientId = $config['client_id'] ?? config('services.google.client_id');
        $clientSecret = $config['client_secret'] ?? config('services.google.client_secret');
        $apiKey = $config['api_key'] ?? config('services.google.api_key');

        if (! $clientId || ! $clientSecret || ! $apiKey) {
            $globalGmail = Configuration::getValue('gmail_integration', []);
            $clientId = $clientId ?: ($globalGmail['client_id'] ?? null);
            $clientSecret = $clientSecret ?: ($globalGmail['client_secret'] ?? null);
            $apiKey = $apiKey ?: ($globalGmail['api_key'] ?? null);
        }

        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);

        if ($apiKey) {
            $client->setDeveloperKey($apiKey);
        }

        if (! empty($config['access_token'])) {
            $client->setAccessToken([
                'access_token' => $config['access_token'],
                'refresh_token' => $config['refresh_token'] ?? null,
                'expires_in' => isset($config['expires_at']) ? Carbon::parse($config['expires_at'])->diffInSeconds(now(), false) : 3600,
                'created' => time(),
            ]);
        }

        if (! empty($scopes)) {
            $client->addScope($scopes);
        }

        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    /**
     * Refresh the access token if it's expired or about to expire.
     */
    protected function refreshAccessToken(string $service, array &$config, ?string $resourceId = null): ?string
    {
        $expiresAt = isset($config['expires_at']) ? Carbon::parse($config['expires_at']) : null;

        if ($expiresAt && $expiresAt->isFuture() && ! empty($config['access_token'])) {
            return $config['access_token'];
        }

        if (empty($config['refresh_token'])) {
            return $config['access_token'] ?? null;
        }

        // Logic to refresh token based on service
        if ($service === 'gmail') {
            $client = $this->getGoogleClient($config);
            if ($client->getRefreshToken()) {
                $token = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());

                if (isset($token['error'])) {
                    return $config['access_token'] ?? null;
                }

                $config['access_token'] = $token['access_token'];
                if (isset($token['refresh_token'])) {
                    $config['refresh_token'] = $token['refresh_token'];
                }

                if (isset($token['expires_in'])) {
                    $config['expires_at'] = now()->addSeconds($token['expires_in'])->toDateTimeString();
                }

                $this->saveRefreshedToken($service, $config, $resourceId);

                return $config['access_token'];
            }
        }

        return $config['access_token'] ?? null;
    }

    /**
     * Save the refreshed token back to the storage.
     */
    protected function saveRefreshedToken(string $service, array $config, ?string $resourceId = null): void
    {
        if ($resourceId) {
            $user = auth()->user();
            $resource = $user ? Resource::visibleTo($user)->find($resourceId) : null;
            if ($resource) {
                $newData = [
                    'access_token' => $config['access_token'],
                    'expires_at' => $config['expires_at'] ?? null,
                ];

                if (! empty($config['refresh_token'])) {
                    $newData['refresh_token'] = $config['refresh_token'];
                }

                $resource->update([
                    'data' => array_merge($resource->getDecryptedData(), $newData),
                ]);
            }
        } else {
            $user = auth()->user();
            $userIntegration = $user && $service ? UserIntegration::where('user_id', $user->id)->where('service', $service)->first() : null;

            if ($userIntegration) {
                $newData = [
                    'access_token' => $config['access_token'],
                    'expires_at' => $config['expires_at'] ?? null,
                ];

                if (! empty($config['refresh_token'])) {
                    $newData['refresh_token'] = $config['refresh_token'];
                }

                $userIntegration->update([
                    'data' => array_merge($userIntegration->data, $newData),
                ]);
            } elseif ($user && $user->hasAnyRole(['superadmin', 'admin'])) {
                // Save back to global config
                Configuration::setValue("{$service}_integration", $config);
            }
        }
    }
}
