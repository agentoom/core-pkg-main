<script setup>
import { ref, computed, reactive, watch, onMounted } from 'vue'
import { useForm, router, usePage, Head } from '@inertiajs/vue3'
import ActionMethodSelector from '../../../Ui/ActionMethodSelector.vue'
import JsonStructureHelper from '../../../Ui/JsonStructureHelper.vue'
import UdCard from '../../../Ui/UdCard.vue'
import UdButton from '../../../Ui/UdButton.vue'
import UdInput from '../../../Ui/UdInput.vue'
import UdSwitch from '../../../Ui/UdSwitch.vue'
import UdCheckbox from '../../../Ui/UdCheckbox.vue'
import UdSelect from '../../../Ui/UdSelect.vue'
import UdBadge from '../../../Ui/UdBadge.vue'
import UdModal from '../../../Ui/UdModal.vue'
import UdRadioGroup from '../../../Ui/UdRadioGroup.vue'
import UdRadio from '../../../Ui/UdRadio.vue'
import UdField from '../../../Ui/UdField.vue'
import UdLabel from '../../../Ui/UdLabel.vue'
import UdDescription from '../../../Ui/UdDescription.vue'
import UdError from '../../../Ui/UdError.vue'
import UdSeparator from '../../../Ui/UdSeparator.vue'

const props = defineProps({
    pipeline: Object,
    projects: Array,
    agents: Array,
    actionableMethods: Array,
    steps: Array,
    variables: Array,
    failureStep: Object,
    initialDiagrams: Object,
    translations: Object,
    endpoint: Object,
})

const page = usePage()

const state = reactive({
    steps: props.steps || [],
    variables: props.variables || [],
    failureStep: props.failureStep || null,
    showMoveModal: false,
    isBulkMove: false,
    isBulkCloneMove: false,
    movingStepIndex: null,
    targetStepIndex: null,
    movePosition: 'before',
    expandedSteps: [],
    selectedSteps: [],
    failureStepEnabled: props.failureStep !== null,
    activeModals: [],
    showHelp: false,
    showMethodSelectorModal: false,
    methodSelectorStepIndex: null,
    showJsonHelperModal: false,
    jsonHelperTargetStep: null,
    jsonHelperTargetFieldIndex: null,
    jsonHelperParamValue: '',
})

const openModal = (name) => {
    if (!state.activeModals.includes(name)) {
        state.activeModals.push(name)
    }
}

const closeModal = (name) => {
    state.activeModals = state.activeModals.filter(n => n !== name)
}

const isModalOpen = (name) => {
    return state.activeModals.includes(name)
}

const form = useForm({
    name: props.pipeline?.name || '',
    type: props.pipeline?.type || 'standard',
    description: props.pipeline?.description || '',
    project_id: props.pipeline?.project_id || null,
    is_active: props.pipeline?.is_active ? 1 : 0,
    allow_memory_analysis: props.pipeline?.allow_memory_analysis ? 1 : 0,
    concurrency_lock: props.pipeline?.concurrency_lock ? 1 : 0,
})

const renderBatchSize = 5
const renderBatch = ref(0)

const visibleSteps = computed(() => {
    const end = (renderBatch.value + 1) * renderBatchSize
    return state.steps.slice(0, end)
})

const loadMoreSteps = () => {
    renderBatch.value++
}

const shouldShowLoadMore = computed(() => {
    return (renderBatch.value + 1) * renderBatchSize < state.steps.length
})

const agentsFiltered = computed(() => {
    if (!form.project_id) return props.agents
    return props.agents.filter(a => a.project_id === form.project_id)
})

const actionableMethodsGrouped = computed(() => {
    const groups = {}
    props.actionableMethods.forEach(m => {
        const cat = m.category || 'General'
        if (!groups[cat]) groups[cat] = []
        groups[cat].push(m)
    })
    return groups
})

const hasSteps = computed(() => state.steps.length > 0)

const uniqid = (prefix = '') => prefix + Math.random().toString(36).substr(2, 9)

const toggleStep = (uid) => {
    if (state.expandedSteps.includes(uid)) {
        state.expandedSteps = state.expandedSteps.filter(s => s !== uid)
    } else {
        state.expandedSteps.push(uid)
    }
}

const toggleStepSelection = (uid) => {
    if (state.selectedSteps.includes(uid)) {
        state.selectedSteps = state.selectedSteps.filter(s => s !== uid)
    } else {
        state.selectedSteps.push(uid)
    }
}

const addStep = () => {
    const uid = uniqid('step_')
    const type = state.steps.length === 0 && form.type === 'endpoint' ? 'receive_data' : 'agent'

    const newStep = {
        uid,
        id: null,
        type,
        agent_id: '',
        class: '',
        method: '',
        method_key: '',
        order: state.steps.length,
        name: '',
        instructions_override: '',
        pass_output_as_input: false,
        pass_all_previous_outputs: false,
        selected_outputs: [],
        condition_if: '',
        next_step_on_true: null,
        next_step_on_false: null,
        on_error: 'continue',
        next_step_on_error: null,
        retry_attempts: 0,
        retry_delay: 0,
        condition_on_previous: false,
        condition_on_input: false,
        condition_on_current: false,
        is_active: true,
    }

    state.steps.push(newStep)
    state.expandedSteps = [uid]
}

const removeStep = (index) => {
    const step = state.steps[index]
    const uid = step.uid

    state.steps.splice(index, 1)
    state.steps = state.steps.map((s, i) => ({ ...s, order: i }))

    state.selectedSteps = state.selectedSteps.filter(s => s !== uid)
    state.expandedSteps = state.expandedSteps.filter(s => s !== uid)

    if (state.steps.length > 0) {
        state.expandedSteps = [state.steps[state.steps.length - 1].uid]
    }
}

const moveUp = (index) => {
    if (index === 0) return
    const temp = state.steps[index - 1]
    state.steps[index - 1] = state.steps[index]
    state.steps[index] = temp
    state.steps = state.steps.map((s, i) => ({ ...s, order: i }))
}

const moveDown = (index) => {
    if (index >= state.steps.length - 1) return
    const temp = state.steps[index + 1]
    state.steps[index + 1] = state.steps[index]
    state.steps[index] = temp
    state.steps = state.steps.map((s, i) => ({ ...s, order: i }))
}

const addVariable = () => {
    state.variables.push({ key: '', value: '' })
}

