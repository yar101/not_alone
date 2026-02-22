<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';
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

// ── Tabs ─────────────────────────────────────────────────────
const tab = ref('about');
const tabDir = ref(1);  // +1 → slide-left, -1 → slide-right
const TAB_ORDER = ['about', 'posts', 'services', 'content'];

const tabAboutEl    = ref(null);
const tabPostsEl    = ref(null);
const tabServicesEl = ref(null);
const tabContentEl  = ref(null);

const indicatorStyle = computed(() => {
    const elMap = {
        about:    tabAboutEl.value,
        posts:    tabPostsEl.value,
        services: tabServicesEl.value,
        content:  tabContentEl.value,
    };
    const el = elMap[tab.value];
    if (!el) return {};
    return { left: el.offsetLeft + 'px', width: el.offsetWidth + 'px' };
});

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

            <!-- Tab bar (монолитно продолжает карточку) -->
            <div class="profile-tabs">
                <div class="tab-indicator" :style="indicatorStyle" />
                <button
                    ref="tabAboutEl"
                    class="tab-btn"
                    :class="{ active: tab === 'about' }"
                    @click="switchTab('about')"
                >
                    Обо мне
                </button>
                <button
                    ref="tabPostsEl"
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
                    ref="tabServicesEl"
                    class="tab-btn"
                    :class="{ active: tab === 'services' }"
                    @click="switchTab('services')"
                >
                    Услуги
                </button>
                <button
                    ref="tabContentEl"
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
                        <div id="tour-about" class="anim-block">
                            <ProfileAbout :about="profileUser.about" :is-owner="isOwner" />
                        </div>
                        <div id="tour-voice" class="anim-block">
                            <ProfileVoice :voice-url="profileUser.voice_url" :is-owner="isOwner" />
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
    background: #16162a !important;
    border: 1px solid rgba(200, 70, 126, 0.3) !important;
    color: rgba(255, 255, 255, 0.9) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6) !important;
    border-radius: 12px !important;
}
.driver-popover-title {
    color: #ffffff !important;
    font-size: 1rem !important;
}
.driver-popover-description {
    color: rgba(255, 255, 255, 0.65) !important;
    font-size: 0.88rem !important;
    line-height: 1.55 !important;
}
.driver-popover-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.07) !important;
}
.driver-popover-prev-btn,
.driver-popover-next-btn,
.driver-popover-done-btn {
    background: rgba(200, 70, 126, 0.15) !important;
    border: 1px solid rgba(200, 70, 126, 0.35) !important;
    color: rgba(255, 255, 255, 0.85) !important;
    border-radius: 8px !important;
    text-shadow: none !important;
}
.driver-popover-prev-btn:hover,
.driver-popover-next-btn:hover,
.driver-popover-done-btn:hover {
    background: rgba(200, 70, 126, 0.3) !important;
}
.driver-popover-progress-text {
    color: rgba(255, 255, 255, 0.35) !important;
}
.driver-popover-arrow-side-left.driver-popover-arrow   { border-left-color: #16162a !important; }
.driver-popover-arrow-side-right.driver-popover-arrow  { border-right-color: #16162a !important; }
.driver-popover-arrow-side-top.driver-popover-arrow    { border-top-color: #16162a !important; }
.driver-popover-arrow-side-bottom.driver-popover-arrow { border-bottom-color: #16162a !important; }
</style>

<style scoped>
.profile-page {
    height: 100vh;
    overflow: hidden;
    background:
        radial-gradient(ellipse 80% 40% at 20% 0%,   rgba(200,70,126,0.16) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 80% 5%,   rgba(100,60,200,0.13) 0%, transparent 55%),
        #0d0d18;
    padding: 0 1rem;
    box-sizing: border-box;
}

.profile-container {
    max-width: 900px;
    margin: 0 auto;
    padding-top: 1.5rem;
    height: 100%;
    display: flex;
    flex-direction: column;
}

/* Хедер — не сжимается */
:deep(#tour-header) {
    flex-shrink: 0;
}

/* Tab bar — монолитно продолжает .profile-card */
.profile-tabs {
    display: flex;
    flex-shrink: 0;
    position: relative;
    background: rgba(255,255,255,0.035);
    border: 1px solid rgba(255,255,255,0.08);
    border-top: 1px solid rgba(255,255,255,0.04);
    border-radius: 0 0 16px 16px;
    padding: 6px 8px;
    margin-bottom: 1.25rem;
}

.tab-indicator {
    position: absolute;
    top: 6px;
    height: calc(100% - 12px);
    background: linear-gradient(135deg, rgba(200,70,126,0.3), rgba(140,60,200,0.2));
    border: 1px solid rgba(200,70,126,0.28);
    border-radius: 10px;
    z-index: 0;
    transition:
        left 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94),
        width 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.tab-btn {
    flex: 1;
    padding: 0.55rem 1rem;
    border-radius: 10px;
    border: none;
    background: transparent;
    color: rgba(255,255,255,0.4);
    font-size: 0.88rem;
    cursor: pointer;
    font-family: inherit;
    transition: color 0.2s;
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
}
.tab-btn.active {
    color: #fff;
    font-weight: 500;
}

.tab-badge {
    font-size: 0.68rem;
    padding: 0.05rem 0.45rem;
    border-radius: 8px;
    background: rgba(200,70,126,0.2);
    color: rgba(200,70,126,0.9);
    border: 1px solid rgba(200,70,126,0.2);
}

/* Tab transitions */
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
.slide-right-enter-active  { transition: transform 0.3s cubic-bezier(0.25,0.46,0.45,0.94), opacity 0.25s ease; }
.slide-left-leave-active,
.slide-right-leave-active  { transition: transform 0.22s ease-in, opacity 0.18s ease; }

.tab-panel {
    height: 100%;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding-bottom: 1.5rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(200,70,126,0.25) transparent;
}
.tab-panel::-webkit-scrollbar { width: 4px; }
.tab-panel::-webkit-scrollbar-track { background: transparent; }
.tab-panel::-webkit-scrollbar-thumb { background: rgba(200,70,126,0.25); border-radius: 2px; }

/* Placeholders */
.coming-soon-block {
    padding: 3rem 2rem;
    text-align: center;
    background: rgba(255,255,255,0.02);
    border-radius: 16px;
    border: 1px dashed rgba(255,255,255,0.07);
}
.coming-soon-title {
    font-size: 1rem;
    font-weight: 600;
    color: rgba(255,255,255,0.35);
    margin: 0 0 0.4rem;
}
.coming-soon-text {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.2);
    margin: 0;
}
</style>
