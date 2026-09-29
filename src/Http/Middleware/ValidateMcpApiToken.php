<?php

namespace Agentoom\Core\Http\Middleware;

use Agentoom\Core\Models\Configuration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateMcpApiToken
{
    /**
     * Handle an incoming request and validate the MCP API token.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $storedToken = Configuration::getValue('mcp_api_token');

        if (empty($storedToken)) {
            return response()->json(['message' => 'MCP API token not configured.'], 401);
        }

        $bearerToken = $request->bearerToken();

        if (empty($bearerToken) || ! hash_equals($storedToken, $bearerToken)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
