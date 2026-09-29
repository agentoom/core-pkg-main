<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Livewire\Component;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ResourceExport extends Component
{
    public $show_modal = false;

    public $resource_type_id = '';

    public $format = 'csv';

    public function mount($resourceTypeId = '')
    {
        $this->resource_type_id = $resourceTypeId;
    }

    public function export()
    {
        $this->validate([
            'resource_type_id' => 'required|exists:resource_types,id',
            'format' => 'required|in:csv,xls,xlsx',
        ]);

        $resourceType = ResourceType::findOrFail($this->resource_type_id);
        $resources = Resource::visibleTo(auth()->user())
            ->where('resource_type_id', $this->resource_type_id);

        if ($resources->count() === 0) {
            $this->addError('resource_type_id', __('agentoom-core::admin.common.no_results'));

            return;
        }

        if (in_array($this->format, ['xls', 'xlsx'])) {
            return $this->exportExcel($resourceType, $resources);
        }

        return $this->exportCsv($resourceType, $resources);
    }

    protected function exportCsv(ResourceType $resourceType, $resourcesQuery)
    {
        $filename = str_replace(' ', '_', strtolower($resourceType->name_translated)).'_export_'.now()->format('Y-m-d_H-i-s').'.csv';

        // Headers
        $headers = ['name', 'description'];
        $typeFields = array_column($resourceType->fields, 'name');
        $headers = array_merge($headers, $typeFields);

        $callback = function () use ($resourcesQuery, $headers, $typeFields) {
            DB::connection()->disableQueryLog();

            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach ($resourcesQuery->cursor() as $resource) {
                $row = [
                    $resource->name,
                    $resource->description,
                ];

                $data = $resource->data;
                foreach ($typeFields as $field) {
                    $row[] = $data[$field] ?? '';
                }

                fputcsv($file, $row);
            }

            fclose($file);
        };

        $responseHeaders = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $this->show_modal = false;

        return Response::streamDownload($callback, $filename, $responseHeaders);
    }

    protected function exportExcel(ResourceType $resourceType, $resourcesQuery)
    {
        $extension = $this->format;
        $filename = str_replace(' ', '_', strtolower($resourceType->name_translated)).'_export_'.now()->format('Y-m-d_H-i-s').'.'.$extension;

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = ['name', 'description'];
        $typeFields = array_column($resourceType->fields, 'name');
        $headers = array_merge($headers, $typeFields);

        foreach ($headers as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'1', $header);
        }

        $rowNumber = 2;
        foreach ($resourcesQuery->cursor() as $resource) {
            $sheet->setCellValue('A'.$rowNumber, $resource->name);
            $sheet->setCellValue('B'.$rowNumber, $resource->description);

            $data = $resource->data;
            foreach ($typeFields as $index => $field) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 3).$rowNumber, $data[$field] ?? '');
            }

            $rowNumber++;
        }

        $writer = IOFactory::createWriter($spreadsheet, ucfirst($extension));

        $callback = function () use ($writer) {
            DB::connection()->disableQueryLog();
            $writer->save('php://output');
        };

        $contentType = $extension === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'application/vnd.ms-excel';

        $responseHeaders = [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $this->show_modal = false;

        return Response::streamDownload($callback, $filename, $responseHeaders);
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.resource-export', [
            'resourceTypes' => ResourceType::visibleTo(auth()->user())->get()->sortBy(fn ($t) => $t->name_translated),
        ]);
    }
}
