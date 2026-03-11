<script setup>
import { ref, computed } from 'vue';
import { Check, Minus } from '@element-plus/icons-vue';

const props = defineProps({
    user:      { type: Object, required: true },
    traits:    { default: null },
    interests: { default: null },
    languages: { default: null },
});

const open = ref(false);

const items = computed(() => [
    { key: 'about',     label: 'Заполни «Обо мне»',     done: !!props.user.about },
    { key: 'traits',    label: 'Добавь черты характера', done: (props.traits?.length ?? 0) > 0 },
    { key: 'interests', label: 'Добавь интересы',        done: (props.interests?.length ?? 0) > 0 },
    { key: 'voice',     label: 'Запиши аудио',           done: !!props.user.voice_url },
    { key: 'languages', label: 'Укажи языки',            done: (props.languages?.length ?? 0) > 0 },
    { key: 'timezone',  label: 'Укажи часовой пояс',     done: !!props.user.timezone },
]);

const doneCount = computed(() => items.value.filter(i => i.done).length);
const allDone   = computed(() => doneCount.value === items.value.length);
const pct       = computed(() => Math.round(doneCount.value / items.value.length * 100));

// SVG circle: r=15, circumference ≈ 94.25
const CIRC = 2 * Math.PI * 15;
const strokeDash = computed(() => ({
    strokeDasharray: CIRC,
    strokeDashoffset: CIRC * (1 - doneCount.value / items.value.length),
}));
</script>

<template>
    <div v-if="!allDone" class="cl-widget">
        <button
            class="cl-trigger"
            :class="{ done: allDone }"
            :title="'Заполнение профиля: ' + doneCount + '/' + items.length"
            @click="open = !open"
        >
            <svg class="cl-svg" viewBox="0 0 36 36">
                <circle class="cl-track" cx="18" cy="18" r="15" />
                <circle class="cl-fill" cx="18" cy="18" r="15" :style="strokeDash" />
            </svg>
            <span class="cl-label">{{ doneCount }}<small>/{{ items.length }}</small></span>
        </button>

        <Transition name="cl-panel-fade">
            <div v-if="open" class="cl-panel">
                <div class="cl-panel-head">
                    <span class="cl-panel-title">Заполни профиль</span>
                    <button class="cl-close" @click="open = false">✕</button>
                </div>
                <div class="cl-progress-bar">
                    <div class="cl-progress-fill" :style="{ width: pct + '%' }" />
                </div>
                <ul class="cl-items">
                    <li
                        v-for="item in items"
                        :key="item.key"
                        class="cl-item"
                        :class="{ done: item.done }"
                    >
                        <el-icon class="cl-item-icon">
                            <component :is="item.done ? Check : Minus" />
                        </el-icon>
                        {{ item.label }}
                    </li>
                </ul>

            </div>
        </Transition>
    </div>
</template>

<style scoped>
.cl-widget {
    position: absolute;
    bottom: 0;
    right: 0;
    z-index: 10;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.cl-trigger {
    width: 52px; height: 52px;
    border-radius: 50%;
    border: none;
    background: rgba(14,10,24,0.92);
    backdrop-filter: blur(14px);
    cursor: pointer;
    position: relative;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.55), 0 0 0 1px rgba(155,110,232,0.2);
    transition: box-shadow 0.2s;
}
.cl-trigger:hover {
    box-shadow: 0 4px 24px rgba(0,0,0,0.6), 0 0 16px rgba(155,110,232,0.3), 0 0 0 1px rgba(155,110,232,0.35);
}
.cl-trigger.done {
    box-shadow: 0 4px 20px rgba(0,0,0,0.5), 0 0 18px rgba(155,110,232,0.4), 0 0 0 1px rgba(155,110,232,0.45);
}

.cl-svg {
    position: absolute; inset: 0;
    width: 100%; height: 100%;
    transform: rotate(-90deg);
}
.cl-track {
    fill: none;
    stroke: rgba(255,255,255,0.07);
    stroke-width: 3;
}
.cl-fill {
    fill: none;
    stroke: rgba(155,110,232,0.85);
    stroke-width: 3;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.5s ease;
}

.cl-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: rgba(255,255,255,0.9);
    position: relative;
    z-index: 1;
    line-height: 1;
}
.cl-label small {
    font-size: 0.58rem;
    opacity: 0.55;
}

.cl-panel {
    width: 230px;
    padding: 1rem 1rem 0.85rem;
    background: rgba(14,10,24,0.94);
    backdrop-filter: blur(18px);
    border: 1px solid rgba(155,110,232,0.2);
    border-radius: 3px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.65), 0 0 0 1px rgba(155,110,232,0.06);
}

.cl-panel-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.65rem;
}
.cl-panel-title {
    font-size: 0.82rem;
    font-weight: 600;
    color: rgba(255,255,255,0.85);
}
.cl-close {
    background: none; border: none;
    color: rgba(255,255,255,0.3);
    cursor: pointer; font-size: 0.8rem; padding: 0;
}
.cl-close:hover { color: rgba(255,255,255,0.7); }

.cl-progress-bar {
    height: 4px;
    background: rgba(255,255,255,0.07);
    border-radius: 3px;
    margin-bottom: 0.85rem;
    overflow: hidden;
}
.cl-progress-fill {
    height: 100%;
    border-radius: 3px;
    background: linear-gradient(90deg, rgba(155,110,232,0.9), rgba(140,60,200,0.75));
    box-shadow: 0 0 8px rgba(155,110,232,0.4);
    transition: width 0.5s cubic-bezier(0.25,0.46,0.45,0.94);
}

.cl-items {
    list-style: none; padding: 0; margin: 0 0 0.75rem;
    display: flex; flex-direction: column; gap: 0.45rem;
}
.cl-item {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.82rem; color: rgba(255,255,255,0.5);
}
.cl-item-icon {
    font-size: 0.78rem;
    color: rgba(255,255,255,0.2);
    flex-shrink: 0;
}
.cl-item.done .cl-item-icon {
    color: rgba(155,110,232,0.8);
    filter: drop-shadow(0 0 4px rgba(155,110,232,0.5));
}
.cl-item.done {
    color: rgba(255,255,255,0.28);
    text-decoration: line-through;
    text-decoration-color: rgba(155,110,232,0.3);
}

.cl-complete {
    font-size: 0.82rem;
    color: rgba(155,110,232,0.75);
    margin: 0;
    display: flex; align-items: center; gap: 0.3rem;
}

/* Panel animation */
.cl-panel-fade-enter-active {
    transition: opacity 0.2s, transform 0.25s cubic-bezier(0.34,1.56,0.64,1);
}
.cl-panel-fade-enter-from {
    opacity: 0;
    transform: scale(0.88) translateY(-6px);
    transform-origin: top right;
}
.cl-panel-fade-leave-active {
    transition: opacity 0.15s, transform 0.15s ease-in;
}
.cl-panel-fade-leave-to {
    opacity: 0;
    transform: scale(0.92) translateY(-4px);
    transform-origin: top right;
}
</style>
