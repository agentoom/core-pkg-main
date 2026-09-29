<?php

namespace Agentoom\Core\Livewire\Docs;

use Livewire\Attributes\Layout;
use Livewire\Component;

class Logistics extends Component
{
    #[Layout('agentoom-core::layouts.docs')]
    public function render()
    {
        return view('agentoom-core::livewire.docs.logistics');
    }
}
