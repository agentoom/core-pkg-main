<div
    x-data="{
        search: '',
        sections: @js($openGroups),
        matchesSearch(label) {
            return this.search === '' || label.toLowerCase().includes(this.search.toLowerCase());
        },
        sectionHasMatch(items) {
            if (this.search === '') return true;
            return items.some(label => label.toLowerCase().includes(this.search.toLowerCase()));
        }
    }"
    class="space-y-6"
>
    <div>
        <flux:heading size="xl">{{ __('agentoom-core::admin.import_export.title') }}</flux:heading>
        <flux:subheading>{{ __('agentoom-core::admin.import_export.subtitle') }}</flux:subheading>
    </div>

    @if (session('success'))
        <flux:callout variant="success">{{ session('success') }}</flux:callout>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">

        {{-- Import Section --}}
        <flux:card class="space-y-4">
            <flux:heading level="2">{{ __('agentoom-core::admin.import_export.import.title') }}</flux:heading>
            <flux:text>{{ __('agentoom-core::admin.import_export.import.description') }}</flux:text>

            <flux:field>
                <flux:label>{{ __('agentoom-core::admin.import_export.import.json_file') }}</flux:label>
                <flux:file-upload wire:model="importFile">
                    <flux:file-upload.dropzone
                        :heading="__('agentoom-core::admin.import_export.import.drop_zone_heading')"
                        :text="__('agentoom-core::admin.import_export.import.drop_zone_text')"
                        inline
                    />
                </flux:file-upload>
                <flux:error name="importFile" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('agentoom-core::admin.import_export.import.paste_json') }}</flux:label>
                <flux:textarea wire:model="importText" rows="10" />
                <flux:error name="importText" />
            </flux:field>

            <flux:error name="import" />

            <flux:button wire:click="import" variant="primary" icon="arrow-up-tray" class="w-full">
                {{ __('agentoom-core::admin.import_export.import.button') }}
            </flux:button>
        </flux:card>

        {{-- Export Section --}}
        <flux:card class="space-y-4">
            <flux:heading level="2">{{ __('agentoom-core::admin.import_export.export.title') }}</flux:heading>
            <flux:text>{{ __('agentoom-core::admin.import_export.export.description') }}</flux:text>

            <flux:input
                x-model="search"
                placeholder="{{ __('agentoom-core::admin.common.search') }}..."
                icon="magnifying-glass"
                clearable
            />

            <div class="space-y-2 max-h-[600px] overflow-y-auto pr-1">

                {{-- One block per UI group, one section per registered import/export section. --}}
                @foreach ($groups as $group => $groupData)
                    @php
                        $groupLabels = collect($groupData['sections'])
                            ->flatMap(fn (array $section): array => array_column($section['options'], 'label'))
                            ->all();
                    @endphp
                    <div x-show="sectionHasMatch({{ json_encode($groupLabels) }})">
                        <button type="button" @click="sections.{{ $group }} = !sections.{{ $group }}"
                            class="flex items-center justify-between w-full text-left py-2 px-3 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition">
                            <span class="font-medium text-sm">{{ $groupData['title'] }}</span>
                            <flux:icon.chevron-down class="size-4 transition-transform" x-bind:class="sections.{{ $group }} ? 'rotate-180' : ''" />
                        </button>
                        <div x-show="sections.{{ $group }}" x-collapse class="mt-1 space-y-3 pl-3 pb-2">
                            @foreach ($groupData['sections'] as $section)
                                <div class="space-y-1">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mt-1">{{ $section['label'] }}</p>
                                    <flux:checkbox wire:model.live="selectAll.{{ $section['key'] }}" :label="$section['all_label']" />
                                    @if (! ($selectAll[$section['key']] ?? false))
                                        @foreach ($section['options'] as $option)
                                            <div x-show="matchesSearch('{{ addslashes($option['label']) }}')">
                                                <flux:checkbox wire:model="exportOptions.{{ $section['key'] }}" :value="$option['id']" :label="$option['label']" />
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

            </div>

            <flux:error name="export" />

            <flux:button wire:click="export" variant="primary" icon="arrow-down-tray" class="w-full">
                {{ __('agentoom-core::admin.import_export.export.button') }}
            </flux:button>
        </flux:card>

    </div>
</div>
