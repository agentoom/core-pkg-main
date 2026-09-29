<?php

namespace Agentoom\Core\Livewire\Docs;

use Livewire\Attributes\Layout;
use Livewire\Component;

class B2bSales extends Component
{
    #[Layout('agentoom-core::layouts.docs')]
    public function render()
    {
        return view('agentoom-core::livewire.docs.b2b-sales');
    }
}
