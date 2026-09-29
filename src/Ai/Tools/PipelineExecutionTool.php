<?php

namespace Agentoom\Core\Ai\Tools;

use Agentoom\Core\Contracts\PrivacyIntegration;
use Agentoom\Core\Services\PipelineExecutionService;
use Agentoom\Core\Traits\HandlesPrivacy;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PipelineExecutionTool implements Tool
{
    use HandlesPrivacy;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Allows interacting with and controlling the current pipeline execution.
        Use this tool to jump to a specific step, stop the execution entirely, run a new pipeline, or retrieve details about the current execution.
        Jumping is useful for loops, skipping steps, or retrying previous steps.
        Stopping is useful when instructed, the final goal has been reached or if an unrecoverable error occurs.
        Running a new pipeline is useful for handing off tasks to a different workflow. When a new pipeline is started, the current one completes successfully.
        Retrieving details is useful to understand the context of the current execution.';
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Stringable|string
    {
        $action = (string) $request->string('action', 'jump');
        $reason = (string) $request->string('reason', 'Agent requested action');

        // Deanonymize any <REDACTED-...> placeholders in the reason string
        $mappingKey = $this->resolvePrivacyMappingKey();
        if ($mappingKey !== null && str_contains($reason, 'REDACTED-')) {
            $reason = app(PrivacyIntegration::class)->restore($reason, $mappingKey);
        }

        if ($action === 'get_details') {
            $run = app(PipelineExecutionService::class)->getCurrentRun();
            if (! $run) {
                return 'Error: No active pipeline run found.';
            }

            return $this->anonymizeResponse(json_encode([
                'id' => $run->id,
                'pipeline_id' => $run->pipeline_id,
                'pipeline_name' => $run->pipeline?->name,
                'status' => $run->status,
                'input' => $run->input,
                'started_at' => $run->started_at?->toDateTimeString(),
            ], JSON_PRETTY_PRINT), $mappingKey);
        }

        if ($action === 'stop') {
            Log::info("Pipeline stop requested: {$reason}");
            app(PipelineExecutionService::class)->stopPipeline($reason);

            return $this->anonymizeResponse("Pipeline execution has been stopped. Reason: {$reason}", $mappingKey);
        }

        if ($action === 'jump' && ! $request->has('step_order')) {
            return 'Error: step_order is required when jumping to a different step.';
        }

        if ($action === 'jump' && $request->integer('step_order') <= 0) {
            return 'Error: step_order must be a positive integer.';
        }
        if ($action === 'jump') {
            $stepOrder = $request->integer('step_order');
            $params = $request['params'] ?? null;

            app(PipelineExecutionService::class)->jumpToStep($stepOrder - 1, $reason, is_array($params) ? $params : null);

            return $this->anonymizeResponse("Jumping to step {$stepOrder}", $mappingKey);
        }

        if ($action === 'run_pipeline') {
            $pipeline = (string) $request->string('pipeline_id_or_name');
            if (empty($pipeline)) {
                return 'Error: pipeline_id_or_name is required when running a new pipeline.';
            }
            $input = $request['params'] ?? null;

            app(PipelineExecutionService::class)->runPipeline($pipeline, is_array($input) ? $input : null, $reason);

            return $this->anonymizeResponse("Handing off to pipeline: {$pipeline}. Current pipeline will be marked as completed.", $mappingKey);
        }

        return 'Error: Invalid action. Use "jump", "stop", "run_pipeline", or "get_details".';

    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['jump', 'stop', 'run_pipeline', 'get_details'])
                ->required()
                ->description('The action to perform: "jump" to a different step, "stop" to terminate, "run_pipeline" to start a new one, or "get_details" to retrieve information about the current execution.'),
            'step_order' => $schema->integer()
                ->description('The order number of the step you want to jump to (required if action is "jump").'),
            'pipeline_id_or_name' => $schema->string()
                ->description('The ID or Name of the pipeline you want to run (required if action is "run_pipeline").'),
            'reason' => $schema->string()
                ->description('The reason why you are performing this action.'),
            'params' => $schema->object()
                ->description('Optional data to pass to the target step or new pipeline.'),
        ];
    }
}
