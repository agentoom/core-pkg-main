<div>
    <flux:button wire:click="$set('show_modal', true)" variant="outline" icon="arrow-up-tray">
        {{ __('agentoom-core::admin.common.import') }}
    </flux:button>

    <flux:modal wire:model="show_modal" class="md:w-[600px] space-y-6">
        <div>
            <flux:heading size="lg">{{ __('agentoom-core::admin.resources.import_modal_title') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.resources.import_modal_subtitle') }}</flux:subheading>
        </div>

        @if ($step === 1)
            <div class="space-y-4">
                <flux:select wire:model.live="resource_type_id" label="{{ __('agentoom-core::admin.resources.resource_type') }}" placeholder="{{ __('agentoom-core::admin.common.select_option') }}">
                    @foreach ($resourceTypes as $type)
                        <flux:select.option value="{{ $type->id }}">{{ $type->name_translated }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model.live="file" type="file" label="{{ __('agentoom-core::admin.resources.select_file') }}" accept=".csv,.txt,.xls,.xlsx" />

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button wire:click="$set('show_modal', false)">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                    <flux:button variant="primary" wire:click="next" :disabled="!$file || !$resource_type_id">{{ __('agentoom-core::admin.common.next') }}</flux:button>
                </div>
            </div>
        @else
            <div class="space-y-4">
                <div class="bg-zinc-50 dark:bg-white/5 p-4 rounded-lg border border-zinc-200 dark:border-white/10">
                    <div class="grid grid-cols-2 gap-4 font-medium text-sm text-zinc-500 mb-2">
                        <div>{{ __('agentoom-core::admin.resources.resource_fields') }}</div>
                        <div>{{ __('agentoom-core::admin.resources.file_headers') }}</div>
                    </div>

                    <div class="space-y-3">
                        {{-- Fixed fields like name and description if they are not in the dynamic fields --}}
                        <div class="grid grid-cols-2 gap-4 items-center">
                            <flux:label>{{ __('agentoom-core::admin.resources.name') }}</flux:label>
                            <flux:select wire:model="mapping.name" placeholder="{{ __('agentoom-core::admin.common.select_option') }}">
                                <flux:select.option value="">-- {{ __('agentoom-core::admin.common.none') }} --</flux:select.option>
                                @foreach ($headers as $index => $header)
                                    <flux:select.option value="{{ $index }}">{{ $header }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="grid grid-cols-2 gap-4 items-center">
                            <flux:label>{{ __('agentoom-core::admin.resources.description') }}</flux:label>
                            <flux:select wire:model="mapping.description" placeholder="{{ __('agentoom-core::admin.common.select_option') }}">
                                <flux:select.option value="">-- {{ __('agentoom-core::admin.common.none') }} --</flux:select.option>
                                @foreach ($headers as $index => $header)
                                    <flux:select.option value="{{ $index }}">{{ $header }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>

                        <div class="my-4 border-t border-zinc-200 dark:border-white/10"></div>

                        @foreach ($targetFields as $field)
                            <div class="grid grid-cols-2 gap-4 items-center">
                                <flux:label>{{ $field['name'] }} @if(!empty($field['required'])) <span class="text-red-500">*</span> @endif</flux:label>
                                <flux:select wire:model="mapping.{{ $field['name'] }}" placeholder="{{ __('agentoom-core::admin.common.select_option') }}">
                                    <flux:select.option value="">-- {{ __('agentoom-core::admin.common.none') }} --</flux:select.option>
                                    @foreach ($headers as $index => $header)
                                        <flux:select.option value="{{ $index }}">{{ $header }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                        @endforeach
                    </div>

                    <div class="my-4 border-t border-zinc-200 dark:border-white/10"></div>

                    <div class="space-y-3">
                        <flux:select wire:model="unique_field" label="{{ __('agentoom-core::admin.resources.unique_field') }}" placeholder="{{ __('agentoom-core::admin.common.none') }}">
                            <flux:select.option value="">-- {{ __('agentoom-core::admin.common.none') }} --</flux:select.option>
                            <flux:select.option value="name">{{ __('agentoom-core::admin.resources.name') }}</flux:select.option>
                            @foreach ($targetFields as $field)
                                <flux:select.option value="{{ $field['name'] }}">{{ $field['name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <p class="text-xs text-zinc-500">{{ __('agentoom-core::admin.resources.unique_field_help') }}</p>
                    </div>
                </div>

                <div class="flex justify-between pt-4">
                    <flux:button wire:click="$set('step', 1)">{{ __('agentoom-core::admin.common.back') }}</flux:button>
                    <div class="flex gap-2">
                        <flux:button wire:click="$set('show_modal', false)">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
                        <flux:button variant="primary" wire:click="import">{{ __('agentoom-core::admin.common.import') }}</flux:button>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
