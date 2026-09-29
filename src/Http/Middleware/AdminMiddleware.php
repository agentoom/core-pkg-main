<?php

namespace Agentoom\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasRole(['admin', 'superadmin']) || $user->getAllPermissions()->isNotEmpty()) {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
    }
}
