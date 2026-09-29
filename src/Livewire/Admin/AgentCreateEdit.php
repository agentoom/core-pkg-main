<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Ai\Tools\Public\ManageAgentKnowledgeTool;
use Agentoom\Core\Ai\Tools\Public\ManageAgentLearnedSkillsTool;
use Agentoom\Core\Ai\Tools\Public\SearchAgentKnowledgeTool;
use Agentoom\Core\Ai\Tools\Public\SearchAgentLearnedSkillsTool;
use Agentoom\Core\Helpers\Helpers;
use Agentoom\Core\Contracts\HumanIntervention;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\AiModelPricing;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Models\SkillPreset;
use Agentoom\Core\Models\Tool;
use Agentoom\Core\Services\AgentVersionService;
use Agentoom\Core\Services\ResourceLimitManager;
use Agentoom\Core\Services\VisibilityService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Prop;
use Livewire\Component;

class AgentCreateEdit extends Component
{
    #[Prop]
    public ?Agent $agent = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $instructions = '';

    public string $operational_protocol = '';

    public string $role = '';

    public string $objective = '';

    public array $prioritize = [];

    public array $constraints = [];

    public array $conditional_rules = [];

    public array $output_format = [];

    public array $examples = [];

    public string $error_handling = '';

    public bool $is_cautious_constraint_enabled = true;

    public string $previewPrompt = '';

    public bool $show_preview_modal = false;

    public int $is_active = 0;

    public bool $is_editing = false;

    public string $version_change_description = '';

    public int $allow_human_intervention = 0;

    public ?float $max_usd_limit = null;

    public ?string $limit_period = null;

    public bool $is_hard_limit = false;

    public ?int $max_tasks_per_period = null;

    public ?int $projectId = null;

    public ?string $task_limit_period = null;

    public ?string $limit_starts_at = null;

    public ?string $task_limit_starts_at = null;

    public array $task_permissions = [];

    public int $is_language_policy_enabled = 0;

    public int $allow_memory_analysis = 1;

    public int $anonymization_enabled = 1;

    public array $prompt_settings = [
        'parts' => [],
        'tools' => [],
    ];

    // Config fields
    public ?int $provider_id = null;

    public ?int $provider_model_id = null;

    public string $provider_output_modality = 'all';

    public string $temperature = '';

    public string $top_p = '';

    public string $provider_model_suffix = '';

    public bool $openrouter_disable_cache = false;

    public array $selected_tools = [];

    public array $selected_skills = [];

    public array $selected_presets = [];

    public array $previous_selected_presets = [];

    public function updatedSelectedPresets($value): void
    {
        $value = array_values(array_unique(array_map('intval', (array) $value)));

        $added = array_diff($value, $this->previous_selected_presets);
        $removed = array_diff($this->previous_selected_presets, $value);

        if (empty($added) && empty($removed)) {
            return;
        }

        $allActivePresets = SkillPreset::whereIn('id', $value)->get();
        $presetSkills = $allActivePresets->pluck('skills')->flatten()->unique()->map('intval')->toArray();
        $presetTools = $allActivePresets->pluck('tools')->flatten()->unique()->map('intval')->toArray();

        // Tools from preset skills
        $presetSkillTools = [];
        if (! empty($presetSkills)) {
            $skills = AgentSkill::whereIn('id', $presetSkills)->get();
            foreach ($skills as $skill) {
                if (! empty($skill->tools)) {
                    $toolIds = Tool::whereIn('class_name', $skill->tools)->pluck('id')->toArray();
                    $presetSkillTools = array_merge($presetSkillTools, array_map('intval', $toolIds));
                }
            }
        }
        $allPresetTools = array_unique(array_merge($presetTools, $presetSkillTools));

        if (! empty($added)) {
            $this->selected_skills = array_values(array_unique(array_merge($this->selected_skills, $presetSkills)));
            $this->selected_tools = array_values(array_unique(array_merge($this->selected_tools, $allPresetTools)));
        }

        if (! empty($removed)) {
            // Get skills and tools that are from the removed presets
            $removedPresets = SkillPreset::whereIn('id', $removed)->get();
            $removedSkills = $removedPresets->pluck('skills')->flatten()->unique()->map('intval')->toArray();
            $removedTools = $removedPresets->pluck('tools')->flatten()->unique()->map('intval')->toArray();

            // Tools from removed preset skills
            $removedSkillTools = [];
            if (! empty($removedSkills)) {
                $rSkills = AgentSkill::whereIn('id', $removedSkills)->get();
                foreach ($rSkills as $skill) {
                    if (! empty($skill->tools)) {
                        $toolIds = Tool::whereIn('class_name', $skill->tools)->pluck('id')->toArray();
                        $removedSkillTools = array_merge($removedSkillTools, array_map('intval', $toolIds));
                    }
                }
            }
            $allRemovedTools = array_unique(array_merge($removedTools, $removedSkillTools));

            foreach ($removedSkills as $skillId) {
                if (! in_array($skillId, $presetSkills)) {
                    $this->selected_skills = array_diff($this->selected_skills, [$skillId]);
                }
            }

            foreach ($allRemovedTools as $toolId) {
                if (! in_array($toolId, $allPresetTools)) {
                    $this->selected_tools = array_diff($this->selected_tools, [$toolId]);
                }
            }
        }

        $this->previous_selected_presets = $value;

        // Ensure arrays are clean for Livewire
        $this->selected_skills = array_values(array_map('intval', $this->selected_skills));
        $this->selected_tools = array_values(array_map('intval', $this->selected_tools));
    }

