<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    closeable: {
        type: Boolean,
        default: true,
    },
    variant: {
        type: String,
        default: 'pink',
        validator: (v) => ['pink', 'cyan'].includes(v),
    },
    compact: {
        type: Boolean,
        default: false,
    },
    hideCloseBtn: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: null,
    },
    minHeight: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['close']);
const showSlot = ref(false);
const localShow = ref(false);

watch(
    () => props.show,
    async () => {
        if (props.show) {
            document.body.style.overflow = 'hidden';
            showSlot.value = true;
            await nextTick();
            localShow.value = true;
        } else {
            localShow.value = false;
            document.body.style.overflow = '';
            setTimeout(() => {
                showSlot.value = false;
            }, 180);
        }
    },
    { immediate: true },
);

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const closeOnEscape = (e) => {
    if (e.key === 'Escape') {
        e.preventDefault();
        if (props.show) {
            close();
        }
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div v-if="showSlot" class="site-modal-root">
            <!-- Backdrop -->
            <Transition
                enter-active-class="backdrop-enter-active"
                enter-from-class="backdrop-enter-from"
                enter-to-class="backdrop-enter-to"
                leave-active-class="backdrop-leave-active"
                leave-from-class="backdrop-leave-from"
                leave-to-class="backdrop-leave-to"
            >
                <div
                    v-if="localShow"
                    class="site-modal-backdrop"
                    @click="close"
                />
            </Transition>

            <!-- Panel -->
            <Transition
                enter-active-class="sheet-enter-active"
                enter-from-class="sheet-enter-from"
                enter-to-class="sheet-enter-to"
                leave-active-class="sheet-leave-active"
                leave-from-class="sheet-leave-from"
                leave-to-class="sheet-leave-to"
            >
                <div
                    v-if="localShow"
                    class="site-modal-sheet"
                    :class="[
                        variant === 'pink' ? 'site-modal-pink' : 'site-modal-cyan',
                        compact ? 'site-modal-sheet--compact' : ''
                    ]"
                    :style="{ ...(maxWidth ? { width: maxWidth, maxWidth } : {}), ...(minHeight ? { minHeight } : {}) }"
                >
                    <!-- Ambient orbs -->
                    <div class="site-modal-ambient" :class="variant === 'pink' ? 'ambient-pink' : 'ambient-cyan'" />

                    <!-- Close button -->
                    <button
                        v-if="closeable && !hideCloseBtn"
                        @click="close"
                        class="site-modal-close"
                        :class="variant === 'pink' ? 'site-modal-close-pink' : 'site-modal-close-cyan'"
                        :aria-label="__('common.close')"
                    >
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <!-- Content -->
                    <div class="site-modal-body">
                        <slot />
                    </div>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>

<style scoped>
.site-modal-root {
    position: fixed;
    inset: 0;
    z-index: 2000;
    pointer-events: none;
}

/* ── Backdrop ──────────────────────────────────────── */
.site-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(2, 1, 6, 0.82);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    pointer-events: all;
}

/* ── Sheet ─────────────────────────────────────────── */
.site-modal-sheet {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translateX(-50%) translateY(-50%);
    width: 60%;
    height: 80%;
    max-height: 90vh;
    background:
        linear-gradient(135deg, rgba(110, 110, 210, 0.05) 0%, transparent 45%),
        linear-gradient(160deg, rgb(16, 11, 20) 0%, rgb(7, 6, 11) 100%);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 3px;
    pointer-events: all;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

@media (max-width: 768px) {
    .site-modal-sheet {
        top: auto;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        height: 88svh;
        max-height: 88svh;
        transform: none;
        border-radius: 3px 3px 0 0;
    }
}

@media (min-width: 769px) {
    .site-modal-sheet--compact {
        width: clamp(440px, 36vw, 620px);
        height: auto;
        max-height: 88vh;
    }
}

.site-modal-pink {
    border-top-color: rgba(110, 110, 210, 0.3);
    box-shadow:
        0 0 0 1px rgba(110, 110, 210, 0.08),
        0 -30px 80px rgba(110, 110, 210, 0.12),
        0 40px 100px rgba(0, 0, 0, 0.6),
        inset 0 1px 0 rgba(110, 110, 210, 0.18),
        inset 0 0 80px rgba(110, 110, 210, 0.04);
}

.site-modal-cyan {
    border-top-color: rgba(42, 255, 220, 0.25);
    box-shadow:
        0 0 0 1px rgba(42, 255, 220, 0.06),
        0 -30px 80px rgba(42, 255, 220, 0.08),
        0 40px 100px rgba(0, 0, 0, 0.6),
        inset 0 1px 0 rgba(42, 255, 220, 0.15),
        inset 0 0 80px rgba(42, 255, 220, 0.03);
}

/* ── Ambient orbs ──────────────────────────────────── */
.site-modal-ambient {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 0;
}

.site-modal-ambient::before,
.site-modal-ambient::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    filter: blur(50px);
}

