<script setup>
import { ref, computed } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import StartBtn from "@/Components/Site/StartBtn.vue";
import AuthModal from "@/Components/Site/AuthModal.vue";
import SiteHeader from "@/Components/Site/SiteHeader.vue";
import { useTranslations } from "@/composables/useTranslations";
import LocaleLoader from "@/Components/LocaleLoader.vue";

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
</script>

<template>
    <LocaleLoader />
    <!-- Декоративные фоновые элементы (круги) -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="circle1" />
        <div class="circle2" />
        <div class="circle3" />
    </div>

    <!-- Фоновое изображение (Пикачу) с адаптивным позиционированием -->
    <img
        src="/pika.webp"
        alt="pika"
        class="fixed max-w-[800px] md:max-w-[1400px] left-1/2 -translate-x-1/3 max-[756px]:-translate-y-[20%] md:left-[20rem] md:translate-x-0 opacity-20 md:opacity-100 pointer-events-none z-0"
    />

    <!-- Адаптивное изображение звезды (фиксировано в правой нижней части) -->
    <img
        src="/star.webp"
        alt="star"
        class="fixed w-[200px] sm:w-[350px] md:w-[500px] lg:w-[570px] rotate-[15deg] opacity-[30%] md:opacity-[50%] right-[2%] bottom-[5%] md:right-[5%] md:bottom-[8%] pointer-events-none z-0 transition-all duration-700 ease-in-out"
    />

    <!-- Основной контейнер с градиентным фоном -->
    <div
        class="min-h-screen text-white overflow-x-hidden main-gradient relative z-10 flex flex-col"
    >
        <SiteHeader activePage="home" />

        <div
            class="max-w-[1440px] w-full mx-auto flex flex-col justify-between flex-1"
        >
            <!-- Основной контент -->
            <main
                class="flex-1 flex flex-col items-center justify-center py-10 md:pb-[12%] px-6"
            >
                <!-- Главная кнопка (START) -->
                <div
                    class="scale-110 sm:scale-105 md:scale-125 mb-14 md:mb-16 transform transition-transform"
                >
                    <StartBtn
                        :label="startBtnLabel"
                        @click="handleStartClick"
                    />
                </div>


                <AuthModal
                    :show="showAuthModal"
                    @close="showAuthModal = false"
                />
            </main>

            <!-- Балансировочный отступ -->
            <div class="h-8 md:h-16"></div>
        </div>
    </div>
</template>

<style scoped>
/* Кастомный градиент фона */
.main-gradient {
    background: linear-gradient(
            180deg,
            rgba(255, 42, 191, 0.09) 0%,
            rgba(0, 0, 0, 0.56) 100%
        )
        fixed;
}

/* Фоновые круги */
.circle1,
.circle2,
.circle3 {
    border-radius: 50%;
    background: rgba(60, 60, 190, 0.03);
    box-shadow: inset 0 0 30px rgba(255, 255, 255, 0.015);
    position: absolute;
    right: -10vw;
    top: -10vw;
}

.circle1 {
    width: clamp(350px, 60vw, 900px);
    height: clamp(350px, 60vw, 900px);
}
.circle2 {
    width: clamp(250px, 45vw, 700px);
    height: clamp(250px, 45vw, 700px);
}
.circle3 {
    width: clamp(150px, 30vw, 500px);
    height: clamp(150px, 30vw, 500px);
}

@media (max-width: 768px) {
    .circle1, .circle2, .circle3 {
        right: 0;
        top: 0;
        transform: translate(40%, -40%);
    }
}

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

html {
    scroll-behavior: smooth;
}

</style>