    public function mount(?Agent $agent = null): void
    {
        $this->agent = $agent;
        $this->is_editing = $agent !== null;

        if ($this->is_editing) {
            $this->name = $agent->name;
            $this->slug = $agent->slug;
            $this->description = $agent->description ?? '';
            $this->instructions = $agent->instructions ?? '';
            $this->operational_protocol = $agent->operational_protocol ?? '';
            $this->role = $agent->role ?? '';
            $this->objective = $agent->objective ?? '';
            $this->prioritize = $agent->prioritize ?? [];
            $this->constraints = $agent->constraints ?? [];
            $this->is_cautious_constraint_enabled = $agent->is_cautious_constraint_enabled ?? true;
            $this->conditional_rules = $agent->conditional_rules ?? [];
            $this->output_format = $agent->output_format ?? [];
            $this->examples = $agent->examples ?? [];
            $this->error_handling = $agent->error_handling ?? '';
            $this->is_active = $agent->is_active ? 1 : 0;
            $this->allow_memory_analysis = ($agent->allow_memory_analysis ?? true) ? 1 : 0;
            $this->anonymization_enabled = ($agent->anonymization_enabled ?? true) ? 1 : 0;
            $this->allow_human_intervention = $agent->allow_human_intervention ? 1 : 0;
            $this->max_usd_limit = $agent->max_usd_limit ? (float) $agent->max_usd_limit : null;
            $this->limit_period = $agent->limit_period;
            $this->is_hard_limit = (bool) $agent->is_hard_limit;
            $this->max_tasks_per_period = $agent->max_tasks_per_period;
            $this->projectId = $agent->project_id;
            $this->task_limit_period = $agent->task_limit_period;
            $this->limit_starts_at = $agent->limit_starts_at?->format('Y-m-d');
            $this->task_limit_starts_at = $agent->task_limit_starts_at?->format('Y-m-d');

            $this->prompt_settings = $agent->prompt_settings ?: [
                'parts' => [],
                'tools' => [],
            ];

            // Load task permissions
            $this->task_permissions = $agent->taskPermissions->map(fn ($p) => [
                'permitted_agent_id' => $p->permitted_agent_id,
                'can_assign' => $p->can_assign,
                'can_view' => $p->can_view,
                'assignment_explanation' => $p->assignment_explanation,
            ])->toArray();

            $config = $agent->config ?? [];
            $this->provider_id = isset($config['provider_id']) ? (int) $config['provider_id'] : null;
            $this->provider_model_id = isset($config['provider_model_id']) ? (int) $config['provider_model_id'] : null;
            $this->provider_output_modality = $config['provider_output_modality'] ?? 'all';
            $this->temperature = (string) ($config['temperature'] ?? '');
            $this->top_p = (string) ($config['top_p'] ?? '1.0');
            $this->provider_model_suffix = $config['provider_model_suffix'] ?? '';
            $this->openrouter_disable_cache = (bool) ($config['openrouter_disable_cache'] ?? false);

            // Load selected tools
            $this->selected_tools = $agent->tools()->pluck('tools.id')->toArray();

            // Load selected skills
            $this->selected_skills = $agent->skills()->pluck('agent_skills.id')->toArray();

            // Load selected presets
            $this->selected_presets = $agent->skillPresets()->pluck('skill_presets.id')->toArray();
            $this->previous_selected_presets = $this->selected_presets;

            $this->is_language_policy_enabled = ($this->prompt_settings['parts']['language_policy'] ?? false) ? 1 : 0;
        } else {
            $globalConfig = $this->globalConfig;
            $this->temperature = (string) ($globalConfig['temperature'] ?? '0');
            $this->top_p = (string) ($globalConfig['top_p'] ?? '0');
            $this->prompt_settings = [
                'parts' => [],
                'tools' => [],
            ];

            // Load default tools
            $defaultToolClasses = [
                SearchAgentKnowledgeTool::class,
                ManageAgentKnowledgeTool::class,
                SearchAgentLearnedSkillsTool::class,
                ManageAgentLearnedSkillsTool::class,
            ];

            $this->selected_tools = Tool::whereIn('class_name', $defaultToolClasses)
                ->pluck('id')
                ->toArray();
        }

        if (! $this->provider_id && $this->providers->count() === 1) {
            $this->provider_id = $this->providers->first()->id;
        }
    }

