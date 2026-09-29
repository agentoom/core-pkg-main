<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class UserCreateEdit extends Component
{
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $is_editing = false;

    public string $role = '';

    public ?string $ai_limit_type = null;

    public ?int $ai_limit_amount = null;

    public ?string $ai_limit_start_date = null;

    public bool $is_superadmin = false;

    public function mount(?User $user = null): void
    {
        $this->user = $user;
        $this->is_editing = $user !== null;
        $this->is_superadmin = auth()->user()?->hasRole('superadmin') ?? false;
        if ($user) {
            $this->name = $user->name;
            $this->email = $user->email;
            $this->ai_limit_type = $user->ai_limit_type;
            $this->ai_limit_amount = $user->ai_limit_amount;
            $this->ai_limit_start_date = $user->ai_limit_start_date?->format('Y-m-d');
            $this->role = $user->roles()->pluck('name')->first() ?? '';
        }
    }

    public function save()
    {
        Gate::authorize($this->is_editing ? 'users.edit' : 'users.create');

        if ($this->user) {
            $this->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email,'.$this->user->id,
                'role' => 'required|string|exists:roles,name',
                'ai_limit_type' => 'nullable|in:daily,weekly,monthly',
                'ai_limit_amount' => 'nullable|integer|min:0',
                'ai_limit_start_date' => 'nullable|date',
            ]);
            if ($this->password) {
                $this->validate([
                    'password' => 'required|string|min:8|confirmed',
                ]);
            }
            $updateData = [
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password ? Hash::make($this->password) : $this->user->password,
            ];
            if ($this->is_superadmin) {
                $updateData['ai_limit_type'] = $this->ai_limit_type ?: null;
                $updateData['ai_limit_amount'] = $this->ai_limit_amount;
                $updateData['ai_limit_start_date'] = $this->ai_limit_start_date ?: null;
            }
            $this->user->update($updateData);
            $this->user->syncRoles([$this->role]);
            session()->flash('success', __('agentoom-core::admin.users.updated_success'));
        } else {
            $this->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|string|exists:roles,name',
                'ai_limit_type' => 'nullable|in:daily,weekly,monthly',
                'ai_limit_amount' => 'nullable|integer|min:0',
                'ai_limit_start_date' => 'nullable|date',
            ]);
            $createData = [
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
            ];
            if ($this->is_superadmin) {
                $createData['ai_limit_type'] = $this->ai_limit_type ?: null;
                $createData['ai_limit_amount'] = $this->ai_limit_amount;
                $createData['ai_limit_start_date'] = $this->ai_limit_start_date ?: null;
            }
            $userModel = config('auth.providers.users.model');
            $user = $userModel::create($createData);
            $user->syncRoles([$this->role]);
            session()->flash('success', __('agentoom-core::admin.users.created_success'));
        }

        return $this->redirect(route('admin.users.index'), navigate: true);
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.user-create-edit', [
            'roles' => Role::orderBy('name')->get(),
        ])->layout('agentoom-core::layouts.admin');
    }
}
