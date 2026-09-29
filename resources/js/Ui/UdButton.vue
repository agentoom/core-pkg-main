<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    href: String,
    variant: {
        type: String,
        default: 'primary'
    },
    size: {
        type: String,
        default: 'md'
    },
    type: {
        type: String,
        default: 'button'
    },
    disabled: Boolean,
    inertia: {
        type: Boolean,
        default: true
    }
})

const variants = {
    primary: 'bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 hover:bg-zinc-800 dark:hover:bg-zinc-200',
    outline: 'border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800',
    ghost: 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800',
    subtle: 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 hover:bg-zinc-200 dark:hover:bg-zinc-700',
    danger: 'bg-red-600 text-white hover:bg-red-700'
}

const sizes = {
    xs: 'px-2 py-1 text-[10px]',
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
    lg: 'px-6 py-3 text-base'
}

const className = `inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none ${variants[props.variant]} ${sizes[props.size]}`
</script>

<template>
    <Link v-if="href && inertia" :href="href" :class="className">
        <slot />
    </Link>
    <a v-else-if="href" :href="href" :class="className">
        <slot />
    </a>
    <button v-else :type="type" :class="className" :disabled="disabled">
        <slot />
    </button>
</template>
