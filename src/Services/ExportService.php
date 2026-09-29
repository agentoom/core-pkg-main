<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Project;

/**
 * Exports the import/export sections registered by core and its packages.
 */
class ExportService
{
    public function __construct(protected ImportExportRegistry $registry) {}

    /**
     * Export data from the system.
     *
     * @param  array<string, array<int, int|string>|array{0: 'all'}>  $options
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function export(array $options): array
    {
        $options = $this->prepareOptions($options);

        $data = [];

        foreach ($this->registry->all() as $key => $section) {
            if (isset($options[$key])) {
                $data[$key] = $section->export($options[$key]);
            }
        }

        return $data;
    }

    /**
     * Expand the selection with records the user did not tick explicitly: a
     * selected project brings its own records, a selected record brings the
     * agent it belongs to, and a selected agent brings its own records.
     *
     * Only sections that declare a relationship take part, which is what keeps
     * an export limited to the installed packages.
     *
     * @param  array<string, array<int, int|string>|array{0: 'all'}>  $options
     * @return array<string, array<int, int|string>|array{0: 'all'}>
     */
    protected function prepareOptions(array $options): array
    {
        if (! empty($options['projects'])) {
            $projectIds = $options['projects'] === ['all'] ? Project::pluck('id')->toArray() : $options['projects'];

            foreach ($this->registry->all() as $key => $section) {
                $records = $section->projectIds($projectIds);

                if ($records !== null) {
                    $options[$key] = $this->mergeIds($options[$key] ?? [], $records);
                }
            }
        }

        foreach ($this->registry->all() as $key => $section) {
            if (! empty($options[$key])) {
                $agentIds = $section->agentIds($options[$key]);

                if ($agentIds !== null) {
                    $options['agents'] = $this->mergeIds($options['agents'] ?? [], $agentIds);
                }
            }
        }

        if (! empty($options['agents'])) {
            $agentIds = $options['agents'] === ['all'] ? Agent::pluck('id')->toArray() : $options['agents'];

            foreach ($this->registry->all() as $key => $section) {
                $records = $section->idsForAgents($agentIds);

                if ($records !== null) {
                    $options[$key] = $this->mergeIds($options[$key] ?? [], $records);
                }
            }
        }

        return $options;
    }

    /**
     * Merge two arrays of IDs, handling the 'all' value.
     *
     * @param  array<int, int|string>|array{0: 'all'}  $current
     * @param  array<int, int|string>  $new
     * @return array<int, int|string>|array{0: 'all'}
     */
    protected function mergeIds(array $current, array $new): array
    {
        if ($current === ['all']) {
            return ['all'];
        }

        return array_values(array_unique(array_merge($current, $new)));
    }
}
