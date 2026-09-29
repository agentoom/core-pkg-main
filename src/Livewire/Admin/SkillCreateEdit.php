<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\ChatWidget\Models\Form;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Models\Tool;
use Illuminate\Support\Str;
use Livewire\Component;

class SkillCreateEdit extends Component
{
    public ?AgentSkill $skill = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $instructions = '';

    public array $musts = [];

    public array $donts = [];

    public array $if_this_then = [];

    public bool $is_active = true;

    public array $selected_tools = [];

    public bool $is_editing = false;

    public array $resource_type_ids = [];

    public function mount(?AgentSkill $skill = null): void
    {
        $this->skill = $skill;
        $this->is_editing = $skill !== null;

        if ($this->is_editing) {
            $this->name = (string) $skill->name;
            $this->slug = (string) $skill->slug;
            $this->description = (string) ($skill->description ?? '');
            $this->instructions = (string) ($skill->instructions ?? '');
            $this->musts = (array) ($skill->musts ?? []);
            $this->donts = (array) ($skill->donts ?? []);
            $this->if_this_then = (array) ($skill->if_this_then ?? []);
            $this->is_active = (bool) $skill->is_active;
            $this->selected_tools = (array) ($skill->tools ?? []);
            $this->resource_type_ids = $skill->resourceTypes->pluck('id')->toArray();
        }
    }

    public function updatedName($value): void
    {
        if (! $this->is_editing) {
            $this->slug = Str::slug($value);
        }
    }

    public function addMust(): void
    {
        $this->musts[] = '';
    }

    public function removeMust(int $index): void
    {
        unset($this->musts[$index]);
        $this->musts = array_values($this->musts);
    }

    public function addDont(): void
    {
        $this->donts[] = '';
    }

    public function removeDont(int $index): void
    {
        unset($this->donts[$index]);
        $this->donts = array_values($this->donts);
    }

    public function addIfThisThen(): void
    {
        $this->if_this_then[] = ['event' => '', 'action' => ''];
    }

    public function removeIfThisThen(int $index): void
    {
        unset($this->if_this_then[$index]);
        $this->if_this_then = array_values($this->if_this_then);
    }

    public function save(bool $stay = false)
    {
        $this->musts = array_values(array_filter(array_map('trim', $this->musts)));
        $this->donts = array_values(array_filter(array_map('trim', $this->donts)));
        $this->if_this_then = array_values(array_filter($this->if_this_then, fn ($item) => ! empty(trim($item['event'] ?? '')) || ! empty(trim($item['action'] ?? '')) || ! empty(trim($item['form_slug'] ?? ''))));

        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:agent_skills,slug,'.($this->skill->id ?? 'NULL'),
            'description' => 'required|string',
            'instructions' => 'required|string',
            'musts' => 'nullable|array',
            'donts' => 'nullable|array',
            'if_this_then' => 'nullable|array',
            'is_active' => 'boolean',
            'selected_tools' => 'array',
            'resource_type_ids' => 'nullable|array',
            'resource_type_ids.*' => 'exists:resource_types,id',
        ]);

        $data = [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'musts' => $this->musts,
            'donts' => $this->donts,
            'if_this_then' => $this->if_this_then,
            'is_active' => $this->is_active,
            'tools' => $this->selected_tools,
        ];

        if ($this->is_editing) {
            $this->skill->update($data);
            $this->skill->resourceTypes()->sync($this->resource_type_ids);
            session()->flash('success', __('agentoom-core::admin.skills.updated_success'));
        } else {
            $this->skill = AgentSkill::create($data);
            $this->skill->resourceTypes()->sync($this->resource_type_ids);
            session()->flash('success', __('agentoom-core::admin.skills.created_success'));
            $this->is_editing = true;
        }

        if ($stay) {
            return $this->redirect(route('admin.skills.edit', $this->skill), navigate: true);
        }

        return $this->redirect(route('admin.skills.index'), navigate: true);
    }

    public function clone(): void
    {
        if (! $this->skill || ! $this->skill->exists) {
            return;
        }

        $newSkill = $this->skill->replicate();
        $newSkill->name = $this->skill->name.' (Clone)';
        $newSkill->slug = Str::slug($newSkill->name);
        $newSkill->save();

        // Sync resource types
        $newSkill->resourceTypes()->sync($this->skill->resourceTypes()->pluck('resource_types.id'));

        session()->flash('success', __('agentoom-core::admin.common.cloned_success'));

        $this->redirect(route('admin.skills.edit', $newSkill), navigate: true);
    }

    public function render()
    {
        $tools = Tool::where('is_available', true)->orderBy('name')->get();

        $forms = collect();
        if (class_exists(Form::class)) {
            $forms = Form::query()->orderBy('name->'.app()->getLocale())->get();
        }

        $resourceTypes = ResourceType::orderByTranslatedName()->get();

        return view('agentoom-core::livewire.admin.skill-create-edit', [
            'tools' => $tools,
            'skillForms' => $forms,
            'resourceTypes' => $resourceTypes,
        ])->layout('agentoom-core::layouts.admin');
    }
}
