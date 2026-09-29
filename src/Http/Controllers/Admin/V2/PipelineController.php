<?php

namespace Agentoom\Core\Http\Controllers\Admin\V2;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Services\ActionDiscoveryService;
use Agentoom\Core\Services\ResourceLimitManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class PipelineController extends Controller
{
    public function edit(?Pipeline $pipeline = null)
    {
        $actionDiscovery = app(ActionDiscoveryService::class);

        $payload = [
            'pipeline' => $pipeline?->exists ? $this->loadPipelineData($pipeline) : null,
            'projects' => $this->loadProjects(),
            'agents' => $this->loadAgents($pipeline?->project_id),
            'actionableMethods' => $this->loadActionableMethods($actionDiscovery),
            'steps' => $this->loadSteps($pipeline),
            'variables' => $pipeline?->config['variables'] ?? [],
            'failureStep' => $this->loadFailureStep($pipeline),
            'initialDiagrams' => $pipeline?->config['diagram'] ?? null,
            'translations' => $this->getTranslations(),
            'endpoint' => $pipeline?->exists ? $this->loadEndpointForPipeline($pipeline) : null,
        ];

        return Inertia::render('Admin/V2/PipelineCreateEdit', $payload);
    }

    public function create()
    {
        return $this->edit(null);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePipeline($request);

        $pipeline = new Pipeline;
        $this->savePipeline($pipeline, $validated);

        session()->flash('success', __('agentoom-core::admin.pipelines.saved_success'));

        if ($request->boolean('stay')) {
            return redirect()->route('admin.pipelines.edit', $pipeline);
        }

        return Inertia::location(route('admin.pipelines.index'));
    }

    public function update(Request $request, Pipeline $pipeline)
    {
        $validated = $this->validatePipeline($request, $pipeline);

        $this->savePipeline($pipeline, $validated);

        session()->flash('success', __('agentoom-core::admin.pipelines.saved_success'));

        if ($request->boolean('stay')) {
            return redirect()->route('admin.pipelines.edit', $pipeline);
        }

        return Inertia::location(route('admin.pipelines.index'));
    }

    public function clone(Pipeline $pipeline)
    {
        $newPipeline = $pipeline->replicate();
        $newPipeline->name = $pipeline->name.' (Copy)';
        $newPipeline->save();

        foreach ($pipeline->steps as $step) {
            $newStep = $step->replicate();
            $newStep->pipeline_id = $newPipeline->id;
            $newStep->save();
        }

        session()->flash('success', __('agentoom-core::admin.common.cloned_success'));

        return redirect()->route('admin.pipelines.edit', $newPipeline);
    }

    private function loadEndpointForPipeline(Pipeline $pipeline): ?array
    {
        if ($pipeline->type !== Pipeline::TYPE_ENDPOINT) {
            return null;
        }

        $endpoint = Endpoint::where('pipeline_id', $pipeline->id)->first();

        if (! $endpoint) {
            return null;
        }

        return [
            'id' => $endpoint->id,
            'name' => $endpoint->name,
            'data_structure' => $endpoint->data_structure ?? [],
        ];
    }

    private function loadPipelineData(Pipeline $pipeline): array
    {
        return [
            'id' => $pipeline->id,
            'name' => $pipeline->name,
            'type' => $pipeline->type ?? 'standard',
            'description' => $pipeline->description,
            'project_id' => $pipeline->project_id,
            'is_active' => (bool) ($pipeline->is_active ?? true),
            'allow_memory_analysis' => (bool) ($pipeline->allow_memory_analysis ?? false),
            'concurrency_lock' => (bool) ($pipeline->concurrency_lock ?? false),
            'config' => $pipeline->config ?? [],
        ];
    }

    private function loadProjects(): array
    {
        return Project::orderBy('name')->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
        ])->toArray();
    }

    private function loadAgents(?int $projectId = null): array
    {
        $query = Agent::where('is_active', true);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return $query->orderBy('name')->get()->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'project_id' => $a->project_id,
        ])->toArray();
    }

    private function loadActionableMethods(ActionDiscoveryService $actionDiscovery): array
    {
        $methods = $actionDiscovery->discover();

        uasort($methods, function ($a, $b) {
            $categoryA = $a['category'] ?? 'General';
            $categoryB = $b['category'] ?? 'General';
            $nameA = $a['name'] ?? '';
            $nameB = $b['name'] ?? '';

            if ($categoryA !== $categoryB) {
                return strcmp($categoryA, $categoryB);
            }

            return strcmp($nameA, $nameB);
        });

        return array_map(function ($key, $data) {
            return [
                'key' => $key,
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'category' => $data['category'] ?? 'General',
                'class' => $data['class'],
                'method' => $data['method'],
                'mcp_actionable_id' => $data['mcp_actionable_id'] ?? null,
                'parameters' => $data['parameters'] ?? [],
                'source' => $data['source'] ?? 'local',
            ];
        }, array_keys($methods), $methods);
    }

    private function loadSteps(?Pipeline $pipeline): array
    {
        if (! $pipeline?->exists) {
            return [];
        }

        $rawSteps = $pipeline->steps()
            ->with('agent')
            ->orderBy('order')
            ->get();

        $indexToUid = [];
        foreach ($rawSteps as $s) {
            $indexToUid[$s->order] = uniqid('step_');
        }

        return $rawSteps->map(function (PipelineStep $s) use ($indexToUid) {
            $selectedOutputs = $s->selected_outputs ?? [];
            $passOutputAsInput = $s->pass_output_as_input;
            $passAllOutputs = $s->pass_all_previous_outputs;

            if (empty($selectedOutputs)) {
                if ($passAllOutputs) {
                    $selectedOutputs = $s->order > 0 ? range(0, $s->order - 1) : [];
                } elseif ($passOutputAsInput && $s->order > 0) {
                    $selectedOutputs = [$s->order - 1];
                }
            }

            $uidSelectedOutputs = [];
            foreach ($selectedOutputs as $idx) {
                if (isset($indexToUid[$idx])) {
                    $uidSelectedOutputs[] = $indexToUid[$idx];
                }
            }

            return [
                'uid' => $indexToUid[$s->order],
                'id' => $s->id,
                'type' => ($s->method || $s->mcp_actionable_id) ? 'custom' : ($s->agent_id ? 'agent' : 'receive_data'),
                'agent_id' => $s->agent_id,
                'class' => $s->class,
                'method' => $s->method,
                'mcp_actionable_id' => $s->mcp_actionable_id,
                'method_key' => $s->mcp_actionable_id ? "mcp:{$s->mcp_actionable_id}" : ($s->method ? "{$s->class}@{$s->method}" : ''),
                'order' => $s->order,
                'name' => $s->name ?? '',
                'instructions_override' => $this->stripCacheBuster($s->instructions_override ?? ''),
                'pass_output_as_input' => false,
                'pass_all_previous_outputs' => false,
                'selected_outputs' => $uidSelectedOutputs,
                'condition_if' => $s->condition_if ?? '',
                'next_step_on_true' => $indexToUid[$s->next_step_on_true] ?? null,
                'next_step_on_false' => $indexToUid[$s->next_step_on_false] ?? null,
                'on_error' => $s->on_error ?? 'continue',
                'next_step_on_error' => $indexToUid[$s->next_step_on_error] ?? null,
                'retry_attempts' => $s->retry_attempts ?? 0,
                'retry_delay' => $s->retry_delay ?? 0,
                'condition_on_previous' => (bool) ($s->condition_on_previous ?? false),
                'condition_on_input' => (bool) ($s->condition_on_input ?? false),
                'condition_on_current' => (bool) ($s->condition_on_current ?? false),
                'is_active' => $s->is_active,
                'force_json' => (bool) ($s->force_json ?? false),
            ];
        })->values()->toArray();
    }

    private function loadFailureStep(?Pipeline $pipeline): ?array
    {
        if (! $pipeline?->exists) {
            return null;
        }

        $failureStep = $pipeline->failureStep()->with('agent')->first();

        if (! $failureStep) {
            return null;
        }

        return [
            'uid' => uniqid('step_'),
            'id' => $failureStep->id,
            'type' => ($failureStep->method || $failureStep->mcp_actionable_id) ? 'custom' : ($failureStep->agent_id ? 'agent' : 'receive_data'),
            'agent_id' => $failureStep->agent_id,
            'class' => $failureStep->class,
            'method' => $failureStep->method,
            'mcp_actionable_id' => $failureStep->mcp_actionable_id,
            'method_key' => $failureStep->mcp_actionable_id ? "mcp:{$failureStep->mcp_actionable_id}" : ($failureStep->method ? "{$failureStep->class}@{$failureStep->method}" : ''),
            'name' => $failureStep->name ?? '',
            'instructions_override' => $this->stripCacheBuster($failureStep->instructions_override ?? ''),
            'on_error' => $failureStep->on_error ?? 'continue',
            'retry_attempts' => $failureStep->retry_attempts ?? 0,
            'retry_delay' => $failureStep->retry_delay ?? 0,
            'is_active' => $failureStep->is_active,
            'force_json' => (bool) ($failureStep->force_json ?? false),
        ];
    }

    private function stripCacheBuster(string $instructions): string
    {
        if (empty($instructions)) {
            return '';
        }

        $decoded = json_decode($instructions, true);
        $isJson = json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded));

        if ($isJson) {
            if (is_array($decoded) && isset($decoded[0]) && is_array($decoded[0]) && isset($decoded[0]['name'])) {
                if ($decoded[0]['name'] === '_cache_buster') {
                    array_shift($decoded);

                    return json_encode($decoded);
                }
            } elseif (is_array($decoded) && ! array_is_list($decoded)) {
                if (isset($decoded['_cache_buster'])) {
                    unset($decoded['_cache_buster']);

                    return json_encode($decoded);
                }
            }
        } else {
            return preg_replace('/^(\[STEP NUMBER \d+ saved on .*?\]\s*)+/', '', $instructions);
        }

        return $instructions;
    }

    private function getTranslations(): array
    {
        return [
            'edit' => __('agentoom-core::admin.pipelines.edit'),
            'create' => __('agentoom-core::admin.pipelines.create'),
            'new' => __('agentoom-core::admin.pipelines.new'),
            'back' => __('agentoom-core::admin.common.back'),
            'form_editor' => 'FORM EDITOR',
            'diagram_editor' => 'NEW PIPELINE EDITOR',
            'active' => __('agentoom-core::admin.common.active'),
            'inactive' => __('agentoom-core::admin.common.inactive'),
            'analyze' => __('agentoom-core::admin.common.analyze'),
            'ignore' => __('agentoom-core::admin.common.ignore'),
            'project' => __('agentoom-core::admin.projects.title'),
            'no_project' => __('agentoom-core::admin.common.no_project'),
            'no_project_warning' => __('agentoom-core::admin.common.no_project_warning'),
            'name' => __('agentoom-core::admin.pipelines.name'),
            'type_standard' => __('agentoom-core::admin.pipelines.type_standard'),
            'type_endpoint' => __('agentoom-core::admin.pipelines.type_endpoint'),
            'type_standard_help' => __('agentoom-core::admin.pipelines.type_standard_help'),
            'type_endpoint_help' => __('agentoom-core::admin.pipelines.type_endpoint_help'),
            'details' => __('agentoom-core::admin.pipelines.details'),
            'variables' => __('agentoom-core::admin.pipelines.variables'),
            'variables_subtitle' => __('agentoom-core::admin.pipelines.variables_subtitle'),
            'add_variable' => __('agentoom-core::admin.pipelines.add_variable'),
            'no_variables_hint' => __('agentoom-core::admin.pipelines.no_variables_hint'),
            'variable_key' => __('agentoom-core::admin.pipelines.variable_key'),
            'variable_value' => __('agentoom-core::admin.pipelines.variable_value'),
            'variables_help_title' => __('agentoom-core::admin.pipelines.variables_help_title'),
            'help_instructions' => __('agentoom-core::admin.pipelines.help_instructions'),
            'variables_help_text' => __('agentoom-core::admin.pipelines.variables_help_text'),
            'placeholders_help_title' => __('agentoom-core::admin.pipelines.placeholders_help_title'),
            'placeholders_help_text' => __('agentoom-core::admin.pipelines.placeholders_help_text'),
            'step' => __('agentoom-core::admin.pipelines.step'),
            'steps' => __('agentoom-core::admin.pipelines.steps'),
            'select_all' => __('agentoom-core::admin.common.select_all'),
            'delete' => __('agentoom-core::admin.common.delete'),
            'move' => __('agentoom-core::admin.common.move'),
            'clone_and_move' => __('agentoom-core::admin.common.clone_and_move'),
            'add_step' => __('agentoom-core::admin.pipelines.add_step'),
            'no_steps_hint' => __('agentoom-core::admin.pipelines.no_steps_hint'),
            'step_name' => __('agentoom-core::admin.pipelines.step_name'),
            'step_name_placeholder' => __('agentoom-core::admin.pipelines.step_name_placeholder'),
            'agent' => __('agentoom-core::admin.pipelines.agent'),
            'select_agent' => __('agentoom-core::admin.pipelines.select_agent'),
            'select' => __('agentoom-core::admin.common.select'),
            'method' => __('agentoom-core::admin.schedules.method'),
            'target_type' => __('agentoom-core::admin.schedules.target_type'),
            'receive_data' => __('agentoom-core::admin.endpoints.receive_data'),
            'receive_data_hint' => __('agentoom-core::admin.endpoints.receive_data_hint'),
            'custom_method' => __('agentoom-core::admin.schedules.custom_method'),
            'pass_output' => __('agentoom-core::admin.pipelines.pass_output'),
            'pass_output_hint' => __('agentoom-core::admin.pipelines.pass_output_hint'),
            'select_outputs' => __('Select Previous Outputs'),
            'select_outputs_description' => __('Choose which previous steps outputs to prepend to this step.'),
            'instructions_override' => __('agentoom-core::admin.pipelines.instructions_override'),
            'instructions_override_placeholder' => __('agentoom-core::admin.pipelines.instructions_override_placeholder'),
            'parameters' => __('agentoom-core::admin.schedules.parameters'),
            'conditional_logic' => __('agentoom-core::admin.pipelines.conditional_logic'),
            'condition_if' => __('agentoom-core::admin.pipelines.condition_if'),
            'condition_if_placeholder' => __('agentoom-core::admin.pipelines.condition_if_placeholder'),
            'condition_if_help' => __('agentoom-core::admin.pipelines.condition_if_help'),
            'next_step_on_true' => __('agentoom-core::admin.pipelines.next_step_on_true'),
            'next_step_on_false' => __('agentoom-core::admin.pipelines.next_step_on_false'),
            'next_step_default' => __('agentoom-core::admin.pipelines.next_step_default'),
            'on_error' => __('agentoom-core::admin.pipelines.on_error'),
            'on_error_fail' => __('agentoom-core::admin.pipelines.on_error_fail'),
            'on_error_continue' => __('agentoom-core::admin.pipelines.on_error_continue'),
            'on_error_retry' => __('agentoom-core::admin.pipelines.on_error_retry'),
            'on_error_jump' => __('agentoom-core::admin.pipelines.on_error_jump'),
            'on_error_help' => __('agentoom-core::admin.pipelines.on_error_help'),
            'retry_attempts' => __('agentoom-core::admin.pipelines.retry_attempts'),
            'retry_delay' => __('agentoom-core::admin.pipelines.retry_delay'),
            'next_step_on_error' => __('agentoom-core::admin.pipelines.next_step_on_error'),
            'next_step_placeholder' => __('agentoom-core::admin.pipelines.next_step_placeholder'),
            'failure_handler' => __('agentoom-core::admin.pipelines.failure_handler'),
            'failure_handler_subtitle' => __('agentoom-core::admin.pipelines.failure_handler_subtitle'),
            'failure_handler_disabled_hint' => __('agentoom-core::admin.pipelines.failure_handler_disabled_hint'),
            'concurrency_lock' => __('agentoom-core::admin.pipelines.concurrency_lock'),
            'concurrency_lock_enabled' => __('agentoom-core::admin.pipelines.concurrency_lock_enabled'),
            'concurrency_lock_disabled' => __('agentoom-core::admin.pipelines.concurrency_lock_disabled'),
            'concurrency_lock_help' => __('agentoom-core::admin.pipelines.concurrency_lock_help'),
            'allow_memory_analysis' => __('agentoom-core::admin.common.allow_memory_analysis'),
            'cancel' => __('agentoom-core::admin.common.cancel'),
            'save' => __('agentoom-core::admin.common.save'),
            'save_and_stay' => __('agentoom-core::admin.common.save_and_stay'),
            'processing' => __('agentoom-core::admin.common.processing'),
            'clone' => __('agentoom-core::admin.common.clone'),
            'move_step' => __('agentoom-core::admin.pipelines.move_step'),
            'clone_and_move_step' => __('agentoom-core::admin.pipelines.clone_and_move_step'),
            'move_step_subtitle' => __('agentoom-core::admin.pipelines.move_step_subtitle'),
            'move_position' => __('agentoom-core::admin.pipelines.move_position'),
            'move_before' => __('agentoom-core::admin.pipelines.move_before'),
            'move_after' => __('agentoom-core::admin.pipelines.move_after'),
            'move_target' => __('agentoom-core::admin.pipelines.move_target'),
            'move_target_placeholder' => __('agentoom-core::admin.pipelines.move_target_placeholder'),
            'close' => __('agentoom-core::admin.common.close'),
            'fail_pipeline' => __('agentoom-core::admin.pipelines.on_error_fail'),
            'continue' => __('agentoom-core::admin.pipelines.on_error_continue'),
            'retry' => __('agentoom-core::admin.pipelines.on_error_retry'),
            'jump' => __('agentoom-core::admin.pipelines.on_error_jump'),
            'failure_handler_disabled' => __('agentoom-core::admin.pipelines.failure_handler_disabled_hint'),
            'select_step' => __('agentoom-core::admin.common.select'),
            'delay' => __('agentoom-core::admin.pipelines.retry_delay'),
            'is_active' => __('agentoom-core::admin.common.is_active'),
            'force_json' => __('agentoom-core::admin.endpoints.force_json'),
            'force_json_help' => __('agentoom-core::admin.endpoints.force_json_help'),
            'select_action' => __('agentoom-core::admin.pipelines.select_action'),
            'change' => __('agentoom-core::admin.common.change'),
            'outputs_selected' => __('agentoom-core::admin.pipelines.selected_outputs_count'),
            'evaluate_on' => __('agentoom-core::admin.pipelines.evaluate_on'),
            'previous_step' => __('agentoom-core::admin.pipelines.previous_step'),
            'pipeline_input' => __('agentoom-core::admin.pipelines.pipeline_input'),
            'current_step' => __('agentoom-core::admin.pipelines.current_step'),
            'no_parameters_defined' => __('agentoom-core::admin.pipelines.no_parameters_defined'),
            'add_parameter' => __('agentoom-core::admin.pipelines.add_parameter'),
            'param_name' => __('agentoom-core::admin.pipelines.param_name'),
            'param_value' => __('agentoom-core::admin.pipelines.param_value'),
            'direct_actions' => __('agentoom-core::admin.direct_actions'),
        ];
    }

    private function validatePipeline(Request $request, ?Pipeline $pipeline = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:standard,endpoint',
            'description' => 'nullable|string',
            'project_id' => 'nullable|exists:projects,id',
            'is_active' => 'required|integer|in:0,1',
            'allow_memory_analysis' => 'required|integer|in:0,1',
            'concurrency_lock' => 'required|integer|in:0,1',
            'steps' => 'array',
            'variables' => 'array',
            'failureStepEnabled' => 'boolean',
        ];

        $messages = [
            'name.required' => __('agentoom-core::admin.pipelines.name_required'),
            'name.max' => __('agentoom-core::admin.pipelines.name_max'),
            'type.in' => __('agentoom-core::admin.pipelines.type_invalid'),
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($pipeline && ! $pipeline->exists && app(ResourceLimitManager::class)->isLimitReached('pipelines')) {
            $validator->errors()->add('name', __('agentoom-core::admin.resource_limits.usage.limit_reached'));
        }

        $validator->validate();

        return $request->only([
            'name', 'type', 'description', 'project_id', 'is_active',
            'allow_memory_analysis', 'concurrency_lock', 'steps', 'variables',
        ]);
    }

    private function savePipeline(Pipeline $pipeline, array $data): void
    {
        $pipeline->fill([
            'name' => $data['name'],
            'type' => $data['type'],
            'description' => $data['description'] ?: null,
            'project_id' => $data['project_id'],
            'is_active' => $data['is_active'],
            'allow_memory_analysis' => $data['allow_memory_analysis'],
            'concurrency_lock' => $data['concurrency_lock'],
            'config' => array_merge($pipeline->config ?? [], ['variables' => array_values($data['variables'] ?? [])]),
        ])->save();

        $this->saveSteps($pipeline, $data['steps'] ?? []);
    }

    private function saveSteps(Pipeline $pipeline, array $steps): void
    {
        $keepIds = collect($steps)->pluck('id')->filter()->all();

        $pipeline->steps()->whereNotIn('id', $keepIds)->delete();

        $uidToIndex = [];
        foreach ($steps as $index => $stepData) {
            $uidToIndex[$stepData['uid']] = $index;
        }

        foreach ($steps as $index => $stepData) {
            $step = $stepData['id']
                ? PipelineStep::findOrFail($stepData['id'])
                : new PipelineStep(['pipeline_id' => $pipeline->id]);

            $selectedOutputs = [];
            if (isset($stepData['selected_outputs']) && is_array($stepData['selected_outputs'])) {
                foreach ($stepData['selected_outputs'] as $uid) {
                    if (isset($uidToIndex[$uid])) {
                        $selectedOutputs[] = $uidToIndex[$uid];
                    }
                }
            }
            sort($selectedOutputs);

            $nextTrue = $stepData['next_step_on_true'] ?? null;
            if ($nextTrue && isset($uidToIndex[$nextTrue])) {
                $nextTrue = $uidToIndex[$nextTrue];
            } else {
                $nextTrue = null;
            }

            $nextFalse = $stepData['next_step_on_false'] ?? null;
            if ($nextFalse && isset($uidToIndex[$nextFalse])) {
                $nextFalse = $uidToIndex[$nextFalse];
            } else {
                $nextFalse = null;
            }

            $nextError = $stepData['next_step_on_error'] ?? null;
            if ($nextError && isset($uidToIndex[$nextError])) {
                $nextError = $uidToIndex[$nextError];
            } else {
                $nextError = null;
            }

            $step->fill([
                'pipeline_id' => $pipeline->id,
                'agent_id' => ($stepData['type'] ?? 'agent') === 'agent' ? (($stepData['agent_id'] ?? null) ?: null) : null,
                'class' => ($stepData['type'] ?? 'agent') === 'custom' ? (($stepData['class'] ?? null) ?: null) : null,
                'method' => ($stepData['type'] ?? 'agent') === 'custom' ? (($stepData['method'] ?? null) ?: null) : null,
                'mcp_actionable_id' => ($stepData['type'] ?? 'agent') === 'custom' ? (($stepData['mcp_actionable_id'] ?? null) ?: null) : null,
                'order' => $index,
                'name' => ($stepData['name'] ?? null) ?: null,
                'instructions_override' => ($stepData['instructions_override'] ?? null) ?: null,
                'pass_output_as_input' => false,
                'pass_all_previous_outputs' => false,
                'selected_outputs' => $selectedOutputs,
                'condition_if' => ($stepData['condition_if'] ?? null) ?: null,
                'next_step_on_true' => $nextTrue,
                'next_step_on_false' => $nextFalse,
                'on_error' => $stepData['on_error'] ?? 'continue',
                'next_step_on_error' => $nextError,
                'retry_attempts' => $stepData['retry_attempts'] ?? 0,
                'retry_delay' => $stepData['retry_delay'] ?? 0,
                'condition_on_previous' => (bool) ($stepData['condition_on_previous'] ?? false),
                'condition_on_input' => (bool) ($stepData['condition_on_input'] ?? false),
                'condition_on_current' => (bool) ($stepData['condition_on_current'] ?? false),
                'is_active' => (bool) ($stepData['is_active'] ?? true),
                'force_json' => (bool) ($stepData['force_json'] ?? false),
            ])->save();
        }
    }
}
