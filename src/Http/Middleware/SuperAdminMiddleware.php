<?php

namespace Agentoom\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->hasRole('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
