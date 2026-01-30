<script setup>
import {Link} from '@inertiajs/vue3';
import {ChatDotRound, ArrowRight} from '@element-plus/icons-vue';

// You can customize these links
const links = [
    {text: 'Ссылка куда-то', url: '/link1'},
    {text: 'Ссылка куда-то', url: '/link2'},
    {text: 'Ссылка куда-то', url: '/link3'}
];
</script>


<template>
    <div class="h-screen bg-[#141414] text-white">
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
                    <h1 class="text-5xl font-serif text-pink-400">no alone</h1>
                </div>

                <!-- Contact Info -->
                <div class="flex items-center gap-6 text-sm">
                    <a href="https://t.me/no_alone" class="flex items-center gap-2 hover:text-pink-400 transition">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.12.02-1.96 1.25-5.54 3.67-.52.36-.99.53-1.42.52-.47-.01-1.37-.26-2.03-.48-.82-.27-1.47-.42-1.42-.88.03-.24.37-.48 1.02-.73 3.99-1.74 6.66-2.89 8-3.45 3.81-1.58 4.6-1.85 5.12-1.86.11 0 .37.03.53.16.14.11.18.26.2.37.01.08.03.29.01.45z"/>
                        </svg>
                        @no_alone
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
            <main class="flex-1 flex flex-col items-center justify-end mb-[100px] px-8">
                <Link
                    href="/start"
                    class="group bg-[#202020] relative px-[100px] start-button"
                >
                    <span class="text-[92px] imbue tracking-widest text-gray-300 group-hover:text-white transition">
                      START
                    </span>
                </Link>
                <div class="mt-8">
                    <el-space direction="horizontal" :size="30" class="w-full">
                        <Link
                            v-for="(link, index) in links"
                            :key="index"
                            :href="link.url"
                            class="flex-1"
                        >
                            <button class="flex items-center gap-2 bg-[#202020] py-1 px-5 link-button rounded-[4px]">
                                <el-icon>
                                    <ChatDotRound/>
                                </el-icon>
                                <span class="flex-1 text-left">{{ link.text }}</span>
                                <el-icon class="ml-2">
                                    <ArrowRight/>
                                </el-icon>
                            </button>
                        </Link>
                    </el-space>
                </div>

            </main>
        </div>

    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Imbue:opsz,wght@10..100,100..900&display=swap');

.start-button {
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-top: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 4px;
}

.start-button:hover {
    background: #212121;
}

.link-button {
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-top: 1px solid rgba(255, 255, 255, 0.3);
}

.link-button:hover {
    background: #242424;
}

:deep(.link-button-el) {
    background: #202020 !important;
    border: 1px solid rgba(255, 255, 255, 0.16) !important;
    color: #d1d5db !important;
    height: 48px !important;
    padding: 12px 24px !important;
    justify-content: flex-start !important;
    display: flex !important;
}

:deep(.link-button-el:hover) {
    background: #212121 !important;
    color: #ffffff !important;
}

:deep(.link-button-el:hover .el-icon) {
    color: #ec4899 !important;
}

:deep(.link-button-el .el-icon) {
    color: #9ca3af;
    transition: color 0.3s;
}

.imbue {
    font-family: "Imbue", serif;
    font-optical-sizing: auto;
    font-weight: 500;
    font-style: normal;
    letter-spacing: 17%;
}

/* Custom font for the serif look - you can import your preferred font */
.font-serif {
    font-family: 'Playfair Display', serif;
}
</style>
