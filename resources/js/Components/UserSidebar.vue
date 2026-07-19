<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { User, UserFilled, Search, Picture, Star, Brush } from '@element-plus/icons-vue';
import AvatarUploader from '@/Components/AvatarUploader.vue';
import IdolBadge from '@/Components/IdolBadge.vue';
import HelpModal from '@/Components/Site/HelpModal.vue';
import { useTranslations } from '@/composables/useTranslations';
import { useModalHistory } from '@/composables/useModalHistory';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    user:        { type: Object, required: true },
    isIdol:      { type: Boolean, default: false },
    rating:      { default: null },
});
const emit = defineEmits(['update:modelValue']);

const page = usePage();

const isOpen = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const modalHistory = useModalHistory(isOpen, 'usb');

const ratingValue = computed(() => {
    const r = props.rating ?? props.user?.rating;
    return r != null ? Number(r) : null;
});
const ratingLabel = computed(() => ratingValue.value ? Math.round(ratingValue.value).toString() : '0');

const idolStatus = computed(() => page.props.idol_status);
const showBecomeIdol = computed(() => !props.isIdol && idolStatus.value !== 'pending');

function close() { emit('update:modelValue', false); }

function closeForNav() {
    if (modalHistory?.skipHistoryBack) {
        modalHistory.skipHistoryBack();
    }
    emit('update:modelValue', false);
}

const showHelp = ref(false);
function openHelp() { showHelp.value = true; }

watch(() => props.modelValue, (val) => {
    if (val) {
        document.documentElement.classList.add('chat-scroll-locked');
    } else {
        document.documentElement.classList.remove('chat-scroll-locked');
    }
});

function onKey(e) { if (e.key === 'Escape') close(); }
onMounted(() => {
    document.addEventListener('keydown', onKey);
    if (props.modelValue) {
        document.documentElement.classList.add('chat-scroll-locked');
    }
});
onUnmounted(() => {
    document.removeEventListener('keydown', onKey);
    document.documentElement.classList.remove('chat-scroll-locked');
});

const { locale, __, transChoice, switchLocale } = useTranslations();
function ageLabel(n) { return `${n} ${transChoice('search.age.years', n)}`; }
</script>

