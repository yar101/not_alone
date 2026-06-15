<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { router } from "@inertiajs/vue3";
import { useTranslations } from "@/composables/useTranslations";
import LocaleSwitcher from "@/Components/Site/LocaleSwitcher.vue";

const props = defineProps({
    activePage: { type: String, default: "home" },
});

const { __ } = useTranslations();

const tabs = computed(() => [
    {
        key: "home",
        label: __("nav.home"),
        icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg>`,
    },
    {
        key: "about",
        label: __("nav.about"),
        icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L13.8 9.2L21 12L13.8 14.8L12 22L10.2 14.8L3 12L10.2 9.2L12 2Z"/></svg>`,
    },
    {
        key: "news",
        label: __("nav.news"),
        icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8M15 18h-5M10 6h8v4h-8z"/></svg>`,
    },
    {
        key: "contacts",
        label: __("nav.contacts"),
        icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>`,
    },
]);

const visualActive = ref(props.activePage);
const mobileMenuOpen = ref(false);
let navigating = false;

// Закрываем мобильное меню при ресайзе на десктопный размер
function handleResize() {
    if (window.innerWidth > 768 && mobileMenuOpen.value) {
        mobileMenuOpen.value = false;
    }
}

onMounted(() => window.addEventListener("resize", handleResize));
onUnmounted(() => window.removeEventListener("resize", handleResize));

function getHref(tab) {
    if (tab.key === "home") return "/";
    if (tab.key === "about") return route("about");
    if (tab.key === "news") return route("news");
    if (tab.key === "contacts") return route("contacts");
}

function toggleMobileMenu() {
    mobileMenuOpen.value = !mobileMenuOpen.value;
}

function closeMobileMenu() {
    mobileMenuOpen.value = false;
}

function onTabClick(tab) {
    if (tab.key === visualActive.value || navigating) return;
    navigating = true;
    visualActive.value = tab.key;

    // Закрываем мобильное меню перед переходом
    if (mobileMenuOpen.value) {
        mobileMenuOpen.value = false;
    }

    setTimeout(() => {
        router.visit(getHref(tab));
    }, 200);
}
</script>

<template>
    <div class="pub-nav" :class="{ 'pub-nav--mobile-open': mobileMenuOpen }">
        <!-- Кнопка-бургер (видна только на мобильных) -->
        <button
            class="pub-mobile-toggle"
            @click="toggleMobileMenu"
            aria-label="Открыть меню"
            :aria-expanded="mobileMenuOpen"
        >
            <span class="pub-mobile-toggle__text">{{ __("nav.menu") }}</span>
            <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <line x1="4" y1="6" x2="20" y2="6" />
                <line x1="4" y1="12" x2="20" y2="12" />
                <line x1="4" y1="18" x2="20" y2="18" />
            </svg>
        </button>

        <!-- Основная навигация -->
        <nav class="pub-tabs" :class="{ 'pub-tabs--open': mobileMenuOpen }">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                class="pub-tab"
                :class="{ 'pub-tab--active': visualActive === tab.key }"
                @click="onTabClick(tab)"
            >
                <span class="pub-tab__icon" v-html="tab.icon" />
                {{ tab.label }}
            </button>

            <!-- Разделитель для десктопа -->
            <div class="pub-tabs__divider" />

            <!-- Свитчер языков -->
            <div class="pub-locale-wrap">
                <LocaleSwitcher />
            </div>
        </nav>

        <!-- Оверлей для мобильного меню -->
        <Transition name="overlay-fade">
            <div
                v-if="mobileMenuOpen"
                class="pub-nav__overlay"
                @click="closeMobileMenu"
            />
        </Transition>
    </div>
</template>

<style scoped>
/* ========== БАЗОВЫЕ СТИЛИ (ДЕСКТОП) ========== */
.pub-locale-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
}

.pub-nav {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    position: relative;
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

/* Масштабирование для всех экранов (включая 4K) */
.pub-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    border-radius: 7px;
    border: 1px solid transparent;
    background: transparent;
    color: rgba(255, 255, 255, 0.4);
    font-family: "Rubik", sans-serif;
    font-size: 1rem;
    padding: 0.5rem 1.1rem;
    cursor: pointer;
    white-space: nowrap;
    transition:
        color 0.18s,
        background 0.18s,
        border-color 0.18s;
}

.pub-tab:hover {
    color: rgba(255, 255, 255, 0.7);
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.08);
}

.pub-tab--active {
    color: rgba(255, 255, 255, 0.92);
    background: rgba(255, 178, 239, 0.15);
    border-color: rgba(255, 178, 239, 0.3);
}

.pub-tab__icon {
    flex-shrink: 0;
    opacity: 0.5;
    transition: opacity 0.18s;
    display: flex;
    align-items: center;
}

/* Иконки теперь масштабируются вместе с кнопкой */
.pub-tab__icon :deep(svg) {
    width: 1em;
    height: 1em;
    display: block;
}

.pub-tab--active .pub-tab__icon {
    opacity: 1;
    color: var(--color-base-1, #ffffff);
}

.pub-tab:hover .pub-tab__icon {
    opacity: 0.8;
}

/* Кнопка-бургер */
.pub-mobile-toggle {
    display: none; /* скрыта на десктопах */
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    color: rgba(255, 255, 255, 0.7);
    padding: 0.5rem 0.8rem;
    cursor: pointer;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    transition:
        background 0.2s,
        color 0.2s;
}

.pub-mobile-toggle__text {
    font-family: "Rubik", sans-serif;
    font-size: 0.95rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.9;
}

.pub-mobile-toggle:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

/* Оверлей */
.pub-nav__overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 98;
}

/* Анимация появления оверлея */
.overlay-fade-enter-active,
.overlay-fade-leave-active {
    transition: opacity 0.3s ease;
}
.overlay-fade-enter-from,
.overlay-fade-leave-to {
    opacity: 0;
}

/* Анимация появления панели при первой загрузке */
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

/* ========== МОБИЛЬНАЯ ВЕРСИЯ (≤768px) ========== */
@media (max-width: 768px) {
    .pub-nav {
        justify-content: flex-end; /* бургер справа */
        padding: 0;
    }

    .pub-mobile-toggle {
        display: flex;
        z-index: 100;
        flex: 1;
        justify-content: space-between;
        padding: 0.6rem 1rem;
    }

    /* Скрываем десктопную панель, готовим мобильное меню */
    .pub-tabs {
        display: none;
        position: absolute;
        top: calc(100% + 0.5rem);
        right: 0.5rem;
        left: 0.5rem;
        flex-direction: column;
        background: rgba(20, 20, 30, 0.85);
        backdrop-filter: blur(30px);
        -webkit-backdrop-filter: blur(30px);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 14px;
        padding: 0.8rem;
        gap: 0.5rem;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        z-index: 99;
        animation: none; /* убираем анимацию загрузки, добавим свою */
        transform-origin: top right;
    }

    /* Открытое мобильное меню */
    .pub-tabs--open {
        display: flex;
        animation: mobile-menu-in 0.3s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .pub-tab {
        width: 100%;
        justify-content: flex-start;
        padding: 0.7rem 1rem;
        font-size: 1rem; /* фиксированный читаемый размер */
    }

    .pub-mobile-locale {
        display: flex;
        justify-content: center;
        padding-top: 0.8rem;
        margin-top: 0.3rem;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }

    @keyframes mobile-menu-in {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(-8px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
}
@media (min-width: 2000px) {
    .pub-tab {
        /* Немного увеличиваем максимальные значения */
        font-size: 1.1rem;
        padding: 0.6rem 1.4rem;
    }
    .pub-tabs {
        padding: 0.4rem;
        border-radius: 14px;
        gap: 0.4rem;
    }
}
</style>
