<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import ProfileHeader from '@/Components/Profile/ProfileHeader.vue';
import ProfileAbout from '@/Components/Profile/ProfileAbout.vue';
import ProfileTraits from '@/Components/Profile/ProfileTraits.vue';
import ProfileInterests from '@/Components/Profile/ProfileInterests.vue';
import ProfileLanguages from '@/Components/Profile/ProfileLanguages.vue';
import ProfileChecklist from '@/Components/Profile/ProfileChecklist.vue';
import ProfilePinnedCard from '@/Components/Profile/ProfilePinnedCard.vue';
import ProfilePosts from '@/Components/Profile/ProfilePosts.vue';

const props = defineProps({
    profileUser:   { type: Object, required: true },
    isOwner:       { type: Boolean, default: false },
    allTraits:     { type: Array, default: () => [] },
    allCategories: { type: Array, default: () => [] },
});

const checklistVisible = computed(() => props.isOwner);

// ── driver.js Tour ─────────────────────────────────────────
const TOUR_KEY = 'profile_tour_done';

onMounted(async () => {
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
                    description: 'Здесь отображается основная информация. Наведи мышь и нажми ✏️ чтобы отредактировать.',
                    side: 'bottom',
                },
            },
            {
                element: '#tour-about',
                popover: {
                    title: 'Обо мне',
                    description: 'Расскажи о себе — это первое, что видят другие пользователи.',
                    side: 'bottom',
                },
            },
            {
                element: '#tour-traits',
                popover: {
                    title: 'Черты характера',
                    description: 'Выбери черты, которые тебя описывают. До 10 вариантов.',
                    side: 'bottom',
                },
            },
            {
                element: '#tour-interests',
                popover: {
                    title: 'Интересы',
                    description: 'Добавь свои увлечения — так легче найти собеседника.',
                    side: 'bottom',
                },
            },
            {
                element: '#tour-voice',
                popover: {
                    title: 'Голосовое',
                    description: 'Запиши короткое приветствие до 27 секунд — живой голос лучше любого текста.',
                    side: 'top',
                },
            },
            ...(checklistVisible.value ? [{
                element: '#tour-checklist',
                popover: {
                    title: 'Чеклист',
                    description: 'Отслеживай прогресс заполнения профиля.',
                    side: 'top',
                },
            }] : []),
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

    <div class="page-wrap">
        <div class="profile-container">
            <!-- Full-width header -->
            <div class="header-row anim-card" style="--anim-i: 0">
                <ProfileHeader
                    :user="profileUser"
                    :is-owner="isOwner"
                    :voice-url="profileUser.voice_url"
                />
            </div>

            <!-- Two-column grid -->
            <div class="profile-grid">
                <!-- Sidebar -->
                <aside class="profile-sidebar">
                    <div class="anim-card" style="--anim-i: 1">
                        <ProfileChecklist
                            v-if="checklistVisible"
                            :user="profileUser"
                        />
                    </div>
                    <div class="anim-card" style="--anim-i: 2">
                        <ProfileLanguages
                            :languages="profileUser.languages"
                            :is-owner="isOwner"
                        />
                    </div>
                    <div class="anim-card" style="--anim-i: 3">
                        <ProfileTraits
                            :traits="profileUser.traits"
                            :all-traits="allTraits"
                            :is-owner="isOwner"
                            :gender="profileUser.gender"
                        />
                    </div>
                    <div class="anim-card" style="--anim-i: 4">
                        <ProfileInterests
                            :interests="profileUser.interests"
                            :all-categories="allCategories"
                            :is-owner="isOwner"
                        />
                    </div>
                </aside>

                <!-- Main column -->
                <main class="profile-main">
                    <div class="anim-card" style="--anim-i: 1">
                        <ProfileAbout
                            :about="profileUser.about"
                            :is-owner="isOwner"
                        />
                    </div>
                    <div class="anim-card" style="--anim-i: 2">
                        <ProfilePinnedCard
                            :user="profileUser"
                            :is-owner="isOwner"
                        />
                    </div>
                    <div class="anim-card" style="--anim-i: 3">
                        <ProfilePosts
                            :posts="profileUser.posts"
                            :is-owner="isOwner"
                        />
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>

<!-- driver.js dark theme override (non-scoped, applies globally) -->
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
.driver-popover-arrow-side-left.driver-popover-arrow  { border-left-color: #16162a !important; }
.driver-popover-arrow-side-right.driver-popover-arrow { border-right-color: #16162a !important; }
.driver-popover-arrow-side-top.driver-popover-arrow   { border-top-color: #16162a !important; }
.driver-popover-arrow-side-bottom.driver-popover-arrow{ border-bottom-color: #16162a !important; }

/* Fade-in animation */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}

.anim-card {
    animation: fadeInUp 0.45s ease both;
    animation-delay: calc(var(--anim-i, 0) * 0.07s);
}
</style>

<style scoped>
.page-wrap {
    min-height: 100vh;
    background:
        radial-gradient(ellipse at 15% 20%, rgba(200,70,126,0.13) 0%, transparent 55%),
        radial-gradient(ellipse at 85% 10%, rgba(120,60,200,0.09) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(100,60,180,0.12) 0%, transparent 55%),
        radial-gradient(ellipse at 50% 100%, rgba(150,40,90,0.07) 0%, transparent 45%),
        #0d0d18;
    padding: 2rem 1rem 4rem;
}

.profile-container {
    max-width: 960px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.header-row { width: 100%; }

.profile-grid {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 1rem;
    align-items: start;
}

.profile-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.profile-main {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

@media (max-width: 900px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }

    /* Mobile order: main first, then sidebar */
    .profile-sidebar { order: 2; }
    .profile-main    { order: 1; }
}
</style>
