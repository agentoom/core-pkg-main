<script setup>
defineProps({
    name: String
})
</script>

<template>
    <p v-if="name" class="text-xs text-red-600 dark:text-red-400">
        <slot />
    </p>
</template>
