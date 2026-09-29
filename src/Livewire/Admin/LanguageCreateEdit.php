<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Language;
use Livewire\Component;

class LanguageCreateEdit extends Component
{
    public ?Language $language = null;

    public string $name = '';

    public string $code = '';

    public bool $is_active = true;

    public bool $is_default = false;

    public bool $is_editing = false;

    public function mount(?Language $language = null): void
    {
        $this->language = $language;
        $this->is_editing = $language !== null && $language->exists;

        if ($this->is_editing) {
            $this->name = $language->name;
            $this->code = $language->code;
            $this->is_active = (bool) $language->is_active;
            $this->is_default = (bool) $language->is_default;
        }
    }

    public function save()
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:languages,code,'.($this->language->id ?? 'NULL'),
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        if ($this->is_editing) {
            $this->language->update($validated);
            session()->flash('success', __('agentoom-core::admin.languages.updated_success'));
        } else {
            Language::create($validated);
            session()->flash('success', __('agentoom-core::admin.languages.created_success'));
        }

        return $this->redirect(route('admin.languages.index'), navigate: true);
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.language-create-edit')
            ->layout('agentoom-core::layouts.admin');
    }
}
