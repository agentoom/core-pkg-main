<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Tool;
use Agentoom\Core\Services\VisibilityService;
use Livewire\Component;
use Livewire\WithPagination;

class AgentToolIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $toolToDeactivateId = null;

    public array $agentsUsingTool = [];

    public bool $showConfirmationModal = false;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function toggleStatus(int $toolId): void
    {
        $tool = Tool::findOrFail($toolId);

        if (! auth()->user()->hasRole('superadmin')) {
            session()->flash('error', __('agentoom-core::admin.common.unauthorized'));

            return;
        }

        if ($tool->is_active) {
            // Check if tool is assigned to any agents
            $agents = $tool->agents()->get();

            if ($agents->isNotEmpty()) {
                $this->toolToDeactivateId = $toolId;
                $this->agentsUsingTool = $agents->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->toArray();
                $this->showConfirmationModal = true;

                return;
            }
        }

        $tool->is_active = ! $tool->is_active;
        $tool->save();

        session()->flash('success', __('agentoom-core::admin.common.updated_success'));
    }

    public function confirmDeactivate(): void
    {
        if (! $this->toolToDeactivateId) {
            return;
        }

        $tool = Tool::findOrFail($this->toolToDeactivateId);

        if (! auth()->user()->hasRole('superadmin')) {
            return;
        }

        // Disconnect agents
        $tool->agents()->detach();

        // Deactivate tool
        $tool->is_active = false;
        $tool->save();

        $this->resetConfirmation();
        session()->flash('success', __('agentoom-core::admin.common.updated_success'));
    }

    public function resetConfirmation(): void
    {
        $this->toolToDeactivateId = null;
        $this->agentsUsingTool = [];
        $this->showConfirmationModal = false;
    }

    public function render()
    {
        $visibilityService = app(VisibilityService::class);

        $query = Tool::query()->search($this->search);

        if (! $visibilityService->isAllGroupsActive()) {
            $activeGroups = $visibilityService->getActiveGroups();
            $query->where(function ($q) use ($activeGroups) {
                $q->whereNull('visibility_group')
                    ->orWhereIn('visibility_group', $activeGroups->toArray());
            });
        }

        $tools = $query->orderBy('name')->paginate(20);

        return view('agentoom-core::livewire.admin.agent-tool-index', [
            'tools' => $tools,
        ])->layout('agentoom-core::layouts.admin');
    }
}
