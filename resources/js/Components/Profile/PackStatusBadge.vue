<script setup>
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

const props = defineProps({
    status: { type: String, required: true },
});

const LABELS = computed(() => ({
    pending_review: __('pack.status.pending'),
    approved:       __('pack.status.approved'),
    published:      __('pack.status.published'),
    has_remarks:    __('pack.status.remarks'),
    rejected:       __('pack.status.rejected'),
    hidden:         __('pack.status.hidden'),
}));

const STYLES = {
    pending_review: { color: 'var(--color-base-1)', bg: 'rgba(160,160,255,0.14)', border: 'rgba(160,160,255,0.28)', shine: 'rgba(200,200,255,0.65)' },
    approved:       { color: '#70e0a8', bg: 'rgba(80,210,140,0.14)',  border: 'rgba(80,210,140,0.28)',  shine: 'rgba(120,240,175,0.60)' },
    published:      { color: 'rgba(200,200,210,0.7)', bg: 'rgba(200,200,210,0.07)', border: 'rgba(200,200,210,0.15)', shine: 'rgba(220,220,230,0.45)' },
    has_remarks:    { color: '#ff8f8f', bg: 'rgba(255,100,100,0.14)', border: 'rgba(255,100,100,0.28)', shine: 'rgba(255,150,150,0.62)' },
    rejected:       { color: '#ff6666', bg: 'rgba(220,60,60,0.14)',   border: 'rgba(220,60,60,0.28)',   shine: 'rgba(255,120,120,0.58)' },
    hidden:         { color: 'rgba(180,180,200,0.65)', bg: 'rgba(180,180,200,0.08)', border: 'rgba(180,180,200,0.18)', shine: 'rgba(220,220,230,0.38)' },
};

const label  = computed(() => LABELS.value[props.status] ?? props.status);
const style  = computed(() => STYLES[props.status] ?? STYLES.pending_review);
</script>

<template>
    <span class="psb" :style="{
        '--psb-color':  style.color,
        '--psb-bg':     style.bg,
        '--psb-border': style.border,
        '--psb-shine':  style.shine,
    }">{{ label }}</span>
</template>

<style scoped>
.psb {
    display: inline-flex;
    align-self: flex-start;
    align-items: center;
    padding: 0.2rem 0.65rem;
    border-radius: 5px;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    white-space: nowrap;
    color: var(--psb-color);
    background: var(--psb-bg);
    border: 1px solid var(--psb-border);
    border-top: none;
    box-shadow:
        inset 0 1px 0 var(--psb-shine),
        inset 0 -1px 0 rgba(0, 0, 0, 0.12),
        0 1px 3px rgba(0, 0, 0, 0.2);
}
</style>
