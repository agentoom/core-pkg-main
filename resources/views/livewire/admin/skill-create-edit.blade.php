<div class="space-y-6">
    <div>
        <flux:heading size="xl">
            {{ $is_editing ? __('agentoom-core::admin.skills.edit') : __('agentoom-core::admin.skills.new') }}
        </flux:heading>
        <flux:subheading>
            {{ $is_editing ? __('agentoom-core::admin.skills.edit_subtitle') : __('agentoom-core::admin.skills.create_subtitle') }}
        </flux:subheading>
    </div>

    {{-- Card --}}
    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <div class="flex justify-end">
                <flux:switch wire:model="is_active"
                    label="{{ __('agentoom-core::admin.skills.is_active') }}" />
            </div>

            {{-- Basic Info --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model.live="name"
                    label="{{ __('agentoom-core::admin.skills.name') }}"
                    placeholder="{{ __('agentoom-core::admin.skills.name') }}" />

                <flux:input wire:model="slug"
                    label="{{ __('agentoom-core::admin.skills.slug') }}"
                    placeholder="{{ __('agentoom-core::admin.skills.slug') }}" />
            </div>

            <flux:textarea wire:model="description"
                label="{{ __('agentoom-core::admin.skills.description') }}"
                placeholder="{{ __('agentoom-core::admin.skills.description') }}" />

            <x-agentoom-core::markdown-editor wire:model="instructions"
                label="{{ __('agentoom-core::admin.skills.instructions') }}"
                placeholder="{{ __('agentoom-core::admin.skills.instructions') }}"
                height="500px" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Musts --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:label>{{ __('agentoom-core::admin.skills.musts') }}</flux:label>
                        <flux:button wire:click="addMust" size="xs" icon="plus" variant="ghost"></flux:button>
                    </div>
                    <flux:description class="-mt-2">{{ __('agentoom-core::admin.skills.musts_help') }}</flux:description>

                    <div class="space-y-2">
                        @foreach($musts as $index => $must)
                            <div class="flex gap-2">
                                <flux:input wire:model="musts.{{ $index }}" placeholder="Requirement..." class="flex-1" />
                                <flux:button wire:click="removeMust({{ $index }})" icon="trash" variant="ghost" size="sm" />
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Donts --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:label>{{ __('agentoom-core::admin.skills.donts') }}</flux:label>
                        <flux:button wire:click="addDont" size="xs" icon="plus" variant="ghost"></flux:button>
                    </div>
                    <flux:description class="-mt-2">{{ __('agentoom-core::admin.skills.donts_help') }}</flux:description>

                    <div class="space-y-2">
                        @foreach($donts as $index => $dont)
                            <div class="flex gap-2">
                                <flux:input wire:model="donts.{{ $index }}" placeholder="Prohibition..." class="flex-1" />
                                <flux:button wire:click="removeDont({{ $index }})" icon="trash" variant="ghost" size="sm" />
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Conditional Logic --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <flux:label>{{ __('agentoom-core::admin.skills.if_this_then') }}</flux:label>
                    <flux:button wire:click="addIfThisThen" size="xs" icon="plus" variant="ghost"></flux:button>
                </div>
                <flux:description class="-mt-2">{{ __('agentoom-core::admin.skills.if_this_then_help') }}</flux:description>

                <div class="space-y-2">
                    @foreach($if_this_then as $index => $item)
                        <div class="flex gap-2 items-center">
                            <flux:input wire:model="if_this_then.{{ $index }}.event" placeholder="If this event..." class="flex-1" />
                            <flux:icon.arrow-right class="text-zinc-400 size-4" />
                            <div class="flex-1 flex gap-2">
                                <flux:input wire:model="if_this_then.{{ $index }}.action" placeholder="Then do this action..." class="flex-1" />
                                @if(count($skillForms) > 0)
                                    <flux:select wire:model="if_this_then.{{ $index }}.form_slug" searchable placeholder="Show form (optional)" class="flex-1">
                                        <flux:select.option value="">{{ __('agentoom-core::admin.skills.no_form') }}</flux:select.option>
                                        @foreach($skillForms as $form)
                                            <flux:select.option value="{{ $form->slug }}">{{ $form->name[app()->getLocale()] }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @endif
                            </div>
                            <flux:button wire:click="removeIfThisThen({{ $index }})" icon="trash" variant="ghost" size="sm" />
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <flux:heading size="sm" class="mb-3">{{ __('agentoom-core::admin.skills.tools') }}</flux:heading>
                @if(count($selected_tools) > 0)
                    <div class="flex flex-wrap gap-2 mb-4">
                        @foreach($selected_tools as $toolClassName)
                            @php
                                $tool = $tools->firstWhere('class_name', $toolClassName);
                            @endphp
                            @if($tool)
                                <flux:badge size="sm" wire:click="$set('selected_tools', {{ json_encode(array_values(array_diff($selected_tools, [$toolClassName]))) }})">
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
                             wire:model.live="selected_tools"
                             placeholder="{{ __('agentoom-core::admin.skills.tools_placeholder') }}">
                    @foreach ($tools as $tool)
                        <flux:select.option value="{{ $tool->class_name }}">
                            <div>
                                <div class="font-medium">{{ $tool->name }}</div>
                                @if ($tool->description)
                                    <div class="text-xs text-gray-500">{{ $tool->description }}</div>
                                @endif
                            </div>
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <p class="mt-2 text-xs text-zinc-500">
                    {{ __('agentoom-core::admin.skills.tools_placeholder') }}
                </p>
            </div>

            <div>
                <flux:heading size="sm" class="mb-3">{{ __('agentoom-core::admin.skills.resource_types') }}</flux:heading>
                @if(count($resource_type_ids) > 0)
                    <div class="flex flex-wrap gap-2 mb-4">
                        @foreach($resource_type_ids as $resourceTypeId)
                            @php
                                $resourceType = $resourceTypes->firstWhere('id', $resourceTypeId);
                            @endphp
                            @if($resourceType)
                                <flux:badge size="sm" wire:click="$set('resource_type_ids', {{ json_encode(array_values(array_diff($resource_type_ids, [$resourceTypeId]))) }})">
                                    {{ $resourceType->name_translated }}
                                    <flux:badge.close class="cursor-pointer" />
                                </flux:badge>
                            @endif
                        @endforeach
                    </div>
                @endif
                <flux:select variant="listbox"
                             multiple
                             searchable
                             wire:model.live="resource_type_ids"
                             placeholder="{{ __('agentoom-core::admin.skills.resource_types_placeholder') }}">
                    @foreach ($resourceTypes as $resourceType)
                        <flux:select.option value="{{ $resourceType->id }}">
                            {{ $resourceType->name_translated }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <p class="mt-2 text-xs text-zinc-500">
                    {{ __('agentoom-core::admin.skills.resource_types_help') }}
                </p>
            </div>

            <div class="flex gap-2 justify-end">
                <flux:button type="button" href="{{ route('admin.skills.index') }}">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                @if($is_editing)
                    <flux:button type="button" variant="subtle" icon="document-duplicate" wire:click="clone">{{ __('agentoom-core::admin.common.clone') }}</flux:button>
                @endif
                <flux:button type="button" variant="subtle" wire:click="save(true)">{{ __('agentoom-core::admin.common.save_and_stay') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('agentoom-core::admin.common.save') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
