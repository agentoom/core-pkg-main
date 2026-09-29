<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Project;
use Flux;
use Livewire\Component;

class ProjectCreateEdit extends Component
{
    public ?Project $project = null;

    public $name = '';

    public $description = '';

    public $budget_limit = 0;

    public $budget_limit_starts_at = null;

    public $is_active = true;

    public $userId = null;

    public $assignedUserIds = [];

    public function mount(?Project $project = null): void
    {
        if ($project && $project->exists) {
            $this->project = $project;
            $this->name = $project->name;
            $this->description = $project->description;
            $this->budget_limit = $project->budget_limit ?? 0;
            $this->budget_limit_starts_at = $project->budget_limit_starts_at?->format('Y-m-d');
            $this->is_active = $project->is_active;
            $this->userId = $project->user_id;
            $this->assignedUserIds = $project->assignedUsers()->pluck('users.id')->toArray();
        } else {
            $this->userId = auth()->id();
        }
    }

    public function save(bool $stay = false): void
    {
        if (! is_numeric($this->budget_limit)) {
            $this->budget_limit = 0;
        }

        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'budget_limit' => 'nullable|numeric|min:0',
            'budget_limit_starts_at' => 'nullable|date',
            'is_active' => 'boolean',
            'userId' => 'required|exists:users,id',
            'assignedUserIds' => 'array',
            'assignedUserIds.*' => 'exists:users,id',
        ]);

        $data = [
            'user_id' => $this->userId,
            'name' => $this->name,
            'description' => $this->description,
            'budget_limit' => $this->budget_limit,
            'budget_limit_starts_at' => $this->budget_limit_starts_at,
            'is_active' => $this->is_active,
        ];

        if ($this->project && $this->project->exists) {
            $this->project->update($data);
            $this->project->assignedUsers()->sync($this->assignedUserIds);
            Flux::toast(__('agentoom-core::admin.projects.updated_success'));
        } else {
            $this->project = Project::create($data);
            $this->project->assignedUsers()->sync($this->assignedUserIds);
            Flux::toast(__('agentoom-core::admin.projects.created_success'));
        }

        if ($stay) {
            $this->redirect(route('admin.projects.edit', $this->project), navigate: true);

            return;
        }

        $this->redirect(route('admin.projects.index'), navigate: true);
    }

    public function clone(): void
    {
        if (! $this->project || ! $this->project->exists) {
            return;
        }

        $newProject = $this->project->replicate();
        $newProject->name = $this->project->name.' (Clone)';
        $newProject->save();

        Flux::toast(__('agentoom-core::admin.common.cloned_success'));

        $this->redirect(route('admin.projects.edit', $newProject), navigate: true);
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.project-create-edit', [
            'users' => auth()->user()->getAssignableUsers(),
            'agents' => $this->project?->agents,
            'endpoints' => $this->project?->endpoints,
            'pipelines' => $this->project?->pipelines,
            'schedules' => $this->project?->schedules,
            'aiTasks' => $this->project?->aiTasks,
        ])->layout('agentoom-core::layouts.admin');
    }
}
