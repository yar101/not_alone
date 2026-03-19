<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import SiteModal from '@/Components/Site/SiteModal.vue';

const props = defineProps({
    user:      { type: Object, required: true },
    traits:    { default: null },
    interests: { default: null },
    languages: { default: null },
});

const open = ref(false);
const animKey = ref(0);

const ICONS = {
    about:     { path: 'M4 6h16M4 10h16M4 14h10', type: 'stroke' },
    traits:    { path: 'M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z', type: 'fill' },
    interests: { path: 'M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z', type: 'stroke' },
    voice:     { path: 'M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3zM19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8', type: 'stroke' },
    languages: { path: 'M12 2a10 10 0 100 20A10 10 0 0012 2zM2 12h20M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20', type: 'stroke' },
    timezone:  { path: 'M12 2a10 10 0 100 20A10 10 0 0012 2zM12 6v6l4 2', type: 'stroke' },
};

const COLORS = {
    about:     { base: '190,145,255', accent: '155,110,232' },
    traits:    { base: '252,211,77',  accent: '245,158,11'  },
    interests: { base: '249,168,212', accent: '236,72,153'  },
    voice:     { base: '110,231,183', accent: '16,185,129'  },
    languages: { base: '147,197,253', accent: '59,130,246'  },
    timezone:  { base: '253,186,116', accent: '249,115,22'  },
};

const HINTS = {
    about:     'Помогает другим узнать тебя',
    traits:    'Показывает твой характер',
    interests: 'Находи людей со схожими увлечениями',
    voice:     'Живое первое впечатление',
    languages: 'Найди собеседников на своём языке',
    timezone:  'Удобное планирование встреч',
};

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

// Trigger button: small ring, r=15
const CIRC_SM = 2 * Math.PI * 15;
const strokeDashSm = computed(() => ({
    strokeDasharray: CIRC_SM,
    strokeDashoffset: CIRC_SM * (1 - doneCount.value / items.value.length),
}));

// Modal header: large ring, r=28, viewBox 64×64
const CIRC_LG = 2 * Math.PI * 28;
const strokeDashLg = computed(() => ({
    strokeDasharray: CIRC_LG,
    strokeDashoffset: CIRC_LG * (1 - doneCount.value / items.value.length),
}));

// Re-trigger item entrance animation each time modal opens
watch(open, async (val) => {
    if (val) {
        await nextTick();
        animKey.value++;
    }
});
</script>

<template>
    <div v-if="!allDone" class="cl-widget">
        <!-- Trigger button -->
        <button
            class="cl-trigger"
            :title="'Заполнение профиля: ' + doneCount + '/' + items.length"
            @click="open = !open"
        >
            <svg class="cl-svg" viewBox="0 0 36 36">
                <circle class="cl-track" cx="18" cy="18" r="15" />
                <circle class="cl-fill" cx="18" cy="18" r="15" :style="strokeDashSm" />
            </svg>
            <span class="cl-label">{{ doneCount }}<small>/{{ items.length }}</small></span>
        </button>

        <SiteModal :show="open" variant="pink" :compact="true" @close="open = false">
            <div class="cl-modal-body">

                <!-- Hero header -->
                <div class="cl-hero">
                    <div class="cl-ring-wrap">
                        <svg class="cl-ring-svg" viewBox="0 0 64 64">
                            <circle class="cl-ring-track" cx="32" cy="32" r="28" />
                            <circle class="cl-ring-fill" cx="32" cy="32" r="28" :style="strokeDashLg" />
                        </svg>
                        <div class="cl-ring-center">
                            <span class="cl-ring-pct">{{ pct }}<small>%</small></span>
                        </div>
                    </div>
                    <div class="cl-hero-text">
                        <p class="cl-hero-title">Оформление профиля</p>
                        <p class="cl-hero-sub">{{ doneCount }} из {{ items.length }} пунктов заполнено</p>
                        <div class="cl-progress-bar">
                            <div class="cl-progress-fill" :style="{ width: pct + '%' }" />
                        </div>
                    </div>
                </div>

                <!-- Items list -->
                <ul class="cl-items" :key="animKey">
                    <li
                        v-for="(item, idx) in items"
                        :key="item.key"
                        class="cl-item"
                        :class="{ 'cl-item--done': item.done }"
                        :style="{ '--i': idx, '--base': COLORS[item.key].base, '--accent': COLORS[item.key].accent }"
                    >
                        <!-- Icon box -->
                        <div class="cl-item-icon-box">
                            <svg class="cl-item-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path :d="ICONS[item.key].path" :fill="ICONS[item.key].type === 'fill' ? 'currentColor' : 'none'" />
                            </svg>
                        </div>

                        <!-- Text -->
                        <div class="cl-item-text">
                            <span class="cl-item-label">{{ item.label }}</span>
                            <span class="cl-item-hint">{{ HINTS[item.key] }}</span>
                        </div>

                        <!-- Status badge -->
                        <div class="cl-item-status">
                            <svg v-if="item.done" class="cl-status-icon cl-status-icon--done" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            <svg v-else class="cl-status-icon cl-status-icon--todo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9" />
                            </svg>
                        </div>
                    </li>
                </ul>

            </div>
        </SiteModal>
    </div>
</template>

