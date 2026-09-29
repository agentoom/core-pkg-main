<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Livewire\Admin\Traits\ManageDynamicInstance;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Services\ResourceLimitManager;
use Livewire\Attributes\Url;
use Livewire\Component;

class ResourceCreateEdit extends Component
{
    use ManageDynamicInstance;

    public ?Resource $resource = null;

    #[Url]
    public $resource_type_id = '';

    #[Url]
    public $search = '';

    public function mount(?Resource $resource = null): void
    {
        $this->resource = $resource;
        $this->mountDynamicInstance($resource, ResourceType::class, 'resource_type_id');
    }

    public function updatedResourceTypeId($value): void
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(['superadmin', 'admin']) && ! $this->is_editing) {
            $exists = Resource::where('resource_type_id', $value)
                ->where('user_id', $user->id)
                ->exists();

            if ($exists) {
                $this->addError('resource_type_id', __('agentoom-core::admin.resources.already_have_type'));

                return;
            }
        }

        $this->updatedDynamicTypeId($value, ResourceType::class);
    }

    public function save(bool $stay = false)
    {
        if (! $this->is_editing && app(ResourceLimitManager::class)->isLimitReached('resources_per_type', (int) $this->resource_type_id)) {
            $this->addError('resource_type_id', __('agentoom-core::admin.resource_limits.usage.limit_reached'));

            return;
        }

        $user = auth()->user();
        $isAdmin = $user->hasAnyRole(['superadmin', 'admin']);

        if (! $isAdmin && ! $this->is_editing) {
            $exists = Resource::where('resource_type_id', $this->resource_type_id)
                ->where('user_id', $user->id)
                ->exists();

            if ($exists) {
                $this->addError('resource_type_id', __('agentoom-core::admin.resources.already_have_type'));

                return;
            }
        }

        $this->validateDynamicInstance(ResourceType::class, $this->resource_type_id);

        $data = [
            'resource_type_id' => $this->resource_type_id,
            'name' => $this->name,
            'description' => $this->description,
            'data' => $this->data,
            'project_id' => $this->projectId,
        ];

        if ($this->is_editing) {
            $this->resource->update($data);
            session()->flash('success', __('agentoom-core::admin.resources.updated_success'));
        } else {
            $data['user_id'] = $user->id;
            $this->resource = Resource::create($data);
            session()->flash('success', __('agentoom-core::admin.resources.created_success'));
            $this->is_editing = true;
        }

        if ($stay) {
            return $this->redirect(route('admin.resources.edit', $this->resource), navigate: true);
        }

        return $this->redirect(route('admin.resources.index', [
            'resource_type_id' => $this->resource_type_id,
            'search' => $this->search,
        ]), navigate: true);
    }

    public function clone(): void
    {
        if (! $this->resource || ! $this->resource->exists) {
            return;
        }

        $newResource = $this->resource->replicate();
        $newResource->name = $this->resource->name.' (Clone)';
        $newResource->save();

        session()->flash('success', __('agentoom-core::admin.common.cloned_success'));

        $this->redirect(route('admin.resources.edit', $newResource), navigate: true);
    }

    public function getOAuthService(): ?string
    {
        $resourceType = ResourceType::find($this->resource_type_id);
        if (! $resourceType) {
            return null;
        }

        return match ($resourceType->name['en'] ?? '') {
            'Gmail Connection' => 'gmail',
            default => null,
        };
    }

    public function getRedirectUri(): ?string
    {
        $service = $this->getOAuthService();
        if (! $service) {
            return null;
        }

        $driver = match ($service) {
            'google_seo', 'gmail' => 'google',
            'meta_ads' => 'facebook',
            'tiktok_ads' => 'tiktok',
            default => $service,
        };

        return route('admin.oauth.callback', ['service' => $driver]);
    }

    public function connect(): mixed
    {
        $service = $this->getOAuthService();
        if (! $service || ! $this->is_editing) {
            return null;
        }

        return redirect()->route('admin.oauth.redirect', [
            'service' => $service,
            'resource_id' => $this->resource->id,
        ]);
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.resource-create-edit', array_merge($this->getDynamicInstanceRenderData(), [
            'resourceTypes' => ResourceType::all()->sortBy(fn ($t) => $t->name_translated),
        ]))->layout('agentoom-core::layouts.admin');
    }
}
