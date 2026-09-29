<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">
                {{ $pipeline?->exists ? __('agentoom-core::admin.pipelines.edit') : __('agentoom-core::admin.pipelines.create') }}
            </flux:heading>
            <flux:subheading>{{ $pipeline?->name ?? __('agentoom-core::admin.pipelines.new') }}</flux:subheading>
        </div>
        <div class="flex items-center gap-3">
            {{--
            <flux:button type="button" wire:click="toggleDiagram" variant="outline" size="sm" wire:key="diagram-toggle-btn">
                {{ $showDiagram ? 'SHOW FORM EDITOR' : 'NEW PIPELINE EDITOR' }}
            </flux:button>
            --}}
            <flux:button :href="$pipeline?->exists ? route('admin.pipelines.edit', $pipeline) : route('admin.pipelines.create')" variant="ghost" size="sm" icon="sparkles">
                {{ __('Switch to V2') }}
            </flux:button>
            <flux:button href="{{ route('admin.pipelines.index') }}" variant="ghost" icon="arrow-left" wire:navigate>
                {{ __('agentoom-core::admin.common.back') }}
            </flux:button>
        </div>
    </div>

    @if (session('success'))
        <flux:callout variant="success">{{ session('success') }}</flux:callout>
    @endif

    @if($showDiagram)
        @vite(['packages/agentoom/core-main/resources/js/pipeline-editor.jsx'])
        <flux:card>
            <div
                x-init="window.mountPipelineEditor()"
                id="pipeline-editor-root"
                wire:ignore
                data-props="{{ json_encode([
                    'initialSteps' => $steps,
                    'variables' => $variables,
                    'agents' => $this->agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),
                    'actionableMethods' => $this->actionableMethods,
                    'pipelineId' => $pipeline?->id,
                    'diagramState' => $pipeline?->config['diagram'] ?? null,
                    'translations' => $this->getTranslations(),
                ]) }}"
            ></div>
        </flux:card>
    @else
        <form wire:submit="save" class="space-y-6">
        {{-- Pipeline details --}}
        <flux:card class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6 items-end">
                <flux:field class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <flux:label>{{ __('agentoom-core::admin.pipelines.is_active') }}</flux:label>
                    <flux:radio.group wire:model="is_active" variant="segmented">
                        <flux:radio :value="1" label="{{ __('agentoom-core::admin.common.active') }}" />
                        <flux:radio :value="0" label="{{ __('agentoom-core::admin.common.inactive') }}" />
                    </flux:radio.group>
                </flux:field>

                <flux:field class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <flux:label>{{ __('agentoom-core::admin.common.allow_memory_analysis') }}</flux:label>
                    <flux:radio.group wire:model="allow_memory_analysis" variant="segmented">
                        <flux:radio :value="1" label="{{ __('agentoom-core::admin.common.analyze') }}" />
                        <flux:radio :value="0" label="{{ __('agentoom-core::admin.common.ignore') }}" />
                    </flux:radio.group>
                </flux:field>

                <flux:field class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <flux:label>{{ __('agentoom-core::admin.pipelines.concurrency_lock') }}</flux:label>
                    <flux:radio.group wire:model="concurrency_lock" variant="segmented">
                        <flux:radio :value="1" label="{{ __('agentoom-core::admin.pipelines.concurrency_lock_enabled') }}" />
                        <flux:radio :value="0" label="{{ __('agentoom-core::admin.pipelines.concurrency_lock_disabled') }}" />
                    </flux:radio.group>
                    <flux:description>{{ __('agentoom-core::admin.pipelines.concurrency_lock_help') }}</flux:description>
                </flux:field>
            </div>

            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('agentoom-core::admin.pipelines.details') }}</flux:heading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('agentoom-core::admin.pipelines.name') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="name" id="pipeline-name" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('agentoom-core::admin.projects.title') }}</flux:label>
                    <flux:select variant="listbox" searchable wire:model.live="projectId">
                        <flux:select.option value="">No Project</flux:select.option>
                        @foreach($this->projects as $p)
                            <flux:select.option value="{{ $p->id }}">{{ $p->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="projectId" />
                </flux:field>
            </div>

            @if(!$projectId)
                <flux:callout variant="warning">
                    {{ __('agentoom-core::admin.common.no_project_warning') }}
                </flux:callout>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:field>
                    <flux:radio.group wire:model.live="type" variant="segmented">
                        <flux:radio value="standard" label="{{ __('agentoom-core::admin.pipelines.type_standard') }}" />
                        <flux:radio value="endpoint" label="{{ __('agentoom-core::admin.pipelines.type_endpoint') }}" />
                    </flux:radio.group>
                    <flux:description>
                        {{ $type === 'standard' ? __('agentoom-core::admin.pipelines.type_standard_help') : __('agentoom-core::admin.pipelines.type_endpoint_help') }}
                    </flux:description>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('agentoom-core::admin.pipelines.description') }}</flux:label>
                <flux:textarea wire:model="description" id="pipeline-description" rows="2" />
                <flux:error name="description" />
            </flux:field>
        </flux:card>

        {{-- Pipeline variables --}}
        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">{{ __('agentoom-core::admin.pipelines.variables') }}</flux:heading>
                    <flux:subheading>{{ __('agentoom-core::admin.pipelines.variables_subtitle') }}</flux:subheading>
                </div>
                <flux:button type="button" wire:click="addVariable" variant="outline" size="sm" icon="plus">
                    {{ __('agentoom-core::admin.pipelines.add_variable') }}
                </flux:button>
            </div>

            @if (empty($variables))
                <div class="text-center py-4 text-zinc-500 border-2 border-dashed border-zinc-200 dark:border-zinc-700 rounded-lg">
                    {{ __('agentoom-core::admin.pipelines.no_variables_hint') }}
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($variables as $index => $variable)
                        <div class="flex gap-3 items-start" wire:key="variable-{{ $index }}">
                            <flux:field class="flex-1">
                                <flux:input wire:model="variables.{{ $index }}.key" placeholder="{{ __('agentoom-core::admin.pipelines.variable_key') }}" />
                            </flux:field>
                            <flux:field class="flex-1">
                                <flux:input wire:model="variables.{{ $index }}.value" placeholder="{{ __('agentoom-core::admin.pipelines.variable_value') }}" />
                            </flux:field>
                            <flux:button type="button" wire:click="removeVariable({{ $index }})" variant="ghost" icon="trash" size="sm" class="mt-1" />
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-zinc-200 dark:border-zinc-700">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 font-medium text-sm text-zinc-700 dark:text-zinc-300">
                            <flux:icon icon="information-circle" variant="mini" />
                            {{ __('agentoom-core::admin.pipelines.variables_help_title') }}
                        </div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            {!! __('agentoom-core::admin.pipelines.variables_help_text') !!}
                        </div>
                    </div>
                    <div class="space-y-2 md:border-l md:border-zinc-200 md:dark:border-zinc-700 md:pl-6">
                        <div class="flex items-center gap-2 font-medium text-sm text-zinc-700 dark:text-zinc-300">
                            <flux:icon icon="code-bracket-square" variant="mini" />
                            {{ __('agentoom-core::admin.pipelines.placeholders_help_title') }}
                        </div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            {!! __('agentoom-core::admin.pipelines.placeholders_help_text') !!}
                        </div>
                    </div>
                </div>
            </div>
        </flux:card>

        {{-- Pipeline steps --}}
        @island(name: 'pipeline-steps', lazy: true)
            @placeholder
                <flux:card class="space-y-4">
                    <div class="flex items-center justify-center py-12">
                        <flux:icon.loading />
                    </div>
                </flux:card>
            @endplaceholder
            <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <flux:heading size="lg">{{ __('agentoom-core::admin.pipelines.steps') }}</flux:heading>

                    @if (count($steps) > 0)
                        <div class="flex items-center gap-2">
                            <flux:checkbox wire:model.live="allSelected" />
                            <span class="text-xs text-zinc-500">{{ __('agentoom-core::admin.common.select_all') }}</span>
                        </div>
                    @endif

                    @if (count($selectedSteps) > 0)
                        <div class="flex items-center gap-2 ml-4 pl-4 border-l border-zinc-200 dark:border-zinc-700">
                            <flux:button type="button" wire:click="deleteSelectedSteps" variant="ghost" size="sm" icon="trash" color="red" wire:confirm="{{ __('agentoom-core::admin.common.are_you_sure') }}">
                                {{ __('agentoom-core::admin.common.delete') }} ({{ count($selectedSteps) }})
                            </flux:button>
                            <flux:button type="button" wire:click="openBulkMoveModal(false)" variant="ghost" size="sm" icon="arrows-up-down">
                                {{ __('agentoom-core::admin.common.move') }}
                            </flux:button>
                            <flux:button type="button" wire:click="openBulkMoveModal(true)" variant="ghost" size="sm" icon="document-duplicate">
                                {{ __('agentoom-core::admin.common.clone_and_move') }}
                            </flux:button>
                        </div>
                    @endif
                </div>
                <flux:button type="button" wire:click="addStep" variant="outline" size="sm" icon="plus">
                    {{ __('agentoom-core::admin.pipelines.add_step') }}
                </flux:button>
            </div>

            <flux:error name="steps" />

            <div class="space-y-4">
                @foreach ($steps as $i => $step)
                    <div wire:key="step-{{ $step['uid'] }}" class="flex items-center gap-4 mb-4 last:mb-0">
                        <flux:checkbox wire:model.live="selectedSteps" value="{{ $step['uid'] }}" />

                        <div class="grid flex-1 col-1 items-center p-2 border border-zinc-700 dark:border-zinc-400 rounded-xl overflow-hidden shadow-sm bg-white dark:bg-zinc-700">
                            <div class="ml-auto flex items-center gap-1 flex-shrink-0" >
                            <flux:button wire:click.stop="moveUp({{ $i }})" size="xs" variant="ghost" icon="chevron-up" :disabled="$i === 0"></flux:button>
                            <flux:button type="button" wire:click.stop="moveDown({{ $i }})" size="xs" variant="ghost" icon="chevron-down" :disabled="$i === count($steps) - 1"></flux:button>
                            <flux:button type="button" wire:click.stop="openMoveModal({{ $i }})" size="xs" variant="ghost" icon="arrows-up-down"></flux:button>
                            <flux:button type="button" wire:click.stop="cloneStep({{ $i }})" size="xs" variant="ghost" icon="document-duplicate" ></flux:button>
                            <flux:button type="button" wire:click.stop="removeStep({{ $i }})" size="xs" variant="ghost" icon="trash" ></flux:button>
                        </div>
                        <div>
                            <flux:accordion transition class="dark:bg-zinc-700" wire:key="step-accordion-{{ $step['uid'] }}">
                                <flux:accordion.item :expanded="in_array($step['uid'], $expandedSteps)" class="!border-0" wire:key="step-item-{{ $step['uid'] }}">
                                    <flux:accordion.heading class="!p-4 bg-zinc-50 dark:bg-zinc-800/50" wire:click="toggleStep('{{ $step['uid'] }}')">
                                        <div class="flex items-center justify-between gap-3 w-full">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <flux:badge variant="subtle" size="sm" color="zinc" class="font-mono flex-shrink-0">{{ (int)$i + 1 }}</flux:badge>
                                                <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 truncate text-left @if(!($step['is_active'] ?? true)) opacity-50 @endif">
                                                    {{ ($step['name'] ?? '') ?: __('agentoom-core::admin.pipelines.step').' '.((int)$i + 1) }}
                                                    @if(!($step['is_active'] ?? true))
                                                        <span class="text-xs font-normal text-zinc-500">({{ __('agentoom-core::admin.common.inactive') }})</span>
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </flux:accordion.heading>

                                    <flux:accordion.content class="!p-0 border-t border-zinc-100 dark:border-zinc-800">
                                        @if (in_array($step['uid'], $expandedSteps))
                                            <div class="p-6 space-y-6">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                @if ($i === 0 && $type === 'endpoint')
                                                    <flux:field wire:key="step-{{ $step['uid'] }}-target-type">
                                                        <flux:label>{{ __('agentoom-core::admin.schedules.target_type') }}</flux:label>
                                                        <div class="flex items-center gap-2 py-2">
                                                            <flux:badge color="zinc">{{ __('agentoom-core::admin.endpoints.receive_data') }}</flux:badge>
                                                            <span class="text-xs text-zinc-500">{{ __('agentoom-core::admin.endpoints.receive_data_hint') }}</span>
                                                        </div>
                                                        <input type="hidden" wire:model="steps.{{ $i }}.type" value="receive_data">
                                                    </flux:field>
                                                @else
                                                    <flux:field wire:key="step-{{ $step['uid'] }}-target-type-radio">
                                                        <flux:radio.group wire:model.live="steps.{{ $i }}.type" label="{{ __('agentoom-core::admin.schedules.target_type') }}" variant="segmented">
                                                            <flux:radio value="agent" label="{{ __('agentoom-core::admin.schedules.agent') }}" />
                                                            <flux:radio value="custom" label="{{ __('agentoom-core::admin.schedules.custom_method') }}" />
                                                        </flux:radio.group>
                                                    </flux:field>
                                                @endif

                                                <flux:field wire:key="step-{{ $step['uid'] }}-name-field">
                                                    <flux:label>{{ __('agentoom-core::admin.pipelines.step_name') }}</flux:label>
                                                    <flux:input wire:model="steps.{{ $i }}.name" placeholder="{{ __('agentoom-core::admin.pipelines.step_name_placeholder') }}" />
                                                </flux:field>

                                                <flux:field wire:key="step-{{ $step['uid'] }}-active-field">
                                                    <flux:label>{{ __('agentoom-core::admin.pipelines.is_active') }}</flux:label>
                                                    <flux:switch wire:model="steps.{{ $i }}.is_active" />
                                                </flux:field>

                                                @if (($step['type'] ?? 'agent') === 'agent')
                                                    <flux:field wire:key="step-{{ $step['uid'] }}-agent-field">
                                                        <flux:label>{{ __('agentoom-core::admin.pipelines.agent') }} <span class="text-red-500">*</span></flux:label>
                                                        <flux:select variant="listbox" wire:model.live="steps.{{ $i }}.agent_id" searchable wire:key="step-{{ $step['uid'] }}-agent-select">
                                                            <flux:select.option value="">{{ __('agentoom-core::admin.pipelines.select_agent') }}</flux:select.option>
                                                            @foreach ($this->agents as $agent)
                                                                <flux:select.option value="{{ $agent->id }}">{{ $agent->name }} <span class="sr-only">{{ str_replace([' ', '-', '_'], '', $agent->name) }}</span></flux:select.option>
                                                            @endforeach
                                                        </flux:select>
                                                        <flux:error name="steps.{{ $i }}.agent_id" />
                                                    </flux:field>
                                                @elseif (($step['type'] ?? 'agent') === 'custom')
                                                    <flux:field wire:key="step-{{ $step['uid'] }}-method-field">
                                                        <flux:label>{{ __('agentoom-core::admin.schedules.method') }} <span class="text-red-500">*</span></flux:label>
                                                        
                                                        <div class="flex flex-col gap-2">
                                                            @if(!empty($step['method_key']) && isset($this->actionableMethods()[$step['method_key']]))
                                                                @php $selectedMethod = $this->actionableMethods()[$step['method_key']]; @endphp
                                                                <div class="flex items-center justify-between p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                                                                    <div class="flex flex-col">
                                                                        <span class="text-sm font-semibold">{{ $selectedMethod['name'] }}</span>
                                                                        @if($selectedMethod['description'])
                                                                            <span class="text-xs text-zinc-500">{{ $selectedMethod['description'] }}</span>
                                                                        @endif
                                                                    </div>
                                                                    <flux:button type="button" size="xs" variant="ghost" wire:click="openMethodSelector({{ $i }})">{{ __('agentoom-core::admin.common.edit') }}</flux:button>
                                                                </div>
                                                            @else
                                                                <flux:button type="button" variant="outline" class="w-full" icon="magnifying-glass" wire:click="openMethodSelector({{ $i }})">
                                                                    {{ __('agentoom-core::admin.schedules.select_method') }}
                                                                </flux:button>
                                                            @endif
                                                        </div>
                                                        
                                                        <flux:error name="steps.{{ $i }}.method" />
                                                    </flux:field>
                                                @endif

                                                @if (($step['type'] ?? 'agent') !== 'receive_data' && (int)$i > 0)
                                                    <flux:field wire:key="step-{{ $step['uid'] }}-outputs-field">
                                                        <flux:label>{{ __('agentoom-core::admin.pipelines.pass_output') }}</flux:label>
                                                        <div class="flex items-center gap-2" wire:key="step-{{ $step['uid'] }}-outputs-trigger-container">
                                                            <flux:modal.trigger name="select-outputs-{{ $step['uid'] }}">
                                                                <flux:button variant="outline" size="sm" icon="list-bullet">
                                                                    {{ __('Select Previous Outputs') }}
                                                                    @if(count($step['selected_outputs'] ?? []) > 0)
                                                                        <flux:badge size="sm" color="blue" class="ml-2">{{ count($step['selected_outputs']) }}</flux:badge>
                                                                    @endif
                                                                </flux:button>
                                                            </flux:modal.trigger>
                                                            @if(count($step['selected_outputs'] ?? []) > 0)
                                                                <span class="text-xs text-zinc-500">
                                                                    {{ __(':count outputs selected', ['count' => count($step['selected_outputs'])]) }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <flux:description class="mt-2">{{ __('agentoom-core::admin.pipelines.pass_output_hint') }}</flux:description>
                                                    </flux:field>
                                                @endif
                                            </div>

                                            @if (($step['type'] ?? 'agent') !== 'receive_data' && (int)$i > 0)
                                                <flux:modal name="select-outputs-{{ $step['uid'] }}" class="md:w-[28rem] space-y-4" wire:key="step-modal-{{ $step['uid'] }}">
                                                    <div class="space-y-2">
                                                        <flux:heading size="lg">{{ __('Select Previous Outputs') }}</flux:heading>
                                                        <flux:subheading>{{ __('Choose which previous steps outputs to prepend to this step.') }}</flux:subheading>
                                                    </div>
                                                    <div class="space-y-3 max-h-60 overflow-y-auto pr-2">
                                                        @for ($j = 0; $j < $i; $j++)
                                                            <div class="flex items-center gap-2" wire:key="output-selection-{{ $step['uid'] }}-{{ $steps[$j]['uid'] }}">
                                                                <flux:checkbox
                                                                    wire:model="steps.{{ $i }}.selected_outputs"
                                                                    value="{{ $steps[$j]['uid'] }}"
                                                                    label="Step {{ $j + 1 }}: {{ ($steps[$j]['name'] ?? '') ?: (($steps[$j]['type'] ?? 'agent') === 'agent' ? 'Agent' : (($steps[$j]['type'] ?? 'agent') === 'receive_data' ? 'Receive Data' : 'Action')) }}"
                                                                />
                                                            </div>
                                                        @endfor
                                                    </div>
                                                    <div class="flex justify-end">
                                                                                                                    <flux:button variant="primary"
                                                                x-on:click="$el.closest('dialog').close()">{{ __('agentoom-core::admin.common.close') }}</flux:button>
                                                    </div>
                                                </flux:modal>
                                            @endif

                                            @if (($step['type'] ?? 'agent') === 'agent')
                                                <flux:field wire:key="step-{{ $step['uid'] }}-force-json">
                                                    <flux:checkbox wire:model="steps.{{ $i }}.force_json" label="{{ __('Force JSON Output') }}" />
                                                    <flux:description>{{ __('When enabled, the agent will be forced to respond with a structured JSON output matching the schema defined below.') }}</flux:description>
                                                </flux:field>
                                                @if ($step['force_json'] ?? false)
                                                    <div class="space-y-2" wire:key="step-{{ $step['uid'] }}-force-json-container">
                                                        <flux:label>{{ __('JSON Structure Schema') }}</flux:label>
                                                        @livewire('agentoom-core::admin.json-structure-helper', [
                                                            'initialFields' => $this->getStepInstructionFields($step['uid']),
                                                            'identifier' => $step['uid']
                                                        ], key('json-helper-agent-'.$step['uid']))
                                                    </div>
                                                @else
                                                    <flux:field wire:key="step-{{ $step['uid'] }}-instr-override">
                                                        <flux:label>{{ __('agentoom-core::admin.pipelines.instructions_override') }}</flux:label>
                                                        <flux:textarea wire:model="steps.{{ $i }}.instructions_override" rows="3" placeholder="{{ __('agentoom-core::admin.pipelines.instructions_override_placeholder') }}" />
                                                    </flux:field>
                                                @endif
                                            @elseif (($step['type'] ?? 'agent') === 'custom')
                                                <div class="space-y-2" wire:key="step-{{ $step['uid'] }}-params-container">
                                                    <flux:label>{{ __('agentoom-core::admin.schedules.parameters') }}</flux:label>
                                                    @livewire('agentoom-core::admin.json-structure-helper', [
                                                        'initialFields' => $this->getStepInstructionFields($step['uid']),
                                                        'identifier' => $step['uid']
                                                    ], key('json-helper-'.$step['uid'].'-'.($step['method_key'] ?: 'none')))
                                                </div>
                                            @endif

                                            {{-- Conditional Logic --}}
                                            <div class="pt-6 border-t border-zinc-100 dark:border-zinc-800 space-y-4">
                                                <div class="flex items-center gap-2 text-zinc-800 dark:text-zinc-200">
                                                    <flux:icon icon="arrow-path" variant="mini" class="text-zinc-400" />
                                                    <flux:heading size="sm">{{ __('agentoom-core::admin.pipelines.conditional_logic') }}</flux:heading>
                                                </div>
                                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                                    <flux:field wire:key="step-{{ $step['uid'] }}-condition-if">
                                                        <flux:label>{{ __('agentoom-core::admin.pipelines.condition_if') }}</flux:label>
                                                        <flux:input wire:model="steps.{{ $i }}.condition_if" placeholder="{{ __('agentoom-core::admin.pipelines.condition_if_placeholder') }}" />
                                                        <flux:description>{{ __('agentoom-core::admin.pipelines.condition_if_help') }}</flux:description>
                                                    </flux:field>

                                                    <div class="flex flex-col gap-2 pt-1" wire:key="step-{{ $step['uid'] }}-evaluate-on">
                                                        <p class="text-xs font-medium text-zinc-500 uppercase tracking-wider">{{ __('Evaluate on:') }}</p>
                                                        <div class="flex flex-col gap-2">
                                                            <flux:checkbox wire:model="steps.{{ $i }}.condition_on_previous" label="{{ __('Previous Step Output') }}" wire:key="step-{{ $step['uid'] }}-on-prev" />
                                                            <flux:checkbox wire:model="steps.{{ $i }}.condition_on_input" label="{{ __('Pipeline Input') }}" wire:key="step-{{ $step['uid'] }}-on-input" />
                                                            @if(($step['type'] ?? 'agent') !== 'receive_data')
                                                                <flux:checkbox wire:model="steps.{{ $i }}.condition_on_current" label="{{ __('Current Step Output') }}" wire:key="step-{{ $step['uid'] }}-on-curr" />
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <flux:field wire:key="step-{{ $step['uid'] }}-next-true">
                                                        <flux:label>{{ __('agentoom-core::admin.pipelines.next_step_on_true') }}</flux:label>
                                                        <flux:select wire:model="steps.{{ $i }}.next_step_on_true" wire:key="step-{{ $step['uid'] }}-next-true-select">
                                                            <flux:select.option value="">{{ __('agentoom-core::admin.pipelines.next_step_default') }}</flux:select.option>
                                                            @foreach ($steps as $nextStepIdx => $nextStep)
                                                                <flux:select.option value="{{ $nextStep['uid'] }}" wire:key="step-{{ $step['uid'] }}-next-true-opt-{{ $nextStep['uid'] }}">
                                                                    Step {{ $nextStepIdx + 1 }}: {{ ($nextStep['name'] ?? '') ?: (($nextStep['type'] ?? 'agent') === 'agent' ? 'Agent' : 'Action') }}
                                                                </flux:select.option>
                                                            @endforeach
                                                        </flux:select>
                                                    </flux:field>

                                                    <flux:field wire:key="step-{{ $step['uid'] }}-next-false">
                                                        <flux:label>{{ __('agentoom-core::admin.pipelines.next_step_on_false') }}</flux:label>
                                                        <flux:select wire:model="steps.{{ $i }}.next_step_on_false" wire:key="step-{{ $step['uid'] }}-next-false-select">
                                                            <flux:select.option value="">{{ __('agentoom-core::admin.pipelines.next_step_default') }}</flux:select.option>
                                                            @foreach ($steps as $nextStepIdx => $nextStep)
                                                                <flux:select.option value="{{ $nextStep['uid'] }}" wire:key="step-{{ $step['uid'] }}-next-false-opt-{{ $nextStep['uid'] }}">
                                                                    Step {{ $nextStepIdx + 1 }}: {{ ($nextStep['name'] ?? '') ?: (($nextStep['type'] ?? 'agent') === 'agent' ? 'Agent' : 'Action') }}
                                                                </flux:select.option>
                                                            @endforeach
                                                        </flux:select>
                                                    </flux:field>
                                                </div>
                                            </div>

                                            {{-- Error Handling --}}
                                            <div class="pt-6 border-t border-zinc-100 dark:border-zinc-800 space-y-4">
                                                <div class="flex items-center gap-2 text-zinc-800 dark:text-zinc-200">
                                                    <flux:icon icon="exclamation-triangle" variant="mini" class="text-zinc-400" />
                                                    <flux:heading size="sm">{{ __('agentoom-core::admin.pipelines.error_handling') }}</flux:heading>
                                                </div>

                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                    <flux:field wire:key="step-{{ $step['uid'] }}-on-error">
                                                        <flux:label>{{ __('agentoom-core::admin.pipelines.on_error') }}</flux:label>
                                                        <flux:select wire:model="steps.{{ $i }}.on_error" wire:key="step-{{ $step['uid'] }}-on-error-select">
                                                            <flux:select.option value="fail">{{ __('agentoom-core::admin.pipelines.on_error_fail') }}</flux:select.option>
                                                            <flux:select.option value="continue">{{ __('agentoom-core::admin.pipelines.on_error_continue') }}</flux:select.option>
                                                            <flux:select.option value="retry">{{ __('agentoom-core::admin.pipelines.on_error_retry') }}</flux:select.option>
                                                            <flux:select.option value="jump">{{ __('agentoom-core::admin.pipelines.on_error_jump') }}</flux:select.option>
                                                        </flux:select>
                                                        <flux:description>{{ __('agentoom-core::admin.pipelines.on_error_help') }}</flux:description>
                                                    </flux:field>

                                                    @if (($step['on_error'] ?? 'continue') === 'retry')
                                                        <div class="grid grid-cols-2 gap-4" wire:key="step-{{ $step['uid'] }}-retry-settings">
                                                            <flux:field wire:key="step-{{ $step['uid'] }}-retry-att">
                                                                <flux:label>{{ __('agentoom-core::admin.pipelines.retry_attempts') }}</flux:label>
                                                                <flux:input type="number" wire:model="steps.{{ $i }}.retry_attempts" min="1" max="10" />
                                                            </flux:field>
                                                            <flux:field wire:key="step-{{ $step['uid'] }}-retry-delay">
                                                                <flux:label>{{ __('agentoom-core::admin.pipelines.retry_delay') }} (s)</flux:label>
                                                                <flux:input type="number" wire:model="steps.{{ $i }}.retry_delay" min="0" />
                                                            </flux:field>
                                                        </div>
                                                    @endif

                                                    @if (($step['on_error'] ?? 'continue') === 'jump')
                                                        <flux:field wire:key="step-{{ $step['uid'] }}-next-error">
                                                            <flux:label>{{ __('agentoom-core::admin.pipelines.next_step_on_error') }}</flux:label>
                                                            <flux:select wire:model="steps.{{ $i }}.next_step_on_error" wire:key="step-{{ $step['uid'] }}-next-error-select">
                                                                <flux:select.option value="">{{ __('agentoom-core::admin.pipelines.next_step_placeholder') }}</flux:select.option>
                                                                @foreach ($steps as $nextStepIdx => $nextStep)
                                                                    @if ($i !== $nextStepIdx)
                                                                        <flux:select.option value="{{ $nextStep['uid'] }}" wire:key="step-{{ $step['uid'] }}-next-error-opt-{{ $nextStep['uid'] }}">
                                                                            Step {{ $nextStepIdx + 1 }}: {{ ($nextStep['name'] ?? '') ?: (($nextStep['type'] ?? 'agent') === 'agent' ? 'Agent' : 'Action') }}
                                                                        </flux:select.option>
                                                                    @endif
                                                                @endforeach
                                                            </flux:select>
                                                        </flux:field>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                    </flux:accordion.content>
                                </flux:accordion.item>
                            </flux:accordion>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="flex flex-col items-center justify-center py-12 text-zinc-500 border-2 border-dashed border-zinc-200 dark:border-zinc-700 rounded-xl bg-zinc-50/50 dark:bg-zinc-800/50">
                @if (empty($steps))
                    <flux:icon icon="list-bullet" class="size-8 mb-2 opacity-50" />
                    <span class="mb-4 text-sm">{{ __('agentoom-core::admin.pipelines.no_steps_hint') }}</span>
                @endif
                <flux:button type="button" wire:click="addStep" variant="subtle" size="sm" icon="plus">
                    {{ __('agentoom-core::admin.pipelines.add_step') }}
                </flux:button>
            </div>
        </flux:card>
        @endisland

        {{-- Failure Handler Step --}}
        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <flux:icon icon="exclamation-triangle" variant="mini" class="text-red-400" />
                    <div>
                        <flux:heading size="lg">{{ __('agentoom-core::admin.pipelines.failure_handler') }}</flux:heading>
                        <flux:subheading>{{ __('agentoom-core::admin.pipelines.failure_handler_subtitle') }}</flux:subheading>
                    </div>
                </div>
                <flux:switch wire:change="toggleFailureStep" :checked="$failureStepEnabled" />
            </div>

            @if($failureStepEnabled && $failureStep)
                <div class="p-4 border border-red-200 dark:border-red-800 rounded-xl bg-red-50/50 dark:bg-red-950/20 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>{{ __('agentoom-core::admin.pipelines.step_name') }}</flux:label>
                            <flux:input wire:model="failureStep.name" placeholder="{{ __('agentoom-core::admin.pipelines.failure_step_name_placeholder') }}" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('agentoom-core::admin.pipelines.is_active') }}</flux:label>
                            <flux:switch wire:model="failureStep.is_active" />
                        </flux:field>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:radio.group wire:model.live="failureStep.type" label="{{ __('agentoom-core::admin.schedules.target_type') }}" variant="segmented">
                                <flux:radio value="agent" label="{{ __('agentoom-core::admin.schedules.agent') }}" />
                                <flux:radio value="custom" label="{{ __('agentoom-core::admin.schedules.custom_method') }}" />
                            </flux:radio.group>
                        </flux:field>

                        @if (($failureStep['type'] ?? 'agent') === 'agent')
                            <flux:field>
                                <flux:label>{{ __('agentoom-core::admin.pipelines.agent') }} <span class="text-red-500">*</span></flux:label>
                                <flux:select variant="listbox" wire:model.live="failureStep.agent_id" searchable>
                                    <flux:select.option value="">{{ __('agentoom-core::admin.pipelines.select_agent') }}</flux:select.option>
                                    @foreach ($this->agents as $agent)
                                        <flux:select.option value="{{ $agent->id }}">{{ $agent->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                        @elseif (($failureStep['type'] ?? 'agent') === 'custom')
                            <flux:field>
                                <flux:label>{{ __('agentoom-core::admin.schedules.method') }} <span class="text-red-500">*</span></flux:label>
                                <flux:select variant="listbox" wire:model.live="failureStep.method_key" searchable>
                                    <flux:select.option value="">{{ __('agentoom-core::admin.common.select') }}</flux:select.option>
                                    @foreach ($this->actionableMethods as $key => $data)
                                        <flux:select.option value="{{ $key }}">{{ $data['name'] }} ({{ $data['category'] ?? 'General' }})</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                        @endif
                    </div>

                    @if (($failureStep['type'] ?? 'agent') === 'agent')
                        <flux:field>
                            <flux:checkbox wire:model="failureStep.force_json" label="{{ __('Force JSON Output') }}" />
                            <flux:description>{{ __('When enabled, the agent will be forced to respond with a structured JSON output matching the schema defined below.') }}</flux:description>
                        </flux:field>
                        @if ($failureStep['force_json'] ?? false)
                            <div class="space-y-2">
                                <flux:label>{{ __('JSON Structure Schema') }}</flux:label>
                                @livewire('agentoom-core::admin.json-structure-helper', [
                                    'initialFields' => $this->getStepInstructionFields($failureStep['uid']),
                                    'identifier' => $failureStep['uid']
                                ], key('json-helper-failure-agent-'.$failureStep['uid']))
                            </div>
                        @else
                            <flux:field>
                                <flux:label>{{ __('agentoom-core::admin.pipelines.instructions_override') }}</flux:label>
                                <flux:textarea wire:model="failureStep.instructions_override" rows="3" placeholder="{{ __('agentoom-core::admin.pipelines.failure_instructions_placeholder') }}" />
                            </flux:field>
                        @endif
                    @elseif (($failureStep['type'] ?? 'agent') === 'custom')
                        <div class="space-y-2">
                            <flux:label>{{ __('agentoom-core::admin.schedules.parameters') }}</flux:label>
                            @livewire('agentoom-core::admin.json-structure-helper', [
                                'initialFields' => $this->getStepInstructionFields($failureStep['uid']),
                                'identifier' => $failureStep['uid']
                            ], key('json-helper-failure-'.($failureStep['method_key'] ?: 'none')))
                        </div>
                    @endif
                </div>
            @endif

            @if(!$failureStepEnabled)
                <div class="text-center py-4 text-zinc-500 border-2 border-dashed border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <flux:icon icon="exclamation-triangle" variant="outline" class="size-6 mx-auto mb-2 opacity-50" />
                    <p class="text-sm">{{ __('agentoom-core::admin.pipelines.failure_handler_disabled_hint') }}</p>
                </div>
            @endif
        </flux:card>

            <div class="flex justify-end gap-3">
                <flux:button href="{{ route('admin.pipelines.index') }}" variant="ghost" wire:navigate>
                    {{ __('agentoom-core::admin.common.cancel') }}
                </flux:button>
                @if($pipeline?->exists)
                    <flux:button type="button" variant="subtle" icon="document-duplicate" wire:click="clone">{{ __('agentoom-core::admin.common.clone') }}</flux:button>
                @endif
                <flux:button type="button" variant="subtle" wire:click="save(true)">{{ __('agentoom-core::admin.common.save_and_stay') }}</flux:button>
                <flux:button type="submit" variant="primary">
                    <span class="in-data-loading:hidden">{{ __('agentoom-core::admin.common.save') }}</span>
                    <span class="hidden in-data-loading:flex items-center gap-2">
                        <flux:icon.loading variant="mini" />
                        {{ __('agentoom-core::admin.common.processing') }}
                    </span>
                </flux:button>
            </div>

        <flux:modal wire:model="showMoveStepModal" class="md:w-[28rem] space-y-4">
            <div class="space-y-2">
                <flux:heading size="lg">
                    @if ($isBulkMove)
                        {{ $isBulkCloneMove ? __('agentoom-core::admin.common.clone_and_move') : __('agentoom-core::admin.pipelines.move_step') }} ({{ count($selectedSteps) }} {{ __('agentoom-core::admin.pipelines.steps') }})
                    @else
                        {{ __('agentoom-core::admin.pipelines.move_step') }}
                    @endif
                </flux:heading>
                <flux:subheading>{{ __('agentoom-core::admin.pipelines.move_step_subtitle') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('agentoom-core::admin.pipelines.move_position') }}</flux:label>
                    <flux:radio.group wire:model="movePosition" variant="segmented">
                        <flux:radio value="before" label="{{ __('agentoom-core::admin.pipelines.move_before') }}" />
                        <flux:radio value="after" label="{{ __('agentoom-core::admin.pipelines.move_after') }}" />
                    </flux:radio.group>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('agentoom-core::admin.pipelines.move_target') }}</flux:label>
                    <flux:select wire:model="targetStepIndex" searchable>
                        <flux:select.option value="">{{ __('agentoom-core::admin.pipelines.move_target_placeholder') }}</flux:select.option>
                        @foreach ($steps as $idx => $s)
                            @if (!$isBulkMove && $idx !== $movingStepIndex)
                                <flux:select.option value="{{ $idx }}">
                                    {{ $idx + 1 }}. {{ ($s['name'] ?? '') ?: __('agentoom-core::admin.pipelines.step') . ' ' . ($idx + 1) }}
                                </flux:select.option>
                            @elseif ($isBulkMove && !in_array($idx, $selectedSteps))
                                <flux:select.option value="{{ $idx }}">
                                    {{ $idx + 1 }}. {{ ($s['name'] ?? '') ?: __('agentoom-core::admin.pipelines.step') . ' ' . ($idx + 1) }}
                                </flux:select.option>
                            @endif
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            <div class="flex justify-end gap-3">
                                    <flux:button variant="ghost"
                        x-on:click="$el.closest('dialog').close()">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="executeMoveStep">
                    @if ($isBulkCloneMove)
                        {{ __('agentoom-core::admin.common.clone_and_move') }}
                    @else
                        {{ __('agentoom-core::admin.common.move') }}
                    @endif
                </flux:button>
            </div>
        </flux:modal>
        </form>
    @endif

    <livewire:agentoom-core::admin.action-method-selector />
</div>
