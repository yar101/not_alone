<script setup>
import { computed, useId } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    type: {
        type: String,
        default: 'text',
    },
    placeholder: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    readonly: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'md', // sm | md | lg
    },
    id: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue', 'change', 'blur', 'focus']);

const inputId = props.id || useId();

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'px-2.5 py-1.5 text-xs rounded-lg';
        case 'lg':
            return 'px-4 py-2.5 text-base rounded-xl';
        case 'md':
        default:
            return 'px-3.5 py-2 text-sm rounded-lg';
    }
});
</script>

<template>
    <div class="relative w-full">
        <div v-if="$slots.prefix" class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-500">
            <slot name="prefix" />
        </div>

        <input
            :id="inputId"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :readonly="readonly"
            class="block w-full bg-zinc-900 border text-zinc-100 placeholder-zinc-500 transition-colors duration-150 focus:outline-none focus:ring-1 disabled:opacity-50 disabled:cursor-not-allowed"
            :class="[
                sizeClasses,
                $slots.prefix ? 'pl-9' : '',
                $slots.suffix ? 'pr-9' : '',
                error
                    ? 'border-rose-500 focus:border-rose-500 focus:ring-rose-500/30'
                    : 'border-zinc-800 hover:border-zinc-700 focus:border-violet-500 focus:ring-violet-500/30'
            ]"
            @input="emit('update:modelValue', $event.target.value)"
            @change="emit('change', $event)"
            @blur="emit('blur', $event)"
            @focus="emit('focus', $event)"
        />

        <div v-if="$slots.suffix" class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-500">
            <slot name="suffix" />
        </div>

        <p v-if="error" class="mt-1 text-xs text-rose-400 font-medium">
            {{ error }}
        </p>
    </div>
</template>
