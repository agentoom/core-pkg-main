<?php

namespace Agentoom\Core\Policies;

use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Models\User;

class SchedulePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Schedule $schedule): bool
    {
        if ($user->hasAnyRole(['superadmin', 'admin'])) {
            return true;
        }

        if ($schedule->user_id === $user->id) {
            return true;
        }

        if ($schedule->project_id) {
            $project = $schedule->project;
            if ($project->user_id === $user->id || $project->system_user_id === $user->id) {
                return true;
            }
            if ($project->assignedUsers()->where('users.id', $user->id)->exists()) {
                return true;
            }
        }

        if ($schedule->role_id && $user->hasRole($schedule->role->name)) {
            return true;
        }

        if ($schedule->user_id === null && $schedule->role_id === null && $schedule->project_id === null) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Schedule $schedule): bool
    {
        return $this->view($user, $schedule);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Schedule $schedule): bool
    {
        return $this->view($user, $schedule);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Schedule $schedule): bool
    {
        return $user->hasRole('superadmin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Schedule $schedule): bool
    {
        return $user->hasRole('superadmin');
    }

    /**
     * Determine whether the user can clear the logs of the schedule.
     */
    public function clearLogs(User $user, Schedule $schedule): bool
    {
        return $this->view($user, $schedule);
    }

    /**
     * Determine whether the user can truncate the schedule logs table.
     */
    public function truncateLogs(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->hasRole('admin');
    }
}
