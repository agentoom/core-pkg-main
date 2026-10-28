<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Ai\Agents\PipelineStructuredAgent;
use Agentoom\Core\Attributes\Actionable;
use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Exceptions\ActionPausedException;
use Agentoom\Core\Exceptions\JumpToStepException;
use Agentoom\Core\Exceptions\RunPipelineException;
use Agentoom\Core\Exceptions\StopPipelineException;
use Agentoom\Core\Helpers\Helpers;
use Agentoom\Core\Jobs\RunPipelineJob;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineRunStep;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Models\User;
use Agentoom\Core\Traits\ParsesParameters;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

#[GroupVisibility('agentoom-core')]
class PipelineRunnerService
{
    use ParsesParameters;

    /**
     * Execute a pipeline with the given input.
     *
     * Each step receives the user's original input. If the step has
     * `pass_output_as_input = true`, the previous step's output is also
     * prepended so the agent has full context.
     */
    public function run(Pipeline $pipeline, ?string $input = null, ?User $user = null, ?int $scheduleId = null, ?int $scheduleLogId = null, ?int $aiTaskId = null, ?int $endpointId = null, ?int $endpointLogId = null, bool $background = false): PipelineRun
    {
        // Concurrency lock: if enabled and a run is already in progress, block it
        if ($pipeline->concurrency_lock) {
            $activeRun = PipelineRun::where('pipeline_id', $pipeline->id)
                ->whereIn('status', ['running', 'pending'])
                ->exists();

            if ($activeRun) {
                $run = PipelineRun::create([
                    'pipeline_id' => $pipeline->id,
                    'user_id' => $user?->id,
                    'status' => 'failed',
                    'input' => $input ?? '',
                    'error' => 'A pipeline run is already in progress. Concurrency lock is enabled — only one instance can run at a time.',
                    'completed_at' => now(),
                ]);

                return $run;
            }
        }

        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'user_id' => $user?->id,
            'status' => 'pending',
            'input' => $input ?? '',
        ]);

        if ($background) {
            Log::info('Dispatching RunPipelineJob for run #'.$run->id, [
                'pipeline_id' => $pipeline->id,
                'user_id' => $user?->id,
            ]);
            try {
                dispatch(new RunPipelineJob($run->id))->onQueue('default');
                Log::info('Dispatched RunPipelineJob for run #'.$run->id.' on queue default');
            } catch (Throwable $e) {
                Log::error('Failed to dispatch RunPipelineJob for run #'.$run->id.': '.$e->getMessage(), [
                    'exception' => $e,
                ]);
                $this->fail($run, 'Failed to dispatch background job: '.$e->getMessage());
            }

            return $run;
        }

        return $this->executeRun($run, $input, $scheduleId, $scheduleLogId, $aiTaskId, $endpointId, $endpointLogId, $background);
    }

    /**
     * Run a pipeline by its ID.
     *
     * @param  int|string  $pipelineId  The ID of the pipeline to run.
     * @param  array|string|null  $data  Initial data to pass to the first step.
     * @param  bool  $background  Whether to run the pipeline in the background.
     */
    #[Actionable(
        name: 'Run a pipeline',
        description: 'Execute a pipeline by its ID, optionally providing initial data and running in the background.',
        category: 'Automations'
    )]
    public function runByPipelineId(int|string $pipelineId, array|string|null $data = null, bool $background = false): PipelineRun
    {
        $pipeline = Pipeline::findOrFail($pipelineId);

        $input = is_array($data) ? json_encode($data) : $data;

        return $this->run(
            pipeline: $pipeline,
            input: $input,
            user: Auth::user(),
            background: $background
        );
    }

    /**
     * Resume a pipeline run from a specific step order.
     */
    public function resumeFromStep(PipelineRun $run, int $stepOrder, bool $background = false): PipelineRun
    {
        Log::info("Resuming pipeline run #{$run->id} from step order {$stepOrder}", [
            'background' => $background,
        ]);

        // Clean up subsequent step runs
        $run->stepRuns()->where('order', '>=', $stepOrder)->delete();

        $run->update([
            'status' => 'pending',
            'error' => null,
            'completed_at' => null,
            'paused_at' => null,
        ]);

        if ($background) {
            dispatch(new RunPipelineJob($run->id))->onQueue('default');

            return $run;
        }

        return $this->executeRun($run, $run->input);
    }

    /**
     * Stop the current pipeline execution and set a specific status.
     *
     * @param  string  $status  The status to set for the pipeline run (e.g., completed, failed, stopped).
     * @param  string  $reason  The reason for stopping the pipeline.
     */
    #[Actionable(
        name: 'End current pipeline',
        description: 'Stop the current pipeline execution and mark it with a specific status.',
        category: 'Automations'
    )]
    public function endCurrentPipeline(string $status = 'completed', string $reason = ''): void
    {
        app(PipelineExecutionService::class)->stopPipeline($reason, $status);
    }

    /**
     * Continue a paused or new pipeline run.
     */
    public function executeRun(PipelineRun $run, ?string $input = null, ?int $scheduleId = null, ?int $scheduleLogId = null, ?int $aiTaskId = null, ?int $endpointId = null, ?int $endpointLogId = null, bool $background = false): PipelineRun
    {
        Log::info('Executing pipeline run #'.$run->id, [
            'pipeline_id' => $run->pipeline_id,
            'status' => $run->status,
            'background' => $background,
        ]);

        if ($run->project_id && $run->project?->system_user_id) {
            Auth::onceUsingId($run->project->system_user_id);
        } elseif ($run->user_id) {
            Auth::onceUsingId($run->user_id);
        }

        $pipeline = $run->pipeline;

        if ($pipeline->project_id) {
            app()->instance('current_project_id', $pipeline->project_id);
        }

        $steps = $pipeline->steps()->where('is_failure_handler', false)->orderBy('order')->with('agent.skills', 'agent.tools', 'agent.knowledgeFiles')->get();

        app(PipelineExecutionService::class)->clear();
        app(PipelineExecutionService::class)->setCurrentRun($run);

        if ($steps->isEmpty()) {
            return $this->fail($run, 'Pipeline has no steps configured.');
        }

        // Detect if we are resuming from a genuine pause before clearing paused_at.
        $isResumingFromPause = $run->stepRuns()->where('status', 'paused')->exists();

        if ($run->status === 'stopped') {
            return $run;
        }

        $run->update([
            'status' => 'running',
            'paused_at' => null,
            'started_at' => $run->started_at ?? now(),
        ]);

        // Find where to start/resume.
        // If we are resuming from pause, always resume from the latest paused step run.
        // This avoids skipping a jumped-back step when a later step requested a jump
        // and then the target step paused (e.g. 6 -> 5 pause -> resume should start at 5).
        $lastPausedStepRun = $isResumingFromPause
            ? $run->stepRuns()->where('status', 'paused')->reorder()->latest('id')->first()
            : null;

        $lastFailedStepRun = $run->stepRuns()->where('status', 'failed')->reorder()->latest('id')->first();
        $lastCompletedStepRun = $run->stepRuns()->where('status', 'completed')->reorder()->latest('id')->first();
        $startIndex = 0;
        $previousOutput = $lastPausedStepRun?->output ?? $lastFailedStepRun?->output ?? $lastCompletedStepRun?->output;

        $resumeAnchorStepRun = $lastPausedStepRun ?? $lastFailedStepRun ?? $lastCompletedStepRun;

        if ($resumeAnchorStepRun) {
            $lastStep = $steps->firstWhere('order', $resumeAnchorStepRun->order);
            $lastStepIndex = $lastStep ? $steps->search(fn ($s) => $s->id === $lastStep->id) : -1;

            // If we are resuming from a pause or a failure, we want to stay on that step.
            // If we are resuming after a successful completion, we move to the next step.
            $nextIndex = ($lastPausedStepRun || $lastFailedStepRun) ? $lastStepIndex : $lastStepIndex + 1;

            if ($nextIndex >= count($steps)) {
                $run->update([
                    'status' => 'completed',
                    'output' => $previousOutput,
                    'completed_at' => now(),
                ]);

                return $run;
            }
            $startIndex = $nextIndex;
        }

        try {
            for ($i = $startIndex; $i < count($steps); $i++) {
                $step = $steps[$i];

                if (! $step->is_active) {
                    continue;
                }

                // For condition-only steps (no class/method), evaluate condition BEFORE running
                // For steps with both an action AND a condition, run the step first then evaluate
                $hasAction = ($step->class && $step->method) || $step->agent_id || $step->isDecisionStep();
                $actualPreviousOutput = $previousOutput;

                if ($step->condition_if && ! $hasAction) {
                    $conditionMet = $this->evaluateCondition(
                        $step->condition_if,
                        $input,
                        $actualPreviousOutput,
                        null,
                        $step->condition_on_input ?? true,
                        $step->condition_on_previous ?? true,
                        $step->condition_on_current ?? false
                    );
                    Log::debug("Pre-action condition check for step {$step->id} (order {$step->order}): Met=".($conditionMet ? 'YES' : 'NO')." Condition={$step->condition_if}");
                    $nextOrder = $conditionMet ? $step->next_step_on_true : $step->next_step_on_false;

                    if ($nextOrder !== null) {
                        $nextStep = $steps->firstWhere('order', $nextOrder);
                        if ($nextStep) {
                            $i = $steps->search(fn ($s) => $s->id === $nextStep->id) - 1; // -1 because for loop will increment

                            continue;
                        }
                    }
                }

                try {
                    $attempts = 0;
                    $maxAttempts = ($step->on_error === 'retry') ? ($step->retry_attempts ?: 0) : 0;

                    while (true) {
                        try {
                            Log::debug('Running step '.($i + 1).'/'.count($steps).' for run #'.$run->id, [
                                'step_id' => $step->id,
                                'displayName' => $step->displayName(),
                                'attempt' => $attempts + 1,
                            ]);
                            $previousOutput = $this->runStep($run, $step, $input, $previousOutput, $scheduleId, $scheduleLogId, $aiTaskId, $endpointId, $endpointLogId, $isResumingFromPause);
                            break;
                        } catch (Throwable $e) {
                            if ($e instanceof JumpToStepException || $e instanceof StopPipelineException || $e instanceof RunPipelineException || $e instanceof ActionPausedException) {
                                throw $e;
                            }

                            if ($attempts < $maxAttempts) {
                                $attempts++;
                                Log::warning("Step #{$step->id} failed, retrying ({$attempts}/{$maxAttempts})...", [
                                    'pipeline_run_id' => $run->id,
                                    'error' => $e->getMessage(),
                                ]);
                                if ($step->retry_delay > 0) {
                                    sleep($step->retry_delay);
                                }

                                continue;
                            }

                            throw $e;
                        }
                    }

                    Log::debug('Step '.($i + 1).' completed for run #'.$run->id);

                    if ($step->isDecisionStep()) {
                        $lastResult = app()->bound('last_decision_result') ? app('last_decision_result') : null;
                        $passed = $lastResult['passed'] ?? false;
                        $nextOrder = $passed ? $step->next_step_on_true : $step->next_step_on_false;
                        Log::debug("Decision step #{$step->id} check: Passed=".($passed ? 'YES' : 'NO')." NextOrder=".($nextOrder ?? 'default next'));

                        if ($nextOrder !== null) {
                            $nextStep = $steps->firstWhere('order', $nextOrder);
                            if ($nextStep) {
                                $i = $steps->search(fn ($s) => $s->id === $nextStep->id) - 1;

                                continue;
                            }
                        }
                    } elseif ($hasAction && $step->condition_if) {
                        $conditionMet = $this->evaluateCondition(
                            $step->condition_if,
                            $input,
                            $actualPreviousOutput,
                            $previousOutput,
                            $step->condition_on_input ?? true,
                            $step->condition_on_previous ?? true,
                            $step->condition_on_current ?? true
                        );
                        Log::debug("Post-action condition check for step {$step->id} (order {$step->order}): Met=".($conditionMet ? 'YES' : 'NO')." Condition={$step->condition_if}");
                        $nextOrder = $conditionMet ? $step->next_step_on_true : $step->next_step_on_false;

                        if ($nextOrder !== null) {
                            $nextStep = $steps->firstWhere('order', $nextOrder);
                            if ($nextStep) {
                                $i = $steps->search(fn ($s) => $s->id === $nextStep->id) - 1; // -1 because for loop will increment

                                continue;
                            }
                        }
                    }

                    $isResumingFromPause = false; // only the first step of a resume gets this flag
                } catch (Throwable $e) {
                    if ($e instanceof ActionPausedException) {
                        throw $e;
                    }

                    if ($e instanceof JumpToStepException) {
                        $nextStep = $steps->firstWhere('order', $e->stepOrder);
                        if ($nextStep) {
                            $i = $steps->search(fn ($s) => $s->id === $nextStep->id) - 1; // -1 because for loop will increment
                            $previousOutput = $e->params ? (is_array($e->params) ? json_encode($e->params) : $e->params) : $e->getMessage();
                            $previousOutput = '[JUMP OUTPUT]'.$previousOutput.'[/JUMP OUTPUT] ';

                            continue;
                        }

                        throw $e;
                    }

                    if ($e instanceof StopPipelineException) {
                        $run->update([
                            'status' => $e->getStatus(),
                            'output' => $e->getMessage(),
                            'completed_at' => now(),
                        ]);

                        return $run->fresh('stepRuns');
                    }

                    if ($e instanceof RunPipelineException) {
                        $run->update([
                            'status' => 'completed',
                            'output' => $e->getMessage(),
                            'completed_at' => now(),
                        ]);

                        // Trigger the new pipeline
                        $pipeline = is_numeric($e->pipelineIdOrName)
                            ? Pipeline::find($e->pipelineIdOrName)
                            : Pipeline::where('name', $e->pipelineIdOrName)->first();

                        if ($pipeline) {
                            $this->run(
                                pipeline: $pipeline,
                                input: $e->input ? json_encode($e->input) : null,
                                user: $run->user,
                                background: true
                            );
                        } else {
                            Log::error("New pipeline not found: {$e->pipelineIdOrName}");
                        }

                        return $run->fresh('stepRuns');
                    }

                    if ($step->on_error === 'continue') {
                        Log::warning("Step #{$step->id} failed, continuing to next step as per on_error policy.", [
                            'pipeline_run_id' => $run->id,
                            'error' => $e->getMessage(),
                        ]);
                        $previousOutput = $e->getMessage();
                        $isResumingFromPause = false;

                        continue;
                    }

                    if ($step->on_error === 'jump' && $step->next_step_on_error !== null) {
                        $nextStep = $steps->firstWhere('order', $step->next_step_on_error);
                        if ($nextStep) {
                            Log::warning("Step #{$step->id} failed, jumping to step {$step->next_step_on_error} as per on_error policy.", [
                                'pipeline_run_id' => $run->id,
                                'error' => $e->getMessage(),
                            ]);
                            $i = $steps->search(fn ($s) => $s->id === $nextStep->id) - 1;
                            $previousOutput = $e->getMessage();
                            $isResumingFromPause = false;

                            continue;
                        }
                    }

                    if ($background) {
                        Log::warning("Step #{$step->id} failed in background, but pipeline is configured to continue.", [
                            'pipeline_run_id' => $run->id,
                            'error' => $e->getMessage(),
                        ]);

                        // We continue to the next step.
                        // The output of the failed step is the error message, so subsequent steps can potentially handle it.
                        $previousOutput = $e->getMessage();
                        $isResumingFromPause = false;

                        continue;
                    }

                    throw $e;
                }

                // If step is paused (e.g. waiting for security), stop execution for now
                $freshStatus = $run->fresh()->status;
                if ($freshStatus === 'paused') {
                    return $run;
                }
                if ($freshStatus === 'stopped') {
                    $run->update(['completed_at' => now()]);

                    return $run;
                }

                // For steps with both an action AND a condition, evaluate AFTER execution
                // so the condition checks the step's own output
                if ($step->condition_if && $hasAction) {
                    $conditionMet = $this->evaluateCondition($step->condition_if, $input, $previousOutput);
                    $nextOrder = $conditionMet ? $step->next_step_on_true : $step->next_step_on_false;

                    if ($nextOrder !== null) {
                        $nextStep = $steps->firstWhere('order', $nextOrder);
                        if ($nextStep) {
                            $i = $steps->search(fn ($s) => $s->id === $nextStep->id) - 1;

                            continue;
                        }
                    }
                }
            }

            $run->update([
                'status' => 'completed',
                'output' => $previousOutput,
                'completed_at' => now(),
            ]);
        } catch (ActionPausedException $e) {
            return $run;
        } catch (Throwable $e) {
            if ($run->fresh()->status !== 'paused') {
                Log::error('Pipeline run failed', [
                    'pipeline_run_id' => $run->id,
                    'error' => $e->getMessage(),
                ]);

                $this->fail($run, $e->getMessage());

                // Execute failure handler step if configured
                $this->executeFailureHandler($run, $pipeline, $input, $previousOutput, $scheduleId, $scheduleLogId, $aiTaskId, $endpointId, $endpointLogId);
            }
        }

        return $run->fresh('stepRuns');
    }

    /**
     * Execute the pipeline's failure handler step, if one is configured and active.
     * This step runs only when the entire pipeline fails (not on caught step-level errors).
     */
    private function executeFailureHandler(
        PipelineRun $run,
        Pipeline $pipeline,
        ?string $originalInput,
        ?string $previousOutput,
        ?int $scheduleId = null,
        ?int $scheduleLogId = null,
        ?int $aiTaskId = null,
        ?int $endpointId = null,
        ?int $endpointLogId = null,
    ): void {
        $failureStep = $pipeline->failureStep()->with('agent.skills', 'agent.tools', 'agent.knowledgeFiles')->first();

        if (! $failureStep || ! $failureStep->is_active) {
            return;
        }

        Log::info('Executing failure handler step for pipeline run #'.$run->id, [
            'pipeline_id' => $pipeline->id,
            'step_id' => $failureStep->id,
            'step_name' => $failureStep->displayName(),
        ]);

        try {
            $output = $this->runStep(
                $run,
                $failureStep,
                $originalInput,
                $originalInput,
                $scheduleId,
                $scheduleLogId,
                $aiTaskId,
                $endpointId,
                $endpointLogId,
                false
            );

            // Mark the run as failed-but-handled so the user knows the failure step ran
            $run->refresh();
            $currentError = $run->error ?? '';
            $run->update([
                'output' => $output,
                'error' => $currentError."\n\n[Failure handler executed: {$failureStep->displayName()}]",
            ]);
        } catch (Throwable $fe) {
            Log::error('Failure handler step also failed for pipeline run #'.$run->id, [
                'error' => $fe->getMessage(),
            ]);
        }
    }

    /**
     * Evaluate a condition based on if/else/then logic.
     */
    public function evaluateCondition(
        string $condition,
        ?string $input,
        ?string $previousOutput,
        ?string $currentOutput = null,
        bool $onInput = true,
        bool $onPrevious = true,
        bool $onCurrent = true
    ): bool {
        if (! $onInput && ! $onPrevious && ! $onCurrent) {
            $onInput = true;
            $onPrevious = true;
            $onCurrent = ($currentOutput !== null);
        }

        // Check if condition is valid JSON
        $decoded = json_decode($condition, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['operator'])) {
            return $this->evaluateStructuredCondition($decoded, $input, $previousOutput, $currentOutput, $onInput, $onPrevious, $onCurrent);
        }

        $contexts = [];
        if ($onInput) {
            $contexts[] = $this->toString($input);
        }
        if ($onPrevious) {
            $contexts[] = $this->toString($previousOutput);
        }
        if ($onCurrent && $currentOutput !== null) {
            $contexts[] = $this->toString($currentOutput);
        }

        $context = implode(' ', $contexts);

        return str_contains(strtolower($context), strtolower($this->toString($condition)));
    }

    /**
     * Evaluate a structured condition (field, operator, value).
     */
    protected function evaluateStructuredCondition(
        array $condition,
        ?string $input,
        ?string $previousOutput,
        ?string $currentOutput = null,
        bool $onInput = true,
        bool $onPrevious = true,
        bool $onCurrent = true
    ): bool {
        $param = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $expected = $condition['value'] ?? null;

        $inputData = $onInput ? json_decode($input ?? '', true) : null;
        if ($inputData === null && $onInput && $input !== null && strtolower($input) !== 'null') {
            $inputData = $input;
        }

        $previousOutputData = $onPrevious ? json_decode($previousOutput ?? '', true) : null;
        if ($previousOutputData === null && $onPrevious && $previousOutput !== null && strtolower($previousOutput) !== 'null') {
            $previousOutputData = $previousOutput;
        }

        $currentOutputData = ($onCurrent && $currentOutput !== null) ? json_decode($currentOutput, true) : null;
        if ($currentOutputData === null && $onCurrent && $currentOutput !== null && strtolower($currentOutput) !== 'null') {
            $currentOutputData = $currentOutput;
        }

        $actual = null;

        // Priority: Current -> Previous -> Input
        if ($onCurrent && $currentOutputData !== null) {
            $actual = data_get($currentOutputData, $param);
            if ($actual === null && $param !== '' && is_array($currentOutputData) && array_is_list($currentOutputData)) {
                $actual = data_get($currentOutputData, "0.{$param}");
            }
        }

        if ($actual === null && $onPrevious && $previousOutputData !== null) {
            $actual = data_get($previousOutputData, $param);
            if ($actual === null && $param !== '' && is_array($previousOutputData) && array_is_list($previousOutputData)) {
                $actual = data_get($previousOutputData, "0.{$param}");
            }
        }

        if ($actual === null && $onInput && $inputData !== null && $param !== '') {
            $actual = data_get($inputData, $param);
            if ($actual === null && is_array($inputData) && array_is_list($inputData)) {
                $actual = data_get($inputData, "0.{$param}");
            }
        }

        // If field is empty, we check the whole data
        if ($param === '') {
            $actual = $currentOutputData ?? $previousOutputData ?? $inputData;
        }

        return match (strtolower($operator)) {
            '=', '==', 'equals' => $actual == $expected,
            '!=', '<>', 'not_equals' => $actual != $expected,
            '>', 'greater_than', 'is_greater' => $actual > $expected,
            '>=' => $actual >= $expected,
            '<', 'less_than', 'is_smaller' => $actual < $expected,
            '<=' => $actual <= $expected,
            'contains' => str_contains($this->toString($actual), $this->toString($expected)),
            'does_not_contain', 'not_contains' => ! str_contains($this->toString($actual), $this->toString($expected)),
            'starts_with' => str_starts_with($this->toString($actual), $this->toString($expected)),
            'ends_with' => str_ends_with($this->toString($actual), $this->toString($expected)),
            'is_empty' => empty($actual),
            'is_not_empty' => ! empty($actual),
            'exists' => $actual !== null,
            'in' => in_array($actual, is_array($expected) ? $expected : explode(',', $this->toString($expected))),
            'not_in' => ! in_array($actual, is_array($expected) ? $expected : explode(',', $this->toString($expected))),
            'matches' => (bool) preg_match($this->toString($expected), $this->toString($actual)),
            default => false,
        };
    }

    /**
     * Execute a single pipeline step and persist its result.
     *
     * @throws Throwable
     */
    public function runStep(
        PipelineRun $run,
        PipelineStep $step,
        ?string $originalInput,
        ?string $previousOutput,
        ?int $scheduleId = null,
        ?int $scheduleLogId = null,
        ?int $aiTaskId = null,
        ?int $endpointId = null,
        ?int $endpointLogId = null,
        bool $isResumingFromPause = false
    ): string {
        app()->instance('current_pipeline_run_id', $run->id);
        app()->instance('current_pipeline_run', $run);
        if ($scheduleId) {
            app()->instance('current_schedule_id', $scheduleId);
        }
        if ($scheduleLogId) {
            app()->instance('current_schedule_log_id', $scheduleLogId);
        }
        if ($aiTaskId) {
            app()->instance('current_ai_task_id', $aiTaskId);
        }
        if ($endpointId) {
            app()->instance('current_endpoint_id', $endpointId);
        }
        if ($endpointLogId) {
            app()->instance('current_endpoint_log_id', $endpointLogId);
        }

        // A wait resumption only applies when we are genuinely resuming from a pause
        // AND the last step run for this step is a paused wait. Using $isResumingFromPause
        // (set in executeRun before the loop) avoids false positives when the pipeline
        // loops back and hits the same wait step again in a new iteration.
        $lastStepRun = $run->stepRuns()->where('pipeline_step_id', $step->id)->reorder()->latest('id')->first();
        $isWaitResumption = $isResumingFromPause
            && $lastStepRun
            && $lastStepRun->status === 'paused'
            && str_contains($lastStepRun->output ?? '', 'Execution paused until');

        $stepRun = PipelineRunStep::create([
            'pipeline_run_id' => $run->id,
            'pipeline_step_id' => $step->id,
            'order' => $step->order,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $agent = null;
        try {
            $previousStepRuns = PipelineRunStep::where('pipeline_run_id', $run->id)
                ->where('status', 'completed')
                ->whereIn('id', function ($query) use ($run) {
                    $query->selectRaw('MAX(id)')
                        ->from('pipeline_run_steps')
                        ->where('pipeline_run_id', $run->id)
                        ->where('status', 'completed')
                        ->groupBy('pipeline_step_id');
                })
                ->with('pipelineStep')
                ->orderBy('order')
                ->get();

            $allPreviousOutputs = [];
            foreach ($previousStepRuns as $index => $psr) {
                $absOrder = $psr->pipelineStep?->order ?? $psr->order;
                $allPreviousOutputs[$absOrder + 1] = $psr->output;
                if ($absOrder > 0) {
                    $allPreviousOutputs[$absOrder] = $psr->output;
                }
                $allPreviousOutputs[$index + 1] = $psr->output;
            }

            if ($step->mcp_actionable_id || ($step->class && $step->method)) {
                if ($isWaitResumption) {
                    $output = 'Wait duration finished.';
                } else {
                    $instructions = $this->replaceShortcodes($step->instructions_override ?? '[]', $run->pipeline);
                    $params = json_decode($instructions, true);

                    if (is_array($params)) {
                        $params = $this->replaceOutputVariables($params, $previousOutput, $allPreviousOutputs, $originalInput);

                        Log::debug('Executing action for step '.$step->id, [
                            'mcp_actionable_id' => $step->mcp_actionable_id,
                            'class' => $step->class,
                            'method' => $step->method,
                            'params' => $params,
                        ]);

                        // If the instructions are in the JsonStructureHelper format, convert them to a simple key-value array
                        if (! empty($params) && isset($params[0]) && is_array($params[0]) && isset($params[0]['name'])) {
                            $formattedParams = [];
                            foreach ($params as $param) {
                                if (isset($param['name'])) {
                                    $value = $param['value'] ?? null;
                                    $type = $param['type'] ?? 'string';

                                    if ($value !== null) {
                                        $value = $this->parseParameterValue($value, $type, $param['name']);
                                    }

                                    $formattedParams[$param['name']] = $value;
                                }
                            }
                            $params = $formattedParams;
                        }
                    } else {
                        $params = [];
                    }

                    if ($step->mcp_actionable_id) {
                        $actionable = $step->mcpActionable;
                        if (! $actionable) {
                            throw new \Exception("MCP Actionable not found for step #{$step->id}");
                        }
                        $result = app(McpActionableService::class)->execute($actionable, $params);
                    } else {
                        Log::debug('Calling local action for step '.$step->id, [
                            'class' => $step->class,
                            'method' => $step->method,
                            'params' => $params,
                        ]);
                        try {
                            $instance = app($step->class);
                            $result = $instance->{$step->method}(...$params);
                            Log::debug('Local action succeeded for step '.$step->id, [
                                'result' => is_string($result) ? $result : json_encode($result),
                            ]);
                        } catch (Throwable $e) {
                            Log::error('Local action failed for step '.$step->id, [
                                'class' => $step->class,
                                'method' => $step->method,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                            throw $e;
                        }
                    }

                    $output = is_string($result) ? $result : json_encode($result, JSON_PRETTY_PRINT);

                    $executionService = app(PipelineExecutionService::class);
                    if ($executionService->hasStopped()) {
                        $reason = $executionService->getStopReason();
                        $status = $executionService->getStopStatus();
                        $executionService->clear();
                        throw new StopPipelineException($reason, $status);
                    }

                    if ($executionService->hasJumped()) {
                        $order = $executionService->getJumpOrder();
                        $reason = $executionService->getJumpReason();
                        $params = $executionService->getJumpParams();
                        $executionService->clear();
                        throw new JumpToStepException($order, $reason, $params);
                    }

                    if ($executionService->hasNextPipeline()) {
                        $pipelineIdOrName = $executionService->getNextPipelineIdOrName();
                        $reason = $executionService->getRunPipelineReason();
                        $input = $executionService->getNextPipelineInput();
                        $executionService->clear();
                        throw new RunPipelineException($pipelineIdOrName, $reason, $input);
                    }
                }

                $stepRun->update([
                    'status' => 'completed',
                    'output' => $output,
                    'completed_at' => now(),
                ]);

                return $output;
            }

            if ($step->isDecisionStep()) {
                $decisionConfig = $step->decision_config ?? [];
                $instructions = $decisionConfig['instructions'] ?? $step->instructions_override ?? '';
                if ($instructions) {
                    $instructions = $this->replaceShortcodes($instructions, $run->pipeline);
                    $instructions = $this->replaceOutputVariables($instructions, $previousOutput, $allPreviousOutputs, $originalInput);
                }

                $stateToEvaluate = $previousOutput ?? $originalInput;
                if ($step->pass_all_previous_outputs && ! empty($allPreviousOutputs)) {
                    $stateToEvaluate = $allPreviousOutputs;
                }

                $threshold = isset($decisionConfig['confidence_threshold'])
                    ? (float) $decisionConfig['confidence_threshold']
                    : 0.80;
                $questionType = $decisionConfig['question_type'] ?? 'noul';
                $criteria = $decisionConfig['criteria'] ?? null;
                $modelOverride = $step->model ?: ($decisionConfig['model'] ?? null);
                $providerOverride = $decisionConfig['provider_id'] ?? null;

                $decisionService = app(DecisionModelService::class);
                $result = $decisionService->evaluate(
                    state: $stateToEvaluate,
                    instructions: $instructions,
                    threshold: $threshold,
                    providerIdentifier: $providerOverride,
                    modelIdentifier: $modelOverride,
                    questionType: $questionType,
                    criteria: $criteria,
                    user: $run->user,
                    projectId: $run->pipeline?->project_id
                );

                $output = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                $stepRun->update([
                    'status' => 'completed',
                    'input' => is_string($stateToEvaluate) ? $stateToEvaluate : json_encode($stateToEvaluate),
                    'output' => $output,
                    'completed_at' => now(),
                ]);

                app()->instance('last_decision_result', $result);

                return $output;
            }

            if (! $step->agent_id) {
                // Pass-through / Receive Data step
                $output = $previousOutput ?? $originalInput;

                $stepRun->update([
                    'status' => 'completed',
                    'input' => $originalInput,
                    'output' => $output,
                    'completed_at' => now(),
                ]);

                return $output;
            }

            app()->instance('current_agent_id', $step->agent->id);

            // Prepare agent with optional step-level instruction override
            $instructions = ! empty($step->instructions_override) ? $this->replaceShortcodes($step->instructions_override, $run->pipeline) : null;
            if ($instructions) {
                $instructions = $this->replaceOutputVariables($instructions, $previousOutput, $allPreviousOutputs, $originalInput);
            }

            $agentClass = $step->force_json ? PipelineStructuredAgent::class : DynamicAgent::class;

            $agent = new $agentClass(
                $step->agent,
                scheduleId: $scheduleId,
                isHeadless: true,
                pipelineRunId: $run->id,
                aiTaskId: $aiTaskId,
                endpointId: $endpointId,
                scheduleLogId: $scheduleLogId,
                pipelineStepOrder: $step->order,
                endpointLogId: $endpointLogId,
                user: $run->user,
                extraInstructions: $instructions
            );

            // Resolve provider & model from DB config (same pattern as AgentChat)
            $providerConfig = InjectCurrentAiConfigService::inject($step->agent);

            $initialPrompt = $this->buildPrompt($step, $originalInput, $previousOutput, $allPreviousOutputs, $previousStepRuns);
            $prompt = $initialPrompt;

            if ($isWaitResumption) {
                $prompt = "The wait duration has finished. Please proceed with your task. For context, here is the task you were performing:\n\n".$initialPrompt;
            } elseif ($isResumingFromPause && $lastStepRun && $lastStepRun->status === 'paused' && ! str_contains($lastStepRun->output ?? '', 'Execution paused until')) {
                $prompt = "Security authorization successful. Please proceed with your previous action. For context, here is the task you were performing:\n\n".$initialPrompt;
            }

            if ($step->force_json) {
                $prompt .= "\n\nIMPORTANT: You MUST respond with valid JSON only. Your entire response must be a parseable JSON object with at least a 'result' key.";
            }

            $stepRun->update(['input' => $prompt]);

            $response = $agent->prompt(
                $prompt,
                provider: $providerConfig['provider_name'],
                model: $providerConfig['model'],
                timeout: $step->timeout ?: null,
            );

            $executionService = app(PipelineExecutionService::class);
            if ($executionService->hasStopped()) {
                $reason = $executionService->getStopReason();
                $status = $executionService->getStopStatus();
                $executionService->clear();
                throw new StopPipelineException($reason, $status);
            }

            if ($executionService->hasJumped()) {
                $order = $executionService->getJumpOrder();
                $reason = $executionService->getJumpReason();
                $params = $executionService->getJumpParams();
                $executionService->clear();
                throw new JumpToStepException($order, $reason, $params);
            }

            if ($executionService->hasNextPipeline()) {
                $pipelineIdOrName = $executionService->getNextPipelineIdOrName();
                $reason = $executionService->getRunPipelineReason();
                $input = $executionService->getNextPipelineInput();
                $executionService->clear();
                throw new RunPipelineException($pipelineIdOrName, $reason, $input);
            }

            $output = $response->text;

            $tokensUsed = 0;
            if ($response->usage) {
                $tokensUsed = ($response->usage->promptTokens ?? 0) + ($response->usage->completionTokens ?? 0);
            }

            $stepRun->update([
                'status' => 'completed',
                'output' => $output,
                'tokens_used' => $tokensUsed,
                'completed_at' => now(),
            ]);

            return $output;
        } catch (JumpToStepException $e) {
            $stepRun->update([
                'status' => 'completed',
                'output' => $e->getMessage(),
                'tokens_used' => $this->getCapturedTokens($agent),
                'completed_at' => now(),
            ]);

            throw $e;
        } catch (StopPipelineException $e) {
            $stepRun->update([
                'status' => 'completed',
                'output' => $e->getMessage(),
                'tokens_used' => $this->getCapturedTokens($agent),
                'completed_at' => now(),
            ]);

            throw $e;
        } catch (RunPipelineException $e) {
            $stepRun->update([
                'status' => 'completed',
                'output' => $e->getMessage(),
                'tokens_used' => $this->getCapturedTokens($agent),
                'completed_at' => now(),
            ]);

            throw $e;
        } catch (ActionPausedException $e) {
            $stepRun->update([
                'status' => 'paused',
                'output' => $e->getMessage(),
                'tokens_used' => $this->getCapturedTokens($agent),
                'completed_at' => now(),
            ]);

            $run->update([
                'status' => 'paused',
                'paused_at' => now(),
            ]);

            throw $e;
        } catch (Throwable $e) {
            $stepRun->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'tokens_used' => $this->getCapturedTokens($agent),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Try to capture tokens used even if an exception occurred.
     */
    private function getCapturedTokens(?DynamicAgent $agent): int
    {
        if (! $agent) {
            return 0;
        }

        // For OpenRouter, we can try to get the tokens from the last captured cost if we have pricing info,
        // but it's easier to just check if we have any other way.
        // Since we don't have a robust way to get tokens from all providers on failure yet,
        // we'll at least try to see if the agent has them (if we implement CaptureUsage middleware later).

        return 0; // Placeholder for now, but we've fixed AiUsageLog via middleware
    }

    /**
     * Compose the full prompt for a step by combining the original
     * user input with the previous step's output (when configured).
     */
    private function buildPrompt(PipelineStep $step, ?string $originalInput, ?string $previousOutput, ?array $allPreviousOutputs = null, $previousStepRuns = null): string
    {
        $originalInput = $originalInput ?? '';
        $promptParts = [];

        if ($step->pass_all_previous_outputs && ! empty($allPreviousOutputs)) {
            $promptParts[] = "## All Previous Steps Outputs (JSON)\n".json_encode($allPreviousOutputs, JSON_PRETTY_PRINT);
        }

        if (! empty($step->selected_outputs) && $previousStepRuns) {
            $selectedContext = '';
            $minOrder = $step->pipeline?->steps?->min('order') ?? 0;
            foreach ($previousStepRuns as $psr) {
                if (in_array($psr->order, $step->selected_outputs)) {
                    $stepNum = ($minOrder === 1) ? $psr->order : ($psr->order + 1);
                    $stepLabel = 'STEP '.$stepNum;
                    $selectedContext .= "[OUTPUT {$stepLabel}]{$psr->output}[/OUTPUT {$stepLabel}]\n\n";
                }
            }

            if (! empty($selectedContext)) {
                $promptParts[] = "## Selected Previous Outputs\n".trim($selectedContext);
            }
        } elseif ($previousOutput !== null && $step->pass_output_as_input) {
            $promptParts[] = "## Previous Step Output\n{$previousOutput}";
        }

        if (empty($promptParts)) {
            return $originalInput ?: 'Proceed with the next step according to your instructions.';
        }

        $promptParts[] = "## Your Task\n".($originalInput ?: 'Proceed with the next step.');

        return implode("\n\n", $promptParts);
    }

    /**
     * Replace shortcodes in text with pipeline variables.
     */
    private function replaceShortcodes(?string $text, Pipeline $pipeline): string
    {
        if (empty($text)) {
            return '';
        }

        $variables = $pipeline->config['variables'] ?? [];
        foreach ($variables as $variable) {
            if (! empty($variable['key'])) {
                $text = str_replace('{{'.$variable['key'].'}}', $this->toString($variable['value'] ?? ''), $text);
            }
        }

        return $text;
    }

    /**
     * Mark the run as failed and return it.
     */
    private function fail(PipelineRun $run, string $message): PipelineRun
    {
        $run->update([
            'status' => 'failed',
            'error' => $message,
            'completed_at' => now(),
        ]);

        return $run;
    }

    /**
     * Safely convert a value to a string, handling arrays.
     */
    private function toString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return is_array($value) ? json_encode($value) : (string) $value;
    }

    /**
     * Helper to flatten j- prefixes in an array.
     */
    private function flattenJPrefixes(array $data): array
    {
        foreach ($data as $key => $val) {
            if (! is_string($key)) {
                continue;
            }

            $newKey = null;
            if (str_starts_with($key, 'j-data_')) {
                $newKey = substr($key, 7);
            } elseif (str_starts_with($key, 'j-meta_')) {
                $newKey = substr($key, 7);
            } elseif (str_starts_with($key, 'j-')) {
                $newKey = substr($key, 2);
            }

            if ($newKey !== null) {
                if (! isset($data[$newKey])) {
                    $data[$newKey] = $val;
                }

                // Also support j-prefix for data/meta if it wasn't already there
                $jPrefixKey = 'j-'.$newKey;
                if (! isset($data[$jPrefixKey])) {
                    $data[$jPrefixKey] = $val;
                }
            }
        }

        return $data;
    }

    /**
     * Recursively replace output variables in parameters.
     */
    protected function replaceOutputVariables(mixed $value, ?string $previousOutput, ?array $allPreviousOutputs = null, ?string $originalInput = null): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $subValue) {
                $value[$key] = $this->replaceOutputVariables($subValue, $previousOutput, $allPreviousOutputs, $originalInput);
            }

            return $value;
        }

        if (! is_string($value) || ! str_contains($value, '{')) {
            return $value;
        }

        $decodedPrevious = json_decode($previousOutput ?? '', true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decodedPrevious)) {
            $decodedPrevious = Helpers::extractJsonFromText($previousOutput);
        }

        // Build the output data map:
        // - 'output' / 'previous_output' / 'agent_output' always hold the raw full string
        // - If we extracted structured data, merge those fields for dot-notation access
        $outputData = [
            'output' => $previousOutput,
            'previous_output' => $previousOutput,
            'agent_output' => $previousOutput,
        ];
        if (is_array($decodedPrevious)) {
            $outputData = array_merge($outputData, $decodedPrevious);
        }

        $inputData = json_decode($originalInput ?? '', true) ?? [];

        // If output is a list, wrap it in 'items' and merge first item for convenience
        if (is_array($outputData) && array_is_list($outputData)) {
            $items = $outputData;
            $outputData = [
                'items' => $items,
            ] + $items;

            if (count($items) >= 1 && is_array($items[0])) {
                $outputData = array_merge($outputData, $items[0]);
            }

            // Flatten j-data_, j-meta_ and j- prefixes for easier access (e.g. {url} or {j-url} instead of {j-data_url})
            $outputData = $this->flattenJPrefixes($outputData);

            // Also flatten items in the list for nested access like {items.0.aic_code}
            if (isset($outputData['items']) && is_array($outputData['items'])) {
                foreach ($outputData['items'] as $index => $item) {
                    if (is_array($item)) {
                        $outputData['items'][$index] = $this->flattenJPrefixes($item);
                        // Also update the direct index keys in outputData (0, 1, 2...)
                        if (isset($outputData[$index]) && $outputData[$index] === $item) {
                            $outputData[$index] = $outputData['items'][$index];
                        }
                    }
                }
            }

            // Also ensure 'data' exists, if not set by merge, set it to the whole list
            if (! isset($outputData['data'])) {
                $outputData['data'] = $items;
            }
        }

        if ($allPreviousOutputs) {
            foreach ($allPreviousOutputs as $key => $stepOutput) {
                $raw = is_string($stepOutput) ? $stepOutput : json_encode($stepOutput);
                $decoded = is_string($stepOutput) ? json_decode($stepOutput, true) : $stepOutput;
                if (! is_array($decoded)) {
                    $decoded = Helpers::extractJsonFromText($raw);
                }
                // Store the raw full-text output so {N} always returns the complete output.
                // Also store any extracted JSON fields under _{N} for dot-notation access.
                $outputData[(string) $key] = $raw;
                if (is_array($decoded)) {
                    $outputData['_'.$key] = $decoded;
                }
            }
        }

        // Merge initial input data if available, so it can be accessed via {field}.
        // Use array_replace instead of array_merge to preserve integer keys —
        // array_merge renumbers numeric keys, breaking {1.url} resolution.
        if (! empty($inputData) && is_array($inputData)) {
            $outputData = array_replace($inputData, $outputData);
        }

        if (empty($outputData)) {
            return $value;
        }

        // Handle exact match for non-string values: e.g. parameter value is exactly "{field}"
        if (preg_match('/^{([a-zA-Z0-9_.\-\ ]+)}$/', $value, $matches)) {
            $key = trim($matches[1]);
            if (isset($outputData['_'.$key])) {
                return $outputData['_'.$key];
            }

            if (preg_match('/^(\d+)\.(.+)$/', $key, $subMatches)) {
                $val = data_get($outputData, "_{$subMatches[1]}.{$subMatches[2]}");
                if ($val !== null) {
                    return $val;
                }
            }

            return data_get($outputData, $key, $value);
        }

        // Handle multiple placeholders in a string
        return preg_replace_callback('/{([a-zA-Z0-9_.\-\ ]+)}/', function ($matches) use ($outputData, $value) {
            $key = trim($matches[1]);
            $val = data_get($outputData, $key);
            if ($val === null && preg_match('/^(\d+)\.(.+)$/', $key, $subMatches)) {
                $val = data_get($outputData, "_{$subMatches[1]}.{$subMatches[2]}");
            }
            if ($val === null) {
                return $matches[0];
            }
            if (is_array($val) || is_object($val)) {
                return json_encode($val);
            }

            // When the enclosing value looks like it's JSON (starts with [ or {),
            // escape the replacement so it doesn't break JSON syntax.
            if (preg_match('/^\s*[\[\{]/', $value)) {
                $encoded = json_encode((string) $val);

                return substr($encoded, 1, -1);
            }

            return (string) $val;
        }, $value);
    }
}
