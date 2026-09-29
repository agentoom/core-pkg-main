<?php

namespace Agentoom\Core\Livewire\Admin\Traits;

use Agentoom\Core\Models\Project;

trait ManageDynamicInstance
{
    public $name = '';

    public string $description = '';

    public array $data = [];

    public ?int $projectId = null;

    public bool $is_editing = false;

    protected function mountDynamicInstance($model, $typeModel, $typeIdField)
    {
        $user = auth()->user();
        $this->is_editing = $model !== null;

        if ($this->is_editing) {
            // Check visibility
            if (! $user->hasAnyRole(['superadmin', 'admin'])) {
                if (! $model::visibleTo($user)->where('id', $model->id)->exists()) {
                    abort(403);
                }
            }

            $this->{$typeIdField} = $model->{$typeIdField};
            $this->name = $model->name;
            $this->description = $model->description ?? '';
            $this->data = $model->data ?? [];
            $this->projectId = $model->project_id;

            $type = $typeModel::find($this->{$typeIdField});
            if ($type) {
                $this->initializeDataFields($type);
            }
        } else {
            if ($this->{$typeIdField}) {
                $type = $typeModel::find($this->{$typeIdField});
                if ($type) {
                    $this->initializeDataFields($type);
                }
            }
        }
    }

    protected function initializeDataFields($type)
    {
        foreach ($type->fields as $field) {
            if (! isset($this->data[$field['name']])) {
                if (($field['type'] ?? '') === 'checkbox' && ! empty($field['options'])) {
                    $this->data[$field['name']] = [];
                } else {
                    $this->data[$field['name']] = '';
                }
            } elseif (($field['type'] ?? '') === 'checkbox' && ! empty($field['options']) && ! is_array($this->data[$field['name']])) {
                $this->data[$field['name']] = array_filter(explode(',', (string) $this->data[$field['name']]));
            }
        }
    }

    protected function updatedDynamicTypeId($value, $typeModel)
    {
        $type = $typeModel::find($value);
        if ($type) {
            $this->data = [];
            $this->initializeDataFields($type);
        } else {
            $this->data = [];
        }
    }

    protected function validateDynamicInstance($typeModel, $typeId)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'projectId' => 'nullable|exists:projects,id',
        ];

        $type = $typeModel::find($typeId);
        if ($type) {
            foreach ($type->fields as $field) {
                if ($field['required'] ?? false) {
                    $rules['data.'.$field['name']] = 'required';
                } else {
                    $rules['data.'.$field['name']] = 'nullable';
                }

                if (($field['type'] ?? '') === 'number') {
                    $rules['data.'.$field['name']] .= '|numeric';
                } elseif (($field['type'] ?? '') === 'date') {
                    $rules['data.'.$field['name']] .= '|date';
                } elseif (($field['type'] ?? '') === 'select' || ($field['type'] ?? '') === 'radio') {
                    if (! empty($field['options'])) {
                        $rules['data.'.$field['name']] .= '|in:'.implode(',', $field['options']);
                    }
                } elseif (($field['type'] ?? '') === 'checkbox') {
                    if (! empty($field['options'])) {
                        $rules['data.'.$field['name']] .= '|array';
                        $rules['data.'.$field['name'].'.*'] = 'in:'.implode(',', $field['options']);
                    } else {
                        $rules['data.'.$field['name']] .= '|boolean';
                    }
                }
            }
        }

        return $this->validate($rules);
    }

    protected function getDynamicInstanceRenderData()
    {
        return [
            'projects' => Project::orderBy('name')->get(),
        ];
    }
}
