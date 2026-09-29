<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Endpoint;
use Livewire\Component;
use Livewire\WithPagination;

class EndpointLogIndex extends Component
{
    use WithPagination;

    public Endpoint $endpoint;

    public function mount(Endpoint $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.endpoint-log-index', [
            'logs' => $this->endpoint->logs()->latest()->paginate(20),
        ])->layout('agentoom-core::layouts.admin');
    }
}