.ambient-pink::before {
    top: -80px;
    left: -60px;
    width: 320px;
    height: 320px;
    background: radial-gradient(circle, rgba(110, 110, 210, 0.18) 0%, transparent 70%);
    animation: orb-drift-a 9s ease-in-out infinite alternate;
}

.ambient-pink::after {
    bottom: -100px;
    right: -80px;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(60, 60, 180, 0.12) 0%, transparent 70%);
    animation: orb-drift-b 13s ease-in-out infinite alternate;
}

.ambient-cyan::before {
    top: -80px;
    right: -60px;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(42, 255, 220, 0.1) 0%, transparent 70%);
    animation: orb-drift-a 9s ease-in-out infinite alternate;
}

.ambient-cyan::after {
    bottom: -100px;
    left: -80px;
    width: 360px;
    height: 360px;
    background: radial-gradient(circle, rgba(0, 120, 200, 0.1) 0%, transparent 70%);
    animation: orb-drift-b 13s ease-in-out infinite alternate;
}

@keyframes orb-drift-a {
    from { transform: translate(0, 0) scale(1); }
    to   { transform: translate(25px, 18px) scale(1.12); }
}

@keyframes orb-drift-b {
    from { transform: translate(0, 0) scale(1); }
    to   { transform: translate(-20px, -25px) scale(1.08); }
}


/* ── Close button ──────────────────────────────────── */
.site-modal-close {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    width: 36px;
    height: 36px;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: rgba(255, 255, 255, 0.28);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.18s ease, background 0.18s ease, transform 0.22s ease;
    z-index: 10;
    flex-shrink: 0;
}

.site-modal-close:hover {
    color: rgba(255, 255, 255, 0.75);
    background: rgba(255, 255, 255, 0.06);
    transform: rotate(90deg);
}

.site-modal-close-pink:hover {
    color: rgba(220, 100, 145, 0.9);
    background: rgba(110, 110, 210, 0.1);
}

.site-modal-close-cyan:hover {
    color: rgba(42, 255, 220, 0.8);
    background: rgba(42, 255, 220, 0.07);
}

/* ── Body ──────────────────────────────────────────── */
.site-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 2rem;
    padding-top: 3rem;
    position: relative;
    z-index: 1;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.08) transparent;
}

@media (max-width: 768px) {
    .site-modal-body {
        padding: 1.25rem;
        padding-top: 3rem;
    }
}

.site-modal-body::-webkit-scrollbar { width: 3px; }
.site-modal-body::-webkit-scrollbar-track { background: transparent; }
.site-modal-body::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 3px;
}

/* ── Transitions ───────────────────────────────────── */
.backdrop-enter-active,
.backdrop-leave-active { transition: opacity 0.12s ease; }
.backdrop-enter-from,
.backdrop-leave-to { opacity: 0; }
.backdrop-enter-to,
.backdrop-leave-from { opacity: 1; }

.sheet-enter-active {
    transition: transform 0.14s cubic-bezier(0.2, 0, 0.2, 1), opacity 0.12s ease;
}
.sheet-leave-active {
    transition: transform 0.12s cubic-bezier(0.4, 0, 1, 1), opacity 0.12s ease;
}
.sheet-enter-from,
.sheet-leave-to {
    transform: translateX(-50%) translateY(calc(-50% + 14px));
    opacity: 0;
}
.sheet-enter-to,
.sheet-leave-from {
    transform: translateX(-50%) translateY(-50%);
    opacity: 1;
}

@media (max-width: 768px) {
    .sheet-enter-from,
    .sheet-leave-to { transform: translateY(100%); opacity: 1; }
    .sheet-enter-to,
    .sheet-leave-from { transform: translateY(0); opacity: 1; }
}
</style>
