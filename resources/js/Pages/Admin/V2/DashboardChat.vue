<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import UdBadge from '../../../Ui/UdBadge.vue'
import UdButton from '../../../Ui/UdButton.vue'
import UdModal from '../../../Ui/UdModal.vue'
import UdSelect from '../../../Ui/UdSelect.vue'
import UdSwitch from '../../../Ui/UdSwitch.vue'

const props = defineProps({
    availableAgents: Array,
    recentConversations: Array,
    conversationId: String,
    selectedAgentId: [String, Number, null],
    initialMessages: Array,
    translations: Object,
    hasCredits: Boolean,
    userCredits: Number,
    shareableUsers: { type: Array, default: () => [] },
    currentConversationShares: { type: Array, default: () => [] },
    search: { type: String, default: '' },
    aiDisclosureEnabled: { type: Boolean, default: false },
    aiDisclosureText: { type: String, default: '' },
    aiDisclosureVersion: { type: String, default: '' },
    privacyEnabled: { type: Boolean, default: false },
    isEmbedded: { type: Boolean, default: false },
})
const translations = computed(() => props.translations ?? {})

const chatIndexRoute = computed(() => props.isEmbedded ? 'admin.chat.embed' : 'admin.v2.chat.index')

const normalizeId = (value) => value === null || value === undefined || value === '' ? null : String(value)
const selectedAgentId = ref(normalizeId(props.selectedAgentId))
const selectedConversationId = ref(normalizeId(props.conversationId))
const messages = ref(props.initialMessages || [])
const pendingFiles = ref([])
const draft = ref('')
const isSending = ref(false)
const isStreaming = ref(false)
const anonymizeNextMessage = ref(true)

const currentAgent = computed(() => props.availableAgents.find((agent) => String(agent.id) === selectedAgentId.value))

watch(currentAgent, (newAgent) => {
    if (newAgent && !newAgent.anonymization_enabled) {
        anonymizeNextMessage.value = false
    } else if (newAgent && newAgent.anonymization_enabled) {
        anonymizeNextMessage.value = true
    }
}, { immediate: true })
const streamingMessageId = ref(null)

// Per-message anonymized/clear view toggle: { [messageId]: 'clear' | 'anonymized' }
const messageViewMode = ref({})

const toggleMessageViewMode = (messageId) => {
    messageViewMode.value[messageId] = messageViewMode.value[messageId] === 'anonymized' ? 'clear' : 'anonymized'
}

const getMessageViewMode = (messageId) => messageViewMode.value[messageId] ?? 'clear'
const pendingAssistantHint = computed(() => {
    if (isSending.value) {
        return translations.value.sending_message ?? 'Sending message...'
    }

    if (isStreaming.value) {
        return translations.value.agent_thinking ?? 'Agent is thinking...'
    }

    return null
})
const textareaRef = ref(null)
const fileInputRef = ref(null)
const messagesContainerRef = ref(null)
let activeEchoChannel = null
let chatGeneration = 0
const isRecording = ref(false)
const mediaRecorder = ref(null)
const audioChunks = ref([])

const searchQuery = ref(props.search)

