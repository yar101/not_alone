<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SiteHeader from '@/Components/Site/SiteHeader.vue';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

const props = defineProps({
    item: Object,
});

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' });
}

const heroRef    = ref(null);
const heroVisible = ref(true);
let   heroObserver = null;

onMounted(() => {
    heroObserver = new IntersectionObserver(
        ([entry]) => { heroVisible.value = entry.isIntersecting; },
        { threshold: 0.05 }
    );
    if (heroRef.value) heroObserver.observe(heroRef.value);
});

onUnmounted(() => heroObserver?.disconnect());
</script>

<template>
    <Head>
        <title>{{ item.title }} — no alone</title>
        <meta property="og:title"   :content="item.title" />
        <meta property="og:type"    content="article" />
        <meta v-if="item.image" property="og:image" :content="item.image" />
    </Head>

    <!-- Декор -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="sh-circle sh-circle--1"/><div class="sh-circle sh-circle--2"/><div class="sh-circle sh-circle--3"/>
        <div class="sh-orb sh-orb--pink"/><div class="sh-orb sh-orb--cyan"/>
    </div>

    <div class="sh-shell">

        <SiteHeader activePage="news" />

        <!-- Плавающая кнопка назад (появляется когда герой уходит из вьюпорта) -->
        <Transition name="float-back">
            <Link v-if="!heroVisible" :href="route('news')" class="sh-float-back">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 5l-7 7 7 7"/>
                </svg>
                <span>{{ __('news.back') }}</span>
            </Link>
        </Transition>

        <!-- Скролл -->
        <div class="sh-content">
            <div class="sh-scroll">

                <div class="sh-wrap">

                    <!-- Кнопка назад (статичная, в потоке) -->
                    <Link :href="route('news')" class="sh-back">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 12H5M12 5l-7 7 7 7"/>
                        </svg>
                        <span>{{ __('news.back') }}</span>
                    </Link>

                    <!-- Герой -->
                    <div ref="heroRef" class="sh-hero" :class="{ 'sh-hero--no-img': !item.image }">

                        <!-- Фоновое изображение -->
                        <template v-if="item.image">
                            <img :src="item.image" :alt="item.title" class="sh-hero__img" />
                            <div class="sh-hero__overlay"/>
                            <div class="sh-hero__meta">
                                <h1 class="sh-hero__title">{{ item.title }}</h1>
                            </div>
                        </template>

                        <!-- Без фото — текстовый герой -->
                        <template v-else>
                            <div class="sh-hero__no-img-bg"/>
                            <div class="sh-hero__meta sh-hero__meta--center">
                                <h1 class="sh-hero__title sh-hero__title--gradient">{{ item.title }}</h1>
                            </div>
                        </template>

                    </div>

                    <!-- Мета-строка под героем -->
                    <div class="sh-meta-bar">
                        <span class="sh-meta__ornament">◈</span>
                        <time class="sh-meta__date">{{ formatDate(item.published_at) }}</time>
                    </div>

                    <!-- Тело статьи -->
                    <div class="sh-body">
                        <div class="sh-divider">
                            <span class="sh-divider__line"/>
                            <span class="sh-divider__dot"/>
                            <span class="sh-divider__line"/>
                        </div>
                        <p class="sh-text">{{ item.body }}</p>

                    </div>

                </div>

            </div>
        </div>

    </div>
</template>

<style scoped>
/* ── Shell ──────────────────────────────────────────────────── */
.sh-shell {
    width: 100%; height: 100dvh;
    display: flex; flex-direction: column; overflow: hidden;
    position: relative; z-index: 1;
    background: linear-gradient(180deg, rgba(255,42,191,0.07) 0%, rgba(0,0,0,0.65) 100%) fixed;
}

