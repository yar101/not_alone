<script setup>
import { ref } from 'vue';
import StartBtn from "@/Components/Site/StartBtn.vue";
import FirstModal from "@/Components/Site/FirstModal.vue";
import AuthModal from "@/Components/Site/AuthModal.vue";

const showFirstModal = ref(false);
const showAuthModal = ref(false);
</script>

<template>
    <!-- Декоративные фоновые элементы (круги) -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="circle1"/>
        <div class="circle2"/>
        <div class="circle3"/>
    </div>

    <!-- Фоновое изображение (Пикачу) с адаптивным позиционированием -->
    <img src="/pika.png" alt="pika"
         class="fixed max-w-[800px] md:max-w-[1400px] left-1/2 -translate-x-1/3 max-[756px]:-translate-y-[20%] md:left-[20rem] md:translate-x-0 opacity-20 md:opacity-100 pointer-events-none z-0"/>

    <!-- Адаптивное изображение звезды (фиксировано в правой нижней части) -->
    <img src="/star.png" alt="star"
         class="fixed w-[200px] sm:w-[350px] md:w-[500px] lg:w-[570px] rotate-[15deg] opacity-[30%] md:opacity-[50%] right-[2%] bottom-[5%] md:right-[5%] md:bottom-[8%] pointer-events-none z-0 transition-all duration-700 ease-in-out"/>

    <!-- Основной контейнер с градиентным фоном -->
    <div class="min-h-screen text-white overflow-x-hidden main-gradient relative z-10 flex flex-col">
        <div class="max-w-[1440px] w-full mx-auto flex flex-col justify-between flex-1">

            <!-- Шапка сайта -->
            <header class="flex flex-col md:flex-row items-center justify-between p-6 md:p-8 gap-6">
                <!-- Логотип -->
                <div class="flex items-center gap-3 md:gap-4">
                    <svg class="w-16 h-16 md:w-24 h-24" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="50" cy="50" rx="35" ry="35" stroke="#ec4899" stroke-width="2"
                                 transform="rotate(-15 50 50)"/>
                        <line x1="20" y1="60" x2="80" y2="40" stroke="#ec4899" stroke-width="2"/>
                    </svg>
                    <h1 class="logo-text text-5xl sm:text-6xl md:text-[96px]">no alone</h1>
                </div>

                <!-- Контактная информация -->
                <div class="flex flex-col sm:flex-row items-center gap-4 md:gap-8 text-lg md:text-xl">
                    <a href="https://t.me/no_alone" class="flex items-center gap-2 hover:text-cyan-400 transition-colors duration-300">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.12.02-1.96 1.25-5.54 3.67-.52.36-.99.53-1.42.52-.47-.01-1.37-.26-2.03-.48-.82-.27-1.47-.42-1.42-.88.03-.24.37-.48 1.02-.73 3.99-1.74 6.66-2.89 8-3.45 3.81-1.58 4.6-1.85 5.12-1.86.11 0 .37.03.53.16.14.11.18.26.2.37.01.08.03.29.01.45z"/>
                        </svg>
                        no_alone
                    </a>
                    <a href="mailto:adm@na.ru" class="flex items-center gap-2 hover:text-pink-400 transition-colors duration-300">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                        </svg>
                        adm@na.ru
                    </a>
                </div>
            </header>

            <!-- Основной контент -->
            <main class="flex-1 flex flex-col items-center justify-center py-10 md:pb-[12%] px-6">

                <!-- Главная кнопка (START) -->
                <div class="scale-110 sm:scale-105 backdrop-blur md:scale-125 mb-14 md:mb-16 transform transition-transform">
                    <StartBtn @click="showAuthModal = true" />
                </div>

                <!-- Кнопка модалки -->
                <div class="w-full flex justify-center">
                    <div class="w-full max-w-[550px]">
                        <button
                            class="flex items-center justify-center gap-4 py-3.5 md:py-4 px-4 md:px-6 link-button w-full transition-all duration-500 ease-out group link-left"
                            @click="showFirstModal = true"
                        >
                            <span class="text-gray-200 group-hover:text-white transition-colors">
                                Справка
                            </span>
                        </button>
                    </div>
                </div>

                <FirstModal :show="showFirstModal" @close="showFirstModal = false" />
                <AuthModal :show="showAuthModal" @close="showAuthModal = false" />
            </main>

            <!-- Балансировочный отступ -->
            <div class="h-8 md:h-16"></div>
        </div>
    </div>
</template>

<style scoped>

/* Кастомный градиент фона */
.main-gradient {
    background: linear-gradient(180deg, rgba(255, 42, 191, 0.09) 0%, rgba(0, 0, 0, 0.56) 100%) fixed;
}

/* Фоновые круги */
.circle1, .circle2, .circle3 {
    border-radius: 50%;
    background: rgba(60, 60, 190, 0.04);
    box-shadow: inset 0 0 20px rgba(255, 255, 255, 0.02);
    position: absolute;
    right: -15%;
    top: -10%;
}

.circle1 { width: 1000px; height: 1000px; }
.circle2 { width: 800px; height: 800px; }
.circle3 { width: 600px; height: 600px; }

@media (max-width: 1024px) {
    .circle1 { width: 700px; height: 700px; }
    .circle2 { width: 550px; height: 550px; }
    .circle3 { width: 400px; height: 400px; }
}

@media (max-width: 640px) {
    .circle1 { width: 400px; height: 400px; right: -20%; }
    .circle2 { width: 300px; height: 300px; right: -15%; }
    .circle3 { width: 200px; height: 200px; right: -10%; }
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
    color: #7070d8;
    text-shadow: 0 0 30px rgba(110, 110, 210, 0.25);
    line-height: 0.9;
    letter-spacing: 0.08em;
    font-size: 28px;
}

.link-left {
    background: linear-gradient(90deg, rgba(45, 20, 45, 0.25) 0%, rgba(20, 20, 20, 0.5) 100%);
}

.link-left:hover {
    border-color: rgba(255, 42, 191, 0.35);
    box-shadow: inset 2px 0 20px rgba(255, 42, 191, 0.12), 0 10px 30px rgba(0, 0, 0, 0.3);
}

.link-right {
    background: linear-gradient(90deg, rgba(20, 20, 20, 0.5) 0%, rgba(20, 35, 35, 0.25) 100%);
}

.link-right:hover {
    border-color: rgba(42, 255, 220, 0.35);
    box-shadow: inset -2px 0 20px rgba(42, 255, 220, 0.12), 0 10px 30px rgba(0, 0, 0, 0.3);
}

.link-button:hover {
    transform: translateY(-3px);
}

/* Логотип */
.logo-text {
    font-family: "Imbue", serif;
    color: #7070d8;
    text-shadow: 0 0 30px rgba(110, 110, 210, 0.25);
    line-height: 0.9;
    letter-spacing: -0.02em;
}

html {
    scroll-behavior: smooth;
}
</style>
