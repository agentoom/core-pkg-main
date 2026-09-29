<?php

namespace Agentoom\Core\DataTransfer;

use Illuminate\Support\Collection;

/**
 * One exportable/importable section of the admin Import/Export page.
 *
 * Core ships a section per core data type; optional packages register their own
 * from their service provider. Uninstalling a package therefore removes its
 * section from the export list, its payload key from exports and its handler
 * from imports without any change to core.
 */
interface ImportExportSection
{
    /**
     * Payload key, e.g. "agents". Also the key of the export options array.
     */
    public function key(): string;

    /**
     * UI group the section is listed under: agents, skills, resources,
     * automation, communication, access or system.
     */
    public function group(): string;

    /**
     * Translated section title, shown above the section's checkboxes.
     */
    public function label(): string;

    /**
     * Translated label of the section's "select all" checkbox.
     */
    public function allLabel(): string;

    /**
     * Permission required to use this section; null means superadmin only.
     */
    public function permission(): ?string;

    /**
     * Records offered for selection in the UI.
     */
    public function options(): Collection;

    /**
     * Value submitted by the checkbox of a record returned by options().
     */
    public function optionId(mixed $option): int|string;

    /**
     * Label shown next to the checkbox of a record returned by options().
     */
    public function optionLabel(mixed $option): string;

    /**
     * Export the selected records.
     *
     * @param  array<int, int|string>|array{0: 'all'}  $ids
     * @return array<int, array<string, mixed>>
     */
    public function export(array $ids): array;

    /**
     * Import previously exported records.
     *
     * $options carries this section's entry of ImportService::import()'s
     * $options argument, so a section can define its own import modifiers.
     *
     * @param  array<int, array<string, mixed>>  $payload
     * @param  array<string, mixed>  $options
     */
    public function import(array $payload, array $options = []): void;

    /**
     * Import phase; lower levels run first (0 foundation … 7 access control).
     */
    public function importLevel(): int;

    /**
     * Ids of this section's records owned by the given projects.
     *
     * Return null when the section's records have no project owner, which keeps
     * this section out of project exports.
     *
     * @param  array<int, int|string>  $projectIds
     * @return array<int, int|string>|null
     */
    public function projectIds(array $projectIds): ?array;

    /**
     * Ids of the agents referenced by the given records of this section, so
     * that selecting a record also exports the agent it belongs to.
     *
     * Return null when the section's records do not belong to an agent, which
     * keeps the agents key out of an export that selected this section.
     *
     * @param  array<int, int|string>|array{0: 'all'}  $ids
     * @return array<int, int|string>|null
     */
    public function agentIds(array $ids): ?array;

    /**
     * Ids of this section's records owned by the given agents, so that
     * selecting an agent also exports the records that depend on it.
     *
     * Return null when the section's records do not belong to an agent, which
     * keeps this section out of agent exports.
     *
     * @param  array<int, int|string>  $agentIds
     * @return array<int, int|string>|null
     */
    public function idsForAgents(array $agentIds): ?array;
}
