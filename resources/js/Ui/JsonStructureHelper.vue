<script setup>
import { ref, watch } from 'vue'
import UdModal from './UdModal.vue'
import UdInput from './UdInput.vue'
import UdSelect from './UdSelect.vue'
import UdButton from './UdButton.vue'
import UdLabel from './UdLabel.vue'

const props = defineProps({
    show: Boolean,
    modelValue: {
        type: [Array, String],
        default: () => [],
    },
    translations: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits(['close', 'update:modelValue'])

const t = (key, fallback) => props.translations[key] || fallback

const fields = ref([])

// Initialize fields when modal opens
watch(() => props.show, (isOpen) => {
    if (isOpen) {
        let initial = []
        if (typeof props.modelValue === 'string' && props.modelValue.trim()) {
            try {
                const parsed = JSON.parse(props.modelValue)
                if (Array.isArray(parsed)) {
                    initial = parsed.map(f => ({
                        name: f.name || '',
                        type: f.type || 'string',
                        value: f.value !== undefined ? String(f.value) : '',
                        description: f.description || '',
                    }))
                }
            } catch (e) {
                // Not valid JSON, start empty
            }
        } else if (Array.isArray(props.modelValue) && props.modelValue.length > 0) {
            initial = props.modelValue.map(f => ({
                name: f.name || '',
                type: f.type || 'string',
                value: f.value !== undefined ? String(f.value) : '',
                description: f.description || '',
            }))
        }
        if (initial.length === 0) {
            initial = [{ name: '', type: 'string', value: '', description: '' }]
        }
        fields.value = initial
    }
})

const addField = () => {
    fields.value.push({ name: '', type: 'string', value: '', description: '' })
}

const removeField = (index) => {
    fields.value.splice(index, 1)
}

const save = () => {
    // Output as JSON Structure Helper array format: [{name, type, value, description}, ...]
    const output = fields.value.map(f => ({
        name: f.name,
        type: f.type,
        value: f.value,
        description: f.description,
    }))
    emit('update:modelValue', JSON.stringify(output))
    emit('close')
}
</script>

<template>
    <UdModal :show="show" @close="$emit('close')" :title="t('json_structure_title', 'JSON Structure Helper')" variant="4xl">
        <div class="space-y-4">
            <p v-if="t('json_structure_subtitle')" class="text-sm text-zinc-500 -mt-2">
                {{ t('json_structure_subtitle', 'Define the fields and values for this parameter.') }}
            </p>

            <div class="space-y-3 max-h-[30rem] overflow-y-auto pr-2">
                <div
                    v-for="(field, index) in fields"
                    :key="index"
                    class="flex items-start gap-3 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/50"
                >
                    <div class="flex-1">
                        <UdLabel>{{ t('field_name', 'Name') }}</UdLabel>
                        <UdInput v-model="fields[index].name" placeholder="e.g. user_id" />
                    </div>
                    <div class="w-28">
                        <UdLabel>{{ t('field_type', 'Type') }}</UdLabel>
                        <UdSelect v-model="fields[index].type">
                            <option value="string">string</option>
                            <option value="integer">integer</option>
                            <option value="boolean">boolean</option>
                            <option value="array">array</option>
                            <option value="object">object</option>
                        </UdSelect>
                    </div>
                    <div class="flex-1">
                        <UdLabel>{{ t('value', 'Value') }}</UdLabel>
                        <UdInput v-model="fields[index].value" placeholder="e.g. value" />
                    </div>
                    <div class="flex-[2]">
                        <UdLabel>{{ t('field_description', 'Description') }}</UdLabel>
                        <UdInput v-model="fields[index].description" :placeholder="t('field_description_placeholder', 'Optional description')" />
                    </div>
                    <div class="pt-6">
                        <UdButton type="button" variant="ghost" size="xs" @click="removeField(index)" class="text-red-500 hover:text-red-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.34 6m-4.77 0-.34-6M4.5 6.375h15m-15 0A2.25 2.25 0 0 1 6.75 4.125h10.5a2.25 2.25 0 0 1 2.25 2.25v.375m-15 0A2.25 2.25 0 0 0 4.5 9v11.25A2.25 2.25 0 0 0 6.75 22.5h10.5a2.25 2.25 0 0 0 2.25-2.25V9a2.25 2.25 0 0 0-2.25-2.25v-.375m-15 0V6.375m15 0V6.375m-15 0h15" />
                            </svg>
                        </UdButton>
                    </div>
                </div>

                <div v-if="fields.length === 0" class="text-center py-8 text-zinc-500">
                    {{ t('no_fields', 'No fields defined yet. Add one to build your structure.') }}
                </div>
            </div>

            <div class="flex justify-between pt-4">
                <UdButton type="button" variant="subtle" @click="addField">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ t('add_field', 'Add Field') }}
                </UdButton>
                <div class="flex gap-3">
                    <UdButton type="button" variant="ghost" @click="$emit('close')">
                        {{ t('cancel', 'Cancel') }}
                    </UdButton>
                    <UdButton type="button" variant="primary" @click="save">
                        {{ t('confirm_structure', 'Confirm Structure') }}
                    </UdButton>
                </div>
            </div>
        </div>
    </UdModal>
</template>
