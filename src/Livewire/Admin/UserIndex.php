<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Livewire\Admin\Traits\WithBulkActions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class UserIndex extends Component
{
    use WithBulkActions;
    use WithPagination;

    public $show_delete_modal = false;

    public $user_id_to_delete = null;

    public function showDeleteModal($userId)
    {
        $this->user_id_to_delete = $userId;
        $this->show_delete_modal = true;
    }

    public function delete()
    {
        Gate::authorize('users.delete');

        $userModel = config('auth.providers.users.model');
        $user = $userModel::find($this->user_id_to_delete);

        if ($user && str_contains((string) $user->email, '.system@agentoom.internal')) {
            session()->flash('error', 'System users cannot be deleted.');
            $this->show_delete_modal = false;
            $this->user_id_to_delete = null;

            return;
        }

        $user?->delete();
        $this->show_delete_modal = false;
        $this->user_id_to_delete = null;
        $this->clearSelection();
        $this->resetPage();
    }

    protected function getPageIds(): array
    {
        $userModel = config('auth.providers.users.model');

        return $userModel::query()
            ->pluck('id')
            ->toArray();
    }

    public function bulkDelete(): void
    {
        Gate::authorize('users.delete');

        if (empty($this->selected)) {
            return;
        }

        $userModel = config('auth.providers.users.model');
        $users = $userModel::whereIn('id', $this->selected)->get();

        foreach ($users as $user) {
            if (str_contains((string) $user->email, '.system@agentoom.internal')) {
                continue;
            }
            $user->delete();
        }

        $this->clearSelection();
        session()->flash('success', __('agentoom-core::admin.common.deleted_success'));
    }

    public function bulkClone(): void
    {
        Gate::authorize('users.create');

        if (empty($this->selected)) {
            return;
        }

        $userModel = config('auth.providers.users.model');
        foreach ($this->selected as $id) {
            $user = $userModel::find($id);
            if ($user) {
                $newUser = $user->replicate();
                $newUser->email = 'clone-'.Str::random(5).'-'.$user->email;
                $newUser->save();
            }
        }

        $this->clearSelection();
        session()->flash('success', __('agentoom-core::admin.common.cloned_success'));
    }

    public function render()
    {
        $userModel = config('auth.providers.users.model');

        return view('agentoom-core::livewire.admin.user-index', [
            'users' => $userModel::query()->orderBy('name')->paginate(15),
        ])->layout('agentoom-core::layouts.admin');
    }
}
