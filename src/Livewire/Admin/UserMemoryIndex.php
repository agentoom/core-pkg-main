<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\User;
use Agentoom\Core\Models\UserInformation;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class UserMemoryIndex extends Component
{
    use WithPagination;

    public User $user;

    public string $search = '';

    public bool $show_memory_modal = false;

    public bool $show_delete_modal = false;

    public ?int $editing_memory_id = null;

    public ?int $memory_id_to_delete = null;

    public string $title = '';

    public int $importance = 3;

    public ?string $expires_at = null;

    public string $content = '';

    public function mount(User $user): void
    {
        Gate::authorize('users.view');

        $this->user = $user;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function createMemory(): void
    {
        Gate::authorize('users.edit');

        $this->resetMemoryForm();
        $this->show_memory_modal = true;
    }

    public function editMemory(UserInformation $memory): void
    {
        Gate::authorize('users.edit');
        abort_unless($memory->user_id === $this->user->id, 404);

        $this->editing_memory_id = $memory->id;
        $this->title = $memory->title;
        $this->content = $memory->content;
        $this->importance = $memory->importance ?? 3;
        $this->expires_at = $memory->expires_at?->format('Y-m-d');
        $this->resetValidation();
        $this->show_memory_modal = true;
    }

    public function saveMemory(): void
    {
        Gate::authorize('users.edit');

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'importance' => ['required', 'integer', 'min:1', 'max:5'],
            'expires_at' => ['nullable', 'date'],
        ]);

        if ($this->editing_memory_id) {
            $memory = UserInformation::query()
                ->where('user_id', $this->user->id)
                ->findOrFail($this->editing_memory_id);

            $memory->update([
                'title' => $this->title,
                'content' => $this->content,
                'importance' => $this->importance,
                'expires_at' => $this->expires_at ?: null,
            ]);

            session()->flash('success', __('agentoom-core::admin.users.memory_updated_success'));
        } else {
            UserInformation::create([
                'user_id' => $this->user->id,
                'title' => $this->title,
                'content' => $this->content,
                'importance' => $this->importance,
                'expires_at' => $this->expires_at ?: null,
            ]);

            session()->flash('success', __('agentoom-core::admin.users.memory_created_success'));
        }

        $this->show_memory_modal = false;
        $this->resetMemoryForm();
        $this->resetPage();
    }

    public function confirmDeleteMemory(UserInformation $memory): void
    {
        Gate::authorize('users.edit');
        abort_unless($memory->user_id === $this->user->id, 404);

        $this->memory_id_to_delete = $memory->id;
        $this->show_delete_modal = true;
    }

    public function deleteMemory(): void
    {
        Gate::authorize('users.edit');

        UserInformation::query()
            ->where('user_id', $this->user->id)
            ->findOrFail($this->memory_id_to_delete)
            ->delete();

        $this->show_delete_modal = false;
        $this->memory_id_to_delete = null;
        session()->flash('success', __('agentoom-core::admin.users.memory_deleted_success'));
        $this->resetPage();
    }

    protected function resetMemoryForm(): void
    {
        $this->editing_memory_id = null;
        $this->title = '';
        $this->content = '';
        $this->importance = 3;
        $this->expires_at = null;
        $this->resetValidation();
    }

    public function render()
    {
        $memories = UserInformation::query()
            ->where('user_id', $this->user->id)
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('content', 'like', '%'.$this->search.'%');
                });
            })
            ->latest()
            ->paginate(12);

        return view('agentoom-core::livewire.admin.user-memory-index', [
            'memories' => $memories,
        ])->layout('agentoom-core::layouts.admin');
    }
}
