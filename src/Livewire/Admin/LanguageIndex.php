<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Language;
use Livewire\Component;
use Livewire\WithPagination;

class LanguageIndex extends Component
{
    use WithPagination;

    public string $search = '';

    protected $languages;

    public function delete(int $id): void
    {
        $language = Language::findOrFail($id);
        $language->delete();

        session()->flash('success', __('agentoom-core::admin.languages.deleted_success'));
    }

    public function toggleActive(int $id): void
    {
        $language = Language::findOrFail($id);
        $language->is_active = ! $language->is_active;
        $language->save();

        session()->flash('success', __('agentoom-core::admin.languages.updated_success'));
    }

    public function toggleDefault(int $id): void
    {
        $language = Language::findOrFail($id);
        $language->is_default = ! $language->is_default;
        $language->save();

        session()->flash('success', __('agentoom-core::admin.languages.updated_success'));

        $this->js('window.location.reload();');
    }

    public function getLanguages()
    {
        $this->languages = Language::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('code', 'like', '%'.$this->search.'%');
            })
            ->latest()
            ->paginate(10);
    }

    public function render()
    {
        $this->getLanguages();

        return view('agentoom-core::livewire.admin.language-index', [
            'languages' => $this->languages,
        ])->layout('agentoom-core::layouts.admin');
    }
}
