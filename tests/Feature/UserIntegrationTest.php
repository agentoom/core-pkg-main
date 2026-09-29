<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\UserIntegration;
use Agentoom\Core\Services\Api\GmailService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Google\Client as GoogleClient;
use Google\Service\Oauth2;
use Google\Service\Oauth2\Userinfo;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('agentoom:install');

        // agentoom:install resets this to '0' for fresh installations, which makes
        // the onboarding middleware redirect every admin page away from these tests.
        Configuration::setValue('onboarding_complete', '1');

        $this->superadmin = User::where('email', 'superadmin@unusualdope.com')->first();
        $this->user = User::factory()->create();
        $this->user->assignRole('user');
    }

    public function test_user_can_access_my_integrations_but_not_admin_integrations()
    {
        $this->actingAs($this->user)
            ->get(route('admin.my-integrations.index'))
            ->assertOk()
            ->assertSee(__('agentoom-core::admin.integrations.my_integrations'))
            ->assertDontSee(__('agentoom-core::admin.configs.email_configuration'));

        $this->actingAs($this->user)
            ->get(route('admin.configs.email'))
            ->assertForbidden();
    }

    public function test_my_integrations_page_shows_enabled_status_from_admin_config()
    {
        // Initially, no services are enabled by admin
        $this->actingAs($this->user)
            ->get(route('admin.my-integrations.index'))
            ->assertSee('Disabled');

        // Admin enables Gmail
        Configuration::setValue('gmail_integration', [
            'client_id' => 'abc',
            'client_secret' => '123',
            'status' => 'not_connected',
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.my-integrations.index'))
            ->assertSee('Gmail')
            ->assertSee('Not Connected');
    }

    public function test_user_oauth_flow_saves_to_user_integrations_table()
    {
        Configuration::setValue('gmail_integration', [
            'client_id' => 'abc',
            'client_secret' => '123',
        ]);

        $googleClient = $this->mock(GoogleClient::class);
        $googleClient->shouldReceive('setClientId')->once();
        $googleClient->shouldReceive('setClientSecret')->once();
        $googleClient->shouldReceive('setRedirectUri')->once();
        $googleClient->shouldReceive('setScopes')->once();
        $googleClient->shouldReceive('setAccessType')->once();
        $googleClient->shouldReceive('setPrompt')->once();
        $googleClient->shouldReceive('setIncludeGrantedScopes')->once();
        $googleClient->shouldReceive('fetchAccessTokenWithAuthCode')->andReturn([
            'access_token' => 'test-token',
            'refresh_token' => 'test-refresh',
            'expires_in' => 3600,
        ]);
        $googleClient->shouldReceive('setAccessToken')->once();

        $oauth2Mock = $this->createMock(Oauth2::class);
        $userinfoMock = $this->createMock(Oauth2\Resource\Userinfo::class);
        $googleUser = $this->createMock(Userinfo::class);
        $googleUser->method('getEmail')->willReturn('user@example.com');

        $oauth2Mock->userinfo = $userinfoMock;
        $userinfoMock->method('get')->willReturn($googleUser);

        $this->app->instance(GoogleClient::class, $googleClient);
        $this->app->bind(Oauth2::class, function () use ($oauth2Mock) {
            return $oauth2Mock;
        });

        $state = json_encode(['service' => 'gmail', 'user_integration' => true]);

        $this->actingAs($this->user)
            ->get(route('admin.oauth.callback', ['service' => 'google', 'state' => $state, 'code' => 'test-code']))
            ->assertRedirect(route('admin.my-integrations.index'));

        $this->assertDatabaseHas('user_integrations', [
            'user_id' => $this->user->id,
            'service' => 'gmail',
            'is_active' => true,
        ]);

        $integration = UserIntegration::where('user_id', $this->user->id)->first();
        $this->assertEquals('test-token', $integration->data['access_token']);
        $this->assertEquals('user@example.com', $integration->data['authorized_email']);
    }

    public function test_base_api_service_resolves_user_integration_automatically()
    {
        // Admin config exists but standard user should not see it
        Configuration::setValue('gmail_integration', [
            'client_id' => 'abc',
            'client_secret' => '123',
            'access_token' => 'admin-token',
            'status' => 'connected',
        ]);

        $this->actingAs($this->user);

        // Helper class to access protected method
        $service = new class extends GmailService
        {
            public function getResolvedData()
            {
                return $this->resolveResourceData(null, [], 'gmail');
            }
        };

        // Without user integration, it should be empty for standard user
        $this->assertEmpty($service->getResolvedData());

        // Create user integration
        UserIntegration::create([
            'user_id' => $this->user->id,
            'service' => 'gmail',
            'data' => [
                'access_token' => 'user-token',
                'status' => 'connected',
                'expires_at' => now()->addHour()->toDateTimeString(),
            ],
        ]);

        // Now it should resolve to user integration
        $this->assertEquals('user-token', $service->getResolvedData()['access_token']);

        // Admin should still resolve to admin config if they don't have personal integration
        $this->actingAs($this->superadmin);
        $this->assertEquals('admin-token', $service->getResolvedData()['access_token']);
    }

    public function test_user_oauth_flow_preserves_existing_refresh_token()
    {
        Configuration::setValue('gmail_integration', [
            'client_id' => 'abc',
            'client_secret' => '123',
        ]);

        // Create initial integration with refresh token
        UserIntegration::create([
            'user_id' => $this->user->id,
            'service' => 'gmail',
            'data' => [
                'access_token' => 'old-token',
                'refresh_token' => 'persistent-refresh-token',
                'status' => 'connected',
            ],
            'is_active' => true,
        ]);

        $googleClient = $this->mock(GoogleClient::class);
        $googleClient->shouldReceive('setClientId', 'setClientSecret', 'setRedirectUri', 'setScopes', 'setAccessType', 'setPrompt', 'setIncludeGrantedScopes');
        $googleClient->shouldReceive('fetchAccessTokenWithAuthCode')->andReturn([
            'access_token' => 'new-access-token',
            'expires_in' => 3600,
            // refresh token omitted
        ]);
        $googleClient->shouldReceive('setAccessToken')->once();

        $userinfoMock = $this->createMock(Oauth2\Resource\Userinfo::class);
        $googleUser = $this->createMock(Userinfo::class);
        $googleUser->method('getEmail')->willReturn('user@example.com');
        $userinfoMock->method('get')->willReturn($googleUser);

        $oauth2Mock = $this->createMock(Oauth2::class);
        $oauth2Mock->userinfo = $userinfoMock;

        $this->app->instance(GoogleClient::class, $googleClient);
        $this->app->bind(Oauth2::class, function () use ($oauth2Mock) {
            return $oauth2Mock;
        });

        $state = json_encode(['service' => 'gmail', 'user_integration' => true]);

        $this->actingAs($this->user)
            ->get(route('admin.oauth.callback', ['service' => 'google', 'state' => $state, 'code' => 'test-code']))
            ->assertRedirect(route('admin.my-integrations.index'));

        $integration = UserIntegration::where('user_id', $this->user->id)->first();
        $this->assertEquals('new-access-token', $integration->data['access_token']);
        $this->assertEquals('persistent-refresh-token', $integration->data['refresh_token']);
    }

    public function test_admin_oauth_flow_preserves_existing_refresh_token()
    {
        Configuration::setValue('gmail_integration', [
            'client_id' => 'abc',
            'client_secret' => '123',
            'refresh_token' => 'admin-refresh-token',
            'status' => 'connected',
        ]);

        $googleClient = $this->mock(GoogleClient::class);
        $googleClient->shouldReceive('setClientId', 'setClientSecret', 'setRedirectUri', 'setScopes', 'setAccessType', 'setPrompt', 'setIncludeGrantedScopes');
        $googleClient->shouldReceive('fetchAccessTokenWithAuthCode')->andReturn([
            'access_token' => 'new-admin-access-token',
            'expires_in' => 3600,
        ]);
        $googleClient->shouldReceive('setAccessToken')->once();

        $userinfoMock = $this->createMock(Oauth2\Resource\Userinfo::class);
        $googleUser = $this->createMock(Userinfo::class);
        $googleUser->method('getEmail')->willReturn('admin@example.com');
        $userinfoMock->method('get')->willReturn($googleUser);

        $oauth2Mock = $this->createMock(Oauth2::class);
        $oauth2Mock->userinfo = $userinfoMock;

        $this->app->instance(GoogleClient::class, $googleClient);
        $this->app->bind(Oauth2::class, function () use ($oauth2Mock) {
            return $oauth2Mock;
        });

        $state = json_encode(['service' => 'gmail']);

        $this->actingAs($this->superadmin)
            ->get(route('admin.oauth.callback', ['service' => 'google', 'state' => $state, 'code' => 'test-code']))
            ->assertRedirect(route('admin.configs.email'));

        $config = Configuration::getValue('gmail_integration');
        $this->assertEquals('new-admin-access-token', $config['access_token']);
        $this->assertEquals('admin-refresh-token', $config['refresh_token']);
    }
}
