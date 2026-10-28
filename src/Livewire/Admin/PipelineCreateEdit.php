<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Services\ActionDiscoveryService;
use Agentoom\Core\Services\ResourceLimitManager;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Prop;
use Livewire\Attributes\Validate;
use Livewire\Component;

class PipelineCreateEdit extends Component
{
    #[Prop]
    public ?Pipeline $pipeline = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|in:standard,endpoint')]
    public string $type = 'standard';

    #[Validate('nullable|string')]
    public string $description = '';

    #[Validate('nullable|exists:projects,id')]
    public ?int $projectId = null;

    public int $is_active = 1;

    public int $allow_memory_analysis = 0;

    public int $concurrency_lock = 0;

    /** @var array<int, array<string, mixed>> */
    public array $steps = [];

    /** @var array<string, mixed>|null */
    public ?array $failureStep = null;

    public bool $failureStepEnabled = false;

    /** @var array<int, array<string, string>> */
    public array $variables = [];

    public array $expandedSteps = [];

    public bool $showDiagram = false;

    public bool $showMoveStepModal = false;

    public array $selectedSteps = [];

    public bool $allSelected = false;

    public bool $isBulkMove = false;

    public bool $isBulkCloneMove = false;

    public ?int $movingStepIndex = null;

    public ?int $targetStepIndex = null;

    public string $movePosition = 'before';

    public function toggleDiagram(): void
    {
        $this->showDiagram = ! $this->showDiagram;
    }

    public function toggleStep(string $uid): void
    {
        if (in_array($uid, $this->expandedSteps)) {
            $this->expandedSteps = array_values(array_diff($this->expandedSteps, [$uid]));
        } else {
            $this->expandedSteps[] = $uid;
        }
    }

    public function saveDiagram(array $nodes, array $edges, array $steps, array $variables = []): void
    {
        $this->steps = $steps;
        $this->variables = $variables;

        $this->reorderSteps();

        if ($this->pipeline) {
            $config = $this->pipeline->config ?? [];
            $config['diagram'] = [
                'nodes' => $nodes,
                'edges' => $edges,
            ];
            $this->pipeline->config = $config;
        }

        $this->save(stay: true);
        session()->flash('showDiagram', true);
    }

