<?php

namespace Agentoom\Core\Livewire\Docs;

use Agentoom\Core\Models\Language;
use Livewire\Component;

class Header extends Component
{
    public function render()
    {
        return view('agentoom-core::livewire.docs.header', [
            'languages' => Language::where('is_active', true)->get(),
        ]);
    }
}