const performSearch = () => {
    router.get(route(chatIndexRoute.value), { search: searchQuery.value || undefined }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

let searchDebounceTimer = null
const onSearchInput = () => {
    clearTimeout(searchDebounceTimer)
    searchDebounceTimer = setTimeout(performSearch, 300)
}

const showSharePanel = ref(false)
const showDisclosureModal = ref(false)
const conversationShares = ref(props.currentConversationShares ?? [])
const shareSearchQuery = ref('')
const isSharing = ref(false)

const filteredShareableUsers = computed(() => {
    const query = shareSearchQuery.value.toLowerCase()
    const sharedIds = conversationShares.value.map((s) => String(s.user_id))
    return props.shareableUsers.filter(
        (u) => !sharedIds.includes(String(u.id)) && (
            u.name.toLowerCase().includes(query) || u.email.toLowerCase().includes(query)
        )
    )
})

const currentConversationIsOwned = computed(() => {
    if (!selectedConversationId.value) {
        return false
    }
    const conv = props.recentConversations.find((c) => String(c.id) === selectedConversationId.value)
    return conv ? !conv.is_shared_with_me : false
})

const shareWithUser = async (userId) => {
    if (!selectedConversationId.value || isSharing.value) {
        return
    }
    isSharing.value = true
    try {
        const response = await axios.post(route('admin.v2.chat.share', selectedConversationId.value), { user_ids: [userId] })
        conversationShares.value = response.data.shares
    } catch (error) {
        console.error('Failed to share conversation', error)
    } finally {
        isSharing.value = false
    }
}

const unshareWithUser = async (userId) => {
    if (!selectedConversationId.value || isSharing.value) {
        return
    }
    isSharing.value = true
    try {
        const response = await axios.delete(route('admin.v2.chat.unshare', selectedConversationId.value), { data: { user_id: userId } })
        conversationShares.value = response.data.shares
    } catch (error) {
        console.error('Failed to unshare conversation', error)
    } finally {
        isSharing.value = false
    }
}

const startRecording = async () => {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
        audioChunks.value = []
        mediaRecorder.value = new MediaRecorder(stream)
        mediaRecorder.value.ondataavailable = (e) => {
            if (e.data.size > 0) {
                audioChunks.value.push(e.data)
            }
        }
        mediaRecorder.value.onstop = () => {
            const audioBlob = new Blob(audioChunks.value, { type: 'audio/webm' })
            const file = new File([audioBlob], `recording-${Date.now()}.webm`, { type: 'audio/webm' })
            pushFiles([file])
        }
        mediaRecorder.value.start()
        isRecording.value = true
    } catch (err) {
        console.error('Error accessing microphone', err)
        alert('Could not access microphone. Please check your browser permissions.')
    }
}

const stopRecording = () => {
    if (mediaRecorder.value && isRecording.value) {
        mediaRecorder.value.stop()
        mediaRecorder.value.stream.getTracks().forEach(track => track.stop())
        isRecording.value = false
    }
}

const canSend = computed(() => selectedAgentId.value && !isSending.value && !isStreaming.value && (draft.value.trim().length > 0 || pendingFiles.value.length > 0))

const PLACEHOLDER_PATTERN = /<REDACTED-([A-Z_]+)-([A-F0-9]{10})>/g

// Escape placeholders so markdown never swallows them as unknown HTML tags.
const escapePlaceholders = (text) => (text ?? '').replace(
    PLACEHOLDER_PATTERN,
    '&lt;REDACTED-$1-$2&gt;'
)

// Wrap placeholders in a highlight span so the anonymized view renders exactly
// the tokens the model received, visually marked.
const highlightPlaceholders = (text) => (text ?? '').replace(
    PLACEHOLDER_PATTERN,
    '<span class="pii-placeholder">&lt;REDACTED-$1-$2&gt;</span>'
)

const sanitizeHtml = (html) => DOMPurify.sanitize(html, {
    USE_PROFILES: { html: true, svg: true },
    ADD_TAGS: ['svg', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan', 'g', 'defs', 'linearGradient', 'radialGradient', 'stop', 'use', 'clipPath', 'span'],
    ADD_ATTR: ['d', 'fill', 'stroke', 'stroke-width', 'x', 'y', 'r', 'cx', 'cy', 'viewBox', 'width', 'height', 'rx', 'ry', 'points', 'transform', 'offset', 'stop-color', 'stop-opacity', 'id', 'href', 'xlink:href', 'class', 'title', 'data-pii'],
})

// Plain text (clear or anonymized) -> markdown -> sanitized HTML.
const renderText = (text, transform) => {
    try {
        return sanitizeHtml(marked.parse(transform(text)))
    } catch (e) {
        console.error('Markdown parsing failed', e)
        return escapePlaceholders(text)
    }
}

const renderPlainHtml = (html) => sanitizeHtml(html ?? '')

// Resolve what a message bubble should display for the active view mode.
const messageHtml = (message) => {
    if (getMessageViewMode(message.id) === 'anonymized' && message.anonymized_content) {
        return renderText(message.anonymized_content, highlightPlaceholders)
    }

    // Server-rendered HTML with anonymized values already highlighted.
    if (message.highlighted_content) {
        return renderPlainHtml(message.highlighted_content)
    }

    // Streaming chunks arrive as HTML when anonymization is active.
    if (message.content_html) {
        return renderPlainHtml(message.content)
    }

    return renderText(message.content, escapePlaceholders)
}

// Number of anonymized values carried by a message (0 when nothing was redacted).
const anonymizedCount = (message) => {
    if (message.highlighted_content) {
        return (message.highlighted_content.match(/data-pii="/g) || []).length
    }

    if (message.anonymized_content) {
        return (message.anonymized_content.match(PLACEHOLDER_PATTERN) || []).length
    }

    return 0
}

const messageAttachments = (message) => {
    const storedAttachments = Array.isArray(message.attachments) ? message.attachments : []
    const streamedImages = Array.isArray(message.images) ? message.images : []

    const normalizedStored = storedAttachments.map((attachment) => ({
        name: attachment.name,
        type: attachment.type === 'document' ? 'file' : attachment.type,
        src: attachment.path ? `/storage/${attachment.path}` : attachment.real_path,
    }))
    const normalizedImages = streamedImages.map((image, index) => ({
        name: image.name || `Image ${index + 1}`,
        type: 'image',
        src: image.url || image.path,
    }))

    return [...normalizedStored, ...normalizedImages]
}

const finalizeMessage = async (messageId) => {
    const normalizedId = normalizeId(messageId)
    const existingIndex = messages.value.findIndex(
        (m) => normalizeId(m.id) === normalizedId
    )
    if (existingIndex === -1) {
        return
    }

    // Mark the message as loading its final content
    messages.value[existingIndex]._loading = true

    try {
        const { data } = await axios.get(
            route('admin.v2.chat.fetch-message', normalizeId(messageId))
        )

        const updated = {
            ...messages.value[existingIndex],
            ...data,
            _loading: false,
            _fadeIn: true, // triggers CSS fade transition
        }
        messages.value.splice(existingIndex, 1, updated)

        isStreaming.value = false
        streamingMessageId.value = null
        nextTick(scrollToBottom)
    } catch {
        // API fetch failed — fall back to the streaming content
        messages.value[existingIndex]._loading = false
        isStreaming.value = false
        streamingMessageId.value = null
    }
}

const upsertMessage = (incomingMessage) => {
    const hasContent = incomingMessage.content && incomingMessage.content.trim().length > 0
    const hasAttachments = (incomingMessage.attachments && incomingMessage.attachments.length > 0) || (incomingMessage.images && incomingMessage.images.length > 0)

    if (!hasContent && !hasAttachments) {
        return
    }

    const normalizedIncomingId = normalizeId(incomingMessage.id)
    const existingIndex = messages.value.findIndex((message) => normalizeId(message.id) === normalizedIncomingId)
    if (existingIndex !== -1) {
        messages.value.splice(existingIndex, 1, incomingMessage)
    } else {
        messages.value.push(incomingMessage)
    }
}

const leaveEchoChannel = () => {
    if (activeEchoChannel && window.Echo) {
        window.Echo.leave(activeEchoChannel)
    }

    activeEchoChannel = null
}

const setupEchoListener = (conversationId) => {
    if (!window.Echo || !conversationId) {
        return
    }

    const channelName = `private-conversations.${conversationId}`
    if (activeEchoChannel === channelName) {
        return
    }

    leaveEchoChannel()

    activeEchoChannel = channelName
    window.Echo.private(`conversations.${conversationId}`)
        .listen('.MessageBroadcasted', (event) => {
            const incomingMessage = event.message
            if (!incomingMessage) {
                return
            }

            // is_final signal: fetch the complete message from the API instead of
            // relying on the WebSocket payload (avoids Pusher's 10KB limit entirely)
            if (incomingMessage.is_final) {
                finalizeMessage(incomingMessage.id)
                return
            }

            upsertMessage(incomingMessage)

            // Clear/maintain the thinking indicator based on any non-user message chunks/finals.
            // Some backends use role "assistant" instead of "ai" for final messages.
            const role = String(incomingMessage.role || '').toLowerCase()
            const isChunk = incomingMessage.is_chunk === true
            if (role !== 'user') {
                if (isChunk) {
                    streamingMessageId.value = normalizeId(incomingMessage.id)
                    isStreaming.value = true
                } else if (role === 'ai' || role === 'assistant') {
                    isStreaming.value = false
                    streamingMessageId.value = null
                }
            }

            nextTick(scrollToBottom)
        })
}

const scrollToBottom = () => {
    if (messagesContainerRef.value) {
        messagesContainerRef.value.scrollTop = messagesContainerRef.value.scrollHeight
    }
}

const autoResizeTextarea = () => {
    if (!textareaRef.value) {
        return
    }

    textareaRef.value.style.height = 'auto'
    textareaRef.value.style.height = `${Math.min(textareaRef.value.scrollHeight, 220)}px`
}

const pushFiles = (files) => {
    files.forEach((file) => {
        const duplicate = pendingFiles.value.find((pendingFile) => pendingFile.name === file.name && pendingFile.size === file.size)
        if (!duplicate) {
            pendingFiles.value.push(file)
        }
    })
}

const onFileInputChange = (event) => {
    const files = Array.from(event.target.files || [])
    pushFiles(files)
    event.target.value = ''
}

const onPasteFiles = (event) => {
    const files = Array.from(event.clipboardData?.files || [])
    if (files.length === 0) {
        return
    }

    event.preventDefault()
    pushFiles(files)
}

const onDropFiles = (event) => {
    event.preventDefault()
    pushFiles(Array.from(event.dataTransfer?.files || []))
}

const removePendingFile = (index) => {
    pendingFiles.value.splice(index, 1)
}

const handleWrapperClick = (event) => {
    // Focus textarea if clicking anywhere on the wrapper except buttons or input
    if (event.target.tagName !== 'BUTTON' && event.target.tagName !== 'A' && event.target.tagName !== 'INPUT' && event.target.tagName !== 'TEXTAREA') {
        textareaRef.value?.focus()
    }
}

const send = async () => {
    if (!canSend.value) {
        return
    }
    const requestGeneration = chatGeneration

    const outgoingText = draft.value.trim()
    const outgoingFiles = [...pendingFiles.value]
    const tempId = `temp-${Date.now()}`
    const optimisticMessage = {
        id: tempId,
        role: 'user',
        content: outgoingText,
        created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        attachments: outgoingFiles.map((file) => ({
            name: file.name,
            type: file.type.startsWith('image/') ? 'image' : (file.type.startsWith('audio/') ? 'audio' : 'file'),
            real_path: URL.createObjectURL(file),
        })),
    }

    messages.value.push(optimisticMessage)
    draft.value = ''
    pendingFiles.value = []
    isSending.value = true
    autoResizeTextarea()
    nextTick(scrollToBottom)

    try {
        const formData = new FormData()
        formData.append('prompt', outgoingText)
        formData.append('agentId', selectedAgentId.value)
        formData.append('anonymize', anonymizeNextMessage.value ? '1' : '0')
        if (selectedConversationId.value) {
            formData.append('conversationId', selectedConversationId.value)
        }

        outgoingFiles.forEach((file) => {
            formData.append('attachments[]', file, file.name)
        })

        const response = await axios.post(route('admin.v2.chat.send'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        const { conversationId, messageId, anonymizedContent, highlightedContent } = response.data

        if (requestGeneration !== chatGeneration) {
            await axios.post(route('admin.v2.chat.stop', conversationId)).catch(() => {})

            return
        }

        const optimisticIndex = messages.value.findIndex((message) => message.id === tempId)
        if (optimisticIndex !== -1) {
            // Replace the entire message object to guarantee Vue reactivity,
            // and migrate the ANONYMIZED/CLEAR toggle state from the temp ID
            // to the real message ID.
            const oldId = messages.value[optimisticIndex].id
            const updatedMessage = {
                ...messages.value[optimisticIndex],
                id: messageId,
                anonymized_content: anonymizedContent || undefined,
                highlighted_content: highlightedContent || undefined,
            }
            messages.value.splice(optimisticIndex, 1, updatedMessage)

            // Migrate toggle state when the ID changes
            if (oldId !== messageId && messageViewMode.value[oldId] !== undefined) {
                messageViewMode.value[messageId] = messageViewMode.value[oldId]
                delete messageViewMode.value[oldId]
            }
        }

        selectedConversationId.value = normalizeId(conversationId)
        setupEchoListener(selectedConversationId.value)
        isStreaming.value = true
        isSending.value = false

        await axios.post(route('admin.v2.chat.ask', conversationId), {
            agentId: selectedAgentId.value,
        })
        if (requestGeneration !== chatGeneration) {
            return
        }
    } catch (error) {
        if (requestGeneration !== chatGeneration) {
            return
        }
        messages.value = messages.value.filter((message) => message.id !== tempId)
        isStreaming.value = false

        const errorText = error.response?.data?.error
            ?? error.response?.data?.message
            ?? translations.value?.error_sending_message
            ?? 'An error occurred. Please try again.'

        if (error.response?.status === 403) {
            messages.value.push({
                id: `error-${Date.now()}`,
                role: 'assistant',
                content: errorText,
                is_error: true,
                created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            })
        } else {
            console.error('Failed to send message', error)
        }
    } finally {
        isSending.value = false
    }
}

const selectConversation = (conversationId) => {
    const normalizedConversationId = normalizeId(conversationId)
    selectedConversationId.value = normalizedConversationId
    router.get(route(chatIndexRoute.value), { conversationId: normalizedConversationId }, {
        preserveScroll: true,
        replace: true,
    })
}

const newChat = () => {
    chatGeneration += 1
    const previousConversationId = selectedConversationId.value
    const shouldStopGeneration = isSending.value || isStreaming.value

    leaveEchoChannel()
    selectedConversationId.value = null
    messages.value = []
    isSending.value = false
    isStreaming.value = false
    streamingMessageId.value = null

    if (previousConversationId && shouldStopGeneration) {
        axios.post(route('admin.v2.chat.stop', previousConversationId)).catch(() => {})
    }

    router.get(route(chatIndexRoute.value), {}, {
        preserveScroll: true,
        replace: true,
    })
}

const deleteConversation = (id) => {
    if (confirm(props.translations.delete_conversation_confirm)) {
        router.delete(route('admin.v2.chat.destroy', id))
    }
}

const stopGeneration = async () => {
    if (!selectedConversationId.value) {
        return
    }

    try {
        await axios.post(route('admin.v2.chat.stop', selectedConversationId.value))
    } catch (error) {
        console.error('Failed to stop generation', error)
    }
}

onMounted(() => {
    if (selectedConversationId.value) {
        setupEchoListener(selectedConversationId.value)
    }
    nextTick(scrollToBottom)
})

onBeforeUnmount(() => {
    if (activeEchoChannel) {
        window.Echo?.leave(activeEchoChannel)
    }
})

watch(() => props.initialMessages, (newMessages) => {
    messages.value = newMessages || []
    // Reset any stale streaming state when messages are re-hydrated
    // BUT only if we are not actively streaming or sending
    if (!isStreaming.value && !isSending.value) {
        isStreaming.value = false
        streamingMessageId.value = null
    }
    nextTick(scrollToBottom)
}, { immediate: true })

watch(() => props.conversationId, (newConversationId) => {
    selectedConversationId.value = normalizeId(newConversationId)
    showSharePanel.value = false
    if (selectedConversationId.value) {
        setupEchoListener(selectedConversationId.value)
    }
})

watch(() => props.currentConversationShares, (newShares) => {
    conversationShares.value = newShares ?? []
})

watch(() => props.selectedAgentId, (newAgentId) => {
    selectedAgentId.value = normalizeId(newAgentId)
}, { immediate: true })
</script>

<template>
    <Head title="Chat Dashboard" />

    <div
        class="flex flex-col md:flex-row overflow-hidden bg-zinc-50 dark:bg-zinc-950"
        :class="isEmbedded
            ? 'h-full'
            : 'h-[calc(100dvh-3.5rem)] -m-6 lg:-m-8'"
    >
        <!-- History Sidebar -->
        <div class="w-full md:w-80 flex-shrink-0 border-r border-zinc-200 dark:border-zinc-800 flex flex-col bg-white dark:bg-zinc-900 overflow-hidden">
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/50 space-y-2">
                <UdButton @click="newChat" variant="outline" class="w-full">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </template>
                    {{ translations.new_chat }}
                </UdButton>
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-zinc-400 pointer-events-none">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="translations.search_conversations"
                        class="w-full text-xs pl-8 pr-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-300 dark:focus:ring-zinc-600"
                        @input="onSearchInput"
                    />
                </div>
            </div>

            <div class="p-3 bg-zinc-50/50 dark:bg-zinc-800/30 border-b border-zinc-200 dark:border-zinc-800">
                 <div class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">
                    {{ translations.history }}
                 </div>
            </div>

            <div class="flex-1 overflow-y-auto">
                <div v-if="recentConversations.length > 0" class="p-2 space-y-1">
                    <div v-for="conv in recentConversations" :key="conv.id" class="group relative">
                        <button
                            @click="selectConversation(conv.id)"
                            class="w-full text-left p-3 rounded-lg transition-colors"
                            :class="[
                                selectedConversationId === String(conv.id)
                                    ? (conv.is_shared_with_me ? 'bg-indigo-50 dark:bg-indigo-900/30 ring-1 ring-indigo-200 dark:ring-indigo-700' : 'bg-zinc-100 dark:bg-zinc-800 ring-1 ring-zinc-200 dark:ring-zinc-700')
                                    : (conv.is_shared_with_me ? 'hover:bg-indigo-50 dark:hover:bg-indigo-900/20' : 'hover:bg-zinc-100 dark:hover:bg-zinc-800/80')
                            ]"
                        >
                            <div class="flex items-center gap-1.5 pr-6">
                                <svg v-if="conv.is_shared_with_me" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3 flex-shrink-0 text-indigo-500">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                                </svg>
                                <span class="text-sm font-medium truncate" :class="conv.is_shared_with_me ? 'text-indigo-900 dark:text-indigo-200' : 'text-zinc-900 dark:text-white'">
                                    {{ conv.title || translations.untitled_chat }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 mt-1">
                                <div class="text-[10px]" :class="conv.is_shared_with_me ? 'text-indigo-400' : 'text-zinc-500'">{{ conv.updated_at_diff }}</div>
                                <div v-if="conv.is_shared_with_me && conv.shared_by" class="text-[10px] text-indigo-400 truncate">
                                    {{ conv.shared_by.name }}
                                </div>
                                <div v-if="!conv.is_shared_with_me" class="text-[10px] bg-zinc-200 dark:bg-zinc-700 px-1.5 py-0.5 rounded text-zinc-600 dark:text-zinc-400 truncate max-w-[100px]">
                                    {{ conv.agent_name ?? translations.deleted_agent }}
                                </div>
                            </div>
                        </button>
                        <button
                            v-if="!conv.is_shared_with_me"
                            @click="deleteConversation(conv.id)"
                            class="absolute top-3 right-2 opacity-0 group-hover:opacity-100 p-1 text-zinc-400 hover:text-red-500 transition-all"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.34 6m-4.77 0-.34-6M4.5 6.375h15m-15 0A2.25 2.25 0 0 1 6.75 4.125h10.5a2.25 2.25 0 0 1 2.25 2.25v.375m-15 0A2.25 2.25 0 0 0 4.5 9v11.25A2.25 2.25 0 0 0 6.75 22.5h10.5a2.25 2.25 0 0 0 2.25-2.25V9a2.25 2.25 0 0 0-2.25-2.25v-.375m-15 0V6.375m15 0V6.375m-15 0h15" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center h-full p-4 text-center">
                    <div class="text-sm text-zinc-400">{{ translations.no_history }}</div>
                </div>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="flex-1 min-h-0 flex flex-col bg-zinc-50/30 dark:bg-zinc-950/20 overflow-hidden">
            <!-- Header with Agent Selector -->
            <div class="h-16 px-6 border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <UdSelect
                        v-model="selectedAgentId"
                        :placeholder="translations.select_agent"
                        class="!w-64 border-none shadow-none focus:ring-0"
                        :disabled="messages.length > 0"
                    >
                        <option v-for="agent in availableAgents" :key="agent.id" :value="agent.id">
                            {{ agent.name }}
                        </option>
                    </UdSelect>

                    <div v-if="currentAgent" class="hidden sm:flex items-center gap-2">
                         <div class="size-2 rounded-full" :class="currentAgent.is_active ? 'bg-green-500' : 'bg-red-500'"></div>
                         <span class="text-xs text-zinc-500">{{ currentAgent.is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <UdBadge v-if="!hasCredits" color="red" size="sm">{{ translations.credits_exhausted }}</UdBadge>
                    <button
                        v-if="selectedConversationId && currentConversationIsOwned"
                        @click="showSharePanel = !showSharePanel"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg border transition-colors"
                        :class="showSharePanel ? 'bg-indigo-50 border-indigo-200 text-indigo-700 dark:bg-indigo-900/30 dark:border-indigo-700 dark:text-indigo-300' : 'border-zinc-200 text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800'"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                        </svg>
                        {{ translations.share }}
                        <span v-if="conversationShares.length > 0" class="inline-flex items-center justify-center size-4 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-800 dark:text-indigo-200 text-[10px] font-semibold">
                            {{ conversationShares.length }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Share Panel -->
            <div v-if="showSharePanel && selectedConversationId && currentConversationIsOwned" class="border-b border-zinc-200 dark:border-zinc-800 bg-indigo-50/50 dark:bg-indigo-950/20 px-6 py-4 space-y-3">
                <div class="text-xs font-semibold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider">{{ translations.share_conversation }}</div>

                <!-- Currently shared with -->
                <div v-if="conversationShares.length > 0" class="space-y-1.5">
                    <div class="text-[11px] text-zinc-500 font-medium">{{ translations.shared_with }}</div>
                    <div class="flex flex-wrap gap-2">
                        <div
                            v-for="share in conversationShares"
                            :key="share.user_id"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-800 dark:text-indigo-200 text-xs"
                        >
                            <span>{{ share.name || share.email }}</span>
                            <button @click="unshareWithUser(share.user_id)" class="text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-100 transition-colors" :disabled="isSharing">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Add users -->
                <div class="space-y-1.5">
                    <div class="text-[11px] text-zinc-500 font-medium">{{ translations.add_people }}</div>
                    <input
                        v-model="shareSearchQuery"
                        type="text"
                        :placeholder="translations.search_users_placeholder"
                        class="w-full text-xs px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-indigo-300 dark:focus:ring-indigo-700"
                    />
                    <div v-if="filteredShareableUsers.length > 0" class="max-h-32 overflow-y-auto space-y-1 mt-1">
                        <button
                            v-for="user in filteredShareableUsers"
                            :key="user.id"
                            @click="shareWithUser(user.id)"
                            :disabled="isSharing"
                            class="w-full text-left px-3 py-1.5 rounded-lg text-xs hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors flex items-center justify-between group"
                        >
                            <div>
                                <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ user.name }}</div>
                                <div class="text-zinc-400">{{ user.email }}</div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5 text-indigo-400 opacity-0 group-hover:opacity-100 transition-opacity">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>
                    </div>
                    <div v-else-if="shareSearchQuery && filteredShareableUsers.length === 0" class="text-[11px] text-zinc-400 px-1">{{ translations.no_users_found }}</div>
                    <div v-else-if="!shareSearchQuery && filteredShareableUsers.length === 0 && conversationShares.length > 0" class="text-[11px] text-zinc-400 px-1">{{ translations.all_users_have_access }}</div>
                </div>
            </div>

            <div class="flex-1 min-h-0 flex flex-col">
                <div v-if="!selectedAgentId && !selectedConversationId" class="h-full flex items-center justify-center border-t border-zinc-200 dark:border-zinc-800 text-zinc-500 text-sm">
                    {{ translations.select_agent }}
                </div>
                <template v-else>
                    <div
                        v-if="privacyEnabled && currentAgent?.anonymization_enabled"
                        class="flex items-start gap-2 px-6 py-2 border-t border-amber-200/70 dark:border-amber-900/40 bg-amber-50/70 dark:bg-amber-950/20 text-[11px] leading-4 text-amber-800 dark:text-amber-200"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5 mt-px flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                        <span>
                            {{ translations.privacy_legend ?? 'Privacy active — values highlighted in amber were replaced with placeholders before reaching the AI. The placeholder the model saw is shown next to each value; use the toggle above a message to view it fully anonymized.' }}
                        </span>
                    </div>
                    <div
                        ref="messagesContainerRef"
                        class="flex-1 overflow-y-auto p-6 space-y-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950"
                        @dragover.prevent
                        @drop="onDropFiles"
                    >
                        <div v-if="messages.length === 0" class="h-full flex items-center justify-center text-zinc-400 text-sm">
                            {{ translations.type_message }}
                        </div>

                        <div
                            v-for="message in messages"
                            :key="message.id"
                            class="flex"
                            :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
                        >
                            <div class="max-w-[85%] space-y-2">
                                <!-- Anonymized/Clear view toggle -->
                                <button
                                    v-if="privacyEnabled && anonymizedCount(message) > 0"
                                    @click="toggleMessageViewMode(message.id)"
                                    class="inline-flex items-center gap-1 text-[10px] font-medium uppercase tracking-wider transition-colors"
                                    :class="message.role === 'user' ? 'ml-auto' : 'mr-auto'"
                                    :title="getMessageViewMode(message.id) === 'anonymized'
                                        ? 'Show the message as the user wrote it'
                                        : 'Show exactly what was sent to the AI'"
                                >
                                    <template v-if="getMessageViewMode(message.id) === 'anonymized'">
                                        <span class="text-rose-600 dark:text-rose-400">🔒 {{ translations.view_anonymized ?? 'Anonymized' }}</span>
                                        <span v-if="anonymizedCount(message)" class="px-1.5 py-0.5 rounded-full bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">{{ anonymizedCount(message) }}</span>
                                    </template>
                                    <template v-else>
                                        <span class="text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300">👁 {{ translations.view_clear ?? 'Clear' }}</span>
                                        <span v-if="anonymizedCount(message)" class="px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300" :title="`${anonymizedCount(message)} value(s) anonymized before reaching the AI`">{{ anonymizedCount(message) }} anonymized</span>
                                    </template>
                                </button>
                                <div
                                    class="rounded-2xl px-4 py-3 text-sm leading-6 message-content"
                                    :class="[
                                        message.role === 'user'
                                            ? 'bg-zinc-200 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100'
                                            : 'bg-white text-zinc-900 border border-zinc-200 dark:bg-zinc-900 dark:text-zinc-100 dark:border-zinc-800',
                                        getMessageViewMode(message.id) === 'anonymized' ? 'ring-1 ring-rose-400/50' : '',
                                        message._fadeIn ? 'fade-in-message' : '',
                                        message._loading ? 'message-loading' : '',
                                    ]"
                                >
                                    <div v-if="messageHtml(message)" v-html="messageHtml(message)"></div>
                                    <div v-for="attachment in messageAttachments(message)" :key="`${message.id}-${attachment.src}`" class="mt-2">
                                        <img v-if="attachment.type === 'image'" :src="attachment.src" :alt="attachment.name" class="max-h-56 rounded-lg border border-zinc-200 dark:border-zinc-700" />
                                        <audio v-else-if="attachment.type === 'audio'" :src="attachment.src" controls class="max-w-full h-8"></audio>
                                        <a v-else :href="attachment.src" target="_blank" class="text-xs underline text-blue-600 dark:text-blue-400">{{ attachment.name }}</a>
                                    </div>
                                </div>
                                <div class="text-[10px] text-zinc-500" :class="message.role === 'user' ? 'text-right' : 'text-left'">
                                    {{ message.created_at }}
                                </div>
                            </div>
                        </div>

                        <div v-if="pendingAssistantHint" class="flex justify-start">
                            <div class="max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-6 bg-white text-zinc-600 border border-zinc-200 dark:bg-zinc-900 dark:text-zinc-300 dark:border-zinc-800 inline-flex items-center gap-2">
                                <span class="inline-flex gap-1">
                                    <span class="size-1.5 rounded-full bg-zinc-400 animate-pulse"></span>
                                    <span class="size-1.5 rounded-full bg-zinc-400 animate-pulse [animation-delay:120ms]"></span>
                                    <span class="size-1.5 rounded-full bg-zinc-400 animate-pulse [animation-delay:240ms]"></span>
                                </span>
                                <span>{{ pendingAssistantHint }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 space-y-3">
                        <div v-if="pendingFiles.length > 0" class="flex flex-wrap gap-2">
                            <div v-for="(file, index) in pendingFiles" :key="`${file.name}-${file.size}`" class="inline-flex items-center gap-2 text-xs rounded-full px-3 py-1 bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                <span class="truncate max-w-[12rem]">{{ file.name }}</span>
                                <button type="button" @click="removePendingFile(index)">×</button>
                            </div>
                        </div>

                        <div
                            class="rounded-3xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-950 px-4 py-3"
                            @click="handleWrapperClick"
                        >
                            <textarea
                                ref="textareaRef"
                                v-model="draft"
                                rows="1"
                                class="w-full resize-none bg-transparent text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none"
                                :placeholder="translations.type_message"
                                @input="autoResizeTextarea"
                                @paste="onPasteFiles"
                                @keydown.enter.exact.prevent="send"
                            ></textarea>

                            <div class="mt-2 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <input ref="fileInputRef" type="file" multiple class="hidden" @change="onFileInputChange" />
                                    <button type="button" class="text-xs text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200" @click="fileInputRef?.click()">
                                        {{ translations.attach_file ?? 'Attach file' }}
                                    </button>

                                    <div class="h-3 w-px bg-zinc-200 dark:bg-zinc-800 mx-1"></div>

                                    <button
                                        type="button"
                                        class="p-1 rounded-full transition-colors flex items-center gap-1.5"
                                        :class="isRecording ? 'bg-red-100 text-red-600 animate-pulse dark:bg-red-900/30 dark:text-red-400' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'"
                                        @click="isRecording ? stopRecording() : startRecording()"
                                        :title="isRecording ? (translations.stop_recording ?? 'Stop recording') : (translations.record_voice ?? 'Record voice message')"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                                        </svg>
                                        <span v-if="isRecording" class="text-[10px] font-medium uppercase tracking-wider">{{ translations.recording ?? 'Recording' }}</span>
                                    </button>

                                    <div v-if="privacyEnabled" class="flex items-center gap-2 ml-2 pl-3 border-l border-zinc-200 dark:border-zinc-800">
                                        <UdSwitch v-model="anonymizeNextMessage" size="sm" :disabled="currentAgent && !currentAgent.anonymization_enabled" />
                                        <span class="text-[10px] text-zinc-500 font-medium uppercase tracking-wider" :class="{ 'opacity-50': currentAgent && !currentAgent.anonymization_enabled }">{{ translations.anonymize ?? 'Anonymize' }}</span>
                                        <span v-if="!anonymizeNextMessage" class="text-[10px] text-amber-600 dark:text-amber-400 font-medium whitespace-nowrap">⚠ {{ translations.anonymize_off_warning ?? 'Data will be sent to the AI provider' }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <UdButton v-if="isStreaming" @click="stopGeneration" variant="outline" size="xs" class="!px-3 !py-1 !text-[10px] text-red-600 border-red-200 hover:bg-red-50 dark:text-red-400 dark:border-red-900/50 dark:hover:bg-red-900/20">
                                        {{ translations.stop ?? 'Stop' }}
                                    </UdButton>

                                    <span v-if="isSending" class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ translations.sending_message ?? 'Sending...' }}</span>
                                    <button
                                        v-if="!isStreaming"
                                        type="button"
                                        class="inline-flex items-center justify-center size-8 rounded-full bg-black text-white disabled:opacity-50 dark:bg-white dark:text-black"
                                        :disabled="!canSend"
                                        @click="send"
                                    >
                                        ↑
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="px-6 pb-4 flex items-center justify-between text-[10px] text-zinc-400">
                <div>
                    <span v-if="currentAgent && translations.disclaimer">
                        {{ translations.disclaimer.replace(':name', currentAgent.name) }}
                    </span>
                </div>
                <UdButton v-if="aiDisclosureEnabled" variant="ghost" size="xs" @click="showDisclosureModal = true">
                    AI Disclosure
                </UdButton>
            </div>
            <UdModal v-model="showDisclosureModal" title="AI Disclosure">
                <div class="p-4 text-sm text-zinc-700 dark:text-zinc-300">
                    {{ aiDisclosureText }}
                    <div v-if="aiDisclosureVersion" class="mt-2 text-xs text-zinc-500">
                        Version: {{ aiDisclosureVersion }}
                    </div>
                </div>
            </UdModal>
        </div>
    </div>
</template>

<style scoped>
.message-content :deep(p) {
    margin-bottom: 0.5rem;
}
.message-content :deep(p:last-child) {
    margin-bottom: 0;
}
.message-content :deep(pre) {
    background-color: #f4f4f5;
    padding: 0.75rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    margin-bottom: 0.5rem;
}
.dark .message-content :deep(pre) {
    background-color: #27272a;
}
.message-content :deep(code) {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 0.875rem;
}
.message-content :deep(ul), .message-content :deep(ol) {
    margin-bottom: 0.5rem;
    padding-left: 1.25rem;
}
.message-content :deep(ul) {
    list-style-type: disc;
}
.message-content :deep(ol) {
    list-style-type: decimal;
}
.message-content :deep(svg) {
    max-width: 100%;
    height: auto;
    display: block;
    margin: 0.5rem 0;
}
.message-content :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 0.75rem;
    font-size: 0.8125rem;
    overflow-x: auto;
    display: block;
}
.message-content :deep(th),
.message-content :deep(td) {
    border: 1px solid #e4e4e7;
    padding: 0.4rem 0.75rem;
    text-align: left;
    white-space: nowrap;
}
.message-content :deep(th) {
    background-color: #f4f4f5;
    font-weight: 600;
}
.message-content :deep(tr:nth-child(even) td) {
    background-color: #fafafa;
}
.dark .message-content :deep(th),
.dark .message-content :deep(td) {
    border-color: #3f3f46;
}
.dark .message-content :deep(th) {
    background-color: #27272a;
}
.dark .message-content :deep(tr:nth-child(even) td) {
    background-color: #1c1c1f;
}

/* Anonymized PII: the restored value plus the exact placeholder sent to the AI */
.message-content :deep(.deanonymized-pii) {
    background-color: rgba(245, 158, 11, 0.18);
    border-bottom: 1px dotted rgb(217 119 6);
    border-radius: 3px;
    padding: 0 2px;
    cursor: help;
}
.dark .message-content :deep(.deanonymized-pii) {
    background-color: rgba(245, 158, 11, 0.22);
    border-bottom-color: rgb(251 191 36);
}
.message-content :deep(.deanonymized-pii)::after {
    content: attr(data-pii);
    margin-left: 4px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 9px;
    line-height: 1;
    padding: 1px 4px;
    border-radius: 9999px;
    background-color: rgba(120, 53, 15, 0.9);
    color: #fde68a;
    vertical-align: middle;
    white-space: nowrap;
}
.dark .message-content :deep(.deanonymized-pii)::after {
    background-color: rgba(253, 230, 138, 0.9);
    color: #78350f;
}
.message-content :deep(.pii-placeholder) {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 0.85em;
    padding: 0 3px;
    border-radius: 3px;
    background-color: rgba(244, 63, 94, 0.12);
    color: #be123c;
}
.dark .message-content :deep(.pii-placeholder) {
    background-color: rgba(244, 63, 94, 0.22);
    color: #fda4af;
}

/* Final-message fade-in after fetch */
.fade-in-message {
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Loading shimmer for the streaming message while final content is fetched */
.message-loading {
    position: relative;
    overflow: hidden;
}

.message-loading::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.4) 50%, transparent 100%);
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }

}
</style>