    public function updatedProviderId()
    {
        // Reset model when provider changes
        $this->provider_output_modality = 'all';
        $this->provider_model_id = null;
    }

    public function updatedProviderOutputModality()
    {
        $this->provider_model_id = null;
    }

    public function updatedSelectedSkills($values)
    {
        $skills = AgentSkill::whereIn('id', $values)->get();

        foreach ($skills as $skill) {
            if (! empty($skill->tools)) {
                $toolClassNames = $skill->tools;
                $toolIds = Tool::whereIn('class_name', $toolClassNames)->pluck('id')->toArray();
                foreach ($toolIds as $id) {
                    if (! in_array($id, $this->selected_tools)) {
                        $this->selected_tools[] = $id;
                    }
                }
            }
        }
    }

    public function updatedName($value): void
    {
        if (! $this->is_editing) {
            $this->slug = Str::slug($value);
        }
    }

    public function addTaskPermission(): void
    {
        $this->task_permissions[] = [
            'permitted_agent_id' => null,
            'can_assign' => true,
            'can_view' => true,
            'assignment_explanation' => '',
        ];
    }

    public function removeTaskPermission(int $index): void
    {
        unset($this->task_permissions[$index]);
        $this->task_permissions = array_values($this->task_permissions);
    }

    public function addPrioritize(): void
    {
        $this->prioritize[] = '';
    }

    public function removePrioritize(int $index): void
    {
        unset($this->prioritize[$index]);
        $this->prioritize = array_values($this->prioritize);
    }

    public function addConstraint(): void
    {
        $this->constraints[] = '';
    }

    public function removeConstraint(int $index): void
    {
        unset($this->constraints[$index]);
        $this->constraints = array_values($this->constraints);
    }

    public function addConditionalRule(): void
    {
        $this->conditional_rules[] = [
            'condition' => '',
            'if_true' => '',
            'if_false' => '',
        ];
    }

    public function removeConditionalRule(int $index): void
    {
        unset($this->conditional_rules[$index]);
        $this->conditional_rules = array_values($this->conditional_rules);
    }

    public function addOutputFormat(): void
    {
        $this->output_format[] = '';
    }

    public function removeOutputFormat(int $index): void
    {
        unset($this->output_format[$index]);
        $this->output_format = array_values($this->output_format);
    }

    public function addExample(): void
    {
        $this->examples[] = [
            'input' => '',
            'output' => '',
        ];
    }

    public function removeExample(int $index): void
    {
        unset($this->examples[$index]);
        $this->examples = array_values($this->examples);
    }

    public function previewSystemPrompt(): void
    {
        $tempAgent = new Agent;
        $tempAgent->name = $this->name;
        $tempAgent->role = $this->role;
        $tempAgent->objective = $this->objective;
        $tempAgent->instructions = $this->instructions;
        $tempAgent->operational_protocol = $this->operational_protocol;
        $tempAgent->prioritize = $this->prioritize;
        $tempAgent->constraints = $this->constraints;
        $tempAgent->is_cautious_constraint_enabled = $this->is_cautious_constraint_enabled;
        $tempAgent->conditional_rules = $this->conditional_rules;
        $tempAgent->error_handling = $this->error_handling;
        $tempAgent->output_format = $this->output_format;
        $tempAgent->examples = $this->examples;
        $tempAgent->project_id = $this->projectId;
        $tempAgent->prompt_settings = $this->prompt_settings;

        // Set relationships for DynamicAgent
        $tempAgent->setRelation('skills', AgentSkill::whereIn('id', $this->selected_skills)->get());
        if ($this->projectId) {
            $tempAgent->setRelation('project', Project::find($this->projectId));
        }

        $dynamicAgent = new DynamicAgent($tempAgent);
        $this->previewPrompt = $dynamicAgent->instructions();
        $this->show_preview_modal = true;
    }

