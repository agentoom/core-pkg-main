<div class="space-y-6 max-w-4xl mx-auto">
    <div>
        <flux:heading size="xl">{{ $is_editing ? __('agentoom-core::admin.resources.edit') : __('agentoom-core::admin.resources.new') }}</flux:heading>
        <flux:subheading>{{ __('agentoom-core::admin.resources.create_edit_subtitle') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-6">
            <flux:select
                wire:model.live="resource_type_id"
                variant="listbox"
                searchable
                :label="__('agentoom-core::admin.resources.resource_type')"
                required
            >
                <flux:select.option value="">{{ __('agentoom-core::admin.common.select_option') }}</flux:select.option>
                @foreach($resourceTypes as $type)
                    <flux:select.option value="{{ $type->id }}">{{ $type->name_translated }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select
                wire:model.live="projectId"
                :label="__('agentoom-core::admin.projects.title')"
                variant="listbox"
                searchable
            >
                <flux:select.option value="">No Project</flux:select.option>
                @foreach($projects as $project)
                    <flux:select.option value="{{ $project->id }}">{{ $project->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if(!$projectId)
                <flux:callout variant="warning">
                    {{ __('agentoom-core::admin.common.no_project_warning') }}
                </flux:callout>
            @endif

            <flux:input
                wire:model="name"
                :label="__('agentoom-core::admin.resources.name')"
                required
            />

            <flux:textarea
                wire:model="description"
                :label="__('agentoom-core::admin.resources.description')"
            />
        </flux:card>

        @if($resource_type_id)
            <flux:card class="space-y-6">
                <flux:heading size="lg">{{ __('agentoom-core::admin.resources.dynamic_fields') }}</flux:heading>

                @if($this->getOAuthService())
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('agentoom-core::admin.integrations.redirect_uri') }}</p>
                        <div class="p-2 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-md text-xs font-mono break-all select-all text-zinc-600 dark:text-zinc-400">
                            {{ $this->getRedirectUri() }}
                        </div>
                    </div>

                    @if($is_editing)
                        <div class="flex items-center gap-4">
                            <flux:button wire:click="connect" variant="primary" icon="bolt">
                                {{ __('agentoom-core::admin.integrations.connect') }}
                            </flux:button>
                            <span class="text-sm {{ ($data['status'] ?? '') === 'connected' ? 'text-green-600' : 'text-zinc-500' }}">
                                {{ ($data['status'] ?? '') === 'connected' ? __('agentoom-core::admin.integrations.connected') : __('agentoom-core::admin.integrations.not_connected') }}
                            </span>
                        </div>
                    @else
                        <flux:callout variant="info" icon="information-circle">
                            {{ __('agentoom-core::admin.resources.save_before_connect') }}
                        </flux:callout>
                    @endif
                @endif

                @php
                    $selectedType = $resourceTypes->firstWhere('id', $resource_type_id);
                @endphp

                @if($selectedType)
                    @foreach($selectedType->fields as $field)
                        @php
                            $fieldType = $field['type'] ?? 'text';
                            $fieldName = $field['name'];
                            $fieldLabel = $field['label'] ?? $fieldName;
                            $fieldRequired = $field['required'] ?? false;
                        @endphp

                        @if($fieldType === 'textarea')
                            <flux:textarea
                                wire:model="data.{{ $fieldName }}"
                                :label="$fieldLabel"
                                :required="$fieldRequired"
                            />
                        @elseif($fieldType === 'number')
                            <flux:input
                                type="number"
                                wire:model="data.{{ $fieldName }}"
                                :label="$fieldLabel"
                                :required="$fieldRequired"
                            />
                        @elseif($fieldType === 'date')
                            <flux:input
                                type="date"
                                wire:model="data.{{ $fieldName }}"
                                :label="$fieldLabel"
                                :required="$fieldRequired"
                            />
                        @elseif($fieldType === 'password')
                            <flux:input
                                type="password"
                                wire:model="data.{{ $fieldName }}"
                                :label="$fieldLabel"
                                :required="$fieldRequired"
                            />
                        @elseif($fieldType === 'select')
                            <flux:select
                                wire:model="data.{{ $fieldName }}"
                                :label="$fieldLabel"
                                :required="$fieldRequired"
                            >
                                <flux:select.option value="">{{ __('agentoom-core::admin.common.select_option') }}</flux:select.option>
                                @foreach($field['options'] ?? [] as $option)
                                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @elseif($fieldType === 'radio')
                            <flux:radio.group
                                wire:model="data.{{ $fieldName }}"
                                :label="$fieldLabel"
                                :required="$fieldRequired"
                            >
                                @foreach($field['options'] ?? [] as $option)
                                    <flux:radio value="{{ $option }}" label="{{ $option }}" />
                                @endforeach
                            </flux:radio.group>
                        @elseif($fieldType === 'checkbox')
                            @if(!empty($field['options']))
                                <flux:checkbox.group
                                    wire:model="data.{{ $fieldName }}"
                                    :label="$fieldLabel"
                                    :required="$fieldRequired"
                                >
                                    @foreach($field['options'] ?? [] as $option)
                                        <flux:checkbox value="{{ $option }}" label="{{ $option }}" />
                                    @endforeach
                                </flux:checkbox.group>
                            @else
                                <flux:checkbox
                                    wire:model="data.{{ $fieldName }}"
                                    :label="$fieldLabel"
                                />
                            @endif
                        @else
                            <flux:input
                                wire:model="data.{{ $fieldName }}"
                                :label="$fieldLabel"
                                :required="$fieldRequired"
                            />
                        @endif
                    @endforeach
                @endif
            </flux:card>
        @endif


        <div class="flex justify-end gap-3">
            <flux:button :href="route('admin.resources.index')" wire:navigate>
                {{ __('agentoom-core::admin.common.cancel') }}
            </flux:button>
            @if($is_editing)
                <flux:button type="button" variant="subtle" icon="document-duplicate" wire:click="clone">{{ __('agentoom-core::admin.common.clone') }}</flux:button>
            @endif
            <flux:button type="button" variant="subtle" wire:click="save(true)">{{ __('agentoom-core::admin.common.save_and_stay') }}</flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('agentoom-core::admin.common.save') }}
            </flux:button>
        </div>
    </form>
</div>
