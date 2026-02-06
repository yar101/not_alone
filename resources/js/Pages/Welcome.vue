<script setup>
import {Link} from '@inertiajs/vue3';
import {ChatDotRound, ArrowRight} from '@element-plus/icons-vue';
// Убедись, что путь к компоненту кнопки верный.
// В прошлом шаге мы создали StartButton.vue, здесь импорт StartBtn.vue
import StartBtn from "@/Components/Site/StartBtn.vue";

// You can customize these links
const links = [
    {text: 'Ссылка куда-то', url: '/link1'},
    {text: 'Ссылка куда-то', url: '/link2'},
];
</script>

<template>
    <!-- Фоновые элементы (круги) -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="circle1"/>
        <div class="circle2"/>
        <div class="circle3"/>
    </div>

    <!-- Фоновая картинка -->
    <img src="/pika.png" alt="pika"
         class="fixed max-w-[1400px] left-[20rem] pointer-events-none z-0"/>

    <!-- Основной контейнер с градиентом -->
    <!-- Убрал bg-[#141414], добавил класс main-gradient -->
    <div class="h-screen text-white overflow-hidden main-gradient relative z-10">
        <div class="w-[1440px] mx-auto flex flex-col justify-between h-full">
            <!-- Header -->
            <header class="flex items-center justify-between p-8">
                <!-- Logo -->
                <div class="flex items-center gap-4">
                    <svg class="w-24 h-24" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="50" cy="50" rx="35" ry="35" stroke="#ec4899" stroke-width="2"
                                 transform="rotate(-15 50 50)"/>
                        <line x1="20" y1="60" x2="80" y2="40" stroke="#ec4899" stroke-width="2"/>
                    </svg>
                    <h1 class="logo-text">no alone</h1>
                </div>

                <!-- Contact Info -->
                <div class="flex items-center gap-6 text-xl">
                    <a href="https://t.me/no_alone" class="flex items-center gap-2 hover:text-pink-400 transition">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.12.02-1.96 1.25-5.54 3.67-.52.36-.99.53-1.42.52-.47-.01-1.37-.26-2.03-.48-.82-.27-1.47-.42-1.42-.88.03-.24.37-.48 1.02-.73 3.99-1.74 6.66-2.89 8-3.45 3.81-1.58 4.6-1.85 5.12-1.86.11 0 .37.03.53.16.14.11.18.26.2.37.01.08.03.29.01.45z"/>
                        </svg>
                        no_alone
                    </a>
                    <a href="mailto:adm@na.ru" class="flex items-center gap-2 hover:text-pink-400 transition">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                        </svg>
                        adm@na.ru
                    </a>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 flex flex-col items-center justify-end mb-[15%] px-8">

                <StartBtn />

                <div class="mt-8 w-full flex justify-center">
                    <div class="grid grid-cols-2 gap-4 w-full max-w-[800px]">
                        <Link
                            v-for="(link, index) in links"
                            :key="index"
                            :href="link.url"
                            class="block w-full"
                        >
                            <button
                                class="flex backdrop-blur-md items-center gap-2 bg-[#202020]/60 py-3 px-5 link-button rounded-[4px] w-full transition-colors duration-300 group">
                                <el-icon class="group-hover:text-[#ec4899] transition-colors">
                                    <ChatDotRound/>
                                </el-icon>
                                <span class="flex-1 text-left">{{ link.text }}</span>
                                <el-icon class="ml-2 group-hover:translate-x-1 transition-transform">
                                    <ArrowRight/>
                                </el-icon>
                            </button>
                        </Link>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Imbue:opsz,wght@10..100,100..900&display=swap');

.main-gradient {
    /* Градиент сверху вниз (180deg).
       Верх: полупрозрачный розовый (просвечивают круги и пикачу).
       Низ: черный.
    */
    background: linear-gradient(180deg, rgba(255, 42, 191, 0.09) 0%, rgba(0, 0, 0, 0.56) 100%);
}

.circle1 {
    width: 1000px;
    height: 1000px;
    border-radius: 50%;
    background: rgba(74, 70, 200, 0.04);
    box-shadow: inset 0 0 15px rgba(255, 255, 255, 0.02);
    position: absolute;
    right: -200px;
    top: -200px;
}

.circle2 {
    width: 800px;
    height: 800px;
    border-radius: 50%;
    background: rgba(74, 70, 200, 0.04);
    box-shadow: inset 0 0 15px rgba(255, 255, 255, 0.02);
    position: absolute;
    right: -200px;
    top: -200px;
}

.circle3 {
    width: 600px;
    height: 600px;
    border-radius: 50%;
    background: rgba(74, 70, 200, 0.04);
    box-shadow: inset 0 0 15px rgba(255, 255, 255, 0.02);
    position: absolute;
    right: -200px;
    top: -200px;
}

/* Стили кнопок ссылок */
.link-button {
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-top: 1px solid rgba(255, 255, 255, 0.3);
    /* Добавляем легкую тень для читаемости на фоне градиента */
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.link-button:hover {
    background: #2b2b2b;
    border-color: rgba(236, 72, 153, 0.5); /* Розовая подсветка при наведении */
}

.imbue {
    font-family: "Imbue", serif;
    font-optical-sizing: auto;
    font-weight: 500;
    font-style: normal;
    letter-spacing: 17%;
}

.logo-text {
    font-family: "Imbue", serif;
    font-size: 96px;
    color: #C8467E;
    text-shadow: 0 0 20px rgba(200, 70, 126, 0.3);
}
</style>
