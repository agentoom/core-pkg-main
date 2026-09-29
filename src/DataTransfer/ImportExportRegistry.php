<?php

namespace Agentoom\Core\DataTransfer;

use InvalidArgumentException;

/**
 * Registry of every import/export section.
 *
 * Core registers its own sections from AgentoomCoreServiceProvider; optional
 * packages register theirs from boot(), which is what makes an installed
 * package plug its export/import in and an uninstalled one disappear from the
 * page, the export payload and the import run.
 */
class ImportExportRegistry
{
    public const GROUP_AGENTS = 'agents';

    public const GROUP_SKILLS = 'skills';

    public const GROUP_RESOURCES = 'resources';

    public const GROUP_AUTOMATION = 'automation';

    public const GROUP_COMMUNICATION = 'communication';

    public const GROUP_ACCESS = 'access';

    public const GROUP_SYSTEM = 'system';

    /**
     * Display order of the UI groups.
     */
    public const GROUP_ORDER = [
        self::GROUP_AGENTS,
        self::GROUP_SKILLS,
        self::GROUP_RESOURCES,
        self::GROUP_AUTOMATION,
        self::GROUP_COMMUNICATION,
        self::GROUP_ACCESS,
        self::GROUP_SYSTEM,
    ];

    /**
     * @var array<string, ImportExportSection>
     */
    protected array $sections = [];

    /**
     * Register a section. Section keys must be unique: two sections cannot share
     * the same export payload key.
     */
    public function register(ImportExportSection $section): void
    {
        $key = $section->key();

        if (isset($this->sections[$key])) {
            throw new InvalidArgumentException("Import/export section [{$key}] is already registered.");
        }

        $this->sections[$key] = $section;
    }

    public function has(string $key): bool
    {
        return isset($this->sections[$key]);
    }

    public function get(string $key): ?ImportExportSection
    {
        return $this->sections[$key] ?? null;
    }

    /**
     * @return array<string, ImportExportSection>
     */
    public function all(): array
    {
        return $this->sections;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->sections);
    }

    /**
     * Sections grouped for the UI, in group display order. A group declared by
     * a package is appended after the core groups.
     *
     * @return array<string, array<int, ImportExportSection>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->sections as $section) {
            $grouped[$section->group()][] = $section;
        }

        $order = self::GROUP_ORDER;

        foreach (array_keys($grouped) as $group) {
            if (! in_array($group, $order, true)) {
                $order[] = $group;
            }
        }

        $ordered = [];

        foreach ($order as $group) {
            if (isset($grouped[$group])) {
                $ordered[$group] = $grouped[$group];
            }
        }

        return $ordered;
    }

    /**
     * Sections in import execution order: by level, then registration order.
     *
     * @return array<int, ImportExportSection>
     */
    public function ordered(): array
    {
        $sections = array_values($this->sections);

        usort($sections, fn (ImportExportSection $a, ImportExportSection $b): int => $a->importLevel() <=> $b->importLevel());

        return $sections;
    }
}
