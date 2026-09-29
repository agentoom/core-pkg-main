<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ $is_editing ? __('agentoom-core::admin.ai_tasks.edit') : __('agentoom-core::admin.ai_tasks.new') }}</flux:heading>
        <flux:subheading>{{ $is_editing ? __('agentoom-core::admin.ai_tasks.edit_subtitle') : __('agentoom-core::admin.ai_tasks.create_subtitle') }}</flux:subheading>
    </div>

    <flux:card>
        <div class="mb-6 flex flex-wrap gap-4 text-sm text-zinc-500">
            <div class="flex items-center gap-2">
                <flux:icon.clock class="size-4" />
                <span>{{ __('agentoom-core::admin.common.server_time') }}:</span>
                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $currentTime }}</span>
            </div>
            <div class="flex items-center gap-2">
                <flux:icon.globe-alt class="size-4" />
                <span>{{ __('agentoom-core::admin.common.app_timezone') }}:</span>
                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $appTimezone }}</span>
            </div>
        </div>

        <form wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                <flux:field class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <flux:label>{{ __('agentoom-core::admin.ai_tasks.is_active') }}</flux:label>
                    <flux:radio.group wire:model.live="is_active" variant="segmented">
                        <flux:radio :value="1" label="{{ __('agentoom-core::admin.common.active') }}" />
                        <flux:radio :value="0" label="{{ __('agentoom-core::admin.common.inactive') }}" />
                    </flux:radio.group>
                </flux:field>

                <flux:field class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <flux:label>{{ __('agentoom-core::admin.common.allow_memory_analysis') }}</flux:label>
                    <flux:radio.group wire:model.live="allow_memory_analysis" variant="segmented">
                        <flux:radio :value="1" label="{{ __('agentoom-core::admin.common.analyze') }}" />
                        <flux:radio :value="0" label="{{ __('agentoom-core::admin.common.ignore') }}" />
                    </flux:radio.group>
                </flux:field>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="name" label="{{ __('agentoom-core::admin.ai_tasks.name') }}" placeholder="{{ __('agentoom-core::admin.ai_tasks.name_placeholder') }}" />

                <flux:select wire:model.live="agent_id" label="{{ __('agentoom-core::admin.ai_tasks.agent') }}" searchable variant="listbox">
                    <flux:select.option value="">{{ __('agentoom-core::admin.common.select') }}</flux:select.option>
                    @foreach($agents as $agent)
                        <flux:select.option value="{{ $agent->id }}">{{ $agent->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:textarea wire:model="description" label="{{ __('agentoom-core::admin.ai_tasks.description') }}" placeholder="{{ __('agentoom-core::admin.ai_tasks.description_placeholder') }}" rows="5" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:select wire:model.live="period" variant="listbox" label="{{ __('agentoom-core::admin.ai_tasks.period') }}">
                    <flux:select.option value="once">{{ __('agentoom-core::admin.common.once') }}</flux:select.option>
                    <flux:select.option value="custom">{{ __('agentoom-core::admin.ai_tasks.recurring') }}</flux:select.option>
                </flux:select>

                <flux:select variant="listbox" searchable wire:model.live="projectId" label="{{ __('agentoom-core::admin.projects.title') }}">
                    <flux:select.option value="">No Project</flux:select.option>
                    @foreach($projects as $p)
                        <flux:select.option value="{{ $p->id }}">{{ $p->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            @if(!$projectId)
                <flux:callout variant="warning">
                    {{ __('agentoom-core::admin.common.no_project_warning') }}
                </flux:callout>
            @endif

            @if($period !== 'once')
                <flux:separator />

                <flux:heading size="lg">{{ __('agentoom-core::admin.schedules.cron') }}</flux:heading>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:select
                        label="{{ __('agentoom-core::admin.schedules.common_timeframes') }}"
                        wire:model.live="timeframe"
                    >
                        <flux:select.option value="">{{ __('agentoom-core::admin.schedules.select_timeframe') }}</flux:select.option>
                        <flux:select.option value="every_minute">{{ __('agentoom-core::admin.schedules.timeframes.every_minute') }}</flux:select.option>
                        <flux:select.option value="every_5_minutes">{{ __('agentoom-core::admin.schedules.timeframes.every_5_minutes') }}</flux:select.option>
                        <flux:select.option value="every_30_minutes">{{ __('agentoom-core::admin.schedules.timeframes.every_30_minutes') }}</flux:select.option>
                        <flux:select.option value="every_hour">{{ __('agentoom-core::admin.schedules.timeframes.every_hour') }}</flux:select.option>
                        <flux:select.option value="every_3_hours">{{ __('agentoom-core::admin.schedules.timeframes.every_3_hours') }}</flux:select.option>
                        <flux:select.option value="twice_a_day">{{ __('agentoom-core::admin.schedules.timeframes.twice_a_day') }}</flux:select.option>
                        <flux:select.option value="everyday">{{ __('agentoom-core::admin.schedules.timeframes.everyday') }}</flux:select.option>
                        <flux:select.option value="every_3_days">{{ __('agentoom-core::admin.schedules.timeframes.every_3_days') }}</flux:select.option>
                        <flux:select.option value="every_week">{{ __('agentoom-core::admin.schedules.timeframes.every_week') }}</flux:select.option>
                        <flux:select.option value="every_month">{{ __('agentoom-core::admin.schedules.timeframes.every_month') }}</flux:select.option>
                    </flux:select>
                </div>

                <div class="grid grid-cols-5 gap-4">
                    <flux:input label="{{ __('agentoom-core::admin.schedules.minute') }}" wire:model="minute" required placeholder="*" />
                    <flux:input label="{{ __('agentoom-core::admin.schedules.hour') }}" wire:model="hour" required placeholder="*" />
                    <flux:input label="{{ __('agentoom-core::admin.schedules.day') }}" wire:model="day" required placeholder="*" />
                    <flux:input label="{{ __('agentoom-core::admin.schedules.month') }}" wire:model="month" required placeholder="*" />
                    <flux:input label="{{ __('agentoom-core::admin.schedules.day_of_week') }}" wire:model="day_of_week" required placeholder="*" />
                </div>
                <flux:subheading class="text-xs">{{ __('agentoom-core::admin.schedules.cron_help') }}</flux:subheading>

                <div class="grid grid-cols-1 gap-4">
                    <flux:input wire:model="cron" label="{{ __('agentoom-core::admin.ai_tasks.cron') }}" placeholder="* * * * *" readonly />
                </div>
            @endif

            <div class="flex gap-2 justify-end">
                <flux:button type="button" href="{{ route('admin.agents.ai-tasks.index') }}" wire:navigate>{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                @if($is_editing)
                    <flux:button type="button" variant="subtle" icon="document-duplicate" wire:click="clone">{{ __('agentoom-core::admin.common.clone') }}</flux:button>
                @endif
                <flux:button type="button" variant="subtle" wire:click="save(true)">{{ __('agentoom-core::admin.common.save_and_stay') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('agentoom-core::admin.common.save') }}</flux:button>
            </div>
        </form>
    </flux:card>

    @if($is_editing && $task->logs()->exists())
        <flux:heading size="lg" class="mt-8">{{ __('agentoom-core::admin.ai_tasks.logs') }}</flux:heading>
        <div class="space-y-4">
            @foreach($task->logs()->latest()->paginate(10) as $log)
                <flux:card>
                    <div class="flex justify-between items-start mb-2">
                        <div class="text-sm font-medium text-zinc-500">
                            {{ $log->created_at->toDateTimeString() }} ({{ $log->created_at->diffForHumans() }})
                        </div>
                        <flux:badge size="sm" color="zinc">{{ $log->agent ? $log->agent->name : 'System' }}</flux:badge>
                    </div>
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        {!! nl2br(e($log->content)) !!}
                    </div>
                    @if($log->results)
                        <div class="mt-4 p-3 bg-zinc-50 dark:bg-zinc-900 rounded border border-zinc-200 dark:border-zinc-800" x-data="{ showRaw: false }">
                            <flux:heading size="xs" class="mb-2">Results</flux:heading>

                            {{-- Render Native AI Images --}}
                            @if(isset($log->results['images']) && count($log->results['images']) > 0)
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                    @foreach($log->results['images'] as $image)
                                        <div class="space-y-2">
                                            <img src="{{ $image['url'] ?? $image['data'] }}" class="rounded-lg shadow-sm max-w-full h-auto" alt="Generated Image">
                                            @if(isset($image['revised_prompt']))
                                                <p class="text-[10px] text-zinc-500 italic">{{ $image['revised_prompt'] }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Render Tool Results (specifically for AgentFileTool) --}}
                            @if(isset($log->results['tool_results']) && count($log->results['tool_results']) > 0)
                                <div class="space-y-3 mb-4">
                                    @foreach($log->results['tool_results'] as $toolResult)
                                        @php
                                            $data = is_string($toolResult['result']) ? json_decode($toolResult['result'], true) : $toolResult['result'];
                                        @endphp
                                        @if(isset($data['url']))
                                            <div class="p-2 rounded bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700">
                                                <div class="flex items-center gap-2 text-sm mb-2">
                                                    <flux:icon.document-text class="size-4 text-zinc-400" />
                                                    <span class="font-medium truncate">{{ basename($data['path'] ?? 'File') }}</span>
                                                </div>

                                                @if(isset($data['path']) && in_array(strtolower(pathinfo($data['path'], PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg']))
                                                    <img src="{{ $data['url'] }}" class="rounded border max-w-sm h-auto mb-2" alt="Tool Generated Image">
                                                @endif

                                                <div class="flex gap-2">
                                                    <flux:button href="{{ $data['url'] }}" target="_blank" size="xs" variant="subtle" icon="arrow-down-tray">
                                                        Download / View
                                                    </flux:button>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <flux:button variant="ghost" size="xs" x-on:click="showRaw = !showRaw" class="mb-2">
                                <span x-show="!showRaw">Show Raw JSON</span>
                                <span x-show="showRaw">Hide Raw JSON</span>
                            </flux:button>
                            <div x-show="showRaw" x-cloak class="mt-2">
                                <pre class="text-xs overflow-auto max-h-60 p-2 bg-black text-zinc-300 rounded"><code>{{ json_encode($log->results, JSON_PRETTY_PRINT) }}</code></pre>
                            </div>
                        </div>
                    @endif
                </flux:card>
            @endforeach
            {{ $task->logs()->latest()->paginate(10)->links() }}
        </div>
    @endif
</div>
