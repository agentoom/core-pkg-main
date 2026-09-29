<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Provider;
use Agentoom\Core\Services\ProviderModelSyncService;
use Livewire\Component;
use Livewire\WithPagination;

class ProviderIndex extends Component
{
    use WithPagination;

    public $show_delete_modal = false;

    public $provider_id_to_delete = null;

    public function showDeleteModal($providerId)
    {
        $this->provider_id_to_delete = $providerId;
        $this->show_delete_modal = true;
    }

    public function delete()
    {
        Provider::find($this->provider_id_to_delete)->delete();
        $this->show_delete_modal = false;
        $this->provider_id_to_delete = null;
    }

    public function syncModels($providerId)
    {
        $provider = Provider::find($providerId);

        if (! $provider) {
            session()->flash('error', __('agentoom-core::admin.providers.sync_error', ['error' => 'Provider not found']));

            return;
        }

        $syncService = new ProviderModelSyncService;
        $result = $syncService->syncProvider($provider);

        if ($result['success']) {
            session()->flash('success', __('agentoom-core::admin.providers.models_synced_success'));
        } else {
            session()->flash('error', __('agentoom-core::admin.providers.sync_error', ['error' => $result['error']]));
        }
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.provider-index', [
            'providers' => Provider::withCount('models')->orderBy('name')->paginate(15),
        ])->layout('agentoom-core::layouts.admin');
    }
}
