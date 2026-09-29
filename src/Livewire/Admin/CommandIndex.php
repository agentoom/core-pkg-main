<?php

namespace Agentoom\Core\Livewire\Admin;

use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class CommandIndex extends Component
{
    public function executeCommand(string $command): void
    {
        if (! auth()->user()?->hasRole('superadmin')) {
            abort(403);
        }

        Artisan::call($command.' --no-interaction');

        session()->flash('success', __('agentoom-core::admin.configs.command_executed_success', ['command' => $command]));
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.command-index')
            ->layout('agentoom-core::layouts.admin', [
                'title' => __('agentoom-core::admin.configs.section_console_commands'),
                'headerTitle' => __('agentoom-core::admin.configs.section_console_commands'),
            ]);
    }
}