<template>
    <Teleport to="body">
        <Transition name="sidebar-backdrop">
            <div v-if="modelValue" class="usb-backdrop" @click="close" />
        </Transition>

        <Transition name="sidebar-panel">
            <div v-if="modelValue" class="usb-panel">

                <!-- Hero header -->
                <div class="usb-hero">
                    <button class="usb-close" @click="close" aria-label="Закрыть">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/>
                        </svg>
                    </button>

                    <div class="usb-avatar-wrap">
                        <AvatarUploader :user="user" :size="88" :editable="true" />
                        
                        <!-- Rating badge — only for idols -->
                        <div v-if="isIdol && ratingValue != null" class="usb-rating-badge">
                            <svg class="usb-rating-badge__star" width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                            <span class="usb-rating-badge__value">{{ ratingLabel }}</span>
                        </div>
                    </div>

                    <div class="usb-user-info">
                        <div class="usb-name">{{ user.name }}</div>

                        <div class="usb-badges">
                            <IdolBadge v-if="isIdol" :gender="user.gender" />
                            <span v-if="user.gender" class="usb-badge" :class="'usb-badge--' + user.gender">{{ user.gender === 'female' ? '\u2640\uFE0F' : '\u2642\uFE0F' }}</span>
                            <span v-if="user.age" class="usb-badge usb-badge--age">{{ ageLabel(user.age) }}</span>
                            <span v-if="!isIdol && !user.gender && !user.age" class="usb-badge usb-badge--default">{{ __('nav.user') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Nav -->
                <nav class="usb-nav">
                    <!-- Профиль -->
                    <Link :href="route('profile.show', { user: user.id })" class="usb-item" @click="closeForNav">
                        <el-icon class="usb-item__icon"><User /></el-icon>
                        {{ __('nav.profile') }}
                    </Link>

                    <!-- Поиск -->
                    <Link :href="route('users.search')" class="usb-item" @click="closeForNav">
                        <div class="usb-item__icon search-user-icon">
                            <el-icon><UserFilled /></el-icon>
                            <div class="search-user-icon__badge">
                                <svg class="custom-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="8.5" cy="8.5" r="7.5" />
                                    <line x1="22" y1="22" x2="14.5" y2="14.5" />
                                </svg>
                            </div>
                        </div>
                        {{ __('common.search') }}
                    </Link>

                    <!-- Галерея -->
                    <Link :href="route('gallery.index')" class="usb-item" @click="closeForNav">
                        <el-icon class="usb-item__icon"><Picture /></el-icon>
                        {{ __('common.gallery') }}
                    </Link>

                    <!-- Избранное -->
                    <Link :href="route('tracked.index')" class="usb-item" @click="closeForNav">
                        <el-icon class="usb-item__icon"><Star /></el-icon>
                        {{ __('nav.tracked') }}
                    </Link>

                    <div class="usb-nav-divider" />

                    <!-- Настройки -->
                    <Link :href="route('settings.edit')" class="usb-item" @click="closeForNav">
                        <svg class="usb-item__icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                        {{ __('nav.settings') }}
                    </Link>

                    <button class="usb-item" @click="openHelp">
                        <svg class="usb-item__icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                            <circle cx="12" cy="17" r="0.5" fill="currentColor"/>
                        </svg>
                        {{ __('nav.help') }}
                    </button>
                </nav>

                <!-- Feature cards -->
                <div class="usb-features">
                    <div class="usb-feature-card usb-feature-card--violet">
                        <div class="usb-feature-card__icon-wrap usb-feature-card__icon-wrap--violet">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="7" width="20" height="14" rx="2.5"/>
                                <path d="M16 7V5a2 2 0 0 0-4 0v2"/>
                                <circle cx="12" cy="14" r="2" fill="currentColor" stroke="none" opacity="0.55"/>
                            </svg>
                        </div>
                        <span class="usb-feature-card__label">{{ __('nav.wallet') }}</span>
                    </div>

                    <Link :href="route('orders.index')" class="usb-feature-card usb-feature-card--emerald" @click="closeForNav">
                        <div class="usb-feature-card__icon-wrap usb-feature-card__icon-wrap--emerald">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                                <rect x="9" y="3" width="6" height="4" rx="1"/>
                                <line x1="9" y1="12" x2="15" y2="12"/>
                                <line x1="9" y1="16" x2="12" y2="16"/>
                            </svg>
                        </div>
                        <span class="usb-feature-card__label">{{ __('nav.orders') }}</span>
                    </Link>

                    <Link :href="route('profile.customization')" class="usb-feature-card usb-feature-card--blue usb-feature-card--full" @click="closeForNav">
                        <div class="usb-feature-card__icon-wrap usb-feature-card__icon-wrap--blue">
                            <el-icon size="20"><Brush /></el-icon>
                        </div>
                        <span class="usb-feature-card__label">Кастомизация</span>
                    </Link>
                </div>

                <!-- Footer -->
                <div class="usb-footer">
                    <!-- Become idol button for non-idols -->
                    <div v-if="showBecomeIdol" class="usb-become-footer">
                        <Link :href="route('idol.apply')" class="usb-become-btn" @click="closeForNav">
                            {{ __('layout.become_idol') }}
                        </Link>
                    </div>

                    <!-- Language switcher -->
                    <div class="usb-locale" v-if="false">
                        <button
                            v-for="(label, code) in locale?.available"
                            :key="code"
                            class="usb-locale__btn"
                            :class="{ 'usb-locale__btn--active': locale?.current === code }"
                            @click="switchLocale(code)"
                        >
                            <span class="usb-locale__flag">{{ code === 'ru' ? '🇷🇺' : '🇬🇧' }}</span>
                            {{ label }}
                        </button>
                    </div>
                </div>

            </div>
        </Transition>
    </Teleport>

    <HelpModal :show="showHelp" @close="showHelp = false" />
</template>

<style scoped>
/* ── Backdrop ─────────────────────────────────────────────── */
.usb-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 1100;
}

/* ── Panel ────────────────────────────────────────────────── */
.usb-panel {
    position: fixed;
    top: 1.25rem;
    right: 1.25rem;
    bottom: 1.25rem;
    width: 360px;
    border-radius: 8px;
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 1101;
    display: flex;
    flex-direction: column;
    background: linear-gradient(175deg, #121228 0%, #0a0a1a 55%, #080814 100%);
    border: 1px solid rgba(255, 178, 239, 0.18);
    box-shadow: -14px 0 70px rgba(0, 0, 0, 0.65), -2px 0 20px rgba(255, 178, 239, 0.14);
}

@media (max-width: 480px) {
    .usb-panel {
        top: 1rem;
        right: 1rem;
        left: 1rem;
        bottom: 1rem;
        width: auto;
        border-radius: 16px;
        border: 1px solid rgba(255, 178, 239, 0.28);
    }
}

/* ── Hero header ──────────────────────────────────────────── */
.usb-hero {
    position: relative;
    display: flex;
    flex-direction: row;
    align-items: center;
    padding: 3rem 1.5rem 1.5rem;
    gap: 1.25rem;
    border-bottom: 1px solid rgba(255, 178, 239, 0.1);
    background:
        radial-gradient(ellipse 280px 160px at 50% 0%, rgba(255, 178, 239, 0.1) 0%, transparent 100%);
}

.usb-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    left: auto;
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.55);
    cursor: pointer;
    padding: 0.4rem;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.15s, background 0.15s;
}
.usb-close:hover { color: rgba(255, 255, 255, 0.85); background: rgba(255, 255, 255, 0.06); }

