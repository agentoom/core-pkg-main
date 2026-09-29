<?php

namespace Agentoom\Core\DataTransfer\Sections;

use Agentoom\Core\DataTransfer\AbstractImportExportSection;
use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Models\SkillPreset;
use Agentoom\Core\Models\Tool;
use Agentoom\Core\Services\InjectCurrentAiConfigService;
use Illuminate\Support\Collection;

/**
 * Agents export/import section.
 */
class AgentsSection extends AbstractImportExportSection
{
    public function key(): string
    {
        return 'agents';
    }

    public function group(): string
    {
        return ImportExportRegistry::GROUP_AGENTS;
    }

    public function permission(): ?string
    {
        return 'agents.view';
    }

    public function options(): Collection
    {
        return Agent::all();
    }

    public function importLevel(): int
    {
        return 2;
    }

    /**
     * @param  array<int, int|string>  $projectIds
     * @return array<int, int|string>
     */
    public function projectIds(array $projectIds): ?array
    {
        return Agent::whereIn('project_id', $projectIds)->pluck('id')->toArray();
    }

    public function export(array $ids): array
    {
        $query = Agent::query();
        if (($ids = $this->selectedIds($ids)) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function ($agent) {
            $config = $agent->config;

            if (empty($config['provider_id'])) {
                $effective = InjectCurrentAiConfigService::inject($agent);
                $config['provider_id'] = $effective['provider_name'];
                $config['provider_model_id'] = $effective['model'];
                $config['temperature'] = $effective['temperature'];
                $config['top_p'] = $effective['top_p'];
            } else {
                if (isset($config['provider_id']) && is_numeric($config['provider_id'])) {
                    $provider = Provider::find($config['provider_id']);
                    if ($provider) {
                        $config['provider_id'] = $provider->name;
                    }
                }

                if (isset($config['provider_model_id']) && is_numeric($config['provider_model_id'])) {
                    $model = ProviderModel::find($config['provider_model_id']);
                    if ($model) {
                        $config['provider_model_id'] = $model->model_name;
                    }
                }
            }

            $mcpServerName = null;
            if ($agent->mcp_server_id) {
                $mcpServer = McpServer::find($agent->mcp_server_id);
                $mcpServerName = $mcpServer?->name;
            }

            return [
                'name' => $agent->name,
                'slug' => $agent->slug,
                'description' => $agent->description,
                'instructions' => $agent->instructions,
                'role' => $agent->role,
                'objective' => $agent->objective,
                'prioritize' => $agent->prioritize,
                'constraints' => $agent->constraints,
                'conditional_rules' => $agent->conditional_rules,
                'output_format' => $agent->output_format,
                'examples' => $agent->examples,
                'operational_protocol' => $agent->operational_protocol,
                'error_handling' => $agent->error_handling,
                'is_cautious_constraint_enabled' => $agent->is_cautious_constraint_enabled,
                'is_active' => $agent->is_active,
                'is_public' => $agent->is_public,
                'allow_human_intervention' => $agent->allow_human_intervention,
                'system_agent' => $agent->system_agent,
                'default_agent' => $agent->default_agent,
                'config' => $config,
                'prompt_settings' => $agent->prompt_settings,
                'max_usd_limit' => $agent->max_usd_limit,
                'limit_period' => $agent->limit_period,
                'is_hard_limit' => $agent->is_hard_limit,
                'max_tasks_per_period' => $agent->max_tasks_per_period,
                'task_limit_period' => $agent->task_limit_period,
                'project_name' => $agent->project?->name,
                'skill_presets' => $agent->skillPresets->pluck('name')->toArray(),
                'skills' => $agent->skills->pluck('slug')->toArray(),
                'tools' => $agent->tools->pluck('class_name')->toArray(),
                'mcp_server' => $mcpServerName,
                'remote_name' => $agent->remote_name,
            ];
        })->toArray();
    }

