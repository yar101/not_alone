<script setup>
import { useTranslations } from '@/composables/useTranslations';
const { __ } = useTranslations();

defineProps({
    gender: {
        type: String,
        default: 'female'
    }
});
</script>

<template>
    <span class="idol-badge" :class="{ 'is-male': gender === 'male' }">{{ __('common.idol') }}</span>
</template>

<style scoped>
.idol-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.85rem;
    border-radius: 3px;
    font-size: 0.92rem;
    letter-spacing: 0.04em;
    background: color-mix(in srgb, var(--color-base-1), transparent 92%);
    color: var(--color-base-1);
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 70%);
    white-space: nowrap;
    line-height: 1;
}

.idol-badge.is-male {
    background: color-mix(in srgb, var(--color-base-2), transparent 92%);
    color: var(--color-base-2);
    border-color: color-mix(in srgb, var(--color-base-2), transparent 70%);
}
</style>
