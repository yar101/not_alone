<script setup>
import { Link } from "@inertiajs/vue3";
import PublicNav from "@/Components/Site/PublicNav.vue";

defineProps({
    activePage: { type: String, default: "home" },
});
</script>

<template>
    <header class="site-header">
        <!-- Основной логотип (слева на десктопе) -->
        <component
            :is="activePage === 'home' ? 'span' : Link"
            :href="activePage !== 'home' ? '/' : undefined"
            :view-transition="activePage !== 'home' ? true : undefined"
            class="site-header__logo"
        >
            <img src="/app-logo-v3.webp" alt="Not Alone" class="site-header__logo-img" />
        </component>

        <!-- Навигация по центру -->
        <PublicNav :activePage="activePage" class="site-header__nav" />
    </header>
</template>

<style scoped>
.site-header {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center; /* Центрируем навбар */
    padding: 2rem 4rem 1.2rem;
    flex-shrink: 0;
    position: relative;
    width: 100%;
}

.site-header__logo {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    text-decoration: none;
    cursor: default;
    position: absolute;
    left: 4rem;
    top: 65%;
    transform: translateY(-50%);
    transition: opacity 0.15s ease, transform 0.15s ease;
}
a.site-header__logo {
    cursor: pointer;
}
a.site-header__logo:hover {
    opacity: 0.92;
    transform: translateY(-50%) scale(1.02);
}

.site-header__logo-img {
    height: 100px; /* Размер для мобильных по умолчанию */
    width: auto;
    display: block;
    filter: drop-shadow(0 4px 12px rgba(0,0,0,0.35));
}

@media (min-width: 768px) {
    .site-header__logo-img {
        height: 160px; /* Увеличенный размер для десктопа */
    }
}

.site-header__nav {
    display: flex;
    justify-content: center;
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
        justify-content: space-between; /* На мобилке разносим лого и бургер */
    }
    .site-header__nav {
        flex: 1;
    }
    .site-header__contact-label {
        display: none;
    }
    .site-header__logo {
        position: static;
        transform: none;
    }
    /* .mobile-only-logo {
        display: flex !important;
    } */
}
</style>
