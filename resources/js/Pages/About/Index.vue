<script setup>
import { Head } from '@inertiajs/vue3';
import SiteHeader from '@/Components/Site/SiteHeader.vue';
import LocaleLoader from '@/Components/LocaleLoader.vue';
import AuthModal from '@/Components/Site/AuthModal.vue';
import { ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import '@fontsource-variable/inter';

const page = usePage();
const showAuthModal = ref(false);

const handleStartClick = () => {
    if (page.props.auth?.user) {
        router.visit(route('profile'));
    } else {
        showAuthModal.value = true;
    }
};
</script>

<template>
    <Head>
        <title>О нас — not alone</title>
        <meta name="description" content="not alone — анонимный чат и платформа для тех, кому не с кем поговорить. Найдите онлайн собеседника, напарника для игр или совместного просмотра фильмов." />
        <meta property="og:title" content="О нас — not alone" />
        <meta property="og:description" content="Бывают моменты, когда очень одиноко и нужен слушатель онлайн. Наша платформа поможет найти понимание и безопасное общение." />
        <meta property="og:type" content="website" />
    </Head>

    <LocaleLoader />

    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="ab-circle ab-circle--1" />
        <div class="ab-circle ab-circle--2" />
        <div class="ab-circle ab-circle--3" />
        <div class="ab-orb ab-orb--pink" />
        <div class="ab-orb ab-orb--cyan" />
    </div>

    <div class="ab-shell">
        <SiteHeader activePage="about" />

        <div class="ab-content">
            <div class="ab-blocks-container">

                <!-- Hero -->
                <div class="ab-hero">
                    <h1 class="ab-title">
                        Никто не должен чувствовать себя одиноким
                    </h1>
                    <p class="ab-text">
                        Иногда каждому нужен человек, который просто выслушает. Здесь вы можете найти собеседника для общения, совместного досуга или дружеской поддержки — <span class="pink-highlight">без осуждения и неловкости</span>.
                    </p>
                </div>

                <!-- 3 Columns Grid -->
                <div class="ab-grid">

                    <!-- Блок 1 -->
                    <div class="ab-glass-block">
                        <h2 class="ab-subtitle">Открытое общение</h2>
                        <p class="ab-text">
                            Общайтесь открыто и без лишнего волнения. Найдите собеседника, который готов выслушать, поддержать разговор или просто составить компанию в любое время.
                        </p>
                    </div>

                    <!-- Блок 2 -->
                    <div class="ab-glass-block">
                        <h2 class="ab-subtitle">Совместный досуг</h2>
                        <p class="ab-text">
                            Не знаете, с кем провести время? Найдите человека со схожими интересами для игр, просмотра фильмов или аниме, рисования, занятий спортом, совместных тренировок и других увлечений.
                        </p>
                    </div>

                    <!-- Блок 3 -->
                    <div class="ab-glass-block">
                        <h2 class="ab-subtitle">Первый шаг</h2>
                        <p class="ab-text">
                            Вам не придется искать повод для разговора — здесь уже есть люди, которые хотят с вами общаться. Выбирайте собеседника по интересам, начинайте диалог и приятно проводите время.
                        </p>
                    </div>

                </div>

                <!-- CTA -->
                <div class="ab-cta">
                    <h3 class="ab-cta-title">Сделайте первый шаг</h3>
                    <button class="ab-cta-btn" @click="handleStartClick">
                        Начать общение
                    </button>
                </div>

                <AuthModal
                    :show="showAuthModal"
                    @close="showAuthModal = false"
                />

            </div>
        </div>
    </div>
</template>

<style scoped>
.ab-shell {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    position: relative;
    z-index: 1;
    background: linear-gradient(180deg, rgba(255, 42, 191, 0.05) 0%, rgba(0, 0, 0, 0.6) 100%) fixed;
    color: rgba(255,255,255,0.85);
    font-family: "Inter Variable", system-ui, -apple-system, sans-serif;
}

/* Декор */
.ab-circle { border-radius: 50%; background: rgba(60,60,190,0.03); box-shadow: inset 0 0 30px rgba(255,255,255,0.015); position: absolute; right: -10vw; top: -10vw; }
.ab-circle--1 { width: clamp(350px, 60vw, 900px); height: clamp(350px, 60vw, 900px); }
.ab-circle--2 { width: clamp(250px, 45vw, 700px); height: clamp(250px, 45vw, 700px); }
.ab-circle--3 { width: clamp(150px, 30vw, 500px); height: clamp(150px, 30vw, 500px); }
@media (max-width: 768px) { .ab-circle { right: 0; top: 0; transform: translate(40%, -40%); } }

.ab-orb { position: absolute; border-radius: 50%; filter: blur(100px); pointer-events: none; }
.ab-orb--pink { width: 500px; height: 500px; background: radial-gradient(circle, rgba(236,72,153,0.08) 0%, transparent 70%); top: -10%; left: -5%; }
.ab-orb--cyan { width: 400px; height: 400px; background: radial-gradient(circle, rgba(34,211,238,0.06) 0%, transparent 70%); bottom: 5%; right: 5%; }

/* Контент */
.ab-content {
    flex: 1;
    display: flex;
    justify-content: center;
    padding: 1rem;
    position: relative;
    z-index: 10;
}

.ab-blocks-container {
    max-width: 1200px;
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Hero */
.ab-hero {
    text-align: center;
    padding: 3rem 2rem;
    background: rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 178, 239, 0.15);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    border-radius: 20px;
}

.ab-title {
    font-family: "Inter Variable", system-ui, -apple-system, sans-serif;
    font-size: clamp(1.8rem, 3.5vw, 3rem);
    font-weight: 600;
    line-height: 1.15;
    margin-bottom: 1rem;
    background: linear-gradient(90deg, #ffb2ef, #ec4899);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* 3 Columns Grid */
.ab-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}

.ab-glass-block {
    background: rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.05);
    border-radius: 20px;
    padding: 1.5rem 1.8rem;
    display: flex;
    flex-direction: column;
}

.ab-subtitle {
    font-family: "Inter Variable", system-ui, -apple-system, sans-serif;
    font-size: clamp(1.1rem, 1.8vw, 1.3rem);
    font-weight: 600;
    color: rgba(255,255,255,0.95);
    margin: 0 0 0.8rem 0;
    line-height: 1.2;
}

.ab-text {
    font-size: 0.95rem;
    line-height: 1.6;
    color: rgba(255,255,255,0.7);
    margin: 0;
}

.pink-highlight {
    color: #ffb2ef;
    font-weight: 500;
    text-shadow: 0 0 10px rgba(255, 178, 239, 0.4);
}

/* CTA */
.ab-cta {
    background: rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.2rem;
}

.ab-cta-title {
    font-family: "Inter Variable", system-ui, -apple-system, sans-serif;
    font-size: 1.3rem;
    font-weight: 600;
    color: rgba(255,255,255,0.95);
    margin: 0;
}

.ab-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.6rem 1.4rem;
    background: rgba(255, 178, 239, 0.15);
    border: 1px solid rgba(255, 178, 239, 0.3);
    box-shadow: inset 0 1px 0 rgba(255, 178, 239, 0.4);
    border-radius: 8px;
    color: #ffb2ef;
    font-weight: 400;
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 1rem;
}
.ab-btn:hover {
    background: rgba(255, 178, 239, 0.25);
    border-color: rgba(255, 178, 239, 0.4);
    color: #fff;
}

/* ab-cta-btn - розовая стеклянная кнопка */
.ab-cta-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.7rem 2rem;
    background: rgba(255, 178, 239, 0.1);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 178, 239, 0.3);
    border-radius: 10px;
    color: #ffb2ef;
    font-family: "Inter Variable", system-ui, -apple-system, sans-serif;
    font-weight: 600;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.ab-cta-btn:hover {
    background: rgba(255, 178, 239, 0.15);
    border-color: rgba(255, 178, 239, 0.5);
    box-shadow: 0 6px 25px rgba(0, 0, 0, 0.25);
}

.ab-cta-btn:active {
    transform: scale(0.97);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

@media (max-width: 900px) {
    .ab-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .ab-grid {
        grid-template-columns: 1fr;
    }
    .ab-content {
        padding: 1.5rem 1rem 3rem;
    }
    .ab-glass-block, .ab-hero, .ab-cta {
        padding: 1.5rem;
    }
}
</style>