/* ── Декор ──────────────────────────────────────────────────── */
.sh-circle { border-radius: 50%; background: rgba(60,60,190,0.03); box-shadow: inset 0 0 30px rgba(255,255,255,0.015); position: absolute; right: -12%; top: -8%; }
.sh-circle--1 { width: 900px; height: 900px; }
.sh-circle--2 { width: 700px; height: 700px; }
.sh-circle--3 { width: 500px; height: 500px; }
.sh-orb { position: absolute; border-radius: 50%; filter: blur(100px); pointer-events: none; }
.sh-orb--pink { width: 600px; height: 600px; background: radial-gradient(circle, rgba(236,72,153,0.14) 0%, transparent 70%); top: -15%; left: -8%; }
.sh-orb--cyan { width: 450px; height: 450px; background: radial-gradient(circle, rgba(34,211,238,0.09) 0%, transparent 70%); bottom: 5%; right: 5%; }

/* ── Content / scroll ────────────────────────────────────────── */
.sh-content { flex: 1; min-height: 0; position: relative; }
.sh-scroll  { position: absolute; inset: 0; overflow-y: auto; }

/* ── Wrapper ─────────────────────────────────────────────────── */
.sh-wrap {
    max-width: 1100px;
    margin: 0 auto;
    padding: 1.25rem 3rem 5rem;
}

/* ── Entrance animations ─────────────────────────────────────── */
@keyframes ns-fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes ns-fade-up { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

.sh-back     { animation: ns-fade-in 0.18s ease-out both; }
.sh-hero     { animation: ns-fade-in 0.28s ease-out 0.13s both; }
.sh-meta-bar { animation: ns-fade-up  0.2s  ease-out 0.21s both; }
.sh-body     { animation: ns-fade-in 0.22s ease-out 0.27s both; }

/* ── Back ────────────────────────────────────────────────────── */
.sh-back {
    display: inline-flex; align-items: center; gap: 0.55rem;
    font-family: "Figtree", sans-serif;
    font-size: 0.78rem; letter-spacing: 0.06em; text-transform: uppercase;
    color: rgba(255,255,255,0.3); text-decoration: none;
    margin-bottom: 1.5rem;
    transition: color 0.2s;
}
.sh-back:hover { color: rgba(190,145,255,0.85); }
.sh-back svg { transition: transform 0.2s; }
.sh-back:hover svg { transform: translateX(-3px); }

/* ── Floating back button ────────────────────────────────────── */
.sh-float-back {
    position: fixed;
    top: 1.35rem;
    left: max(1rem, calc(50% - 590px));
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.45rem 0.9rem 0.45rem 0.7rem;
    background: rgba(12, 10, 28, 0.72);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(190,145,255,0.22);
    border-radius: 4px;
    color: rgba(190,145,255,0.75);
    text-decoration: none;
    font-family: "Figtree", sans-serif;
    font-size: 0.78rem; letter-spacing: 0.06em; text-transform: uppercase;
    z-index: 100;
    transition: color 0.18s, border-color 0.18s, background 0.18s, box-shadow 0.18s;
    box-shadow: 0 4px 20px rgba(0,0,0,0.35);
}
.sh-float-back:hover {
    color: rgba(190,145,255,1);
    border-color: rgba(190,145,255,0.5);
    background: rgba(20, 15, 45, 0.85);
    box-shadow: 0 4px 24px rgba(190,145,255,0.12);
}
.sh-float-back svg { transition: transform 0.18s; flex-shrink: 0; }
.sh-float-back:hover svg { transform: translateX(-3px); }

.float-back-enter-active, .float-back-leave-active { transition: opacity 0.25s, transform 0.25s; }
.float-back-enter-from, .float-back-leave-to { opacity: 0; transform: translateY(-6px); }

@media (max-width: 900px) { .sh-float-back { display: none; } }

/* ── Hero ────────────────────────────────────────────────────── */
.sh-hero {
    position: relative;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0;
}

/* С изображением */
.sh-hero__img {
    width: 100%; height: 480px;
    object-fit: cover; display: block;
    transform-origin: center;
}

.sh-hero__overlay {
    position: absolute; inset: 0;
    background: linear-gradient(
        to top,
        rgba(0,0,0,0.88) 0%,
        rgba(0,0,0,0.45) 40%,
        rgba(0,0,0,0.1)  70%,
        transparent      100%
    );
}

/* Псевдо-зернистость поверх фото */
.sh-hero::after {
    content: '';
    position: absolute; inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
    background-size: 200px 200px;
    opacity: 0.35;
    pointer-events: none;
    mix-blend-mode: overlay;
    border-radius: inherit;
}

/* ── Meta-bar (под героем) ───────────────────────────────────── */
.sh-meta-bar {
    display: flex; align-items: center; gap: 0.75rem;
    margin: 1.5rem 0 0; padding: 0 0.5rem;
}
.sh-meta__ornament {
    font-size: 0.65rem; color: rgba(190,145,255,0.45); line-height: 1;
}
.sh-meta__date {
    font-family: "Figtree", sans-serif;
    font-size: 0.82rem; color: rgba(255,255,255,0.42);
    letter-spacing: 0.1em; text-transform: uppercase;
}

.sh-hero__meta {
    position: absolute; bottom: 0; left: 0; right: 0;
    padding: 2.5rem 3rem;
    display: flex; flex-direction: column; gap: 0.65rem;
}

/* Без изображения */
.sh-hero--no-img {
    min-height: 280px;
    display: flex; align-items: center; justify-content: center;
    border: 1px solid rgba(255,255,255,0.06);
    background: rgba(255,255,255,0.015);
}

.sh-hero__no-img-bg {
    position: absolute; inset: 0;
    background:
        radial-gradient(ellipse 60% 80% at 20% 50%, rgba(190,145,255,0.08) 0%, transparent 70%),
        radial-gradient(ellipse 50% 70% at 80% 50%, rgba(236,72,153,0.06) 0%, transparent 70%);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
}

.sh-hero__meta--center {
    position: relative;
    padding: 3.5rem 3rem;
    align-items: center;
    text-align: center;
}

/* Заголовок в герое */
.sh-hero__title {
    font-family: "Brygada 1918", serif;
    font-size: clamp(1.9rem, 4.5vw, 3.4rem);
    font-weight: 400; line-height: 1.1;
    color: rgba(255,255,255,0.95);
    margin: 0;
    text-shadow: 0 2px 30px rgba(0,0,0,0.5);
    max-width: 820px;
}

.sh-hero__title--gradient {
    color: rgba(255,255,255,0.95);
    text-shadow: none;
}

/* ── Divider ─────────────────────────────────────────────────── */
.sh-divider {
    display: flex; align-items: center; gap: 0.85rem;
    margin: 2.2rem 0 2rem;
}
.sh-divider__line {
    flex: 1; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(190,145,255,0.3), rgba(112,112,216,0.15), transparent);
}
.sh-divider__dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: rgba(190,145,255,0.5);
    box-shadow: 0 0 8px rgba(190,145,255,0.4);
    flex-shrink: 0;
}

