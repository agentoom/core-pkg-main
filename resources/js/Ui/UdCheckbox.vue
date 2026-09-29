<script setup>
const props = defineProps({
    modelValue: [Boolean, Array],
    value: [String, Number, Boolean],
    label: String,
})

const emit = defineEmits(['update:modelValue'])

const toggle = (e) => {
    if (Array.isArray(props.modelValue)) {
        const newValue = [...props.modelValue]
        if (e.target.checked) {
            newValue.push(props.value)
        } else {
            const index = newValue.indexOf(props.value)
            if (index > -1) newValue.splice(index, 1)
        }
        emit('update:modelValue', newValue)
    } else {
        emit('update:modelValue', e.target.checked)
    }
}

const isChecked = () => {
    if (Array.isArray(props.modelValue)) {
        return props.modelValue.includes(props.value)
    }
    return !!props.modelValue
}
</script>

<template>
    <label class="inline-flex items-center gap-2 cursor-pointer group">
        <input
            type="checkbox"
            :checked="isChecked()"
            @change="toggle"
            class="size-4 rounded border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-zinc-700 dark:bg-zinc-800"
        />
        <span v-if="label" class="text-sm text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-zinc-100 transition-colors">
            {{ label }}
        </span>
    </label>
</template>
