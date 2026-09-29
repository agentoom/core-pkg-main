<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Tool;
use Agentoom\Core\Services\ActionDiscoveryService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ActionMethodSelector extends Component
{
    public string $search = '';

    public string $sourceFilter = 'all';

    public bool $show = false;

    public ?string $identifier = null;

    public string $mode = 'all';

    #[On('open-method-selector')]
    public function open(?string $identifier = null, ?string $mode = null)
    {
        $this->identifier = $identifier;
        $this->mode = $mode ?? 'all';
        $this->search = '';
        $this->sourceFilter = 'all';
        $this->show = true;
    }

    public function selectMethod(string $key)
    {
        $this->dispatch('method-selected', key: $key, identifier: $this->identifier);
        $this->show = false;
    }

    #[Computed]
    public function methods()
    {
        $methods = collect(app(ActionDiscoveryService::class)->discover());

        if ($this->mode === 'tools-only') {
            $toolClasses = Tool::where('is_available', true)->pluck('class_name')->flip();

            $methods = $methods->filter(function ($m, $key) use ($toolClasses) {
                // Always include registered Tools
                if (str_starts_with($key, 'tool:')) {
                    return true;
                }

                // Include MCP actionables marked as ai_tool
                if (str_starts_with($key, 'mcp:')) {
                    return $m['ai_tool'] ?? false;
                }

                // Include local #[Actionable] methods whose class is a registered Tool
                return isset($toolClasses[$m['class']]);
            });
        }

        if ($this->search) {
            $methods = $methods->filter(fn ($m) => str_contains(strtolower($m['name']), strtolower($this->search)) ||
                str_contains(strtolower($m['category'] ?? ''), strtolower($this->search)) ||
                str_contains(strtolower($m['display_class'] ?? $m['class']), strtolower($this->search))
            );
        }

        if ($this->sourceFilter !== 'all') {
            $methods = $methods->filter(fn ($m) => ($m['source'] ?? 'local') === $this->sourceFilter);
        }

        return $methods->sortBy('category')->toArray();
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.action-method-selector');
    }
}
