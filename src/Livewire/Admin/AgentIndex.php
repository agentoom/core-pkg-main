<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Livewire\Admin\Traits\WithBulkActions;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Services\ExportService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class AgentIndex extends Component
{
    use WithBulkActions;
    use WithPagination;

    public $search = '';

    public $project_id = '';

    public $show_system = false;

    public $show_delete_modal = false;

    public $show_bulk_delete_modal = false;

    public $agent_id_to_delete = null;

    /**
     * Blockers that prevent deletion of the selected agent.
     *
     * @var array<int, array{type: string, name: string, url: string}>
     */
    public array $deletion_blockers = [];

    public int $perPage = 15;

    protected $queryString = [
        'search' => ['except' => ''],
        'project_id' => ['except' => ''],
        'show_system' => ['except' => false],
    ];

    public bool $is_embedded = false;

    public function mount(?int $project_id = null, bool $is_embedded = false): void
    {
        if ($project_id) {
            $this->project_id = (string) $project_id;
        }
        $this->is_embedded = $is_embedded;
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedProjectId()
    {
        $this->resetPage();
    }

    public function updatedShowSystem()
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function showDeleteModal($agentId): void
    {
        $this->agent_id_to_delete = $agentId;

        $agent = Agent::find($agentId);
        $this->deletion_blockers = $agent?->getDeletionBlockers()->toArray() ?? [];

        $this->show_delete_modal = true;
    }

    public function delete(): void
    {
        Gate::authorize('agents.delete');

        $agent = Agent::find($this->agent_id_to_delete);

        if ($agent) {
            $blockers = $agent->getDeletionBlockers();
            if ($blockers->isNotEmpty()) {
                $this->deletion_blockers = $blockers->toArray();

                return;
            }

            $agent->delete();
        }

        $this->show_delete_modal = false;
        $this->agent_id_to_delete = null;
        $this->deletion_blockers = [];
        $this->clearSelection();
        $this->resetPage();
    }

    public function clone($id): void
    {
        Gate::authorize('agents.create');

        $agent = Agent::findOrFail($id);
        $newAgent = $agent->replicate();
        $newAgent->name = $agent->name.' (Clone)';
        $newAgent->slug = Str::slug($newAgent->name);
        $newAgent->system_agent = false;
        $newAgent->default_agent = false;
        $newAgent->save();

        // Sync relationships
        $newAgent->tools()->sync($agent->tools()->pluck('tools.id'));
        $newAgent->skills()->sync($agent->skills()->pluck('agent_skills.id'));

        // Task permissions
        foreach ($agent->taskPermissions as $permission) {
            $newAgent->taskPermissions()->create($permission->toArray());
        }

        session()->flash('success', __('agentoom-core::admin.common.cloned_success'));

        $this->redirect(route('admin.agents.edit', $newAgent), navigate: true);
    }

    protected function getPageIds(): array
    {
        return Agent::visibleTo(auth()->user())
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->project_id, function ($q) {
                $q->where('project_id', $this->project_id);
            })
            ->when(! $this->show_system, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('system_agent', false)->orWhere('default_agent', true);
                });
            })
            ->orderBy('name')
            ->paginate($this->perPage)
            ->pluck('id')
            ->toArray();
    }

    public function bulkDelete(): void
    {
        Gate::authorize('agents.delete');

        if (empty($this->selected)) {
            return;
        }

        $this->show_bulk_delete_modal = true;
    }

    public function confirmBulkDelete(): void
    {
        if (empty($this->selected)) {
            return;
        }

        Agent::visibleTo(auth()->user())->whereIn('id', $this->selected)->delete();
        $this->clearSelection();
        $this->resetPage();
        $this->show_bulk_delete_modal = false;
        session()->flash('success', __('agentoom-core::admin.common.deleted_success'));
    }

    public function bulkClone(): void
    {
        Gate::authorize('agents.create');

        if (empty($this->selected)) {
            return;
        }

        foreach ($this->selected as $id) {
            $agent = Agent::find($id);
            if ($agent) {
                $newAgent = $agent->replicate();
                $newAgent->name = $agent->name.' (Clone)';
                $newAgent->slug = Str::slug($newAgent->name).'-'.Str::random(5);
                $newAgent->system_agent = false;
                $newAgent->default_agent = false;
                $newAgent->save();

                $newAgent->tools()->sync($agent->tools()->pluck('tools.id'));
                $newAgent->skills()->sync($agent->skills()->pluck('agent_skills.id'));

                foreach ($agent->taskPermissions as $permission) {
                    $newAgent->taskPermissions()->create($permission->toArray());
                }
            }
        }

        $this->clearSelection();
        $this->resetPage();
        session()->flash('success', __('agentoom-core::admin.common.cloned_success'));
    }

    public function bulkExport()
    {
        if (empty($this->selected)) {
            return null;
        }

        $exportService = app(ExportService::class);
        $data = $exportService->export(['agents' => $this->selected]);

        $json = json_encode($data, JSON_PRETTY_PRINT);
        $filename = 'agents_export_'.now()->format('Y-m-d_H-i-s').'.json';

        $this->clearSelection();

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename);
    }

    public function render()
    {
        $user = auth()->user();

        $query = Agent::visibleTo($user)
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->project_id, function ($q) {
                $q->where('project_id', $this->project_id);
            })
            ->when(! $this->show_system, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('system_agent', false)->orWhere('default_agent', true);
                });
            })
            ->orderBy('name');

        $projectsQuery = Project::query();

        if (! $user->hasAnyRole(['superadmin', 'admin'])) {
            $projectsQuery->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('assignedUsers', fn ($aq) => $aq->where('users.id', $user->id));
            });
        }

        return view('agentoom-core::livewire.admin.agent-index', [
            'agents' => $query->paginate($this->perPage),
            'projects' => $projectsQuery->orderBy('name')->get(),
        ])->layout('agentoom-core::layouts.admin');
    }
}
