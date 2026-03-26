<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    activePage: { type: String, default: 'home' },
});
</script>

<template>
    <div class="pub-nav">
        <nav class="pub-tabs">
            <!-- Главная -->
            <component
                :is="activePage === 'home' ? 'button' : Link"
                :href="activePage !== 'home' ? '/' : undefined"
                class="pub-tab"
                :class="{ 'pub-tab--active': activePage === 'home' }"
            >
                <svg class="pub-tab__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/>
                    <polyline points="9 21 9 12 15 12 15 21"/>
                </svg>
                Главная
            </component>

            <!-- О проекте -->
            <component
                :is="activePage === 'about' ? 'button' : Link"
                :href="activePage !== 'about' ? route('about') : undefined"
                class="pub-tab"
                :class="{ 'pub-tab--active': activePage === 'about' }"
            >
                <svg class="pub-tab__icon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2L13.8 9.2L21 12L13.8 14.8L12 22L10.2 14.8L3 12L10.2 9.2L12 2Z"/>
                </svg>
                О проекте
            </component>

            <!-- Новости -->
            <component
                :is="activePage === 'news' ? 'button' : Link"
                :href="activePage !== 'news' ? route('news') : undefined"
                class="pub-tab"
                :class="{ 'pub-tab--active': activePage === 'news' }"
            >
                <svg class="pub-tab__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/>
                    <path d="M18 14h-8M15 18h-5M10 6h8v4h-8z"/>
                </svg>
                Новости
            </component>
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
}
.pub-tab {
    display: inline-flex; align-items: center; gap: 0.45rem;
    padding: 0.45rem 1rem; border-radius: 7px;
    border: none; background: transparent;
    color: rgba(255,255,255,0.4);
    font-family: "Figtree", sans-serif; font-size: 0.85rem;
    cursor: pointer; text-decoration: none;
    transition: color 0.2s, background 0.2s;
    white-space: nowrap;
}
.pub-tab:hover { color: rgba(255,255,255,0.7); }
.pub-tab--active {
    background: rgba(112,112,216,0.18);
    color: rgba(255,255,255,0.92);
    border: 1px solid rgba(112,112,216,0.25);
}
.pub-tab__icon { flex-shrink: 0; opacity: 0.5; transition: opacity 0.2s; }
.pub-tab--active .pub-tab__icon { opacity: 1; color: #be91ff; }
.pub-tab:hover .pub-tab__icon { opacity: 0.8; }
</style>