const removeVariable = (index) => {
    state.variables.splice(index, 1)
}

const toggleFailureStep = () => {
    state.failureStepEnabled = !state.failureStepEnabled
    if (state.failureStepEnabled && !state.failureStep) {
        state.failureStep = {
            uid: uniqid('step_'),
            id: null,
            type: 'agent',
            agent_id: '',
            class: '',
            method: '',
            method_key: '',
            name: '',
            instructions_override: '',
            on_error: 'continue',
            retry_attempts: 0,
            retry_delay: 0,
            is_active: true,
        }
    }
}

const toggleOutputSelection = (step, stepIndex, checked) => {
    const uid = state.steps[stepIndex].uid
    if (checked) {
        if (!step.selected_outputs.includes(uid)) {
            step.selected_outputs.push(uid)
        }
    } else {
        step.selected_outputs = step.selected_outputs.filter(o => o !== uid)
    }
}

const getStepTypeName = (step) => {
    if (!step) return ''
    if (step.type === 'agent') return 'Agent'
    if (step.type === 'receive_data') return 'Receive Data'
    if (step.type === 'custom') return 'Direct Action'
    return step.type
}

const openMoveModal = (index) => {
    state.movingStepIndex = index
    state.targetStepIndex = null
    state.movePosition = 'before'
    state.isBulkMove = false
    state.isBulkCloneMove = false
    state.showMoveModal = true
}

const openMethodSelector = (index) => {
    state.showMethodSelectorModal = false
    state.methodSelectorStepIndex = index
    setTimeout(() => {
        state.showMethodSelectorModal = true
    }, 50)
}

const buildParameterFields = (key) => {
    const method = props.actionableMethods.find(m => m.key === key)
    if (!method || !method.parameters || Object.keys(method.parameters).length === 0) {
        return ''
    }
    const fields = Object.entries(method.parameters).map(([name, paramData]) => ({
        name,
        type: paramData.type || 'string',
        value: '',
        description: paramData.description || '',
    }))
    return JSON.stringify(fields)
}

const parseParameterFields = (instructions) => {
    if (!instructions) return []
    try {
        const data = JSON.parse(instructions)
        if (Array.isArray(data) && data.length > 0 && data[0].name !== undefined) {
            return data
        }
    } catch (e) {
        // not valid JSON
    }
    return []
}

const updateParameterField = (step, index, key, value) => {
    const fields = parseParameterFields(step.instructions_override)
    fields[index] = { ...fields[index], [key]: value }
    step.instructions_override = JSON.stringify(fields)
}

const removeParameterField = (step, index) => {
    const fields = parseParameterFields(step.instructions_override)
    fields.splice(index, 1)
    step.instructions_override = JSON.stringify(fields)
}

const addParameterField = (step) => {
    const fields = parseParameterFields(step.instructions_override)
    fields.push({ name: '', type: 'string', value: '', description: '' })
    step.instructions_override = JSON.stringify(fields)
}

const openJsonHelper = (step, fieldIndex) => {
    const fields = parseParameterFields(step.instructions_override)
    const field = fields[fieldIndex]
    state.jsonHelperTargetStep = step
    state.jsonHelperTargetFieldIndex = fieldIndex
    state.jsonHelperParamValue = (field && field.value) || ''
    state.showJsonHelperModal = true
}

const onJsonStructureUpdated = (value) => {
    const step = state.jsonHelperTargetStep
    const fi = state.jsonHelperTargetFieldIndex
    if (step && fi !== null) {
        updateParameterField(step, fi, 'value', value)
    }
    state.showJsonHelperModal = false
    state.jsonHelperTargetStep = null
    state.jsonHelperTargetFieldIndex = null
    state.jsonHelperParamValue = ''
}

const onMethodSelected = (key) => {
    const method = props.actionableMethods.find(m => m.key === key)
    const applyToStep = (step) => {
        step.method_key = key
        step.type = 'custom'
        step.class = method?.class || null
        step.method = method?.method || null
        step.mcp_actionable_id = method?.mcp_actionable_id || null
        step.instructions_override = buildParameterFields(key)
    }
    if (state.methodSelectorStepIndex === 'failure') {
        applyToStep(state.failureStep)
    } else if (state.methodSelectorStepIndex !== null) {
        applyToStep(state.steps[state.methodSelectorStepIndex])
    }
    state.showMethodSelectorModal = false
    state.methodSelectorStepIndex = null
}

const openBulkMoveModal = (clone = false) => {
    if (state.selectedSteps.length === 0) return
    state.isBulkMove = true
    state.isBulkCloneMove = clone
    state.movingStepIndex = null
    state.targetStepIndex = null
    state.movePosition = 'before'
    state.showMoveModal = true
}

const deleteSelectedSteps = () => {
    if (state.selectedSteps.length === 0) return
    state.steps = state.steps.filter(s => !state.selectedSteps.includes(s.uid))
    state.selectedSteps = []
    state.steps = state.steps.map((s, i) => ({ ...s, order: i }))
    if (state.steps.length > 0) {
        state.expandedSteps = [state.steps[state.steps.length - 1].uid]
    }
}

const executeMoveStep = () => {
    if (state.isBulkMove) {
        executeBulkMove()
        return
    }
    if (state.movingStepIndex === null || state.targetStepIndex === null) return

    const fromIndex = state.movingStepIndex
    const targetIndex = parseInt(state.targetStepIndex)
    const position = state.movePosition

    if (fromIndex === targetIndex && position === 'before') {
        state.showMoveModal = false
        return
    }

    const step = state.steps.splice(fromIndex, 1)[0]

    let adjustedTargetIndex = targetIndex
    if (fromIndex < targetIndex) {
        adjustedTargetIndex--
    }

    const newIndex = position === 'before' ? adjustedTargetIndex : adjustedTargetIndex + 1
    const clampedIndex = Math.max(0, Math.min(state.steps.length, newIndex))

    state.steps.splice(clampedIndex, 0, step)
    state.steps = state.steps.map((s, i) => ({ ...s, order: i }))
    state.expandedSteps = [step.uid]
    state.selectedSteps = []

    state.movingStepIndex = null
    state.targetStepIndex = null
    state.showMoveModal = false
}

