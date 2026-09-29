<?php

namespace Agentoom\Core\Livewire\Admin;

use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\WithPagination;

class IconPicker extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function selectIcon(string $icon): void
    {
        $this->dispatch('icon-selected', icon: $icon);
    }

    public function render()
    {
        $allIcons = $this->getIcons();

        if ($this->search) {
            $allIcons = array_filter($allIcons, function ($icon) {
                return str_contains($icon, strtolower($this->search));
            });
        }

        $total = count($allIcons);
        $perPage = 60;
        $currentPage = $this->getPage();
        $icons = array_slice($allIcons, ($currentPage - 1) * $perPage, $perPage);

        return view('agentoom-core::livewire.admin.icon-picker', [
            'icons' => $icons,
            'total' => $total,
            'perPage' => $perPage,
            'totalPages' => (int) ceil($total / $perPage),
        ]);
    }

    protected function getIcons(): array
    {
        $icons = [];

        // Built-in Flux icons (mostly Heroicons)
        $fluxIconPath = base_path('vendor/livewire/flux/stubs/resources/views/flux/icon');
        if (File::isDirectory($fluxIconPath)) {
            $files = File::files($fluxIconPath);
            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $icons[] = $file->getBasename('.blade.php');
                }
            }
        }

        // App custom/imported icons
        $appIconPath = resource_path('views/flux/icon');
        if (File::isDirectory($appIconPath)) {
            $files = File::files($appIconPath);
            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $icons[] = $file->getBasename('.blade.php');
                }
            }
        }

        $icons = array_unique($icons);
        sort($icons);

        return $icons;
    }
}