    public function getTranslations(): array
    {
        return [
            'pipeline_settings' => __('agentoom-core::admin.pipelines.details'),
            'variables' => __('agentoom-core::admin.pipelines.variables'),
            'variables_subtitle' => __('agentoom-core::admin.pipelines.variables_subtitle'),
            'add_variable' => __('agentoom-core::admin.pipelines.add_variable'),
            'no_variables' => __('agentoom-core::admin.pipelines.no_variables_hint'),
            'variable_key' => __('agentoom-core::admin.pipelines.variable_key'),
            'variable_value' => __('agentoom-core::admin.pipelines.variable_value'),
            'variables_help' => strip_tags(__('agentoom-core::admin.pipelines.variables_help_text')),
            'step_properties' => __('agentoom-core::admin.pipelines.step'),
            'step_name' => __('agentoom-core::admin.pipelines.step_name'),
            'step_name_placeholder' => __('agentoom-core::admin.pipelines.step_name_placeholder'),
            'agent' => __('agentoom-core::admin.pipelines.agent'),
            'select_agent' => __('agentoom-core::admin.pipelines.select_agent'),
            'select_outputs' => __('agentoom-core::admin.pipelines.select_outputs'),
            'selected_outputs_count' => __('agentoom-core::admin.pipelines.selected_outputs_count'),
            'custom_method' => __('agentoom-core::admin.schedules.custom_method'),
            'select_method' => __('agentoom-core::admin.direct_actions.select_title'),
            'pass_output' => __('agentoom-core::admin.pipelines.pass_output'),
            'pass_output_hint' => __('agentoom-core::admin.pipelines.pass_output_hint'),
            'pass_all_outputs' => __('agentoom-core::admin.pipelines.pass_all_outputs'),
            'pass_all_outputs_hint' => __('agentoom-core::admin.pipelines.pass_all_outputs_hint'),
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
            'delete_step' => __('agentoom-core::admin.common.delete') ?? 'Delete',
            'add_step' => __('agentoom-core::admin.pipelines.add_step'),
            'add' => __('agentoom-core::admin.common.add'),
            'save_diagram' => __('agentoom-core::admin.common.save'),
            'unnamed_agent' => __('agentoom-core::admin.pipelines.unnamed_agent'),
            'unnamed_method' => __('agentoom-core::admin.pipelines.unnamed_action'),
            'start' => __('agentoom-core::admin.pipelines.start_node'),
            'step' => __('agentoom-core::admin.pipelines.step'),
            'confirm_delete_step' => __('agentoom-core::admin.common.delete_confirmation_text') ?? 'Are you sure?',
            'select_node_hint' => __('agentoom-core::admin.pipelines.select_node_hint'),
            'learn_more_filters' => __('agentoom-core::admin.common.learn_more'),
            'filter_documentation' => __('agentoom-core::admin.common.documentation'),
            'filters_description' => __('agentoom-core::admin.pipelines.filters_description'),
            'learn_more_fields' => __('agentoom-core::admin.common.learn_more'),
            'fields_documentation' => __('agentoom-core::admin.common.documentation'),
            'fields_description' => __('agentoom-core::admin.pipelines.fields_description'),
            'resources_fields' => __('agentoom-core::admin.pipelines.resources_fields'),
            'scraped_data_fields' => __('agentoom-core::admin.pipelines.scraped_data_fields'),
            'fields_tip' => __('agentoom-core::admin.pipelines.fields_tip'),
            'learn_more_order_by' => __('agentoom-core::admin.common.learn_more'),
            'order_by_documentation' => __('agentoom-core::admin.common.documentation'),
            'order_by_description' => __('agentoom-core::admin.pipelines.order_by_description'),
            'order_by_tip' => __('agentoom-core::admin.pipelines.order_by_tip'),
            'data_flow' => __('agentoom-core::admin.pipelines.data_flow'),
            'no_parameters' => __('agentoom-core::admin.pipelines.no_parameters_defined'),
            'evaluate_on' => __('agentoom-core::admin.pipelines.evaluate_on'),
            'previous_step' => __('agentoom-core::admin.pipelines.previous_step'),
            'pipeline_input' => __('agentoom-core::admin.pipelines.pipeline_input'),
            'current_step' => __('agentoom-core::admin.pipelines.current_step'),
            'close' => __('agentoom-core::admin.common.close'),
            'change' => __('agentoom-core::admin.common.edit'),
            'select_action' => __('agentoom-core::admin.schedules.select_method'),
            'outputs_selected' => __('agentoom-core::admin.pipelines.selected_outputs_count'),
            'direct_actions' => [
                'select_title' => __('agentoom-core::admin.direct_actions.select_title'),
                'select_subtitle' => __('agentoom-core::admin.direct_actions.select_subtitle'),
                'search_placeholder' => __('agentoom-core::admin.direct_actions.search_placeholder'),
                'all_sources' => __('agentoom-core::admin.direct_actions.all_sources'),
                'local' => __('agentoom-core::admin.direct_actions.local'),
                'mcp_server' => __('agentoom-core::admin.direct_actions.mcp_server'),
                'external_mcp' => __('agentoom-core::admin.direct_actions.external_mcp'),
                'local_actions' => __('agentoom-core::admin.direct_actions.local_actions'),
                'no_actions_found' => __('agentoom-core::admin.direct_actions.no_actions_found'),
                'cancel' => __('agentoom-core::admin.direct_actions.cancel'),
            ],
        ];
    }

    public function mount(?Pipeline $pipeline = null): void
    {
        if (session()->get('showDiagram')) {
            $this->showDiagram = true;
        }

        if ($pipeline?->exists) {
            $this->pipeline = $pipeline;
            $this->name = $pipeline->name;
            $this->type = $pipeline->type ?? 'standard';
            $this->description = $pipeline->description ?? '';
            $this->projectId = $pipeline->project_id;
            $this->is_active = $pipeline->is_active ? 1 : 0;
            $this->allow_memory_analysis = ($pipeline->allow_memory_analysis ?? false) ? 1 : 0;
            $this->concurrency_lock = ($pipeline->concurrency_lock ?? false) ? 1 : 0;
            $this->variables = $pipeline->config['variables'] ?? [];

            $rawSteps = $pipeline->steps()
                ->with('agent')
                ->get();

            $indexToUid = [];
            foreach ($rawSteps as $s) {
                $indexToUid[$s->order] = uniqid('step_');
            }

            $this->steps = $rawSteps->map(function (PipelineStep $s) use ($indexToUid) {
                $selectedOutputs = $s->selected_outputs ?? [];
                $passOutputAsInput = $s->pass_output_as_input;
                $passAllOutputs = $s->pass_all_previous_outputs;

                // Migration logic: if using old flags and no new selection, convert to new selection
                if (empty($selectedOutputs)) {
                    if ($passAllOutputs) {
                        $selectedOutputs = $s->order > 0 ? range(0, $s->order - 1) : [];
                    } elseif ($passOutputAsInput && $s->order > 0) {
                        $selectedOutputs = [$s->order - 1];
                    }
                }

                // Convert indices to UIDs
                $uidSelectedOutputs = [];
                foreach ($selectedOutputs as $idx) {
                    if (isset($indexToUid[$idx])) {
                        $uidSelectedOutputs[] = $indexToUid[$idx];
                    }
                }

                return [
                    'uid' => $indexToUid[$s->order],
                    'id' => $s->id,
                    'type' => $s->step_type ?: (($s->method || $s->mcp_actionable_id) ? 'custom' : ($s->agent_id ? 'agent' : 'receive_data')),
                    'agent_id' => $s->agent_id,
                    'class' => $s->class,
                    'method' => $s->method,
                    'mcp_actionable_id' => $s->mcp_actionable_id,
                    'method_key' => $s->mcp_actionable_id ? "mcp:{$s->mcp_actionable_id}" : ($s->method ? "{$s->class}@{$s->method}" : ''),
                    'model' => $s->model ?? '',
                    'decision_config' => $s->decision_config ?? [
                        'instructions' => '',
                        'question_type' => 'noul',
                        'confidence_threshold' => 0.80,
                        'provider_id' => '',
                    ],
                    'order' => $s->order,
                    'name' => $s->name ?? '',
                    'instructions_override' => $this->stripCacheBuster($s->instructions_override ?? ''),
                    'pass_output_as_input' => false, // Superseded
                    'pass_all_previous_outputs' => false, // Superseded
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
                    'force_json' => (bool) ($s->force_json ?? false),
                    'is_active' => $s->is_active,
                ];
            })
                ->values()
                ->toArray();

            if (! empty($this->steps)) {
                $this->expandedSteps = [$this->steps[count($this->steps) - 1]['uid']];
            }

            // Load failure handler step
            $failureStep = $pipeline->failureStep()->with('agent')->first();
            if ($failureStep) {
                $this->failureStepEnabled = true;
                $this->failureStep = [
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
                    'force_json' => (bool) ($failureStep->force_json ?? false),
                    'is_active' => $failureStep->is_active,
                ];
            }
        }
    }

