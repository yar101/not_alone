<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    activePage: { type: String, default: 'home' },
});

const { __ } = useTranslations();

const tabs = computed(() => [
    {
        key: 'home', label: __('nav.home'),
        icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg>`,
    },
    {
        key: 'about', label: __('nav.about'),
        icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L13.8 9.2L21 12L13.8 14.8L12 22L10.2 14.8L3 12L10.2 9.2L12 2Z"/></svg>`,
    },
    {
        key: 'news', label: __('nav.news'),
        icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8M15 18h-5M10 6h8v4h-8z"/></svg>`,
    },
]);

const visualActive = ref(props.activePage);
let navigating = false;

function getHref(tab) {
    if (tab.key === 'home') return '/';
    if (tab.key === 'about') return route('about');
    if (tab.key === 'news') return route('news');
}

function onTabClick(tab) {
    if (tab.key === visualActive.value || navigating) return;
    navigating = true;
    visualActive.value = tab.key;

    setTimeout(() => {
        router.visit(getHref(tab));
    }, 200);
}
</script>

<template>
    <div class="pub-nav">
        <nav class="pub-tabs">
            <button v-for="tab in tabs" :key="tab.key" class="pub-tab"
                :class="{ 'pub-tab--active': visualActive === tab.key }" @click="onTabClick(tab)">
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
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    padding: 0.3rem;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    animation: pub-tabs-appear 0.45s cubic-bezier(0.22, 1, 0.36, 1) 0.15s both;
}

@keyframes pub-tabs-appear {
    from {
        opacity: 0;
        transform: translateY(-6px) scale(0.97);
        filter: blur(4px);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
        filter: blur(0);
    }
}

.pub-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 0.45rem 1rem;
    border-radius: 7px;
    border: 1px solid transparent;
    background: transparent;
    color: rgba(255, 255, 255, 0.4);
    font-family: 'Rubik', sans-serif;
    font-size: 1rem;
    cursor: pointer;
    white-space: nowrap;
    transition: color 0.18s, background 0.18s, border-color 0.18s;
}

.pub-tab:hover {
    color: rgba(255, 255, 255, 0.7);
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.08);
}

.pub-tab--active {
    color: rgba(255, 255, 255, 0.92);
    background: rgba(160, 160, 255, 0.15);
    border-color: rgba(160, 160, 255, 0.3);
}

.pub-tab__icon {
    flex-shrink: 0;
    opacity: 0.5;
    transition: opacity 0.18s;
    display: flex;
    align-items: center;
}

.pub-tab--active .pub-tab__icon {
    opacity: 1;
    color: var(--color-base-1);
}

.pub-tab:hover .pub-tab__icon {
    opacity: 0.8;
}
</style>
