<script setup>
import { inject } from 'vue'

const props = defineProps({
    value: [String, Number, Boolean],
    label: String
})

const radioGroup = inject('radioGroup')
</script>

<template>
    <label class="inline-flex items-center px-3 py-1.5 rounded-md cursor-pointer transition-colors"
        :class="[
            radioGroup.modelValue.modelValue === value 
                ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-white' 
                : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'
        ]"
        @click="radioGroup.updateValue(value)"
    >
        <span class="text-xs font-medium">{{ label }}</span>
    </label>
</template>
