<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Services\AiCreditService;
use Livewire\Component;

class AiCreditWarningBanner extends Component
{
    public bool $isVisible = false;

    public function mount(): void
    {
        $this->isVisible = app(AiCreditService::class)->isLowCreditThresholdReached();
    }

    public function render()
    {
        return <<<'BLADE'
            <div>
                @if ($isVisible)
                    <div class="bg-yellow-100 border-b border-yellow-200 px-4 py-2 mb-4 text-yellow-800 text-sm flex items-center justify-center gap-2 font-medium">
                        <flux:icon name="exclamation-triangle" class="size-4" />
                        {!! __('agentoom-core::admin.ai_credits.low_credits_warning_html', [
                            'url' => route('admin.ai-credits.index', ['tab' => 'buy']),
                        ]) !!}
                    </div>
                @endif
            </div>
        BLADE;
    }
}