<style scoped>
/* ── Trigger button ───────────────────────────────────────── */
.cl-widget {
    position: absolute;
    bottom: 0;
    right: 0;
    z-index: 10;
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

.cl-svg {
    position: absolute; inset: 0;
    width: 100%; height: 100%;
    transform: rotate(-90deg);
}
.cl-track { fill: none; stroke: rgba(255,255,255,0.07); stroke-width: 3; }
.cl-fill  { fill: none; stroke: rgba(155,110,232,0.85); stroke-width: 3; stroke-linecap: round; transition: stroke-dashoffset 0.5s ease; }

.cl-label {
    font-size: 0.72rem; font-weight: 700;
    color: rgba(255,255,255,0.9);
    position: relative; z-index: 1; line-height: 1;
}
.cl-label small { font-size: 0.58rem; opacity: 0.55; }

/* ── Modal body ───────────────────────────────────────────── */
.cl-modal-body {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    font-family: 'Figtree', sans-serif;
}

/* ── Hero ─────────────────────────────────────────────────── */
.cl-hero {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 1.25rem 1.5rem;
    background: rgba(155,110,232,0.05);
    border: 1px solid rgba(155,110,232,0.14);
    border-radius: 3px;
}

.cl-ring-wrap {
    flex-shrink: 0;
    position: relative;
    width: 72px; height: 72px;
}

.cl-ring-svg {
    width: 100%; height: 100%;
    transform: rotate(-90deg);
}
.cl-ring-track { fill: none; stroke: rgba(255,255,255,0.06); stroke-width: 4; }
.cl-ring-fill  {
    fill: none;
    stroke: url(#cl-grad);
    stroke-width: 4;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.7s cubic-bezier(0.25,0.46,0.45,0.94);
    /* gradient via filter glow */
    stroke: rgba(155,110,232,0.9);
    filter: drop-shadow(0 0 6px rgba(155,110,232,0.55));
}

.cl-ring-center {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
}
.cl-ring-pct {
    font-size: 1.4rem; font-weight: 700;
    color: rgba(190,145,255,0.95);
    letter-spacing: -0.03em;
    line-height: 1;
    text-shadow: 0 0 18px rgba(155,110,232,0.5);
}
.cl-ring-pct small {
    font-size: 0.75em;
    font-weight: 600;
    color: rgba(155,110,232,0.7);
    vertical-align: baseline;
    margin-left: 1px;
}

.cl-hero-text { flex: 1; min-width: 0; }
.cl-hero-title {
    font-size: 1rem; font-weight: 700;
    color: rgba(255,255,255,0.9);
    margin: 0 0 0.25rem;
    letter-spacing: -0.01em;
}
.cl-hero-sub {
    font-size: 0.78rem;
    color: rgba(255,255,255,0.35);
    margin: 0 0 0.75rem;
}

.cl-progress-bar {
    height: 5px;
    background: rgba(255,255,255,0.07);
    border-radius: 99px;
    overflow: hidden;
}
.cl-progress-fill {
    height: 100%;
    border-radius: 99px;
    background: linear-gradient(90deg, #7070d8, #7c3aed);
    box-shadow: 0 0 10px rgba(155,110,232,0.5);
    transition: width 0.7s cubic-bezier(0.25,0.46,0.45,0.94);
}

/* ── Items list ───────────────────────────────────────────── */
.cl-items {
    list-style: none; padding: 0; margin: 0;
    display: flex; flex-direction: column; gap: 0.5rem;
}

.cl-item {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.7rem 0.875rem;
    border-radius: 3px;
    border: 1px solid rgba(var(--base), 0.1);
    background: rgba(var(--base), 0.04);
    transition: background 0.2s, border-color 0.2s;
    /* stagger entrance */
    animation: cl-item-in 0.38s cubic-bezier(0.34,1.56,0.64,1) both;
    animation-delay: calc(var(--i) * 65ms + 120ms);
}
.cl-item:hover {
    background: rgba(var(--base), 0.08);
    border-color: rgba(var(--base), 0.2);
}
.cl-item--done {
    background: rgba(var(--base), 0.03);
    border-color: rgba(var(--base), 0.07);
}

@keyframes cl-item-in {
    from { opacity: 0; transform: translateY(10px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0)    scale(1); }
}

/* Icon box */
.cl-item-icon-box {
    flex-shrink: 0;
    width: 34px; height: 34px;
    border-radius: 3px;
    background: rgba(var(--base), 0.1);
    border: 1px solid rgba(var(--base), 0.18);
    display: flex; align-items: center; justify-content: center;
    color: rgb(var(--base));
    transition: background 0.2s;
}
.cl-item--done .cl-item-icon-box {
    background: rgba(var(--base), 0.06);
    border-color: rgba(var(--base), 0.1);
    color: rgba(var(--base), 0.4);
}
.cl-item-svg { width: 15px; height: 15px; }

/* Text */
.cl-item-text { flex: 1; min-width: 0; }
.cl-item-label {
    display: block;
    font-size: 0.85rem; font-weight: 500;
    color: rgba(255,255,255,0.75);
    line-height: 1.3;
    transition: color 0.2s;
}
.cl-item--done .cl-item-label {
    color: rgba(255,255,255,0.28);
    text-decoration: line-through;
    text-decoration-color: rgba(var(--base), 0.25);
}
.cl-item-hint {
    display: block;
    font-size: 0.7rem;
    color: rgba(255,255,255,0.22);
    margin-top: 0.1rem;
}

/* Status icon */
.cl-item-status { flex-shrink: 0; }
.cl-status-icon { width: 18px; height: 18px; }
.cl-status-icon--done {
    color: rgb(var(--base));
    filter: drop-shadow(0 0 4px rgba(var(--base), 0.45));
}
.cl-status-icon--todo {
    color: rgba(255,255,255,0.1);
}
</style>
