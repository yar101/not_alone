<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from "vue";

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    closeable: {
        type: Boolean,
        default: true,
    },
    maxWidth: {
        type: String,
        default: null,
    },
    noPadding: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: "",
    },
});

const emit = defineEmits(["close"]);

const showSlot = ref(false);
const localShow = ref(false);

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
        <div v-if="showSlot" class="admin-modal-root">
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
                    class="admin-modal-backdrop"
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
                    class="admin-modal-sheet"
                    :style="{
                        ...(maxWidth ? { width: maxWidth, maxWidth } : {}),
                    }"
                >
                    <div class="admin-modal-header" v-if="title">
                        <h3 class="admin-modal-title">{{ title }}</h3>
                        <button class="admin-modal-close" @click="close">✕</button>
                    </div>

                    <!-- Content -->
                    <div
                        class="admin-modal-body"
                        :class="{
                            'admin-modal-body--no-padding': noPadding,
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
.admin-modal-root {
    position: fixed;
    inset: 0;
    z-index: 2000;
    pointer-events: none;
}

/* ── Backdrop ──────────────────────────────────────── */
.admin-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(2, 1, 6, 0.82);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    pointer-events: all;
}

/* ── Sheet ─────────────────────────────────────────── */
.admin-modal-sheet {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translateX(-50%) translateY(-50%);
    width: 60%;
    max-width: 800px; /* Default max-width for admin modals */
    max-height: 90vh;
    background: linear-gradient(
        175deg,
        #121228 0%,
        #0a0a1a 55%,
        #080814 100%
    ); /* Darker theme for admin */
    border: 1px solid rgba(155, 110, 232, 0.3); /* Admin purple border */
    border-radius: 6px; /* Slightly larger border-radius */
    pointer-events: all;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow:
        0 10px 40px rgba(0, 0, 0, 0.6),
        0 0 0 1px rgba(155, 110, 232, 0.2);
}

/* Mobile styles for admin modal sheet */
@media (max-width: 768px) {
    .admin-modal-sheet {
        top: auto;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100% !important; /* Override maxWidth */
        max-width: 100% !important;
        min-height: unset !important;
        height: 88svh; /* Match SiteModal mobile height */
        max-height: 88svh;
        transform: none;
        border-radius: 6px 6px 0 0;
    }
}

/* ── Header ────────────────────────────────────────── */
.admin-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.85rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    background: rgba(0, 0, 0, 0.2);
}

.admin-modal-title {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
    font-weight: 600;
}

.admin-modal-close {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    font-size: 1.2rem;
    padding: 0;
    transition: color 0.15s;
}
.admin-modal-close:hover {
    color: rgba(255, 255, 255, 0.8);
}

/* ── Body ──────────────────────────────────────────── */
.admin-modal-body {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    position: relative;
    z-index: 1;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.08) transparent;
    min-height: 0;
    padding: 1.5rem; /* Admin modals use slightly less padding by default */
}

.admin-modal-body--no-padding {
    padding: 0 !important;
}

@media (max-width: 768px) {
    .admin-modal-body {
        padding: 1rem; /* Adjust padding for mobile */
    }
}

.admin-modal-body::-webkit-scrollbar {
    width: 3px;
}
.admin-modal-body::-webkit-scrollbar-track {
    background: transparent;
}
.admin-modal-body::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 3px;
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
