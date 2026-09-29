<?php

namespace Agentoom\Core\Livewire\Admin;

use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Services\AiCreditService;
use Agentoom\Core\Services\PipelineRunnerService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class PipelineRunIndex extends Component
{
    use WithPagination;

    #[Locked]
    public int $pipelineId;

    public string $input = '';

    public bool $isRunning = false;

    public function mount(Pipeline $pipeline): void
    {
        $this->pipelineId = $pipeline->id;
    }

    #[Computed]
    public function pipeline(): Pipeline
    {
        return Pipeline::find($this->pipelineId);
    }

    public function startRun(): void
    {
        if (! app(AiCreditService::class)->hasSufficientCredits(auth()->user())) {
            Flux::toast(
                heading: __('agentoom-core::admin.ai_usage_logs.exhausted_title'),
                text: __('agentoom-core::admin.ai_usage_logs.exhausted_message'),
                variant: 'warning'
            );

            return;
        }

        $this->validate([
            'input' => 'nullable|string',
        ]);

        $service = app(PipelineRunnerService::class);
        $service->run($this->pipeline, $this->input, auth()->user(), background: true);

        $this->input = '';
        $this->resetPage();

        session()->flash('success', __('agentoom-core::admin.pipelines.run_started'));
    }

    public function deleteRun(int $id): void
    {
        PipelineRun::findOrFail($id)->delete();
        session()->flash('success', __('agentoom-core::admin.pipelines.run_deleted'));
    }

    public function stopRun(int $id): void
    {
        $run = PipelineRun::findOrFail($id);

        if (in_array($run->status, ['running', 'pending', 'paused'])) {
            $run->update([
                'status' => 'stopped',
                'output' => 'Stopped by user',
            ]);

            session()->flash('success', __('agentoom-core::admin.pipelines.run_stopping'));
        }
    }

    public function deleteAllPipelineLogs(): void
    {
        $this->pipeline->runs()->delete();
        $this->resetPage();
        session()->flash('success', __('agentoom-core::admin.pipelines.all_runs_deleted'));
    }

    public function render()
    {
        $runs = $this->pipeline->runs()->with('user')->paginate(15);

        return view('agentoom-core::livewire.admin.pipeline-run-index', [
            'runs' => $runs,
            'pipeline' => $this->pipeline,
        ])->layout('agentoom-core::layouts.admin');
    }
}
