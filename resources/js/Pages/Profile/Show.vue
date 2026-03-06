<script setup>
import { ref, watch, nextTick, onMounted, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { StarFilled, MagicStick } from '@element-plus/icons-vue';

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
                    description: 'Нажми кнопку редактирования чтобы изменить.',
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

            <!-- Two-column body -->
            <div class="profile-body">

                <!-- Left sidebar: header + vertical tabs -->
                <div class="profile-sidebar">
                    <ProfileHeader
                        class="page-block"
                        :class="{ 'header-flat-bottom': !isOwner }"
                        :user="profileUser"
                        :is-owner="isOwner"
                    />
                    <button v-if="!isOwner" class="sidebar-subscribe-btn">
                        Подписаться
                    </button>

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
                </div>

                <!-- Right main: scrollable tab content -->
                <div class="profile-main">
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
                        <!-- If profileUser is an idol -->
                        <template v-if="profileUser.is_idol">
                            <div class="anim-block coming-soon-block">
                                <p class="coming-soon-title"><el-icon style="vertical-align: middle; margin-right: 4px"><StarFilled /></el-icon>Услуги Айдола</p>
                                <p class="coming-soon-text">Услуги появятся здесь совсем скоро</p>
                            </div>
                        </template>
                        <!-- If owner and not idol -->
                        <template v-else-if="isOwner">
                            <div class="anim-block idol-cta-block">
                                <el-icon class="idol-cta-icon"><MagicStick /></el-icon>
                                <p class="idol-cta-title">Стань Айдолом</p>
                                <p class="idol-cta-text">Айдолы могут предоставлять уникальные услуги другим участникам платформы. Пройди тест и подай заявку!</p>
                                <Link href="/idol/apply" class="idol-cta-btn">Стать Айдолом</Link>
                            </div>
                        </template>
                        <template v-else>
                            <div class="anim-block coming-soon-block">
                                <p class="coming-soon-title">Услуги</p>
                                <p class="coming-soon-text">Раздел в разработке</p>
                            </div>
                        </template>
                    </div>

                    <div v-else key="content" class="tab-panel">
                        <div class="anim-block coming-soon-block">
                            <p class="coming-soon-title">Контент</p>
                            <p class="coming-soon-text">Платные паки контента — скоро</p>
                        </div>
                    </div>

                </Transition>
                </div><!-- /tab-content-wrap -->
                </div><!-- /profile-main -->

            </div><!-- /profile-body -->

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

/* ── Two-column body ──────────────────────────────────────── */
.profile-body {
    display: flex;
    flex: 1;
    min-height: 0;
    gap: 1rem;
}

.profile-sidebar {
    width: 300px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    overflow-x: hidden;
    border-right: 1px solid rgba(255, 255, 255, 0.06);
    padding-right: 1rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(254,40,162,0.25) transparent;
}
.profile-sidebar::-webkit-scrollbar { width: 3px; }
.profile-sidebar::-webkit-scrollbar-track { background: transparent; }
.profile-sidebar::-webkit-scrollbar-thumb {
    background: rgba(254,40,162,0.28);
    border-radius: 999px;
}

.profile-main {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    padding-left: 0.25rem;
}


/* ── Таббар (vertical) ────────────────────────────────────── */
.profile-tabs {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    background: transparent;
    border: none;
    padding: 0.75rem 0 0.5rem;
    gap: 0.1rem;
    margin-top: 0.35rem;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.tab-btn {
    padding: 0.6rem 0.85rem 0.6rem 1rem;
    border: none;
    border-radius: 3px;
    background: transparent;
    color: rgba(255,255,255,0.45);
    font-size: 0.75rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 0.5rem;
    transition: background 0.18s ease, color 0.18s ease, padding-left 0.18s ease;
    white-space: nowrap;
    width: 100%;
    position: relative;
}
.tab-btn::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%) scaleY(0);
    width: 2px;
    height: 60%;
    background: #FE28A2;
    border-radius: 0 2px 2px 0;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.18s ease;
    opacity: 0;
}
.tab-btn.active {
    background: rgba(254, 40, 162, 0.08);
    color: rgba(254, 40, 162, 0.95);
    padding-left: 1.25rem;
}
.tab-btn.active::before {
    transform: translateY(-50%) scaleY(1);
    opacity: 1;
}
.tab-btn:hover:not(.active) {
    background: rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.75);
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

/* ── Idol CTA block ──────────────────────────────────────── */
.idol-cta-block {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 2rem;
    text-align: center;
    background: rgba(200, 70, 126, 0.04);
    border: 1px solid rgba(200, 70, 126, 0.15);
    border-radius: 16px;
}
.idol-cta-icon { font-size: 2.5rem; display: flex; justify-content: center; }
.idol-cta-title { font-size: 1.25rem; color: rgba(255,255,255,0.9); margin: 0; font-weight: 600; }
.idol-cta-text { font-size: 0.85rem; color: rgba(255,255,255,0.45); margin: 0; max-width: 320px; line-height: 1.6; }
.idol-cta-btn {
    margin-top: 0.5rem;
    padding: 0.6rem 1.5rem;
    background: linear-gradient(135deg, #C8467E, #a03466);
    border-radius: 10px;
    color: #fff;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    transition: opacity 0.15s;
}
.idol-cta-btn:hover { opacity: 0.85; }

/* ── Subscribe button fused below header ─────────────────── */
.profile-sidebar :deep(.profile-header.header-flat-bottom) {
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
    border-bottom: none;
}

.sidebar-subscribe-btn {
    width: 100%;
    padding: 0.5rem;
    background: rgba(254, 40, 162, 0.05);
    border: 1px solid rgba(254, 40, 162, 0.35);
    border-top: none;
    border-radius: 0 0 3px 3px;
    color: rgba(254, 40, 162, 0.75);
    font-family: inherit;
    font-size: 0.78rem;
    font-weight: 500;
    letter-spacing: 0.06em;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    flex-shrink: 0;
}
.sidebar-subscribe-btn:hover {
    background: rgba(254, 40, 162, 0.1);
    color: rgba(254, 40, 162, 1);
}

/* ── Адаптив ──────────────────────────────────────────────── */
@media (max-width: 768px) {
    .profile-page {
        height: auto;
        overflow: visible;
        padding: 0 1rem;
    }
    .profile-container {
        height: auto;
    }
    .profile-body {
        flex-direction: column;
    }
    .profile-sidebar {
        width: 100%;
        overflow: visible;
        border-right: none;
        padding-right: 0;
    }
    .profile-main {
        padding-left: 0;
    }
    /* Horizontal tabs on mobile */
    .profile-tabs {
        flex-direction: row;
        overflow-x: auto;
        scrollbar-width: none;
        padding: 0.25rem 0;
        margin-top: 0.25rem;
        border-top: none;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }
    .profile-tabs::-webkit-scrollbar { display: none; }
    .tab-btn {
        flex-shrink: 0;
        width: auto;
        justify-content: center;
        padding-left: 0.85rem;
    }
    .tab-btn.active {
        padding-left: 0.85rem;
    }
    /* Swap to bottom indicator on mobile */
    .tab-btn::before {
        top: auto;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%) scaleX(0);
        width: 60%;
        height: 2px;
        border-radius: 2px 2px 0 0;
    }
    .tab-btn.active::before {
        transform: translateX(-50%) scaleX(1);
    }
    .profile-main {
        height: 60vh;
    }
}

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
