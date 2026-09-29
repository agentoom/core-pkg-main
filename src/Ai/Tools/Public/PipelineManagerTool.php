<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class PipelineManagerTool implements Tool
{
    use HandlesToolActions;

    public function description(): Stringable|string
    {
        return 'Create, read, and edit Agentoom Pipelines and inspect their execution logs.
        Use this tool to list pipelines, retrieve their full configuration (including steps), create new pipelines with steps, update existing pipelines or individual steps, and review run logs to diagnose issues or suggest optimisations.
        CRITICAL: You MUST NOT create or modify any pipeline (or its steps) unless the user has explicitly granted permission in the current conversation.
        Always present your proposed changes to the user and wait for explicit approval before calling create_pipeline, update_pipeline, create_step, update_step, or delete_step.';
    }

    /**
     * List all pipelines visible to the current user.
     */
    #[Sentinelable(
        name: 'Pipeline Manager Tool List Pipelines',
        description: 'List all pipelines visible to the current user.',
        category: 'Pipelines'
    )]
    protected function listPipelines(Request $request): string
    {
        $user = auth()->user();
        $pipelines = Pipeline::visibleTo($user)
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'type', 'is_active', 'concurrency_lock', 'allow_memory_analysis', 'created_at', 'updated_at']);

        if ($pipelines->isEmpty()) {
            return 'No pipelines found.';
        }

        $output = "Available Pipelines:\n\n";
        foreach ($pipelines as $pipeline) {
            $status = $pipeline->is_active ? 'active' : 'inactive';
            $output .= "### ID: {$pipeline->id} | Name: {$pipeline->name}\n";
            $output .= "  Type: {$pipeline->type} | Status: {$status}\n";
            if ($pipeline->description) {
                $output .= "  Description: {$pipeline->description}\n";
            }
            $output .= '  Concurrency Lock: '.($pipeline->concurrency_lock ? 'yes' : 'no').' | Memory Analysis: '.($pipeline->allow_memory_analysis ? 'yes' : 'no')."\n";
            $output .= "  Created: {$pipeline->created_at} | Updated: {$pipeline->updated_at}\n";
            $output .= "---\n";
        }

        return $output;
    }

    /**
     * Get full details of a pipeline including its steps.
     */
    #[Sentinelable(
        name: 'Pipeline Manager Tool Get Pipeline',
        description: 'Get full details of a pipeline including all its steps.',
        category: 'Pipelines'
    )]
    protected function getPipeline(Request $request): string
    {
        $pipelineId = (int) $request->integer('pipeline_id');

        if (! $pipelineId) {
            return 'Error: pipeline_id is required.';
        }

        $user = auth()->user();
        $pipeline = Pipeline::visibleTo($user)->with(['steps.agent'])->find($pipelineId);

        if (! $pipeline) {
            return "Error: Pipeline with ID {$pipelineId} not found or not accessible.";
        }

        $status = $pipeline->is_active ? 'active' : 'inactive';
        $output = "## Pipeline: {$pipeline->name} (ID: {$pipeline->id})\n";
        $output .= "Type: {$pipeline->type} | Status: {$status}\n";
        $output .= 'Description: '.($pipeline->description ?? '(none)')."\n";
        $output .= 'Concurrency Lock: '.($pipeline->concurrency_lock ? 'yes' : 'no').' | Memory Analysis: '.($pipeline->allow_memory_analysis ? 'yes' : 'no')."\n";

        if (! empty($pipeline->config)) {
            $output .= 'Config: '.json_encode($pipeline->config)."\n";
        }

        $output .= "\n### Steps (".count($pipeline->steps)." total):\n\n";

        foreach ($pipeline->steps as $step) {
            $stepType = $step->agent_id ? 'Agent' : ($step->class ? 'Direct Action' : 'Unknown');
            $output .= "#### Step {$step->order}: {$step->displayName()} (ID: {$step->id})\n";
            $output .= "  Type: {$stepType} | Active: ".($step->is_active ? 'yes' : 'no')."\n";

            if ($step->agent_id) {
                $output .= "  Agent ID: {$step->agent_id}".($step->agent ? " ({$step->agent->name})" : '')."\n";
            }

            if ($step->class) {
                $output .= "  Class: {$step->class} | Method: {$step->method}\n";
            }

            if ($step->instructions_override) {
                $output .= "  Instructions Override: {$step->instructions_override}\n";
            }

            $output .= '  Pass Output As Input: '.($step->pass_output_as_input ? 'yes' : 'no')."\n";
            $output .= '  Pass All Previous Outputs: '.($step->pass_all_previous_outputs ? 'yes' : 'no')."\n";

            if ($step->condition_if) {
                $output .= "  Condition: {$step->condition_if}\n";
                $output .= "  On True → Step {$step->next_step_on_true} | On False → Step {$step->next_step_on_false}\n";
            }

            if ($step->on_error) {
                $output .= "  On Error: {$step->on_error}".($step->next_step_on_error ? " → Step {$step->next_step_on_error}" : '')."\n";
            }

            if ($step->retry_attempts) {
                $output .= "  Retry: {$step->retry_attempts} attempts, {$step->retry_delay}s delay\n";
            }

            $output .= "---\n";
        }

        return $output;
    }

    /**
     * Create a new pipeline with optional steps.
     * REQUIRES explicit user permission before calling.
     */
    #[Sentinelable(
        name: 'Pipeline Manager Tool Create Pipeline',
        description: 'Create a new pipeline with optional steps. Requires explicit user permission.',
        category: 'Pipelines'
    )]
    protected function createPipeline(Request $request): string
    {
        $name = trim($request->string('name'));
        $description = trim($request->string('description', ''));
        $type = $request->string('type', Pipeline::TYPE_STANDARD);
        $isActive = $request->boolean('is_active', true);
        $concurrencyLock = $request->boolean('concurrency_lock', false);
        $allowMemoryAnalysis = $request->boolean('allow_memory_analysis', false);
        $steps = $request->array('steps');

        if (empty($name)) {
            return 'Error: name is required to create a pipeline.';
        }

        if (! in_array($type, [Pipeline::TYPE_STANDARD, Pipeline::TYPE_ENDPOINT])) {
            return "Error: type must be 'standard' or 'endpoint'.";
        }

        $user = auth()->user();

        $pipeline = Pipeline::create([
            'name' => $name,
            'description' => $description ?: null,
            'type' => $type,
            'is_active' => $isActive,
            'concurrency_lock' => $concurrencyLock,
            'allow_memory_analysis' => $allowMemoryAnalysis,
            'user_id' => $user->id,
            'project_id' => app()->bound('current_project_id') ? app('current_project_id') : null,
        ]);

        $createdSteps = 0;
        $stepErrors = [];

        foreach ($steps as $index => $stepData) {
            $stepResult = $this->createStepForPipeline($pipeline->id, $stepData, $index);
            if (str_starts_with($stepResult, 'Error')) {
                $stepErrors[] = "Step {$index}: {$stepResult}";
            } else {
                $createdSteps++;
            }
        }

        $output = "Successfully created pipeline '{$pipeline->name}' (ID: {$pipeline->id}) with {$createdSteps} step(s).";

        if (! empty($stepErrors)) {
            $output .= "\nStep errors:\n".implode("\n", $stepErrors);
        }

        return $output;
    }

    /**
     * Update an existing pipeline's metadata.
     * REQUIRES explicit user permission before calling.
     */
    #[Sentinelable(
        name: 'Pipeline Manager Tool Update Pipeline',
        description: 'Update an existing pipeline metadata. Requires explicit user permission.',
        category: 'Pipelines'
    )]
    protected function updatePipeline(Request $request): string
    {
        $pipelineId = (int) $request->integer('pipeline_id');

        if (! $pipelineId) {
            return 'Error: pipeline_id is required.';
        }

        $user = auth()->user();
        $pipeline = Pipeline::visibleTo($user)->find($pipelineId);

        if (! $pipeline) {
            return "Error: Pipeline with ID {$pipelineId} not found or not accessible.";
        }

        $updatable = ['name', 'description', 'type', 'is_active', 'concurrency_lock', 'allow_memory_analysis'];
        $data = [];

        foreach ($updatable as $field) {
            $raw = $request->all();
            if (array_key_exists($field, $raw)) {
                $data[$field] = $raw[$field];
            }
        }

        if (empty($data)) {
            return 'Error: No updatable fields provided. Provide at least one of: '.implode(', ', $updatable).'.';
        }

        $pipeline->update($data);

        return "Successfully updated pipeline '{$pipeline->name}' (ID: {$pipeline->id}).";
    }

    /**
     * Add a new step to an existing pipeline.
     * REQUIRES explicit user permission before calling.
     */
    #[Sentinelable(
        name: 'Pipeline Manager Tool Create Step',
        description: 'Add a new step to an existing pipeline. Requires explicit user permission.',
        category: 'Pipelines'
    )]
    protected function createStep(Request $request): string
    {
        $pipelineId = (int) $request->integer('pipeline_id');

        if (! $pipelineId) {
            return 'Error: pipeline_id is required.';
        }

        $user = auth()->user();
        $pipeline = Pipeline::visibleTo($user)->find($pipelineId);

        if (! $pipeline) {
            return "Error: Pipeline with ID {$pipelineId} not found or not accessible.";
        }

        $stepData = $request->all();
        unset($stepData['pipeline_id'], $stepData['action']);

        $maxOrder = $pipeline->steps()->max('order') ?? -1;
        $stepData['order'] = $stepData['order'] ?? ($maxOrder + 1);

        $result = $this->createStepForPipeline($pipelineId, $stepData, $stepData['order']);

        return $result;
    }

    /**
     * Update an existing pipeline step.
     * REQUIRES explicit user permission before calling.
     */
    #[Sentinelable(
        name: 'Pipeline Manager Tool Update Step',
        description: 'Update an existing pipeline step. Requires explicit user permission.',
        category: 'Pipelines'
    )]
    protected function updateStep(Request $request): string
    {
        $stepId = (int) $request->integer('step_id');

        if (! $stepId) {
            return 'Error: step_id is required.';
        }

        $step = PipelineStep::find($stepId);

        if (! $step) {
            return "Error: Step with ID {$stepId} not found.";
        }

        $user = auth()->user();
        $pipeline = Pipeline::visibleTo($user)->find($step->pipeline_id);

        if (! $pipeline) {
            return "Error: You do not have access to the pipeline containing step {$stepId}.";
        }

        $updatable = [
            'agent_id', 'class', 'method', 'name', 'order', 'is_active', 'is_failure_handler',
            'instructions_override', 'pass_output_as_input', 'pass_all_previous_outputs',
            'selected_outputs', 'condition_if', 'next_step_on_true', 'next_step_on_false',
            'on_error', 'next_step_on_error', 'retry_attempts', 'retry_delay',
            'condition_on_previous', 'condition_on_input', 'condition_on_current',
        ];

        $raw = $request->all();
        $data = [];

        foreach ($updatable as $field) {
            if (array_key_exists($field, $raw)) {
                $data[$field] = $raw[$field];
            }
        }

        if (empty($data)) {
            return 'Error: No updatable fields provided.';
        }

        $step->update($data);

        return "Successfully updated step ID {$stepId} ('{$step->displayName()}') in pipeline '{$pipeline->name}'.";
    }

    /**
     * Delete a step from a pipeline.
     * REQUIRES explicit user permission before calling.
     */
    #[Sentinelable(
        name: 'Pipeline Manager Tool Delete Step',
        description: 'Delete a step from a pipeline. Requires explicit user permission.',
        category: 'Pipelines'
    )]
    protected function deleteStep(Request $request): string
    {
        $stepId = (int) $request->integer('step_id');

        if (! $stepId) {
            return 'Error: step_id is required.';
        }

        $step = PipelineStep::find($stepId);

        if (! $step) {
            return "Error: Step with ID {$stepId} not found.";
        }

        $user = auth()->user();
        $pipeline = Pipeline::visibleTo($user)->find($step->pipeline_id);

        if (! $pipeline) {
            return "Error: You do not have access to the pipeline containing step {$stepId}.";
        }

        $stepName = $step->displayName();
        $step->delete();

        return "Successfully deleted step '{$stepName}' (ID: {$stepId}) from pipeline '{$pipeline->name}'.";
    }

    /**
     * Get execution logs for a pipeline.
     */
    #[Sentinelable(
        name: 'Pipeline Manager Tool Get Logs',
        description: 'Get recent execution logs for a pipeline to diagnose issues or analyse performance.',
        category: 'Pipelines'
    )]
    protected function getLogs(Request $request): string
    {
        $pipelineId = (int) $request->integer('pipeline_id');
        $limit = min((int) $request->integer('limit', 10), 50);
        $status = $request->string('status', '');

        if (! $pipelineId) {
            return 'Error: pipeline_id is required.';
        }

        $user = auth()->user();
        $pipeline = Pipeline::visibleTo($user)->find($pipelineId);

        if (! $pipeline) {
            return "Error: Pipeline with ID {$pipelineId} not found or not accessible.";
        }

        $query = PipelineRun::where('pipeline_id', $pipelineId)->latest()->limit($limit);

        if ($status) {
            $query->where('status', $status);
        }

        $runs = $query->with('stepRuns')->get();

        if ($runs->isEmpty()) {
            return "No execution logs found for pipeline '{$pipeline->name}'.".($status ? " (filtered by status: {$status})" : '');
        }

        $output = "## Execution Logs for Pipeline: {$pipeline->name} (ID: {$pipelineId})\n";
        $output .= "Showing last {$runs->count()} run(s)".($status ? " with status '{$status}'" : '')."\n\n";

        foreach ($runs as $run) {
            $duration = $run->durationInSeconds();
            $output .= "### Run ID: {$run->id} | Status: {$run->status}\n";
            $output .= "  Started: {$run->started_at} | Completed: ".($run->completed_at ?? 'N/A')."\n";
            $output .= "  Duration: {$duration}s\n";

            if ($run->input) {
                $inputPreview = is_array($run->input) ? json_encode($run->input) : (string) $run->input;
                $output .= '  Input: '.mb_substr($inputPreview, 0, 300).(mb_strlen($inputPreview) > 300 ? '...' : '')."\n";
            }

            if ($run->output) {
                $outputPreview = is_array($run->output) ? json_encode($run->output) : (string) $run->output;
                $output .= '  Output: '.mb_substr($outputPreview, 0, 300).(mb_strlen($outputPreview) > 300 ? '...' : '')."\n";
            }

            if ($run->error) {
                $output .= "  Error: {$run->error}\n";
            }

            if ($run->stepRuns->isNotEmpty()) {
                $output .= '  Step Runs ('.count($run->stepRuns)."):\n";
                foreach ($run->stepRuns as $stepRun) {
                    $stepDuration = ($stepRun->started_at && $stepRun->completed_at)
                        ? $stepRun->started_at->diffInSeconds($stepRun->completed_at).'s'
                        : 'N/A';
                    $output .= "    - Step {$stepRun->order}: status={$stepRun->status}, duration={$stepDuration}";
                    if ($stepRun->error) {
                        $output .= ", error={$stepRun->error}";
                    }
                    $output .= "\n";
                }
            }

            $output .= "---\n";
        }

        return $output;
    }

    /**
     * List available agents to use in pipeline steps.
     */
    #[Sentinelable(
        name: 'Pipeline Manager Tool List Agents',
        description: 'List available agents that can be assigned to pipeline steps.',
        category: 'Pipelines'
    )]
    protected function listAgents(Request $request): string
    {
        $agents = Agent::where('is_active', true)
            ->where('system_agent', false)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        if ($agents->isEmpty()) {
            return 'No active agents found.';
        }

        $output = "Available Agents for Pipeline Steps:\n\n";
        foreach ($agents as $agent) {
            $output .= "ID: {$agent->id} | Name: {$agent->name} | Slug: {$agent->slug}\n";
            if ($agent->description) {
                $output .= "  Description: {$agent->description}\n";
            }
        }

        return $output;
    }

    /**
     * Internal helper to create a step for a pipeline.
     */
    private function createStepForPipeline(int $pipelineId, array $stepData, int $defaultOrder): string
    {
        $agentId = isset($stepData['agent_id']) ? (int) $stepData['agent_id'] : null;
        $class = $stepData['class'] ?? null;
        $method = $stepData['method'] ?? null;

        if (! $agentId && ! $class) {
            return 'Error: Each step must have either an agent_id or a class+method (Direct Action).';
        }

        if ($agentId) {
            $agent = Agent::find($agentId);
            if (! $agent) {
                return "Error: Agent with ID {$agentId} not found.";
            }
        }

        $step = PipelineStep::create([
            'pipeline_id' => $pipelineId,
            'agent_id' => $agentId,
            'class' => $class,
            'method' => $method,
            'name' => $stepData['name'] ?? null,
            'order' => $stepData['order'] ?? $defaultOrder,
            'is_active' => $stepData['is_active'] ?? true,
            'is_failure_handler' => $stepData['is_failure_handler'] ?? false,
            'instructions_override' => $stepData['instructions_override'] ?? null,
            'pass_output_as_input' => $stepData['pass_output_as_input'] ?? true,
            'pass_all_previous_outputs' => $stepData['pass_all_previous_outputs'] ?? false,
            'selected_outputs' => $stepData['selected_outputs'] ?? null,
            'condition_if' => $stepData['condition_if'] ?? null,
            'next_step_on_true' => $stepData['next_step_on_true'] ?? null,
            'next_step_on_false' => $stepData['next_step_on_false'] ?? null,
            'on_error' => $stepData['on_error'] ?? null,
            'next_step_on_error' => $stepData['next_step_on_error'] ?? null,
            'retry_attempts' => $stepData['retry_attempts'] ?? null,
            'retry_delay' => $stepData['retry_delay'] ?? null,
            'condition_on_previous' => $stepData['condition_on_previous'] ?? false,
            'condition_on_input' => $stepData['condition_on_input'] ?? false,
            'condition_on_current' => $stepData['condition_on_current'] ?? false,
        ]);

        return "Created step ID {$step->id} (order {$step->order}).";
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum([
                    'list_pipelines',
                    'get_pipeline',
                    'create_pipeline',
                    'update_pipeline',
                    'create_step',
                    'update_step',
                    'delete_step',
                    'get_logs',
                    'list_agents',
                ])
                ->required()
                ->description('The action to perform. Read actions: list_pipelines, get_pipeline, get_logs, list_agents. Write actions (require explicit user permission): create_pipeline, update_pipeline, create_step, update_step, delete_step.'),

            'pipeline_id' => $schema->integer()
                ->description('The ID of the pipeline (required for get_pipeline, update_pipeline, create_step, get_logs).'),

            'step_id' => $schema->integer()
                ->description('The ID of the pipeline step (required for update_step, delete_step).'),

            'name' => $schema->string()
                ->description('Pipeline name (required for create_pipeline, optional for update_pipeline).'),

            'description' => $schema->string()
                ->description('Pipeline description (optional).'),

            'type' => $schema->string()
                ->enum(['standard', 'endpoint'])
                ->description('Pipeline type: "standard" (default) or "endpoint".'),

            'is_active' => $schema->boolean()
                ->description('Whether the pipeline or step is active.'),

            'concurrency_lock' => $schema->boolean()
                ->description('Prevent concurrent executions of the same pipeline.'),

            'allow_memory_analysis' => $schema->boolean()
                ->description('Allow memory analysis for this pipeline.'),

            'steps' => $schema->array()
                ->description('Array of step definitions for create_pipeline. Each step object can contain: agent_id (int), class (string), method (string), name (string), order (int), is_active (bool), is_failure_handler (bool), instructions_override (string), pass_output_as_input (bool), pass_all_previous_outputs (bool), condition_if (string), next_step_on_true (int), next_step_on_false (int), on_error (string), next_step_on_error (int), retry_attempts (int), retry_delay (int).')
                ->items($schema->object([])),

            'agent_id' => $schema->integer()
                ->description('Agent ID to assign to a step (for create_step or update_step).'),

            'class' => $schema->string()
                ->description('PHP class for a Direct Action step (e.g. "Agentoom\\Core\\Services\\Api\\SomeService").'),

            'method' => $schema->string()
                ->description('Method name for a Direct Action step.'),

            'instructions_override' => $schema->string()
                ->description('Custom instructions to override the agent\'s default prompt for this step.'),

            'pass_output_as_input' => $schema->boolean()
                ->description('Pass the previous step\'s output as input to this step.'),

            'pass_all_previous_outputs' => $schema->boolean()
                ->description('Pass all previous steps\' outputs as context to this step.'),

            'condition_if' => $schema->string()
                ->description('Condition expression to evaluate for conditional branching.'),

            'next_step_on_true' => $schema->integer()
                ->description('Step order to jump to when condition_if is true.'),

            'next_step_on_false' => $schema->integer()
                ->description('Step order to jump to when condition_if is false.'),

            'on_error' => $schema->string()
                ->description('Error handling strategy: "stop", "continue", or "goto".'),

            'next_step_on_error' => $schema->integer()
                ->description('Step order to jump to on error (when on_error is "goto").'),

            'retry_attempts' => $schema->integer()
                ->description('Number of retry attempts on failure.'),

            'retry_delay' => $schema->integer()
                ->description('Delay in seconds between retry attempts.'),

            'is_failure_handler' => $schema->boolean()
                ->description('Mark this step as a failure handler step.'),

            'limit' => $schema->integer()
                ->description('Number of log runs to retrieve (default: 10, max: 50). Used with get_logs.'),

            'status' => $schema->string()
                ->description('Filter logs by status (e.g. "completed", "failed", "running"). Used with get_logs.'),
        ];
    }
}
