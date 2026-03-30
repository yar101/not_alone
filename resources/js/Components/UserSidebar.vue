<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import AvatarUploader from '@/Components/AvatarUploader.vue';
import IdolBadge from '@/Components/IdolBadge.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    user:        { type: Object, required: true },
    isIdol:      { type: Boolean, default: false },
    rating:      { default: null },
});
const emit = defineEmits(['update:modelValue']);

function close() { emit('update:modelValue', false); }

function onKey(e) { if (e.key === 'Escape') close(); }
onMounted(() => document.addEventListener('keydown', onKey));
onUnmounted(() => document.removeEventListener('keydown', onKey));

const agePR = new Intl.PluralRules('ru');
const ageForms = { one: 'год', few: 'года', many: 'лет', other: 'лет' };
function ageLabel(n) { return `${n} ${ageForms[agePR.select(n)]}`; }

const ratingValue  = computed(() => props.rating != null ? Number(props.rating) : null);
const ratingLabel  = computed(() => ratingValue.value != null ? ratingValue.value : '—');
const ratingPct    = computed(() => ratingValue.value != null ? Math.min(ratingValue.value / 100, 1) * 100 : 0);
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
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/>
                        </svg>
                    </button>

                    <AvatarUploader :user="user" :size="88" :editable="true" />

                    <div class="usb-name">{{ user.name }}</div>
                    <div class="usb-badges">
                        <IdolBadge v-if="isIdol" />
                        <span v-if="user.gender" class="usb-badge" :class="'usb-badge--' + user.gender">{{ user.gender === 'female' ? '\u2640\uFE0F' : '\u2642\uFE0F' }}</span>
                        <span v-if="user.age" class="usb-badge usb-badge--age">{{ ageLabel(user.age) }}</span>
                        <span v-if="!isIdol && !user.gender && !user.age" class="usb-badge usb-badge--default">Пользователь</span>
                    </div>

                    <!-- Rating block — only for idols -->
                    <div v-if="isIdol" class="usb-rating">
                        <div class="usb-rating__top">
                            <svg class="usb-rating__star" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                            <span class="usb-rating__value">{{ ratingLabel }}</span>
                            <span class="usb-rating__max">/ 100</span>
                        </div>
                        <div class="usb-rating__bar-track">
                            <div class="usb-rating__bar-fill" :style="{ width: ratingPct + '%' }" />
                        </div>
                    </div>
                </div>

                <!-- Nav -->
                <nav class="usb-nav">
                    <Link :href="route('profile.show', { user: user.id })" class="usb-item" @click="close">
                        <svg class="usb-item__icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                        </svg>
                        Профиль
                    </Link>
                    <Link :href="route('settings.edit')" class="usb-item" @click="close">
                        <svg class="usb-item__icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                        Настройки
                    </Link>
                </nav>

                <!-- Feature cards -->
                <div class="usb-features">
                    <div class="usb-feature-card usb-feature-card--violet">
                        <div class="usb-feature-card__glow" />
                        <div class="usb-feature-card__icon-wrap usb-feature-card__icon-wrap--violet">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="7" width="20" height="14" rx="2.5"/>
                                <path d="M16 7V5a2 2 0 0 0-4 0v2"/>
                                <circle cx="12" cy="14" r="2" fill="currentColor" stroke="none" opacity="0.55"/>
                            </svg>
                        </div>
                        <span class="usb-feature-card__label">Кошелёк</span>
                    </div>

                    <Link :href="route('orders.index')" class="usb-feature-card usb-feature-card--emerald" @click="close">
                        <div class="usb-feature-card__glow" />
                        <div class="usb-feature-card__icon-wrap usb-feature-card__icon-wrap--emerald">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                                <rect x="9" y="3" width="6" height="4" rx="1"/>
                                <line x1="9" y1="12" x2="15" y2="12"/>
                                <line x1="9" y1="16" x2="12" y2="16"/>
                            </svg>
                        </div>
                        <span class="usb-feature-card__label">Заказы</span>
                    </Link>
                </div>

            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* ── Backdrop ─────────────────────────────────────────────── */
.usb-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 200;
}

