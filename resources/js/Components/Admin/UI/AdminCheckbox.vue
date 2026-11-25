<script setup>
import { computed, useId } from 'vue';

const props = defineProps({
    modelValue: {
        type: [Boolean, Array],
        default: false,
    },
    value: {
        type: [String, Number, Boolean, Object],
        default: null,
    },
    label: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    id: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);
const checkboxId = props.id || useId();

const isChecked = computed(() => {
    if (Array.isArray(props.modelValue)) {
        return props.modelValue.includes(props.value);
    }
    return Boolean(props.modelValue);
});

function onChange(e) {
    if (Array.isArray(props.modelValue)) {
        const newValue = [...props.modelValue];
        if (e.target.checked) {
            newValue.push(props.value);
        } else {
            const idx = newValue.indexOf(props.value);
            if (idx > -1) newValue.splice(idx, 1);
        }
        emit('update:modelValue', newValue);
        emit('change', newValue);
    } else {
        emit('update:modelValue', e.target.checked);
        emit('change', e.target.checked);
    }
}
</script>

<template>
    <label
        :for="checkboxId"
        class="inline-flex items-start gap-2.5 cursor-pointer select-none"
        :class="{ 'opacity-50 cursor-not-allowed': disabled }"
    >
        <div class="flex items-center h-5">
            <input
                :id="checkboxId"
                type="checkbox"
                :checked="isChecked"
                :disabled="disabled"
                class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500 focus:ring-offset-zinc-950 transition-colors"
                @change="onChange"
            />
        </div>
        <div v-if="label || description || $slots.default" class="text-sm">
            <span v-if="label" class="font-medium text-zinc-200">{{ label }}</span>
            <slot />
            <p v-if="description" class="text-xs text-zinc-400 mt-0.5">{{ description }}</p>
        </div>
    </label>
</template>
