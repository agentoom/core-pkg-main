<div class="space-y-6 max-w-5xl mx-auto" wire:poll.5s>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">{{ __('agentoom-core::admin.agent.knowledge_for_agent', ['agent' => $agent->name]) }}</flux:heading>
        <flux:button size="sm" icon="arrow-left" variant="subtle" href="{{ route('admin.agents.index') }}" wire:navigate>
            {{ __('agentoom-core::admin.agent.back') }}
        </flux:button>
    </div>

    @if (session('success'))
        <flux:callout variant="success">{{ session('success') }}</flux:callout>
    @endif

    {{-- Upload Card --}}
    <flux:card class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <flux:icon.cloud-arrow-up class="size-5 text-indigo-500" />
                <flux:heading size="lg">{{ __('agentoom-core::admin.agent.upload_new_knowledge_file') }}</flux:heading>
            </div>
            <div class="flex gap-2">
                <flux:button size="sm" icon="plus" wire:click="addFileInput">
                    {{ __('agentoom-core::admin.agent.add_file') }}
                </flux:button>
                <flux:button size="sm" icon="plus" wire:click="addUrlInput">
                    {{ __('agentoom-core::admin.agent.add_url') }}
                </flux:button>
                <flux:button size="sm" icon="plus" wire:click="addTextInput">
                    {{ __('agentoom-core::admin.agent.add_text') }}
                </flux:button>
            </div>
        </div>

        <form wire:submit="saveKnowledge" class="space-y-4">
            @foreach ($knowledgeInputs as $index => $input)
                <div wire:key="knowledge-input-{{ $input['id'] ?? $index }}" class="p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg bg-zinc-50 dark:bg-zinc-800/50">
                    <flux:label class="mb-2 font-medium">
                        {{ __('agentoom-core::admin.agent.knowledge_file_number', ['num' => $index + 1]) }}
                        @php
                            $typeLabel = match($input['type'] ?? 'file') {
                                'file' => __('agentoom-core::admin.knowledge.file'),
                                'url' => __('agentoom-core::admin.knowledge.url'),
                                'text' => __('agentoom-core::admin.knowledge.text'),
                                default => __('agentoom-core::admin.knowledge.file'),
                            };
                        @endphp
                        ({{ $typeLabel }})
                    </flux:label>
                    <div class="flex items-center gap-3">
                        <div class="flex-1 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-600 rounded-md p-1">
                            @if (($input['type'] ?? 'file') === 'file')
                                <div
                                    wire:key="knowledge-file-wrap-{{ $input['id'] ?? $index }}-{{ $input['upload_key'] ?? '0' }}"
                                    x-data="{
                                        maxBytes: 10 * 1024 * 1024,
                                        tooLargeMessage: @js(__('agentoom-core::admin.knowledge.validation_file_too_large')),
                                        handleChange(event) {
                                            const input = event.target;
                                            const file = input.files && input.files[0];
                                            if (!file) { return; }
                                            if (file.size > this.maxBytes) {
                                                event.preventDefault();
                                                event.stopImmediatePropagation();
                                                input.value = '';
                                                $wire.handleFileUploadError({{ $index }});
                                            }
                                        }
                                    }"
                                    x-on:change.capture="handleChange($event)"
                                    x-on:livewire-upload-error.capture="$wire.handleFileUploadError({{ $index }})"
                                >
                                    <flux:input
                                        type="file"
                                        wire:model="knowledgeInputs.{{ $index }}.file"
                                        accept=".txt,.pdf,.doc,.docx"
                                    />
                                </div>
                            @elseif (($input['type'] ?? 'file') === 'url')
                                <flux:input type="url" wire:model="knowledgeInputs.{{ $index }}.url" placeholder="https://example.com" />
                            @else
                                <flux:textarea wire:model="knowledgeInputs.{{ $index }}.text" placeholder="{{ __('agentoom-core::admin.knowledge.text_placeholder') }}" rows="5" />
                            @endif
                        </div>
                        @if (count($knowledgeInputs) > 1)
                            <flux:button variant="danger" icon="x-mark" class="px-3" wire:click="removeInput({{ $index }})" />
                        @endif
                    </div>
                    @if (($input['type'] ?? 'file') === 'file')
                        <flux:error name="knowledgeInputs.{{ $index }}.file" />
                    @elseif (($input['type'] ?? 'file') === 'url')
                        <flux:error name="knowledgeInputs.{{ $index }}.url" />
                    @else
                        <flux:error name="knowledgeInputs.{{ $index }}.text" />
                    @endif
                </div>
            @endforeach
            <div class="text-xs text-zinc-500 py-1">
                {{ __('agentoom-core::admin.agent.supported_formats_hint') }}
            </div>

            <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button type="submit" variant="primary">
                    {{ __('agentoom-core::admin.agent.save_knowledge') }}
                </flux:button>
            </div>
        </form>
    </flux:card>

    {{-- Stats Row --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 py-2">
        <div class="flex justify-between items-center bg-white dark:bg-zinc-900 p-5 rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div>
                <div class="text-sm font-medium text-zinc-500">{{ __('agentoom-core::admin.knowledge.total_files') }}</div>
                <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ $totalFiles }}</div>
            </div>
            <flux:icon.document-duplicate class="size-8 text-indigo-600/50 dark:text-indigo-400/50" />
        </div>

        <div class="flex justify-between items-center bg-white dark:bg-zinc-900 p-5 rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div>
                <div class="text-sm font-medium text-zinc-500">{{ __('agentoom-core::admin.knowledge.total_files_synchronized') }}</div>
                <div class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ $totalFilesSynchronized }}</div>
            </div>
            <flux:icon.check-circle class="size-8 text-emerald-600/50 dark:text-emerald-400/50" />
        </div>

        <div class="flex justify-between items-center bg-white dark:bg-zinc-900 p-5 rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div>
                <div class="text-sm font-medium text-zinc-500">{{ __('agentoom-core::admin.knowledge.total_files_unsynchronized') }}</div>
                <div class="text-3xl font-bold text-red-600 dark:text-red-400">{{ $totalFilesUnsynchronized }}</div>
            </div>
            <flux:icon.document-text class="size-8 text-red-600/50 dark:text-red-400/50" />
        </div>
    </div>
    {{-- AI Summarization Toggle --}}
    <flux:card class="space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <flux:icon.sparkles class="size-5 text-amber-500" />
                <flux:heading size="lg">{{ __('agentoom-core::admin.knowledge.enable_summarization') }}</flux:heading>
            </div>
            <flux:switch wire:model.live="enable_summarization" />
        </div>
        <flux:callout variant="warning" inline icon="exclamation-triangle">
            {{ __('agentoom-core::admin.knowledge.enable_summarization_disclaimer') }}
        </flux:callout>
    </flux:card>

    @if ($totalFiles === 0)
        <flux:callout variant="warning" inline icon="exclamation-triangle">{{ __('agentoom-core::admin.knowledge.no_files_uploaded_yet') }}</flux:callout>
    @elseif ($totalFilesUnsynchronized > 0)
        <flux:callout variant="warning" inline icon="exclamation-triangle">
            <flux:callout.heading>{{ __('agentoom-core::admin.knowledge.some_files_are_unsynchronized') }}</flux:callout.heading>
            <x-slot name="actions">
                @if ($enable_summarization)
                    <flux:button variant="primary" wire:click="synchronizeFiles">
                        {{ __('agentoom-core::admin.knowledge.synchronize_files') }}
                    </flux:button>
                @else
                    <flux:tooltip content="{{ __('agentoom-core::admin.knowledge.summarization_disabled_warning') }}">
                        <flux:button variant="primary" disabled>
                            {{ __('agentoom-core::admin.knowledge.synchronize_files') }}
                        </flux:button>
                    </flux:tooltip>
                @endif
            </x-slot>
        </flux:callout>
    @else
        <flux:callout variant="success">{{ __('agentoom-core::admin.knowledge.all_files_synchronized') }}</flux:callout>
    @endif

    {{-- Current Files List --}}
    <div>
        <flux:heading size="lg" class="mb-4">{{ __('agentoom-core::admin.agent.current_knowledge_files', ['count' => $totalFiles]) }}</flux:heading>

        <div class="space-y-3">
            @forelse ($files as $file)
                <flux:card class="flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 bg-zinc-900 border-zinc-800">
                    <div class="flex items-center gap-4 mb-3 sm:mb-0">
                        <div @class([
                            'flex items-center justify-center size-12 rounded-lg shrink-0',
                            'bg-emerald-900/40 text-emerald-400' => $file->type === 'file',
                            'bg-indigo-900/40 text-indigo-400' => $file->type === 'url',
                            'bg-amber-900/40 text-amber-400' => $file->type === 'text',
                        ])>
                            @if ($file->type === 'file')
                                <flux:icon.document-text class="size-6" />
                            @elseif ($file->type === 'url')
                                <flux:icon.globe-alt class="size-6" />
                            @else
                                <flux:icon.pencil-square class="size-6" />
                            @endif
                        </div>
                        <div>
                            <div class="font-semibold text-white truncate max-w-sm" title="{{ $file->name }}">{{ $file->name }}</div>
                            <div class="text-sm text-zinc-400 flex items-center gap-2">
                                <span>{{ __('agentoom-core::admin.knowledge.chunks_count', ['count' => $file->knowledge_count]) }}</span>
                                <span class="size-1 bg-zinc-600 rounded-full"></span>
                                <span>
                                    @if ($file->type === 'file')
                                        {{ __('agentoom-core::admin.knowledge.file') }}
                                    @elseif ($file->type === 'url')
                                        {{ __('agentoom-core::admin.knowledge.url') }}
                                    @else
                                        {{ __('agentoom-core::admin.knowledge.text') }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-6">
                        @if ($file->status === 'completed')
                            <div class="flex items-center text-emerald-400 bg-emerald-900/20 px-3 py-1 rounded-full text-xs font-medium border border-emerald-800/50">
                                <flux:icon.check-circle class="size-3.5 mr-1.5" /> {{ __('agentoom-core::admin.knowledge.completed') }}
                            </div>
                        @elseif ($file->status === 'failed')
                            <div class="flex items-center text-red-400 bg-red-900/20 px-3 py-1 rounded-full text-xs font-medium border border-red-800/50" title="{{ $file->last_error }}">
                                <flux:icon.x-mark class="size-3.5 mr-1.5" /> {{ __('agentoom-core::admin.knowledge.failed') }}
                            </div>
                        @elseif ($file->status === 'processing')
                            <div class="flex items-center text-indigo-400 bg-indigo-900/20 px-3 py-1 rounded-full text-xs font-medium border border-indigo-800/50">
                                <flux:icon.arrow-path class="size-3.5 mr-1.5 animate-spin" /> {{ __('agentoom-core::admin.knowledge.processing') }}
                            </div>
                        @elseif ($file->status === 'syncing')
                            <div class="flex items-center text-amber-400 bg-amber-900/20 px-3 py-1 rounded-full text-xs font-medium border border-amber-800/50">
                                <flux:icon.arrow-path class="size-3.5 mr-1.5 animate-spin" /> {{ __('agentoom-core::admin.knowledge.syncing') }}
                            </div>
                        @elseif ($file->status === 'chunked')
                            <div class="flex items-center text-indigo-400 bg-indigo-900/20 px-3 py-1 rounded-full text-xs font-medium border border-indigo-800/50">
                                <flux:icon.check-circle class="size-3.5 mr-1.5" /> {{ __('agentoom-core::admin.knowledge.chunked') }}
                            </div>
                        @else
                            <div class="flex items-center text-zinc-400 bg-zinc-900/20 px-3 py-1 rounded-full text-xs font-medium border border-zinc-800/50">
                                <flux:icon.clock class="size-3.5 mr-1.5" /> {{ __('agentoom-core::admin.knowledge.pending') }}
                            </div>
                        @endif

                        <div class="flex items-center gap-2">
                            @if ($file->type === 'file')
                                <flux:button size="sm" variant="subtle" icon="arrow-down-tray" class="text-zinc-400 hover:text-white" href="{{ Storage::url($file->path) }}" target="_blank" download />
                            @else
                                <flux:button size="sm" variant="subtle" icon="link" class="text-zinc-400 hover:text-white" href="{{ $file->path }}" target="_blank" />
                            @endif
                            <flux:button size="sm" variant="subtle" icon="trash" class="text-red-500 hover:text-red-400" wire:click="confirmDeleteFile({{ $file->id }})" />
                        </div>
                    </div>
                </flux:card>
            @empty
                <div class="text-center py-12 border-2 border-dashed border-zinc-200 dark:border-zinc-700 rounded-xl text-zinc-500">
                    {{ __('agentoom-core::admin.knowledge.no_files_uploaded_yet') }}
                </div>
            @endforelse
        </div>
    </div>

    {{-- File Delete Modal --}}
    <flux:modal wire:model="show_delete_file_modal" class="max-w-md text-center bg-zinc-900">
        <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-900/30 mb-4">
            <flux:icon.exclamation-triangle class="size-6 text-red-500" />
        </div>
        <flux:heading size="lg" class="mb-2 text-white">{{ __('agentoom-core::admin.knowledge.delete_file_title') }}</flux:heading>
        <flux:subheading class="mb-6 text-zinc-400">{{ __('agentoom-core::admin.knowledge.delete_file_text') }}</flux:subheading>
        <div class="flex justify-center gap-3">
            <flux:button variant="subtle" wire:click="$set('show_delete_file_modal', false)" class="text-white">{{ __('agentoom-core::admin.common.cancel') }}</flux:button>
            <flux:button variant="danger" wire:click="deleteFile">{{ __('agentoom-core::admin.common.delete') }}</flux:button>
        </div>
    </flux:modal>
</div>
