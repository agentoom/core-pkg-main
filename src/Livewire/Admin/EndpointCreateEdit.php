<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Services\ResourceLimitManager;
use Illuminate\Support\Str;
use Livewire\Component;

class EndpointCreateEdit extends Component
{
    public ?Endpoint $endpoint = null;

    public string $name = '';

    public string $description = '';

    public string $prompt = '';

    public ?int $agent_id = null;

    public ?int $pipeline_id = null;

    public array $data_structure = [];

    public ?int $projectId = null;

    public int $is_active = 1;

    public int $allow_memory_analysis = 0;

    public bool $force_json = false;

    public string $allowed_sources = '';

    public int $auth_token_enabled = 0;

    public string $auth_token = '';

    public string $auth_token_header = 'X-Agentoom-Signature';

    public bool $show_auth_token = false;

    public bool $is_editing = false;

    public bool $show_structure_modal = false;

    public string $slug = '';

    public bool $direct_response_enabled = false;

    public string $direct_response_body = '';

    public int $direct_response_code = 200;

    public string $direct_response_content_type = 'application/json';

    public bool $decision_filter_enabled = false;

    public ?string $decision_filter_provider = null;

    public ?string $decision_filter_model = null;

    public string $decision_filter_instructions = '';

    public float $decision_filter_threshold = 0.80;

    public string $decision_filter_reject_action = 'skip_200';

    protected $listeners = ['json-structure-updated' => 'updateDataStructure'];

    public function mount(?Endpoint $endpoint = null): void
    {
        if ($endpoint?->exists) {
            $this->endpoint = $endpoint;
            $this->is_editing = true;
            $this->name = $endpoint->name;
            $this->slug = $endpoint->slug ?? '';
            $this->description = $endpoint->description ?? '';
            $this->prompt = $endpoint->prompt ?? '';
            $this->agent_id = $endpoint->agent_id;
            $this->pipeline_id = $endpoint->pipeline_id;
            $this->data_structure = $endpoint->data_structure ?? [];
            $this->is_active = $endpoint->is_active ? 1 : 0;
            $this->allow_memory_analysis = ($endpoint->allow_memory_analysis ?? false) ? 1 : 0;
            $this->force_json = (bool) ($endpoint->force_json ?? false);
            $this->projectId = $endpoint->project_id;
            $this->allowed_sources = implode(', ', $endpoint->allowed_sources ?? []);
            $this->auth_token_enabled = $endpoint->auth_token_enabled ? 1 : 0;
            $this->auth_token = $endpoint->auth_token ?? '';
            $this->auth_token_header = $endpoint->auth_token_header ?? 'X-Agentoom-Signature';
            $this->direct_response_enabled = (bool) ($endpoint->direct_response_enabled ?? false);
            $this->direct_response_body = $endpoint->direct_response_body ?? '';
            $this->direct_response_code = (int) ($endpoint->direct_response_code ?: 200);
            $this->direct_response_content_type = $endpoint->direct_response_content_type ?: 'application/json';
            $this->decision_filter_enabled = (bool) ($endpoint->decision_filter_enabled ?? false);
            $this->decision_filter_provider = $endpoint->decision_filter_provider ?? null;
            $this->decision_filter_model = $endpoint->decision_filter_model ?? null;
            $this->decision_filter_instructions = $endpoint->decision_filter_instructions ?? '';
            $this->decision_filter_threshold = (float) ($endpoint->decision_filter_threshold ?? 0.80);
            $this->decision_filter_reject_action = $endpoint->decision_filter_reject_action ?: 'skip_200';
        } else {
            $this->endpoint = new Endpoint;
            $this->is_editing = false;
        }
    }

    public function updateDataStructure(array $fields): void
    {
        $this->data_structure = $fields;
        $this->show_structure_modal = false;
    }

    public function updatedAgentId($value): void
    {
        if ($value) {
            $this->pipeline_id = null;
        }
    }

    public function updatedPipelineId($value): void
    {
        if ($value) {
            $this->agent_id = null;
        }
    }

