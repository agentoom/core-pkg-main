<?php

namespace Agentoom\Core\Http\Controllers;

use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\UserIntegration;
use Agentoom\Core\Services\Api\BaseApiService;
use App\Http\Controllers\Controller;
use Google\Client as GoogleClient;
use Google\Service\Oauth2;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    /**
     * Redirect to the service's OAuth page.
     */
    public function redirect(string $service, Request $request)
    {
        $resourceId = $request->query('resource_id');
        $userIntegration = $request->query('user_integration');
        $config = Configuration::getValue("{$service}_integration");

        if ($resourceId) {
            $resource = Resource::find($resourceId);
            if ($resource) {
                // If the resource has its own client_id and client_secret, use them.
                // Otherwise, use the system-wide integration resources.
                $resourceData = $resource->getDecryptedData();
                if (! empty($resourceData['client_id']) && ! empty($resourceData['client_secret'])) {
                    $config = array_merge($config ?: [], [
                        'client_id' => $resourceData['client_id'],
                        'client_secret' => $resourceData['client_secret'],
                    ]);
                }
            }
        }

        if (! $config || empty($config['client_id']) || empty($config['client_secret'])) {
            $route = auth()->user()->hasAnyRole(['admin', 'superadmin']) ? route('admin.configs.email') : route('admin.my-integrations.index');

            return redirect($route)
                ->with('error', "Resources for {$service} are not configured.");
        }

        $driver = $this->getDriver($service);
        if ($driver !== 'google') {
            $this->setConfig($service, $config);
        }

        $scopes = $this->getScopes($service);

        $stateData = [
            'service' => $service,
        ];
        if ($resourceId) {
            $stateData['resource_id'] = $resourceId;
        }
        if ($userIntegration) {
            $stateData['user_integration'] = true;
        }
        $state = json_encode($stateData);

        if ($driver === 'google') {
            $client = $this->getGoogleClientForOAuth($service, $config);
            $client->setState($state);

            return redirect($client->createAuthUrl());
        }

        $params = [
            'access_type' => 'offline',
            'prompt' => 'consent select_account',
            'include_granted_scopes' => 'true',
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return Socialite::driver($driver)
            ->scopes($scopes)
            ->stateless()
            ->with($params)
            ->redirect();
    }

    /**
     * Handle the callback from the service.
     */
    public function callback(string $service, Request $request)
    {
        $state = json_decode($request->input('state'), true);
        $resourceId = $state['resource_id'] ?? null;
        $userIntegration = $state['user_integration'] ?? false;
        $service = $state['service'] ?? $service;

        $config = Configuration::getValue("{$service}_integration");

        if ($resourceId) {
            $resource = Resource::find($resourceId);
            if ($resource) {
                $resourceData = $resource->getDecryptedData();
                if (! empty($resourceData['client_id']) && ! empty($resourceData['client_secret'])) {
                    $config = array_merge($config ?: [], [
                        'client_id' => $resourceData['client_id'],
                        'client_secret' => $resourceData['client_secret'],
                    ]);
                }
            }
        }

        if (! $config) {
            $route = auth()->user()->hasAnyRole(['admin', 'superadmin']) ? route('admin.configs.email') : route('admin.my-integrations.index');

            return redirect($route)
                ->with('error', "Integration config not found for {$service}.");
        }

        if ($this->getDriver($service) !== 'google') {
            $this->setConfig($service, $config);
        }

        try {
            if ($this->getDriver($service) === 'google') {
                $client = $this->getGoogleClientForOAuth($service, $config);
                $token = $client->fetchAccessTokenWithAuthCode($request->input('code'));

                if (isset($token['error'])) {
                    throw new \Exception(($token['error_description'] ?? $token['error']).' (Token Exchange)');
                }

                $googleUserEmail = null;

                // Try to get email from ID token first (avoids extra API call and 401 issues)
                if (isset($token['id_token'])) {
                    $payload = $client->verifyIdToken($token['id_token']);
                    if ($payload && ! empty($payload['email'])) {
                        $googleUserEmail = $payload['email'];
                    }
                }

                // Fallback to userinfo API if ID token is not available or invalid
                if (! $googleUserEmail) {
                    $client->setAccessToken($token);
                    $oauth2 = app()->bound(Oauth2::class) ? app(Oauth2::class, ['client' => $client]) : new Oauth2($client);
                    $googleUser = $oauth2->userinfo->get();
                    $googleUserEmail = $googleUser->getEmail();
                }

                if (! $googleUserEmail) {
                    Log::error("OAuth Callback Error ({$service}): Could not retrieve email. Token keys: ".implode(', ', array_keys($token)));
                    throw new \Exception('Could not retrieve authorized email from Google.');
                }

                $tokens = [
                    'access_token' => $token['access_token'],
                    'expires_at' => now()->addSeconds($token['expires_in'])->toDateTimeString(),
                    'authorized_email' => $googleUserEmail,
                    'status' => 'connected',
                ];

                if (isset($token['refresh_token'])) {
                    $tokens['refresh_token'] = $token['refresh_token'];
                }
            } else {
                $driver = $this->getDriver($service);
                $user = Socialite::driver($driver)->stateless()->user();

                $tokens = [
                    'access_token' => $user->token,
                    'expires_at' => now()->addSeconds($user->expiresIn)->toDateTimeString(),
                    'authorized_email' => $user->getEmail(),
                    'status' => 'connected',
                ];

                if ($user->refreshToken) {
                    $tokens['refresh_token'] = $user->refreshToken;
                }
            }

            if ($userIntegration) {
                $existing = UserIntegration::where('user_id', auth()->id())->where('service', $service)->first();
                $mergedTokens = array_merge($existing?->data ?? [], $tokens);

                UserIntegration::updateOrCreate(
                    ['user_id' => auth()->id(), 'service' => $service],
                    ['data' => $mergedTokens, 'is_active' => true]
                );

                return redirect()->route('admin.my-integrations.index')
                    ->with('success', 'Successfully connected to '.ucfirst($service));
            }

            if ($resourceId && isset($resource)) {
                $resource->update([
                    'data' => array_merge($resource->getDecryptedData(), $tokens),
                ]);

                return redirect()->route('admin.resources.edit', $resource)
                    ->with('success', 'Successfully connected to '.ucfirst($service));
            }

            $config = array_merge($config, $tokens);
            Configuration::setValue("{$service}_integration", $config);

            return redirect()->route('admin.configs.email')
                ->with("{$service}_success", 'Successfully connected to '.ucfirst($service));

        } catch (\Exception $e) {
            Log::error("OAuth Callback Error ({$service}): ".$e->getMessage());

            if ($userIntegration) {
                $route = route('admin.my-integrations.index');
            } else {
                $route = $resourceId ? route('admin.resources.edit', $resourceId) : route('admin.configs.email');
            }

            return redirect($route)
                ->with('error', "Failed to connect to {$service}: ".$e->getMessage());
        }
    }

    /**
     * Get a Google Client instance configured for OAuth.
     */
    private function getGoogleClientForOAuth(string $service, array $config): GoogleClient
    {
        $driver = $this->getDriver($service);
        $client = app(GoogleClient::class);
        $client->setClientId($config['client_id']);
        $client->setClientSecret($config['client_secret']);
        $client->setRedirectUri(route('admin.oauth.callback', ['service' => $driver]));
        $client->setScopes($this->getScopes($service));
        $client->setAccessType('offline');
        $client->setPrompt('consent select_account');
        $client->setIncludeGrantedScopes(true);

        return $client;
    }

    /**
     * Set Socialite configuration at runtime.
     */
    private function setConfig(string $service, array $config): void
    {
        $driver = $this->getDriver($service);

        config([
            "services.{$driver}.client_id" => $config['client_id'],
            "services.{$driver}.client_secret" => $config['client_secret'],
            "services.{$driver}.redirect" => route('admin.oauth.callback', ['service' => $driver]),
        ]);
    }

    /**
     * Get the Socialite driver name for the service.
     */
    private function getDriver(string $service): string
    {
        return match ($service) {
            'gmail' => 'google',
            default => $service,
        };
    }

    /**
     * Get the required scopes for the service.
     */
    private function getScopes(string $service): array
    {
        return BaseApiService::getScopes($service);
    }
}
