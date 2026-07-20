<script setup>
import { Head } from "@inertiajs/vue3";
import SiteHeader from "@/Components/Site/SiteHeader.vue";
import LocaleLoader from "@/Components/LocaleLoader.vue";

const contacts = [
    {
        title: "Telegram",
        value: "@no_alone",
        link: "https://t.me/no_alone",
        icon: "M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.12.02-1.96 1.25-5.54 3.67-.52.36-.99.53-1.42.52-.47-.01-1.37-.26-2.03-.48-.82-.27-1.47-.42-1.42-.88.03-.24.37-.48 1.02-.73 3.99-1.74 6.66-2.89 8-3.45 3.81-1.58 4.6-1.85 5.12-1.86.11 0 .37.03.53.16.14.11.18.26.2.37.01.08.03.29.01.45z",
        color: "#24A1DE",
    },
    {
        title: "Email",
        value: "adm@na.ru",
        link: "mailto:adm@na.ru",
        icon: "M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z",
        color: "#ffb2ef",
    },
];
</script>

<template>
    <Head title="Контакты — not alone" />

    <LocaleLoader />

    <!-- Декоративные фоновые элементы (круги) -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="circle1" />
        <div class="circle2" />
        <div class="circle3" />
    </div>

    <!-- Основной контейнер с градиентным фоном -->
    <div
        class="min-h-screen text-white overflow-x-hidden main-gradient relative z-10 flex flex-col"
    >
        <SiteHeader activePage="contacts" />

        <main class="flex-1 flex flex-col items-center justify-center px-6">
            <div class="w-full max-w-[500px] flex flex-col gap-3">
                <a
                    v-for="c in contacts"
                    :key="c.title"
                    :href="c.link"
                    target="_blank"
                    class="minimal-link group"
                >
                    <div class="minimal-link__icon" :style="{ color: c.color }">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path :d="c.icon" />
                        </svg>
                    </div>
                    <div class="minimal-link__content">
                        <span class="minimal-link__value">{{ c.value }}</span>
                        <span class="minimal-link__label">{{ c.title }}</span>
                    </div>
                </a>
            </div>

            <!-- Балансировочный отступ снизу -->
            <div class="h-32"></div>
        </main>
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

.minimal-link {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 1.1rem 1.5rem;
    background: rgba(10, 7, 20, 0.7);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.14), 0 4px 20px rgba(0, 0, 0, 0.3);
    border-radius: 12px;
    transition: all 0.3s ease;
    text-decoration: none;
}

.minimal-link:hover {
    background: rgba(15, 12, 25, 0.85);
    border-color: rgba(255, 178, 239, 0.3);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 6px 24px rgba(0, 0, 0, 0.4);
    transform: translateX(4px);
}

.minimal-link__icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    opacity: 0.7;
    transition: opacity 0.3s ease;
}

.minimal-link:hover .minimal-link__icon {
    opacity: 1;
}

.minimal-link__icon svg {
    width: 100%;
    height: 100%;
}

.minimal-link__content {
    display: flex;
    flex-direction: column;
}

.minimal-link__value {
    font-family: "Courier New", Courier, monospace;
    font-size: 1.25rem;
    color: rgba(255, 255, 255, 0.85);
    line-height: 1.2;
}

.minimal-link__label {
    font-size: 0.65rem;
    color: rgba(255, 255, 255, 0.25);
    text-transform: uppercase;
    letter-spacing: 0.2em;
    margin-top: 0.15rem;
}

@media (max-width: 640px) {
    .minimal-link {
        padding: 1rem 1.25rem;
    }
    .minimal-link__value {
        font-size: 1.1rem;
    }
}
</style>