    public function updatedType(string $value): void
    {
        $this->reorderSteps();
    }

    public function addStep(): void
    {
        $uid = uniqid('step_');
        $type = 'agent';
        if (empty($this->steps) && $this->type === 'endpoint') {
            $type = 'receive_data';
        }

        $defaultDecisionConfig = \Agentoom\Core\Services\InjectCurrentAiConfigService::getDecisionConfig();

        $this->steps[] = [
            'uid' => $uid,
            'id' => null,
            'type' => $type,
            'agent_id' => '',
            'class' => '',
            'method' => '',
            'mcp_actionable_id' => null,
            'method_key' => '',
            'model' => $defaultDecisionConfig['model'] ?? '',
            'decision_config' => [
                'instructions' => '',
                'question_type' => 'noul',
                'confidence_threshold' => $defaultDecisionConfig['confidence_threshold'] ?? 0.80,
                'provider_id' => $defaultDecisionConfig['provider'] ?? '',
            ],
            'order' => count($this->steps),
            'name' => '',
            'instructions_override' => '',
            'pass_output_as_input' => false,
            'pass_all_previous_outputs' => false,
            'selected_outputs' => [],
            'condition_if' => '',
            'next_step_on_true' => null,
            'next_step_on_false' => null,
            'on_error' => 'continue',
            'next_step_on_error' => null,
            'retry_attempts' => 0,
            'retry_delay' => 0,
            'condition_on_previous' => false,
            'condition_on_input' => false,
            'condition_on_current' => false,
            'force_json' => false,
            'is_active' => true,
        ];

        $this->reorderSteps();

        $this->expandedSteps = [$uid];
        $this->updatedSelectedSteps();
    }

    public function toggleFailureStep(): void
    {
        $this->failureStepEnabled = ! $this->failureStepEnabled;

        if ($this->failureStepEnabled && $this->failureStep === null) {
            $this->failureStep = [
                'uid' => uniqid('step_'),
                'id' => null,
                'type' => 'agent',
                'agent_id' => '',
                'class' => '',
                'method' => '',
                'mcp_actionable_id' => null,
                'method_key' => '',
                'name' => '',
                'instructions_override' => '',
                'on_error' => 'continue',
                'retry_attempts' => 0,
                'retry_delay' => 0,
                'force_json' => false,
                'is_active' => true,
            ];
        }

        if (! $this->failureStepEnabled) {
            $this->failureStep = null;
        }
    }

    public function addVariable(): void
    {
        $this->variables[] = ['key' => '', 'value' => ''];
    }

    public function removeVariable(int $index): void
    {
        array_splice($this->variables, $index, 1);
    }

    public function removeStep(int $index): void
    {
        $oldIndexToUid = [];
        foreach ($this->steps as $idx => $s) {
            $oldIndexToUid[$idx] = $s['uid'];
        }

        $uid = $this->steps[$index]['uid'] ?? null;
        array_splice($this->steps, $index, 1);
        $this->steps = array_values($this->steps);

        $this->adjustReferences($oldIndexToUid);

        if ($uid) {
            $this->selectedSteps = array_values(array_diff($this->selectedSteps, [$uid]));
            $this->expandedSteps = array_values(array_diff($this->expandedSteps, [$uid]));
        }

        $this->reorderSteps();

        if (! empty($this->steps)) {
            $this->expandedSteps = [$this->steps[count($this->steps) - 1]['uid']];
        } else {
            $this->expandedSteps = [];
        }

        $this->updatedSelectedSteps();
    }

