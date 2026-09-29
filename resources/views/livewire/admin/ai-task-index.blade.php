<div class="space-y-6">
    @if(! $is_embedded)
    <div class="flex justify-between items-center">
        <div>
            <flux:heading size="xl">{{ __('agentoom-core::admin.ai_tasks.title') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.ai_tasks.subtitle') }}</flux:subheading>
        </div>
        <flux:button href="{{ route('admin.agents.ai-tasks.create') }}" icon="plus" variant="primary" size="sm" wire:navigate>
            {{ __('agentoom-core::admin.ai_tasks.new') }}
        </flux:button>
    </div>
    @else
    <div class="flex justify-end">
        <flux:button href="{{ route('admin.agents.ai-tasks.create', ['project_id' => $project_id]) }}" icon="plus" variant="primary" size="sm" wire:navigate>
            {{ __('agentoom-core::admin.ai_tasks.new') }}
        </flux:button>
    </div>
    @endif

    {{-- Bulk Actions --}}
    @if ($this->isAnySelected)
        <div class="flex items-center gap-4 p-4 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
            <span class="text-sm font-medium">{{ __('agentoom-core::admin.common.selected_count', ['count' => $this->selectedCount]) }}</span>

            <flux:separator vertical />

            <div class="flex gap-2">
                <flux:button size="sm" icon="trash" variant="danger" wire:click="bulkDelete">
                    {{ __('agentoom-core::admin.common.delete_selected') }}
                </flux:button>
                <flux:button size="sm" icon="arrow-down-tray" variant="subtle" wire:click="bulkExport">
                    {{ __('agentoom-core::admin.common.export_selected') }}
                </flux:button>
                <flux:button size="sm" icon="document-duplicate" variant="subtle" wire:click="bulkClone">
                    {{ __('agentoom-core::admin.common.clone_selected') }}
                </flux:button>
            </div>

            <flux:spacer />

            <flux:button size="sm" variant="ghost" wire:click="clearSelection">
                {{ __('agentoom-core::admin.common.clear_selection') }}
            </flux:button>
        </div>
    @endif

    <flux:card>
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center gap-4 w-1/2">
                <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="{{ __('agentoom-core::admin.common.search') }}" class="flex-1" />
                <div class="w-32">
                    <flux:select wire:model.live="perPage" placeholder="{{ __('agentoom-core::admin.common.per_page') }}">
                        <flux:select.option value="15">15</flux:select.option>
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                        <flux:select.option value="100">100</flux:select.option>
                    </flux:select>
                </div>
            </div>
        </div>

        <flux:table :paginate="$tasks">
            <flux:table.columns>
                <flux:table.column>
                    <flux:checkbox wire:model.live="selectAll" />
                </flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.id') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.ai_tasks.name') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.ai_tasks.agent') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.ai_tasks.assigned_by') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.ai_tasks.period') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.ai_tasks.status') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.ai_tasks.next_run') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.ai_tasks.last_run') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.ai_tasks.is_active') }}</flux:table.column>
                <flux:table.column align="end">{{ __('agentoom-core::admin.common.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($tasks as $task)
                    <flux:table.row :key="$task->id">
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selected" :value="$task->id" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-500 tabular-nums">{{ $task->id }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-800 dark:text-white">{{ $task->name }}</div>
                            <flux:dropdown hover position="bottom" align="start" offset="-16" gap="10">
                                <div class="text-xs text-zinc-500 line-clamp-1">
                                    {{ substr($task->description, 0 , 60) }}
                                </div>
                                <flux:popover class="flex flex-col gap-3 rounded-xl shadow-xl">
                                    <div class="text-xs text-zinc-500 line-clamp-1">
                                        {{ $task->description }}
                                    </div>
                                </flux:popover>
                            </flux:dropdown>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $task->agent->name }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($task->assignedByAgent)
                                <flux:badge size="sm" color="blue">{{ $task->assignedByAgent->name }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">System</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($task->cron)
                                <flux:badge variant="outline" size="sm" icon="clock">
                                    {{ $task->cron }}
                                </flux:badge>
                            @else
                                <span class="capitalize">{{ $task->period === 'once' ? __('agentoom-core::admin.common.once') : $task->period }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $color = match($task->status) {
                                    'completed' => 'green',
                                    'running' => 'blue',
                                    'failed' => 'red',
                                    'paused' => 'orange',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge size="sm" :color="$color" inset>{{ ucfirst($task->status) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="text-xs">
                                {{ $task->next_run_at ? $task->next_run_at->diffForHumans() : '—' }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="text-xs">
                                {{ $task->last_run_at ? $task->last_run_at->diffForHumans() : 'Never' }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:switch wire:click="toggleActive({{ $task->id }})" :checked="$task->is_active" />
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:dropdown>
                                <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                <flux:menu>
                                    <flux:menu.item icon="eye" wire:click="showTask({{ $task->id }})">{{ __('agentoom-core::admin.common.show') }}</flux:menu.item>
                                    <flux:menu.item icon="play" wire:click="runManually({{ $task->id }})">{{ __('agentoom-core::admin.ai_tasks.run_manually') }}</flux:menu.item>
                                    <flux:menu.item icon="document-duplicate" wire:click="clone({{ $task->id }})">{{ __('agentoom-core::admin.common.clone') }}</flux:menu.item>
                                    <flux:menu.item icon="pencil" href="{{ route('admin.agents.ai-tasks.edit', $task->id) }}" wire:navigate>{{ __('agentoom-core::admin.common.edit') }}</flux:menu.item>
                                    <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $task->id }})" wire:confirm="{{ __('agentoom-core::admin.common.delete_confirm') }}">{{ __('agentoom-core::admin.common.delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @if ($selectedTask)
        <flux:modal wire:model="show_task_modal" class="md:min-w-[40rem] space-y-6">
            <div class="flex justify-between items-start">
                <div>
                    <flux:heading size="xl">{{ __('agentoom-core::admin.ai_tasks.overview') }}</flux:heading>
                    <flux:subheading>ID: #{{ $selectedTask->id }} — {{ $selectedTask->name }}</flux:subheading>
                </div>
                <div class="flex gap-2">
                    @php
                        $color = match($selectedTask->status) {
                            'completed' => 'green',
                            'running' => 'blue',
                            'failed' => 'red',
                            'paused' => 'orange',
                            default => 'zinc',
                        };
                    @endphp
                    <flux:badge :color="$color">{{ ucfirst($selectedTask->status) }}</flux:badge>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-6">
                <flux:card variant="subtle" class="space-y-2">
                    <flux:label>{{ __('agentoom-core::admin.ai_tasks.agent') }}</flux:label>
                    <div class="flex items-center gap-2">
                        <flux:badge size="sm" color="zinc">{{ $selectedTask->agent->name }}</flux:badge>
                    </div>
                </flux:card>

                <flux:card variant="subtle" class="space-y-2">
                    <flux:label>{{ __('agentoom-core::admin.ai_tasks.assigned_by') }}</flux:label>
                    <div class="flex items-center gap-2">
                        @if ($selectedTask->assignedByAgent)
                            <flux:badge size="sm" color="blue">{{ $selectedTask->assignedByAgent->name }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">System</flux:badge>
                        @endif
                    </div>
                </flux:card>

                <flux:card variant="subtle" class="space-y-2">
                    <flux:label>{{ __('agentoom-core::admin.ai_tasks.period') }}</flux:label>
                    <div>
                        @if($selectedTask->cron)
                            <flux:badge variant="outline" size="sm" icon="clock">
                                {{ $selectedTask->cron }}
                            </flux:badge>
                        @else
                            <span class="capitalize text-sm">{{ $selectedTask->period === 'once' ? __('agentoom-core::admin.common.once') : $selectedTask->period }}</span>
                        @endif
                    </div>
                </flux:card>

                <flux:card variant="subtle" class="space-y-2">
                    <flux:label>{{ __('agentoom-core::admin.ai_tasks.last_run') }}</flux:label>
                    <div class="text-sm">
                        {{ $selectedTask->last_run_at ? $selectedTask->last_run_at->format('Y-m-d H:i:s') . ' (' . $selectedTask->last_run_at->diffForHumans() . ')' : 'Never' }}
                    </div>
                </flux:card>

            </div>

            <flux:separator />

            <div class="space-y-2">
                <flux:label>{{ __('agentoom-core::admin.ai_tasks.description') }}</flux:label>
                <div class="text-sm p-3 bg-zinc-50 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 whitespace-pre-wrap">
                    {{ $selectedTask->description }}
                </div>
            </div>

            @if($selectedTask->parameters)
                <div class="space-y-2">
                    <flux:label>{{ __('agentoom-core::admin.ai_tasks.parameters') ?? 'Parameters' }}</flux:label>
                    <div class="text-xs p-3 bg-zinc-900 text-zinc-300 rounded-lg overflow-auto max-h-40 font-mono">
                        <pre>{{ json_encode($selectedTask->parameters, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>
            @endif

            <flux:separator />

            <div class="space-y-4">
                <flux:heading size="lg">{{ __('agentoom-core::admin.ai_tasks.logs') }}</flux:heading>

                <div class="space-y-3">
                    @forelse($selectedTask->logs as $log)
                        <div class="p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg bg-zinc-50 dark:bg-zinc-800/50">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-xs font-medium text-zinc-500">{{ $log->created_at->format('Y-m-d H:i:s') }}</span>
                            </div>
                            <div class="text-sm whitespace-pre-wrap">{{ $log->content }}</div>
                            @if($log->results)
                                <div class="mt-2 text-xs p-2 bg-zinc-900 text-zinc-400 rounded font-mono overflow-auto max-h-32">
                                    <pre>{{ json_encode($log->results, JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-sm text-zinc-500 italic">
                            {{ __('agentoom-core::admin.common.no_results') }}
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="flex justify-end pt-4">
                                    <flux:button variant="subtle"
                        x-on:click="$el.closest('dialog').close()">{{ __('agentoom-core::admin.common.close') }}</flux:button>
            </div>
        </flux:modal>
    @endif

    <flux:modal wire:model="show_bulk_delete_modal" class="max-w-md">
        <div class="text-center space-y-4">
            <div class="mx-auto flex items-center justify-center size-12 rounded-full bg-red-100 dark:bg-red-900/30">
                <flux:icon.exclamation-triangle class="size-6 text-red-600 dark:text-red-400" />
            </div>

            <flux:heading size="lg">
                {{ __('agentoom-core::admin.common.delete_selected_confirmation_title', ['count' => $this->selectedCount]) }}
            </flux:heading>

            <flux:subheading class="text-zinc-500">
                {{ __('agentoom-core::admin.common.delete_selected_confirmation_text') }}
            </flux:subheading>

            <div class="flex gap-3 justify-center pt-2">
                <flux:button wire:click="$set('show_bulk_delete_modal', false)">
                    {{ __('agentoom-core::admin.common.cancel') }}
                </flux:button>
                <flux:button variant="danger" icon="trash" wire:click="confirmBulkDelete">
                    {{ __('agentoom-core::admin.common.delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
