<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'neutral', // neutral | success | warning | danger | info | primary
    },
    size: {
        type: String,
        default: 'md', // sm | md | lg
    },
    dot: {
        type: Boolean,
        default: false,
    },
});

const variantClasses = computed(() => {
    switch (props.variant) {
        case 'success':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800/60';
        case 'warning':
            return 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400 dark:border-amber-800/60';
        case 'danger':
        case 'destructive':
            return 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-400 dark:border-rose-800/60';
        case 'info':
            return 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/60 dark:text-sky-400 dark:border-sky-800/60';
        case 'primary':
        case 'purple':
            return 'bg-admin-primary-light text-admin-accent border border-admin-primary';
        case 'neutral':
        default:
            return 'bg-zinc-100 text-zinc-700 border-zinc-200 dark:bg-zinc-800/80 dark:text-zinc-300 dark:border-zinc-700/80';
    }
});

const dotClasses = computed(() => {
    switch (props.variant) {
        case 'success':
            return 'bg-emerald-400';
        case 'warning':
            return 'bg-amber-400';
        case 'danger':
        case 'destructive':
            return 'bg-rose-400';
        case 'info':
            return 'bg-sky-400';
        case 'primary':
        case 'purple':
            return 'bg-admin-primary';
        case 'neutral':
        default:
            return 'bg-zinc-400';
    }
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'px-2 py-0.5 text-[11px] font-medium gap-1';
        case 'lg':
            return 'px-3 py-1 text-sm font-semibold gap-2';
        case 'md':
        default:
            return 'px-2.5 py-0.5 text-xs font-medium gap-1.5';
    }
});
</script>

<template>
    <span
        class="inline-flex items-center border rounded-full font-medium select-none whitespace-nowrap shrink-0"
        :class="[variantClasses, sizeClasses]"
    >
        <span
            v-if="dot"
            class="w-1.5 h-1.5 rounded-full shrink-0 animate-pulse"
            :class="dotClasses"
        />
        <slot />
    </span>
</template>
