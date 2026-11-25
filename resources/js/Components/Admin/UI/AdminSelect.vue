<script setup>
import { computed, useId } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number, Boolean],
        default: '',
    },
    options: {
        type: Array,
        default: () => [], // [{ value: '...', label: '...' }]
    },
    placeholder: {
        type: String,
        default: 'Выберите вариант...',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'md', // sm | md
    },
    id: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);
const selectId = props.id || useId();

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'py-1.5 pl-2.5 pr-8 text-xs rounded-lg';
        case 'md':
        default:
            return 'py-2 pl-3.5 pr-10 text-sm rounded-lg';
    }
});
</script>

<template>
    <div class="relative w-full">
        <select
            :id="selectId"
            :value="modelValue"
            :disabled="disabled"
            class="block w-full bg-admin-card border border-admin-subtle text-admin-main transition-colors duration-150 focus:outline-none focus:ring-1 appearance-none cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
            :class="[
                sizeClasses,
                error
                    ? 'border-rose-500 focus:border-rose-500 focus:ring-rose-500/30'
                    : 'hover:border-admin-theme focus:border-admin-primary focus:ring-admin-primary/30'
            ]"
            @change="emit('update:modelValue', $event.target.value); emit('change', $event.target.value)"
        >
            <option v-if="placeholder" value="" disabled selected class="bg-admin-card text-admin-muted">
                {{ placeholder }}
            </option>
            <option
                v-for="opt in options"
                :key="opt.value"
                :value="opt.value"
                class="bg-admin-card text-admin-main py-1"
            >
                {{ opt.label }}
            </option>
            <slot />
        </select>

        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-admin-muted">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>

        <p v-if="error" class="mt-1 text-xs text-rose-400 font-medium">
            {{ error }}
        </p>
    </div>
</template>
