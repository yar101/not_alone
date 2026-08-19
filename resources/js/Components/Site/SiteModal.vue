<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch, computed } from "vue";
import { useTranslations } from "@/composables/useTranslations";
import { useModalHistory } from "@/composables/useModalHistory";

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
        default: "pink",
        validator: (v) => ["pink", "blue"].includes(v),
    },
    compact: {
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
    fill: {
        type: Boolean,
        default: false,
    },
    noPadding: {
        type: Boolean,
        default: false,
    },
    noHistory: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["close"]);
const showSlot = ref(false);
const localShow = ref(false);

const isOpen = computed({
    get: () => props.show,
    set: (val) => {
        if (!val) emit("close");
    },
});

const modalHistory = props.noHistory ? null : useModalHistory(isOpen, "sm");

defineExpose({
    skipHistoryBack: () => modalHistory?.skipHistoryBack?.(),
});

watch(
    () => props.show,
    async () => {
        if (props.show) {
            document.body.style.overflow = "hidden";
            showSlot.value = true;
            await nextTick();
            localShow.value = true;
        } else {
            localShow.value = false;
            document.body.style.overflow = "";
            setTimeout(() => {
                showSlot.value = false;
            }, 180);
        }
    },
    { immediate: true },
);

const close = () => {
    if (props.closeable) {
        emit("close");
    }
};

const closeOnEscape = (e) => {
    if (e.key === "Escape") {
        e.preventDefault();
        if (props.show) {
            close();
        }
    }
};

onMounted(() => {
    document.addEventListener("keydown", closeOnEscape);
});

onUnmounted(() => {
    document.removeEventListener("keydown", closeOnEscape);
    document.body.style.overflow = "";
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
                        variant === 'pink'
                            ? 'site-modal-pink'
                            : 'site-modal-blue',
                        compact ? 'site-modal-sheet--compact' : '',
                    ]"
                    :style="{
                        ...(maxWidth ? { width: maxWidth, maxWidth } : {}),
                        ...(minHeight ? { minHeight } : {}),
                    }"
                >
                    <!-- Ambient orbs -->
                    <div
                        class="site-modal-ambient"
                        :class="
                            variant === 'pink' ? 'ambient-pink' : 'ambient-blue'
                        "
                    />

                    <!-- Content -->
                    <div
                        class="site-modal-body"
                        :class="{
                            'site-modal-body--fill': fill,
                            'site-modal-body--no-padding': noPadding,
                        }"
                    >
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
    display: flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 768px) {
    .site-modal-root {
        align-items: flex-end;
    }
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
    position: relative;
    width: 60%;
    height: 80%;
    max-height: 90vh;
    background: rgba(10, 7, 20, 0.7);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-top: none;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.14);
    border-radius: 12px;
    pointer-events: all;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

@media (max-width: 768px) {
    .site-modal-sheet {
        width: 100% !important;
        max-width: 100% !important;
        min-height: unset !important;
        height: 88svh;
        max-height: 88svh;
        border-radius: 16px 16px 0 0;
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
    box-shadow: inset 0 1px 0 rgba(255, 178, 239, 0.3);
}

.site-modal-blue {
    box-shadow:
        0 0 0 1px color-mix(in srgb, var(--color-base-2), transparent 94%),
        0 -30px 80px color-mix(in srgb, var(--color-base-2), transparent 92%),
        0 40px 100px rgba(0, 0, 0, 0.6),
        inset 0 1px 0 color-mix(in srgb, var(--color-base-2), transparent 45%),
        inset 0 0 80px color-mix(in srgb, var(--color-base-2), transparent 97%);
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
    content: "";
    position: absolute;
    border-radius: 50%;
    filter: blur(50px);
}

.ambient-pink::before {
    top: -80px;
    left: -60px;
    width: 320px;
    height: 320px;
    background: radial-gradient(
        circle,
        rgba(255, 178, 239, 0.18) 0%,
        transparent 70%
    );
}

.ambient-pink::after {
    bottom: -100px;
    right: -80px;
    width: 380px;
    height: 380px;
    background: radial-gradient(
        circle,
        rgba(255, 178, 239, 0.12) 0%,
        transparent 70%
    );
}

.ambient-blue::before {
    top: -80px;
    right: -60px;
    width: 300px;
    height: 300px;
    background: radial-gradient(
        circle,
        color-mix(in srgb, var(--color-base-2), transparent 85%) 0%,
        transparent 70%
    );
}

.ambient-blue::after {
    bottom: -100px;
    left: -80px;
    width: 360px;
    height: 360px;
    background: radial-gradient(
        circle,
        color-mix(in srgb, var(--color-base-2), transparent 88%) 0%,
        transparent 70%
    );
}

/* ── Body ──────────────────────────────────────────── */
.site-modal-body {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    position: relative;
    z-index: 1;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.08) transparent;
    min-height: 0;
    padding: 2rem;
    padding-top: 1.5rem;
}

.site-modal-body--fill {
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.site-modal-body--no-padding {
    padding: 0;
}

@media (max-width: 768px) {
    .site-modal-body {
        padding: 1.25rem;
        padding-top: 1.5rem;
    }
    .site-modal-body--no-padding {
        padding: 0 !important;
    }
}

.site-modal-body::-webkit-scrollbar {
    width: 3px;
}
.site-modal-body::-webkit-scrollbar-track {
    background: transparent;
}
.site-modal-body::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 8px;
}

/* ── Transitions ───────────────────────────────────── */
.backdrop-enter-active,
.backdrop-leave-active {
    transition: opacity 0.12s ease;
}
.backdrop-enter-from,
.backdrop-leave-to {
    opacity: 0;
}
.backdrop-enter-to,
.backdrop-leave-from {
    opacity: 1;
}

.sheet-enter-active {
    transition:
        transform 0.14s cubic-bezier(0.2, 0, 0.2, 1),
        opacity 0.12s ease;
}
.sheet-leave-active {
    transition:
        transform 0.12s cubic-bezier(0.4, 0, 1, 1),
        opacity 0.12s ease;
}
.sheet-enter-from,
.sheet-leave-to {
    transform: translateY(14px);
    opacity: 0;
}
.sheet-enter-to,
.sheet-leave-from {
    transform: translateY(0);
    opacity: 1;
}

@media (max-width: 768px) {
    .sheet-enter-from,
    .sheet-leave-to {
        transform: translateY(100%);
        opacity: 1;
    }
    .sheet-enter-to,
    .sheet-leave-from {
        transform: translateY(0);
        opacity: 1;
    }
}
</style>
