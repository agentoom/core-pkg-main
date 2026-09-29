<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Services\PipelineRunnerService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PipelineRunShow extends Component
{
    #[Locked]
    public int $pipelineId;

    #[Locked]
    public int $runId;

    public function mount(Pipeline $pipeline, PipelineRun $run): void
    {
        $this->pipelineId = $pipeline->id;
        $this->runId = $run->id;
    }

    #[Computed]
    public function pipeline(): Pipeline
    {
        return Pipeline::find($this->pipelineId);
    }

    #[Computed]
    public function run(): PipelineRun
    {
        return PipelineRun::with('stepRuns.pipelineStep.agent', 'user')->find($this->runId);
    }

    public function refresh(): void
    {
        // Computed properties will refresh automatically when accessed in the view
    }

    public function continueFromStep(int $stepOrder): void
    {
        $run = $this->run;
        app(PipelineRunnerService::class)->resumeFromStep($run, $stepOrder, background: true);

        Flux::toast(
            text: __('agentoom-core::admin.pipelines.resuming_from_step', ['step' => $stepOrder + 1]),
            variant: 'success',
        );
    }

    public function retry(): void
    {
        $run = $this->run;
        app(PipelineRunnerService::class)->resumeFromStep($run, 0, background: true);

        Flux::toast(
            text: __('agentoom-core::admin.pipelines.retrying_run'),
            variant: 'success',
        );
    }

    public function render()
    {
        return view('agentoom-core::livewire.admin.pipeline-run-show', [
            'pipeline' => $this->pipeline,
            'run' => $this->run,
        ])->layout('agentoom-core::layouts.admin');
    }
}
