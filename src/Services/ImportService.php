<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Imports the payload of the registered import/export sections.
 */
class ImportService
{
    public function __construct(protected ImportExportRegistry $registry) {}

    /**
     * Import data into the system.
     *
     * Sections run in importLevel() order, so foundation data (projects,
     * configurations) exists before the records that reference it, and
     * an uninstalled package simply has no section to run.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $data
     * @param  array<string, array<string, mixed>>  $options
     */
    public function import(array $data, array $options = []): void
    {
        DB::transaction(function () use ($data, $options) {
            foreach ($this->registry->ordered() as $section) {
                $key = $section->key();

                if (isset($data[$key])) {
                    $section->import($data[$key], $options[$key] ?? []);
                }
            }
        });
    }
}
