<?php

namespace Agentoom\Core\Ai\Agents;

use Agentoom\Core\Models\PipelineStep;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;

class PipelineStructuredAgent extends DynamicAgent implements HasStructuredOutput
{
    /**
     * Provide a JSON schema for structured output when force_json is enabled on the pipeline step.
     */
    public function schema(JsonSchema $schema): array
    {
        if (! $this->pipelineRunId || $this->pipelineStepOrder === null) {
            return [];
        }

        $step = PipelineStep::where('pipeline_id', function ($q) {
            $q->select('pipeline_id')->from('pipeline_runs')->where('id', $this->pipelineRunId);
        })->where('order', $this->pipelineStepOrder)->first();

        if (! $step || ! $step->force_json) {
            return [];
        }

        $instructions = $step->instructions_override;
        if (empty($instructions)) {
            return [];
        }

        $fields = json_decode($instructions, true);
        if (! is_array($fields) || ! isset($fields[0]['name'])) {
            return [];
        }

        $schemaFields = [];
        foreach ($fields as $field) {
            $name = $field['name'] ?? null;
            $type = $field['type'] ?? 'string';
            $description = $field['description'] ?? null;
            if (empty($name)) {
                continue;
            }

            $builder = match ($type) {
                'integer' => $schema->integer(),
                'boolean' => $schema->boolean(),
                'number', 'float' => $schema->number(),
                default => $schema->string(),
            };

            if ($description) {
                $builder = $builder->description($description);
            }

            $schemaFields[$name] = $builder;
        }

        return $schemaFields;
    }
}
