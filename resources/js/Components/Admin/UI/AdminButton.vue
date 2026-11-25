<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary', // primary | secondary | destructive | outline | ghost | success
    },
    size: {
        type: String,
        default: 'md', // sm | md | lg | icon
    },
    type: {
        type: String,
        default: 'button',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    as: {
        type: [String, Object],
        default: 'button',
    },
    href: {
        type: String,
        default: null,
    },
});

const isLink = computed(() => !!props.href || props.as === Link);

const variantClasses = computed(() => {
    switch (props.variant) {
        case 'primary':
            return 'bg-admin-primary text-white shadow-sm hover:opacity-90 active:opacity-80 transition-all';
        case 'secondary':
            return 'bg-white hover:bg-zinc-50 text-zinc-700 border border-zinc-300 shadow-sm focus-visible:ring-zinc-400 active:bg-zinc-100 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-zinc-200 dark:border-zinc-700/80 dark:active:bg-zinc-800';
        case 'destructive':
            return 'bg-rose-600 hover:bg-rose-500 text-white shadow-sm shadow-rose-950/20 focus-visible:ring-rose-500 active:bg-rose-700';
        case 'outline':
            return 'bg-transparent hover:bg-zinc-100 text-zinc-700 border border-zinc-300 focus-visible:ring-zinc-400 active:bg-zinc-200 dark:hover:bg-zinc-800/80 dark:text-zinc-300 dark:border-zinc-700 dark:active:bg-zinc-800';
        case 'ghost':
            return 'bg-transparent hover:bg-zinc-100 text-zinc-600 hover:text-zinc-900 focus-visible:ring-zinc-400 dark:hover:bg-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-100';
        case 'success':
            return 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm shadow-emerald-950/20 focus-visible:ring-emerald-500 active:bg-emerald-700';
        default:
            return 'bg-white hover:bg-zinc-50 text-zinc-700 border border-zinc-300 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-zinc-200 dark:border-zinc-700';
    }
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'px-2.5 py-1.5 text-xs font-medium gap-1.5 rounded-lg';
        case 'md':
            return 'px-3.5 py-2 text-sm font-medium gap-2 rounded-lg';
        case 'lg':
            return 'px-5 py-2.5 text-base font-medium gap-2.5 rounded-xl';
        case 'icon':
            return 'p-2 text-sm rounded-lg aspect-square flex items-center justify-center';
        default:
            return 'px-3.5 py-2 text-sm font-medium gap-2 rounded-lg';
    }
});
</script>

<template>
    <component
        :is="isLink ? Link : as"
        :href="href"
        :type="!isLink ? type : undefined"
        :disabled="disabled || loading"
        class="inline-flex items-center justify-center font-medium transition-all duration-150 select-none focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-zinc-950 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none"
        :class="[variantClasses, sizeClasses]"
    >
        <svg
            v-if="loading"
            class="animate-spin -ml-0.5 h-4 w-4 text-current"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
        </svg>

        <slot name="icon" v-if="!loading" />
        <slot />
        <slot name="suffix" />
    </component>
</template>
