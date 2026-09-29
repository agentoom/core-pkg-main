<script setup>
import { provide } from 'vue'

const props = defineProps({
    modelValue: [String, Number, Boolean],
    label: String,
    variant: String
})

const emit = defineEmits(['update:modelValue'])

provide('radioGroup', {
    modelValue: props,
    updateValue: (val) => emit('update:modelValue', val)
})
</script>

<template>
    <div class="space-y-2">
        <label v-if="label" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ label }}</label>
        <div :class="[
            variant === 'segmented' 
                ? 'inline-flex p-1 bg-zinc-100 dark:bg-zinc-800 rounded-lg' 
                : 'flex flex-wrap gap-4'
        ]">
            <slot />
        </div>
    </div>
</template>
