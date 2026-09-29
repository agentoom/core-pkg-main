<div class="space-y-6 max-w-4xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $is_editing ? __('agentoom-core::admin.schedules.edit') : __('agentoom-core::admin.schedules.new') }}</flux:heading>
            <flux:subheading>{{ $is_editing ? __('agentoom-core::admin.schedules.edit_subtitle') : __('agentoom-core::admin.schedules.create_subtitle') }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            @if($is_editing)
                <flux:button icon="list-bullet" :href="route('admin.schedules.logs', $schedule)" wire:navigate>
                    {{ __('agentoom-core::admin.schedules.logs') }}
                </flux:button>
            @endif
            <flux:button icon="chevron-left" :href="route('admin.schedules.index')" wire:navigate>
                {{ __('agentoom-core::admin.common.back') }}
            </flux:button>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-6">
            <div class="flex flex-wrap gap-4 text-sm text-zinc-500">
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

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                <flux:field class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <flux:label>{{ __('agentoom-core::admin.schedules.active') }}</flux:label>
                    <flux:radio.group wire:model.live="active" variant="segmented">
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

            {{-- Name --}}
            <flux:input
                label="{{ __('agentoom-core::admin.schedules.name') }}"
                wire:model="name"
                required
            />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Target Type --}}
                <flux:select
                    label="{{ __('agentoom-core::admin.schedules.target_type') }}"
                    wire:model.live="target_type"
                    required
                >
                    <flux:select.option value="agent">{{ __('agentoom-core::admin.schedules.agent') }}</flux:select.option>
                    <flux:select.option value="pipeline">{{ __('agentoom-core::admin.schedules.pipeline') }}</flux:select.option>
                    <flux:select.option value="custom">{{ __('agentoom-core::admin.schedules.custom_method') }}</flux:select.option>
                </flux:select>

                {{-- Target ID or Method --}}
                @if($target_type === 'custom')
                    <flux:field>
                        <flux:label>{{ __('agentoom-core::admin.schedules.custom_method') }} <span class="text-red-500">*</span></flux:label>
                        
                        <div class="flex flex-col gap-2">
                            @if(!empty($selectedMethodKey) && isset($actionableMethods[$selectedMethodKey]))
                                @php $selectedMethod = $actionableMethods[$selectedMethodKey]; @endphp
                                <div class="flex items-center justify-between p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-semibold">{{ $selectedMethod['name'] }}</span>
                                        @if($selectedMethod['description'])
                                            <span class="text-xs text-zinc-500">{{ $selectedMethod['description'] }}</span>
                                        @endif
                                    </div>
                                    <flux:button type="button" size="xs" variant="ghost" wire:click="openMethodSelector">{{ __('agentoom-core::admin.common.edit') }}</flux:button>
                                </div>
                            @else
                                <flux:button type="button" variant="outline" class="w-full" icon="magnifying-glass" wire:click="openMethodSelector">
                                    {{ __('agentoom-core::admin.schedules.select_method') }}
                                </flux:button>
                            @endif
                        </div>
                        <flux:error name="selectedMethodKey" />
                    </flux:field>
                @else
                    <flux:select
                        variant="listbox"
                        label="{{ __('agentoom-core::admin.schedules.target') }}"
                        wire:model.live="model_id"
                        required
                        searchable
                    >
                        <flux:select.option value="">{{ __('agentoom-core::admin.common.select') }}</flux:select.option>
                        @if($target_type === 'agent')
                            @foreach($agents as $agent)
                                <flux:select.option value="{{ $agent->id }}">{{ $agent->name }}</flux:select.option>
                            @endforeach
                        @else
                            @foreach($pipelines as $pipeline)
                                <flux:select.option value="{{ $pipeline->id }}">{{ $pipeline->name }}</flux:select.option>
                            @endforeach
                        @endif
                    </flux:select>
                @endif
            </div>

            {{-- Instructions / Parameters --}}
            @if($target_type === 'custom')
                <div class="space-y-4">
                    <flux:label>{{ __('agentoom-core::admin.schedules.parameters') }}</flux:label>
                    <div class="p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                        <livewire:agentoom-core::admin.json-structure-helper :initial-fields="$this->getInstructionFields()" :key="'helper-'.$selectedMethodKey" />
                    </div>
                    <flux:subheading class="text-xs">
                        {!! __('agentoom-core::admin.schedules.relative_timeframes_explanation') !!}
                    </flux:subheading>
                </div>
            @else
                <flux:textarea
                    label="{{ __('agentoom-core::admin.schedules.instructions') }}"
                    wire:model="instructions"
                    rows="6"
                />
            @endif

            {{-- Repository Instructions --}}
            <flux:textarea
                label="{{ __('agentoom-core::admin.schedules.repository_instructions') }}"
                wire:model="repository_instructions"
                rows="4"
                placeholder="{{ __('agentoom-core::admin.schedules.repository_instructions_placeholder') }}"
            />

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

            {{-- Timezone --}}
            <flux:select
                label="{{ __('agentoom-core::admin.schedules.timezone') }}"
                wire:model="timezone"
                required
                searchable
            >
                @foreach($timezones as $tz)
                    <flux:select.option value="{{ $tz }}">{{ $tz }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:separator />

            <flux:heading size="lg">{{ __('agentoom-core::admin.schedules.access_control') }}</flux:heading>
            <flux:subheading>{{ __('agentoom-core::admin.schedules.access_control_subtitle') }}</flux:subheading>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Project --}}
                <flux:select
                    variant="listbox"
                    label="{{ __('agentoom-core::admin.projects.title') }}"
                    wire:model.live="projectId"
                    searchable
                >
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Assign to User --}}
                <flux:select
                    variant="listbox"
                    label="{{ __('agentoom-core::admin.schedules.assign_to_user') }}"
                    wire:model="user_id"
                    searchable
                >
                    <flux:select.option value="">{{ __('agentoom-core::admin.schedules.public_anyone') }}</flux:select.option>
                    @foreach($users as $user)
                        <flux:select.option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</flux:select.option>
                    @endforeach
                </flux:select>

            </div>
        </flux:card>

        <div class="flex justify-end gap-3">
            <flux:button variant="ghost" :href="route('admin.schedules.index')" wire:navigate>
                {{ __('agentoom-core::admin.common.cancel') }}
            </flux:button>
            @if($schedule?->exists)
                <flux:button type="button" variant="subtle" icon="document-duplicate" wire:click="clone">{{ __('agentoom-core::admin.common.clone') }}</flux:button>
            @endif
            <flux:button type="button" variant="subtle" wire:click="save(true)">{{ __('agentoom-core::admin.common.save_and_stay') }}</flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('agentoom-core::admin.common.save') }}
            </flux:button>
        </div>
    </form>

    <livewire:agentoom-core::admin.action-method-selector />
</div>
