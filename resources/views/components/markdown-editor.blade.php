@props([
    'label' => null,
    'description' => null,
    'placeholder' => null,
    'height' => '500px',
])

<div
    x-data="{
        value: @entangle($attributes->wire('model')),
        editor: null,
        init() {
            const isDark = document.documentElement.classList.contains('dark');

            this.editor = new toastui.Editor({
                el: this.$refs.editor,
                height: '{{ $height }}',
                initialEditType: 'markdown',
                previewStyle: 'vertical',
                initialValue: this.value || '',
                placeholder: '{{ $placeholder }}',
                theme: isDark ? 'dark' : 'light',
                usageStatistics: false,
                toolbarItems: [
                    ['heading', 'bold', 'italic', 'strike'],
                    ['hr', 'quote'],
                    ['ul', 'ol', 'task', 'indent', 'outdent'],
                    ['table', 'image', 'link'],
                    ['code', 'codeblock'],
                    ['scrollSync'],
                ],
                events: {
                    change: () => {
                        this.value = this.editor.getMarkdown();
                    }
                }
            });

            this.$watch('value', (newValue) => {
                if (newValue !== this.editor.getMarkdown()) {
                    this.editor.setMarkdown(newValue || '');
                }
            });
        }
    }"
    wire:ignore
    {{ $attributes->whereDoesntStartWith('wire:model')->class(['space-y-2']) }}
>
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    <div x-ref="editor" class="markdown-editor-container border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden"></div>

    <style>
        .markdown-editor-container .toastui-editor-defaultUI {
            border: none;
        }
        .dark .markdown-editor-container .toastui-editor-defaultUI {
            background-color: #18181b;
        }
        .dark .markdown-editor-container .toastui-editor-toolbar {
            background-color: #27272a;
        }
        .dark .markdown-editor-container .toastui-editor-main-container {
            background-color: #18181b;
        }
    </style>
</div>
