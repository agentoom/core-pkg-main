<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class EndpointMountTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_mounts_endpoint_with_allowed_sources(): void
    {
        $user = User::factory()->create();
        if (! Role::where('name', 'superadmin')->exists()) {
            Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        }
        $user->assignRole('superadmin');
        $this->actingAs($user);

        // Create an endpoint with allowed_sources
        $endpoint = Endpoint::create([
            'name' => 'Test Endpoint',
            'slug' => 'test-endpoint',
            'allowed_sources' => ['1.2.3.4', 'example.com'],
            'is_active' => true,
        ]);

        // This should not throw TypeError
        Livewire::test('agentoom-core::admin.endpoint-create-edit', ['endpoint' => $endpoint])
            ->assertSet('allowed_sources', '1.2.3.4, example.com');
    }

    public function test_it_mounts_endpoint_with_null_allowed_sources(): void
    {
        $user = User::factory()->create();
        if (! Role::where('name', 'superadmin')->exists()) {
            Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        }
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $endpoint = Endpoint::create([
            'name' => 'Test Endpoint Null',
            'slug' => 'test-endpoint-null',
            'allowed_sources' => null,
            'is_active' => true,
        ]);

        Livewire::test('agentoom-core::admin.endpoint-create-edit', ['endpoint' => $endpoint])
            ->assertSet('allowed_sources', '');
    }

    public function test_it_mounts_endpoint_with_auth_token(): void
    {
        $user = User::factory()->create();
        if (! Role::where('name', 'superadmin')->exists()) {
            Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        }
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $endpoint = Endpoint::create([
            'name' => 'Token Endpoint',
            'slug' => 'token-endpoint',
            'auth_token_enabled' => true,
            'auth_token' => 'test-token-32-characters-long!!',
            'auth_token_header' => 'X-Custom-Header',
            'is_active' => true,
        ]);

        Livewire::test('agentoom-core::admin.endpoint-create-edit', ['endpoint' => $endpoint])
            ->assertSet('auth_token_enabled', 1)
            ->assertSet('auth_token', 'test-token-32-characters-long!!')
            ->assertSet('auth_token_header', 'X-Custom-Header');
    }

    public function test_it_can_generate_and_save_auth_token(): void
    {
        $user = User::factory()->create();
        if (! Role::where('name', 'superadmin')->exists()) {
            Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        }
        $user->assignRole('superadmin');
        $this->actingAs($user);

        Livewire::test('agentoom-core::admin.endpoint-create-edit')
            ->set('name', 'Token Gen Endpoint')
            ->set('agent_id', null)
            ->set('pipeline_id', null)
            ->set('auth_token_enabled', 1)
            ->call('generateAuthToken')
            ->assertSet('auth_token_enabled', 1)
            ->assertSet('auth_token', fn ($value) => strlen($value) === 32 && ctype_alnum($value))
            ->call('save', true);
    }
}