/* Avatar */
.usb-avatar {
    width: 88px;
    height: 88px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    background: rgba(255, 178, 239, 0.15);
    border: 2px solid rgba(255, 178, 239, 0.45);
    box-shadow: none;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.9rem;
}
.usb-avatar__img { width: 100%; height: 100%; object-fit: cover; }
.usb-avatar__initials { font-size: 2rem; font-weight: 600; color: var(--color-base-1); }

/* Name */
.usb-name {
    font-size: 1.05rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.94);
    text-align: left;
    letter-spacing: 0.01em;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Badges */
.usb-badges {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-start;
    gap: 0.35rem;
    margin-top: 0.45rem;
}

.usb-badges :deep(.idol-badge) {
    padding: 0.15rem 0.5rem;
    font-size: 0.72rem;
}

.usb-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.5rem;
    border-radius: 3px;
    font-size: 0.72rem;
    letter-spacing: 0.04em;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.55);
}
.usb-badge--female {
    border-color: rgba(255, 178, 239, 0.3);
    background: rgba(255, 178, 239, 0.06);
    color: rgba(255, 178, 239, 0.85);
}
.usb-badge--male {
    border-color: rgba(100, 210, 255, 0.3);
    background: rgba(100, 210, 255, 0.06);
    color: rgba(100, 210, 255, 0.85);
}
.usb-badge--age {
    color: rgba(255, 255, 255, 0.45);
}
.usb-badge--default {
    font-size: 0.65rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 178, 239, 0.5);
    border-color: rgba(255, 178, 239, 0.12);
    background: rgba(255, 178, 239, 0.06);
}

/* Avatar wrap */
.usb-avatar-wrap {
    position: relative;
    flex-shrink: 0;
}

.usb-user-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

/* ── Rating badge ── */
.usb-rating-badge {
    position: absolute;
    bottom: -6px;
    left: 50%;
    transform: translateX(-50%);
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.2rem 0.6rem;
    background: rgba(20, 15, 30, 0.85);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 178, 239, 0.4);
    border-radius: 4px;
    font-size: 0.75rem;
    color: var(--color-base-1);
    font-weight: 700;
    white-space: nowrap;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    z-index: 10;
}

