<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header --}}
    <div>
        <flux:heading size="xl">
            {{ $is_editing ? __('agentoom-core::admin.endpoints.edit') : __('agentoom-core::admin.endpoints.new') }}
        </flux:heading>
        <flux:subheading>
            {{ $is_editing ? __('agentoom-core::admin.endpoints.edit_subtitle') : __('agentoom-core::admin.endpoints.new_subtitle') }}
        </flux:subheading>
    </div>

    <form wire:submit="save">
        <flux:card class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                <flux:field class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <flux:label>{{ __('agentoom-core::admin.endpoints.is_active') }}</flux:label>
                    <flux:radio.group wire:model.live="is_active" variant="segmented">
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

                <flux:field class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <flux:label>{{ __('agentoom-core::admin.endpoints.force_json') }}</flux:label>
                    <flux:description>{{ __('agentoom-core::admin.endpoints.force_json_help') }}</flux:description>
                    <flux:switch wire:model.live="force_json" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:input
                    label="{{ __('agentoom-core::admin.endpoints.name') }}"
                    wire:model="name"
                    required />

                <flux:select variant="listbox" searchable wire:model.live="projectId" label="{{ __('agentoom-core::admin.projects.title') }}">
                    <flux:select.option value="">No Project</flux:select.option>
                    @foreach($projects as $p)
                        <flux:select.option value="{{ $p->id }}">{{ $p->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:field>
                <flux:label>{{ __('agentoom-core::admin.endpoints.url') }}</flux:label>
                <flux:description>{{ __('agentoom-core::admin.endpoints.url_help') }}</flux:description>
                <div class="flex items-center gap-0 mt-1">
                    <span class="inline-flex items-center px-3 py-2 text-sm bg-zinc-100 dark:bg-zinc-700 border border-r-0 border-zinc-200 dark:border-zinc-600 rounded-l-lg text-zinc-500 dark:text-zinc-400 font-mono">{{ url('/e/') }}/</span>
                    <flux:input
                        wire:model="slug"
                        class="rounded-l-none flex-1"
                        placeholder="my-endpoint" />
                    <flux:button type="button" variant="subtle" icon="arrow-path" wire:click="generateSlug" class="ml-2 flex-shrink-0">
                        {{ __('agentoom-core::admin.endpoints.generate_slug') }}
                    </flux:button>
                </div>
            </flux:field>

            @if(!$projectId)
                <flux:callout variant="warning">
                    {{ __('agentoom-core::admin.common.no_project_warning') }}
                </flux:callout>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:select
                        variant="listbox"
                        label="{{ __('agentoom-core::admin.endpoints.agent') }}"
                        wire:model.live="agent_id"
                        searchable
                    >
                        <flux:select.option value="">{{ __('agentoom-core::admin.endpoints.select_agent') }}</flux:select.option>
                        @foreach ($agents as $agent)
                            <flux:select.option value="{{ $agent->id }}">{{ $agent->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select
                        variant="listbox"
                        label="{{ __('agentoom-core::admin.endpoints.pipeline') }}"
                        wire:model.live="pipeline_id"
                        searchable
                    >
                        <flux:select.option value="">{{ __('agentoom-core::admin.endpoints.select_pipeline') }}</flux:select.option>
                        @foreach ($pipelines as $pipeline)
                            <flux:select.option value="{{ $pipeline->id }}">{{ $pipeline->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            @if($agent_id)
                <x-agentoom-core::markdown-editor
                    label="{{ __('agentoom-core::admin.endpoints.prompt') }}"
                    description="{{ __('agentoom-core::admin.endpoints.prompt_help') }}"
                    wire:model="prompt"
                    height="400px"
                />
            @endif

            <flux:textarea
                label="{{ __('agentoom-core::admin.endpoints.description') }}"
                wire:model="description"
                rows="3" />

            <div class="space-y-2">
                <flux:label>{{ __('agentoom-core::admin.endpoints.data_structure') }}</flux:label>
                <div class="flex items-center gap-4">
                    <div class="flex-1 p-3 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg min-h-[50px]">
                        @if (empty($data_structure))
                            <span class="text-zinc-400 text-sm">{{ __('agentoom-core::admin.endpoints.no_structure_defined') }}</span>
                        @else
                            <div class="flex flex-wrap gap-2">
                                @foreach ($data_structure as $field)
                                    <flux:badge variant="outline">
                                        {{ $field['name'] }} ({{ $field['type'] }})
                                    </flux:badge>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <flux:button variant="subtle" icon="pencil-square" wire:click="$set('show_structure_modal', true)">
                        {{ __('agentoom-core::admin.endpoints.edit_structure') }}
                    </flux:button>
                </div>
                <flux:subheading size="sm">
                    {{ __('agentoom-core::admin.endpoints.data_structure_help') }}
                </flux:subheading>
            </div>

            <flux:input
                label="{{ __('agentoom-core::admin.endpoints.allowed_sources') }}"
                description="{{ __('agentoom-core::admin.endpoints.allowed_sources_help') }}"
                wire:model="allowed_sources"
                placeholder="example.com, 192.168.1.1" />

            <div class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="sm">{{ __('agentoom-core::admin.endpoints.direct_response') }}</flux:heading>
                        <flux:subheading size="sm">{{ __('agentoom-core::admin.endpoints.direct_response_help') }}</flux:subheading>
                    </div>
                    <flux:switch wire:model.live="direct_response_enabled" />
                </div>

                @if($direct_response_enabled)
                    <flux:callout variant="warning" icon="exclamation-triangle" size="sm">
                        {{ __('agentoom-core::admin.endpoints.direct_response_warning') }}
                    </flux:callout>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <flux:select wire:model="direct_response_code" label="{{ __('agentoom-core::admin.endpoints.direct_response_code') }}">
                            <flux:select.option value="200">200 OK</flux:select.option>
                            <flux:select.option value="201">201 Created</flux:select.option>
                            <flux:select.option value="204">204 No Content</flux:select.option>
                            <flux:select.option value="400">400 Bad Request</flux:select.option>
                            <flux:select.option value="403">403 Forbidden</flux:select.option>
                            <flux:select.option value="404">404 Not Found</flux:select.option>
                            <flux:select.option value="422">422 Unprocessable Entity</flux:select.option>
                            <flux:select.option value="500">500 Server Error</flux:select.option>
                        </flux:select>
                        <flux:input
                            label="{{ __('agentoom-core::admin.endpoints.direct_response_content_type') }}"
                            wire:model="direct_response_content_type"
                            class="md:col-span-3" />
                    </div>
                    <flux:textarea
                        label="{{ __('agentoom-core::admin.endpoints.direct_response_body') }}"
                        wire:model="direct_response_body"
                        rows="6"
                        placeholder='{ "message": "ok" }' />
                @endif
            </div>

            <div class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="sm">{{ __('agentoom-core::admin.endpoints.auth_token') }}</flux:heading>
                        <flux:subheading size="sm">{{ __('agentoom-core::admin.endpoints.auth_token_description') }}</flux:subheading>
                    </div>
                    <flux:radio.group wire:model.live="auth_token_enabled" variant="segmented">
                        <flux:radio :value="1" label="{{ __('agentoom-core::admin.common.enabled') }}" />
                        <flux:radio :value="0" label="{{ __('agentoom-core::admin.common.disabled') }}" />
                    </flux:radio.group>
                </div>

                @if($auth_token_enabled)
                    <div class="space-y-4">
                        <div>
                            <flux:label>{{ __('agentoom-core::admin.endpoints.auth_token_label') }}</flux:label>
                            <div class="flex items-center gap-2 mt-1">
                                <div class="relative flex-1">
                                    <flux:input
                                        wire:model="auth_token"
                                        :type="$show_auth_token ? 'text' : 'password'"
                                        placeholder="{{ __('agentoom-core::admin.endpoints.auth_token_placeholder') }}" />
                                    <button type="button" wire:click="$toggle('show_auth_token')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                                        @if($show_auth_token)
                                            <flux:icon.eye-slash class="w-5 h-5" />
                                        @else
                                            <flux:icon.eye class="w-5 h-5" />
                                        @endif
                                    </button>
                                </div>
                                <flux:button type="button" variant="subtle" icon="arrow-path" wire:click="regenerateAuthToken">
                                    {{ __('agentoom-core::admin.endpoints.auth_token_regenerate') }}
                                </flux:button>
                                <flux:button type="button" variant="subtle" icon="document-plus" wire:click="generateAuthToken">
                                    {{ __('agentoom-core::admin.endpoints.auth_token_generate') }}
                                </flux:button>
                            </div>
                        </div>

                        <flux:input
                            wire:model="auth_token_header"
                            label="{{ __('agentoom-core::admin.endpoints.auth_token_header') }}"
                            description="{{ __('agentoom-core::admin.endpoints.auth_token_header_help') }}" />

                        <flux:callout variant="info" icon="information-circle">
                            <div class="text-sm space-y-1">
                                <p>{{ __('agentoom-core::admin.endpoints.auth_token_explanation_intro') }}</p>
                                <p class="font-mono text-xs bg-zinc-100 dark:bg-zinc-800 px-2 py-1 rounded inline-block">
                                    {{ $auth_token_header }}: {{ $auth_token ?: 'your-token' }}
                                </p>
                                <p>{{ __('agentoom-core::admin.endpoints.auth_token_explanation_howto') }}</p>
                                <p>{{ __('agentoom-core::admin.endpoints.auth_token_explanation_note') }}</p>
                            </div>
                        </flux:callout>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button variant="ghost" :href="route('admin.endpoints.index')" wire:navigate>
                    {{ __('agentoom-core::admin.common.cancel') }}
                </flux:button>
                @if($endpoint?->exists)
                    <flux:button type="button" variant="subtle" icon="document-duplicate" wire:click="clone">{{ __('agentoom-core::admin.common.clone') }}</flux:button>
                @endif
                <flux:button type="button" variant="subtle" wire:click="save(true)">{{ __('agentoom-core::admin.common.save_and_stay') }}</flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('agentoom-core::admin.common.save') }}
                </flux:button>
            </div>
        </flux:card>
    </form>

    {{-- JSON Structure Helper Modal --}}
    <flux:modal wire:model="show_structure_modal" class="max-w-4xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('agentoom-core::admin.endpoints.structure_helper_title') }}</flux:heading>
                <flux:subheading>{{ __('agentoom-core::admin.endpoints.structure_helper_subtitle') }}</flux:subheading>
            </div>

            <livewire:agentoom-core::admin.json-structure-helper :initial-fields="$data_structure" />

            <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button wire:click="$set('show_structure_modal', false)">
                    {{ __('agentoom-core::admin.common.close') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
