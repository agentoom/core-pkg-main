<?php

namespace Agentoom\Core\Services;

/**
 * Collects permission groups from core and from every installed optional
 * package, so that a package's permissions are only ever offered — and only
 * ever seeded — while that package is installed.
 *
 * `live-support.view` must not exist on an instance without live support,
 * exactly like `widgets.view` must not exist without chat-widget.
 */
class PermissionRegistry
{
    /**
     * @var array<string, array<int, string>>
     */
    protected array $groups = [];

    /**
     * Register a permission group. Registering the same group twice merges
     * the permissions, so a package can extend core's list without having to
     * know what core already declared.
     *
     * @param  array<int, string>  $permissions
     */
    public function register(string $group, array $permissions): void
    {
        $this->groups[$group] = array_values(array_unique(array_merge(
            $this->groups[$group] ?? [],
            $permissions
        )));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function all(): array
    {
        return $this->groups;
    }

    /**
     * @return array<int, string>
     */
    public function group(string $group): array
    {
        return $this->groups[$group] ?? [];
    }

    /**
     * Every permission name across every registered group.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return array_values(array_unique(array_merge(...array_values($this->groups) ?: [[]])));
    }
}
