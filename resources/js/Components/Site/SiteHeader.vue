<script setup>
import { Link } from "@inertiajs/vue3";
import PublicNav from "@/Components/Site/PublicNav.vue";

defineProps({
    activePage: { type: String, default: "home" },
});
</script>

<template>
    <header class="site-header">
        <!-- Логотип -->
        <component
            :is="activePage === 'home' ? 'span' : Link"
            :href="activePage !== 'home' ? '/' : undefined"
            class="site-header__logo"
        >
            <img src="/app-logo-v3.png" alt="Not Alone" class="site-header__logo-img" />
        </component>

        <!-- Навигация по центру -->
        <PublicNav :activePage="activePage" class="site-header__nav" />
    </header>
</template>

<style scoped>
.site-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 1.2rem 4rem 1.2rem;
    flex-shrink: 0;
    position: relative;
    z-index: 10;
    width: 100%;
}

.site-header__logo {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    text-decoration: none;
    cursor: default;
    position: relative;
    z-index: 100;
    transition: opacity 0.2s;
    overflow: visible;
}
a.site-header__logo {
    cursor: pointer;
}
a.site-header__logo:hover {
    opacity: 0.8;
}

.site-header__logo-img {
    height: 168px;
    width: auto;
    display: block;
}

.site-header__nav {
    position: absolute;
    left: 0;
    right: 0;
    display: flex;
    justify-content: center;
    pointer-events: none;
}
.site-header__nav > * {
    pointer-events: auto;
}
.site-header__nav .pub-tab {
    font-size: 0.9rem;
    padding: 0.45rem 0.95rem;
}
@media (min-width: 2000px) {
    .site-header__nav .pub-tab {
        font-size: 1rem;
        padding: 0.5rem 1.1rem;
    }
}

@media (max-width: 768px) {
    .site-header__contacts {
        display: none;
    }
}

.site-header__contact {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.5);
    font-size: 1rem;
    text-decoration: none;
    transition: color 0.2s;
    font-family: "Rubik", sans-serif;
}
.site-header__contact:hover {
    color: rgba(255, 255, 255, 0.85);
}

.site-header__contact-icon {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

@media (max-width: 768px) {
    .site-header {
        padding: 0.75rem 1rem 0.75rem;
        gap: 1.25rem;
    }
    .site-header__nav {
        position: static;
        flex: 1;
        transform: none;
    }
    .site-header__contact-label {
        display: none;
    }
    .site-header__logo-img {
        height: 72px;
    }
}
</style>
