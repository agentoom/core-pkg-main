<?php

namespace Agentoom\Core\Livewire\Admin;

use Livewire\Component;

class JsonStructureHelper extends Component
{
    public array $fields = [];

    public ?string $identifier = null;

    public function mount(array $initialFields = [], ?string $identifier = null): void
    {
        $this->identifier = $identifier;
        if (empty($initialFields)) {
            $this->fields = [
                ['name' => '', 'type' => 'string', 'value' => '', 'description' => ''],
            ];
        } else {
            $this->fields = array_map(function ($field) {
                return array_merge(['name' => '', 'type' => 'string', 'value' => '', 'description' => ''], $field);
            }, $initialFields);
        }
    }

    public function addField(): void
    {
        $this->fields[] = ['name' => '', 'type' => 'string', 'value' => '', 'description' => ''];
    }

    public function removeField(int $index): void
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
    }

    public function save(): void
    {
        $this->dispatch('json-structure-updated', fields: $this->fields, identifier: $this->identifier);
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.json-structure-helper');
    }
}
