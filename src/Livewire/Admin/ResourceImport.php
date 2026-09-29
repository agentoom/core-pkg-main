<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ResourceImport extends Component
{
    use WithFileUploads;

    public $file;

    public $resource_type_id;

    public $headers = [];

    public $mapping = [];

    public $unique_field = '';

    public $show_modal = false;

    public $step = 1; // 1: Upload, 2: Mapping

    public $file_path;

    protected $rules = [
        'file' => 'required|mimes:csv,txt,xls,xlsx|max:10240',
        'resource_type_id' => 'required|exists:resource_types,id',
    ];

    public function mount()
    {
        $this->resource_type_id = request()->query('resource_type_id', '');
    }

    public function updatedFile()
    {
        $this->validateOnly('file');
        $this->parseHeaders();
    }

    public function parseHeaders()
    {
        if (! $this->file) {
            return;
        }

        $path = $this->file->getRealPath();
        $extension = $this->file->getClientOriginalExtension();

        if (in_array(strtolower($extension), ['csv', 'txt'])) {
            if (($handle = fopen($path, 'r')) !== false) {
                $this->headers = fgetcsv($handle);
                fclose($handle);
            }
        } elseif (in_array(strtolower($extension), ['xls', 'xlsx'])) {
            try {
                $spreadsheet = IOFactory::load($path);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestColumn = $worksheet->getHighestColumn();
                $this->headers = $worksheet->rangeToArray('A1:'.$highestColumn.'1', null, true, false)[0];

                // Clean up headers (remove nulls or empty strings from the end)
                $this->headers = array_filter($this->headers, fn ($header) => ! is_null($header) && $header !== '');
            } catch (\Exception $e) {
                $this->headers = [];
                $this->addError('file', 'Error parsing Excel file: '.$e->getMessage());
            }
        } else {
            $this->headers = [];
        }

        if (! empty($this->headers)) {
            $this->autoMap();
        }
    }

    public function autoMap()
    {
        $this->mapping = [];
        if (! $this->resource_type_id || empty($this->headers)) {
            return;
        }

        $resourceType = ResourceType::find($this->resource_type_id);
        if (! $resourceType) {
            return;
        }

        $targetFields = array_merge([
            ['name' => 'name'],
            ['name' => 'description'],
        ], $resourceType->fields);

        foreach ($targetFields as $field) {
            $fieldName = $field['name'];
            $match = false;

            foreach ($this->headers as $index => $header) {
                $headerClean = strtolower(trim($header));
                $fieldClean = strtolower(trim($fieldName));

                if ($headerClean === $fieldClean) {
                    $match = $index;
                    break;
                }
            }

            if ($match !== false) {
                $this->mapping[$fieldName] = (string) $match;
            } else {
                $this->mapping[$fieldName] = '';
            }
        }
    }

    public function updatedResourceTypeId()
    {
        if (! empty($this->headers)) {
            $this->autoMap();
        }
    }

    public function next()
    {
        $this->validate();

        $this->file_path = $this->file->store('temp_imports');
        $this->step = 2;
    }

    public function import()
    {
        $this->validate([
            'mapping.*' => 'nullable',
        ]);

        $resourceType = ResourceType::findOrFail($this->resource_type_id);
        $path = Storage::path($this->file_path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $count = 0;
        if (in_array($extension, ['csv', 'txt', 'tmp'])) {
            $count = $this->importCsv($path, $resourceType);
        } elseif (in_array($extension, ['xls', 'xlsx'])) {
            $count = $this->importExcel($path, $resourceType);
        } else {
            $this->addError('file', 'Unsupported file format.');

            return;
        }

        Storage::delete($this->file_path);

        session()->flash('success', __('agentoom-core::admin.resources.import_success', ['count' => $count]));

        $this->dispatch('resourceImported');
        $this->reset(['file', 'headers', 'mapping', 'step', 'file_path', 'unique_field']);
        $this->show_modal = false;

        return redirect(route('admin.resources.index', ['resource_type_id' => $this->resource_type_id]));
    }

    protected function importCsv(string $path, ResourceType $resourceType): int
    {
        $count = 0;
        $batchSize = 500;

        // Disable query log to save memory
        DB::connection()->disableQueryLog();

        if (($handle = fopen($path, 'r')) !== false) {
            fgetcsv($handle); // Skip header row

            DB::beginTransaction();
            try {
                while (($row = fgetcsv($handle)) !== false) {
                    $data = [];
                    $name = null;
                    $description = '';

                    foreach ($this->mapping as $fieldName => $headerIndex) {
                        if ($headerIndex !== '' && isset($row[$headerIndex])) {
                            if ($fieldName === 'name') {
                                $name = $row[$headerIndex];
                            } elseif ($fieldName === 'description') {
                                $description = $row[$headerIndex];
                            } else {
                                $data[$fieldName] = $row[$headerIndex];
                            }
                        }
                    }

                    if ($name || ! empty($data)) {
                        $attributes = [
                            'resource_type_id' => $this->resource_type_id,
                            'user_id' => auth()->id(),
                            'name' => $name ?? ($resourceType->name_translated.' '.($count + 1)),
                            'description' => $description,
                            'data' => $data,
                            'project_id' => $resourceType->project_id,
                        ];

                        $existing = null;
                        if ($this->unique_field) {
                            $matchValue = $this->unique_field === 'name' ? $name : ($data[$this->unique_field] ?? null);

                            if ($matchValue !== null) {
                                $query = Resource::where('resource_type_id', $this->resource_type_id);
                                if ($this->unique_field === 'name') {
                                    $query->where('name', $matchValue);
                                } else {
                                    $query->where("data->{$this->unique_field}", (string) $matchValue);
                                }
                                $existing = $query->first();
                            }
                        }

                        if ($existing) {
                            $existing->update($attributes);
                        } else {
                            Resource::create($attributes);
                        }

                        $count++;

                        if ($count % $batchSize === 0) {
                            DB::commit();
                            DB::beginTransaction();
                        }
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                fclose($handle);

                throw $e;
            }
            fclose($handle);
        }

        return $count;
    }

    protected function importExcel(string $path, ResourceType $resourceType): int
    {
        $count = 0;
        $batchSize = 500;

        DB::connection()->disableQueryLog();

        try {
            $spreadsheet = IOFactory::load($path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray(null, true, true, true);
            array_shift($rows); // Skip header row

            DB::beginTransaction();
            foreach ($rows as $row) {
                // Convert associative array from toArray('A', 'B', ...) to indexed array (0, 1, ...)
                $row = array_values($row);

                $data = [];
                $name = null;
                $description = '';

                foreach ($this->mapping as $fieldName => $headerIndex) {
                    if ($headerIndex !== '' && isset($row[$headerIndex])) {
                        if ($fieldName === 'name') {
                            $name = $row[$headerIndex];
                        } elseif ($fieldName === 'description') {
                            $description = $row[$headerIndex];
                        } else {
                            $data[$fieldName] = $row[$headerIndex];
                        }
                    }
                }

                if ($name || ! empty($data)) {
                    $attributes = [
                        'resource_type_id' => $this->resource_type_id,
                        'user_id' => auth()->id(),
                        'name' => $name ?? ($resourceType->name_translated.' '.($count + 1)),
                        'description' => $description,
                        'data' => $data,
                        'project_id' => $resourceType->project_id,
                    ];

                    $existing = null;
                    if ($this->unique_field) {
                        $matchValue = $this->unique_field === 'name' ? $name : ($data[$this->unique_field] ?? null);

                        if ($matchValue !== null) {
                            $query = Resource::where('resource_type_id', $this->resource_type_id);
                            if ($this->unique_field === 'name') {
                                $query->where('name', $matchValue);
                            } else {
                                $query->where("data->{$this->unique_field}", (string) $matchValue);
                            }
                            $existing = $query->first();
                        }
                    }

                    if ($existing) {
                        $existing->update($attributes);
                    } else {
                        Resource::create($attributes);
                    }

                    $count++;

                    if ($count % $batchSize === 0) {
                        DB::commit();
                        DB::beginTransaction();
                    }
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            throw $e;
        }

        return $count;
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.resource-import', [
            'resourceTypes' => ResourceType::visibleTo(auth()->user())->get()->sortBy(fn ($t) => $t->name_translated),
            'targetFields' => $this->resource_type_id ? ResourceType::find($this->resource_type_id)?->fields : [],
        ]);
    }
}
