<?php

namespace Agentoom\Core\Http\Middleware;

use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Endpoint;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class EndpointRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $slug = $request->route('slug');

        $endpoint = Endpoint::where('slug', $slug)->first();

        if (! $endpoint) {
            return $next($request);
        }

        // Check if rate limiting is enabled for this endpoint
        $rateLimitEnabled = $endpoint->rate_limit_enabled
            ?? Configuration::getValue('endpoint_rate_limit_enabled', false);

        if (! $rateLimitEnabled) {
            return $next($request);
        }

        $ip = $this->normalizeIp($request->ip());

        // Per-minute rate limit
        $perMinute = $endpoint->rate_limit_max_per_minute
            ?? (int) Configuration::getValue('endpoint_rate_limit_max_per_minute', 60);

        $minuteKey = 'endpoint:'.$slug.':'.$ip.':minute';

        if (RateLimiter::tooManyAttempts($minuteKey, $perMinute)) {
            $retryAfter = RateLimiter::availableIn($minuteKey);

            return response()->json([
                'message' => 'Too many requests. Please try again later.',
            ], 429)->withHeaders([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $perMinute,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($minuteKey, 60);

        // Per-hour rate limit
        $perHour = $endpoint->rate_limit_max_per_hour
            ?? (int) Configuration::getValue('endpoint_rate_limit_max_per_hour', 1000);

        $hourKey = 'endpoint:'.$slug.':'.$ip.':hour';

        if (RateLimiter::tooManyAttempts($hourKey, $perHour)) {
            $retryAfter = RateLimiter::availableIn($hourKey);

            return response()->json([
                'message' => 'Too many requests. Please try again later.',
            ], 429)->withHeaders([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $perHour,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($hourKey, 3600);

        $remaining = $perMinute - RateLimiter::attempts($minuteKey);

        $response = $next($request);

        // Add rate limit headers to successful responses
        $response->headers->set('X-RateLimit-Limit', $perMinute);
        $response->headers->set('X-RateLimit-Remaining', max(0, $remaining));

        return $response;
    }

    /**
     * Normalize IPv6 addresses to /64 prefix for fair counting.
     */
    protected function normalizeIp(?string $ip): string
    {
        if (empty($ip)) {
            return 'unknown';
        }

        // Normalize IPv6 to /64 prefix
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $binary = inet_pton($ip);
            // Zero out the last 8 bytes (64 bits) for /64 prefix
            $prefix = substr($binary, 0, 8).str_repeat("\0", 8);

            return inet_ntop($prefix).'/64';
        }

        return $ip;
    }
}