.usb-rating-badge__star {
    color: var(--color-base-1);
}

.usb-rating-badge__value {
    line-height: 1;
}

/* ── Footer ── */
.usb-footer {
    margin-top: auto;
}

/* ── Become an idol button ── */
.usb-become-footer {
    padding: 0.5rem 1.5rem;
}

.usb-become-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 0.85rem;
    background: linear-gradient(135deg, rgba(255, 178, 239, 0.15) 0%, rgba(100, 210, 255, 0.1) 100%);
    color: var(--color-base-1);
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    border-radius: 8px;
    text-decoration: none;
    border: none;
    box-shadow: 
        0 4px 12px rgba(0, 0, 0, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.08);
    transition: all 0.2s;
}

@media (hover: hover) {
    .usb-become-btn:hover {
        background: linear-gradient(135deg, rgba(255, 178, 239, 0.25) 0%, rgba(100, 210, 255, 0.2) 100%);
        color: #fff;
    }
}

.usb-become-btn:active {
    transform: scale(0.98);
}

/* ── Nav ──────────────────────────────────────────────────── */
.usb-nav {
    padding: 0.65rem 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
    border-bottom: 1px solid rgba(255, 178, 239, 0.08);
}

.usb-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.72rem 0.85rem;
    border-radius: 10px;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.6);
    text-decoration: none;
    transition: background 0.15s, color 0.15s, box-shadow 0.15s;
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
}
.usb-item:hover {
    background: rgba(255, 178, 239, 0.1);
    color: rgba(255, 255, 255, 0.92);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
}
.usb-item__icon { flex-shrink: 0; opacity: 0.65; }
.usb-item__icon.el-icon { font-size: 1.15rem; }

.usb-nav-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.05);
    margin: 0.4rem -0.75rem;
}

/* ── Custom combined icon ─────────────────────────────────── */
.search-user-icon {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.25rem;
    height: 1.25rem;
}
.search-user-icon .el-icon {
    font-size: 1.2rem;
}
.search-user-icon__badge {
    position: absolute;
    bottom: -1px;
    right: -4px;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    background: #111126; /* Цвет, близкий к фону сайдбара */
    border-radius: 50%;
    width: 14px;
    height: 14px;
    transition: background 0.15s;
}
.usb-item:hover .search-user-icon__badge {
    background: color-mix(in srgb, #111126, var(--color-base-1) 12%);
}
.custom-search-icon {
    width: 10px;
    height: 10px;
    color: rgba(255, 255, 255, 0.45);
}

/* ── Feature cards ────────────────────────────────────────── */
.usb-features {
    padding: 0.85rem 0.75rem 1rem;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem;
}

.usb-feature-card {
    position: relative;
    border-radius: 14px;
    padding: 0.85rem 1rem;
    cursor: pointer;
    overflow: hidden;
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 0.65rem;
    text-decoration: none;
}

/* Top stripe */
.usb-feature-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    border-radius: 14px 14px 0 0;
    pointer-events: none;
}
/* Кошелёк — спокойный зелёный */
.usb-feature-card--violet::before {
    background: linear-gradient(90deg, transparent 0%, rgba(52, 211, 130, 0.25) 50%, transparent 100%);
}
.usb-feature-card--violet {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(52, 211, 130, 0.15);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
    transition: background 0.2s, border-color 0.2s;
}
.usb-feature-card--violet:hover {
    background: rgba(52, 211, 130, 0.06);
    border-color: rgba(52, 211, 130, 0.3);
}

/* Заказы — спокойный розовый/основной */
.usb-feature-card--emerald::before {
    background: linear-gradient(90deg, transparent 0%, rgba(255, 178, 239, 0.25) 50%, transparent 100%);
}
.usb-feature-card--emerald {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 178, 239, 0.15);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
    transition: background 0.2s, border-color 0.2s;
}
.usb-feature-card--emerald:hover {
    background: rgba(255, 178, 239, 0.06);
    border-color: rgba(255, 178, 239, 0.3);
}