    public function moveUp(int $index): void
    {
        if ($index === 0) {
            return;
        }

        $oldIndexToUid = [];
        foreach ($this->steps as $idx => $s) {
            $oldIndexToUid[$idx] = $s['uid'];
        }

        $this->steps = array_values($this->steps);
        [$this->steps[$index - 1], $this->steps[$index]] = [$this->steps[$index], $this->steps[$index - 1]];
        $this->adjustReferences($oldIndexToUid);
        $this->reorderSteps();
    }

    public function moveDown(int $index): void
    {
        if ($index >= count($this->steps) - 1) {
            return;
        }

        $oldIndexToUid = [];
        foreach ($this->steps as $idx => $s) {
            $oldIndexToUid[$idx] = $s['uid'];
        }

        $this->steps = array_values($this->steps);
        [$this->steps[$index + 1], $this->steps[$index]] = [$this->steps[$index], $this->steps[$index + 1]];
        $this->adjustReferences($oldIndexToUid);
        $this->reorderSteps();
    }

    public function openMoveModal(int $index): void
    {
        $this->movingStepIndex = $index;
        $this->targetStepIndex = null;
        $this->movePosition = 'before';
        $this->isBulkMove = false;
        $this->isBulkCloneMove = false;
        $this->showMoveStepModal = true;
    }

    public function openBulkMoveModal(bool $clone = false): void
    {
        if (empty($this->selectedSteps)) {
            return;
        }

        $this->isBulkMove = true;
        $this->isBulkCloneMove = $clone;
        $this->movingStepIndex = null;
        $this->targetStepIndex = null;
        $this->movePosition = 'before';
        $this->showMoveStepModal = true;
    }

    public function toggleStepSelection(int|string $uid): void
    {
        if (in_array($uid, $this->selectedSteps)) {
            $this->selectedSteps = array_values(array_diff($this->selectedSteps, [$uid]));
        } else {
            $this->selectedSteps[] = $uid;
        }

        $this->updatedSelectedSteps();
    }

    public function updatedSelectedSteps(): void
    {
        $this->allSelected = count($this->selectedSteps) === count($this->steps) && count($this->steps) > 0;
    }

    public function updatedAllSelected($value): void
    {
        if ($value) {
            $this->selectedSteps = collect($this->steps)->pluck('uid')->toArray();
        } else {
            $this->selectedSteps = [];
        }
    }

    public function selectAllSteps(): void
    {
        if (count($this->selectedSteps) === count($this->steps)) {
            $this->selectedSteps = [];
            $this->allSelected = false;
        } else {
            $this->selectedSteps = collect($this->steps)->pluck('uid')->toArray();
            $this->allSelected = true;
        }
    }

    public function deleteSelectedSteps(): void
    {
        if (empty($this->selectedSteps)) {
            return;
        }

        $oldIndexToUid = [];
        foreach ($this->steps as $idx => $s) {
            $oldIndexToUid[$idx] = $s['uid'];
        }
        $uids = $this->selectedSteps;

        $this->steps = collect($this->steps)
            ->reject(fn ($step) => in_array($step['uid'], $uids))
            ->values()
            ->toArray();

        $this->selectedSteps = [];
        $this->allSelected = false;

        $this->adjustReferences($oldIndexToUid);

        $this->reorderSteps();

        \Flux::toast(__('agentoom-core::admin.pipelines.steps_deleted'));
    }

    public function executeMoveStep(): void
    {
        if ($this->isBulkMove) {
            $this->executeBulkMove();

            return;
        }

        if ($this->movingStepIndex === null || $this->targetStepIndex === null) {
            return;
        }

        $fromIndex = $this->movingStepIndex;
        $targetIndex = (int) $this->targetStepIndex;
        $position = $this->movePosition;

        if ($fromIndex === $targetIndex && $position === 'before') {
            $this->showMoveStepModal = false;

            return;
        }

        $oldIndexToUid = [];
        foreach ($this->steps as $idx => $s) {
            $oldIndexToUid[$idx] = $s['uid'];
        }

        $step = array_splice($this->steps, $fromIndex, 1)[0];

        $adjustedTargetIndex = $targetIndex;
        if ($fromIndex < $targetIndex) {
            $adjustedTargetIndex--;
        }

        $newIndex = ($position === 'before') ? $adjustedTargetIndex : $adjustedTargetIndex + 1;
        $newIndex = max(0, min(count($this->steps), $newIndex));

        array_splice($this->steps, $newIndex, 0, [$step]);
        $this->steps = array_values($this->steps);
        $this->adjustReferences($oldIndexToUid);

        $this->reorderSteps();
        $this->expandedSteps = [$step['uid']];
        $this->selectedSteps = [];
        $this->allSelected = false;

        $this->movingStepIndex = null;
        $this->targetStepIndex = null;

        $this->showMoveStepModal = false;
        \Flux::toast(__('agentoom-core::admin.pipelines.step_moved'));
    }

