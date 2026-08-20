<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from "vue";
import { router, Link } from "@inertiajs/vue3";
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
    /* {
        key: "news",
        label: __("nav.news"),
        icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8M15 18h-5M10 6h8v4h-8z"/></svg>`,
    }, */
    {
        key: "contacts",
        label: __("nav.contacts"),
        icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>`,
    },
]);

const visualActive = ref(props.activePage);
const mobileMenuOpen = ref(false);

watch(
    () => props.activePage,
    (val) => {
        visualActive.value = val;
    }
);

// Запрещаем скролл когда мобильное меню открыто
function toggleBodyScroll(enable) {
    if (enable) {
        document.body.style.overflow = 'auto';
    } else {
        document.body.style.overflow = 'hidden';
    }
}

// Закрываем мобильное меню при ресайзе на десктопный размер
function handleResize() {
    if (window.innerWidth > 768 && mobileMenuOpen.value) {
        mobileMenuOpen.value = false;
        toggleBodyScroll(true);
    }
}

onMounted(() => window.addEventListener("resize", handleResize));
onUnmounted(() => {
    window.removeEventListener("resize", handleResize);
    // Сбрасываем скролл при размонтировании
    document.body.style.overflow = 'auto';
});

function getHref(tab) {
    if (tab.key === "home") return "/";
    if (tab.key === "about") return route("about");
    // if (tab.key === "news") return route("news");
    if (tab.key === "contacts") return route("contacts");
}

function toggleMobileMenu() {
    mobileMenuOpen.value = !mobileMenuOpen.value;
    // Задержка вызова toggleBodyScroll до следующего цикла, чтобы mobileMenuOpen.value обновилось
    nextTick(() => {
        toggleBodyScroll(!mobileMenuOpen.value);
    });
}

function closeMobileMenu() {
    if (mobileMenuOpen.value) {
        mobileMenuOpen.value = false;
        toggleBodyScroll(true);
    }
}

function onTabClick(tab) {
    if (props.activePage === tab.key) return;
    visualActive.value = tab.key;

    // Закрываем мобильное меню перед переходом
    if (mobileMenuOpen.value) {
        mobileMenuOpen.value = false;
        toggleBodyScroll(true);
    }

    router.visit(getHref(tab));
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
            <template v-for="(tab, index) in tabs" :key="tab.key">
                <button
                    class="pub-tab"
                    :class="{ 'pub-tab--active': visualActive === tab.key }"
                    @click="onTabClick(tab)"
                >
                    <span class="pub-tab__icon" v-html="tab.icon" />
                    {{ tab.label }}
                </button>
            </template>

            <!-- Разделитель для десктопа -->
            <div v-show="false" class="pub-tabs__divider" />

            <!-- Свитчер языков -->
            <div v-show="false" class="pub-locale-wrap">
                <LocaleSwitcher />
            </div>
        </nav>

        <!-- Оверлей для мобильного меню -->
        <div
            v-if="mobileMenuOpen"
            class="pub-nav__overlay"
            @click="closeMobileMenu"
        />
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
    background: rgba(10, 7, 20, 0.7);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.14), 0 4px 20px rgba(0, 0, 0, 0.3);
    border-radius: 12px;
    padding: 0.35rem;
}

/* Масштабирование для всех экранов (включая 4K) */
.pub-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    border-radius: 8px; /* Идеально вписывается в 12px обертки с padding 0.35rem */
    border: 1px solid transparent;
    background: transparent;
    color: rgba(255, 255, 255, 0.75);
    font-family: "Rubik", sans-serif;
    font-size: 1rem;
    padding: 0.5rem 1.1rem;
    cursor: pointer;
    white-space: nowrap;
}

.pub-tab:not(.pub-tab--active):hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.05);
}

.pub-tab--active {
    color: #ffb2ef;
    background: rgba(255, 178, 239, 0.15);
    border-color: rgba(255, 178, 239, 0.3);
    box-shadow: inset 0 1px 0 rgba(255, 178, 239, 0.4);
}

.pub-tab__icon {
    flex-shrink: 0;
    opacity: 0.75;
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
    color: #ffb2ef; /* Явный розовый для иконки активного таба */
}

.pub-tab:not(.pub-tab--active):hover .pub-tab__icon {
    opacity: 1;
}


/* Кнопка-бургер */
.pub-mobile-toggle {
    display: none; /* скрыта на десктопах */
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    background: rgba(10, 7, 20, 0.7);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    color: rgba(255, 255, 255, 0.9);
    padding: 0.5rem 0.8rem;
    cursor: pointer;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.14), 0 4px 20px rgba(0, 0, 0, 0.3);
}

.pub-mobile-toggle__text {
    font-family: "Rubik", sans-serif;
    font-size: 0.95rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.9;
}



/* Оверлей */
.pub-nav__overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 999;
}

/* ========== МОБИЛЬНАЯ ВЕРСИЯ (≤768px) ========== */
@media (max-width: 768px) {
    .pub-nav {
        justify-content: flex-end; /* бургер справа */
        padding: 0;
        /* position: relative сохраняется с десктопной версии */
    }

    .pub-mobile-toggle {
        display: flex;
        z-index: 1000;
        flex: 1;
        justify-content: space-between;
        padding: 0.6rem 1rem;
    }

    /* Скрываем десктопную панель, готовим мобильное меню */
    .pub-tabs {
        display: none;
        position: fixed;
        top: 100px;
        right: 1rem;
        left: 1rem;
        flex-direction: column;
        background: rgba(10, 7, 20, 0.85);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        padding: 0.5rem;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        z-index: 1000;
        transform-origin: top center;
    }

    .pub-nav-logo-wrapper {
        display: none; /* Прячем центральный логотип на мобилках */
    }

    /* Открытое мобильное меню */
    .pub-tabs--open {
        display: flex;
        z-index: 1000;
    }

    .pub-tab {
        width: 100%;
        justify-content: flex-start;
        padding: 0.9rem 1rem;
        font-size: 1.1rem; /* увеличенный читаемый размер для мобилки */
    }

    .pub-mobile-locale {
        display: flex;
        justify-content: center;
        padding-top: 0.8rem;
        margin-top: 0.3rem;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
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
