<div class="space-y-6 max-w-4xl mx-auto">
    <div>
        <flux:heading size="xl">{{ $is_editing ? __('agentoom-core::admin.resource_types.edit') : __('agentoom-core::admin.resource_types.new') }}</flux:heading>
        <flux:subheading>{{ __('agentoom-core::admin.resource_types.create_edit_subtitle') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-6">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Names (Translations)') }}</flux:heading>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($languages as $lang)
                        <flux:field>
                            <flux:label>{{ $lang->name }} ({{ strtoupper($lang->code) }})</flux:label>
                            <flux:input wire:model="names.{{ $lang->code }}" required />
                            <flux:error name="names.{{ $lang->code }}" />
                        </flux:field>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Resource Group') }}</flux:label>
                    <flux:select wire:model.live="resourceGroupId" variant="listbox" searchable placeholder="{{ __('Select Group') }}">
                        @foreach($resourceGroups as $group)
                            <flux:select.option :value="$group->id">{{ $group->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="resourceGroupId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('agentoom-core::admin.projects.project') }}</flux:label>
                    <flux:select wire:model.live="projectId" variant="listbox" searchable placeholder="{{ __('agentoom-core::admin.projects.no_project') }}">
                        <x-slot name="search">
                            <flux:select.search :placeholder="__('agentoom-core::admin.common.search').'...'"/>
                        </x-slot>
                        @foreach($projects as $project)
                            <flux:select.option :value="$project->id">{{ $project->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            @if(! $projectId)
                <flux:card class="bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800">
                    <div class="flex gap-3">
                        <flux:icon icon="exclamation-triangle" class="text-amber-600 dark:text-amber-400 size-5 shrink-0" />
                        <div class="text-sm text-amber-700 dark:text-amber-400">
                            {{ __('agentoom-core::admin.projects.no_project_warning') }}
                        </div>
                    </div>
                </flux:card>
            @endif

            <flux:textarea
                wire:model="description"
                :label="__('agentoom-core::admin.resource_types.description')"
            />

            @if(auth()->user()->hasAnyRole(['superadmin', 'admin']))
                <flux:select wire:model="user_id" :label="__('agentoom-core::admin.resources.owner')" variant="listbox" searchable>
                    <x-slot name="search">
                        <flux:select.search :placeholder="__('agentoom-core::admin.common.search').'...'"/>
                    </x-slot>
                    @foreach($users as $user)
                        <flux:select.option :value="$user->id">{{ $user->name }} ({{ $user->email }})</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </flux:card>

        <flux:card class="bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800">
            <div class="flex gap-3">
                <flux:icon icon="information-circle" class="text-blue-600 dark:text-blue-400 size-6 shrink-0" />
                <div class="space-y-2">
                    <flux:heading size="sm" class="text-blue-800 dark:text-blue-300">
                        {{ __('agentoom-core::admin.resource_types.config_help') }}
                    </flux:heading>
                    <div class="text-sm text-blue-700 dark:text-blue-400 space-y-1">
                        <p>{{ __('agentoom-core::admin.resource_types.config_help_intro') }}</p>
                        <ul class="list-disc list-inside ml-2">
                            <li><strong>{{ __('agentoom-core::admin.resource_types.field_name') }}:</strong> {{ __('agentoom-core::admin.resource_types.config_help_field_name') }}</li>
                            <li><strong>{{ __('agentoom-core::admin.resource_types.field_type') }}:</strong> {{ __('agentoom-core::admin.resource_types.config_help_field_type') }}</li>
                            <li><strong>{{ __('agentoom-core::admin.resource_types.is_required') }}:</strong> {{ __('agentoom-core::admin.resource_types.config_help_required') }}</li>
                            <li><strong>{{ __('agentoom-core::admin.resource_types.is_encrypted') }}:</strong> {{ __('agentoom-core::admin.resource_types.config_help_encrypted') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </flux:card>

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('agentoom-core::admin.resource_types.config_fields') }}</flux:heading>
                <flux:button icon="plus" size="sm" wire:click="addField">
                    {{ __('agentoom-core::admin.resource_types.add_field') }}
                </flux:button>
            </div>

            @foreach($config as $index => $field)
                <flux:card class="relative space-y-4" wire:key="field-{{ $index }}">
                    <div class="absolute top-4 right-4 flex items-center gap-2">
                        <flux:button variant="ghost" icon="chevron-up" size="sm" wire:click="moveFieldUp({{ $index }})" :disabled="$index === 0" />
                        <flux:button variant="ghost" icon="chevron-down" size="sm" wire:click="moveFieldDown({{ $index }})" :disabled="$index === count($config) - 1" />
                        <flux:button variant="ghost" icon="trash" size="sm" wire:click="removeField({{ $index }})" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input
                            wire:model="config.{{ $index }}.name"
                            :label="__('agentoom-core::admin.resource_types.field_name')"
                            required
                        />

                        <flux:select wire:model.live="config.{{ $index }}.type" :label="__('agentoom-core::admin.resource_types.field_type')">
                            <flux:select.option value="text">{{ __('agentoom-core::admin.field_types.text') }}</flux:select.option>
                            <flux:select.option value="number">{{ __('agentoom-core::admin.field_types.number') }}</flux:select.option>
                            <flux:select.option value="textarea">{{ __('agentoom-core::admin.field_types.textarea') }}</flux:select.option>
                            <flux:select.option value="date">{{ __('agentoom-core::admin.field_types.date') }}</flux:select.option>
                            <flux:select.option value="password">{{ __('agentoom-core::admin.field_types.password') }}</flux:select.option>
                            <flux:select.option value="select">{{ __('agentoom-core::admin.field_types.select') }}</flux:select.option>
                            <flux:select.option value="radio">{{ __('agentoom-core::admin.field_types.radio') }}</flux:select.option>
                            <flux:select.option value="checkbox">{{ __('agentoom-core::admin.field_types.checkbox') }}</flux:select.option>
                        </flux:select>
                    </div>

                    @if(in_array($field['type'] ?? '', ['select', 'radio', 'checkbox']))
                        <div class="space-y-2 mt-4">
                            <div class="flex items-center justify-between">
                                <flux:label>{{ __('agentoom-core::admin.resource_types.options') }}</flux:label>
                                <flux:button wire:click="addOption({{ $index }})" size="xs" icon="plus" variant="ghost">
                                    {{ __('agentoom-core::admin.resource_types.add_option') }}
                                </flux:button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                @foreach($field['options'] ?? [] as $optIndex => $option)
                                    <div class="flex gap-2" wire:key="option-{{ $index }}-{{ $optIndex }}">
                                        <flux:input wire:model="config.{{ $index }}.options.{{ $optIndex }}"
                                                    placeholder="{{ __('agentoom-core::admin.resource_types.field_label') }}" class="flex-1" />
                                        <flux:button wire:click="removeOption({{ $index }}, {{ $optIndex }})"
                                                     icon="trash" variant="ghost" size="sm" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center gap-4">
                        <flux:checkbox
                            wire:model="config.{{ $index }}.required"
                            :label="__('agentoom-core::admin.resource_types.is_required')"
                        />
                        <flux:checkbox
                            wire:model="config.{{ $index }}.encrypted"
                            :label="__('agentoom-core::admin.resource_types.is_encrypted')"
                        />
                    </div>
                </flux:card>
            @endforeach

            @error('config')
                <p class="text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end gap-3">
            <flux:button :href="route('admin.resource-types.index')" wire:navigate>
                {{ __('agentoom-core::admin.common.cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('agentoom-core::admin.common.save') }}
            </flux:button>
        </div>
    </form>
</div>
