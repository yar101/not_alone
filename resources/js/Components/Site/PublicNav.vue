<script setup>
import { ref, onMounted, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    activePage: { type: String, default: 'home' },
});

const tabs = [
    {
        key: 'home', label: 'Главная', href: '/',
        icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg>`,
    },
    {
        key: 'about', label: 'О проекте', href: null,
        icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L13.8 9.2L21 12L13.8 14.8L12 22L10.2 14.8L3 12L10.2 9.2L12 2Z"/></svg>`,
    },
    {
        key: 'news', label: 'Новости', href: null,
        icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8M15 18h-5M10 6h8v4h-8z"/></svg>`,
    },
];

const visualActive = ref(props.activePage);
const tabEls = ref([]);
const indicatorEl = ref(null);
let navigating = false;

function getHref(tab) {
    if (tab.key === 'home')  return '/';
    if (tab.key === 'about') return route('about');
    if (tab.key === 'news')  return route('news');
}

function moveIndicator(key, animate) {
    const idx = tabs.findIndex(t => t.key === key);
    const el = tabEls.value[idx];
    const ind = indicatorEl.value;
    if (!el || !ind) return;

    const parentRect = el.parentElement.getBoundingClientRect();
    const elRect     = el.getBoundingClientRect();
    const border     = parseFloat(getComputedStyle(el.parentElement).borderLeftWidth) || 0;
    const x = elRect.left - parentRect.left - border;
    const w = elRect.width;

    if (!animate) {
        ind.classList.add('no-transition');
        requestAnimationFrame(() => ind.classList.remove('no-transition'));
    }
    ind.style.setProperty('--ind-x', `${x}px`);
    ind.style.setProperty('--ind-w', `${w}px`);
}

onMounted(() => {
    nextTick(() => moveIndicator(props.activePage, false));
});

function onTabClick(tab) {
    if (tab.key === visualActive.value || navigating) return;
    navigating = true;
    visualActive.value = tab.key;
    moveIndicator(tab.key, true);

    setTimeout(() => {
        router.visit(getHref(tab));
    }, 300);
}
</script>

<template>
    <div class="pub-nav">
        <nav class="pub-tabs">
            <!-- Скользящий индикатор -->
            <span ref="indicatorEl" class="pub-indicator" aria-hidden="true" />

            <button
                v-for="(tab, i) in tabs"
                :key="tab.key"
                :ref="el => tabEls[i] = el"
                class="pub-tab"
                :class="{ 'pub-tab--active': visualActive === tab.key }"
                @click="onTabClick(tab)"
            >
                <span class="pub-tab__icon" v-html="tab.icon" />
                {{ tab.label }}
            </button>
        </nav>
    </div>
</template>

<style scoped>
.pub-nav {
    display: flex;
    justify-content: center;
    width: 100%;
}
.pub-tabs {
    display: inline-flex;
    gap: 0.25rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    padding: 0.3rem;
    position: relative;
}

/* Скользящий индикатор */
.pub-indicator {
    position: absolute;
    top: 0.3rem; bottom: 0.3rem;
    left: 0;
    border-radius: 7px;
    background: rgba(112,112,216,0.18);
    border: 1px solid rgba(112,112,216,0.25);
    pointer-events: none;
    z-index: 0;
    width: var(--ind-w, 0px);
    transform: translateX(var(--ind-x, 0px));
    transition: transform 0.28s cubic-bezier(0.45, 0, 0.55, 1),
                width     0.28s cubic-bezier(0.45, 0, 0.55, 1);
}
.pub-indicator.no-transition { transition: none; }

.pub-tab {
    flex: 1;
    display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem;
    padding: 0.45rem 1rem; border-radius: 7px;
    border: none; background: transparent;
    color: rgba(255,255,255,0.4);
    font-family: "Figtree", sans-serif; font-size: 0.85rem;
    cursor: pointer; text-decoration: none;
    transition: color 0.22s;
    white-space: nowrap;
    position: relative; z-index: 1;
}
.pub-tab:hover { color: rgba(255,255,255,0.7); }
.pub-tab--active { color: rgba(255,255,255,0.92); }

.pub-tab__icon {
    flex-shrink: 0; opacity: 0.5;
    transition: opacity 0.22s, color 0.22s;
    display: flex; align-items: center;
}
.pub-tab--active .pub-tab__icon { opacity: 1; color: #be91ff; }
.pub-tab:hover .pub-tab__icon { opacity: 0.8; }
</style>