    public function openMethodSelector(int $index): void
    {
        $this->dispatch('open-method-selector', identifier: (string) $index);
    }

    #[On('method-selected')]
    public function onMethodSelected(string $key, ?string $identifier = null): void
    {
        if ($identifier === null || ! is_numeric($identifier)) {
            return;
        }

        $index = (int) $identifier;

        if (isset($this->steps[$index])) {
            $this->steps[$index]['method_key'] = $key;
        }
    }

    private function executeBulkMove(): void
    {
        if (empty($this->selectedSteps) || $this->targetStepIndex === null) {
            return;
        }

        $targetIndex = (int) $this->targetStepIndex;
        $position = $this->movePosition;
        $uids = $this->selectedSteps;

        $oldIndexToUid = [];
        foreach ($this->steps as $idx => $s) {
            $oldIndexToUid[$idx] = $s['uid'];
        }

        $stepsToMove = [];
        foreach ($this->steps as $step) {
            if (in_array($step['uid'], $uids)) {
                if ($this->isBulkCloneMove) {
                    $step['id'] = null;
                    $step['uid'] = uniqid('step_');
                    $step['name'] = $step['name'] ? $step['name'].' (Copy)' : '';
                }
                $stepsToMove[] = $step;
            }
        }

        if (! $this->isBulkCloneMove) {
            $targetUid = $this->steps[$targetIndex]['uid'];

            // Remove steps
            $this->steps = collect($this->steps)
                ->reject(fn ($step) => in_array($step['uid'], $uids))
                ->values()
                ->toArray();

            // Re-find target index
            $targetIndex = collect($this->steps)->search(fn ($step) => $step['uid'] === $targetUid);
            if ($targetIndex === false) {
                $targetIndex = 0;
            }
        }

        $newIndex = ($position === 'before') ? $targetIndex : $targetIndex + 1;
        $newIndex = max(0, min(count($this->steps), $newIndex));

        array_splice($this->steps, $newIndex, 0, $stepsToMove);
        $this->steps = array_values($this->steps);
        $this->adjustReferences($oldIndexToUid);

        $this->reorderSteps();
        $this->expandedSteps = collect($stepsToMove)->pluck('uid')->toArray();

        $isClone = $this->isBulkCloneMove;

        $this->selectedSteps = [];
        $this->allSelected = false;
        $this->isBulkMove = false;
        $this->isBulkCloneMove = false;
        $this->showMoveStepModal = false;

        \Flux::toast($isClone
            ? __('agentoom-core::admin.pipelines.steps_cloned_moved')
            : __('agentoom-core::admin.pipelines.steps_moved')
        );
    }

    public function cloneStep(int $index): void
    {
        $stepToClone = $this->steps[$index];
        $clonedStep = $stepToClone;
        $clonedStep['id'] = null; // Clear ID so it's created as new
        $clonedStep['uid'] = uniqid('step_');
        $clonedStep['name'] = $clonedStep['name'] ? $clonedStep['name'].' (Copy)' : '';

        // Insert after the current step
        $oldIndexToUid = [];
        foreach ($this->steps as $idx => $s) {
            $oldIndexToUid[$idx] = $s['uid'];
        }

        array_splice($this->steps, $index + 1, 0, [$clonedStep]);
        $this->steps = array_values($this->steps);
        $this->adjustReferences($oldIndexToUid);

        $this->reorderSteps();

        $this->expandedSteps = [$clonedStep['uid']];
        $this->selectedSteps = [];
        $this->allSelected = false;
    }

