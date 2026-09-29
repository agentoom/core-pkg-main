<script setup>
import { ref, computed } from 'vue'
import UdModal from './UdModal.vue'
import UdInput from './UdInput.vue'
import UdSelect from './UdSelect.vue'
import UdBadge from './UdBadge.vue'
import UdButton from './UdButton.vue'

const props = defineProps({
    show: Boolean,
    actionableMethods: Array,
    translations: Object,
})

const t = computed(() => props.translations || {
    select_title: 'Select Direct Action',
    select_subtitle: 'Choose a local or remote action to execute',
    search_placeholder: 'Search actions...',
    all_sources: 'All Sources',
    local: 'Local',
    mcp_server: 'MCP Server',
    external_mcp: 'External (MCP Servers)',
    local_actions: 'Local Actions',
    no_actions_found: 'No actions found matching your criteria.',
    cancel: 'Cancel',
})

const emit = defineEmits(['close', 'select'])

const search = ref('')
const sourceFilter = ref('all')

const filteredMethods = computed(() => {
    let methods = props.actionableMethods || []
    
    if (search.value) {
        const s = search.value.toLowerCase()
        methods = methods.filter(m => 
            m.name.toLowerCase().includes(s) || 
            (m.category && m.category.toLowerCase().includes(s))
        )
    }
    
    if (sourceFilter.value !== 'all') {
        methods = methods.filter(m => (m.source || 'local') === sourceFilter.value)
    }
    
    return methods
})

const groupedMethods = computed(() => {
    const groups = {}
    filteredMethods.value.forEach(m => {
        const source = m.source || 'local'
        if (!groups[source]) groups[source] = []
        groups[source].push(m)
    })
    return groups
})

const selectMethod = (method) => {
    emit('select', method.key)
}
</script>

<template>
    <UdModal :show="show" @close="$emit('close')" :title="t.select_title" variant="xl">
        <div class="space-y-4">
            <p v-if="t.select_subtitle" class="text-sm text-zinc-500 -mt-2 mb-2">{{ t.select_subtitle }}</p>
            <div class="flex gap-4">
                <div class="flex-1">
                    <UdInput v-model="search" :placeholder="t.search_placeholder" />
                </div>
                <div class="w-48">
                    <UdSelect v-model="sourceFilter">
                        <option value="all">{{ t.all_sources }}</option>
                        <option value="local">{{ t.local }}</option>
                        <option value="mcp">{{ t.mcp_server }}</option>
                    </UdSelect>
                </div>
            </div>

            <div class="max-h-[30rem] overflow-y-auto space-y-4 pr-2">
                <div v-for="(methods, source) in groupedMethods" :key="source" class="space-y-2">
                    <div class="text-xs font-bold uppercase text-zinc-400 px-2 flex items-center gap-2">
                        <template v-if="source === 'mcp'">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z" />
                            </svg>
                            {{ t.external_mcp }}
                        </template>
                        <template v-else>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21M8.25 8.25h7.5a.75.75 0 0 1 .75.75v7.5a.75.75 0 0 1-.75.75h-7.5a.75.75 0 0 1-.75-.75v-7.5a.75.75 0 0 1 .75-.75Z" />
                            </svg>
                            {{ t.local_actions }}
                        </template>
                    </div>

                    <div class="grid grid-cols-1 gap-2">
                        <button 
                            v-for="method in methods" 
                            :key="method.key"
                            type="button"
                            @click="selectMethod(method)"
                            class="flex flex-col text-left p-3 rounded-xl border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition-colors w-full group"
                        >
                            <div class="flex justify-between items-start mb-1">
                                <span class="font-semibold text-sm text-zinc-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400">{{ method.name }}</span>
                                <UdBadge size="sm" color="zinc">{{ method.category || 'General' }}</UdBadge>
                            </div>
                            <p v-if="method.description" class="text-xs text-zinc-600 dark:text-zinc-400 line-clamp-2 mt-1">{{ method.description }}</p>
                        </button>
                    </div>
                </div>

                <div v-if="filteredMethods.length === 0" class="text-center py-12 text-zinc-500">
                    {{ t.no_actions_found }}
                </div>
            </div>

            <div class="flex justify-end pt-4">
                <UdButton @click="$emit('close')" variant="ghost">{{ t.cancel }}</UdButton>
            </div>
        </div>
    </UdModal>
</template>