    public function save(bool $stay = false)
    {
        if (! $this->is_editing && app(ResourceLimitManager::class)->isLimitReached('endpoints')) {
            $this->addError('name', __('agentoom-core::admin.resource_limits.usage.limit_reached'));

            return;
        }

        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|unique:endpoints,slug'.($this->is_editing ? ','.$this->endpoint->id : ''),
            'description' => 'nullable|string',
            'agent_id' => 'required_without:pipeline_id|nullable|exists:agents,id',
            'pipeline_id' => 'required_without:agent_id|nullable|exists:pipelines,id',
            'data_structure' => 'nullable|array',
            'projectId' => 'nullable|exists:projects,id',
            'is_active' => 'boolean',
            'allowed_sources' => 'nullable|string',
            'auth_token_enabled' => 'boolean',
            'auth_token' => 'nullable|string|max:64',
            'auth_token_header' => 'nullable|string|max:255',
            'direct_response_enabled' => 'boolean',
            'direct_response_body' => 'nullable|string',
            'direct_response_code' => 'nullable|integer|min:100|max:599',
            'direct_response_content_type' => 'nullable|string|max:255',
            'decision_filter_enabled' => 'boolean',
            'decision_filter_provider' => 'nullable|string|max:100',
            'decision_filter_model' => 'nullable|string|max:255',
            'decision_filter_instructions' => 'nullable|string',
            'decision_filter_threshold' => 'nullable|numeric|min:0|max:1',
            'decision_filter_reject_action' => 'nullable|string|max:50',
        ]);

        $data = [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'prompt' => $this->prompt,
            'agent_id' => $this->agent_id ?: null,
            'pipeline_id' => $this->pipeline_id ?: null,
            'project_id' => $this->projectId,
            'allow_memory_analysis' => $this->allow_memory_analysis,
            'force_json' => $this->force_json,
            'data_structure' => empty($this->data_structure) ? null : $this->data_structure,
            'is_active' => $this->is_active,
            'allowed_sources' => array_filter(array_map('trim', explode(',', $this->allowed_sources))),
            'auth_token_enabled' => $this->auth_token_enabled,
            'auth_token' => $this->auth_token_enabled ? $this->auth_token : null,
            'auth_token_header' => $this->auth_token_header,
            'direct_response_enabled' => $this->direct_response_enabled,
            'direct_response_body' => $this->direct_response_enabled ? $this->direct_response_body : null,
            'direct_response_code' => $this->direct_response_enabled ? $this->direct_response_code : null,
            'direct_response_content_type' => $this->direct_response_enabled ? $this->direct_response_content_type : null,
            'decision_filter_enabled' => $this->decision_filter_enabled,
            'decision_filter_provider' => $this->decision_filter_enabled ? $this->decision_filter_provider : null,
            'decision_filter_model' => $this->decision_filter_enabled ? $this->decision_filter_model : null,
            'decision_filter_instructions' => $this->decision_filter_enabled ? $this->decision_filter_instructions : null,
            'decision_filter_threshold' => $this->decision_filter_enabled ? $this->decision_filter_threshold : 0.80,
            'decision_filter_reject_action' => $this->decision_filter_enabled ? $this->decision_filter_reject_action : 'skip_200',
        ];

        if ($this->is_editing) {
            $this->endpoint->update($data);
            \Flux::toast(__('agentoom-core::admin.endpoints.updated_success'));
        } else {
            $this->endpoint = Endpoint::create($data);
            \Flux::toast(__('agentoom-core::admin.endpoints.created_success'));
        }

        if ($stay) {
            return $this->redirect(route('admin.endpoints.edit', $this->endpoint), navigate: true);
        }

        return $this->redirect(route('admin.endpoints.index'), navigate: true);
    }

    public function clone(): void
    {
        if (! $this->endpoint || ! $this->endpoint->exists) {
            return;
        }

        $newEndpoint = $this->endpoint->replicate();
        $newEndpoint->name = $this->endpoint->name.' (Clone)';
        $newEndpoint->slug = null;
        $newEndpoint->save();

        \Flux::toast(__('agentoom-core::admin.common.cloned_success'));

        $this->redirect(route('admin.endpoints.edit', $newEndpoint), navigate: true);
    }

    public function generateAuthToken(): void
    {
        $this->auth_token = Str::random(32);
    }

    public function regenerateAuthToken(): void
    {
        $this->auth_token = Str::random(32);
    }

    public function generateSlug(): void
    {
        $this->slug = Str::random(12);
    }

    public function render()
    {
        $agents = Agent::where('is_active', true)->where('system_agent', false)->orderBy('name')->get();
        $pipelines = Pipeline::where('is_active', true)
            ->where('type', Pipeline::TYPE_ENDPOINT)
            ->orderBy('name')
            ->get();

        $projects = Project::orderBy('name')->get();
        $providers = \Agentoom\Core\Models\Provider::all()->pluck('name', 'id');

        $defaultDecisionConfig = \Agentoom\Core\Services\InjectCurrentAiConfigService::getDecisionConfig();
        $providerName = $this->decision_filter_provider ?: ($defaultDecisionConfig['provider'] ?? null);

        $query = \Agentoom\Core\Models\ProviderModel::query()->where('is_active', true);
        if ($providerName) {
            $query->whereHas('provider', fn ($q) => is_numeric($providerName) ? $q->where('id', $providerName) : $q->where('name', $providerName));
        }

        $decisionModels = (clone $query)->where(function ($q) {
            $q->where('model_features->architecture->output_modalities', 'like', '%"decisions"%')
                ->orWhere('model_name', 'like', '%jev%')
                ->orWhere('model_name', 'like', '%decid%')
                ->orWhere('model_name', 'like', '%classify%');
        })->get();
        if ($decisionModels->isEmpty()) {
            $decisionModels = $query->get();
        }

        return view('agentoom-core::livewire.admin.endpoint-create-edit', [
            'agents' => $agents,
            'pipelines' => $pipelines,
            'projects' => $projects,
            'providers' => $providers,
            'decisionModels' => $decisionModels,
        ])->layout('agentoom-core::layouts.admin');
    }
}
