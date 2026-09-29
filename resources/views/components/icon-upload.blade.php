@props([
    'label',
    'hint' => null,
    'wireModel',
    'existingUrl' => null,
    'removeAction' => null,
])

<div class="flex flex-col gap-2">
    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $label }}</span>

    <div
        x-data="{
            isDragging: false,
            hasImage: {{ ($existingUrl) ? 'true' : 'false' }},
            previewUrl: '{{ $existingUrl ?? '' }}',
            handleDrop(e) {
                this.isDragging = false;
                const file = e.dataTransfer.files[0];
                if (file && file.type.startsWith('image/')) {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    this.$refs.input.files = dt.files;
                    this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            },
            handleChange(e) {
                const file = e.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = (ev) => { this.previewUrl = ev.target.result; this.hasImage = true; };
                reader.readAsDataURL(file);
            }
        }"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="handleDrop($event)"
        @click="$refs.input.click()"
        :class="{
            'border-blue-400 bg-blue-50 dark:bg-blue-950/30': isDragging,
            'border-zinc-200 dark:border-white/10 bg-zinc-50 dark:bg-white/5 hover:border-zinc-300 dark:hover:border-white/20 hover:bg-zinc-100 dark:hover:bg-white/10': !isDragging && !hasImage,
            'border-zinc-200 dark:border-white/10': hasImage
        }"
        class="relative rounded-xl border-2 border-dashed transition-all duration-150 cursor-pointer overflow-hidden"
        style="min-height: 140px;"
    >
        <input
            x-ref="input"
            type="file"
            wire:model="{{ $wireModel }}"
            accept="image/*"
            class="hidden"
            @change="handleChange($event)"
        >

        <div x-show="!hasImage" class="flex flex-col items-center justify-center gap-2 p-8 text-center">
            <div class="flex items-center justify-center w-10 h-10 rounded-full bg-white dark:bg-white/10 border border-zinc-200 dark:border-white/10">
                <flux:icon name="arrow-up-tray" class="size-5 text-zinc-400" />
            </div>
            <div>
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('agentoom-core::admin.common.drop_image_here') }}</p>
                <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('agentoom-core::admin.common.or_click_to_browse') }}</p>
            </div>
            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('agentoom-core::admin.common.png_jpg_webp_max_2_mb') }}</p>
        </div>

        <div x-show="hasImage" class="relative flex items-center justify-center p-4">
            <button
                type="button"
                @click.stop="hasImage = false; previewUrl = ''; $refs.input.value = ''; {{ $removeAction }}"
                class="absolute top-2 right-2 flex items-center justify-center w-7 h-7 rounded-full bg-red-500/50 text-white hover:bg-red-500/70 transition-colors z-10"
            >
                <flux:icon name="trash" class="size-3.5" />
            </button>
            <img :src="previewUrl" class="rounded-lg" style="max-height: 150px; width: auto;" alt="">
        </div>
    </div>

    @if ($hint)
        <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ $hint }}</p>
    @endif
</div>