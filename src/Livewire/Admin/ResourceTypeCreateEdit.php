<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Livewire\Admin\Traits\ManageDynamicType;
use Agentoom\Core\Models\Language;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Services\ResourceLimitManager;
use Livewire\Component;

class ResourceTypeCreateEdit extends Component
{
    use ManageDynamicType;

    public ?ResourceType $resourceType = null;

    public function mount(?ResourceType $resourceType = null): void
    {
        $this->resourceType = $resourceType;
        $this->is_editing = $resourceType !== null && $resourceType->exists;

        if ($this->is_editing) {
            $this->names = $resourceType->name ?? [];
            $this->description = $resourceType->description ?? '';
            $this->resourceGroupId = $resourceType->resource_group_id;
            $this->projectId = $resourceType->project_id;
            $this->user_id = $resourceType->user_id;
            $this->config = array_map(function ($field) {
                return array_merge([
                    'name' => '',
                    'type' => 'text',
                    'required' => false,
                    'encrypted' => false,
                    'options' => [],
                ], $field);
            }, $resourceType->fields);
        } else {
            $this->config = [];
            foreach (Language::where('is_active', true)->get() as $lang) {
                $this->names[$lang->code] = '';
            }
        }
    }

    public function save()
    {
        if (! $this->is_editing && app(ResourceLimitManager::class)->isLimitReached('resource_types')) {
            $this->addError('name', __('agentoom-core::admin.resource_limits.usage.limit_reached'));

            return;
        }

        $this->validateDynamicType();
        $data = $this->getDynamicTypeData();

        if ($this->is_editing) {
            $this->resourceType->update($data);
            session()->flash('success', __('agentoom-core::admin.resource_types.updated_success'));
        } else {
            $this->resourceType = ResourceType::create($data);
            session()->flash('success', __('agentoom-core::admin.resource_types.created_success'));
        }

        return $this->redirect(route('admin.resource-types.index'), navigate: true);
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.resource-type-create-edit', $this->getDynamicTypeRenderData())
            ->layout('agentoom-core::layouts.admin');
    }
}
