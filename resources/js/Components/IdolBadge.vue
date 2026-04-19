<script setup>
import { useTranslations } from '@/composables/useTranslations';
const { __ } = useTranslations();
</script>

<template>
    <span class="idol-badge">{{ __('common.idol') }}</span>
</template>

<style scoped>
.idol-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.85rem;
    border-radius: 3px;
    font-size: 0.92rem;
    letter-spacing: 0.04em;
    background: rgba(160, 160, 255, 0.08);
    color: var(--color-base-1, #a0a0ff);
    border: 1px solid rgba(160, 160, 255, 0.3);
    white-space: nowrap;
    line-height: 1;
}
</style>
