<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

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
});

const emit = defineEmits(['close']);
const showSlot = ref(false);
const localShow = ref(false);

watch(
    () => props.show,
    async () => {
        if (props.show) {
            document.body.style.overflow = 'hidden';
            showSlot.value = true;        // монтируем контейнер
            await nextTick();             // ждём, пока Vue отрисует контейнер
            localShow.value = true;       // теперь <Transition> видит вход элемента
        } else {
            localShow.value = false;      // запускаем leave-анимацию
            document.body.style.overflow = '';
            setTimeout(() => {
                showSlot.value = false;
            }, 450);
        }
    },
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

            <!-- Bottom sheet panel -->
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
                    :class="variant === 'pink' ? 'site-modal-pink' : 'site-modal-cyan'"
                >
                    <!-- Close button -->
                    <button
                        v-if="closeable"
                        @click="close"
                        class="site-modal-close"
                        :class="variant === 'pink' ? 'site-modal-close-pink' : 'site-modal-close-cyan'"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <!-- Scrollable content -->
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
    z-index: 50;
    pointer-events: none;
}

.site-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    pointer-events: all;
}

.site-modal-sheet {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translateX(-50%) translateY(-50%);
    width: 40%;
    height: 80%;
    max-height: 90vh;
    background: linear-gradient(160deg, rgb(18, 13, 22) 0%, rgb(8, 8, 12) 100%);
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 12px;
    pointer-events: all;
    display: flex;
    flex-direction: column;
}

@media (max-width: 768px) {
    .site-modal-sheet {
        width: 92%;
    }
}

.site-modal-pink {
    box-shadow:
        0 -20px 60px rgba(200, 70, 126, 0.08),
        0 -1px 0 rgba(200, 70, 126, 0.2);
    border-top-color: rgba(200, 70, 126, 0.2);
}

.site-modal-cyan {
    box-shadow:
        0 -20px 60px rgba(42, 255, 220, 0.06),
        0 -1px 0 rgba(42, 255, 220, 0.18);
    border-top-color: rgba(42, 255, 220, 0.18);
}

.site-modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    color: rgba(255, 255, 255, 0.35);
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0.35rem;
    border-radius: 4px;
    transition: all 0.25s ease;
    z-index: 10;
    flex-shrink: 0;
}

.site-modal-close:hover {
    color: rgba(255, 255, 255, 0.9);
}

.site-modal-close-pink:hover {
    background: rgba(200, 70, 126, 0.15);
}

.site-modal-close-cyan:hover {
    background: rgba(42, 255, 220, 0.12);
}

.site-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 2rem;
    padding-top: 1.5rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
}

.site-modal-body::-webkit-scrollbar {
    width: 4px;
}

.site-modal-body::-webkit-scrollbar-track {
    background: transparent;
}

.site-modal-body::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
}

/* Transitions */
.backdrop-enter-active,
.backdrop-leave-active {
    transition: opacity 0.3s ease;
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
    transition: transform 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94),
                opacity 0.35s ease;
}
.sheet-leave-active {
    transition: transform 0.3s cubic-bezier(0.55, 0, 1, 0.45),
                opacity 0.25s ease;
}
.sheet-enter-from,
.sheet-leave-to {
    transform: translateX(-50%) translateY(calc(-50% + 60px));
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
        transform: translateX(-50%) translateY(calc(-50% + 60px));
    }
    .sheet-enter-to,
    .sheet-leave-from {
        transform: translateX(-50%) translateY(-50%);
    }
}
</style>
