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
    variant: {
        type: String,
        default: 'default', // 'default' | 'warning' | 'danger' | 'info'
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

const modalClasses = computed(() => {
    if (props.maxWidth) {
        return props.maxWidth;
    }
    if (props.width) {
        return props.width.startsWith('max-w-') ? props.width : `max-w-[${props.width}]`;
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
    return 'max-w-lg';
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
        <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6 md:p-20 flex items-center justify-center">
            <!-- Backdrop -->
            <Transition
                enter-active-class="ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    class="fixed inset-0 bg-black/75 backdrop-blur-[3px] transition-opacity"
                    @click="close"
                />
            </Transition>

            <!-- Dialog Panel -->
            <Transition
                enter-active-class="ease-out duration-200"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="ease-in duration-150"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
                <div
                    class="relative w-full bg-admin-card border border-admin-theme rounded-2xl shadow-2xl overflow-hidden text-zinc-100 transform transition-all flex flex-col max-h-[90vh]"
                    :class="modalClasses"
                >
                    <!-- Header -->
                    <div
                        class="px-6 py-4 border-b border-admin-theme bg-admin-surface/75 flex items-start justify-between gap-4"
                        :class="{
                            'border-rose-500/30 bg-rose-950/10': variant === 'danger',
                            'border-amber-500/30 bg-amber-950/10': variant === 'warning',
                            'border-blue-500/30 bg-blue-950/10': variant === 'info',
                        }"
                    >
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base font-bold text-zinc-100 flex items-center gap-2">
                                <span
                                    v-if="variant === 'danger'"
                                    class="w-2 h-2 rounded-full bg-rose-500 inline-block"
                                />
                                <span
                                    v-else-if="variant === 'warning'"
                                    class="w-2 h-2 rounded-full bg-amber-400 inline-block"
                                />
                                <span
                                    v-else-if="variant === 'info'"
                                    class="w-2 h-2 rounded-full bg-blue-400 inline-block"
                                />
                                {{ title }}
                            </h3>
                            <p v-if="displaySubtitle" class="mt-0.5 text-xs text-zinc-400">
                                {{ displaySubtitle }}
                            </p>
                        </div>
                        <button
                            v-if="closeable"
                            type="button"
                            class="rounded-lg p-1 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
                            @click="close"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-5 overflow-y-auto flex-1">
                        <slot />
                    </div>

                    <!-- Footer -->
                    <div
                        v-if="$slots.footer"
                        class="px-6 py-4 border-t border-admin-theme bg-admin-surface/75 flex items-center justify-end gap-3"
                    >
                        <slot name="footer" />
                    </div>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>
