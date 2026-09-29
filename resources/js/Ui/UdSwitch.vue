<script setup>
const props = defineProps({
    modelValue: [Boolean, Number],
    checked: {
        type: Boolean,
        default: undefined
    },
    size: {
        type: String,
        default: 'md'
    }
})

const emit = defineEmits(['update:modelValue', 'update:checked'])

const toggle = () => {
    const newValue = props.checked !== undefined ? !props.checked : !props.modelValue
    emit('update:modelValue', newValue)
    emit('update:checked', newValue)
}

const isChecked = () => {
    if (props.checked !== undefined) return props.checked
    return !!props.modelValue
}
</script>

<template>
    <button
        type="button"
        @click="toggle"
        :class="[
            'relative inline-flex shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2',
            size === 'sm' ? 'h-5 w-9' : 'h-6 w-11',
            isChecked() ? 'bg-blue-600' : 'bg-zinc-200 dark:bg-zinc-700'
        ]"
    >
        <span
            aria-hidden="true"
            :class="[
                'pointer-events-none inline-block transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                size === 'sm' ? 'h-4 w-4' : 'h-5 w-5',
                isChecked() 
                    ? (size === 'sm' ? 'translate-x-4' : 'translate-x-5') 
                    : 'translate-x-0'
            ]"
        ></span>
    </button>
</template>