/* Кастомизация — синий/голубой */
.usb-feature-card--blue::before {
    background: linear-gradient(90deg, transparent 0%, rgba(100, 210, 255, 0.25) 50%, transparent 100%);
}
.usb-feature-card--blue {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(100, 210, 255, 0.15);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
    transition: background 0.2s, border-color 0.2s;
}
.usb-feature-card--blue:hover {
    background: rgba(100, 210, 255, 0.06);
    border-color: rgba(100, 210, 255, 0.3);
}

.usb-feature-card--full {
    grid-column: 1 / -1;
}

/* Icon */
.usb-feature-card__icon-wrap {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 1;
}
.usb-feature-card__icon-wrap--violet {
    background: rgba(52, 211, 130, 0.08);
    border: 1px solid rgba(52, 211, 130, 0.15);
    color: rgba(52, 211, 130, 0.7);
}
.usb-feature-card__icon-wrap--emerald {
    background: rgba(255, 178, 239, 0.08);
    border: 1px solid rgba(255, 178, 239, 0.15);
    color: rgba(255, 178, 239, 0.7);
}
.usb-feature-card__icon-wrap--blue {
    background: rgba(100, 210, 255, 0.08);
    border: 1px solid rgba(100, 210, 255, 0.15);
    color: rgba(100, 210, 255, 0.9);
}

/* Text */
.usb-feature-card__label {
    font-size: 0.88rem;
    font-weight: 600;
    position: relative;
    z-index: 1;
}
.usb-feature-card--violet .usb-feature-card__label  { color: rgba(255, 255, 255, 0.65); }
.usb-feature-card--emerald .usb-feature-card__label { color: rgba(255, 255, 255, 0.65); }

.usb-feature-card--violet:hover .usb-feature-card__label,
.usb-feature-card--emerald:hover .usb-feature-card__label { color: rgba(255, 255, 255, 0.9); }

/* ── Transitions ──────────────────────────────────────────── */
.sidebar-backdrop-enter-active,
.sidebar-backdrop-leave-active { transition: opacity 0.22s ease; }
.sidebar-backdrop-enter-from,
.sidebar-backdrop-leave-to    { opacity: 0; }

.sidebar-panel-enter-active,
.sidebar-panel-leave-active { transition: transform 0.26s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.26s; }
.sidebar-panel-enter-from,
.sidebar-panel-leave-to    { transform: translateX(calc(100% + 2rem)); opacity: 0; }

/* ── Language switcher ────────────────────────────────────── */
.usb-locale {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(255, 178, 239, 0.12);
}

.usb-locale__btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 178, 239, 0.15);
    padding: 0.45rem 0.5rem;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: rgba(255, 255, 255, 0.35);
    cursor: pointer;
    font-family: inherit;
    border-radius: 4px;
    transition: color 0.15s, background 0.15s, border-color 0.15s, box-shadow 0.15s;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
}

.usb-locale__btn:hover {
    color: rgba(255, 255, 255, 0.7);
    background: rgba(255, 255, 255, 0.07);
    border-color: rgba(255, 178, 239, 0.3);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.usb-locale__btn--active {
    color: var(--color-base-1);
    background: rgba(255, 178, 239, 0.1);
    border-color: rgba(255, 178, 239, 0.3);
    box-shadow: inset 0 1px 0 rgba(255, 178, 239, 0.08);
}

.usb-locale__btn--active:hover {
    color: var(--color-base-1);
    background: rgba(255, 178, 239, 0.14);
    box-shadow: inset 0 1px 0 rgba(255, 178, 239, 0.12);
}

.usb-locale__flag {
    font-size: 0.9rem;
    line-height: 1;
}

@media (max-width: 768px) {
    .usb-become-footer {
        padding-bottom: calc(2.5rem + env(safe-area-inset-bottom, 0px));
    }
}
</style>
