<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineRunStep extends Model
{
    protected $fillable = [
        'pipeline_run_id',
        'pipeline_step_id',
        'order',
        'status',
        'input',
        'output',
        'error',
        'tokens_used',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'tokens_used' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The parent pipeline run.
     */
    public function pipelineRun(): BelongsTo
    {
        return $this->belongsTo(PipelineRun::class);
    }

    /**
     * The pipeline step definition this result corresponds to.
     */
    public function pipelineStep(): BelongsTo
    {
        return $this->belongsTo(PipelineStep::class);
    }

    /**
     * Duration in seconds between started_at and completed_at.
     */
    public function durationInSeconds(): ?float
    {
        if ($this->started_at === null || $this->completed_at === null) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }
}
