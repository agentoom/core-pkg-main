<?php

namespace Agentoom\Core\Livewire\Admin\Config;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\ProviderModel;
use Livewire\Component;

class SystemWorkers extends Component
{
    public string $worker_agent_id = '';

    public array $ai_specialized_agents = [];

    public array $specialized_agents_available = [];

    public string $ai_provider = '';

    public function mount(): void
    {
        $this->specialized_agents_available = __('agentoom-core::admin.configs.specialized_agents_list');
        if (! is_array($this->specialized_agents_available)) {
            $this->specialized_agents_available = [];
        }

        $this->worker_agent_id = Configuration::getValue('worker_agent_id', '');
        if (empty($this->worker_agent_id)) {
            $this->worker_agent_id = (string) (Agent::where('slug', 'memory-worker')->first()?->id ?? '');
        }

        $this->ai_specialized_agents = Configuration::getValue('ai_specialized_agents', []);

        if (empty($this->ai_specialized_agents['summarizer']['agent_id'] ?? '')) {
            $this->ai_specialized_agents['summarizer']['agent_id'] = (string) (Agent::where('slug', 'summarizer')->first()?->id ?? '');
        }

        $this->ai_provider = Configuration::getValue('ai_provider', '');
    }

    protected function rules(): array
    {
        return [
            'worker_agent_id' => 'nullable|integer',
            'ai_specialized_agents' => 'nullable|array',
        ];
    }

    public function save(): void
    {
        $this->validate();

        Configuration::setValue('worker_agent_id', $this->worker_agent_id);
        Configuration::setValue('ai_specialized_agents', $this->ai_specialized_agents);

        session()->flash('success', __('agentoom-core::admin.configs.updated_success'));
    }

    public function render(): mixed
    {
        $agents = Agent::where('is_active', true)->pluck('name', 'id');

        $models = collect();
        if ($this->ai_provider) {
            $models = ProviderModel::where(function ($query) {
                if (is_numeric($this->ai_provider)) {
                    $query->where('provider_id', $this->ai_provider);
                } else {
                    $query->whereHas('provider', function ($q) {
                        $q->where('name', $this->ai_provider);
                    });
                }
            })
                ->where('is_active', true)
                ->pluck('model_name', 'id');
        }

        return view('agentoom-core::livewire.admin.config.system-workers', [
            'agents' => $agents,
            'models' => $models,
        ])->layout('agentoom-core::layouts.admin', [
            'title' => __('agentoom-core::admin.system_workers.title'),
            'headerTitle' => __('agentoom-core::admin.system_workers.title'),
        ]);
    }
}
