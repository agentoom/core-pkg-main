<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentLearnedSkill;
use Livewire\Component;
use Livewire\WithPagination;

class AgentLearnedSkillsManager extends Component
{
    use WithPagination;

    public Agent $agent;

    public $search = '';

    public bool $showDeleteModal = false;

    public ?int $skillIdToDelete = null;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public bool $nested = false;

    public function mount(Agent $agent, bool $nested = false): void
    {
        $this->agent = $agent;
        $this->nested = $nested;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->skillIdToDelete = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if ($this->skillIdToDelete) {
            AgentLearnedSkill::find($this->skillIdToDelete)?->delete();
            session()->flash('success', __('agentoom-core::admin.agents.learned_skill_deleted'));
        }
        $this->showDeleteModal = false;
        $this->skillIdToDelete = null;
    }

    public function render()
    {
        $query = $this->agent->learnedSkills();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('content', 'like', '%'.$this->search.'%');
            });
        }

        return view('agentoom-core::livewire.admin.agent-learned-skills-manager', [
            'skills' => $query->latest()->paginate(10),
        ])->layout('agentoom-core::layouts.admin');
    }
}
