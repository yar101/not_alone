<script setup>
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

const props = defineProps({
    user:      { type: Object, required: true },
    traits:    { default: null },
    interests: { default: null },
    languages: { default: null },
});

const items = computed(() => [
    { key: 'about',     label: __('checklist.about'),     done: !!props.user.about },
    { key: 'traits',    label: __('checklist.traits'),    done: (props.traits?.length ?? 0) > 0 },
    { key: 'interests', label: __('checklist.interests'), done: (props.interests?.length ?? 0) > 0 },
    { key: 'voice',     label: __('checklist.audio'),     done: !!props.user.voice_url },
    { key: 'languages', label: __('checklist.languages'), done: (props.languages?.length ?? 0) > 0 },
    { key: 'timezone',  label: __('checklist.timezone'),  done: !!props.user.timezone },
]);

const done      = computed(() => items.value.filter(i => i.done).length);
const remaining = computed(() => items.value.length - done.value);
const pct       = computed(() => Math.round(done.value / items.value.length * 100));

const sortedItems = computed(() => [
    ...items.value.filter(i => !i.done),
    ...items.value.filter(i =>  i.done),
]);
</script>

<template>
    <div v-if="remaining > 0" class="pcl glass-panel">
        <!-- Header -->
        <div class="pcl__header">
            <span class="pcl__title">{{ __('checklist.title') }}</span>
            <span class="pcl__counter">{{ done }}<span class="pcl__counter-total">/{{ items.length }}</span></span>
        </div>

        <!-- Progress bar -->
        <div class="pcl__bar">
            <div class="pcl__bar-fill" :style="{ width: pct + '%' }" />
        </div>

        <!-- Items -->
        <TransitionGroup tag="ul" name="pcl-move" class="pcl__list">
            <li
                v-for="(item, idx) in sortedItems"
                :key="item.key"
                class="pcl__item"
                :class="{ 'pcl__item--done': item.done }"
                :style="{ '--idx': idx }"
            >
                <span class="pcl__check-wrap" :class="{ 'pcl__check-wrap--done': item.done }">
                    <svg class="pcl__check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                        <polyline v-if="item.done" points="20 6 9 17 4 12" stroke-width="2.5" />
                        <circle v-else cx="12" cy="12" r="8" stroke-width="1.75" />
                    </svg>
                </span>
                <span class="pcl__label">{{ item.label }}</span>
            </li>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.pcl {
    margin-top: 0.65rem;
    padding: 0.875rem 1rem 0.75rem;
    font-family: 'Rubik', sans-serif;
    position: relative;
    overflow: hidden;
}

/* Subtle top accent line */
.pcl::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--color-base-1), transparent 50%), transparent);
}

/* ── Header ── */
.pcl__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.55rem;
}

.pcl__title {
    font-size: 0.65rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: color-mix(in srgb, var(--color-base-1), transparent 50%);
    font-weight: 600;
}

.pcl__counter {
    font-size: 0.78rem;
    font-weight: 700;
    color: color-mix(in srgb, var(--color-base-1), transparent 10%);
    letter-spacing: 0.02em;
    font-variant-numeric: tabular-nums;
    text-shadow: 0 0 12px color-mix(in srgb, var(--color-base-1), transparent 60%);
}
.pcl__counter-total {
    font-weight: 400;
    color: color-mix(in srgb, var(--color-base-1), transparent 60%);
}

/* ── Progress bar ── */
.pcl__bar {
    height: 2px;
    background: rgba(255, 255, 255, 0.06);
    border-radius: 99px;
    margin-bottom: 0.75rem;
    overflow: hidden;
}

.pcl__bar-fill {
    height: 100%;
    border-radius: 99px;
    background: linear-gradient(90deg, color-mix(in srgb, var(--color-base-1), black 20%), var(--color-base-1));
    box-shadow: 0 0 8px color-mix(in srgb, var(--color-base-1), transparent 40%);
    transition: width 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* ── List ── */
.pcl__list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

/* ── Item ── */
.pcl__item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.3rem 0.4rem;
    border-radius: 2px;
    transition: background 0.15s;
    animation: pcl-item-in 0.3s ease both;
    animation-delay: calc(var(--idx) * 40ms);
}

@keyframes pcl-item-in {
    from { opacity: 0; transform: translateX(-6px); }
    to   { opacity: 1; transform: translateX(0); }
}

.pcl-move-move {
    transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* ── Check icon wrap ── */
.pcl__check-wrap {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
    border: 1.5px solid rgba(255, 255, 255, 0.12);
}

.pcl__check-wrap--done {
    background: rgba(16, 185, 129, 0.12);
    border-color: rgba(16, 185, 129, 0.35);
}

.pcl__check {
    width: 13px;
    height: 13px;
}

.pcl__item--done .pcl__check {
    color: rgba(16, 185, 129, 0.85);
    filter: drop-shadow(0 0 4px rgba(16, 185, 129, 0.3));
}

.pcl__item:not(.pcl__item--done) .pcl__check {
    color: rgba(255, 255, 255, 0.18);
}

/* ── Label ── */
.pcl__label {
    font-size: 0.95rem;
    line-height: 1.3;
    transition: color 0.2s;
}

.pcl__item:not(.pcl__item--done) .pcl__label {
    color: rgba(255, 255, 255, 0.65);
}

.pcl__item--done .pcl__label {
    color: rgba(255, 255, 255, 0.4);
}
</style>
