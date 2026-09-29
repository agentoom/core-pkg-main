<script setup>
const props = defineProps({
    show: Boolean,
    title: String,
    variant: {
        type: String,
        default: 'md'
    }
})

const emit = defineEmits(['close'])

const sizes = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
    '3xl': 'max-w-3xl',
    '4xl': 'max-w-4xl',
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div @click="$emit('close')" class="absolute inset-0 bg-zinc-500/75 dark:bg-zinc-900/80 transition-opacity" aria-hidden="true"></div>

            <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

            <div :class="['relative inline-block transform overflow-hidden rounded-2xl bg-white dark:bg-zinc-800 p-6 text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:align-middle', sizes[variant]]">
                <div v-if="title" class="mb-4">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white" id="modal-title">{{ title }}</h3>
                </div>
                <div>
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
