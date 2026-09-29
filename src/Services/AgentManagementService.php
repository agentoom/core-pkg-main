<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\AgentTaskPermission;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Models\Tool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;

class AgentManagementService
{
    public function discoverAgents(): string
    {
        $agents = Agent::query()
            ->with(['tools', 'skills'])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'project_id', 'description', 'is_active', 'is_public']);

        if ($agents->isEmpty()) {
            return 'No agents found.';
        }

        $output = "AVAILABLE AGENTS:\n\n";
        foreach ($agents as $agent) {
            $output .= "- Agent ID: {$agent->id} | Name: {$agent->name} | Slug: {$agent->slug}\n";
            $output .= '  Active: '.($agent->is_active ? 'yes' : 'no').' | Public: '.($agent->is_public ? 'yes' : 'no')."\n";
            if (! empty($agent->project_id)) {
                $output .= "  Project ID: {$agent->project_id}\n";
            }
            if (! empty($agent->description)) {
                $output .= "  Description: {$agent->description}\n";
            }
            if ($agent->tools->isNotEmpty()) {
                $output .= '  Assigned Tools: '.$agent->tools->map(fn ($t) => "{$t->name} (ID: {$t->id}, Class: {$t->class_name})")->implode(', ')."\n";
            }
            if ($agent->skills->isNotEmpty()) {
                $output .= '  Assigned Skills: '.$agent->skills->map(fn ($s) => "{$s->name} (ID: {$s->id}, Slug: {$s->slug})")->implode(', ')."\n";
            }
        }

        return trim($output);
    }

    public function discoverProjects(): string
    {
        $projects = Project::query()
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        if ($projects->isEmpty()) {
            return 'No projects found.';
        }

        $output = "AVAILABLE PROJECTS:\n\n";
        foreach ($projects as $project) {
            $output .= "- Project ID: {$project->id} | Name: {$project->name}\n";
            if (! empty($project->description)) {
                $output .= "  Description: {$project->description}\n";
            }
        }

        return trim($output);
    }

    public function discoverTools(?string $search = null): string
    {
        $tools = Tool::query()
            ->where('is_available', true)
            ->search($search)
            ->orderBy('name')
            ->get(['id', 'name', 'class_name', 'description']);

        if ($tools->isEmpty()) {
            return 'No available tools found.';
        }

        $output = "AVAILABLE TOOLS:\n\n";
        foreach ($tools as $tool) {
            $output .= "- ID: {$tool->id} | Name: {$tool->name} | Class: {$tool->class_name}\n";
            if (! empty($tool->description)) {
                $output .= "  Description: {$tool->description}\n";
            }
        }

        return trim($output);
    }

    public function discoverSkills(?string $search = null): string
    {
        $skills = AgentSkill::query()
            ->where('is_active', true)
            ->search($search)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'tools']);

        if ($skills->isEmpty()) {
            return 'No active skills found.';
        }

        $output = "AVAILABLE SKILLS:\n\n";
        foreach ($skills as $skill) {
            $output .= "- ID: {$skill->id} | Name: {$skill->name} | Slug: {$skill->slug}\n";
            if (! empty($skill->description)) {
                $output .= "  Description: {$skill->description}\n";
            }
            if (! empty($skill->tools)) {
                $output .= '  Tool Classes: '.implode(', ', $skill->tools)."\n";
            }
        }

        return trim($output);
    }

    public function discoverModels(): string
    {
        $providers = Provider::query()->orderBy('name')->get();

        if ($providers->isEmpty()) {
            return 'No providers configured.';
        }

        [$defaultProvider, $defaultModel] = $this->resolveDefaultProviderAndModel();

        $output = "AVAILABLE PROVIDERS & MODELS:\n\n";
        foreach ($providers as $provider) {
            $output .= "- Provider ID: {$provider->id} | Name: {$provider->name}";
            if ($defaultProvider && (int) $defaultProvider->id === (int) $provider->id) {
                $output .= ' [DEFAULT PROVIDER]';
            }
            $output .= "\n";

            $models = ProviderModel::query()
                ->where('provider_id', $provider->id)
                ->where('is_active', true)
                ->orderBy('model_name')
                ->get(['id', 'model_name']);

            if ($models->isEmpty()) {
                $output .= "  (No active models)\n";

                continue;
            }

            foreach ($models as $model) {
                $output .= "  - Model ID: {$model->id} | Name: {$model->model_name}";
                if ($defaultModel && (int) $defaultModel->id === (int) $model->id) {
                    $output .= ' [DEFAULT MODEL]';
                }
                $output .= "\n";
            }
        }

        return trim($output);
    }

    public function createAgent(Request $request): string
    {
        $name = trim((string) $request->string('name'));
        if ($name === '') {
            return 'Error: "name" is required.';
        }

        $role = trim((string) $request->string('role'));
        $instructions = trim((string) $request->string('instructions'));

        if ($role === '') {
            return 'Error: "role" is required to match the prompt section structure.';
        }

        if ($instructions === '') {
            return 'Error: "instructions" is required to match the prompt section structure.';
        }

        $slug = trim((string) $request->string('slug', Str::slug($name)));
        if ($slug === '') {
            $slug = Str::slug($name);
        }

        if (Agent::query()->where('slug', $slug)->exists()) {
            return "Error: An agent with slug '{$slug}' already exists.";
        }

        $prepared = $this->prepareAgentPayload($request, true, null);
        if (isset($prepared['error'])) {
            return $prepared['error'];
        }

        $agent = DB::transaction(function () use ($prepared) {
            $agent = Agent::create($prepared['payload']);
            $agent->refresh();
            $this->syncAgentRelations($agent, $prepared['tool_ids'], $prepared['skill_ids']);

            return $agent;
        });

        $sectionSummary = $this->buildSectionSummary($agent);

        return "Successfully created agent '{$agent->name}' (ID: {$agent->id}, slug: {$agent->slug}).\n"
            .'Assigned tools: '.count($prepared['tool_ids']).' | Assigned skills: '.count($prepared['skill_ids'])."\n"
            .$sectionSummary;
    }

    public function updateAgent(Request $request): string
    {
        $agentId = $request->integer('agent_id');
        if ($agentId <= 0) {
            return 'Error: "agent_id" is required for update_agent.';
        }

        $agent = Agent::query()->find($agentId);
        if (! $agent) {
            return "Error: Agent with ID {$agentId} does not exist.";
        }

        $role = trim((string) $request->string('role', $agent->role ?? ''));
        $instructions = trim((string) $request->string('instructions', $agent->instructions ?? ''));
        if ($role === '') {
            return 'Error: "role" is required to match the prompt section structure.';
        }
        if ($instructions === '') {
            return 'Error: "instructions" is required to match the prompt section structure.';
        }

        $prepared = $this->prepareAgentPayload($request, false, $agent);
        if (isset($prepared['error'])) {
            return $prepared['error'];
        }

        DB::transaction(function () use (&$agent, $prepared) {
            Log::info('Agent tools', $prepared['tool_ids']);
            Log::info('Agent skills', $prepared['skill_ids']);
            $agent->fill($prepared['payload']);
            $agent->save();
            $this->syncAgentRelations($agent, $prepared['tool_ids'], $prepared['skill_ids']);
        });

        $sectionSummary = $this->buildSectionSummary($agent);

        return "Successfully updated agent '{$agent->name}' (ID: {$agent->id}, slug: {$agent->slug}).\n"
            .'Assigned tools: '.count($prepared['tool_ids']).' | Assigned skills: '.count($prepared['skill_ids'])."\n"
            .$sectionSummary;
    }

    public function manageAgentTaskPermission(Request $request): string
    {
        $agentId = $request->integer('agent_id');
        $connectedAgentId = $request->integer('connected_agent_id');

        if ($agentId <= 0) {
            return 'Error: "agent_id" is required for manage_agent_task_permission.';
        }

        if ($connectedAgentId <= 0) {
            return 'Error: "connected_agent_id" is required for manage_agent_task_permission.';
        }

        if ($agentId === $connectedAgentId) {
            return 'Error: "connected_agent_id" must be different from "agent_id".';
        }

        $agent = Agent::query()->find($agentId);
        if (! $agent) {
            return "Error: Agent with ID {$agentId} does not exist.";
        }

        $connectedAgent = Agent::query()->find($connectedAgentId);
        if (! $connectedAgent) {
            return "Error: Connected agent with ID {$connectedAgentId} does not exist.";
        }

        $canAssign = $request->boolean('can_assign', false);
        $canView = $request->boolean('can_view', false);
        if (! $canAssign && ! $canView) {
            return 'Error: At least one permission must be true (`can_assign` or `can_view`).';
        }

        $assignmentExplanation = trim((string) $request->string('assignment_explanation'));
        if ($assignmentExplanation === '') {
            return 'Error: "assignment_explanation" is required to provide task instructions.';
        }

        $permission = AgentTaskPermission::query()->updateOrCreate(
            [
                'agent_id' => $agent->id,
                'permitted_agent_id' => $connectedAgent->id,
            ],
            [
                'can_assign' => $canAssign,
                'can_view' => $canView,
                'assignment_explanation' => $assignmentExplanation,
            ]
        );

        return "Successfully saved task permission (ID: {$permission->id}) for agent '{$agent->name}' (ID: {$agent->id}) with connected agent '{$connectedAgent->name}' (ID: {$connectedAgent->id}). "
            .'Permissions: can_assign='.(
                $permission->can_assign ? 'true' : 'false'
            ).', can_view='.(
                $permission->can_view ? 'true' : 'false'
            ).'.';
    }

    /**
     * Build the payload and resolve relations to sync.
     *
     * @return array{payload: array, tool_ids: array<int,int>, skill_ids: array<int,int>}|array{error: string}
     */
    private function prepareAgentPayload(Request $request, bool $isCreating, ?Agent $agent): array
    {
        $currentConfig = (array) ($agent?->config ?? []);

        $name = trim((string) $request->string('name', $agent?->name ?? ''));
        if ($name === '') {
            return ['error' => 'Error: "name" is required.'];
        }

        $slug = trim((string) $request->string('slug', $agent?->slug ?? Str::slug($name)));
        if ($slug === '') {
            $slug = Str::slug($name);
        }

        $slugQuery = Agent::query()->where('slug', $slug);
        if (! $isCreating && $agent) {
            $slugQuery->whereKeyNot($agent->id);
        }
        if ($slugQuery->exists()) {
            return ['error' => "Error: An agent with slug '{$slug}' already exists."];
        }

        $role = trim((string) $request->string('role', $agent?->role ?? ''));
        $instructions = trim((string) $request->string('instructions', $agent?->instructions ?? ''));

        $objective = trim((string) $request->string('objective', $agent?->objective ?? ''));
        $operationalProtocol = trim((string) $request->string('operational_protocol', $agent?->operational_protocol ?? ''));
        $errorHandling = trim((string) $request->string('error_handling', $agent?->error_handling ?? ''));
        $description = trim((string) $request->string('description', $agent?->description ?? ''));

        $prioritize = $this->resolveArrayInput($request, 'prioritize', $agent?->prioritize ?? []);
        $constraints = $this->resolveArrayInput($request, 'constraints', $agent?->constraints ?? []);
        $conditionalRules = $this->resolveArrayInput($request, 'conditional_rules', $agent?->conditional_rules ?? []);
        $outputFormat = $this->resolveArrayInput($request, 'output_format', $agent?->output_format ?? []);
        $examples = $this->resolveArrayInput($request, 'examples', $agent?->examples ?? []);

        $isPublic = $request->has('is_public') ? $request->boolean('is_public') : ($agent?->is_public ?? true);
        $isActive = $request->has('is_active') ? $request->boolean('is_active') : ($agent?->is_active ?? true);
        $allowHumanIntervention = $request->has('allow_human_intervention') ? $request->boolean('allow_human_intervention') : ($agent?->allow_human_intervention ?? false);
        $allowMemoryAnalysis = $request->has('allow_memory_analysis') ? $request->boolean('allow_memory_analysis') : ($agent?->allow_memory_analysis ?? true);
        $isCautiousConstraintEnabled = $request->has('is_cautious_constraint_enabled') ? $request->boolean('is_cautious_constraint_enabled') : ($agent?->is_cautious_constraint_enabled ?? true);

        [$provider, $providerModel] = $this->resolveProviderAndModel($request);
        if (! $provider && ! $providerModel) {
            [$provider, $providerModel] = $isCreating
                ? $this->resolveDefaultProviderAndModel()
                : [null, null];
        }

        $config = $currentConfig;
        if ($provider) {
            $config['provider_id'] = $provider->id;
        }
        if ($providerModel) {
            $config['provider_model_id'] = $providerModel->id;
        }

        if ($request->has('temperature')) {
            $temperatureValue = trim((string) $request->string('temperature'));
            if ($temperatureValue === '') {
                unset($config['temperature']);
            } else {
                $temperature = (float) $temperatureValue;
                if ($temperature < 0 || $temperature > 2) {
                    return ['error' => 'Error: "temperature" must be between 0 and 2.'];
                }
                $config['temperature'] = $temperature;
            }
        } elseif ($isCreating) {
            $temperatureValue = trim((string) $request->string('temperature'));
            if ($temperatureValue !== '') {
                $temperature = (float) $temperatureValue;
                if ($temperature < 0 || $temperature > 2) {
                    return ['error' => 'Error: "temperature" must be between 0 and 2.'];
                }
                $config['temperature'] = $temperature;
            }
        }

        if ($request->has('top_p')) {
            $topPValue = trim((string) $request->string('top_p'));
            if ($topPValue === '') {
                unset($config['top_p']);
            } else {
                $topP = (float) $topPValue;
                if ($topP < 0 || $topP > 1) {
                    return ['error' => 'Error: "top_p" must be between 0 and 1.'];
                }
                $config['top_p'] = $topP;
            }
        } elseif ($isCreating) {
            $topPValue = trim((string) $request->string('top_p'));
            if ($topPValue !== '') {
                $topP = (float) $topPValue;
                if ($topP < 0 || $topP > 1) {
                    return ['error' => 'Error: "top_p" must be between 0 and 1.'];
                }
                $config['top_p'] = $topP;
            }
        }

        if ($request->has('provider_model_suffix')) {
            $providerModelSuffix = trim((string) $request->string('provider_model_suffix'));
            if ($providerModelSuffix === '') {
                unset($config['provider_model_suffix']);
            } else {
                $config['provider_model_suffix'] = $providerModelSuffix;
            }
        } elseif ($isCreating) {
            $providerModelSuffix = trim((string) $request->string('provider_model_suffix'));
            if ($providerModelSuffix !== '') {
                $config['provider_model_suffix'] = $providerModelSuffix;
            }
        }

        $languagePolicyEnabled = $request->has('language_policy_enabled')
            ? $request->boolean('language_policy_enabled')
            : ($isCreating ? false : (bool) data_get($agent?->prompt_settings, 'parts.language_policy', false));
        $promptSettings = [
            'parts' => [
                'language_policy' => $languagePolicyEnabled,
            ],
            'tools' => data_get($agent?->prompt_settings, 'tools', []),
        ];

        // Flexible resolution: accept IDs or names for tools/skills.
        $toolIdsProvided = array_values(array_unique($this->resolveToolIds($request, $agent)));
        $skillIdsProvided = array_values(array_unique($this->resolveSkillIds($request, $agent)));

        $validToolIds = Tool::query()->whereIn('id', $toolIdsProvided)->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        if (count($toolIdsProvided) !== count($validToolIds)) {
            $missing = array_diff($toolIdsProvided, $validToolIds);

            return ['error' => 'Error: One or more `tool_ids` do not exist or are invalid: '.implode(', ', $missing).'. Please use numeric IDs from discover_tools.'];
        }

        $validSkillIds = AgentSkill::query()->whereIn('id', $skillIdsProvided)->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        if (count($skillIdsProvided) !== count($validSkillIds)) {
            $missing = array_diff($skillIdsProvided, $validSkillIds);

            return ['error' => 'Error: One or more `skill_ids` do not exist or are invalid: '.implode(', ', $missing).'. Please use numeric IDs from discover_skills.'];
        }

        $projectId = $request->has('project_id')
            ? ($request->integer('project_id') ?: null)
            : ($agent?->project_id ?? null);

        if ($projectId && ! Project::query()->where('id', $projectId)->exists()) {
            return ['error' => "Error: Project with ID {$projectId} does not exist. Please use discover_projects to find valid project IDs."];
        }

        $payload = [
            'user_id' => $agent?->user_id ?? auth()->id(),
            'project_id' => $projectId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'role' => $role,
            'objective' => $objective,
            'instructions' => $instructions,
            'operational_protocol' => $operationalProtocol,
            'prioritize' => array_values($prioritize),
            'constraints' => array_values($constraints),
            'conditional_rules' => array_values($conditionalRules),
            'error_handling' => $errorHandling,
            'output_format' => array_values($outputFormat),
            'examples' => array_values($examples),
            'is_cautious_constraint_enabled' => $isCautiousConstraintEnabled,
            'is_active' => $isActive,
            'is_public' => $isPublic,
            'allow_human_intervention' => $allowHumanIntervention,
            'allow_memory_analysis' => $allowMemoryAnalysis,
            'config' => $config,
            'prompt_settings' => $promptSettings,
            'max_usd_limit' => $this->resolveNullableFloat($request, 'max_usd_limit', $agent?->max_usd_limit),
            'limit_period' => $this->resolveNullableString($request, 'limit_period', $agent?->limit_period),
            'is_hard_limit' => $request->has('is_hard_limit') ? $request->boolean('is_hard_limit') : ($agent?->is_hard_limit ?? false),
            'max_tasks_per_period' => $this->resolveNullableInteger($request, 'max_tasks_per_period', $agent?->max_tasks_per_period),
            'task_limit_period' => $this->resolveNullableString($request, 'task_limit_period', $agent?->task_limit_period),
            'limit_starts_at' => $this->resolveNullableString($request, 'limit_starts_at', $agent?->limit_starts_at),
            'task_limit_starts_at' => $this->resolveNullableString($request, 'task_limit_starts_at', $agent?->task_limit_starts_at),
        ];

        return [
            'payload' => $payload,
            'tool_ids' => $this->resolveAllToolIds($validToolIds, $validSkillIds),
            'skill_ids' => $validSkillIds,
        ];
    }

    private function syncAgentRelations(Agent $agent, array $toolIds, array $skillIds): void
    {
        $agent->tools()->sync($toolIds);
        $agent->skills()->sync($skillIds);
    }

    private function resolveAllToolIds(array $validToolIds, array $validSkillIds): array
    {
        $toolIdsFromSkills = [];
        if (! empty($validSkillIds)) {
            $skills = AgentSkill::query()->whereIn('id', $validSkillIds)->get();
            foreach ($skills as $skill) {
                if (! empty($skill->tools)) {
                    $ids = Tool::query()->whereIn('class_name', $skill->tools)->pluck('id')->toArray();
                    $toolIdsFromSkills = array_merge($toolIdsFromSkills, array_map(fn ($id) => (int) $id, $ids));
                }
            }
        }

        return collect(array_merge($validToolIds, $toolIdsFromSkills))->map(fn ($id) => (int) $id)->unique()->values()->toArray();
    }

    private function resolveToolIds(Request $request, ?Agent $agent): array
    {
        // If neither field is present, keep existing tools.
        if (! $request->has('tool_ids') && ! $request->has('tools')) {
            return $agent?->tools()->pluck('tools.id')->toArray() ?? [];
        }

        // Otherwise, we are setting a new list (REPLACE).
        $ids = (array) $request->array('tool_ids');
        $tools = (array) $request->array('tools');

        $allInputs = array_merge($ids, $tools);

        Log::info('Agentoom: Tool resolution inputs (IDs only)', ['inputs' => $allInputs]);

        return $this->normalizeIds($allInputs);
    }

    private function resolveSkillIds(Request $request, ?Agent $agent): array
    {
        // If neither field is present, keep existing skills.
        if (! $request->has('skill_ids') && ! $request->has('skills')) {
            return $agent?->skills()->pluck('agent_skills.id')->toArray() ?? [];
        }

        // Otherwise, we are setting a new list (REPLACE).
        $ids = (array) $request->array('skill_ids');
        $skills = (array) $request->array('skills');

        $allInputs = array_merge($ids, $skills);

        Log::info('Agentoom: Skill resolution inputs (IDs only)', ['inputs' => $allInputs]);

        return $this->normalizeIds($allInputs);
    }

    private function normalizeIds(array $inputs): array
    {
        $normalized = [];
        foreach ($inputs as $val) {
            if ($val === null || (is_string($val) && trim($val) === '')) {
                continue;
            }

            if (is_array($val)) {
                $normalized = array_merge($normalized, $this->normalizeIds($val));

                continue;
            }

            if (is_string($val)) {
                $val = trim($val);

                // Handle JSON array string like "[17, 22]"
                if (str_starts_with($val, '[') && str_ends_with($val, ']')) {
                    $decoded = json_decode($val, true);
                    if (is_array($decoded)) {
                        $normalized = array_merge($normalized, $this->normalizeIds($decoded));

                        continue;
                    }
                }

                // Handle comma-separated string like "17, 22"
                if (str_contains($val, ',')) {
                    $parts = explode(',', $val);
                    $normalized = array_merge($normalized, $this->normalizeIds($parts));

                    continue;
                }
            }

            if (is_numeric($val) && (int) $val > 0) {
                $normalized[] = (int) $val;
            } else {
                $normalized[] = $val;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function resolveArrayInput(Request $request, string $key, array $default): array
    {
        if (! $request->has($key)) {
            return $default;
        }

        $input = $request->all()[$key] ?? null;

        if (is_array($input)) {
            return $input;
        }

        if (is_string($input)) {
            $input = trim($input);

            // Handle JSON array string
            if (str_starts_with($input, '[') && str_ends_with($input, ']')) {
                $decoded = json_decode($input, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            // Handle comma-separated string
            if (str_contains($input, ',')) {
                return array_map('trim', explode(',', $input));
            }

            if ($input === '') {
                return [];
            }

            return [$input];
        }

        return (array) ($request->array($key, []));
    }

    private function resolveNullableString(Request $request, string $key, mixed $default): ?string
    {
        if (! $request->has($key)) {
            return $default !== null ? (string) $default : null;
        }

        $value = trim((string) $request->string($key));

        return $value !== '' ? $value : null;
    }

    private function resolveNullableInteger(Request $request, string $key, mixed $default): ?int
    {
        if (! $request->has($key)) {
            return $default !== null ? (int) $default : null;
        }

        $value = trim((string) $request->string($key));
        if ($value === '') {
            return null;
        }

        return (int) $value;
    }

    private function resolveNullableFloat(Request $request, string $key, mixed $default): ?float
    {
        if (! $request->has($key)) {
            return $default !== null ? (float) $default : null;
        }

        $value = trim((string) $request->string($key));
        if ($value === '') {
            return null;
        }

        return (float) $value;
    }

    private function resolveProviderAndModel(Request $request): array
    {
        $provider = null;
        $providerModel = null;

        $providerId = $request->integer('provider_id');
        if ($providerId > 0) {
            $provider = Provider::query()->find($providerId);
        }

        if (! $provider && trim((string) $request->string('provider_name')) !== '') {
            $provider = Provider::query()->where('name', (string) $request->string('provider_name'))->first();
        }

        $providerModelId = $request->integer('provider_model_id');
        if ($providerModelId > 0) {
            $providerModel = ProviderModel::query()->find($providerModelId);
        }

        if (! $providerModel && trim((string) $request->string('provider_model_name')) !== '') {
            $providerModel = ProviderModel::query()->where('model_name', (string) $request->string('provider_model_name'))->first();
        }

        if ($providerModel && ! $provider) {
            $provider = $providerModel->provider;
        }

        if ($provider && $providerModel && (int) $provider->id !== (int) $providerModel->provider_id) {
            return [null, null];
        }

        return [$provider, $providerModel];
    }

    private function resolveDefaultProviderAndModel(): array
    {
        $provider = null;
        $providerModel = null;

        $providerValue = Configuration::getValue('ai_provider');
        if ($providerValue) {
            $provider = is_numeric($providerValue)
                ? Provider::query()->find((int) $providerValue)
                : Provider::query()->where('name', (string) $providerValue)->first();
        }

        $modelValue = Configuration::getValue('ai_model');
        if ($modelValue) {
            $providerModel = is_numeric($modelValue)
                ? ProviderModel::query()->find((int) $modelValue)
                : ProviderModel::query()->where('model_name', (string) $modelValue)->first();
        }

        if ($providerModel && ! $provider) {
            $provider = $providerModel->provider;
        }

        return [$provider, $providerModel];
    }

    private function buildSectionSummary(Agent $agent): string
    {
        $sections = [
            '[ROLE]' => ! empty($agent->role),
            '[OBJECTIVE]' => ! empty($agent->objective),
            '[INSTRUCTIONS]' => ! empty($agent->instructions),
            '[OPERATIONAL PROTOCOL]' => ! empty($agent->operational_protocol),
            '[PRIORITIZE]' => ! empty($agent->prioritize),
            '[CONSTRAINTS]' => ! empty($agent->constraints) || $agent->is_cautious_constraint_enabled,
            '[CONDITIONAL RULES]' => ! empty($agent->conditional_rules),
            '[ERROR HANDLING]' => ! empty($agent->error_handling),
            '[OUTPUT FORMAT]' => ! empty($agent->output_format),
            '[EXAMPLES]' => ! empty($agent->examples),
        ];

        $enabled = collect($sections)->filter()->keys()->implode(', ');

        return 'Prompt sections configured: '.($enabled !== '' ? $enabled : 'none');
    }
}