    public function save(bool $stay = false): void
    {
        if (! $this->pipeline?->exists && app(ResourceLimitManager::class)->isLimitReached('pipelines')) {
            $this->addError('name', __('agentoom-core::admin.resource_limits.usage.limit_reached'));

            return;
        }

        $this->validate();

        $this->validateSteps();

        $pipeline = $this->pipeline ?? new Pipeline;

        $pipeline->fill([
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description ?: null,
            'project_id' => $this->projectId,
            'is_active' => $this->is_active,
            'allow_memory_analysis' => $this->allow_memory_analysis,
            'concurrency_lock' => $this->concurrency_lock,
            'config' => array_merge($pipeline->config ?? [], ['variables' => array_values($this->variables)]),
        ])->save();

        // Sync steps: delete removed ones, upsert existing/new
        $keepIds = collect($this->steps)->pluck('id')->filter()->all();

        // Also keep the failure step ID if it exists
        if ($this->failureStep && $this->failureStep['id']) {
            $keepIds[] = $this->failureStep['id'];
        }

        $pipeline->steps()->whereNotIn('id', $keepIds)->delete();

        $uidToIndex = [];
        foreach ($this->steps as $index => $stepData) {
            $uidToIndex[$stepData['uid']] = $index;
        }

        foreach ($this->steps as $index => $stepData) {
            $step = $stepData['id']
                ? PipelineStep::findOrFail($stepData['id'])
                : new PipelineStep(['pipeline_id' => $pipeline->id]);

            // Convert UIDs back to indices for saving
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
                'step_type' => $stepData['type'] ?? 'agent',
                'agent_id' => ($stepData['type'] ?? 'agent') === 'agent' ? (($stepData['agent_id'] ?? null) ?: null) : null,
                'class' => ($stepData['type'] ?? 'agent') === 'custom' ? (($stepData['class'] ?? null) ?: null) : null,
                'method' => ($stepData['type'] ?? 'agent') === 'custom' ? (($stepData['method'] ?? null) ?: null) : null,
                'mcp_actionable_id' => ($stepData['type'] ?? 'agent') === 'custom' ? (($stepData['mcp_actionable_id'] ?? null) ?: null) : null,
                'model' => ($stepData['type'] ?? 'agent') === 'decision' ? (($stepData['model'] ?? null) ?: null) : null,
                'decision_config' => ($stepData['type'] ?? 'agent') === 'decision' ? ($stepData['decision_config'] ?? null) : null,
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
                'force_json' => (bool) ($stepData['force_json'] ?? false),
                'is_active' => (bool) ($stepData['is_active'] ?? true),
            ])->save();
        }

        // Save failure handler step
        if ($this->failureStepEnabled && $this->failureStep) {
            $fs = $this->failureStep;
            $failureStep = $fs['id']
                ? PipelineStep::findOrFail($fs['id'])
                : new PipelineStep(['pipeline_id' => $pipeline->id]);

            $failureStep->fill([
                'pipeline_id' => $pipeline->id,
                'agent_id' => ($fs['type'] ?? 'agent') === 'agent' ? (($fs['agent_id'] ?? null) ?: null) : null,
                'class' => ($fs['type'] ?? 'agent') === 'custom' ? (($fs['class'] ?? null) ?: null) : null,
                'method' => ($fs['type'] ?? 'agent') === 'custom' ? (($fs['method'] ?? null) ?: null) : null,
                'mcp_actionable_id' => ($fs['type'] ?? 'agent') === 'custom' ? (($fs['mcp_actionable_id'] ?? null) ?: null) : null,
                'order' => 9999,
                'name' => ($fs['name'] ?? null) ?: null,
                'is_failure_handler' => true,
                'instructions_override' => ($fs['instructions_override'] ?? null) ?: null,
                'on_error' => $fs['on_error'] ?? 'continue',
                'retry_attempts' => $fs['retry_attempts'] ?? 0,
                'retry_delay' => $fs['retry_delay'] ?? 0,
                'force_json' => (bool) ($fs['force_json'] ?? false),
                'is_active' => (bool) ($fs['is_active'] ?? true),
            ])->save();
        } elseif (! $this->failureStepEnabled) {
            // Delete any existing failure handler step
            $pipeline->failureStep()->delete();
        }

        $this->pipeline = $pipeline;

        \Flux::toast(__('agentoom-core::admin.pipelines.saved_success'));

        if ($stay) {
            $this->redirect(route('admin.pipelines.edit', $pipeline), navigate: true);

            return;
        }

        $this->redirect(route('admin.pipelines.index'), navigate: true);
    }

    public function clone(): void
    {
        if (! $this->pipeline || ! $this->pipeline->exists) {
            return;
        }

        $newPipeline = $this->pipeline->replicate();
        $newPipeline->name = $this->pipeline->name.' (Clone)';
        $newPipeline->slug = null;
        $newPipeline->save();

        foreach ($this->pipeline->steps as $step) {
            $newStep = $step->replicate();
            $newStep->pipeline_id = $newPipeline->id;
            $newStep->save();
        }

        \Flux::toast(__('agentoom-core::admin.common.cloned_success'));

        $this->redirect(route('admin.pipelines.edit', $newPipeline), navigate: true);
    }

    protected function stripCacheBuster(string $instructions): string
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

    protected function adjustReferences(?array $oldIndexToUid = null): void
    {
        $this->steps = array_values($this->steps);
        $currentUids = collect($this->steps)->pluck('uid')->toArray();
        $uidToIndex = [];
        foreach ($this->steps as $idx => $s) {
            $uidToIndex[$s['uid']] = $idx;
        }

        foreach ($this->steps as $i => $step) {
            $modified = false;

            // Adjust selected_outputs
            if (isset($step['selected_outputs']) && is_array($step['selected_outputs'])) {
                $newSelected = [];
                foreach ($step['selected_outputs'] as $ref) {
                    // If it's a UID, check if it exists and is before current step
                    if (is_string($ref) && str_starts_with($ref, 'step_')) {
                        if (isset($uidToIndex[$ref]) && $uidToIndex[$ref] < $i) {
                            $newSelected[] = $ref;
                        }
                    }
                    // Support legacy indices if any still exist during transition
                    elseif (is_numeric($ref) && $oldIndexToUid && isset($oldIndexToUid[$ref])) {
                        $uid = $oldIndexToUid[$ref];
                        if (isset($uidToIndex[$uid]) && $uidToIndex[$uid] < $i) {
                            $newSelected[] = $uid;
                        }
                    }
                }
                $newSelected = array_values(array_unique($newSelected));
                if ($step['selected_outputs'] !== $newSelected) {
                    $this->steps[$i]['selected_outputs'] = $newSelected;
                }
            }

            // Adjust next_step_on_*
            foreach (['next_step_on_true', 'next_step_on_false', 'next_step_on_error'] as $field) {
                if (isset($step[$field]) && $step[$field] !== null && $step[$field] !== '') {
                    $ref = $step[$field];
                    if (is_string($ref) && str_starts_with($ref, 'step_')) {
                        if (! in_array($ref, $currentUids)) {
                            $this->steps[$i][$field] = null;
                        }
                    }
                    // Support legacy indices
                    elseif (is_numeric($ref) && $oldIndexToUid && isset($oldIndexToUid[$ref])) {
                        $uid = $oldIndexToUid[$ref];
                        if (in_array($uid, $currentUids)) {
                            $this->steps[$i][$field] = $uid;
                        } else {
                            $this->steps[$i][$field] = null;
                        }
                    }
                }
            }
        }
    }

    private function reorderSteps(): void
    {
        $defaults = [
            'uid' => '',
            'name' => '',
            'type' => 'agent',
            'agent_id' => '',
            'class' => '',
            'method' => '',
            'method_key' => '',
            'instructions_override' => '',
            'selected_outputs' => [],
            'condition_if' => '',
            'next_step_on_true' => null,
            'next_step_on_false' => null,
            'on_error' => 'continue',
            'next_step_on_error' => null,
            'retry_attempts' => 0,
            'retry_delay' => 0,
            'condition_on_previous' => false,
            'condition_on_input' => false,
            'condition_on_current' => false,
            'force_json' => false,
            'is_active' => true,
        ];

        $newSteps = [];
        foreach (array_values($this->steps) as $index => $step) {
            $step = array_merge($defaults, $step);

            if (empty($step['uid'])) {
                $step['uid'] = uniqid('step_');
            }

            $step['order'] = $index;

            $newSteps[$index] = $step;
        }
        $this->steps = $newSteps;
    }

    private function validateSteps(): void
    {
        $this->reorderSteps();

        if ($this->type === 'endpoint' && (empty($this->steps) || ($this->steps[0]['type'] ?? 'agent') !== 'receive_data')) {
            $this->addError('steps', 'Endpoint pipelines must start with a "Receive Data" step.');
        }

        if ($this->type === 'standard' && ! empty($this->steps) && ($this->steps[0]['type'] ?? 'agent') === 'receive_data') {
            $this->addError('steps.0.type', 'Standard pipelines cannot start with a "Receive Data" step.');
        }

        foreach ($this->steps as $index => $step) {
            if (($step['type'] ?? 'agent') === 'agent' && empty($step['agent_id'])) {
                $this->addError("steps.{$index}.agent_id", 'Please select an agent for this step.');
            }

            if (($step['type'] ?? 'agent') === 'custom' && (empty($step['class']) || empty($step['method']))) {
                $this->addError("steps.{$index}.method", 'Please select a Direct Action for this step.');
            }

            if (($step['type'] ?? 'agent') === 'decision' && empty($step['decision_config']['instructions'])) {
                $this->addError("steps.{$index}.decision_config.instructions", 'Please provide a question or instructions for the decision evaluation.');
            }
        }
    }

    public function updatedSteps($value, $key): void
    {
        if (str_ends_with($key, '.type')) {
            $parts = explode('.', $key);
            $index = $parts[0];
            if (isset($this->steps[$index])) {
                $this->steps[$index]['instructions_override'] = '';
                $this->steps[$index]['method_key'] = '';
                $this->steps[$index]['agent_id'] = '';

                if ($value === 'decision') {
                    $defaultDecisionConfig = \Agentoom\Core\Services\InjectCurrentAiConfigService::getDecisionConfig();
                    $this->steps[$index]['model'] = $this->steps[$index]['model'] ?: ($defaultDecisionConfig['model'] ?? '');
                    if (empty($this->steps[$index]['decision_config']['instructions'])) {
                        $this->steps[$index]['decision_config'] = [
                            'instructions' => '',
                            'question_type' => 'noul',
                            'confidence_threshold' => $defaultDecisionConfig['confidence_threshold'] ?? 0.80,
                            'provider_id' => $defaultDecisionConfig['provider'] ?? '',
                        ];
                    }
                }
            }
        }

        // Handle agent selection and project auto-selection
        if (str_ends_with($key, '.agent_id') && $value && is_int($value) && ! $this->projectId) {
            $agent = Agent::find($value);
            if ($agent && $agent->project_id) {
                $this->projectId = $agent->project_id;
            }
        }

        // Handle method selection and parameter prefilling
        if (str_ends_with($key, '.method_key')) {
            $parts = explode('.', $key);
            $index = $parts[0];

            if (! $value) {
                $this->steps[$index]['class'] = '';
                $this->steps[$index]['method'] = '';
                $this->steps[$index]['mcp_actionable_id'] = null;
                $this->steps[$index]['type'] = 'agent';

                return;
            }

            $discoveryService = app(ActionDiscoveryService::class);
            $methods = $discoveryService->discover();

            if (isset($methods[$value])) {
                $methodData = $methods[$value];
                $this->steps[$index]['type'] = 'custom';
                $this->steps[$index]['class'] = $methodData['class'] ?? null;
                $this->steps[$index]['method'] = $methodData['method'] ?? null;
                $this->steps[$index]['mcp_actionable_id'] = $methodData['mcp_actionable_id'] ?? null;

                // Prefill instructions with JSON of parameters
                $prefilled = [];
                foreach ($methodData['parameters'] as $name => $paramData) {
                    $prefilled[] = [
                        'name' => $name,
                        'type' => $paramData['type'],
                        'value' => '',
                        'description' => $paramData['description'],
                    ];
                }
                $this->steps[$index]['instructions_override'] = json_encode($prefilled);
            }
        }
    }

    #[On('json-structure-updated')]
    public function updateStepInstructions(array $fields, ?string $identifier = null): void
    {
        if ($identifier === null) {
            return;
        }

        foreach ($this->steps as $index => $step) {
            if ($step['uid'] === $identifier) {
                $this->steps[$index]['instructions_override'] = json_encode($fields);

                return;
            }
        }

        if (isset($this->steps[$identifier])) {
            $this->steps[$identifier]['instructions_override'] = json_encode($fields);
        }
    }

    /**
     * Get instructions formatted for JsonStructureHelper for a specific step.
     */
    public function getStepInstructionFields(int|string $identifier): array
    {
        $step = null;
        if (is_numeric($identifier) && isset($this->steps[$identifier])) {
            $step = $this->steps[$identifier];
        } else {
            foreach ($this->steps as $s) {
                if ($s['uid'] === $identifier) {
                    $step = $s;
                    break;
                }
            }
        }

        if (! $step) {
            return [];
        }

        $instructions = $step['instructions_override'] ?? '';
        $data = json_decode($instructions, true);
        if (! is_array($data)) {
            return [];
        }

        // If it's already in the new format (array of objects with 'name')
        if (! empty($data) && isset($data[0]) && is_array($data[0]) && isset($data[0]['name'])) {
            return $data;
        }

        // Convert old simple key-value format to new format
        $fields = [];
        foreach ($data as $name => $value) {
            if (is_int($name)) {
                continue;
            }
            $fields[] = [
                'name' => (string) $name,
                'type' => is_int($value) ? 'integer' : (is_bool($value) ? 'boolean' : 'string'),
                'value' => (string) $value,
                'description' => '',
            ];
        }

        return $fields;
    }

    #[Computed]
    public function agents()
    {
        $agentsQuery = Agent::query()->where('is_active', true);

        if ($this->projectId) {
            $agentsQuery->where('project_id', $this->projectId);
        }

        return $agentsQuery->orderBy('name')->get();
    }

    #[Computed]
    public function actionableMethods()
    {
        return collect(app(ActionDiscoveryService::class)->discover())
            ->sortBy('category')
            ->toArray();
    }

    #[Computed]
    public function projects()
    {
        return Project::orderBy('name')->get();
    }

    #[Computed]
    public function decisionModels()
    {
        $defaultConfig = \Agentoom\Core\Services\InjectCurrentAiConfigService::getDecisionConfig();
        $providerName = $defaultConfig['provider'] ?? null;

        $query = \Agentoom\Core\Models\ProviderModel::query()->where('is_active', true);
        if ($providerName) {
            $query->whereHas('provider', fn ($q) => $q->where('name', $providerName));
        }

        $decisionModels = (clone $query)->where(function ($q) {
            $q->where('model_features->architecture->output_modalities', 'like', '%"decisions"%')
                ->orWhere('model_name', 'like', '%jev%')
                ->orWhere('model_name', 'like', '%decid%')
                ->orWhere('model_name', 'like', '%classify%');
        })->get();

        return $decisionModels->isNotEmpty() ? $decisionModels : $query->get();
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.pipeline-create-edit')
            ->layout('agentoom-core::layouts.admin');
    }
}