    public function save(bool $stay = false)
    {
        Gate::authorize($this->is_editing ? 'agents.edit' : 'agents.create');

        if (! $this->is_editing && app(ResourceLimitManager::class)->isLimitReached('agents')) {
            $this->addError('name', __('agentoom-core::admin.resource_limits.usage.limit_reached'));

            return;
        }

        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('agents', 'slug')->ignore($this->agent?->id),
            ],
            'description' => 'nullable|string|max:500',
            'role' => 'nullable|string',
            'objective' => 'nullable|string',
            'instructions' => 'nullable|string',
            'operational_protocol' => 'nullable|string',
            'prioritize' => 'array',
            'constraints' => 'array',
            'is_cautious_constraint_enabled' => 'boolean',
            'conditional_rules' => 'array',
            'error_handling' => 'nullable|string',
            'output_format' => 'array',
            'examples' => 'array',
            'is_active' => 'boolean',
            'max_usd_limit' => 'nullable|numeric|min:0',
            'limit_period' => 'nullable|string|in:daily,weekly,monthly',
            'is_hard_limit' => 'boolean',
            'max_tasks_per_period' => 'nullable|integer|min:0',
            'limit_starts_at' => 'nullable|date',
            'task_limit_starts_at' => 'nullable|date',
            'projectId' => 'nullable|exists:projects,id',
            'task_limit_period' => 'nullable|string|in:daily,weekly,monthly',
            'task_permissions' => 'array',
            'task_permissions.*.permitted_agent_id' => 'required|exists:agents,id',
            'task_permissions.*.can_assign' => 'boolean',
            'task_permissions.*.can_view' => 'boolean',
            'task_permissions.*.assignment_explanation' => 'nullable|string',
            'provider_id' => 'nullable|exists:providers,id',
            'provider_model_id' => 'nullable|exists:provider_models,id',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'top_p' => 'nullable|numeric|min:0|max:1',
            'openrouter_disable_cache' => 'boolean',
            'selected_tools' => 'array',
            'selected_tools.*' => 'exists:tools,id',
            'selected_skills' => 'array',
            'selected_skills.*' => 'exists:agent_skills,id',
        ]);

        $config = [];
        if ($this->provider_id) {
            $config['provider_id'] = $this->provider_id;
        }
        if ($this->provider_model_id) {
            $config['provider_model_id'] = $this->provider_model_id;
        }
        if (auth()->user()->hasRole('superadmin')) {
            $config['provider_output_modality'] = $this->provider_output_modality;
        } elseif ($this->is_editing) {
            $config['provider_output_modality'] = $this->agent->config['provider_output_modality'] ?? 'all';
        } else {
            $config['provider_output_modality'] = 'all';
        }
        if ($this->temperature !== '') {
            $config['temperature'] = (float) $this->temperature;
        }
        if ($this->top_p !== '') {
            $config['top_p'] = (float) $this->top_p;
        }
        if ($this->provider_model_suffix !== '') {
            $config['provider_model_suffix'] = $this->provider_model_suffix;
        }
        $config['openrouter_disable_cache'] = $this->openrouter_disable_cache;

        $this->prompt_settings['parts']['language_policy'] = $this->is_language_policy_enabled;

        $data = [
            'name' => $this->name,
            'slug' => $this->slug ?: Str::slug($this->name),
            'description' => $this->description,
            'role' => $this->role,
            'objective' => $this->objective,
            'instructions' => $this->instructions,
            'operational_protocol' => $this->operational_protocol,
            'prioritize' => $this->prioritize,
            'constraints' => $this->constraints,
            'is_cautious_constraint_enabled' => $this->is_cautious_constraint_enabled,
            'conditional_rules' => $this->conditional_rules,
            'error_handling' => $this->error_handling,
            'output_format' => $this->output_format,
            'examples' => $this->examples,
            'is_active' => $this->is_active,
            'allow_human_intervention' => $this->allow_human_intervention,
            'max_usd_limit' => $this->max_usd_limit,
            'limit_period' => $this->limit_period,
            'is_hard_limit' => $this->is_hard_limit,
            'max_tasks_per_period' => $this->max_tasks_per_period,
            'limit_starts_at' => $this->limit_starts_at,
            'task_limit_starts_at' => $this->task_limit_starts_at,
            'project_id' => $this->projectId,
            'task_limit_period' => $this->task_limit_period,
            'allow_memory_analysis' => $this->allow_memory_analysis,
            'anonymization_enabled' => $this->anonymization_enabled,
            'config' => $config,
            'prompt_settings' => $this->prompt_settings,
        ];

        if ($this->is_editing) {
            $this->agent->update($data);
            $this->saveTaskPermissions($this->agent);
            $this->agent->tools()->sync($this->selected_tools);
            $this->agent->skills()->sync($this->selected_skills);
            $this->agent->skillPresets()->sync($this->selected_presets);

            // Create version snapshot
            app(AgentVersionService::class)->createVersion(
                $this->agent,
                $this->version_change_description ?: null
            );

            session()->flash('success', __('agentoom-core::admin.agents.updated_success'));
        } else {
            $data['user_id'] = auth()->id();
            $this->agent = Agent::create($data);
            $this->saveTaskPermissions($this->agent);
            $this->agent->tools()->sync($this->selected_tools);
            $this->agent->skills()->sync($this->selected_skills);
            $this->agent->skillPresets()->sync($this->selected_presets);

            // Create initial version snapshot
            app(AgentVersionService::class)->createVersion(
                $this->agent,
                'Initial version'
            );

            session()->flash('success', __('agentoom-core::admin.agents.created_success'));
            $this->is_editing = true;
        }

        if ($stay) {
            return $this->redirect(route('admin.agents.edit', $this->agent), navigate: true);
        }

        return $this->redirect(route('admin.agents.index'), navigate: true);
    }

    public function clone(): void
    {
        if (! $this->agent || ! $this->agent->exists) {
            return;
        }

        $newAgent = $this->agent->replicate();
        $newAgent->name = $this->agent->name.' (Clone)';
        $newAgent->slug = Str::slug($newAgent->name);
        $newAgent->system_agent = false;
        $newAgent->default_agent = false;
        $newAgent->save();

        // Sync relationships
        $newAgent->tools()->sync($this->agent->tools()->pluck('tools.id'));
        $newAgent->skills()->sync($this->agent->skills()->pluck('agent_skills.id'));
        $newAgent->skillPresets()->sync($this->agent->skillPresets()->pluck('skill_presets.id'));

        // Task permissions
        foreach ($this->agent->taskPermissions as $permission) {
            $newAgent->taskPermissions()->create($permission->toArray());
        }

        session()->flash('success', __('agentoom-core::admin.common.cloned_success'));

        $this->redirect(route('admin.agents.edit', $newAgent), navigate: true);
    }

    private function saveTaskPermissions(Agent $agent): void
    {
        $agent->taskPermissions()->delete();

        foreach ($this->task_permissions as $permission) {
            $agent->taskPermissions()->create($permission);
        }
    }

    public function getGlobalConfigProperty()
    {
        return [
            'provider_id' => Configuration::getValue('ai_provider'),
            'provider_model_id' => Configuration::getValue('ai_model'),
            'temperature' => Configuration::getValue('ai_temperature', 0.7),
        ];
    }

    public function updatedTaskPermissions($value, $key): void
    {
        if (str_ends_with($key, '.permitted_agent_id') && $value && ! $this->projectId) {
            $agent = Agent::find($value);
            if ($agent && $agent->project_id) {
                $this->projectId = $agent->project_id;
            }
        }
    }

    public function getToolInfo(string $toolClass): array
    {
        return Helpers::getToolInfo($toolClass);
    }

    public function getSelectedModelPricingProperty(): ?AiModelPricing
    {
        if (! $this->provider_model_id) {
            return null;
        }
        $model = ProviderModel::with('provider')->find($this->provider_model_id);

        return $model ? $this->pricingForModel($model) : null;
    }

    public function getGlobalModelPricingProperty(): ?AiModelPricing
    {
        $globalConfig = $this->globalConfig;
        if (! $globalConfig['provider_model_id'] || ! $globalConfig['provider_id']) {
            return null;
        }
        $id = $globalConfig['provider_model_id'];
        $model = is_numeric($id)
            ? ProviderModel::with('provider')->find($id)
            : ProviderModel::with('provider')->where('model_name', $id)->first();

        return $model ? $this->pricingForModel($model) : null;
    }

    private function pricingForModel(ProviderModel $model): ?AiModelPricing
    {
        return AiModelPricing::query()
            ->where('provider', $model->provider->driver ?: $model->provider->name)
            ->where('model', $model->model_name)
            ->whereIn('source', ['manual', 'provider_native', 'litellm', 'models_dev'])
            ->orderByRaw("CASE source WHEN 'manual' THEN 1 WHEN 'provider_native' THEN 2 WHEN 'litellm' THEN 3 ELSE 4 END")
            ->first();
    }

    #[Computed]
    public function providers()
    {
        return Provider::orderBy('name')->get();
    }

    #[Computed]
    public function availableAgents()
    {
        $agentsQuery = Agent::where('is_active', true)
            ->where('id', '!=', $this->agent?->id);

        if ($this->projectId) {
            $agentsQuery->where('project_id', $this->projectId);
        }

        return $agentsQuery->orderBy('name')->get();
    }

    #[Computed]
    public function availableModels()
    {
        if (! $this->provider_id) {
            return [];
        }

        $query = ProviderModel::where(function ($query) {
            if (is_numeric($this->provider_id)) {
                $query->where('provider_id', $this->provider_id);
            } else {
                $query->whereHas('provider', function ($q) {
                    $q->where('name', $this->provider_id);
                });
            }
        })
            ->where('is_active', true);

        // Check if provider is OpenRouter
        $provider = is_numeric($this->provider_id)
            ? Provider::find($this->provider_id)
            : Provider::where('name', $this->provider_id)->first();

        if ($provider && $provider->name === 'openrouter' && $this->provider_output_modality && $this->provider_output_modality !== 'all') {
            $query->where('model_features->architecture->output_modalities', 'like', '%"'.$this->provider_output_modality.'"%');
        }

        return $query->orderBy('model_name')->get();
    }

    #[Computed]
    public function globalProvider()
    {
        if (! $this->globalConfig['provider_id']) {
            return null;
        }
        $id = $this->globalConfig['provider_id'];

        return is_numeric($id) ? Provider::find($id) : Provider::where('name', $id)->first();
    }

    #[Computed]
    public function globalModel()
    {
        if (! $this->globalConfig['provider_model_id']) {
            return null;
        }
        $id = $this->globalConfig['provider_model_id'];

        return is_numeric($id) ? ProviderModel::find($id) : ProviderModel::where('model_name', $id)->first();
    }

    #[Computed]
    public function defaultModelForProvider(): ?ProviderModel
    {
        if (! $this->provider_id || $this->provider_model_id) {
            return null;
        }

        $id = $this->globalConfig['provider_model_id'];
        if (! $id) {
            return null;
        }

        $provider = is_numeric($this->provider_id)
            ? Provider::find($this->provider_id)
            : Provider::where('name', $this->provider_id)->first();

        return is_numeric($id)
            ? ProviderModel::find($id)
            : ProviderModel::where('model_name', $id)
                ->where('provider_id', $provider?->id)
                ->first();
    }

    #[Computed]
    public function tools()
    {
        $visibilityService = app(VisibilityService::class);

        $query = Tool::where('is_available', true)->where('is_active', true);

        if (! $visibilityService->isAllGroupsActive()) {
            $activeGroups = $visibilityService->getActiveGroups();
            $query->where(function ($q) use ($activeGroups) {
                $q->whereNull('visibility_group')
                    ->orWhereIn('visibility_group', $activeGroups->toArray());
            });
        }

        return $query->orderBy('name')->get();
    }

    #[Computed]
    public function skills()
    {
        return AgentSkill::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function presets()
    {
        return SkillPreset::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function incomingPermissions()
    {
        if (! $this->agent) {
            return [];
        }

        return $this->agent->permittedTaskPermissions()->with('agent')->get();
    }

    #[Computed]
    public function projects()
    {
        return Project::orderBy('name')->get();
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.agent-create-edit', [
            'globalConfig' => $this->globalConfig,
            'humanInterventionSupported' => app(HumanIntervention::class)->isSupported(),
        ])
            ->layout('agentoom-core::layouts.admin');
    }
}
