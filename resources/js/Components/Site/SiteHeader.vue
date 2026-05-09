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
            <svg
                class="site-header__svg"
                viewBox="0 0 100 100"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <ellipse
                    cx="50"
                    cy="50"
                    rx="35"
                    ry="35"
                    stroke="#ec4899"
                    stroke-width="2.5"
                    transform="rotate(-15 50 50)"
                />
                <line
                    x1="20"
                    y1="60"
                    x2="80"
                    y2="40"
                    stroke="#ec4899"
                    stroke-width="2.5"
                />
            </svg>
            <span class="site-header__name">no alone</span>
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
    padding: 1.2rem 4rem 0;
    flex-shrink: 0;
    position: relative;
    z-index: 10;
    width: 100%;
}

.site-header__logo {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-shrink: 0;
    text-decoration: none;
    cursor: default;
    position: relative;
    z-index: 100;
}
a.site-header__logo {
    cursor: pointer;
}

.site-header__svg {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
}

.site-header__name {
    font-family: "Imbue", serif;
    font-size: 1.9rem;
    color: #ffb2ef;
    text-shadow: 0 0 30px rgba(255, 178, 239, 0.25);
    line-height: 0.9;
    letter-spacing: -0.02em;
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
        padding: 0.75rem 1rem 0;
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
    .site-header__svg {
        width: 38px;
        height: 38px;
    }
    .site-header__name {
        font-size: 1.7rem;
    }
}
</style>
