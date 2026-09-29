<?php

declare(strict_types=1);

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 * Every admin route must start a session.
 *
 * Package route files are loaded by their own service providers, so they do not
 * inherit the framework's `web` group the way routes/web.php does. Without it no
 * session is started, the auth guard resolves no user, and every navigation
 * collapses into the guest chain visible in the network tab:
 *
 *     GET /admin/privacy-recognizers → 302 → /login → /home → /admin/dashboard
 *
 * Regression guard for that class of bug across all packages.
 */
class AdminRouteSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_route_starts_a_session(): void
    {
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'admin.') && ! str_starts_with($route->uri(), 'admin')) {
                continue;
            }

            if (! in_array('web', $route->gatherMiddleware(), true)) {
                $missing[] = $route->uri().' ['.$name.']';
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Admin routes missing the web middleware group (no session): '.implode(', ', $missing),
        );
    }
}
