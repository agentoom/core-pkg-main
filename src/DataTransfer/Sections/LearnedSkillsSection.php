<?php

namespace Agentoom\Core\DataTransfer\Sections;

use Agentoom\Core\DataTransfer\AbstractImportExportSection;
use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentLearnedSkill;
use Illuminate\Support\Collection;

/**
 * Learned skills export/import section.
 */
class LearnedSkillsSection extends AbstractImportExportSection
{
    public function key(): string
    {
        return 'learned_skills';
    }

    public function group(): string
    {
        return ImportExportRegistry::GROUP_SYSTEM;
    }

    public function label(): string
    {
        return __('agentoom-core::admin.agents.learned_skills');
    }

    public function permission(): ?string
    {
        return 'skills.view';
    }

    public function options(): Collection
    {
        return AgentLearnedSkill::all();
    }

    public function optionLabel(mixed $option): string
    {
        return (string) ($option->title ?? $option->getKey());
    }

    public function importLevel(): int
    {
        return 3;
    }

    public function export(array $ids): array
    {
        $query = AgentLearnedSkill::query();
        if (($ids = $this->selectedIds($ids)) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function ($skill) {
            return [
                'title' => $skill->title,
                'content' => $skill->content,
                'agent_slug' => $skill->agent?->slug,
                'agent_name' => $skill->agent?->name,
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
            $agent = Agent::where('slug', $skillData['agent_slug'] ?? '')
                ->orWhere('name', $skillData['agent_name'] ?? '')
                ->first();

            if ($agent) {
                AgentLearnedSkill::updateOrCreate(
                    ['title' => $skillData['title'], 'agent_id' => $agent->id],
                    [
                        'content' => $skillData['content'] ?? '',
                    ]
                );
            }
        }
    }

    /**
     * @param  array<int, int|string>|array{0: 'all'}  $ids
     * @return array<int, int|string>
     */
    public function agentIds(array $ids): ?array
    {
        $query = AgentLearnedSkill::query();
        if (($ids = $this->selectedIds($ids)) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->pluck('agent_id')->filter()->unique()->values()->toArray();
    }

    /**
     * @param  array<int, int|string>  $agentIds
     * @return array<int, int|string>
     */
    public function idsForAgents(array $agentIds): ?array
    {
        return AgentLearnedSkill::whereIn('agent_id', $agentIds)->pluck('id')->toArray();
    }
}
