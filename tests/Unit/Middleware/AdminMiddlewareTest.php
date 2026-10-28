<?php

namespace Agentoom\Core\Tests\Unit\Middleware;

use Agentoom\Core\Http\Middleware\AdminMiddleware;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Http\Request;
use Livewire\Features\SupportRedirects\Redirector;
use Mockery;

class AdminMiddlewareTest extends TestCase
{
    public function test_it_does_not_crash_on_livewire_redirector()
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasRole')->with(['admin', 'superadmin'])->andReturn(true);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->andReturn($user);

        $redirector = Mockery::mock(Redirector::class);

        $middleware = new AdminMiddleware;

        $result = $middleware->handle($request, function () use ($redirector) {
            return $redirector;
        });

        $this->assertSame($redirector, $result);
    }

    public function test_it_registers_core_middleware_aliases()
    {
        $router = app('router');
        $middlewares = $router->getMiddleware();

        $this->assertArrayHasKey('admin', $middlewares);
        $this->assertSame(AdminMiddleware::class, $middlewares['admin']);
        $this->assertArrayHasKey('superadmin', $middlewares);
        $this->assertArrayHasKey('check.onboarding', $middlewares);
        $this->assertArrayHasKey('endpoint.rate.limit', $middlewares);
        $this->assertArrayHasKey('mcp.api.token', $middlewares);
        $this->assertArrayHasKey('role', $middlewares);
        $this->assertArrayHasKey('permission', $middlewares);
        $this->assertArrayHasKey('role_or_permission', $middlewares);
    }
}
