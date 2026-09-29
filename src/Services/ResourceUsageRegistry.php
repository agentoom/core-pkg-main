<?php

namespace Agentoom\Core\Services;

use Closure;

/**
 * Registry of usage counters backing the resource limits and the dashboard.
 *
 * Core registers counters for the models it owns. Optional packages register
 * counters for their own models from their service providers, so
 * ResourceLimitManager never references classes of packages that may not be
 * installed.
 *
 * Keys must match the `agentoom.limits.{key}` config key and the
 * `admin.dashboard_view.resources.{key}` translation key.
 *
 * The shared instance is bound by the core provider during registration;
 * packages register their counters from their provider's `boot()` method so
 * provider ordering cannot drop registrations.
 */
class ResourceUsageRegistry
{
    /**
     * @var array<string, Closure(): int>
     */
    protected array $counters = [];

    /**
     * Register a usage counter under the given key.
     *
     * @param  Closure(): int  $counter
     */
    public function register(string $key, Closure $counter): void
    {
        $this->counters[$key] = $counter;
    }

    /**
     * Resolve every registered counter.
     *
     * @return array<string, int>
     */
    public function usage(): array
    {
        $usage = [];

        foreach ($this->counters as $key => $counter) {
            $usage[$key] = $counter();
        }

        return $usage;
    }
}
