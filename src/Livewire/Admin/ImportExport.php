<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\DataTransfer\ImportExportSection;
use Agentoom\Core\Services\ExportService;
use Agentoom\Core\Services\ImportService;
use Illuminate\Support\Facades\Response;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Generic import/export page: every section shown here is contributed by the
 * registry, so a package that is not installed has no section, no checkbox and
 * no payload key.
 */
class ImportExport extends Component
{
    use WithFileUploads;

    public $importFile;

    public $importText;

    /**
     * Selected ids per section key; ['all'] when the section's select-all box is on.
     *
     * @var array<string, array<int, int|string>|array{0: 'all'}>
     */
    public $exportOptions = [];

    /**
     * State of each section's select-all checkbox, keyed by section key.
     *
     * @var array<string, bool>
     */
    public $selectAll = [];

    public function mount(ImportExportRegistry $registry): void
    {
        foreach ($registry->keys() as $key) {
            $this->exportOptions[$key] = [];
            $this->selectAll[$key] = false;
        }
    }

    public function updatedSelectAll($value, $key): void
    {
        if (! isset($this->selectAll[$key])) {
            return;
        }

        $this->exportOptions[$key] = $value ? ['all'] : [];
    }

    protected function isSuperAdmin(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole('superadmin');
    }

    protected function canAccessSection(ImportExportSection $section): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permission = $section->permission();

        if ($permission === null) {
            return false; // superadmin only
        }

        return auth()->user()?->can($permission) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function getAllowedSectionKeys(ImportExportRegistry $registry): array
    {
        return array_keys(array_filter(
            $registry->all(),
            fn (ImportExportSection $section): bool => $this->canAccessSection($section)
        ));
    }

    public function import(ImportService $importService, ImportExportRegistry $registry): void
    {
        $data = [];

        if ($this->importFile) {
            $content = file_get_contents($this->importFile->getRealPath());
            $data = json_decode($content, true);
        } elseif ($this->importText) {
            $data = json_decode($this->importText, true);
        }

        if (! $data) {
            $this->addError('import', __('agentoom-core::admin.import_export.import.invalid_json'));

            return;
        }

        // Strip sections the user has no access to
        $data = array_intersect_key($data, array_flip($this->getAllowedSectionKeys($registry)));

        try {
            $importService->import($data);
            $this->importFile = null;
            $this->importText = '';
            session()->flash('success', __('agentoom-core::admin.import_export.import.success'));
        } catch (\Exception $e) {
            $this->addError('import', __('agentoom-core::admin.import_export.import.error', ['message' => $e->getMessage()]));
        }
    }

    public function export(ExportService $exportService, ImportExportRegistry $registry)
    {
        // Strip sections the user has no access to
        $allowedSections = $this->getAllowedSectionKeys($registry);
        $options = array_filter($this->exportOptions, function ($val, $key) use ($allowedSections) {
            return ! empty($val) && in_array($key, $allowedSections, true);
        }, ARRAY_FILTER_USE_BOTH);

        if (empty($options)) {
            $this->addError('export', __('agentoom-core::admin.import_export.export.select_at_least_one'));

            return;
        }

        $data = $exportService->export($options);
        $json = json_encode($data, JSON_PRETTY_PRINT);

        return Response::streamDownload(function () use ($json) {
            echo $json;
        }, 'export-'.now()->format('Y-m-d-H-i-s').'.json');
    }

    public function render(ImportExportRegistry $registry)
    {
        $groups = [];

        foreach ($registry->grouped() as $group => $sections) {
            $allowed = array_values(array_filter(
                $sections,
                fn (ImportExportSection $section): bool => $this->canAccessSection($section)
            ));

            if ($allowed === []) {
                continue;
            }

            $groups[$group] = [
                'title' => $this->groupTitle($allowed),
                'sections' => array_map(function (ImportExportSection $section): array {
                    return [
                        'key' => $section->key(),
                        'label' => $section->label(),
                        'all_label' => $section->allLabel(),
                        'options' => $section->options()->map(function ($option) use ($section): array {
                            return [
                                'id' => $section->optionId($option),
                                'label' => $section->optionLabel($option),
                            ];
                        })->values()->all(),
                    ];
                }, $allowed),
            ];
        }

        $openGroups = array_fill_keys(array_keys($groups), false);

        if ($firstGroup = array_key_first($groups)) {
            $openGroups[$firstGroup] = true;
        }

        return view('agentoom-core::livewire.admin.import-export', [
            'groups' => $groups,
            'openGroups' => $openGroups,
        ])->layout('agentoom-core::layouts.admin', [
            'title' => __('agentoom-core::admin.import_export.title'),
            'headerTitle' => __('agentoom-core::admin.import_export.title'),
        ]);
    }

    /**
     * Title of a group header, built from its sections ("Pipelines, Schedules & Endpoints").
     *
     * @param  array<int, ImportExportSection>  $sections
     */
    protected function groupTitle(array $sections): string
    {
        $labels = array_map(fn (ImportExportSection $section): string => $section->label(), $sections);

        if (count($labels) === 1) {
            return $labels[0];
        }

        return implode(', ', array_slice($labels, 0, -1)).' & '.end($labels);
    }
}
