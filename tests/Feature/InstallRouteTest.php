<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class InstallRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_route_returns_404_when_already_initialized(): void
    {
        // 1. Initialize
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@unusualdope.com',
            'password' => bcrypt('password'),
        ]);

        // 2. Try to hit /install
        $response = $this->get('/install');

        // 3. Assert 404
        $response->assertStatus(404);
    }

    public function test_install_route_executes_installation_when_not_initialized(): void
    {
        // Ensure not initialized
        $this->assertFalse(User::where('email', 'superadmin@unusualdope.com')->exists());

        // Hit /install
        $response = $this->get('/install');

        // Assert success message
        $response->assertStatus(200);
        $response->assertSee('Installation completed successfully');

        // Verify superadmin user exists
        $this->assertTrue(User::where('email', 'superadmin@unusualdope.com')->exists());
        $this->assertTrue(Role::where('name', 'superadmin')->exists());

        // Verify onboarding is enabled (not marked complete) for fresh installations
        $this->assertEquals('0', Configuration::getValue('onboarding_complete'));
    }
}
