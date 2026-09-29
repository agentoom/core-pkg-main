<?php

namespace Agentoom\Core\Http\Middleware;

use Agentoom\Core\Models\Configuration;
use Closure;
use Illuminate\Http\Request;

class CheckOnboarding
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Skip if already on the onboarding route, API routes, or endpoint routes
        if ($request->routeIs('admin.onboarding') || $request->is('api/*') || $request->is('e/*')) {
            return $next($request);
        }

        // Redirect to onboarding if not yet completed
        if (! Configuration::getValue('onboarding_complete', false)) {
            return redirect()->route('admin.onboarding');
        }

        return $next($request);
    }
}