/* ── Panel ────────────────────────────────────────────────── */
.usb-panel {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: 360px;
    z-index: 201;
    display: flex;
    flex-direction: column;
    background: linear-gradient(175deg, #121228 0%, #0a0a1a 55%, #080814 100%);
    border-left: 1px solid rgba(110, 110, 210, 0.18);
    box-shadow: -14px 0 70px rgba(0, 0, 0, 0.65), -2px 0 20px rgba(70, 50, 170, 0.14);
}

/* ── Hero header ──────────────────────────────────────────── */
.usb-hero {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 2.2rem 1.5rem 1.5rem;
    border-bottom: 1px solid rgba(110, 110, 210, 0.1);
    background:
        radial-gradient(ellipse 280px 160px at 50% 0%, rgba(100, 80, 210, 0.1) 0%, transparent 100%);
}

.usb-close {
    position: absolute;
    top: 1rem;
    left: 1rem;
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.55);
    cursor: pointer;
    padding: 0.3rem;
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
    background: rgba(110, 110, 210, 0.15);
    border: 2px solid rgba(120, 100, 230, 0.45);
    box-shadow: none;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.9rem;
}
.usb-avatar__img { width: 100%; height: 100%; object-fit: cover; }
.usb-avatar__initials { font-size: 2rem; font-weight: 600; color: #9090e0; }

/* Name */
.usb-name {
    font-size: 1.05rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.94);
    text-align: center;
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
    justify-content: center;
    gap: 0.35rem;
    margin-top: 0.45rem;
}

.usb-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.28rem 0.75rem;
    border-radius: 3px;
    font-size: 0.85rem;
    letter-spacing: 0.04em;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.55);
}
.usb-badge--female {
    border-color: rgba(160, 160, 255, 0.3);
    background: rgba(160, 160, 255, 0.06);
    color: rgba(160, 160, 255, 0.85);
}
.usb-badge--male {
    border-color: rgba(167, 139, 250, 0.3);
    background: rgba(167, 139, 250, 0.06);
    color: rgba(167, 139, 250, 0.85);
}
.usb-badge--age {
    color: rgba(255, 255, 255, 0.45);
}
.usb-badge--default {
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(160, 140, 230, 0.5);
    border-color: rgba(120, 100, 220, 0.12);
    background: rgba(110, 90, 210, 0.06);
}

/* ── Rating ───────────────────────────────────────────────── */
.usb-rating {
    margin-top: 1rem;
    width: 100%;
    padding: 0 0.1rem;
}

.usb-rating__top {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-bottom: 0.5rem;
}

.usb-rating__star {
    color: rgba(160, 120, 255, 0.9);
    flex-shrink: 0;
    filter: drop-shadow(0 0 5px rgba(140, 90, 255, 0.5));
}

.usb-rating__value {
    font-size: 1.05rem;
    font-weight: 700;
    background: linear-gradient(90deg, #c084fc, #818cf8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: 0.02em;
    line-height: 1;
}

.usb-rating__max {
    font-size: 0.72rem;
    color: rgba(160, 130, 220, 0.35);
    letter-spacing: 0.03em;
    margin-top: 0.1rem;
}

.usb-rating__bar-track {
    height: 3px;
    background: rgba(140, 100, 255, 0.1);
    border-radius: 999px;
    overflow: hidden;
}

.usb-rating__bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #7c3aed 0%, #a78bfa 50%, #c084fc 100%);
    border-radius: 999px;
    transition: width 0.5s ease;
}

