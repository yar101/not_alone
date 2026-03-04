<script setup>
import { ref, watch, nextTick, onMounted, computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
import { gsap } from 'gsap';
import ProfileHeader from '@/Components/Profile/ProfileHeader.vue';
import ProfileAbout from '@/Components/Profile/ProfileAbout.vue';
import ProfileTraits from '@/Components/Profile/ProfileTraits.vue';
import ProfileInterests from '@/Components/Profile/ProfileInterests.vue';
import ProfileLanguages from '@/Components/Profile/ProfileLanguages.vue';
import ProfilePinnedCard from '@/Components/Profile/ProfilePinnedCard.vue';
import ProfilePosts from '@/Components/Profile/ProfilePosts.vue';
import ProfileVoice from '@/Components/Profile/ProfileVoice.vue';

const props = defineProps({
    profileUser:   { type: Object, required: true },
    isOwner:       { type: Boolean, default: false },
    allTraits:     { type: Array, default: () => [] },
    allCategories: { type: Array, default: () => [] },
});

// ── Email verification banner ─────────────────────────────────
const page = usePage();
const showVerificationBanner = computed(() =>
    props.isOwner && !page.props.auth?.user?.email_verified_at
);
const verificationForm = useForm({});
const resendSent = ref(false);
function resendVerification() {
    verificationForm.post(route('verification.send'), {
        onSuccess: () => { resendSent.value = true; },
    });
}

// ── Tabs ─────────────────────────────────────────────────────
const tab = ref('about');
const tabDir = ref(1);  // +1 → slide-left, -1 → slide-right
const TAB_ORDER = ['about', 'posts', 'services', 'content'];

function switchTab(name) {
    tabDir.value = TAB_ORDER.indexOf(name) > TAB_ORDER.indexOf(tab.value) ? 1 : -1;
    tab.value = name;
}

// Stagger entrance on tab change
watch(tab, async () => {
    await nextTick();
    gsap.from('.tab-panel > .anim-block', {
        y: 14, opacity: 0, duration: 0.32, ease: 'power2.out',
    });
});

// ── driver.js Tour ─────────────────────────────────────────
const TOUR_KEY = 'profile_tour_done';

onMounted(async () => {
    // Initial entrance animation
    await nextTick();
    gsap.from('.page-block', {
        y: 16, opacity: 0, duration: 0.4, stagger: 0.08, ease: 'power2.out',
    });

    if (!props.isOwner) return;
    if (localStorage.getItem(TOUR_KEY)) return;

    const { driver } = await import('driver.js');
    await import('driver.js/dist/driver.css');

    const driverObj = driver({
        showProgress: true,
        nextBtnText: 'Далее →',
        prevBtnText: '← Назад',
        doneBtnText: 'Готово',
        steps: [
            {
                element: '#tour-header',
                popover: {
                    title: 'Твой профиль',
                    description: 'Нажми ✏️ чтобы отредактировать.',
                    side: 'bottom',
                },
            },
            {
                element: '.profile-tabs',
                popover: {
                    title: 'Навигация',
                    description: 'Переключайся между разделами профиля.',
                    side: 'bottom',
                },
            },
            {
                element: '#tour-about',
                popover: {
                    title: 'Обо мне',
                    description: 'Расскажи о себе.',
                    side: 'bottom',
                },
            },
            {
                element: '#tour-voice',
                popover: {
                    title: 'Аудио',
                    description: 'Запиши приветствие до 27 секунд.',
                    side: 'bottom',
                },
            },
            {
                element: '#tour-traits',
                popover: {
                    title: 'Черты характера',
                    description: 'До 10 вариантов.',
                    side: 'bottom',
                },
            },
            {
                element: '#tour-interests',
                popover: {
                    title: 'Интересы',
                    description: 'Добавь свои увлечения.',
                    side: 'bottom',
                },
            },
            {
                element: '.cl-widget',
                popover: {
                    title: 'Чеклист',
                    description: 'Прогресс заполнения профиля.',
                    side: 'top',
                    align: 'end',
                },
            },
        ],
        onDestroyStarted: () => {
            localStorage.setItem(TOUR_KEY, '1');
            driverObj.destroy();
        },
    });

    driverObj.drive();
});
</script>

<template>
    <Head :title="profileUser.name + ' — профиль'" />

    <div class="profile-page">
        <div class="profile-container">

            <!-- Email verification banner -->
            <div v-if="showVerificationBanner" class="verify-banner">
                <span class="verify-banner__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </span>
                <span class="verify-banner__text">
                    Подтвердите email — мы отправили письмо на
                    <strong>{{ page.props.auth.user.email }}</strong>
                </span>
                <span v-if="resendSent" class="verify-banner__sent">Письмо отправлено</span>
                <button
                    v-else
                    class="verify-banner__btn"
                    :disabled="verificationForm.processing"
                    @click="resendVerification"
                >
                    Отправить повторно
                </button>
            </div>

            <ProfileHeader class="page-block" :user="profileUser" :is-owner="isOwner" />

            <!-- Tab bar -->
            <div class="profile-tabs page-block">
                <button
                    class="tab-btn"
                    :class="{ active: tab === 'about' }"
                    @click="switchTab('about')"
                >
                    О себе
                </button>
                <button
                    class="tab-btn"
                    :class="{ active: tab === 'posts' }"
                    @click="switchTab('posts')"
                >
                    Публикации
                </button>
                <button
                    class="tab-btn"
                    :class="{ active: tab === 'services' }"
                    @click="switchTab('services')"
                >
                    Услуги
                </button>
                <button
                    class="tab-btn"
                    :class="{ active: tab === 'content' }"
                    @click="switchTab('content')"
                >
                    Контент
                </button>
            </div>

            <!-- Tab panels -->
            <div class="tab-content-wrap page-block">
                <Transition :name="tabDir > 0 ? 'slide-left' : 'slide-right'" mode="out-in">

                    <div v-if="tab === 'about'" key="about" class="tab-panel">

                        <!-- Верх: bio слева, диск+плеер справа -->
                        <div class="about-top-grid anim-block">
                            <ProfileAbout :about="profileUser.about" :is-owner="isOwner" />
                            <div id="tour-voice" class="about-voice-col">
                                <ProfileVoice :voice-url="profileUser.voice_url" :is-owner="isOwner" />
                            </div>
                        </div>

                        <!-- Слитая панель: характер + интересы + языки -->
                        <div class="fused-panel">
                            <div id="tour-traits" class="anim-block">
                                <ProfileTraits
                                    :traits="profileUser.traits"
                                    :all-traits="allTraits"
                                    :is-owner="isOwner"
                                    :gender="profileUser.gender"
                                />
                            </div>

                            <div id="tour-interests" class="anim-block">
                                <ProfileInterests
                                    :interests="profileUser.interests"
                                    :all-categories="allCategories"
                                    :is-owner="isOwner"
                                />
                            </div>

                            <div class="anim-block">
                                <ProfileLanguages :languages="profileUser.languages" :is-owner="isOwner" />
                            </div>
                        </div>

                    </div>

                    <div v-else-if="tab === 'posts'" key="posts" class="tab-panel">
                        <div class="anim-block">
                            <ProfilePinnedCard :user="profileUser" :is-owner="isOwner" />
                        </div>
                        <div class="anim-block">
                            <ProfilePosts :posts="profileUser.posts" :is-owner="isOwner" />
                        </div>
                    </div>

                    <div v-else-if="tab === 'services'" key="services" class="tab-panel">
                        <div class="anim-block coming-soon-block">
                            <p class="coming-soon-title">Услуги</p>
                            <p class="coming-soon-text">Раздел в разработке</p>
                        </div>
                    </div>

                    <div v-else key="content" class="tab-panel">
                        <div class="anim-block coming-soon-block">
                            <p class="coming-soon-title">Контент</p>
                            <p class="coming-soon-text">Платные паки контента — скоро</p>
                        </div>
                    </div>

                </Transition>
            </div>

        </div>
    </div>
</template>

<!-- driver.js dark theme override (non-scoped) -->
<style>
.driver-popover {
    background: #0a0a0f !important;
    border: 1px solid rgba(224, 24, 108, 0.3) !important;
    color: rgba(255, 255, 255, 0.9) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8) !important;
    border-radius: 3px !important;
    font-family: 'Figtree', sans-serif !important;
}
.driver-popover-title {
    color: #ffffff !important;
    font-size: 1rem !important;
}
.driver-popover-description {
    color: rgba(255, 255, 255, 0.6) !important;
    font-size: 0.9rem !important;
    line-height: 1.6 !important;
}
.driver-popover-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.07) !important;
}
.driver-popover-prev-btn,
.driver-popover-next-btn,
.driver-popover-done-btn {
    background: transparent !important;
    border: 1px solid rgba(224, 24, 108, 0.4) !important;
    color: rgba(255, 255, 255, 0.8) !important;
    border-radius: 3px !important;
    text-shadow: none !important;
}
.driver-popover-prev-btn:hover,
.driver-popover-next-btn:hover,
.driver-popover-done-btn:hover {
    border-color: #FE28A2 !important;
    color: #fff !important;
}
.driver-popover-progress-text {
    color: rgba(255, 255, 255, 0.3) !important;
}
.driver-popover-arrow-side-left.driver-popover-arrow   { border-left-color: #0a0a0f !important; }
.driver-popover-arrow-side-right.driver-popover-arrow  { border-right-color: #0a0a0f !important; }
.driver-popover-arrow-side-top.driver-popover-arrow    { border-top-color: #0a0a0f !important; }
.driver-popover-arrow-side-bottom.driver-popover-arrow { border-bottom-color: #0a0a0f !important; }
</style>

<style scoped>
/* ── Verification banner ──────────────────────────────────── */
.verify-banner {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 1rem;
    margin-bottom: 0.75rem;
    border: 1px solid rgba(234, 179, 8, 0.3);
    border-radius: 3px;
    background: rgba(234, 179, 8, 0.06);
    flex-shrink: 0;
    flex-wrap: wrap;
}

.verify-banner__icon {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    color: rgba(234, 179, 8, 0.8);
}
.verify-banner__icon svg {
    width: 1rem;
    height: 1rem;
}

.verify-banner__text {
    flex: 1;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    min-width: 0;
}
.verify-banner__text strong {
    color: rgba(255, 255, 255, 0.85);
    font-weight: 500;
}

.verify-banner__sent {
    font-size: 0.82rem;
    color: rgba(74, 222, 128, 0.8);
    white-space: nowrap;
}

.verify-banner__btn {
    flex-shrink: 0;
    padding: 0.3rem 0.75rem;
    border: 1px solid rgba(234, 179, 8, 0.35);
    border-radius: 3px;
    background: transparent;
    color: rgba(234, 179, 8, 0.85);
    font-size: 0.8rem;
    font-family: inherit;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s;
    white-space: nowrap;
}
.verify-banner__btn:hover:not(:disabled) {
    border-color: rgba(234, 179, 8, 0.7);
    color: rgba(234, 179, 8, 1);
}
.verify-banner__btn:disabled {
    opacity: 0.4;
    cursor: default;
}

/* ── Страница ─────────────────────────────────────────────── */
.profile-page {
    height: calc(100vh - 60px);
    overflow: hidden;
    padding: 0 1.5rem;
    box-sizing: border-box;
    font-family: 'Figtree', sans-serif;
}

.profile-container {
    max-width: 1100px;
    margin: 0 auto;
    padding-top: 1.5rem;
    height: 100%;
    display: flex;
    flex-direction: column;
}

:deep(#tour-header) { flex-shrink: 0; }

/* ── Таббар ───────────────────────────────────────────────── */
.profile-tabs {
    display: flex;
    justify-content: center;
    flex-shrink: 0;
    background: transparent;
    border: none;
    padding: 0.5rem 0.75rem;
    gap: 0.2rem;
}

.tab-btn {
    padding: 0.5rem 1.1rem;
    border: none;
    border-radius: 3px;
    background: transparent;
    color: rgba(255,255,255,0.55);
    font-size: 0.85rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    transition: background 0.2s ease, color 0.2s ease;
    white-space: nowrap;
}
.tab-btn.active {
    background: rgba(254, 40, 162, 0.14);
    color: #FE28A2;
}
.tab-btn:hover:not(.active) {
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.8);
}

/* ── Контент ──────────────────────────────────────────────── */
.tab-content-wrap {
    position: relative;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

.slide-left-enter-from  { transform: translateX(36px); opacity: 0; }
.slide-left-leave-to    { transform: translateX(-36px); opacity: 0; }
.slide-right-enter-from { transform: translateX(-36px); opacity: 0; }
.slide-right-leave-to   { transform: translateX(36px); opacity: 0; }
.slide-left-enter-active,
.slide-right-enter-active  { transition: transform 0.28s cubic-bezier(0.25,0.46,0.45,0.94), opacity 0.22s ease; }
.slide-left-leave-active,
.slide-right-leave-active  { transition: transform 0.2s ease-in, opacity 0.16s ease; }

.tab-panel {
    height: 100%;
    overflow-y: auto;
    padding-bottom: 2rem;
    scrollbar-gutter: stable;
    scrollbar-width: thin;
    scrollbar-color: rgba(254,40,162,0.25) transparent;
}
.tab-panel::-webkit-scrollbar { width: 3px; }
.tab-panel::-webkit-scrollbar-track {
    background: transparent;
    margin-block: 0.5rem;
}
.tab-panel::-webkit-scrollbar-thumb {
    background: rgba(254,40,162,0.28);
    border-radius: 999px;
}

/* ── About: верхняя сетка ─────────────────────────────────── */
.about-top-grid {
    display: grid;
    grid-template-columns: 1fr 300px;
    background: #06060e;
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 3px 3px 0 0;
    overflow: hidden;
    margin-bottom: 0;
}

.about-top-grid > :first-child {
    min-width: 0;
}

.about-top-grid :deep(.block-section) {
    border: none;
    border-right: 1px solid rgba(255,255,255,0.18);
}

.about-voice-col {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem 1rem;
}

/* ── Слитая панель ────────────────────────────────────────── */
.fused-panel {
    background: #06060e;
    border: 1px solid rgba(255,255,255,0.18);
    border-top: none;
    border-radius: 0 0 3px 3px;
    overflow: hidden;
}

.fused-panel :deep(.block-section) {
    border-top: 1px solid rgba(255,255,255,0.18);
    background: #06060e;
}

.fused-panel > :first-child :deep(.block-section) {
    border-top: none;
}

/* ── Остальные табы ───────────────────────────────────────── */
.tab-panel > .anim-block + .anim-block {
    margin-top: 1rem;
}

.coming-soon-block {
    padding: 4rem 2rem;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 3px;
}
.coming-soon-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: rgba(255,255,255,0.28);
    margin: 0 0 0.4rem;
}
.coming-soon-text {
    font-size: 0.95rem;
    color: rgba(255,255,255,0.15);
    margin: 0;
}

/* ── Адаптив ──────────────────────────────────────────────── */
@media (max-width: 700px) {
    .about-top-grid {
        grid-template-columns: 1fr;
    }
    .about-top-grid :deep(.block-section) {
        border-right: none;
        border-bottom: 1px solid rgba(255,255,255,0.18);
    }
    .about-voice-col { padding: 1.25rem; }
}
</style>
