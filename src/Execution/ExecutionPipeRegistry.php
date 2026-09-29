<?php

namespace Agentoom\Core\Execution;

/**
 * Registry for execution pipeline pipes contributed by optional packages.
 *
 * Core owns the pipeline execution but no longer hard-references the
 * pipe classes of optional packages. Installed packages (e.g. Sentinel's
 * SecurityBuffer or the compliance package's GovernanceGate) register
 * their pipes here during their service provider boot. Pipes declared in
 * `agentoom.execution.pipes` config are merged before registry pipes.
 */
class ExecutionPipeRegistry
{
    /** @var array<int, class-string|callable> */
    protected array $pipes = [];

    /**
     * Register one or more pipes.
     *
     * @param  array<int, class-string|callable>|class-string|callable  $pipes
     */
    public function register(array|callable|string $pipes): void
    {
        foreach ((array) $pipes as $pipe) {
            if (! in_array($pipe, $this->pipes, true)) {
                $this->pipes[] = $pipe;
            }
        }
    }

    /**
     * All registered pipes in registration order.
     *
     * @return array<int, class-string|callable>
     */
    public function all(): array
    {
        return $this->pipes;
    }
}
