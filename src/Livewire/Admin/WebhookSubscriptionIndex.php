<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\WebhookSubscription;
use Flux;
use Livewire\Component;
use Livewire\WithPagination;

class WebhookSubscriptionIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 15;

    public $show_delete_modal = false;

    public ?int $delete_id = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function showDeleteModal(int $id): void
    {
        $this->delete_id = $id;
        $this->show_delete_modal = true;
    }

    public function delete(): void
    {
        WebhookSubscription::find($this->delete_id)?->delete();
        $this->show_delete_modal = false;
        $this->delete_id = null;
        $this->resetPage();
        Flux::toast('Webhook subscription deleted.');
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.webhook-subscription-index', [
            'subscriptions' => WebhookSubscription::query()
                ->with(['agent', 'pipeline'])
                ->when($this->search, function ($q) {
                    $q->where('url', 'like', '%'.$this->search.'%');
                })
                ->orderBy('created_at', 'desc')
                ->paginate($this->perPage),
        ])->layout('agentoom-core::layouts.admin');
    }
}
