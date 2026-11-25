<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    badge: {
        type: String,
        default: '',
    },
    badgeType: {
        type: String,
        default: 'primary', // primary | success | warning | danger
    },
    accent: {
        type: String,
        default: 'primary',
    },
    href: {
        type: String,
        default: null,
    },
});

const isLink = computed(() => !!props.href);

const badgeClasses = computed(() => {
    switch (props.badgeType) {
        case 'success':
            return 'bg-emerald-950/60 text-emerald-400 border-emerald-800/60';
        case 'warning':
            return 'bg-amber-950/60 text-amber-400 border-amber-800/60';
        case 'danger':
            return 'bg-rose-950/60 text-rose-400 border-rose-800/60';
        case 'primary':
        default:
            return 'bg-admin-primary-light text-admin-accent border-admin-primary';
    }
});

const borderAccent = computed(() => {
    switch (props.accent) {
        case 'success':
            return 'border-l-emerald-500';
        case 'warning':
            return 'border-l-amber-500';
        case 'danger':
            return 'border-l-rose-500';
        case 'primary':
        default:
            return 'border-l-admin-primary';
    }
});
</script>

<template>
    <component
        :is="isLink ? Link : 'div'"
        :href="href"
        class="flex flex-col justify-between p-5 bg-admin-card border border-admin-subtle rounded-xl transition-all duration-200 select-none border-l-4"
        :class="[
            borderAccent,
            isLink ? 'hover:bg-admin-surface hover:border-admin-theme hover:shadow-lg hover:shadow-black/20 cursor-pointer transform hover:-translate-y-0.5' : ''
        ]"
    >
        <div class="flex items-center justify-between gap-2 mb-3">
            <span class="text-xs font-medium uppercase tracking-wider text-admin-muted">
                {{ title }}
            </span>
            <span
                v-if="badge"
                class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border border-transparent"
                :class="badgeClasses"
            >
                {{ badge }}
            </span>
            <slot name="extra" />
        </div>

        <div class="flex items-baseline justify-between gap-2">
            <div class="text-2xl font-bold tracking-tight text-admin-main">
                {{ typeof value === 'number' ? value.toLocaleString('ru') : value }}
            </div>
            <div class="text-admin-muted">
                <slot name="icon" />
            </div>
        </div>

        <div v-if="subtitle || $slots.footer" class="mt-3 pt-3 border-t border-admin-subtle text-xs text-admin-muted flex items-center justify-between">
            <span v-if="subtitle">{{ subtitle }}</span>
            <slot name="footer" />
        </div>
    </component>
</template>
