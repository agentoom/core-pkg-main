<?php

namespace Agentoom\Core\Livewire\Docs\Features;

use Livewire\Attributes\Layout;
use Livewire\Component;

class InstantMessaging extends Component
{
    #[Layout('agentoom-core::layouts.docs')]
    public function render()
    {
        return view('agentoom-core::livewire.docs.features.instant_messaging');
    }
}
