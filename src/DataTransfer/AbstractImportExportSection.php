<?php

namespace Agentoom\Core\DataTransfer;

use Agentoom\Core\Models\User;
use Illuminate\Support\Collection;

/**
 * Convenience base for import/export sections.
 *
 * A plain section only implements key(), export() and import(); everything else
 * has a neutral default. Labels default to the core translation keys that match
 * the section key, so a package section named after an existing admin section
 * needs no translations of its own.
 */
abstract class AbstractImportExportSection implements ImportExportSection
{
    public function group(): string
    {
        return ImportExportRegistry::GROUP_SYSTEM;
    }

    public function label(): string
    {
        return __('agentoom-core::admin.permissions.groups.'.$this->key());
    }

    public function allLabel(): string
    {
        return __('agentoom-core::admin.import_export.export.all_'.$this->key());
    }

    public function permission(): ?string
    {
        return null;
    }

    public function options(): Collection
    {
        return collect();
    }

    public function optionId(mixed $option): int|string
    {
        return $option->getKey();
    }

    public function optionLabel(mixed $option): string
    {
        return (string) ($option->name ?? $option->getKey());
    }

    public function import(array $payload, array $options = []): void {}

    public function importLevel(): int
    {
        return 3;
    }

    public function projectIds(array $projectIds): ?array
    {
        return null;
    }

    public function agentIds(array $ids): ?array
    {
        return null;
    }

    public function idsForAgents(array $agentIds): ?array
    {
        return null;
    }

    /**
     * Ids selected for export, or null when the user asked for everything.
     *
     * @param  array<int, int|string>|array{0: 'all'}  $ids
     * @return array<int, int|string>|null
     */
    protected function selectedIds(array $ids): ?array
    {
        return $ids === ['all'] ? null : $ids;
    }

    /**
     * Owner recorded on imported rows: the acting user, else the fallback superadmin.
     */
    protected function ownerId(): ?int
    {
        return auth()->id() ?? User::where('email', 'superadmin@unusualdope.com')->first()?->id;
    }
}
