<script setup>
import { computed, watch, onMounted, onUnmounted } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    show: {
        type: Boolean,
        default: undefined,
    },
    open: {
        type: Boolean,
        default: undefined,
    },
    modelValue: {
        type: Boolean,
        default: undefined,
    },
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
    maxWidth: {
        type: String,
        default: '',
    },
    width: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: '', // 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | 'full'
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close', 'update:modelValue']);

const isOpen = computed(() => {
    if (props.open !== undefined) return props.open;
    if (props.show !== undefined) return props.show;
    if (props.modelValue !== undefined) return props.modelValue;
    return false;
});

const displaySubtitle = computed(() => props.subtitle || props.description || '');

const isExplicitDimension = computed(() => {
    const w = props.width;
    return w && (w.endsWith('px') || w.endsWith('%') || w.endsWith('rem') || w.endsWith('vw'));
});

const drawerStyle = computed(() => {
    if (isExplicitDimension.value) {
        return { width: props.width, maxWidth: '100vw' };
    }
    return {};
});

const drawerClasses = computed(() => {
    if (isExplicitDimension.value) {
        return '';
    }
    if (props.width) {
        return props.width.startsWith('max-w-') ? props.width : `max-w-[${props.width}]`;
    }
    if (props.maxWidth) {
        return props.maxWidth;
    }
    if (props.size) {
        const sizes = {
            sm: 'max-w-sm',
            md: 'max-w-md',
            lg: 'max-w-lg',
            xl: 'max-w-xl',
            '2xl': 'max-w-2xl',
            '3xl': 'max-w-3xl',
            full: 'max-w-full',
        };
        return sizes[props.size] || `max-w-${props.size}`;
    }
    return 'max-w-xl';
});

function close() {
    if (props.closeable) {
        emit('close');
        emit('update:modelValue', false);
    }
}

function onKeyDown(e) {
    if (e.key === 'Escape' && isOpen.value) {
        close();
    }
}

watch(isOpen, (val) => {
    if (val) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}, { immediate: true });

onMounted(() => {
    window.addEventListener('keydown', onKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeyDown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div v-if="isOpen" class="fixed inset-0 z-50 overflow-hidden select-none">
            <!-- Backdrop -->
            <Transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    class="fixed inset-0 bg-black/70 backdrop-blur-[3px] transition-opacity"
                    @click="close"
                />
            </Transition>

            <!-- Sheet Content -->
            <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
                <Transition
                    enter-active-class="transform transition ease-out duration-300 sm:duration-300"
                    enter-from-class="translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="transform transition ease-in duration-200 sm:duration-200"
                    leave-from-class="translate-x-0"
                    leave-to-class="translate-x-full"
                >
                    <div
                        class="w-screen flex flex-col bg-admin-base border-l border-admin-subtle shadow-2xl text-zinc-100 focus:outline-none"
                        :class="drawerClasses"
                        :style="drawerStyle"
                    >
                        <!-- Header -->
                        <div class="px-6 py-5 border-b border-admin-subtle bg-admin-surface/75 backdrop-blur-sm flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <h2 class="text-lg font-bold text-zinc-100 truncate">
                                        {{ title }}
                                    </h2>
                                    <slot name="badge" />
                                </div>
                                <p v-if="displaySubtitle" class="mt-0.5 text-xs text-zinc-400">
                                    {{ displaySubtitle }}
                                </p>
                            </div>

                            <button
                                v-if="closeable"
                                type="button"
                                class="rounded-lg p-1.5 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800/80 transition-colors focus:outline-none focus:ring-2 focus:ring-violet-500"
                                @click="close"
                            >
                                <span class="sr-only">Закрыть</span>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Body (Scrollable) -->
                        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
                            <slot />
                        </div>

                        <!-- Footer (Sticky Actions) -->
                        <div
                            v-if="$slots.footer"
                            class="px-6 py-4 border-t border-admin-subtle bg-admin-surface/75 backdrop-blur-sm flex items-center justify-end gap-3"
                        >
                            <slot name="footer" />
                        </div>
                    </div>
                </Transition>
            </div>
        </div>
    </Teleport>
</template>
