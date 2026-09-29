<?php

namespace Agentoom\Core\Livewire\Docs\Features;

use Livewire\Attributes\Layout;
use Livewire\Component;

class LiveSupport extends Component
{
    #[Layout('agentoom-core::layouts.docs')]
    public function render()
    {
        return view('agentoom-core::livewire.docs.features.live_support');
    }
}