    /**
     * @param  array<int, array<string, mixed>>  $payload
     * @param  array<string, mixed>  $options
     */
    public function import(array $payload, array $options = []): void
    {
        $onlyIfConfigEmpty = $options['only_if_config_empty'] ?? false;

        foreach ($payload as $agentData) {
            $agent = null;
            if (isset($agentData['slug'])) {
                $agent = Agent::where('slug', $agentData['slug'])->first();
            }

            if (! $agent && isset($agentData['name'])) {
                $agent = Agent::where('name', $agentData['name'])->first();
            }

            $project = null;
            if (isset($agentData['project_name'])) {
                $project = Project::where('name', $agentData['project_name'])->first();
            }

            $updates = [
                'name' => $agentData['name'] ?? ($agent ? $agent->name : ''),
                'description' => $agentData['description'] ?? ($agent ? $agent->description : null),
                'instructions' => $agentData['instructions'] ?? ($agent ? $agent->instructions : null),
                'role' => $agentData['role'] ?? ($agent ? $agent->role : null),
                'objective' => $agentData['objective'] ?? ($agent ? $agent->objective : null),
                'prioritize' => $agentData['prioritize'] ?? ($agent ? $agent->prioritize : []),
                'constraints' => $agentData['constraints'] ?? ($agent ? $agent->constraints : []),
                'conditional_rules' => $agentData['conditional_rules'] ?? ($agent ? $agent->conditional_rules : []),
                'output_format' => $agentData['output_format'] ?? ($agent ? $agent->output_format : []),
                'examples' => $agentData['examples'] ?? ($agent ? $agent->examples : []),
                'operational_protocol' => $agentData['operational_protocol'] ?? ($agent ? $agent->operational_protocol : null),
                'error_handling' => $agentData['error_handling'] ?? ($agent ? $agent->error_handling : null),
                'is_cautious_constraint_enabled' => $agentData['is_cautious_constraint_enabled'] ?? ($agent ? $agent->is_cautious_constraint_enabled : true),
                'is_active' => $agentData['is_active'] ?? ($agent ? $agent->is_active : true),
                'is_public' => $agentData['is_public'] ?? ($agent ? $agent->is_public : true),
                'system_agent' => $agentData['system_agent'] ?? ($agent ? $agent->system_agent : false),
                'default_agent' => $agentData['default_agent'] ?? ($agent ? $agent->default_agent : false),
                'slug' => $agentData['slug'] ?? ($agent ? $agent->slug : null),
                'allow_human_intervention' => $agentData['allow_human_intervention'] ?? ($agent ? $agent->allow_human_intervention : false),
                'max_usd_limit' => $agentData['max_usd_limit'] ?? ($agent ? $agent->max_usd_limit : null),
                'limit_period' => $agentData['limit_period'] ?? ($agent ? $agent->limit_period : null),
                'is_hard_limit' => $agentData['is_hard_limit'] ?? ($agent ? $agent->is_hard_limit : false),
                'max_tasks_per_period' => $agentData['max_tasks_per_period'] ?? ($agent ? $agent->max_tasks_per_period : null),
                'task_limit_period' => $agentData['task_limit_period'] ?? ($agent ? $agent->task_limit_period : null),
                'project_id' => $project?->id ?? $agentData['project_id'] ?? ($agent ? $agent->project_id : null),
                'user_id' => $agentData['user_id'] ?? ($agent ? $agent->user_id : $this->ownerId()),
                'remote_name' => $agentData['remote_name'] ?? ($agent ? $agent->remote_name : null),
            ];

            if (isset($agentData['mcp_server'])) {
                $mcpServer = McpServer::where('name', $agentData['mcp_server'])->first();
                if ($mcpServer) {
                    $updates['mcp_server_id'] = $mcpServer->id;
                }
            }

            if (! $agent || ! $onlyIfConfigEmpty || empty($agent->config)) {
                $config = $agentData['config'] ?? ($agent ? $agent->config : []);

                if (isset($config['provider_id']) && ! is_numeric($config['provider_id'])) {
                    $provider = Provider::where('name', $config['provider_id'])->first();
                    if ($provider) {
                        $config['provider_id'] = $provider->id;
                    }
                }

                if (isset($config['provider_model_id']) && ! is_numeric($config['provider_model_id'])) {
                    $provider = null;
                    if (isset($config['provider_id']) && is_numeric($config['provider_id'])) {
                        $provider = Provider::find($config['provider_id']);
                    }

                    $modelQuery = ProviderModel::where('model_name', $config['provider_model_id']);
                    if ($provider) {
                        $modelQuery->where('provider_id', $provider->id);
                    }
                    $model = $modelQuery->first();

                    if ($model) {
                        $config['provider_model_id'] = $model->id;
                    }
                }

                $updates['config'] = $config;
            }

            if ($agent) {
                $agent->update($updates);
            } else {
                $agent = Agent::create($updates);
            }

            if (isset($agentData['skill_presets'])) {
                $presetIds = SkillPreset::whereIn('name', $agentData['skill_presets'])
                    ->pluck('id')
                    ->toArray();
                $agent->skillPresets()->syncWithoutDetaching($presetIds);
            }

            if (isset($agentData['skills'])) {
                $skillIds = AgentSkill::whereIn('slug', $agentData['skills'])
                    ->pluck('id')
                    ->toArray();
                $agent->skills()->syncWithoutDetaching($skillIds);
            }

            if (isset($agentData['tools'])) {
                $toolIds = Tool::whereIn('class_name', $agentData['tools'])
                    ->pluck('id')
                    ->toArray();
                $agent->tools()->syncWithoutDetaching($toolIds);
            }
        }
    }
}
