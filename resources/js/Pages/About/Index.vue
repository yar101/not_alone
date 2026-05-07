<script setup>
import { ref, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';
import SiteHeader from '@/Components/Site/SiteHeader.vue';

// ── Фичи ──────────────────────────────────────────────────────
const features = [
    {
        icon: '◈', color: '#ffb2ef', title: 'Профили',
        desc: 'Черты характера, интересы и голосовая визитка',
        detail: 'Заполни профиль с чертами характера, интересами, голосовой визиткой и фото. Другие пользователи поймут, близок ли ты им по духу — ещё до первого сообщения.',
        points: [
            { icon: '◇', text: 'Выбери черты характера из общего списка' },
            { icon: '◇', text: 'Добавь интересы и голосовую визитку' },
            { icon: '◇', text: 'Покажи себя в лучшем свете' },
        ],
        illus: 'profile',
    },
    {
        icon: '⊹', color: '#67e8f9', title: 'Поиск',
        desc: 'Умная фильтрация по интересам, возрасту, полу',
        detail: 'Ищи людей по интересам, возрасту, полу и типу профиля. Умные фильтры помогают быстро найти именно того, кто тебе нужен.',
        points: [
            { icon: '◇', text: 'Фильтрация по возрасту и полу' },
            { icon: '◇', text: 'Поиск по совпадающим интересам' },
            { icon: '◇', text: 'Отдельный поиск айдолов и пользователей' },
        ],
        illus: 'search',
    },
    {
        icon: '✦', color: '#f9a8d4', title: 'Услуги',
        desc: 'Айдолы предлагают своё время и таланты',
        detail: 'Айдолы — верифицированные пользователи, которые предлагают своё время: игры, прогулки, разговоры, обучение. Выбирай по рейтингу и категории.',
        points: [
            { icon: '◇', text: 'Категории: общение, игры, прогулки и др.' },
            { icon: '◇', text: 'Прозрачное ценообразование' },
            { icon: '◇', text: 'Рейтинг и отзывы на каждого айдола' },
        ],
        illus: 'services',
    },
    {
        icon: '◎', color: '#86efac', title: 'Чат',
        desc: 'Личные сообщения в реальном времени',
        detail: 'Пиши напрямую любому пользователю. Сообщения доставляются мгновенно. Удобная история переписки и возможность заблокировать нежелательные контакты.',
        points: [
            { icon: '◇', text: 'Сообщения в реальном времени' },
            { icon: '◇', text: 'Индикатор прочтения' },
            { icon: '◇', text: 'Блокировки и жалобы на контакты' },
        ],
        illus: 'chat',
    },
    {
        icon: '❋', color: '#fcd34d', title: 'Рейтинг',
        desc: 'Система репутации до 100 баллов',
        detail: 'Рейтинг айдола формируется на основе отзывов, активности и выполненных заказов — максимум 100 баллов. Чем выше рейтинг, тем заметнее в поиске.',
        points: [
            { icon: '◇', text: 'Рейтинг от 0 до 100 баллов' },
            { icon: '◇', text: 'Влияет на позицию в поиске' },
            { icon: '◇', text: 'Учитывает отзывы и активность' },
        ],
        illus: 'rating',
    },
    {
        icon: '◉', color: '#a5b4fc', title: 'Безопасность',
        desc: 'Верификация, жалобы и блокировки',
        detail: 'Верификация аккаунтов, система жалоб и блокировок — мы следим за комфортом каждого. Если что-то идёт не так — сообщи нам, и мы разберёмся.',
        points: [
            { icon: '◇', text: 'Верификация профилей айдолов' },
            { icon: '◇', text: 'Система жалоб на нарушителей' },
            { icon: '◇', text: 'Блокировки и ограничения в чате' },
        ],
        illus: 'security',
    },
];

// ── Split-view ─────────────────────────────────────────────────
const selectedFeature = ref(features[0]);
const leftColRef      = ref(null);
const detailColRef    = ref(null);

function openFeature(f) {
    if (selectedFeature.value?.title === f.title) return;
    if (detailColRef.value) detailColRef.value.style.opacity = '0';
    selectedFeature.value = f;
    nextTick(() => {
        if (detailColRef.value) detailColRef.value.style.opacity = '';
    });
}
</script>

<template>
    <Head>
        <title>О проекте — no alone</title>
        <meta name="description" content="no alone — платформа для поиска компании и общения. Узнайте о нашем проекте, его возможностях и последних новостях." />
        <meta property="og:title" content="О проекте — no alone" />
        <meta property="og:description" content="no alone — платформа для поиска компании и общения. Никто не должен чувствовать себя в одиночестве." />
        <meta property="og:type" content="website" />
    </Head>

    <!-- Декор -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="ab-circle ab-circle--1" /><div class="ab-circle ab-circle--2" /><div class="ab-circle ab-circle--3" />
        <div class="ab-orb ab-orb--pink" /><div class="ab-orb ab-orb--cyan" />
    </div>

    <div class="ab-shell">

        <SiteHeader activePage="about" />

        <!-- Контент -->
        <div class="ab-content">

            <div class="ab-about-inner ab-about-inner--split">

                    <!-- Левая колонка -->
                    <div ref="leftColRef" class="ab-left-col">
                        <div class="ab-hero">
                            <h1 class="ab-hero-title">
                                Никто не должен<br>
                                <em>чувствовать себя одиноким</em>
                            </h1>
                            <p class="ab-hero-desc">
                                no alone — платформа, где люди находят собеседников, компанию и живое общение.
                                Айдолы предлагают своё время, ты выбираешь тех, кто близок по духу.
                            </p>
                        </div>

                        <div class="ab-features">
                            <div
                                v-for="f in features"
                                :key="f.title"
                                class="ab-feature-card"
                                :class="{ 'ab-feature-card--active': selectedFeature?.title === f.title }"
                                :style="{ '--accent': f.color }"
                                @click="openFeature(f)"
                            >
                                <span class="ab-feature-card__icon">{{ f.icon }}</span>
                                <div class="ab-feature-card__body">
                                    <div class="ab-feature-card__title">{{ f.title }}</div>
                                    <div class="ab-feature-card__desc">{{ f.desc }}</div>
                                </div>
                                <span class="ab-feature-card__arrow">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M7 7h10v10"/></svg>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Правая панель деталей -->
                    <div ref="detailColRef" class="ab-detail-col" :style="{ '--accent': selectedFeature.color }">

                        <!-- SVG иллюстрация -->
                        <div class="ab-detail-illus">

                            <!-- Профили -->
                            <svg v-if="selectedFeature.illus === 'profile'" viewBox="0 0 200 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="100" cy="58" r="28" stroke="v-bind(selectedFeature.color)" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.4"/>
                                <circle cx="100" cy="52" r="18" fill="rgba(190,145,255,0.12)" stroke="rgba(190,145,255,0.5)" stroke-width="1.2"/>
                                <circle cx="100" cy="46" r="8" fill="rgba(190,145,255,0.25)"/>
                                <path d="M76 88 Q100 76 124 88" stroke="rgba(190,145,255,0.5)" stroke-width="1.2" stroke-linecap="round"/>
                                <rect x="28" y="30" width="30" height="14" rx="7" fill="rgba(190,145,255,0.12)" stroke="rgba(190,145,255,0.35)" stroke-width="1"/>
                                <rect x="32" y="36" width="22" height="3" rx="1.5" fill="rgba(190,145,255,0.5)"/>
                                <rect x="142" y="24" width="32" height="14" rx="7" fill="rgba(190,145,255,0.12)" stroke="rgba(190,145,255,0.35)" stroke-width="1"/>
                                <rect x="146" y="30" width="24" height="3" rx="1.5" fill="rgba(190,145,255,0.5)"/>
                                <rect x="20" y="62" width="34" height="14" rx="7" fill="rgba(190,145,255,0.08)" stroke="rgba(190,145,255,0.25)" stroke-width="1"/>
                                <rect x="24" y="68" width="26" height="3" rx="1.5" fill="rgba(190,145,255,0.35)"/>
                                <rect x="148" y="58" width="30" height="14" rx="7" fill="rgba(190,145,255,0.08)" stroke="rgba(190,145,255,0.25)" stroke-width="1"/>
                                <rect x="152" y="64" width="22" height="3" rx="1.5" fill="rgba(190,145,255,0.35)"/>
                                <line x1="58" y1="37" x2="82" y2="47" stroke="rgba(190,145,255,0.2)" stroke-width="0.8" stroke-dasharray="2 2"/>
                                <line x1="142" y1="31" x2="118" y2="44" stroke="rgba(190,145,255,0.2)" stroke-width="0.8" stroke-dasharray="2 2"/>
                                <line x1="54" y1="69" x2="82" y2="60" stroke="rgba(190,145,255,0.15)" stroke-width="0.8" stroke-dasharray="2 2"/>
                                <line x1="148" y1="65" x2="118" y2="56" stroke="rgba(190,145,255,0.15)" stroke-width="0.8" stroke-dasharray="2 2"/>
                            </svg>

                            <!-- Поиск -->
                            <svg v-if="selectedFeature.illus === 'search'" viewBox="0 0 200 160" fill="none">
                                <rect x="20" y="20" width="50" height="50" rx="8" fill="rgba(103,232,249,0.06)" stroke="rgba(103,232,249,0.25)" stroke-width="1"/>
                                <circle cx="45" cy="38" r="8" fill="rgba(103,232,249,0.15)"/>
                                <rect x="30" y="50" width="30" height="4" rx="2" fill="rgba(103,232,249,0.2)"/>
                                <rect x="30" y="58" width="20" height="3" rx="1.5" fill="rgba(103,232,249,0.12)"/>
                                <rect x="75" y="20" width="50" height="50" rx="8" fill="rgba(103,232,249,0.06)" stroke="rgba(103,232,249,0.25)" stroke-width="1"/>
                                <circle cx="100" cy="38" r="8" fill="rgba(103,232,249,0.15)"/>
                                <rect x="85" y="50" width="30" height="4" rx="2" fill="rgba(103,232,249,0.2)"/>
                                <rect x="85" y="58" width="20" height="3" rx="1.5" fill="rgba(103,232,249,0.12)"/>
                                <rect x="130" y="20" width="50" height="50" rx="8" fill="rgba(103,232,249,0.06)" stroke="rgba(103,232,249,0.25)" stroke-width="1"/>
                                <circle cx="155" cy="38" r="8" fill="rgba(103,232,249,0.15)"/>
                                <rect x="140" y="50" width="30" height="4" rx="2" fill="rgba(103,232,249,0.2)"/>
                                <rect x="140" y="58" width="20" height="3" rx="1.5" fill="rgba(103,232,249,0.12)"/>
                                <!-- Highlight on center -->
                                <rect x="71" y="16" width="58" height="58" rx="11" stroke="rgba(103,232,249,0.6)" stroke-width="1.5" stroke-dasharray="3 2"/>
                                <!-- Magnifier -->
                                <circle cx="152" cy="118" r="18" stroke="rgba(103,232,249,0.5)" stroke-width="1.5" fill="rgba(103,232,249,0.06)"/>
                                <line x1="164" y1="130" x2="178" y2="144" stroke="rgba(103,232,249,0.5)" stroke-width="2" stroke-linecap="round"/>
                                <circle cx="152" cy="118" r="10" fill="rgba(103,232,249,0.1)"/>
                                <!-- Filter dots -->
                                <circle cx="30" cy="100" r="3" fill="rgba(103,232,249,0.4)"/>
                                <rect x="38" y="98" width="40" height="4" rx="2" fill="rgba(103,232,249,0.2)"/>
                                <circle cx="30" cy="114" r="3" fill="rgba(103,232,249,0.2)"/>
                                <rect x="38" y="112" width="28" height="4" rx="2" fill="rgba(103,232,249,0.12)"/>
                            </svg>

                            <!-- Услуги -->
                            <svg v-if="selectedFeature.illus === 'services'" viewBox="0 0 200 160" fill="none">
                                <rect x="14" y="40" width="54" height="80" rx="10" fill="rgba(249,168,212,0.06)" stroke="rgba(249,168,212,0.2)" stroke-width="1"/>
                                <rect x="14" y="40" width="54" height="18" rx="10" fill="rgba(249,168,212,0.1)"/>
                                <rect x="22" y="66" width="38" height="4" rx="2" fill="rgba(249,168,212,0.25)"/>
                                <rect x="22" y="75" width="28" height="3" rx="1.5" fill="rgba(249,168,212,0.15)"/>
                                <rect x="22" y="84" width="32" height="3" rx="1.5" fill="rgba(249,168,212,0.15)"/>
                                <rect x="22" y="98" width="38" height="12" rx="5" fill="rgba(249,168,212,0.15)" stroke="rgba(249,168,212,0.3)" stroke-width="0.8"/>
                                <rect x="28" y="103" width="26" height="2.5" rx="1.25" fill="rgba(249,168,212,0.55)"/>
                                <!-- Center card (highlighted) -->
                                <rect x="73" y="28" width="54" height="86" rx="10" fill="rgba(249,168,212,0.1)" stroke="rgba(249,168,212,0.5)" stroke-width="1.2"/>
                                <rect x="73" y="28" width="54" height="20" rx="10" fill="rgba(249,168,212,0.18)"/>
                                <circle cx="100" cy="38" r="6" fill="rgba(249,168,212,0.3)"/>
                                <rect x="81" y="56" width="38" height="4" rx="2" fill="rgba(249,168,212,0.35)"/>
                                <rect x="81" y="65" width="28" height="3" rx="1.5" fill="rgba(249,168,212,0.2)"/>
                                <rect x="81" y="74" width="32" height="3" rx="1.5" fill="rgba(249,168,212,0.2)"/>
                                <!-- Stars (paths) -->
                                <path d="M84 84 l1.5 3 3.2 0.5-2.3 2.2 0.5 3.2-2.9-1.5-2.9 1.5 0.5-3.2-2.3-2.2 3.2-0.5Z" fill="rgba(249,168,212,0.6)"/>
                                <path d="M94 84 l1.5 3 3.2 0.5-2.3 2.2 0.5 3.2-2.9-1.5-2.9 1.5 0.5-3.2-2.3-2.2 3.2-0.5Z" fill="rgba(249,168,212,0.6)"/>
                                <path d="M104 84 l1.5 3 3.2 0.5-2.3 2.2 0.5 3.2-2.9-1.5-2.9 1.5 0.5-3.2-2.3-2.2 3.2-0.5Z" fill="rgba(249,168,212,0.6)"/>
                                <path d="M114 84 l1.5 3 3.2 0.5-2.3 2.2 0.5 3.2-2.9-1.5-2.9 1.5 0.5-3.2-2.3-2.2 3.2-0.5Z" fill="rgba(249,168,212,0.35)"/>
                                <rect x="81" y="100" width="38" height="12" rx="5" fill="rgba(249,168,212,0.22)" stroke="rgba(249,168,212,0.5)" stroke-width="0.8"/>
                                <rect x="87" y="105" width="26" height="2.5" rx="1.25" fill="rgba(249,168,212,0.8)"/>
                                <!-- Right card -->
                                <rect x="132" y="40" width="54" height="80" rx="10" fill="rgba(249,168,212,0.06)" stroke="rgba(249,168,212,0.2)" stroke-width="1"/>
                                <rect x="132" y="40" width="54" height="18" rx="10" fill="rgba(249,168,212,0.1)"/>
                                <rect x="140" y="66" width="38" height="4" rx="2" fill="rgba(249,168,212,0.25)"/>
                                <rect x="140" y="75" width="28" height="3" rx="1.5" fill="rgba(249,168,212,0.15)"/>
                                <rect x="140" y="84" width="32" height="3" rx="1.5" fill="rgba(249,168,212,0.15)"/>
                                <rect x="140" y="98" width="38" height="12" rx="5" fill="rgba(249,168,212,0.15)" stroke="rgba(249,168,212,0.3)" stroke-width="0.8"/>
                                <rect x="146" y="103" width="26" height="2.5" rx="1.25" fill="rgba(249,168,212,0.55)"/>
                            </svg>

                            <!-- Чат -->
                            <svg v-if="selectedFeature.illus === 'chat'" viewBox="0 0 200 160" fill="none">
                                <rect x="18" y="20" width="110" height="40" rx="12" fill="rgba(134,239,172,0.1)" stroke="rgba(134,239,172,0.35)" stroke-width="1.2"/>
                                <circle cx="38" cy="40" r="10" fill="rgba(134,239,172,0.2)"/>
                                <rect x="54" y="32" width="60" height="5" rx="2.5" fill="rgba(134,239,172,0.3)"/>
                                <rect x="54" y="42" width="42" height="4" rx="2" fill="rgba(134,239,172,0.18)"/>
                                <!-- Right bubble -->
                                <rect x="72" y="78" width="110" height="38" rx="12" fill="rgba(134,239,172,0.06)" stroke="rgba(134,239,172,0.25)" stroke-width="1"/>
                                <rect x="84" y="88" width="64" height="5" rx="2.5" fill="rgba(134,239,172,0.2)"/>
                                <rect x="84" y="98" width="46" height="4" rx="2" fill="rgba(134,239,172,0.12)"/>
                                <circle cx="162" cy="97" r="10" fill="rgba(134,239,172,0.15)"/>
                                <!-- Typing indicator -->
                                <rect x="24" y="128" width="50" height="22" rx="11" fill="rgba(134,239,172,0.08)" stroke="rgba(134,239,172,0.2)" stroke-width="1"/>
                                <circle cx="39" cy="139" r="3" fill="rgba(134,239,172,0.5)"/>
                                <circle cx="49" cy="139" r="3" fill="rgba(134,239,172,0.35)"/>
                                <circle cx="59" cy="139" r="3" fill="rgba(134,239,172,0.2)"/>
                                <!-- Read indicators -->
                                <path d="M158 124 l2.5 2.5 4-4.5" stroke="rgba(134,239,172,0.5)" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                                <path d="M162 124 l2.5 2.5 4-4.5" stroke="rgba(134,239,172,0.35)" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                            </svg>

                            <!-- Рейтинг -->
                            <svg v-if="selectedFeature.illus === 'rating'" viewBox="0 0 200 160" fill="none">
                                <defs>
                                    <linearGradient id="ratingGrad" x1="40" y1="120" x2="146" y2="82" gradientUnits="userSpaceOnUse">
                                        <stop offset="0%" stop-color="#fcd34d" stop-opacity="0.4"/>
                                        <stop offset="100%" stop-color="#fcd34d"/>
                                    </linearGradient>
                                </defs>
                                <!-- Arc progress -->
                                <path d="M40 120 A 60 60 0 1 1 160 120" stroke="rgba(255,255,255,0.08)" stroke-width="10" stroke-linecap="round" fill="none"/>
                                <path d="M40 120 A 60 60 0 0 1 146 82" stroke="url(#ratingGrad)" stroke-width="10" stroke-linecap="round" fill="none"/>
                                <!-- Center circle -->
                                <circle cx="100" cy="120" r="22" fill="rgba(252,211,77,0.07)" stroke="rgba(252,211,77,0.25)" stroke-width="1"/>
                                <circle cx="100" cy="120" r="14" fill="rgba(252,211,77,0.12)" stroke="rgba(252,211,77,0.4)" stroke-width="1"/>
                                <circle cx="100" cy="120" r="6" fill="rgba(252,211,77,0.35)"/>
                                <!-- Stars: 5-point, R=5 r=2, centers spaced 13px at y=150 -->
                                <path d="M74,145 L75.2,148.4 L78.8,148.5 L75.9,150.6 L76.9,154 L74,152.1 L71.1,154 L72.1,150.6 L69.2,148.5 L72.8,148.4 Z" fill="rgba(252,211,77,0.75)"/>
                                <path d="M87,145 L88.2,148.4 L91.8,148.5 L88.9,150.6 L89.9,154 L87,152.1 L84.1,154 L85.1,150.6 L82.2,148.5 L85.8,148.4 Z" fill="rgba(252,211,77,0.75)"/>
                                <path d="M100,145 L101.2,148.4 L104.8,148.5 L101.9,150.6 L102.9,154 L100,152.1 L97.1,154 L98.1,150.6 L95.2,148.5 L98.8,148.4 Z" fill="rgba(252,211,77,0.75)"/>
                                <path d="M113,145 L114.2,148.4 L117.8,148.5 L114.9,150.6 L115.9,154 L113,152.1 L110.1,154 L111.1,150.6 L108.2,148.5 L111.8,148.4 Z" fill="rgba(252,211,77,0.75)"/>
                                <path d="M126,145 L127.2,148.4 L130.8,148.5 L127.9,150.6 L128.9,154 L126,152.1 L123.1,154 L124.1,150.6 L121.2,148.5 L124.8,148.4 Z" fill="rgba(252,211,77,0.25)"/>
                                <!-- Tick mark at arc end -->
                                <circle cx="146" cy="82" r="6" fill="rgba(252,211,77,0.25)" stroke="rgba(252,211,77,0.6)" stroke-width="1"/>
                                <path d="M143 82 L145 84 L150 79" stroke="rgba(252,211,77,0.8)" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                            </svg>

                            <!-- Безопасность -->
                            <svg v-if="selectedFeature.illus === 'security'" viewBox="0 0 200 160" fill="none">
                                <!-- Shield -->
                                <path d="M100 18 L148 36 L148 82 Q148 122 100 142 Q52 122 52 82 L52 36 Z"
                                    fill="rgba(165,180,252,0.06)" stroke="rgba(165,180,252,0.35)" stroke-width="1.5"/>
                                <path d="M100 28 L138 43 L138 80 Q138 112 100 128 Q62 112 62 80 L62 43 Z"
                                    fill="rgba(165,180,252,0.08)" stroke="rgba(165,180,252,0.2)" stroke-width="1"/>
                                <!-- Checkmark -->
                                <path d="M78 80 L92 94 L122 64" stroke="rgba(165,180,252,0.85)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                                <!-- Glow rings -->
                                <path d="M100 10 L152 30 L152 84 Q152 128 100 150 Q48 128 48 84 L48 30 Z"
                                    stroke="rgba(165,180,252,0.1)" stroke-width="1" stroke-dasharray="3 4" fill="none"/>
                                <path d="M100 4 L158 26 L158 86 Q158 134 100 158 Q42 134 42 86 L42 26 Z"
                                    stroke="rgba(165,180,252,0.05)" stroke-width="1" stroke-dasharray="2 5" fill="none"/>
                            </svg>

                        </div>

                        <!-- Текст деталей -->
                        <div class="ab-detail-content">
                            <h3 class="ab-detail-title">{{ selectedFeature.title }}</h3>
                            <p class="ab-detail-text">{{ selectedFeature.detail }}</p>
                            <ul class="ab-detail-points">
                                <li v-for="p in selectedFeature.points" :key="p.text" class="ab-detail-point">
                                    <span class="ab-detail-point__icon" :style="{ color: selectedFeature.color }">{{ p.icon }}</span>
                                    <span>{{ p.text }}</span>
                                </li>
                            </ul>
                        </div>


                    </div>

                </div>

        </div>
    </div>
</template>

<style scoped>
/* ── Shell ─────────────────────────────────────────────────── */
.ab-shell {
    width: 100%; height: 100dvh;
    display: flex; flex-direction: column; overflow: hidden;
    position: relative; z-index: 1;
    background: linear-gradient(180deg, rgba(255,42,191,0.07) 0%, rgba(0,0,0,0.65) 100%) fixed;
}

/* ── Декор ─────────────────────────────────────────────────── */
.ab-circle { border-radius: 50%; background: rgba(60,60,190,0.03); box-shadow: inset 0 0 30px rgba(255,255,255,0.015); position: absolute; right: -12%; top: -8%; }
.ab-circle--1 { width: 900px; height: 900px; }
.ab-circle--2 { width: 700px; height: 700px; }
.ab-circle--3 { width: 500px; height: 500px; }
.ab-orb { position: absolute; border-radius: 50%; filter: blur(100px); pointer-events: none; }
.ab-orb--pink { width: 500px; height: 500px; background: radial-gradient(circle, rgba(236,72,153,0.12) 0%, transparent 70%); top: -10%; left: -5%; }
.ab-orb--cyan { width: 400px; height: 400px; background: radial-gradient(circle, rgba(34,211,238,0.08) 0%, transparent 70%); bottom: 5%; right: 5%; }

/* ── Topbar ─────────────────────────────────────────────────── */

/* ── Content ────────────────────────────────────────────────── */
.ab-content { flex: 1; min-height: 0; position: relative; overflow: hidden; }

/* ── About inner — split layout ────────────────────────────── */
.ab-about-inner {
    height: 100%;
    display: flex;
    align-items: center;
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 4rem;
    gap: 0;
    transition: gap 0.35s ease;
}
.ab-about-inner--split {
    gap: 2.5rem;
}

/* ── Left column ───────────────────────────────────────────── */
.ab-left-col {
    flex: 0 0 100%;
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 2rem;
    will-change: transform;
}
.ab-about-inner--split .ab-left-col {
    flex-basis: 50%;
}

/* Entrance animations */
@keyframes ab-fade-in { from { opacity: 0; } to { opacity: 1; } }
.ab-hero-title  { animation: ab-fade-in 0.5s  ease-out 0.1s  both; }
.ab-hero-desc   { animation: ab-fade-in 0.45s ease-out 0.2s  both; }
.ab-detail-col  { animation: ab-fade-in 0.5s  ease-out 0.15s both; transition: opacity 0.25s ease-out; }

/* Hero */
.ab-hero { display: flex; flex-direction: column; gap: 1rem; }
.ab-hero-title { font-family: "Brygada 1918", serif; font-size: clamp(2.2rem, 4.2vw, 3.6rem); font-weight: 400; color: rgba(255,255,255,0.92); line-height: 1.15; margin: 0; }
.ab-hero-title em { font-style: italic; background: linear-gradient(90deg, #ffb2ef, #ec4899); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.ab-hero-desc { font-size: clamp(1rem, 1.5vw, 1.15rem); line-height: 1.75; color: rgba(255,255,255,0.52); max-width: 580px; margin: 0; }

/* Features grid */
.ab-features { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.65rem; }

.ab-feature-card {
    display: flex; flex-direction: column; align-items: center; text-align: center; gap: 0.55rem;
    padding: 1rem 0.9rem;
    background: rgba(255,255,255,0.025); border: 1px solid rgba(255,255,255,0.05);
    border-radius: 11px;
    transition: background 0.2s, border-color 0.2s, transform 0.2s, opacity 0.25s;
    position: relative; overflow: hidden; cursor: pointer; user-select: none;
}
.ab-feature-card:hover { background: rgba(255,255,255,0.045); border-color: rgba(255,255,255,0.09); transform: translateY(-1px); }
.ab-feature-card--active {
    background: rgba(255,255,255,0.06) !important;
    border-color: rgba(255,255,255,0.15) !important;
    border-color: color-mix(in srgb, var(--accent) 30%, transparent) !important;
    opacity: 1 !important;
}
.ab-feature-card__icon { font-size: 1.35rem; line-height: 1; color: var(--accent); filter: drop-shadow(0 0 6px var(--accent)); }
.ab-feature-card__body { width: 100%; }
.ab-feature-card__title { font-size: 0.95rem; font-weight: 600; color: rgba(255,255,255,0.88); margin-bottom: 0.28rem; }
.ab-feature-card__desc { display: none; }
.ab-feature-card__arrow { position: absolute; top: 0.55rem; right: 0.6rem; color: var(--accent); opacity: 0.4; transition: opacity 0.2s; }
.ab-feature-card:hover .ab-feature-card__arrow { opacity: 0.8; }
.ab-feature-card--active .ab-feature-card__arrow { opacity: 0; }

/* ── Right detail column ───────────────────────────────────── */
.ab-detail-col {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 1.5rem;
    padding-left: 2rem;
    border-left: 1px solid rgba(255,255,255,0.07);
    will-change: transform, opacity;
    position: relative;
}

/* SVG illustration */
.ab-detail-illus {
    width: 100%;
    max-height: 160px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ab-detail-illus svg {
    width: 100%;
    max-width: 220px;
    height: auto;
    filter: drop-shadow(0 0 20px rgba(0,0,0,0.3));
}

/* Detail content */
.ab-detail-content { display: flex; flex-direction: column; gap: 0.85rem; }

.ab-detail-title {
    font-family: "Brygada 1918", serif;
    font-size: clamp(1.7rem, 3vw, 2.5rem);
    font-weight: 400;
    color: rgba(255,255,255,0.92);
    margin: 0;
    line-height: 1.2;
}

.ab-detail-text {
    font-size: clamp(0.95rem, 1.3vw, 1.05rem);
    color: rgba(255,255,255,0.54);
    line-height: 1.75;
    margin: 0;
}

.ab-detail-points { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.6rem; }
.ab-detail-point {
    display: flex; align-items: baseline; gap: 0.65rem;
    font-size: clamp(0.88rem, 1.1vw, 0.95rem); color: rgba(255,255,255,0.58);
    padding: 0.45rem 0.7rem;
    background: linear-gradient(90deg, color-mix(in srgb, var(--accent, #ffb2ef) 10%, transparent) 0%, transparent 100%);
    border-radius: 7px;
    opacity: 0.85;
    transition: opacity 0.15s, background 0.15s;
}
.ab-detail-point:hover { opacity: 1; background: linear-gradient(90deg, color-mix(in srgb, var(--accent, #ffb2ef) 16%, transparent) 0%, transparent 100%); }
.ab-detail-point__icon { font-size: 0.7rem; flex-shrink: 0; opacity: 0.7; }

/* ── Responsive ────────────────────────────────────────────── */
@media (max-width: 900px) {
    .ab-about-inner--split { flex-direction: column; gap: 1.5rem; align-items: stretch; overflow-y: auto; }
    .ab-about-inner--split .ab-left-col { flex-basis: auto; }
    .ab-detail-col { padding-left: 0; padding-top: 1.5rem; border-left: none; border-top: 1px solid rgba(255,255,255,0.07); }
}
@media (max-width: 768px) {
    .ab-topbar { padding: 0.75rem 1.5rem 0; }
    .ab-about-inner { padding: 1.5rem 1.5rem; }
    .ab-features { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    .ab-features { grid-template-columns: 1fr; }
    .ab-hero-title { font-size: 1.6rem; }
}
</style>
