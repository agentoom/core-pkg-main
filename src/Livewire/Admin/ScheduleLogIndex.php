<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Schedule;
use Livewire\Component;
use Livewire\WithPagination;

class ScheduleLogIndex extends Component
{
    use WithPagination;

    public Schedule $schedule;

    public $status = '';

    public function mount(Schedule $schedule): void
    {
        $this->authorize('view', $schedule);
        $this->schedule = $schedule;
    }

    public function clearLogs(): void
    {
        $this->authorize('clearLogs', $this->schedule);

        $this->schedule->logs()->delete();

        $this->resetPage();

        session()->flash('success', __('agentoom-core::admin.schedules.logs_cleared_success'));
    }

    public function render()
    {
        $query = $this->schedule->logs()->latest();

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return view('agentoom-core::livewire.admin.schedule-log-index', [
            'logs' => $query->paginate(15),
        ])->layout('agentoom-core::layouts.admin');
    }
}
