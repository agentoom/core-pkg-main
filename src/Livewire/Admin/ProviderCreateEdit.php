<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Provider;
use Agentoom\Core\Services\ProviderModelSyncService;
use Laravel\Ai\Enums\Lab;
use Livewire\Component;

class ProviderCreateEdit extends Component
{
    public ?Provider $provider = null;

    public string $name = '';

    public string $driver = '';

    public string $api_key = '';

    public string $url = '';

    public bool $is_editing = false;

    public function mount(?Provider $provider = null): void
    {
        $this->provider = $provider;
        $this->is_editing = $provider !== null;

        if ($provider) {
            $this->name = $provider->name;
            $this->driver = $provider->driver ?: $provider->name;
            $this->api_key = $provider->api_key ?? '';
            $this->url = $provider->url ?? '';
        }
    }

    public function save(): mixed
    {
        $this->validate([
            'name' => $this->provider ? 'required|string|max:255' : 'required|string|max:255|unique:providers,name',
            'api_key' => 'nullable|string|max:500',
            'url' => $this->name === 'custom' ? 'required|url|max:2048' : 'nullable|url|max:2048',
        ]);

        $data = [
            'name' => $this->name,
            'driver' => $this->name === 'custom' ? 'openai' : $this->name,
            'api_key' => $this->api_key ?: null,
            'url' => $this->url ?: null,
        ];

        if ($this->provider) {
            $this->provider->update($data);
            session()->flash('success', __('agentoom-core::admin.providers.updated_success'));
        } else {
            Provider::create($data);
            session()->flash('success', __('agentoom-core::admin.providers.created_success'));
        }

        return $this->redirect(route('admin.providers.index'), navigate: true);

    }

    public function syncModels()
    {
        if (! $this->provider) {
            return;
        }

        $syncService = new ProviderModelSyncService;
        $result = $syncService->syncProvider($this->provider);

        if ($result['success']) {
            session()->flash('success', __('agentoom-core::admin.providers.models_synced_success'));
        } else {
            session()->flash('error', __('agentoom-core::admin.providers.sync_error', ['error' => $result['error']]));
        }
    }

    public function getAvailableProvidersProperty(): array
    {
        return collect(Lab::cases())
            ->mapWithKeys(fn (Lab $case): array => [$case->value => ucfirst($case->name)])
            ->put('custom', 'Custom (OpenAI-compatible)')
            ->toArray();
    }

    public function render()
    {
        $models = [];
        if ($this->provider) {
            $models = $this->provider->models()->orderBy('model_name')->get();
        }

        return view('agentoom-core::livewire.admin.provider-create-edit', [
            'models' => $models,
            'availableProviders' => $this->availableProviders,
        ])->layout('agentoom-core::layouts.admin');
    }
}
