<script setup>
import { ref, computed } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import StartBtn from "@/Components/Site/StartBtn.vue";
import AuthModal from "@/Components/Site/AuthModal.vue";
import { useTranslations } from "@/composables/useTranslations";
import LocaleLoader from "@/Components/LocaleLoader.vue";
import PublicLayout from "@/Layouts/PublicLayout.vue";

defineOptions({
    layout: (h, page) => h(PublicLayout, { activePage: "home" }, () => page),
});

const page = usePage();
const showAuthModal = ref(false);

const { __ } = useTranslations();

const startBtnLabel = computed(() => {
    return page.props.auth?.user ? __("welcome.enter") : __("welcome.start");
});

const handleStartClick = () => {
    if (page.props.auth?.user) {
        router.visit(route("profile"));
    } else {
        showAuthModal.value = true;
    }
};

const handleLearnMoreClick = () => {
    router.visit(route("about"));
};
</script>

<template>
    <LocaleLoader />
    <!-- Фоновое изображение (Пикачу) с адаптивным позиционированием -->
    <img
        src="/pika.webp"
        alt="pika"
        class="fixed max-w-[800px] md:max-w-[1400px] left-1/2 -translate-x-1/3 max-[756px]:-translate-y-[20%] md:left-[20rem] md:translate-x-0 opacity-20 md:opacity-100 pointer-events-none z-0"
    />

    <!-- Адаптивное изображение звезды -->
    <img
        src="/star.webp"
        alt="star"
        class="fixed w-[200px] sm:w-[350px] md:w-[500px] lg:w-[570px] rotate-[15deg] opacity-[30%] md:opacity-[50%] right-[2%] bottom-[5%] md:right-[5%] md:bottom-[8%] pointer-events-none z-0 transition-all duration-700 ease-in-out"
    />

    <!-- Основной контент страницы -->
    <div class="max-w-[1440px] w-full mx-auto flex flex-col justify-between flex-1 relative z-10">
        <main class="flex-1 flex flex-col items-center justify-center py-10 md:pb-[12%] px-6">
            <!-- Главная кнопка (START) -->
            <div class="scale-110 sm:scale-105 md:scale-125 mb-8 md:mb-12 transform transition-transform">
                <StartBtn
                    :label="startBtnLabel"
                    @click="handleStartClick"
                />
            </div>

            <!-- Кнопка "Узнать подробнее" -->
            <div class="mt-3 md:mt-4">
                <button
                    class="learn-more-btn"
                    @click="handleLearnMoreClick"
                >
                    Узнать подробнее
                </button>
            </div>

            <AuthModal
                :show="showAuthModal"
                @close="showAuthModal = false"
            />
        </main>

        <div class="h-8 md:h-16"></div>
    </div>
</template>

<style scoped>
/* Стили кнопок-ссылок */
.link-button {
    background: rgba(20, 20, 20, 0.5);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 3px;
    position: relative;
    overflow: hidden;
    font-family: "Brygada 1918", serif;
    font-weight: 400;
    color: #ffb2ef;
    text-shadow: 0 0 30px rgba(255, 178, 239, 0.25);
    line-height: 0.9;
    letter-spacing: 0.08em;
    font-size: 1.6rem;
}

.link-left {
    background: linear-gradient(
        90deg,
        rgba(45, 20, 45, 0.25) 0%,
        rgba(20, 20, 20, 0.5) 100%
    );
}

.link-left:hover {
    border-color: rgba(255, 42, 191, 0.35);
    box-shadow:
        inset 2px 0 20px rgba(255, 42, 191, 0.12),
        0 10px 30px rgba(0, 0, 0, 0.3);
}

/* Кнопка "Узнать подробнее" */
.learn-more-btn {
    min-width: 320px;
    width: 100%;
    max-width: 380px;
    padding: 0.9rem 2.5rem;
    background: rgba(20, 20, 20, 0.6);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    font-family: "Inter Variable", system-ui, -apple-system, sans-serif;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.95);
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.learn-more-btn:hover {
    background: rgba(20, 20, 20, 0.75);
    border-color: rgba(255, 178, 239, 0.3);
    color: #ffb2ef;
    box-shadow: 0 6px 25px rgba(255, 178, 239, 0.15);
}

.learn-more-btn:active {
    transform: scale(0.98);
}

@media (max-width: 480px) {
    .learn-more-btn {
        width: 100%;
        max-width: none;
        font-size: 1.1rem;
        padding: 0.8rem 2rem;
    }
}

html {
    scroll-behavior: smooth;
}
</style>
