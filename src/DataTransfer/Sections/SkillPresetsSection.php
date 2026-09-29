<?php

namespace Agentoom\Core\DataTransfer\Sections;

use Agentoom\Core\DataTransfer\AbstractImportExportSection;
use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\SkillPreset;
use Agentoom\Core\Models\Tool;
use Illuminate\Support\Collection;

/**
 * Skill presets export/import section.
 */
class SkillPresetsSection extends AbstractImportExportSection
{
    public function key(): string
    {
        return 'skill_presets';
    }

    public function group(): string
    {
        return ImportExportRegistry::GROUP_SKILLS;
    }

    public function permission(): ?string
    {
        return 'skill-presets.view';
    }

    public function options(): Collection
    {
        return SkillPreset::all();
    }

    public function importLevel(): int
    {
        return 3;
    }

    public function export(array $ids): array
    {
        $query = SkillPreset::query();
        if (($ids = $this->selectedIds($ids)) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function ($preset) {
            return [
                'name' => $preset->name,
                'description' => $preset->description,
                'skills' => AgentSkill::whereIn('id', $preset->skills ?? [])->pluck('slug')->toArray(),
                'tools' => Tool::whereIn('id', $preset->tools ?? [])->pluck('class_name')->toArray(),
                'is_active' => $preset->is_active,
            ];
        })->toArray();
    }

    /**
     * @param  array<int, array<string, mixed>>  $payload
     * @param  array<string, mixed>  $options
     */
    public function import(array $payload, array $options = []): void
    {
        foreach ($payload as $presetData) {
            $skillIds = [];
            if (isset($presetData['skills'])) {
                $skillIds = AgentSkill::whereIn('slug', $presetData['skills'])
                    ->pluck('id')
                    ->toArray();
            }

            $toolIds = [];
            if (isset($presetData['tools'])) {
                $toolIds = Tool::whereIn('class_name', $presetData['tools'])
                    ->pluck('id')
                    ->toArray();
            }

            $preset = SkillPreset::updateOrCreate(
                ['name' => $presetData['name']],
                [
                    'description' => $presetData['description'] ?? null,
                    'skills' => $skillIds,
                    'tools' => $toolIds,
                    'is_active' => $presetData['is_active'] ?? true,
                ]
            );

            if (isset($presetData['agents'])) {
                $agentIds = Agent::whereIn('slug', $presetData['agents'])
                    ->pluck('id')
                    ->toArray();
                $preset->agents()->syncWithoutDetaching($agentIds);
            }
        }
    }
}
