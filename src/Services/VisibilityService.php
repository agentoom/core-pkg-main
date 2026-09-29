<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\McpActionable;
use Agentoom\Core\Models\Tool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;

class VisibilityService
{
    /**
     * Configuration key used to store active visibility groups in the DB.
     */
    public const CONFIG_KEY = 'active_visibility_groups';

    /**
     * Get the set of active visibility groups.
     *
     * Priority: DB (Configuration model) > .env > default (all groups active).
     *
     * @return Collection<int, string>
     */
    public function getActiveGroups(): Collection
    {
        // Check DB override first
        $dbValue = Configuration::getValue(self::CONFIG_KEY);

        if ($dbValue !== null && $dbValue !== '' && ! (is_array($dbValue) && empty($dbValue))) {
            // Handle JSON array (legacy) or CSV string
            if (is_array($dbValue)) {
                return collect($dbValue)->filter(fn ($g) => is_string($g) && $g !== '')->values();
            }

            if (is_string($dbValue)) {
                return $this->parseGroups($dbValue);
            }
        }

        // Fall back to .env
        $envValue = env('AGENTOOM_ACTIVE_VISIBILITY_GROUPS');

        if (is_string($envValue) && $envValue !== '') {
            return $this->parseGroups($envValue);
        }

        // Default: all groups active (empty collection means no filter)
        return collect();
    }

    /**
     * Check if all groups are allowed (no filtering).
     */
    public function isAllGroupsActive(): bool
    {
        return $this->getActiveGroups()->isEmpty();
    }

    /**
     * Check if a specific group is visible.
     */
    public function isGroupVisible(?string $group): bool
    {
        // Null group = no visibility restriction = always visible
        if ($group === null) {
            return true;
        }

        $activeGroups = $this->getActiveGroups();

        // Empty active groups means all are active
        if ($activeGroups->isEmpty()) {
            return true;
        }

        return $activeGroups->contains($group);
    }

    /**
     * Discover all visibility groups used across tools, actionables, and service classes.
     *
     * @return Collection<int, array{name: string, source: string, count: int}>
     */
    public function discoverAllGroups(): Collection
    {
        $groups = collect();

        // Groups from tools table (column may not exist yet during deployment)
        if ($this->hasVisibilityColumn('tools')) {
            $toolGroups = Tool::query()
                ->whereNotNull('visibility_group')
                ->selectRaw('visibility_group, count(*) as count')
                ->groupBy('visibility_group')
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->visibility_group,
                    'source' => 'tools',
                    'count' => $row->count,
                ]);

            $groups = $groups->merge($toolGroups);
        }

        // Groups from MCP actionables table (column may not exist yet during deployment)
        if ($this->hasVisibilityColumn('mcp_actionables')) {
            $actionableGroups = McpActionable::query()
                ->whereNotNull('visibility_group')
                ->selectRaw('visibility_group, count(*) as count')
                ->groupBy('visibility_group')
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->visibility_group,
                    'source' => 'actionables',
                    'count' => $row->count,
                ]);

            $groups = $groups->merge($actionableGroups);
        }

        // Groups from filesystem classes with #[GroupVisibility] + #[Actionable] methods
        $filesystemGroups = $this->discoverFilesystemGroups();
        $groups = $groups->merge($filesystemGroups);

        // Deduplicate by name, summing counts
        return $groups
            ->groupBy('name')
            ->map(fn ($items) => [
                'name' => $items->first()['name'],
                'total' => $items->sum('count'),
                'sources' => $items->pluck('source')->unique()->values()->toArray(),
            ])
            ->values();
    }

    /**
     * Scan filesystem for classes with #[GroupVisibility] attribute that also have actionable methods.
     *
     * @return Collection<int, array{name: string, source: string, count: int}>
     */
    protected function discoverFilesystemGroups(): Collection
    {
        $groups = collect();

        $paths = [
            realpath(__DIR__.'/../'),
            app_path(),
        ];

        foreach ($paths as $path) {
            if (! $path || ! is_dir($path)) {
                continue;
            }

            $finder = new Finder;
            $finder->files()->in($path)->name('*.php')->exclude(['tests', 'Tests', 'test', 'Test']);

            foreach ($finder as $file) {
                try {
                    $content = $file->getContents();
                } catch (\Throwable) {
                    continue;
                }

                // Quick check: does file contain GroupVisibility?
                if (! str_contains($content, 'GroupVisibility')) {
                    continue;
                }

                // Extract class-level #[GroupVisibility] attribute
                if (preg_match('/#\[GroupVisibility\(\s*[\'"](\w[\w-]*?)[\'"]\s*\)\]/', $content, $matches)) {
                    $groupName = $matches[1];

                    // Count #[Actionable] methods in this class
                    $actionableCount = preg_match_all('/#\[Actionable/', $content);

                    if ($actionableCount > 0) {
                        $groups->push([
                            'name' => $groupName,
                            'source' => 'services',
                            'count' => $actionableCount,
                        ]);
                    }
                }
            }
        }

        return $groups;
    }

    /**
     * Check if a table has the visibility_group column (may not exist yet during deployment).
     */
    protected function hasVisibilityColumn(string $table): bool
    {
        try {
            return Schema::hasColumn($table, 'visibility_group');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Parse a CSV string of group names into a collection.
     *
     * @return Collection<int, string>
     */
    protected function parseGroups(string $value): Collection
    {
        return collect(explode(',', $value))
            ->map(fn ($g) => trim($g))
            ->filter(fn ($g) => $g !== '')
            ->values();
    }
}
