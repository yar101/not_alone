<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SiteHeader from '@/Components/Site/SiteHeader.vue';
import LocaleLoader from '@/Components/LocaleLoader.vue';
import '@fontsource-variable/inter';
import '@fontsource-variable/brygada-1918';
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
                        Наш проект создан для тех, кому не с кем поговорить. Если вы задаетесь вопросом: «почему я одна/один?», вы попали в правильное место. Найдите собеседника и искреннюю поддержку прямо сейчас.
                    </p>
                </div>

                <!-- 3 Columns Grid -->
                <div class="ab-grid">
                    
                    <!-- Блок 1 -->
                    <div class="ab-glass-block">
                        <div class="ab-card-header">
                            <div class="ab-icon-wrap" style="color: #ffb2ef;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            </div>
                            <h2 class="ab-subtitle">Безопасное общение</h2>
                        </div>
                        <p class="ab-text">
                            Наш анонимный чат позволяет безопасно делиться мыслями. Вас ждет слушатель онлайн, готовый проявить эмпатию. Узнайте, как справиться с одиночеством и найти приятного парня или девушку для общения.
                        </p>
                    </div>

                    <!-- Блок 2 -->
                    <div class="ab-glass-block">
                        <div class="ab-card-header">
                            <div class="ab-icon-wrap" style="color: #67e8f9;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                            </div>
                            <h2 class="ab-subtitle">Совместный досуг</h2>
                        </div>
                        <p class="ab-text">
                            Не знаете, с кем поиграть? Найдите тиммейта для игр! Мы предлагаем совместный просмотр фильмов или аниме, рисование и изучение иностранных языков. Разделите свои увлечения.
                        </p>
                    </div>

                    <!-- Блок 3 -->
                    <div class="ab-glass-block">
                        <div class="ab-card-header">
                            <div class="ab-icon-wrap" style="color: #86efac;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            </div>
                            <h2 class="ab-subtitle">Первый шаг</h2>
                        </div>
                        <p class="ab-text">
                            Волнуетесь о том, как начать диалог или о чем переписываться? Платформа стирает эти барьеры. Узнайте, как познакомиться в интернете и обрести друзей онлайн без малейшего осуждения.
                        </p>
                    </div>

                </div>

                <!-- CTA -->
                <div class="ab-cta">
                    <h3 class="ab-cta-title">Готовы присоединиться?</h3>
                    <Link :href="route('register')" class="ab-btn">
                        Начать общение
                    </Link>
                </div>

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
    font-family: "Brygada 1918", serif;
    font-size: clamp(1.8rem, 3.5vw, 3rem);
    font-weight: 400;
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

.ab-card-header {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-bottom: 0.8rem;
}

.ab-icon-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem;
    border-radius: 10px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    flex-shrink: 0;
}

.ab-subtitle {
    font-family: "Brygada 1918", serif;
    font-size: clamp(1.1rem, 1.8vw, 1.3rem);
    font-weight: 400;
    color: rgba(255,255,255,0.95);
    margin: 0;
    line-height: 1.2;
}

.ab-text {
    font-size: 0.95rem;
    line-height: 1.6;
    color: rgba(255,255,255,0.7);
    margin: 0;
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
    font-family: "Brygada 1918", serif;
    font-size: 1.5rem;
    font-weight: 400;
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
