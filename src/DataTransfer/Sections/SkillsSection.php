<?php

namespace Agentoom\Core\DataTransfer\Sections;

use Agentoom\Core\DataTransfer\AbstractImportExportSection;
use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\ResourceType;
use Illuminate\Support\Collection;

/**
 * Skills export/import section.
 */
class SkillsSection extends AbstractImportExportSection
{
    public function key(): string
    {
        return 'skills';
    }

    public function group(): string
    {
        return ImportExportRegistry::GROUP_SKILLS;
    }

    public function permission(): ?string
    {
        return 'skills.view';
    }

    public function options(): Collection
    {
        return AgentSkill::all();
    }

    public function importLevel(): int
    {
        return 3;
    }

    public function export(array $ids): array
    {
        $query = AgentSkill::query();
        if (($ids = $this->selectedIds($ids)) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function ($skill) {
            return [
                'name' => $skill->name,
                'slug' => $skill->slug,
                'description' => $skill->description,
                'instructions' => $skill->instructions,
                'musts' => $skill->musts,
                'donts' => $skill->donts,
                'if_this_then' => $skill->if_this_then,
                'tools' => $skill->tools,
                'is_active' => $skill->is_active,
                'agents' => $skill->agents->pluck('slug')->toArray(),
                'resource_types' => $skill->resourceTypes->pluck('name')->toArray(),
            ];
        })->toArray();
    }

    /**
     * @param  array<int, array<string, mixed>>  $payload
     * @param  array<string, mixed>  $options
     */
    public function import(array $payload, array $options = []): void
    {
        foreach ($payload as $skillData) {
            $skill = null;
            if (isset($skillData['slug'])) {
                $skill = AgentSkill::where('slug', $skillData['slug'])->first();
            }

            if (! $skill && isset($skillData['name'])) {
                $skill = AgentSkill::where('name', $skillData['name'])->first();
            }

            $values = [
                'name' => $skillData['name'] ?? ($skill ? $skill->name : ''),
                'slug' => $skillData['slug'] ?? ($skill ? $skill->slug : null),
                'description' => $skillData['description'] ?? ($skill ? $skill->description : null),
                'instructions' => $skillData['instructions'] ?? ($skill ? $skill->instructions : null),
                'musts' => $skillData['musts'] ?? ($skill ? $skill->musts : []),
                'donts' => $skillData['donts'] ?? ($skill ? $skill->donts : []),
                'if_this_then' => $skillData['if_this_then'] ?? ($skill ? $skill->if_this_then : []),
                'tools' => $skillData['tools'] ?? ($skill ? $skill->tools : []),
                'is_active' => $skillData['is_active'] ?? ($skill ? $skill->is_active : true),
            ];

            if ($skill) {
                $skill->update($values);
            } else {
                $skill = AgentSkill::create($values);
            }

            if (isset($skillData['agents'])) {
                $agentIds = Agent::whereIn('slug', $skillData['agents'])
                    ->orWhereIn('name', $skillData['agents'])
                    ->pluck('id')
                    ->toArray();
                $skill->agents()->syncWithoutDetaching($agentIds);
            }

            if (isset($skillData['resource_types'])) {
                $resourceTypeIds = ResourceType::whereNameIn($skillData['resource_types'])
                    ->pluck('id')
                    ->toArray();
                $skill->resourceTypes()->syncWithoutDetaching($resourceTypeIds);
            }
        }
    }
}
