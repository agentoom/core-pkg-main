<div
    class="space-y-6"
    x-data
    x-init="
        const resetScroll = () => window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
        document.addEventListener('livewire:navigated', resetScroll, { once: true });
        requestAnimationFrame(() => requestAnimationFrame(resetScroll));
    "
>
    <div>
        <flux:heading size="xl">
            {{ $is_editing ? __('agentoom-core::admin.agents.edit') : __('agentoom-core::admin.agents.new') }}
        </flux:heading>
        <flux:subheading>
            {{ $is_editing ? __('agentoom-core::admin.agents.edit_subtitle') : __('agentoom-core::admin.agents.create_subtitle') }}
        </flux:subheading>
    </div>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                <flux:field class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <flux:label>{{ __('agentoom-core::admin.agents.is_active') }}</flux:label>
                    <flux:radio.group wire:model="is_active" variant="segmented">
                        <flux:radio :value="1" label="{{ __('agentoom-core::admin.common.active') }}" />
                        <flux:radio :value="0" label="{{ __('agentoom-core::admin.common.inactive') }}" />
                    </flux:radio.group>
                </flux:field>

                <flux:field class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <flux:label>{{ __('agentoom-core::admin.agents.allow_memory_analysis') }}</flux:label>
                    <flux:radio.group wire:model="allow_memory_analysis" variant="segmented">
                        <flux:radio :value="1" label="{{ __('agentoom-core::admin.common.analyze') }}" />
                        <flux:radio :value="0" label="{{ __('agentoom-core::admin.common.ignore') }}" />
                    </flux:radio.group>
                </flux:field>

                @if(app(\Agentoom\Core\Contracts\PrivacyIntegration::class)->isEnabled())
                <flux:field class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <flux:label>{{ __('agentoom-core::admin.agents.anonymization_enabled') }}</flux:label>
                    <flux:radio.group wire:model="anonymization_enabled" variant="segmented">
                        <flux:radio :value="1" label="{{ __('agentoom-core::admin.common.enabled') }}" />
                        <flux:radio :value="0" label="{{ __('agentoom-core::admin.common.disabled') }}" />
                    </flux:radio.group>
                </flux:field>
                @endif

                <flux:field class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <flux:label>{{ __('agentoom-core::admin.agents.parts.language_policy') }}</flux:label>
                    <flux:radio.group wire:model="is_language_policy_enabled" variant="segmented">
                        <flux:radio :value="1" label="{{ __('agentoom-core::admin.common.enabled') }}" />
                        <flux:radio :value="0" label="{{ __('agentoom-core::admin.common.disabled') }}" />
                    </flux:radio.group>
                </flux:field>

                @if($humanInterventionSupported)
                    <flux:field class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                        <flux:label class="flex items-center gap-2">{{ __('agentoom-core::admin.agents.allow_human_intervention') }} <flux:badge size="sm" color="lime">Beta</flux:badge></flux:label>
                        <flux:radio.group wire:model="allow_human_intervention" variant="segmented">
                            <flux:radio :value="1" label="{{ __('agentoom-core::admin.common.yes') }}" />
                            <flux:radio :value="0" label="{{ __('agentoom-core::admin.common.no') }}" />
                        </flux:radio.group>
                    </flux:field>
                @endif
            </div>

            <flux:tab.group>
                <flux:tabs variant="pills">
                    <flux:tab name="general" icon="identification">{{ __('agentoom-core::admin.agents.general_tab') }}</flux:tab>
                    <flux:tab name="skills_tools" icon="academic-cap">{{ __('agentoom-core::admin.agents.skills_tools_tab') }}</flux:tab>
                    <flux:tab name="limits" icon="banknotes">{{ __('agentoom-core::admin.agents.limits_tab') }}</flux:tab>
                    <flux:tab name="tasks" icon="clipboard-document-list">{{ __('agentoom-core::admin.agents.tasks_tab') }}</flux:tab>
                    <flux:tab name="learned_skills" icon="beaker">{{ __('agentoom-core::admin.agents.learned_skills_tab') }}</flux:tab>
                    <flux:tab name="config" icon="cog-6-tooth">{{ __('agentoom-core::admin.agents.config_tab') }}</flux:tab>
                </flux:tabs>

                <flux:tab.panel name="general" class="space-y-6 pt-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input wire:model.blur="name"
                            label="{{ __('agentoom-core::admin.agents.name') }}"
                            placeholder="{{ __('agentoom-core::admin.agents.name') }}" />

                        <flux:input wire:model="slug"
                            label="{{ __('agentoom-core::admin.agents.slug') }}"
                            placeholder="{{ __('agentoom-core::admin.agents.slug') }}" />
                    </div>

                    <flux:select variant="listbox" searchable wire:model.live="projectId" label="{{ __('agentoom-core::admin.projects.title') }}">
                        <flux:select.option value="">No Project</flux:select.option>
                        @foreach($this->projects as $p)
                            <flux:select.option value="{{ $p->id }}">{{ $p->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @if(!$projectId)
                        <flux:callout variant="warning">
                            {{ __('agentoom-core::admin.common.no_project_warning') }}
                        </flux:callout>
                    @endif

                    <flux:textarea wire:model="description"
                        label="{{ __('agentoom-core::admin.agents.description') }}"
                        placeholder="{{ __('agentoom-core::admin.agents.description') }}" />

                    <div class="flex justify-center gap-4">
                        <flux:button wire:click="previewSystemPrompt" icon="eye" variant="subtle">
                            {{ __('agentoom-core::admin.agents.preview_system_prompt') }}
                        </flux:button>
                    </div>

                    <flux:separator variant="subtle" />

                    <div class="space-y-6">
                        <flux:textarea wire:model="role"
                            label="{{ __('agentoom-core::admin.agents.role') }}"
                            description="{{ __('agentoom-core::admin.agents.role_description') }}"
                            placeholder="{{ __('agentoom-core::admin.agents.role_placeholder') }}" />

                        <flux:textarea wire:model="objective"
                            label="{{ __('agentoom-core::admin.agents.objective') }}"
                            description="{{ __('agentoom-core::admin.agents.objective_description') }}"
                            placeholder="{{ __('agentoom-core::admin.agents.objective_placeholder') }}" />

                        <x-agentoom-core::markdown-editor wire:model="instructions"
                            label="{{ __('agentoom-core::admin.agents.instructions') }}"
                            description="{{ __('agentoom-core::admin.agents.instructions_description') }}"
                            placeholder="{{ __('agentoom-core::admin.agents.instructions_placeholder') }}"
                            height="500px" />

                        <x-agentoom-core::markdown-editor wire:model="operational_protocol"
                            label="{{ __('agentoom-core::admin.agents.operational_protocol') }}"
                            description="{{ __('agentoom-core::admin.agents.operational_protocol_description') }}"
                            placeholder="{{ __('agentoom-core::admin.agents.operational_protocol_placeholder') }}"
                            height="400px" />

                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <flux:label>{{ __('agentoom-core::admin.agents.prioritize') }}</flux:label>
                                <flux:button wire:click="addPrioritize" icon="plus" size="sm" variant="subtle">{{ __('agentoom-core::admin.agents.add_priority') }}</flux:button>
                            </div>
                            <flux:description>{{ __('agentoom-core::admin.agents.prioritize_description') }}</flux:description>
                            <div class="space-y-2">
                                @foreach($prioritize as $index => $p)
                                    <div class="flex gap-2" wire:key="prioritize-{{ $index }}">
                                        <flux:input wire:model="prioritize.{{ $index }}"
                                            placeholder="{{ __('agentoom-core::admin.agents.prioritize_placeholder') }}"
                                            class="flex-1" />
                                        <flux:button wire:click="removePrioritize({{ $index }})" icon="trash" size="sm" variant="ghost" color="red" />
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <flux:label>{{ __('agentoom-core::admin.agents.constraints') }}</flux:label>
                                <flux:button wire:click="addConstraint" icon="plus" size="sm" variant="subtle">{{ __('agentoom-core::admin.agents.add_constraint') }}</flux:button>
                            </div>
                            <flux:description>{{ __('agentoom-core::admin.agents.constraints_description') }}</flux:description>

                            <flux:checkbox wire:model="is_cautious_constraint_enabled"
                                label="{{ __('agentoom-core::admin.agents.cautious_constraint') }}"
                                description="{{ __('agentoom-core::admin.agents.cautious_constraint_description') }}" />

                            <div class="space-y-2">
                                @foreach($constraints as $index => $c)
                                    <div class="flex gap-2" wire:key="constraint-{{ $index }}">
                                        <flux:input wire:model="constraints.{{ $index }}"
                                            placeholder="{{ __('agentoom-core::admin.agents.constraints_placeholder') }}"
                                            class="flex-1" />
                                        <flux:button wire:click="removeConstraint({{ $index }})" icon="trash" size="sm" variant="ghost" color="red" />
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <flux:label>{{ __('agentoom-core::admin.agents.conditional_rules') }}</flux:label>
                                <flux:button wire:click="addConditionalRule" icon="plus" size="sm" variant="subtle">{{ __('agentoom-core::admin.agents.add_conditional_rule') }}</flux:button>
                            </div>
                            <flux:description>{{ __('agentoom-core::admin.agents.conditional_rules_description') }}</flux:description>
                            <div class="space-y-4">
                                @foreach($conditional_rules as $index => $rule)
                                    <flux:card class="space-y-4 relative" wire:key="conditional-rule-{{ $index }}">
                                        <flux:button wire:click="removeConditionalRule({{ $index }})" icon="trash" size="sm" variant="ghost" color="red" class="absolute top-2 right-2" />
                                        <flux:input wire:model="conditional_rules.{{ $index }}.condition" label="{{ __('agentoom-core::admin.agents.condition') }}" />
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <flux:input wire:model="conditional_rules.{{ $index }}.if_true" label="{{ __('agentoom-core::admin.agents.if_true') }}" />
                                            <flux:input wire:model="conditional_rules.{{ $index }}.if_false" label="{{ __('agentoom-core::admin.agents.if_false') }}" />
                                        </div>
                                    </flux:card>
                                @endforeach
                            </div>
                        </div>

                        <flux:textarea wire:model="error_handling"
                            label="{{ __('agentoom-core::admin.agents.error_handling') }}"
                            description="{{ __('agentoom-core::admin.agents.error_handling_description') }}"
                            placeholder="{{ __('agentoom-core::admin.agents.error_handling_placeholder') }}" />

                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <flux:label>{{ __('agentoom-core::admin.agents.output_format') }}</flux:label>
                                <flux:button wire:click="addOutputFormat" icon="plus" size="sm" variant="subtle">{{ __('agentoom-core::admin.agents.add_output_format') }}</flux:button>
                            </div>
                            <flux:description>{{ __('agentoom-core::admin.agents.output_format_description') }}</flux:description>
                            <div class="space-y-2">
                                @foreach($output_format as $index => $f)
                                    <div class="flex gap-2" wire:key="output-format-{{ $index }}">
                                        <flux:input wire:model="output_format.{{ $index }}"
                                            placeholder="{{ __('agentoom-core::admin.agents.output_format_placeholder') }}"
                                            class="flex-1" />
                                        <flux:button wire:click="removeOutputFormat({{ $index }})" icon="trash" size="sm" variant="ghost" color="red" />
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <flux:label>{{ __('agentoom-core::admin.agents.examples') }}</flux:label>
                                <flux:button wire:click="addExample" icon="plus" size="sm" variant="subtle">{{ __('agentoom-core::admin.agents.add_example') }}</flux:button>
                            </div>
                            <flux:description>{{ __('agentoom-core::admin.agents.examples_description') }}</flux:description>
                            <div class="space-y-4">
                                @foreach($examples as $index => $example)
                                    <flux:card class="space-y-4 relative" wire:key="example-{{ $index }}">
                                        <flux:button wire:click="removeExample({{ $index }})" icon="trash" size="sm" variant="ghost" color="red" class="absolute top-2 right-2" />
                                        <flux:textarea wire:model="examples.{{ $index }}.input"
                                            label="{{ __('agentoom-core::admin.agents.input') }}"
                                            placeholder="{{ __('agentoom-core::admin.agents.input_placeholder') }}" />
                                        <flux:textarea wire:model="examples.{{ $index }}.output"
                                            label="{{ __('agentoom-core::admin.agents.output') }}"
                                            placeholder="{{ __('agentoom-core::admin.agents.output_placeholder') }}" />
                                    </flux:card>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </flux:tab.panel>

                <flux:tab.panel name="skills_tools" class="space-y-6 pt-6">
                    @island(name: 'agent-skills-tools', lazy: true)
                        @placeholder
                            <div class="flex items-center justify-center py-12">
                                <flux:icon.loading />
                            </div>
                        @endplaceholder
                    @if(count($this->presets) > 0)
                        <div class="space-y-4">
                            <flux:heading size="sm">{{ __('agentoom-core::admin.skill_presets.panel_title') }}</flux:heading>
                            <flux:description>{{ __('agentoom-core::admin.skill_presets.panel_description') }}</flux:description>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach($this->presets as $preset)
                                    <div class="p-4 border rounded-lg border-zinc-200 dark:border-zinc-700 space-y-2">
                                        <flux:checkbox wire:model.live="selected_presets"
                                                       value="{{ $preset->id }}"
                                                       label="{{ $preset->name }}" />
                                        <div class="text-xs text-zinc-500 pl-7">{{ $preset->description }}</div>
                                        <div class="flex gap-1 pl-7">
                                            <flux:badge size="sm" inset="top bottom">{{ count($preset->skills ?? []) }} {{ __('agentoom-core::admin.skill_presets.skills_count') }}</flux:badge>
                                            <flux:badge size="sm" inset="top bottom">{{ count($preset->tools ?? []) }} {{ __('agentoom-core::admin.skill_presets.tools_count') }}</flux:badge>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <flux:separator variant="subtle" />
                    @endif

                    <div>
                        <flux:heading size="sm" class="mb-3">{{ __('agentoom-core::admin.agents.skills') }}</flux:heading>

                        @if(count($selected_skills) > 0)
                            <div class="flex flex-wrap gap-2 mb-4">
                                @foreach($selected_skills as $skillId)
                                    @php
                                        $skill = $this->skills->firstWhere('id', $skillId);
                                    @endphp
                                    @if($skill)
                                        <flux:badge size="sm" wire:click="$set('selected_skills', {{ json_encode(array_values(array_diff($selected_skills, [$skillId]))) }})">
                                            {{ $skill->name }}
                                            <flux:badge.close class="cursor-pointer" />
                                        </flux:badge>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        <flux:select variant="listbox"
                                     multiple
                                     searchable
                                     wire:model.live="selected_skills"
                                     placeholder="{{ __('agentoom-core::admin.agents.skills_placeholder') }}">
                            @foreach ($this->skills as $skill)
                                <flux:select.option value="{{ $skill->id }}">
                                    <div>
                                        <div class="font-medium">{{ $skill->name }} <span class="sr-only">{{ str_replace([' ', '-', '_'], '', $skill->name) }}</span></div>
                                        @if ($skill->description)
                                            <div class="text-xs text-gray-500">{{ $skill->description }}</div>
                                        @endif
                                    </div>
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <flux:heading size="sm" class="mb-3">{{ __('agentoom-core::admin.agents.tools') }}</flux:heading>

                        @if(count($selected_tools) > 0)
                            <div class="flex flex-wrap gap-2 mb-4">
                                @foreach($selected_tools as $toolId)
                                    @php
                                        $tool = $this->tools->firstWhere('id', $toolId);
                                    @endphp
                                    @if($tool)
                                        <flux:badge size="sm" wire:click="$set('selected_tools', {{ json_encode(array_values(array_diff($selected_tools, [$toolId]))) }})">
                                            {{ $tool->name }}
                                            <flux:badge.close class="cursor-pointer" />
                                        </flux:badge>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        <flux:select variant="listbox"
                                    clearable
                                     multiple
                                     searchable
                                     wire:model.live="selected_tools"
                                     placeholder="{{ __('agentoom-core::admin.agents.tools_placeholder') }}">
                            @foreach ($this->tools as $tool)
                                <flux:select.option value="{{ $tool->id }}">
                                    <div>
                                        <div class="font-medium">{{ $tool->name }} <span class="sr-only">{{ str_replace([' ', '-', '_'], '', $tool->name) }}</span></div>
                                        @if ($tool->description)
                                            <div class="text-xs text-gray-500">{{ $tool->description }}</div>
                                        @endif
                                    </div>
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    @endisland
                </flux:tab.panel>

                <flux:tab.panel name="limits" class="space-y-6 pt-6">
                    <div>
                        <flux:heading size="sm" class="mb-3">{{ __('agentoom-core::admin.agents.credit_limit_section') }}</flux:heading>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <flux:input wire:model="max_usd_limit" type="number" step="0.01" min="0"
                                        label="{{ __('agentoom-core::admin.agents.max_usd_limit') }}"
                                        placeholder="0.00" />

                            <flux:select wire:model="limit_period" label="{{ __('agentoom-core::admin.agents.limit_period') }}">
                                <flux:select.option value="">{{ __('agentoom-core::admin.agents.no_period') }}</flux:select.option>
                                <flux:select.option value="daily">{{ __('agentoom-core::admin.common.daily') }}</flux:select.option>
                                <flux:select.option value="weekly">{{ __('agentoom-core::admin.common.weekly') }}</flux:select.option>
                                <flux:select.option value="monthly">{{ __('agentoom-core::admin.common.monthly') }}</flux:select.option>
                            </flux:select>

                            <flux:input wire:model="limit_starts_at" type="date" label="{{ __('Starts At') }}" />

                            <div>
                                <flux:switch wire:model="is_hard_limit" label="{{ __('agentoom-core::admin.agents.is_hard_limit') }}" />
                                <flux:description class="mt-2">{{ __('agentoom-core::admin.agents.is_hard_limit_description') }}</flux:description>
                            </div>
                        </div>
                    </div>

                    <div>
                        <flux:heading size="sm" class="mb-3">{{ __('agentoom-core::admin.agents.task_limit_section') }}</flux:heading>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <flux:input wire:model="max_tasks_per_period" type="number" min="0"
                                        label="{{ __('agentoom-core::admin.agents.max_tasks_per_period') }}"
                                        placeholder="0" />

                            <flux:select wire:model="task_limit_period" label="{{ __('agentoom-core::admin.agents.task_limit_period') }}">
                                <flux:select.option value="">{{ __('agentoom-core::admin.agents.no_period') }}</flux:select.option>
                                <flux:select.option value="daily">{{ __('agentoom-core::admin.common.daily') }}</flux:select.option>
                                <flux:select.option value="weekly">{{ __('agentoom-core::admin.common.weekly') }}</flux:select.option>
                                <flux:select.option value="monthly">{{ __('agentoom-core::admin.common.monthly') }}</flux:select.option>
                            </flux:select>

                            <flux:input wire:model="task_limit_starts_at" type="date" label="{{ __('Starts At') }}" />
                        </div>
                    </div>
                </flux:tab.panel>

                <flux:tab.panel name="tasks" class="space-y-6 pt-6">
                    @island(name: 'agent-tasks', lazy: true)
                        @placeholder
                            <div class="flex items-center justify-center py-12">
                                <flux:icon.loading />
                            </div>
                        @endplaceholder
                    <div class="space-y-4 pt-2">
                        @if ($is_editing)
                            <div class="space-y-1">
                                <flux:heading size="sm">{{ __('agentoom-core::admin.agents.incoming_task_permissions') }}</flux:heading>
                                <flux:subheading>{{ __('agentoom-core::admin.agents.incoming_task_permissions_description') }}</flux:subheading>
                            </div>

                            @forelse($this->incomingPermissions as $inPermission)
                                <flux:card class="!bg-gray-50/50 dark:!bg-white/5">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <flux:icon.cpu-chip class="size-5 text-zinc-400" />
                                            <div>
                                                <div class="font-medium">{{ $inPermission->agent->name }}</div>
                                                @if($inPermission->assignment_explanation)
                                                    <div class="text-xs text-zinc-500 italic">"{{ $inPermission->assignment_explanation }}"</div>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            @if($inPermission->can_assign)
                                                <flux:badge size="sm" color="blue" variant="subtle">{{ __('agentoom-core::admin.agents.can_assign') }}</flux:badge>
                                            @endif
                                            @if($inPermission->can_view)
                                                <flux:badge size="sm" color="green" variant="subtle">{{ __('agentoom-core::admin.agents.can_view') }}</flux:badge>
                                            @endif
                                        </div>
                                    </div>
                                </flux:card>
                            @empty
                                <div class="text-center py-4 text-zinc-400 italic text-sm">
                                    {{ __('agentoom-core::admin.agents.no_incoming_task_permissions') }}
                                </div>
                            @endforelse

                            <flux:separator variant="subtle" class="my-4" />
                        @endif

                        <div class="flex justify-between items-start">
                            <div class="space-y-1">
                                <flux:heading size="sm">{{ __('agentoom-core::admin.agents.task_permissions') }}</flux:heading>
                                <flux:subheading>{{ __('agentoom-core::admin.agents.task_permissions_description') }}</flux:subheading>
                            </div>
                            <flux:button wire:click="addTaskPermission" variant="subtle" icon="plus" size="sm">{{ __('agentoom-core::admin.agents.add_permission') }}</flux:button>
                        </div>

                        @if(empty($task_permissions))
                            <flux:callout variant="info">
                                {{ __('agentoom-core::admin.agents.no_task_permissions') }}
                            </flux:callout>
                        @else
                            <div class="space-y-4">
                                @foreach($task_permissions as $index => $permission)
                                    <flux:card class="relative !bg-gray-50/50 dark:!bg-white/5">
                                        <flux:button wire:click="removeTaskPermission({{ $index }})"
                                                     variant="ghost" icon="trash" size="sm"
                                                     class="absolute top-2 right-2 text-red-500 hover:text-red-700" />

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                                            <flux:select wire:model.live="task_permissions.{{ $index }}.permitted_agent_id"
                                                         label="{{ __('agentoom-core::admin.agents.permitted_agent') }}"
                                                         description="{{ __('agentoom-core::admin.agents.permitted_agent_help') }}"
                                                         searchable variant="listbox">
                                                <flux:select.option value="">{{ __('agentoom-core::admin.common.select') }}</flux:select.option>
                                                @foreach($this->availableAgents as $availAgent)
                                                    <flux:select.option value="{{ $availAgent->id }}">{{ $availAgent->name }}</flux:select.option>
                                                @endforeach
                                            </flux:select>

                                            <div class="flex flex-col gap-3 pt-2">
                                                <flux:checkbox wire:model="task_permissions.{{ $index }}.can_assign"
                                                               label="{{ __('agentoom-core::admin.agents.can_assign') }}"
                                                               description="{{ __('agentoom-core::admin.agents.can_assign_description') }}" />
                                                <flux:checkbox wire:model="task_permissions.{{ $index }}.can_view"
                                                               label="{{ __('agentoom-core::admin.agents.can_view') }}"
                                                               description="{{ __('agentoom-core::admin.agents.can_view_description') }}" />
                                            </div>
                                        </div>

                                        <div class="mt-4">
                                            <flux:textarea wire:model="task_permissions.{{ $index }}.assignment_explanation"
                                                        label="{{ __('agentoom-core::admin.agents.assignment_explanation') }}"
                                                        description="{{ __('agentoom-core::admin.agents.assignment_explanation_help') }}"
                                                        placeholder="{{ __('agentoom-core::admin.agents.assignment_explanation_placeholder') }}" />
                                        </div>
                                    </flux:card>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @endisland
                </flux:tab.panel>

                <flux:tab.panel name="learned_skills" class="space-y-6 pt-6">
                    @if ($is_editing)
                        <livewire:agentoom-core::admin.agent-learned-skills-manager :agent="$agent" :nested="true" :key="'learned-skills-'.$agent->id" />
                    @else
                        <div class="py-12 text-center text-zinc-400 italic">
                            {{ __('agentoom-core::admin.agents.learned_skills_available_after_create') }}
                        </div>
                    @endif
                </flux:tab.panel>


                <flux:tab.panel name="config" class="space-y-6 pt-6">
                    <flux:heading size="sm" class="mb-3">{{ __('agentoom-core::admin.agents.config') }}</flux:heading>

                    @if (!$provider_id && !$provider_model_id && !$temperature && $this->globalProvider)
                        <flux:callout variant="info" class="mb-4">
                            <div class="space-y-1">
                                <div class="font-semibold">{{ __('agentoom-core::admin.agents.using_global_config') }}</div>
                                <div class="text-sm">
                                    {{ __('agentoom-core::admin.agents.provider') }}: <strong>{{ $this->globalProvider->name }}</strong>
                                </div>
                                @if ($this->globalModel)
                                    <div class="text-sm">
                                        {{ __('agentoom-core::admin.agents.model') }}: <strong>{{ $this->globalModel->model_name }}</strong>
                                        @if(auth()->user()->hasRole('superadmin'))
                                            @if($this->globalModelPricing && $this->globalModelPricing->input_price_per_million !== null && $this->globalModelPricing->output_price_per_million !== null)
                                                <div class="text-xs text-zinc-500 mt-1">{{ __('agentoom-core::admin.agents.pricing_verified') }}: ${{ number_format($this->globalModelPricing->input_price_per_million, 6) }} / ${{ number_format($this->globalModelPricing->output_price_per_million, 6) }} per 1M ({{ $this->globalModelPricing->source }})</div>
                                            @else
                                                <div class="text-xs text-amber-600 mt-1">{{ __('agentoom-core::admin.agents.pricing_unavailable') }}</div>
                                            @endif
                                        @endif
                                    </div>
                                @endif
                                <div class="text-sm">
                                    {{ __('agentoom-core::admin.agents.temperature') }}: <strong>{{ $globalConfig['temperature'] }}</strong>
                                </div>
                                <div class="text-sm">
                                    {{ __('agentoom-core::admin.agents.top_p') }}: <strong>{{ $globalConfig['top_p'] ?? '1.0' }}</strong>
                                </div>
                            </div>
                        </flux:callout>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @if ($this->providers->count() > 1)
                            <flux:select wire:model.live="provider_id"
                                variant="listbox"
                                searchable
                                label="{{ __('agentoom-core::admin.agents.provider') }}"
                                placeholder="{{ __('agentoom-core::admin.agents.provider_placeholder') }}">
                                <flux:select.option value="">{{ __('agentoom-core::admin.agents.use_global_config') }}</flux:select.option>
                                @foreach ($this->providers as $provider)
                                    <flux:select.option value="{{ $provider->id }}">{{ ucfirst($provider->name) }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif

                        <flux:select wire:model.live="provider_model_id"
                            searchable
                            variant="listbox"
                            label="{{ __('agentoom-core::admin.agents.model') }}"
                            placeholder="{{ __('agentoom-core::admin.agents.model_placeholder') }}"
                            :disabled="!$provider_id"
                            :description="auth()->user()->hasRole('superadmin') && $this->selectedModelPricing ? __('Cost per 1M tokens') . ': $' . number_format($this->selectedModelPricing->input_price_per_million, 2) . ' (' . __('input') . ') / $' . number_format($this->selectedModelPricing->output_price_per_million, 2) . ' (' . __('output') . ')' : ''"
                        >
                            <flux:select.option value="">{{ $provider_id ? __('agentoom-core::admin.agents.select_model') : __('agentoom-core::admin.agents.select_provider_first') }}</flux:select.option>
                            @foreach ($this->availableModels as $model)
                                <flux:select.option value="{{ $model->id }}">{{ $model->model_name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @if(auth()->user()->hasRole('superadmin') && $provider_model_id)
                            @if($this->selectedModelPricing && $this->selectedModelPricing->input_price_per_million !== null && $this->selectedModelPricing->output_price_per_million !== null)
                                <flux:description>{{ __('agentoom-core::admin.agents.pricing_verified') }}: ${{ number_format($this->selectedModelPricing->input_price_per_million, 6) }} / ${{ number_format($this->selectedModelPricing->output_price_per_million, 6) }} per 1M ({{ $this->selectedModelPricing->source }})</flux:description>
                            @else
                                <flux:callout variant="warning">{{ __('agentoom-core::admin.agents.pricing_unavailable') }}</flux:callout>
                            @endif
                        @endif
                        <flux:input wire:model="temperature"
                            type="number"
                            min="0"
                            max="2"
                            step="0.1"
                            label="{{ __('agentoom-core::admin.agents.temperature') }}"
                            placeholder="{{ __('agentoom-core::admin.agents.temperature_placeholder') }}" />

                        <flux:input wire:model="top_p"
                            type="number"
                            min="0"
                            max="1"
                            step="0.05"
                            label="{{ __('agentoom-core::admin.agents.top_p') }}"
                            placeholder="{{ __('agentoom-core::admin.agents.top_p_placeholder') }}" />

                        @php
                            $currentProvider = $this->providers->firstWhere('id', $provider_id);
                        @endphp

                        @if ($currentProvider && $currentProvider->name === 'openrouter')
                            @if (auth()->user()->hasRole('superadmin'))
                                <flux:select wire:model.live="provider_output_modality" label="{{ __('Output Modality') }}" icon="adjustments-horizontal">
                                    <flux:select.option value="all">{{ __('All Modalities') }}</flux:select.option>
                                    <flux:select.option value="text">{{ __('Text') }}</flux:select.option>
                                    <flux:select.option value="image">{{ __('Image') }}</flux:select.option>
                                    <flux:select.option value="audio">{{ __('Audio') }}</flux:select.option>
                                    <flux:select.option value="embeddings">{{ __('Embeddings') }}</flux:select.option>
                                    <flux:select.option value="transcription">{{ __('Transcription') }}</flux:select.option>
                                    <flux:select.option value="speech">{{ __('Speech (TTS)') }}</flux:select.option>
                                    <flux:select.option value="rerank">{{ __('Rerank') }}</flux:select.option>
                                    <flux:select.option value="video">{{ __('Video') }}</flux:select.option>
                                </flux:select>
                            @endif

                            <flux:select wire:model="provider_model_suffix" label="{{ __('agentoom-core::admin.agents.openrouter_suffix') }}"
                                         description="{{ __('agentoom-core::admin.agents.openrouter_suffix_help') }}">
                                <flux:select.option value="">{{ __('agentoom-core::admin.agents.openrouter_suffix_none') }}</flux:select.option>
                                <flux:select.option value="nitro">{{ __('agentoom-core::admin.agents.openrouter_suffix_nitro') }}</flux:select.option>
                                <flux:select.option value="floor">{{ __('agentoom-core::admin.agents.openrouter_suffix_floor') }}</flux:select.option>
                            </flux:select>
                        @endif

                        @if (($currentProvider && $currentProvider->name === 'openrouter') || (!$provider_id && $this->globalProvider && $this->globalProvider->name === 'openrouter'))
                            <flux:field>
                                <flux:label>{{ __('agentoom-core::admin.agents.openrouter_disable_cache') }}</flux:label>
                                <flux:description>{{ __('agentoom-core::admin.agents.openrouter_disable_cache_help') }}</flux:description>
                                <flux:switch wire:model="openrouter_disable_cache" />
                            </flux:field>
                        @endif
                    </div>

                    @if ($provider_id && !$provider_model_id)
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">
                            @if ($this->defaultModelForProvider)
                                {{ __('agentoom-core::admin.agents.using_default_model', ['model' => $this->defaultModelForProvider->model_name]) }}
                            @else
                                {{ __('agentoom-core::admin.agents.using_global_default_model') }}
                            @endif
                        </div>
                    @endif
                </flux:tab.panel>
            </flux:tab.group>

            <div class="flex gap-2 justify-end">
                <flux:button type="button" href="{{ route('admin.agents.index') }}">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                @if($is_editing)
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
        </form>
    </flux:card>

    <flux:modal wire:model="show_preview_modal" variant="large">
        <div class="space-y-6">
            <flux:heading size="lg">
                {{ __('agentoom-core::admin.agents.preview_system_prompt') }}
            </flux:heading>

            <div class="p-4 bg-zinc-100 dark:bg-zinc-900 rounded-lg overflow-auto max-h-[70vh]">
                <pre class="whitespace-pre-wrap text-sm font-mono">{{ $previewPrompt }}</pre>
            </div>

            <div class="flex justify-end">
                                    <flux:button variant="subtle"
                        x-on:click="$el.closest('dialog').close()">{{ __('agentoom-core::admin.common.close') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
