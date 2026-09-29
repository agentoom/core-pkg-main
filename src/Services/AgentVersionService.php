<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgentVersionService
{
    /**
     * Create a new version snapshot for the given agent.
     */
    public function createVersion(Agent $agent, ?string $changeDescription = null): AgentVersion
    {
        // Determine the next version number
        $latestVersion = AgentVersion::where('agent_id', $agent->id)
            ->max('version_number');

        $versionNumber = ($latestVersion ?? 0) + 1;

        // Build snapshot with current state
        $snapshot = [
            'instructions' => $agent->instructions,
            'config' => $agent->config,
            'role' => $agent->role,
            'objective' => $agent->objective,
            'description' => $agent->description,
            'operational_protocol' => $agent->operational_protocol,
            'error_handling' => $agent->error_handling,
            'is_cautious_constraint_enabled' => $agent->is_cautious_constraint_enabled,
            'tool_ids' => $agent->tools()->pluck('tools.id')->toArray(),
            'skill_ids' => $agent->skills()->pluck('agent_skills.id')->toArray(),
            'knowledge_file_ids' => $agent->knowledgeFiles()->pluck('agent_knowledge_files.id')->toArray(),
        ];

        return AgentVersion::create([
            'agent_id' => $agent->id,
            'version_number' => $versionNumber,
            'snapshot' => $snapshot,
            'created_by_user_id' => auth()->id(),
            'change_description' => $changeDescription,
            'created_at' => now(),
        ]);
    }

    /**
     * Restore an agent to a previous version's state.
     */
    public function restoreVersion(AgentVersion $version): Agent
    {
        $agent = $version->agent;
        $snapshot = $version->snapshot;

        DB::transaction(function () use ($agent, $snapshot, $version) {
            // Restore only fields that are present in the snapshot
            $restoreData = [];

            $directFields = [
                'instructions', 'config', 'role', 'objective', 'description',
                'operational_protocol', 'error_handling', 'is_cautious_constraint_enabled',
            ];

            foreach ($directFields as $field) {
                if (array_key_exists($field, $snapshot) && $snapshot[$field] !== null) {
                    $restoreData[$field] = $snapshot[$field];
                }
            }

            if (! empty($restoreData)) {
                $agent->update($restoreData);
            }

            // Restore tool associations
            $toolIds = $snapshot['tool_ids'] ?? [];
            $existingToolIds = $agent->tools()->pluck('tools.id')->toArray();

            // Only sync tools that still exist
            $validToolIds = array_intersect($toolIds, $existingToolIds);

            if (! empty($validToolIds)) {
                $agent->tools()->sync($validToolIds);
            }

            // Restore skill associations
            $skillIds = $snapshot['skill_ids'] ?? [];
            $existingSkillIds = $agent->skills()->pluck('agent_skills.id')->toArray();
            $validSkillIds = array_intersect($skillIds, $existingSkillIds);

            if (! empty($validSkillIds)) {
                $agent->skills()->sync($validSkillIds);
            }

            // Note: Knowledge file associations are not synced by default
            // as they typically reference files uploaded later.
            // We skip them to avoid broken references.
            Log::info('Agent version restored', [
                'agent_id' => $agent->id,
                'version_id' => $version->id,
                'restored_by' => auth()->id(),
            ]);
        });

        return $agent;
    }

    /**
     * Compare two versions and return a structured diff array.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function diff(AgentVersion $from, AgentVersion $to): array
    {
        $fromSnapshot = $from->snapshot;
        $toSnapshot = $to->snapshot;

        $fields = [
            'instructions', 'config', 'role', 'objective', 'description',
            'operational_protocol', 'error_handling', 'is_cautious_constraint_enabled',
        ];

        $changes = [];

        foreach ($fields as $field) {
            $oldValue = $fromSnapshot[$field] ?? null;
            $newValue = $toSnapshot[$field] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        // Compare tool_ids
        $oldToolIds = $fromSnapshot['tool_ids'] ?? [];
        $newToolIds = $toSnapshot['tool_ids'] ?? [];
        sort($oldToolIds);
        sort($newToolIds);

        $addedTools = array_diff($newToolIds, $oldToolIds);
        $removedTools = array_diff($oldToolIds, $newToolIds);

        if (! empty($addedTools) || ! empty($removedTools)) {
            $changes['tools'] = [
                'added' => array_values($addedTools),
                'removed' => array_values($removedTools),
            ];
        }

        // Compare skill_ids
        $oldSkillIds = $fromSnapshot['skill_ids'] ?? [];
        $newSkillIds = $toSnapshot['skill_ids'] ?? [];
        sort($oldSkillIds);
        sort($newSkillIds);

        $addedSkills = array_diff($newSkillIds, $oldSkillIds);
        $removedSkills = array_diff($oldSkillIds, $newSkillIds);

        if (! empty($addedSkills) || ! empty($removedSkills)) {
            $changes['skills'] = [
                'added' => array_values($addedSkills),
                'removed' => array_values($removedSkills),
            ];
        }

        return $changes;
    }
}