/* ── Nav ──────────────────────────────────────────────────── */
.usb-nav {
    padding: 0.65rem 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
    border-bottom: 1px solid rgba(110, 110, 210, 0.08);
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
    transition: background 0.15s, color 0.15s;
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
}
.usb-item:hover {
    background: rgba(110, 110, 210, 0.1);
    color: rgba(255, 255, 255, 0.92);
}
.usb-item__icon { flex-shrink: 0; opacity: 0.65; }

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
/* Кошелёк — зелёный */
.usb-feature-card--violet::before {
    background: linear-gradient(90deg, transparent 0%, rgba(52, 211, 130, 0.5) 50%, transparent 100%);
}
.usb-feature-card--violet {
    background: linear-gradient(145deg, rgba(8, 38, 24, 0.92) 0%, rgba(4, 22, 14, 0.96) 100%);
    border: 1px solid rgba(52, 200, 120, 0.2);
    box-shadow: 0 4px 20px rgba(30, 170, 90, 0.1), inset 0 1px 0 rgba(80, 220, 145, 0.06);
    transition: background 0.2s, border-color 0.2s, box-shadow 0.2s;
}
.usb-feature-card--violet:hover {
    background: linear-gradient(145deg, rgba(11, 50, 32, 0.94) 0%, rgba(6, 30, 18, 0.97) 100%);
    border-color: rgba(60, 210, 130, 0.32);
    box-shadow: 0 4px 24px rgba(30, 170, 90, 0.18), inset 0 1px 0 rgba(90, 230, 155, 0.09);
}

/* Заказы — полуночно-фиолетовые */
.usb-feature-card--emerald::before {
    background: linear-gradient(90deg, transparent 0%, rgba(110, 80, 220, 0.45) 50%, transparent 100%);
}
.usb-feature-card--emerald {
    background: linear-gradient(145deg, rgba(20, 12, 54, 0.93) 0%, rgba(12, 6, 36, 0.96) 100%);
    border: 1px solid rgba(110, 80, 220, 0.22);
    box-shadow: 0 4px 20px rgba(90, 55, 200, 0.11), inset 0 1px 0 rgba(150, 120, 248, 0.06);
    transition: background 0.2s, border-color 0.2s, box-shadow 0.2s;
}
.usb-feature-card--emerald:hover {
    background: linear-gradient(145deg, rgba(26, 15, 66, 0.95) 0%, rgba(16, 9, 46, 0.98) 100%);
    border-color: rgba(122, 92, 232, 0.33);
    box-shadow: 0 4px 24px rgba(90, 55, 200, 0.18), inset 0 1px 0 rgba(160, 130, 252, 0.09);
}

/* Glow */
.usb-feature-card__glow {
    position: absolute;
    inset: -20px;
    border-radius: 50%;
    opacity: 0.1;
    pointer-events: none;
    filter: blur(28px);
}
.usb-feature-card--violet .usb-feature-card__glow {
    background: radial-gradient(circle, rgba(50, 200, 120, 0.9) 0%, transparent 70%);
}
.usb-feature-card--emerald .usb-feature-card__glow {
    background: radial-gradient(circle, rgba(100, 60, 210, 0.9) 0%, transparent 70%);
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
    background: rgba(50, 200, 120, 0.1);
    border: 1px solid rgba(60, 210, 130, 0.22);
    color: rgba(80, 220, 150, 0.9);
}
.usb-feature-card__icon-wrap--emerald {
    background: rgba(100, 65, 220, 0.11);
    border: 1px solid rgba(115, 80, 230, 0.22);
    color: rgba(155, 125, 248, 0.9);
}

/* Text */
.usb-feature-card__label {
    font-size: 0.88rem;
    font-weight: 600;
    position: relative;
    z-index: 1;
}
.usb-feature-card--violet .usb-feature-card__label  { color: rgba(80, 220, 150, 0.92); }
.usb-feature-card--emerald .usb-feature-card__label { color: rgba(160, 130, 250, 0.92); }

.usb-feature-card__sub {
    font-size: 0.68rem;
    letter-spacing: 0.02em;
    position: relative;
    z-index: 1;
    margin-top: -0.3rem;
}
.usb-feature-card--violet .usb-feature-card__sub    { color: rgba(50, 185, 120, 0.38); }
.usb-feature-card--emerald .usb-feature-card__sub   { color: rgba(110, 80, 200, 0.38); }

/* ── Transitions ──────────────────────────────────────────── */
.sidebar-backdrop-enter-active,
.sidebar-backdrop-leave-active { transition: opacity 0.22s ease; }
.sidebar-backdrop-enter-from,
.sidebar-backdrop-leave-to    { opacity: 0; }

.sidebar-panel-enter-active,
.sidebar-panel-leave-active { transition: transform 0.26s cubic-bezier(0.4, 0, 0.2, 1); }
.sidebar-panel-enter-from,
.sidebar-panel-leave-to    { transform: translateX(100%); }
</style>