const executeBulkMove = () => {
    if (state.selectedSteps.length === 0 || state.targetStepIndex === null) return

    const targetIndex = parseInt(state.targetStepIndex)
    const position = state.movePosition
    const uids = state.selectedSteps

    const stepsToMove = state.steps.filter(s => uids.includes(s.uid))

    if (!state.isBulkCloneMove) {
        state.steps = state.steps.filter(s => !uids.includes(s.uid))
    }

    const newSteps = stepsToMove.map(s => ({
        ...s,
        id: state.isBulkCloneMove ? null : s.id,
        uid: state.isBulkCloneMove ? uniqid('step_') : s.uid,
        name: state.isBulkCloneMove && s.name ? s.name + ' (Copy)' : s.name,
    }))

    const newIndex = position === 'before' ? targetIndex : targetIndex + 1
    const clampedIndex = Math.max(0, Math.min(state.steps.length, newIndex))

    newSteps.forEach((step, i) => {
        state.steps.splice(clampedIndex + i, 0, step)
    })

    state.steps = state.steps.map((s, i) => ({ ...s, order: i }))
    state.expandedSteps = newSteps.map(s => s.uid)
    state.selectedSteps = []
    state.isBulkMove = false
    state.isBulkCloneMove = false
    state.showMoveModal = false
}

const cloneStep = (index) => {
    const stepToClone = state.steps[index]
    const clonedStep = {
        ...stepToClone,
        id: null,
        uid: uniqid('step_'),
        name: stepToClone.name ? stepToClone.name + ' (Copy)' : '',
    }

    state.steps.splice(index + 1, 0, clonedStep)
    state.steps = state.steps.map((s, i) => ({ ...s, order: i }))
    state.expandedSteps = [clonedStep.uid]
    state.selectedSteps = []
}

const save = () => {
    const isUpdate = !!props.pipeline?.id
    const endpoint = isUpdate ? route('admin.pipelines.update', props.pipeline.id) : route('admin.pipelines.store')

    form.transform((data) => ({
        ...data,
        steps: state.steps,
        variables: state.variables,
        failureStep: state.failureStepEnabled ? state.failureStep : null,
        stay: false,
    })).submit(isUpdate ? 'put' : 'post', endpoint, {
        onError: () => {
        },
        onSuccess: () => {
        },
    })
}

const saveAndStay = () => {
    const isUpdate = !!props.pipeline?.id
    const endpoint = isUpdate ? route('admin.pipelines.update', props.pipeline.id) : route('admin.pipelines.store')

    form.transform((data) => ({
        ...data,
        steps: state.steps,
        variables: state.variables,
        failureStep: state.failureStepEnabled ? state.failureStep : null,
        stay: true,
    })).submit(isUpdate ? 'put' : 'post', endpoint, {
        preserveScroll: true,
        onSuccess: () => {
        },
    })
}

const clonePipeline = () => {
    if (!props.pipeline?.id) return
    router.post(route('admin.pipelines.clone', props.pipeline.id))
}

const allSelected = computed(() => {
    return state.steps.length > 0 && state.selectedSteps.length === state.steps.length
})

const toggleAllSteps = (value) => {
    if (value) {
        state.selectedSteps = state.steps.map(s => s.uid)
    } else {
        state.selectedSteps = []
    }
}

onMounted(() => {
    if (state.steps.length > 0) {
        state.expandedSteps = [state.steps[state.steps.length - 1].uid]
    }
})
</script>

