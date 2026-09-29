<?php

namespace Agentoom\Core\Livewire\Admin\Config;

use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\SkillPreset;
use Agentoom\Core\Models\Tool;
use Livewire\Component;
use Livewire\WithPagination;

class SkillPresets extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public ?SkillPreset $editingPreset = null;

    public string $name = '';

    public string $description = '';

    public array $selected_skills = [];

    public array $selected_tools = [];

    public bool $is_active = true;

    protected $rules = [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'selected_skills' => 'array',
        'selected_tools' => 'array',
        'is_active' => 'boolean',
    ];

    public function create(): void
    {
        $this->resetErrorBag();
        $this->editingPreset = null;
        $this->name = '';
        $this->description = '';
        $this->selected_skills = [];
        $this->selected_tools = [];
        $this->is_active = true;
        $this->showModal = true;
    }

    public function edit(SkillPreset $preset): void
    {
        $this->resetErrorBag();
        $this->editingPreset = $preset;
        $this->name = $preset->name;
        $this->description = $preset->description ?? '';
        $this->selected_skills = $preset->skills ?? [];
        $this->selected_tools = $preset->tools ?? [];
        $this->is_active = $preset->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'skills' => $this->selected_skills,
            'tools' => $this->selected_tools,
            'is_active' => $this->is_active,
        ];

        if ($this->editingPreset) {
            $this->editingPreset->update($data);
        } else {
            SkillPreset::create($data);
        }

        $this->showModal = false;
        session()->flash('success', __('agentoom-core::admin.common.saved_success'));
    }

    public function delete(SkillPreset $preset): void
    {
        $preset->delete();
        session()->flash('success', __('agentoom-core::admin.common.deleted_success'));
    }

    public function updatedSelectedSkills($value): void
    {
        if (empty($value)) {
            return;
        }

        $skills = AgentSkill::whereIn('id', $value)->get();
        foreach ($skills as $skill) {
            if ($skill->tools) {
                foreach ($skill->tools as $toolId) {
                    if (! in_array($toolId, $this->selected_tools)) {
                        $this->selected_tools[] = (string) $toolId;
                    }
                }
            }
        }
    }

    public function render(): mixed
    {
        return view('agentoom-core::livewire.admin.config.skill-presets', [
            'presets' => SkillPreset::latest()->paginate(10),
            'availableSkills' => AgentSkill::where('is_active', true)->orderBy('name')->get(),
            'availableTools' => Tool::where('is_available', true)->orderBy('name')->get(),
        ])->layout('agentoom-core::layouts.admin', [
            'title' => __('agentoom-core::admin.skill_presets.title'),
            'headerTitle' => __('agentoom-core::admin.skill_presets.title'),
        ]);
    }
}
