<div class="space-y-6" @if(in_array($run->status, ['running', 'pending', 'paused'])) wire:poll.5s.non-blocking="refresh" @endif>
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('agentoom-core::admin.pipelines.run_detail') }} #{{ $run->id }}</flux:heading>
            <flux:subheading>{{ $pipeline->name }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            @if(in_array($run->status, ['completed', 'failed', 'stopped']))
                <flux:button icon="arrow-path" variant="filled" wire:click="retry" wire:confirm="{{ __('agentoom-core::admin.pipelines.retry_run_confirm') ?? 'Are you sure you want to retry this run from the beginning?' }}">
                    {{ __('agentoom-core::admin.pipelines.retry_run') }}
                </flux:button>
            @endif
            <flux:button :href="route('admin.pipelines.runs', $pipeline)" variant="ghost" icon="arrow-left" wire:navigate>
                {{ __('agentoom-core::admin.common.back') }}
            </flux:button>
        </div>
    </div>

    {{-- Run summary --}}
    <flux:card>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-sm">
            <div>
                <p class="text-zinc-500">{{ __('agentoom-core::admin.pipelines.status') }}</p>
                @php
                    $color = match($run->status) {
                        'completed' => 'green',
                        'failed'    => 'red',
                        'running'   => 'blue',
                        'paused'    => 'orange',
                        'pending'   => 'zinc',
                        default     => 'zinc',
                    };
                @endphp
                <flux:badge :color="$color" class="mt-1">{{ $run->status }}</flux:badge>
            </div>
            <div>
                <p class="text-zinc-500">{{ __('agentoom-core::admin.pipelines.run_by') }}</p>
                <p class="font-medium mt-1">{{ $run->user?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-zinc-500">{{ __('agentoom-core::admin.pipelines.started_at') }}</p>
                <p class="font-medium mt-1">{{ $run->started_at?->format('Y-m-d H:i:s') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-zinc-500">{{ __('agentoom-core::admin.pipelines.duration') }}</p>
                <p class="font-medium mt-1">
                    {{ $run->durationInSeconds() !== null ? number_format($run->durationInSeconds(), 2).'s' : '—' }}
                </p>
            </div>
        </div>

        @if ($run->error)
            <div class="mt-4">
                <flux:callout variant="danger">
                    <flux:callout.heading>{{ __('agentoom-core::admin.pipelines.error') }}</flux:callout.heading>
                    {{ $run->error }}
                </flux:callout>
            </div>
        @endif
    </flux:card>

    {{-- Original input --}}
    <flux:card class="space-y-2">
        <flux:heading size="md">{{ __('agentoom-core::admin.pipelines.original_input') }}</flux:heading>
        <pre class="bg-zinc-50 dark:bg-zinc-900 rounded p-4 text-sm whitespace-pre-wrap text-zinc-800 dark:text-zinc-200">{{ $run->input }}</pre>
    </flux:card>

    {{-- Step results --}}
    <div class="space-y-4">
        @foreach ($run->stepRuns as $stepRun)
            @php
                $stepColor = match($stepRun->status) {
                    'completed' => 'green',
                    'failed'    => 'red',
                    'running'   => 'blue',
                    'paused'    => 'orange',
                    'pending'   => 'zinc',
                    default     => 'zinc',
                };
            @endphp
            <flux:accordion transition>
                <flux:accordion.item :expanded="false" class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                    <flux:accordion.heading class="!p-4 bg-white dark:bg-zinc-900">
                        <div class="flex items-center w-full">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <span class="flex items-center justify-center w-7 h-7 rounded-full bg-zinc-100 dark:bg-zinc-800 text-sm font-bold text-zinc-700 dark:text-zinc-300 flex-shrink-0">
                                    {{ $stepRun->order + 1 }}
                                </span>
                                <div class="text-left min-w-0">
                                    <p class="font-semibold text-zinc-900 dark:text-white truncate">
                                        {{ $stepRun->pipelineStep?->displayName() ?? 'Step '.($stepRun->order + 1) }}
                                    </p>
                                    <p class="text-xs text-zinc-500 truncate">
                                        {{ $stepRun->pipelineStep?->agent?->name }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 ml-4 mr-10 flex-shrink-0">
                                <flux:badge :color="$stepColor">{{ $stepRun->status }}</flux:badge>
                                @if ($stepRun->durationInSeconds() !== null)
                                    <span class="text-xs text-zinc-400">{{ number_format($stepRun->durationInSeconds(), 2) }}s</span>
                                @endif
                            </div>
                        </div>
                    </flux:accordion.heading>

                    <flux:accordion.content class="bg-white dark:bg-zinc-900 border-t border-zinc-100 dark:border-zinc-800 p-4">
                        <div class="space-y-4 pt-4">
                            @if ($stepRun->error)
                                <flux:callout variant="danger">{{ $stepRun->error }}</flux:callout>
                            @endif

                            @if ($stepRun->output)
                                <div class="space-y-1">
                                    <p class="text-xs font-medium text-zinc-500 uppercase tracking-wider">{{ __('agentoom-core::admin.pipelines.output') }}</p>
                                    <pre class="bg-zinc-50 dark:bg-zinc-900 rounded p-4 text-sm whitespace-pre-wrap text-zinc-800 dark:text-zinc-200">{{ $stepRun->output }}</pre>
                                </div>
                            @endif

                            @if(in_array($run->status, ['completed', 'failed', 'stopped']))
                                <div class="flex justify-end pt-2">
                                    <flux:button size="sm" variant="ghost" icon="play" wire:click="continueFromStep({{ $stepRun->order }})" wire:confirm="{{ __('agentoom-core::admin.pipelines.continue_from_here_confirm') ?? 'Are you sure you want to continue execution from this step? Subsequent step results will be deleted.' }}">
                                        {{ __('agentoom-core::admin.pipelines.continue_from_here') }}
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    </flux:accordion.content>
                </flux:accordion.item>
            </flux:accordion>
        @endforeach
    </div>

    {{-- Final output --}}
    @if ($run->output)
        <flux:card class="space-y-2">
            <flux:heading size="md">{{ __('agentoom-core::admin.pipelines.final_output') }}</flux:heading>
            <pre class="bg-zinc-50 dark:bg-zinc-900 rounded p-4 text-sm whitespace-pre-wrap text-zinc-800 dark:text-zinc-200">{{ $run->output }}</pre>
        </flux:card>
    @endif
</div>
