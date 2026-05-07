<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import AvatarUploader from '@/Components/AvatarUploader.vue';
import IdolBadge from '@/Components/IdolBadge.vue';
import HelpModal from '@/Components/Site/HelpModal.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    user:        { type: Object, required: true },
    isIdol:      { type: Boolean, default: false },
    rating:      { default: null },
});
const emit = defineEmits(['update:modelValue']);

function close() { emit('update:modelValue', false); }

function closeForNav() {
    usbPushed = false;
    emit('update:modelValue', false);
}

const showHelp = ref(false);
function openHelp() { showHelp.value = true; }

// ── History API (back gesture) ───────────────────────────
let usbPushed = false;

const onPopstate = (e) => {
    if (!usbPushed) return;
    if (e.state?.usb !== true) {
        usbPushed = false;
        emit('update:modelValue', false);
    }
};

watch(() => props.modelValue, (val, oldVal) => {
    if (val) {
        history.pushState({ usb: true }, '');
        usbPushed = true;
    }
    if (!val && oldVal && usbPushed) {
        usbPushed = false;
        history.go(-1);
    }
});

function onKey(e) { if (e.key === 'Escape') close(); }
onMounted(() => {
    document.addEventListener('keydown', onKey);
    window.addEventListener('popstate', onPopstate);
});
onUnmounted(() => {
    document.removeEventListener('keydown', onKey);
    window.removeEventListener('popstate', onPopstate);
});

const agePR = new Intl.PluralRules('ru');
const ageForms = { one: 'год', few: 'года', many: 'лет', other: 'лет' };
function ageLabel(n) { return `${n} ${ageForms[agePR.select(n)]}`; }

const ratingValue  = computed(() => props.rating != null ? Number(props.rating) : null);
const ratingLabel  = computed(() => ratingValue.value != null ? ratingValue.value : '—');
const ratingPct    = computed(() => ratingValue.value != null ? Math.min(ratingValue.value / 100, 1) * 100 : 0);

const { locale, __, switchLocale } = useTranslations();
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
                        <span v-if="!isIdol && !user.gender && !user.age" class="usb-badge usb-badge--default">{{ __('nav.user') }}</span>
                    </div>

                    <!-- Rating block — only for idols -->
                    <div v-if="isIdol && ratingValue != null" class="usb-rating">
                        <svg class="usb-rating__star" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                        <span class="usb-rating__value">{{ ratingLabel }}</span>
                    </div>
                </div>

                <!-- Nav -->
                <nav class="usb-nav">
                    <Link :href="route('profile.show', { user: user.id })" class="usb-item" @click="closeForNav">
                        <svg class="usb-item__icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                        </svg>
                        {{ __('nav.profile') }}
                    </Link>
                    <Link :href="route('settings.edit')" class="usb-item" @click="closeForNav">
                        <svg class="usb-item__icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                        {{ __('nav.settings') }}
                    </Link>
                    <Link :href="route('gallery.index')" class="usb-item" @click="closeForNav">
                        <svg class="usb-item__icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                            <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                        </svg>
                        {{ __('nav.gallery') }}
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
                </div>

                <!-- Language switcher -->
                <div class="usb-locale">
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
        </Transition>
    </Teleport>

    <HelpModal :show="showHelp" @close="showHelp = false" />
</template>

<style scoped>
/* ── Backdrop ─────────────────────────────────────────────── */
.usb-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 1100;
}

/* ── Panel ────────────────────────────────────────────────── */
.usb-panel {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: 360px;
    z-index: 1101;
    display: flex;
    flex-direction: column;
    background: linear-gradient(175deg, #121228 0%, #0a0a1a 55%, #080814 100%);
    border-left: 1px solid rgba(255, 178, 239, 0.18);
    box-shadow: -14px 0 70px rgba(0, 0, 0, 0.65), -2px 0 20px rgba(255, 178, 239, 0.14);
}

/* ── Hero header ──────────────────────────────────────────── */
.usb-hero {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 2.2rem 1.5rem 1.5rem;
    border-bottom: 1px solid rgba(255, 178, 239, 0.1);
    background:
        radial-gradient(ellipse 280px 160px at 50% 0%, rgba(255, 178, 239, 0.1) 0%, transparent 100%);
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
    border-color: rgba(255, 178, 239, 0.3);
    background: rgba(255, 178, 239, 0.06);
    color: rgba(255, 178, 239, 0.85);
}
.usb-badge--male {
    border-color: rgba(255, 178, 239, 0.3);
    background: rgba(255, 178, 239, 0.06);
    color: rgba(255, 178, 239, 0.85);
}
.usb-badge--age {
    color: rgba(255, 255, 255, 0.45);
}
.usb-badge--default {
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 178, 239, 0.5);
    border-color: rgba(255, 178, 239, 0.12);
    background: rgba(255, 178, 239, 0.06);
}

/* ── Rating ───────────────────────────────────────────────── */
.usb-rating {
    position: absolute;
    top: 1.15rem;
    right: 1.15rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.22rem 0.6rem;
    border-radius: 6px;
    background: rgba(255, 178, 239, 0.08);
    border: 1px solid rgba(255, 178, 239, 0.2);
    z-index: 1;
}

.usb-rating__star {
    color: var(--color-base-1);
    flex-shrink: 0;
    filter: drop-shadow(0 0 4px rgba(255, 178, 239, 0.3));
}

.usb-rating__value {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--color-base-1);
    letter-spacing: 0.02em;
    line-height: 1;
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
.sidebar-panel-leave-active { transition: transform 0.26s cubic-bezier(0.4, 0, 0.2, 1); }
.sidebar-panel-enter-from,
.sidebar-panel-leave-to    { transform: translateX(100%); }

/* ── Language switcher ────────────────────────────────────── */
.usb-locale {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(255, 178, 239, 0.12);
    margin-top: auto;
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
</style>