<template>
    <Head :title="pipeline?.id ? translations.edit : translations.create" />

    <div class="space-y-6 pb-20 max-w-7xl mx-auto">
        <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">
                        {{ pipeline?.id ? translations.edit : translations.create }}
                    </h1>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ pipeline?.name || translations.new }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <UdButton :href="pipeline?.id ? route('admin.pipelines.legacy.edit', pipeline.id) : route('admin.pipelines.legacy.create')" variant="outline" size="sm" :inertia="false">
                        LEGACY PIPELINE EDITOR
                    </UdButton>
                    <UdButton :href="route('admin.pipelines.index')" variant="ghost" size="sm" :inertia="false">
                        {{ translations.back }}
                    </UdButton>
                </div>
            </div>

            <div v-if="page.props.flash?.success" class="p-4 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-sm border border-green-200 dark:border-green-800">
                {{ page.props.flash.success }}
            </div>

            <form @submit.prevent="save" class="space-y-6">
                <UdCard class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                        <UdField class="p-4 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border border-zinc-200 dark:border-zinc-800">
                            <UdLabel>{{ translations.is_active }}</UdLabel>
                            <UdRadioGroup v-model="form.is_active" variant="segmented">
                                <UdRadio :value="1" :label="translations.active" />
                                <UdRadio :value="0" :label="translations.inactive" />
                            </UdRadioGroup>
                        </UdField>

                        <UdField class="p-4 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border border-zinc-200 dark:border-zinc-800">
                            <UdLabel>{{ translations.allow_memory_analysis }}</UdLabel>
                            <UdRadioGroup v-model="form.allow_memory_analysis" variant="segmented">
                                <UdRadio :value="1" :label="translations.analyze" />
                                <UdRadio :value="0" :label="translations.ignore" />
                            </UdRadioGroup>
                        </UdField>

                        <UdField class="p-4 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border border-zinc-200 dark:border-zinc-800">
                            <UdLabel>{{ translations.concurrency_lock }}</UdLabel>
                            <UdRadioGroup v-model="form.concurrency_lock" variant="segmented">
                                <UdRadio :value="1" :label="translations.concurrency_lock_enabled" />
                                <UdRadio :value="0" :label="translations.concurrency_lock_disabled" />
                            </UdRadioGroup>
                            <UdDescription class="mt-1">{{ translations.concurrency_lock_help }}</UdDescription>
                        </UdField>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">{{ translations.details }}</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <UdField>
                                <UdLabel>{{ translations.name }} <span class="text-red-500">*</span></UdLabel>
                                <UdInput v-model="form.name" id="pipeline-name" />
                                <UdError :name="form.errors.name">{{ form.errors.name }}</UdError>
                            </UdField>

                            <UdField>
                                <UdLabel>{{ translations.project }}</UdLabel>
                                <UdSelect v-model="form.project_id">
                                    <option value="">{{ translations.no_project }}</option>
                                    <option v-for="p in projects" :key="p.id" :value="p.id">
                                        {{ p.name }}
                                    </option>
                                </UdSelect>
                                <UdError :name="form.errors.project_id">{{ form.errors.project_id }}</UdError>
                            </UdField>
                        </div>

                        <div v-if="!form.project_id" class="p-4 rounded-lg bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 text-sm border border-amber-200 dark:border-amber-800">
                            {{ translations.no_project_warning }}
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <UdField>
                                <UdLabel>{{ translations.type }}</UdLabel>
                                <UdRadioGroup v-model="form.type" variant="segmented">
                                    <UdRadio value="standard" :label="translations.type_standard" />
                                    <UdRadio value="endpoint" :label="translations.type_endpoint" />
                                </UdRadioGroup>
                                <UdDescription class="mt-1">
                                    {{ form.type === 'standard' ? translations.type_standard_help : translations.type_endpoint_help }}
                                </UdDescription>
                            </UdField>
                        </div>

                        <UdField>
                            <UdLabel>{{ translations.description }}</UdLabel>
                            <UdInput type="textarea" v-model="form.description" id="pipeline-description" rows="2" />
                            <UdError :name="form.errors.description">{{ form.errors.description }}</UdError>
                        </UdField>
                    </div>
                </UdCard>

                <UdCard class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-zinc-900 dark:text-white">{{ translations.variables }}</h2>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ translations.variables_subtitle }}</p>
                        </div>
                        <UdButton type="button" @click="addVariable" variant="outline" size="sm">
                            {{ translations.add_variable }}
                        </UdButton>
                    </div>

                    <div v-if="state.variables.length === 0" class="text-center py-8 text-zinc-500 border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl">
                        {{ translations.no_variables_hint }}
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="(variable, index) in state.variables" :key="index" class="flex gap-3 items-start">
                            <UdField class="flex-1">
                                <UdInput v-model="state.variables[index].key" :placeholder="translations.variable_key" />
                            </UdField>
                            <UdField class="flex-1">
                                <UdInput v-model="state.variables[index].value" :placeholder="translations.variable_value" />
                            </UdField>
                            <UdButton type="button" @click="removeVariable(index)" variant="ghost" size="sm" class="mt-1 text-red-500 hover:text-red-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.34 6m-4.77 0-.34-6M4.5 6.375h15m-15 0A2.25 2.25 0 0 1 6.75 4.125h10.5a2.25 2.25 0 0 1 2.25 2.25v.375m-15 0A2.25 2.25 0 0 0 4.5 9v11.25A2.25 2.25 0 0 0 6.75 22.5h10.5a2.25 2.25 0 0 0 2.25-2.25V9a2.25 2.25 0 0 0-2.25-2.25v-.375m-15 0V6.375m15 0V6.375m-15 0h15" />
                                </svg>
                            </UdButton>
                        </div>
                    </div>

                    <div class="mt-6">
                        <button
                            type="button"
                            @click="state.showHelp = !state.showHelp"
                            class="flex items-center gap-2 text-sm font-semibold text-zinc-600 dark:text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400 transition-colors focus:outline-none"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 transition-transform duration-200" :class="{ 'rotate-180': state.showHelp }">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                            {{ translations.help_instructions }}
                        </button>

                        <div v-if="state.showHelp" class="mt-4 space-y-6 animate-in fade-in slide-in-from-top-2 duration-200">
                            <div class="bg-zinc-50 dark:bg-zinc-900/50 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div class="space-y-2">
                                        <h4 class="font-bold text-sm text-zinc-900 dark:text-white flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-blue-500">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.835a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                            </svg>
                                            {{ translations.variables_help_title }}
                                        </h4>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed" v-html="translations.variables_help_text"></div>
                                    </div>
                                    <div class="space-y-2 md:border-l md:border-zinc-200 md:dark:border-zinc-800 md:pl-8">
                                        <h4 class="font-bold text-sm text-zinc-900 dark:text-white flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-blue-500">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                                            </svg>
                                            {{ translations.placeholders_help_title }}
                                        </h4>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed whitespace-pre-line" v-html="translations.placeholders_help_text"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </UdCard>

                <UdCard class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <h2 class="text-xl font-bold text-zinc-900 dark:text-white">{{ translations.steps }}</h2>
                            <div v-if="hasSteps" class="flex items-center gap-2">
                                <UdCheckbox :model-value="allSelected" @update:model-value="toggleAllSteps" />
                                <span class="text-xs text-zinc-500">{{ translations.select_all }}</span>
                            </div>
                            <div v-if="state.selectedSteps.length > 0" class="flex items-center gap-2 ml-4 pl-4 border-l border-zinc-200 dark:border-zinc-800">
                                <UdButton type="button" @click="deleteSelectedSteps" variant="ghost" size="sm" class="text-red-500">
                                    {{ translations.delete }} ({{ state.selectedSteps.length }})
                                </UdButton>
                                <UdButton type="button" @click="openBulkMoveModal(false)" variant="ghost" size="sm">
                                    {{ translations.move }}
                                </UdButton>
                                <UdButton type="button" @click="openBulkMoveModal(true)" variant="ghost" size="sm">
                                    {{ translations.clone_and_move }}
                                </UdButton>
                            </div>
                        </div>
                        <UdButton type="button" @click="addStep" variant="outline" size="sm">
                            {{ translations.add_step }}
                        </UdButton>
                    </div>

                    <div v-if="visibleSteps.length === 0" class="flex flex-col items-center justify-center py-16 text-zinc-500 border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl bg-zinc-50/30 dark:bg-zinc-900/20">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-8 mb-3 opacity-30">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 17.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        <span class="mb-4 text-sm">{{ translations.no_steps_hint }}</span>
                        <UdButton type="button" @click="addStep" variant="primary" size="sm">
                            {{ translations.add_step }}
                        </UdButton>
                    </div>

                    <div v-for="(step, i) in visibleSteps" :key="step.uid" class="space-y-4">
                        <div class="flex items-start gap-4">
                            <UdCheckbox :model-value="state.selectedSteps.includes(step.uid)" @update:model-value="toggleStepSelection(step.uid)" class="mt-4" />

                            <div class="flex-1 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm overflow-hidden">
                                <div class="p-4 flex items-center justify-between gap-4 bg-zinc-50 dark:bg-zinc-800/50">
                                    <div class="flex items-center gap-3 min-w-0 flex-1 cursor-pointer" @click="toggleStep(step.uid)">
                                        <UdBadge color="zinc" size="sm" class="font-mono">{{ i + 1 }}</UdBadge>
                                        <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate" :class="{ 'opacity-50': !step.is_active }">
                                            {{ step.name || `${translations.step} ${i + 1}` }}
                                            <span v-if="!step.is_active" class="text-xs font-normal text-zinc-500">({{ translations.inactive }})</span>
                                        </span>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-zinc-400 transition-transform duration-200" :class="{ 'rotate-180': state.expandedSteps.includes(step.uid) }">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <UdButton @click="moveUp(i)" size="xs" variant="ghost" :disabled="i === 0">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                            </svg>
                                        </UdButton>
                                        <UdButton @click="moveDown(i)" size="xs" variant="ghost" :disabled="i === visibleSteps.length - 1">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </UdButton>
                                        <UdButton @click="openMoveModal(i)" size="xs" variant="ghost">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                                            </svg>
                                        </UdButton>
                                        <UdButton @click="cloneStep(i)" size="xs" variant="ghost">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                                            </svg>
                                        </UdButton>
                                        <UdButton @click="removeStep(i)" size="xs" variant="ghost" class="text-red-500 hover:text-red-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.34 6m-4.77 0-.34-6M4.5 6.375h15m-15 0A2.25 2.25 0 0 1 6.75 4.125h10.5a2.25 2.25 0 0 1 2.25 2.25v.375m-15 0A2.25 2.25 0 0 0 4.5 9v11.25A2.25 2.25 0 0 0 6.75 22.5h10.5a2.25 2.25 0 0 0 2.25-2.25V9a2.25 2.25 0 0 0-2.25-2.25v-.375m-15 0V6.375m15 0V6.375m-15 0h15" />
                                            </svg>
                                        </UdButton>
                                    </div>
                                </div>

                                <div v-show="state.expandedSteps.includes(step.uid)" class="p-6 space-y-8 border-t border-zinc-200 dark:border-zinc-800">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div v-if="i === 0 && form.type === 'endpoint'">
                                            <UdLabel>{{ translations.target_type }}</UdLabel>
                                            <div class="flex items-center gap-2 py-2">
                                                <UdBadge color="zinc">{{ translations.receive_data }}</UdBadge>
                                                <span class="text-xs text-zinc-500">{{ translations.receive_data_hint }}</span>
                                            </div>
                                            <input type="hidden" :value="'receive_data'" />

                                            <!-- Endpoint expected parameters (read-only) -->
                                            <div v-if="props.endpoint && props.endpoint.data_structure && props.endpoint.data_structure.length > 0" class="mt-6 space-y-3">
                                                <div class="flex items-center gap-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-zinc-400">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
                                                    </svg>
                                                    <h4 class="text-sm font-bold text-zinc-900 dark:text-white">
                                                        {{ translations.endpoint_parameters || 'Endpoint Parameters' }}
                                                        <span class="text-xs font-normal text-zinc-500">({{ translations.read_only || 'read-only' }})</span>
                                                    </h4>
                                                </div>
                                                <p class="text-xs text-zinc-500">
                                                    {{ translations.endpoint_parameters_hint || 'These parameters are defined by the endpoint and will be passed as the pipeline input. They are not editable here.' }}
                                                </p>
                                                <div class="bg-zinc-50 dark:bg-zinc-900/50 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                                                    <table class="w-full text-sm">
                                                        <thead>
                                                            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800/50">
                                                                <th class="px-4 py-2 text-left text-xs font-semibold text-zinc-500 uppercase tracking-wider">{{ translations.param_name || 'Name' }}</th>
                                                                <th class="px-4 py-2 text-left text-xs font-semibold text-zinc-500 uppercase tracking-wider">Type</th>
                                                                <th class="px-4 py-2 text-left text-xs font-semibold text-zinc-500 uppercase tracking-wider">{{ translations.param_value || 'Value' }}</th>
                                                                <th class="px-4 py-2 text-left text-xs font-semibold text-zinc-500 uppercase tracking-wider">{{ translations.description_label || 'Description' }}</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="(param, pi) in props.endpoint.data_structure" :key="pi" class="border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                                                                <td class="px-4 py-2.5 font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ param.name }}</td>
                                                                <td class="px-4 py-2.5">
                                                                    <UdBadge size="xs" color="zinc">{{ param.type || 'string' }}</UdBadge>
                                                                </td>
                                                                <td class="px-4 py-2.5 font-mono text-xs text-zinc-500">{{ param.value || '—' }}</td>
                                                                <td class="px-4 py-2.5 text-xs text-zinc-500">{{ param.description || '—' }}</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <!-- No endpoint parameters defined -->
                                            <div v-else-if="props.endpoint && props.endpoint.name" class="mt-4 p-3 rounded-lg bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-700">
                                                <p class="text-xs text-zinc-500">
                                                    <span class="font-semibold text-zinc-600 dark:text-zinc-400">{{ translations.endpoint }}:</span> {{ props.endpoint.name }}
                                                </p>
                                                <p class="text-xs text-zinc-400 mt-1">{{ translations.no_endpoint_parameters || 'No parameters defined. All incoming data will be passed through.' }}</p>
                                            </div>
                                        </div>

                                        <div v-else>
                                            <UdLabel>{{ translations.target_type }}</UdLabel>
                                            <UdRadioGroup v-model="step.type" variant="segmented">
                                                <UdRadio value="agent" :label="translations.agent" />
                                                <UdRadio value="custom" :label="translations.custom_method" />
                                            </UdRadioGroup>
                                        </div>

                                        <UdField>
                                            <UdLabel>{{ translations.step_name }}</UdLabel>
                                            <UdInput v-model="step.name" :placeholder="translations.step_name_placeholder" />
                                        </UdField>

                                        <UdField>
                                            <UdLabel>{{ translations.is_active }}</UdLabel>
                                            <UdSwitch v-model="step.is_active" />
                                        </UdField>

                                        <UdField v-if="step.type === 'agent'">
                                            <UdLabel>{{ translations.agent }} <span class="text-red-500">*</span></UdLabel>
                                            <UdSelect v-model="step.agent_id">
                                                <option value="">{{ translations.select_agent }}</option>
                                                <option v-for="agent in agentsFiltered" :key="agent.id" :value="agent.id">
                                                    {{ agent.name }}
                                                </option>
                                            </UdSelect>
                                        </UdField>

                                        <UdField v-else-if="step.type === 'custom'">
                                            <UdLabel>{{ translations.method }} <span class="text-red-500">*</span></UdLabel>
                                            
                                            <div class="flex flex-col gap-2">
                                                <div v-if="step.method_key && actionableMethods.find(m => m.key === step.method_key)" class="flex items-center justify-between p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                                                    <div class="flex flex-col">
                                                        <span class="text-sm font-semibold">{{ actionableMethods.find(m => m.key === step.method_key).name }}</span>
                                                        <span v-if="actionableMethods.find(m => m.key === step.method_key).description" class="text-xs text-zinc-500">{{ actionableMethods.find(m => m.key === step.method_key).description }}</span>
                                                    </div>
                                                    <UdButton type="button" size="xs" variant="ghost" @click="openMethodSelector(i)">{{ translations.change }}</UdButton>
                                                </div>
                                                <UdButton v-else type="button" variant="outline" class="w-full" @click="openMethodSelector(i)">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                                    </svg>
                                                    {{ translations.select_action }}
                                                </UdButton>
                                            </div>
                                        </UdField>

                                        <UdField v-if="step.type !== 'receive_data' && i > 0">
                                            <UdLabel>{{ translations.pass_output }}</UdLabel>
                                            <div class="flex items-center gap-3">
                                                <UdButton @click="openModal('select-outputs-' + step.uid)" variant="outline" size="sm">
                                                    {{ translations.select_outputs }}
                                                    <UdBadge v-if="step.selected_outputs.length > 0" size="sm" color="blue" class="ml-2">
                                                        {{ step.selected_outputs.length }}
                                                    </UdBadge>
                                                </UdButton>
                                                <span v-if="step.selected_outputs.length > 0" class="text-xs text-zinc-500">
                                                    {{ translations.outputs_selected.replace(':count', step.selected_outputs.length) }}
                                                </span>
                                            </div>
                                            <UdDescription class="mt-2">{{ translations.pass_output_hint }}</UdDescription>
                                        </UdField>
                                    </div>

                                    <!-- Output selection modal -->
                                    <UdModal :show="isModalOpen('select-outputs-' + step.uid)" @close="closeModal('select-outputs-' + step.uid)" :title="translations.select_outputs">
                                        <div class="space-y-4">
                                            <p class="text-sm text-zinc-500">{{ translations.select_outputs_description }}</p>
                                            <div class="space-y-2 max-h-60 overflow-y-auto pr-2">
                                                <div v-for="j in i" :key="'output-' + step.uid + '-' + (j-1)">
                                                    <UdCheckbox
                                                        :model-value="step.selected_outputs.includes(state.steps[j - 1]?.uid)"
                                                        @update:model-value="toggleOutputSelection(step, j - 1, $event)"
                                                        :label="`Step ${j}: ${state.steps[j - 1]?.name || getStepTypeName(state.steps[j - 1])}`"
                                                    />
                                                </div>
                                            </div>
                                            <div class="flex justify-end pt-4">
                                                <UdButton @click="closeModal('select-outputs-' + step.uid)" variant="primary">{{ translations.close }}</UdButton>
                                            </div>
                                        </div>
                                    </UdModal>

                                    <UdField v-if="step.type === 'agent'">
                                        <UdLabel>{{ translations.instructions_override }}</UdLabel>
                                        <UdInput type="textarea" v-model="step.instructions_override" rows="3" :placeholder="translations.instructions_override_placeholder" />
                                    </UdField>

                                    <div v-if="step.type === 'custom'" class="space-y-2">
                                        <UdLabel>{{ translations.parameters }}</UdLabel>
                                        <div class="bg-zinc-50 dark:bg-zinc-950/50 p-4 rounded-lg border border-zinc-200 dark:border-zinc-800 space-y-3">
                                            <div
                                                v-for="(field, fi) in parseParameterFields(step.instructions_override)"
                                                :key="fi"
                                                class="grid grid-cols-12 gap-2 items-start"
                                            >
                                                <div class="col-span-3">
                                                    <UdInput :model-value="field.name" @update:model-value="updateParameterField(step, fi, 'name', $event)" :placeholder="translations.param_name || 'Name'" />
                                                </div>
                                                <div class="col-span-2">
                                                    <UdSelect :model-value="field.type" @update:model-value="updateParameterField(step, fi, 'type', $event)">
                                                        <option value="string">string</option>
                                                        <option value="integer">integer</option>
                                                        <option value="boolean">boolean</option>
                                                        <option value="array">array</option>
                                                        <option value="any">any</option>
                                                    </UdSelect>
                                                </div>
                                                <div class="col-span-5">
                                                    <div class="flex gap-1">
                                                        <UdInput :model-value="field.value" @update:model-value="updateParameterField(step, fi, 'value', $event)" :placeholder="translations.param_value || 'Value'" class="flex-1" />
                                                        <UdButton type="button" size="xs" variant="ghost" @click="openJsonHelper(step, fi)" :title="translations.json_structure_helper || 'JSON Structure Helper'">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                            </svg>
                                                        </UdButton>
                                                    </div>
                                                </div>
                                                <div class="col-span-2 flex justify-end">
                                                    <UdButton type="button" size="xs" variant="ghost" @click="removeParameterField(step, fi)">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-red-500">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                        </svg>
                                                    </UdButton>
                                                </div>
                                                <div v-if="field.description" class="col-span-12 -mt-1">
                                                    <p class="text-xs text-zinc-400 pl-1">{{ field.description }}</p>
                                                </div>
                                            </div>
                                            <p v-if="parseParameterFields(step.instructions_override).length === 0" class="text-sm text-zinc-400">{{ translations.no_parameters_defined }}</p>
                                            <UdButton type="button" size="xs" variant="outline" @click="addParameterField(step)">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                </svg>
                                                {{ translations.add_parameter || 'Add Parameter' }}
                                            </UdButton>
                                        </div>
                                    </div>

                                    <!-- Conditional Logic -->
                                    <div class="pt-8 border-t border-zinc-100 dark:border-zinc-800 space-y-4">
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-zinc-400">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l6.75-6.75 4.5 4.5 5.25-5.25M16.5 21L21 16.5m0 0L16.5 12m4.5 4.5H3" />
                                            </svg>
                                            <h4 class="text-sm font-bold text-zinc-900 dark:text-white">{{ translations.conditional_logic }}</h4>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                            <UdField>
                                                <UdLabel>{{ translations.condition_if }}</UdLabel>
                                                <UdInput v-model="step.condition_if" :placeholder="translations.condition_if_placeholder" />
                                                <UdDescription class="mt-1">{{ translations.condition_if_help }}</UdDescription>
                                            </UdField>

                                            <div class="flex flex-col gap-2 pt-1">
                                                <p class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">{{ translations.evaluate_on }}</p>
                                                <div class="flex flex-col gap-2">
                                                    <div><UdCheckbox v-model="step.condition_on_previous" :label="translations.previous_step" /></div>
                                                    <div><UdCheckbox v-model="step.condition_on_input" :label="translations.pipeline_input" /></div>
                                                    <div v-if="step.type !== 'receive_data'"><UdCheckbox v-model="step.condition_on_current" :label="translations.current_step" /></div>
                                                </div>
                                            </div>

                                            <UdField>
                                                <UdLabel>{{ translations.next_step_on_true }}</UdLabel>
                                                <UdSelect v-model="step.next_step_on_true">
                                                    <option value="">{{ translations.next_step_default }}</option>
                                                    <option v-for="(nextStep, nextStepIdx) in state.steps" :key="nextStep.uid" :value="nextStep.uid">
                                                        Step {{ nextStepIdx + 1 }}: {{ nextStep.name || getStepTypeName(nextStep) }}
                                                    </option>
                                                </UdSelect>
                                            </UdField>

                                            <UdField>
                                                <UdLabel>{{ translations.next_step_on_false }}</UdLabel>
                                                <UdSelect v-model="step.next_step_on_false">
                                                    <option value="">{{ translations.next_step_default }}</option>
                                                    <option v-for="(nextStep, nextStepIdx) in state.steps" :key="nextStep.uid" :value="nextStep.uid">
                                                        Step {{ nextStepIdx + 1 }}: {{ nextStep.name || getStepTypeName(nextStep) }}
                                                    </option>
                                                </UdSelect>
                                            </UdField>
                                        </div>
                                    </div>

                                    <!-- Error Handling -->
                                    <div class="pt-8 border-t border-zinc-100 dark:border-zinc-800 space-y-4">
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-zinc-400">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                            </svg>
                                            <h4 class="text-sm font-bold text-zinc-900 dark:text-white">Error Handling</h4>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <UdField>
                                                <UdLabel>On Error</UdLabel>
                                                <UdSelect v-model="step.on_error">
                                                    <option value="fail">{{ translations.fail_pipeline }}</option>
                                                    <option value="continue">{{ translations.continue }}</option>
                                                    <option value="retry">{{ translations.retry }}</option>
                                                    <option value="jump">{{ translations.jump }}</option>
                                                </UdSelect>
                                            </UdField>

                                            <div v-if="step.on_error === 'retry'" class="grid grid-cols-2 gap-4">
                                                <UdField>
                                                    <UdLabel>{{ translations.retry_attempts || 'Retry Attempts' }}</UdLabel>
                                                    <UdInput type="number" v-model="step.retry_attempts" min="1" max="10" />
                                                </UdField>
                                                <UdField>
                                                    <UdLabel>{{ translations.delay }}</UdLabel>
                                                    <UdInput type="number" v-model="step.retry_delay" min="0" />
                                                </UdField>
                                            </div>

                                            <UdField v-if="step.on_error === 'jump'">
                                                <UdLabel>{{ translations.next_step_on_error || 'Next Step on Error' }}</UdLabel>
                                                <UdSelect v-model="step.next_step_on_error">
                                                    <option value="">{{ translations.select_step }}</option>
                                                    <option v-for="(nextStep, nextStepIdx) in state.steps" :key="nextStep.uid" :value="nextStep.uid">
                                                        Step {{ nextStepIdx + 1 }}: {{ nextStep.name || getStepTypeName(nextStep) }}
                                                    </option>
                                                </UdSelect>
                                            </UdField>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-center pt-2">
                        <UdButton type="button" @click="addStep" variant="outline" size="sm">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            {{ translations.add_step }}
                        </UdButton>
                    </div>

                    <UdButton v-if="shouldShowLoadMore" @click="loadMoreSteps" variant="subtle" class="w-full">
                        Load more steps...
                    </UdButton>
                </UdCard>

                <!-- Failure Handler Card -->
                <UdCard class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-red-500">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-zinc-900 dark:text-white">{{ translations.failure_handler }}</h2>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">Logic to run when the pipeline fails.</p>
                            </div>
                        </div>
                        <UdSwitch :checked="state.failureStepEnabled" @update:checked="toggleFailureStep" />
                    </div>

                    <div v-if="state.failureStepEnabled && state.failureStep" class="p-6 border border-red-100 dark:border-red-900/50 rounded-xl bg-red-50/20 dark:bg-red-950/10 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <UdField>
                                <UdLabel>{{ translations.step_name }}</UdLabel>
                                <UdInput v-model="state.failureStep.name" placeholder="Failure Handler" />
                            </UdField>

                            <UdField>
                                <UdLabel>{{ translations.is_active }}</UdLabel>
                                <UdSwitch v-model="state.failureStep.is_active" />
                            </UdField>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <UdField>
                                <UdLabel>Target Type</UdLabel>
                                <UdRadioGroup v-model="state.failureStep.type" variant="segmented">
                                    <UdRadio value="agent" :label="translations.agent" />
                                    <UdRadio value="custom" :label="translations.custom_method" />
                                </UdRadioGroup>
                            </UdField>

                            <UdField v-if="state.failureStep.type === 'agent'">
                                <UdLabel>{{ translations.agent }} <span class="text-red-500">*</span></UdLabel>
                                <UdSelect v-model="state.failureStep.agent_id">
                                    <option value="">{{ translations.select_agent }}</option>
                                    <option v-for="agent in agentsFiltered" :key="agent.id" :value="agent.id">
                                        {{ agent.name }}
                                    </option>
                                </UdSelect>
                            </UdField>

                            <UdField v-else-if="state.failureStep.type === 'custom'">
                                <UdLabel>Action <span class="text-red-500">*</span></UdLabel>
                                
                                <div class="flex flex-col gap-2">
                                    <div v-if="state.failureStep.method_key && actionableMethods.find(m => m.key === state.failureStep.method_key)" class="flex items-center justify-between p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-semibold">{{ actionableMethods.find(m => m.key === state.failureStep.method_key).name }}</span>
                                            <span v-if="actionableMethods.find(m => m.key === state.failureStep.method_key).description" class="text-xs text-zinc-500">{{ actionableMethods.find(m => m.key === state.failureStep.method_key).description }}</span>
                                        </div>
                                        <UdButton type="button" size="xs" variant="ghost" @click="openMethodSelector('failure')">{{ translations.change }}</UdButton>
                                    </div>
                                    <UdButton v-else type="button" variant="outline" class="w-full" @click="openMethodSelector('failure')">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                        </svg>
                                        {{ translations.select_action }}
                                    </UdButton>
                                </div>
                            </UdField>
                        </div>

                        <UdField v-if="state.failureStep.type === 'agent'">
                            <UdLabel>{{ translations.instructions_override }}</UdLabel>
                            <UdInput type="textarea" v-model="state.failureStep.instructions_override" rows="3" placeholder="Failure instructions..." />
                        </UdField>

                        <div v-if="state.failureStep.type === 'custom'" class="space-y-2">
                            <UdLabel>{{ translations.parameters }}</UdLabel>
                            <div class="bg-zinc-50 dark:bg-zinc-950/50 p-4 rounded-lg border border-zinc-200 dark:border-zinc-800 space-y-3">
                                <div
                                    v-for="(field, fi) in parseParameterFields(state.failureStep.instructions_override)"
                                    :key="fi"
                                    class="grid grid-cols-12 gap-2 items-start"
                                >
                                    <div class="col-span-3">
                                        <UdInput :model-value="field.name" @update:model-value="updateParameterField(state.failureStep, fi, 'name', $event)" :placeholder="translations.param_name || 'Name'" />
                                    </div>
                                    <div class="col-span-2">
                                        <UdSelect :model-value="field.type" @update:model-value="updateParameterField(state.failureStep, fi, 'type', $event)">
                                            <option value="string">string</option>
                                            <option value="integer">integer</option>
                                            <option value="boolean">boolean</option>
                                            <option value="array">array</option>
                                            <option value="any">any</option>
                                        </UdSelect>
                                    </div>
                                    <div class="col-span-5">
                                        <UdInput :model-value="field.value" @update:model-value="updateParameterField(state.failureStep, fi, 'value', $event)" :placeholder="translations.param_value || 'Value'" />
                                    </div>
                                    <div class="col-span-2 flex justify-end">
                                        <UdButton type="button" size="xs" variant="ghost" @click="removeParameterField(state.failureStep, fi)">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-red-500">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                            </svg>
                                        </UdButton>
                                    </div>
                                    <div v-if="field.description" class="col-span-12 -mt-1">
                                        <p class="text-xs text-zinc-400 pl-1">{{ field.description }}</p>
                                    </div>
                                </div>
                                <p v-if="parseParameterFields(state.failureStep.instructions_override).length === 0" class="text-sm text-zinc-400">{{ translations.no_parameters_defined }}</p>
                                <UdButton type="button" size="xs" variant="outline" @click="addParameterField(state.failureStep)">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    {{ translations.add_parameter || 'Add Parameter' }}
                                </UdButton>
                            </div>
                        </div>
                    </div>

                    <div v-if="!state.failureStepEnabled" class="text-center py-10 text-zinc-500 border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl">
                        <p class="text-sm">{{ translations.failure_handler_disabled }}</p>
                    </div>
                </UdCard>

                <!-- Actions -->
                <div class="fixed bottom-0 left-0 right-0 lg:left-72 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md border-t border-zinc-200 dark:border-zinc-800 p-4 z-40">
                    <div class="max-w-6xl mx-auto flex justify-end gap-3">
                        <UdButton :href="route('admin.pipelines.index')" variant="ghost" :inertia="false">
                            {{ translations.cancel }}
                        </UdButton>
                        <UdButton v-if="pipeline?.id" type="button" variant="subtle" @click="clonePipeline">
                            {{ translations.clone }}
                        </UdButton>
                        <UdButton type="button" variant="subtle" @click="saveAndStay">
                            {{ translations.save_and_stay }}
                        </UdButton>
                        <UdButton type="submit" variant="primary" :disabled="form.processing">
                            <span v-if="!form.processing">{{ translations.save }}</span>
                            <span v-else class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Processing...
                            </span>
                        </UdButton>
                    </div>
                </div>

                <!-- Move Modal -->
                <UdModal :show="state.showMoveModal" @close="state.showMoveModal = false" variant="lg" :title="state.isBulkCloneMove ? translations.clone_and_move_step : translations.move_step">
                    <div class="space-y-6">
                        <p class="text-sm text-zinc-500">{{ translations.move_step_subtitle }}</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <UdField>
                                <UdLabel>{{ translations.move_target }}</UdLabel>
                                <UdSelect v-model="state.targetStepIndex">
                                    <option value="">{{ translations.move_target_placeholder }}</option>
                                    <option v-for="(s, idx) in state.steps" :key="idx" :value="idx" :disabled="state.isBulkMove && state.selectedSteps.includes(s.uid)">
                                        Step {{ idx + 1 }}: {{ s.name || translations.step + ' ' + (idx + 1) }}
                                    </option>
                                </UdSelect>
                            </UdField>

                            <UdField>
                                <UdLabel>{{ translations.move_position }}</UdLabel>
                                <UdRadioGroup v-model="state.movePosition" variant="segmented">
                                    <UdRadio value="before" :label="translations.move_before" />
                                    <UdRadio value="after" :label="translations.move_after" />
                                </UdRadioGroup>
                            </UdField>
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <UdButton variant="ghost" @click="state.showMoveModal = false">{{ translations.cancel }}</UdButton>
                            <UdButton variant="primary" @click="executeMoveStep">
                                {{ state.isBulkCloneMove ? translations.clone_and_move : translations.move }}
                            </UdButton>
                        </div>
                    </div>
                </UdModal>
        </form>
    </div>

    <ActionMethodSelector 
        :show="state.showMethodSelectorModal"
        :actionable-methods="props.actionableMethods"
        :translations="translations.direct_actions"
        @close="state.showMethodSelectorModal = false"
        @select="onMethodSelected"
    />

    <JsonStructureHelper
        :show="state.showJsonHelperModal"
        :model-value="state.jsonHelperParamValue"
        :translations="translations.json_structure || {}"
        @close="state.showJsonHelperModal = false"
        @update:model-value="onJsonStructureUpdated"
    />
</template>
