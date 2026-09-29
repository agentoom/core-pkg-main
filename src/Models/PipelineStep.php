<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineStep extends Model
{
    protected static function booted()
    {
        static::saving(function (PipelineStep $step) {
            if (empty($step->instructions_override)) {
                return;
            }

            // Only apply prefixing to agent steps.
            // MCP Actionable steps, Class/Method steps, and pass-through steps
            // use instructions_override for JSON parameters or nothing at all,
            // and prepending text would break json_decode in PipelineRunnerService
            // or corrupt stored data.
            if (! $step->agent_id) {
                return;
            }

            $stepNumber = $step->order + 1;
            $now = now()->toDateTimeString();
            $prefix = "[STEP NUMBER {$stepNumber} saved on {$now}]";

            $instructions = $step->instructions_override;

            // Detect if it's JSON (e.g. from some other custom usage, though rare for agent steps)
            $decoded = json_decode($instructions, true);
            $isJson = json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded));

            if ($isJson) {
                // If it's an array of objects (JsonStructureHelper format)
                if (is_array($decoded) && isset($decoded[0]) && is_array($decoded[0]) && isset($decoded[0]['name'])) {
                    // Check if first element is already our cache buster
                    if ($decoded[0]['name'] === '_cache_buster') {
                        array_shift($decoded);
                    }
                    array_unshift($decoded, [
                        'name' => '_cache_buster',
                        'type' => 'string',
                        'value' => $prefix,
                        'description' => 'Automatic cache invalidation',
                    ]);
                    $step->instructions_override = json_encode($decoded);
                } elseif (is_array($decoded) && ! array_is_list($decoded)) {
                    // Other JSON object format
                    $decoded['_cache_buster'] = $prefix;
                    $step->instructions_override = json_encode($decoded);
                }
            } else {
                // Plain text - Remove existing prefix and prepend new one
                $instructions = preg_replace('/^(\[STEP NUMBER \d+ saved on .*?\]\s*)+/', '', $instructions);
                $step->instructions_override = $prefix.' '.$instructions;
            }
        });
    }

    protected $attributes = [
        'on_error' => 'continue',
        'retry_attempts' => 0,
        'retry_delay' => 0,
        'is_active' => true,
        'is_failure_handler' => false,
        'condition_on_previous' => false,
        'condition_on_input' => false,
        'condition_on_current' => false,
    ];

    protected $fillable = [
        'pipeline_id',
        'agent_id',
        'class',
        'method',
        'order',
        'mcp_actionable_id',
        'is_active',
        'is_failure_handler',
        'name',
        'instructions_override',
        'pass_output_as_input',
        'pass_all_previous_outputs',
        'selected_outputs',
        'condition_if',
        'next_step_on_true',
        'next_step_on_false',
        'on_error',
        'next_step_on_error',
        'retry_attempts',
        'retry_delay',
        'timeout',
        'condition_on_previous',
        'condition_on_input',
        'condition_on_current',
        'force_json',
        'model',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_active' => 'boolean',
            'pass_output_as_input' => 'boolean',
            'pass_all_previous_outputs' => 'boolean',
            'selected_outputs' => 'array',
            'next_step_on_true' => 'integer',
            'next_step_on_false' => 'integer',
            'next_step_on_error' => 'integer',
            'retry_attempts' => 'integer',
            'retry_delay' => 'integer',
            'timeout' => 'integer',
            'condition_on_previous' => 'boolean',
            'condition_on_input' => 'boolean',
            'condition_on_current' => 'boolean',
            'force_json' => 'boolean',
            'model' => 'string',
        ];
    }

    /**
     * The pipeline this step belongs to.
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * The agent assigned to this step.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * The MCP actionable assigned to this step.
     */
    public function mcpActionable(): BelongsTo
    {
        return $this->belongsTo(McpActionable::class);
    }

    /**
     * Resolve the effective display name for this step.
     */
    public function displayName(): string
    {
        return $this->name ?? $this->agent?->name ?? $this->method ?? 'Step '.($this->order + 1);
    }
}
