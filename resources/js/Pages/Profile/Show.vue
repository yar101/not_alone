<script setup>
import { ref, watch, nextTick, onMounted, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import ProfileHeader from '@/Components/Profile/ProfileHeader.vue';
import ProfileAbout from '@/Components/Profile/ProfileAbout.vue';
import ProfileTraits from '@/Components/Profile/ProfileTraits.vue';
import ProfileInterests from '@/Components/Profile/ProfileInterests.vue';
import ProfileLanguages from '@/Components/Profile/ProfileLanguages.vue';
import ProfileChecklist from '@/Components/Profile/ProfileChecklist.vue';
import ProfilePinnedCard from '@/Components/Profile/ProfilePinnedCard.vue';
import ProfilePosts from '@/Components/Profile/ProfilePosts.vue';
import ProfileVoice from '@/Components/Profile/ProfileVoice.vue';

const props = defineProps({
    profileUser:   { type: Object, required: true },
    isOwner:       { type: Boolean, default: false },
    allTraits:     { type: Array, default: () => [] },
    allCategories: { type: Array, default: () => [] },
});

// ── Gender label ─────────────────────────────────────────────
const genderLabel = computed(() => ({
    male: 'Мужской', female: 'Женский', other: 'Другой',
}[props.profileUser.gender] ?? '—'));

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
        y: 20, opacity: 0, duration: 0.35, stagger: 0.07, ease: 'power2.out',
    });
});

// ── driver.js Tour ─────────────────────────────────────────
const TOUR_KEY = 'profile_tour_done';

onMounted(async () => {
    // Initial entrance animation
    await nextTick();
    gsap.from('.tab-panel > .anim-block', {
        y: 20, opacity: 0, duration: 0.35, stagger: 0.07, ease: 'power2.out',
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
                    title: 'Голосовое',
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

            <ProfileHeader :user="profileUser" :is-owner="isOwner" />

            <!-- Tab bar -->
            <div class="profile-tabs">
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
                    <span v-if="profileUser.posts && profileUser.posts.length" class="tab-badge">
                        {{ profileUser.posts.length }}
                    </span>
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
            <div class="tab-content-wrap">
                <Transition :name="tabDir > 0 ? 'slide-left' : 'slide-right'" mode="out-in">

                    <div v-if="tab === 'about'" key="about" class="tab-panel">

                        <!-- Верх: bio слева, диск+плеер справа -->
                        <div class="about-top-grid anim-block">
                            <ProfileAbout :about="profileUser.about" :is-owner="isOwner" />
                            <div id="tour-voice" class="about-voice-col">
                                <ProfileVoice :voice-url="profileUser.voice_url" :is-owner="isOwner" />
                            </div>
                        </div>

                        <!-- Слитая панель: пол/возраст + характер + интересы + языки -->
                        <div class="fused-panel">
                            <!-- Пол / Возраст -->
                            <div class="fused-section ga-section anim-block">
                                <div class="ga-item">
                                    <span class="ga-key">Пол</span>
                                    <span class="ga-val">{{ genderLabel }}</span>
                                </div>
                                <div class="ga-item">
                                    <span class="ga-key">Возраст</span>
                                    <span class="ga-val">{{ profileUser.age ?? '—' }}</span>
                                </div>
                            </div>

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

            <!-- Checklist widget (owner only) -->
            <ProfileChecklist v-if="isOwner" :user="profileUser" />

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
    border-radius: 4px !important;
    font-family: 'Brygada 1918', Georgia, serif !important;
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
/* ── Страница ─────────────────────────────────────────────── */
.profile-page {
    height: 100vh;
    overflow: hidden;
    background: #0a0a0f;
    padding: 0 1.5rem;
    box-sizing: border-box;
    font-family: 'Brygada 1918', Georgia, serif;
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
    flex-wrap: wrap;
    flex-shrink: 0;
    background: transparent;
    border: 1px solid rgba(255,255,255,0.08);
    border-top: none;
    padding: 6px 8px;
    gap: 5px;
    margin-bottom: 1.25rem;
}

.tab-btn {
    padding: 0.5rem 1.2rem;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0;
    background: transparent;
    color: rgba(255,255,255,0.35);
    font-size: 1rem;
    cursor: pointer;
    font-family: inherit;
    transition: border-color 0.15s, color 0.15s;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    letter-spacing: 0.02em;
}
.tab-btn.active {
    border-color: #FE28A2;
    color: #fff;
    font-weight: 600;
}
.tab-btn:hover:not(.active) {
    color: rgba(255,255,255,0.65);
    border-color: rgba(255,255,255,0.2);
}

.tab-badge {
    font-size: 0.72rem;
    padding: 0.05rem 0.35rem;
    border: 1px solid rgba(254,40,162,0.35);
    color: rgba(254,40,162,0.8);
    background: transparent;
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
    scrollbar-width: thin;
    scrollbar-color: rgba(254,40,162,0.25) transparent;
}
.tab-panel::-webkit-scrollbar { width: 2px; }
.tab-panel::-webkit-scrollbar-track { background: transparent; }
.tab-panel::-webkit-scrollbar-thumb { background: rgba(254,40,162,0.3); }

/* ── About: верхняя сетка ─────────────────────────────────── */
.about-top-grid {
    display: grid;
    grid-template-columns: 1fr 300px;
    border: 1px solid rgba(255,255,255,0.08);
    overflow: hidden;
    margin-bottom: 0;
}

.about-top-grid :deep(.block-section) {
    border: none;
    border-right: 1px solid rgba(255,255,255,0.08);
}

.about-voice-col {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem 1rem;
}

/* ── Слитая панель ────────────────────────────────────────── */
.fused-panel {
    border: 1px solid rgba(255,255,255,0.08);
    border-top: none;
    overflow: hidden;
}

.fused-panel :deep(.block-section) {
    border-top: 1px solid rgba(255,255,255,0.08);
    background: transparent;
}

.fused-section {
    padding: 1.25rem 2rem;
}

/* ── Пол / Возраст ────────────────────────────────────────── */
.ga-section {
    display: flex;
    gap: 3rem;
}

.ga-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.ga-key {
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #FE28A2;
    opacity: 0.7;
}

.ga-val {
    font-size: 1.1rem;
    color: rgba(255,255,255,0.85);
    font-weight: 500;
}

/* ── Остальные табы ───────────────────────────────────────── */
.tab-panel > .anim-block + .anim-block {
    margin-top: 1rem;
}

.coming-soon-block {
    padding: 4rem 2rem;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.07);
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
        border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .about-voice-col { padding: 1.25rem; }
}
</style>
