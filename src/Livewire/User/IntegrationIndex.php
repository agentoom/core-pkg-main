<?php

namespace Agentoom\Core\Livewire\User;

use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\UserIntegration;
use Livewire\Component;

class IntegrationIndex extends Component
{
    public array $supported_services = [
        'gmail' => [
            'name' => 'Gmail',
            'icon' => 'envelope',
        ],
    ];

    public array $zapier = [
        'endpoint_base_url' => '',
    ];

    public function mount(): void
    {
        $this->zapier['endpoint_base_url'] = url('/e/your-slug');
    }

    public function isEnabled(string $service): bool
    {
        $config = Configuration::getValue("{$service}_integration", []);

        return ! empty($config['client_id']) && ! empty($config['client_secret']);
    }

    public function getIntegration(string $service): ?UserIntegration
    {
        return UserIntegration::where('user_id', auth()->id())
            ->where('service', $service)
            ->first();
    }

    public function connect(string $service): mixed
    {
        if (! $this->isEnabled($service)) {
            return null;
        }

        return redirect()->route('admin.oauth.redirect', [
            'service' => $service,
            'user_integration' => true,
        ]);
    }

    public function disconnect(string $service): void
    {
        UserIntegration::where('user_id', auth()->id())
            ->where('service', $service)
            ->delete();

        session()->flash('success', __('agentoom-core::admin.integrations.disconnected', ['service' => $this->supported_services[$service]['name']]));
    }

    public function render(): mixed
    {
        return view('agentoom-core::livewire.user.integration-index')
            ->layout('agentoom-core::layouts.admin', [
                'title' => __('agentoom-core::admin.integrations.my_integrations'),
                'headerTitle' => __('agentoom-core::admin.integrations.my_integrations'),
            ]);
    }
}