/* ── Body ────────────────────────────────────────────────────── */
.sh-body { padding: 0 0.5rem; }

.sh-text {
    font-family: "Brygada 1918", serif;
    font-size: clamp(1rem, 1.4vw, 1.12rem);
    color: rgba(255,255,255,0.62);
    line-height: 1.9;
    margin: 0;
    white-space: pre-line;
    max-width: 780px;
}


/* ── Responsive ─────────────────────────────────────────────── */
@media (max-width: 900px) {
    .sh-wrap { padding: 1rem 2rem 4rem; }
    .sh-hero__img { height: 360px; }
    .sh-hero__title { font-size: clamp(1.6rem, 5vw, 2.4rem); }
    .sh-hero__meta { padding: 2rem 2rem; }
}
@media (max-width: 640px) {
    .sh-topbar { padding: 0.75rem 1.25rem 0; }
    .sh-wrap { padding: 0.75rem 1.1rem 3rem; }
    .sh-hero__img { height: 240px; }
    .sh-hero__title { font-size: 1.5rem; }
    .sh-hero__meta { padding: 1.25rem 1.25rem; }
    .sh-hero--no-img { min-height: 200px; }
    .sh-hero__meta--center { padding: 2rem 1.25rem; }
    .sh-text { font-size: 0.95rem; }
    .sh-meta-bar { margin: 1rem 0 0; flex-wrap: wrap; }
}
</style>
