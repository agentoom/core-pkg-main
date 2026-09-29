<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <flux:heading size="xl">{{ __('agentoom-core::admin.skill_presets.title') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.skill_presets.subtitle') }}</flux:subheading>
        </div>
        <flux:button wire:click="create" variant="primary" icon="plus">
            {{ __('agentoom-core::admin.skill_presets.create_button') }}
        </flux:button>
    </div>

    <flux:card class="p-0 overflow-hidden">
        <flux:table :paginate="$presets">
            <flux:table.columns>
                <flux:table.column>{{ __('agentoom-core::admin.common.name') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.skill_presets.skills_count') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.skill_presets.tools_count') }}</flux:table.column>
                <flux:table.column>{{ __('agentoom-core::admin.common.status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($presets as $preset)
                    <flux:table.row :key="$preset->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $preset->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $preset->description }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc" inset="top bottom">
                                {{ count($preset->skills ?? []) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc" inset="top bottom">
                                {{ count($preset->tools ?? []) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$preset->is_active ? 'green' : 'zinc'">
                                {{ $preset->is_active ? __('agentoom-core::admin.common.active') : __('agentoom-core::admin.common.inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-2">
                                <flux:button wire:click="edit({{ $preset->id }})" variant="subtle" size="sm" icon="pencil-square" />
                                <flux:button wire:click="delete({{ $preset->id }})" 
                                             wire:confirm="{{ __('agentoom-core::admin.common.are_you_sure') }}"
                                             variant="subtle" color="red" size="sm" icon="trash" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showModal" variant="large">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingPreset ? __('agentoom-core::admin.skill_presets.edit_title') : __('agentoom-core::admin.skill_presets.create_title') }}
                </flux:heading>
                <flux:subheading>
                    {{ __('agentoom-core::admin.skill_presets.modal_subtitle') }}
                </flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="name" label="{{ __('agentoom-core::admin.common.name') }}" />
                <flux:textarea wire:model="description" label="{{ __('agentoom-core::admin.common.description') }}" />
                <flux:switch wire:model="is_active" label="{{ __('agentoom-core::admin.common.is_active') }}" />

                <div class="space-y-4 pt-4">
                    <div>
                        <flux:heading size="sm" class="mb-3">{{ __('agentoom-core::admin.agents.skills') }}</flux:heading>
                        
                        @if(count($selected_skills) > 0)
                            <div class="flex flex-wrap gap-2 mb-4">
                                @foreach($selected_skills as $skillId)
                                    @php
                                        $skill = $availableSkills->firstWhere('id', $skillId);
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
                            @foreach($availableSkills as $skill)
                                <flux:select.option value="{{ $skill->id }}">
                                    {{ $skill->name }}
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
                                        $tool = $availableTools->firstWhere('id', $toolId);
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
                                     multiple 
                                     searchable 
                                     wire:model="selected_tools" 
                                     placeholder="{{ __('agentoom-core::admin.agents.tools_placeholder') }}">
                            @foreach($availableTools as $tool)
                                <flux:select.option value="{{ $tool->id }}">
                                    {{ $tool->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                                    <flux:button variant="subtle"
                        x-on:click="$el.closest('dialog').close()">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('agentoom-core::admin.common.save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
