<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: { default: null },
    options:     { type: Array,   default: () => [] },
    placeholder: { type: String,  default: 'Выберите...' },
    disabled:    { type: Boolean, default: false },
    error:       { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);

const triggerRef  = ref(null);
const dropdownRef = ref(null);
const isOpen      = ref(false);
const dropStyle   = ref({});

// Normalize: primitives → {value, label}
const normalizedOptions = computed(() =>
    props.options.map(o =>
        (o !== null && typeof o === 'object') ? o : { value: o, label: String(o) }
    )
);

const selectedLabel = computed(() => {
    const v = props.modelValue;
    if (v === null || v === undefined) return null;
    const found = normalizedOptions.value.find(o => String(o.value) === String(v));
    return found?.label ?? null;
});

function open() {
    if (props.disabled) return;
    const zoom       = parseFloat(getComputedStyle(document.body).zoom) || 1;
    const rect       = triggerRef.value.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const style      = {
        left:  (rect.left  / zoom) + 'px',
        width: (rect.width / zoom) + 'px',
    };

    if (spaceBelow < 140 && rect.top > spaceBelow) {
        style.bottom = ((window.innerHeight - rect.top) / zoom + 2) + 'px';
    } else {
        style.top = (rect.bottom / zoom + 2) + 'px';
    }
    dropStyle.value = style;
    isOpen.value = true;
}

function toggle() {
    isOpen.value ? (isOpen.value = false) : open();
}

function select(value) {
    emit('update:modelValue', value);
    emit('change', value);
    isOpen.value = false;
}

function onOutsideClick(e) {
    if (!triggerRef.value?.contains(e.target) && !dropdownRef.value?.contains(e.target)) {
        isOpen.value = false;
    }
}

onMounted(()  => document.addEventListener('mousedown', onOutsideClick));
onUnmounted(() => document.removeEventListener('mousedown', onOutsideClick));
</script>

<template>
    <div
        ref="triggerRef"
        class="app-select"
        :class="{
            'app-select--open':     isOpen,
            'app-select--error':    error,
            'app-select--disabled': disabled,
        }"
        @click="toggle"
    >
        <span class="app-select__val" :class="{ 'app-select__val--ph': !selectedLabel }">
            {{ selectedLabel ?? placeholder }}
        </span>
        <svg
            class="app-select__caret"
            :class="{ 'app-select__caret--up': isOpen }"
            width="10" height="6" viewBox="0 0 10 6" fill="none"
        >
            <path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
    </div>

    <Teleport to="body">
        <div
            v-if="isOpen"
            ref="dropdownRef"
            class="app-select-drop"
            :style="dropStyle"
        >
            <div
                v-for="opt in normalizedOptions"
                :key="opt.value"
                class="app-select-opt"
                :class="{ 'app-select-opt--sel': String(opt.value) === String(modelValue ?? '') }"
                @mousedown.prevent="select(opt.value)"
            >
                {{ opt.label }}
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.app-select {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.58rem 0.75rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 0.88rem;
    font-family: inherit;
    cursor: pointer;
    user-select: none;
    box-sizing: border-box;
    width: 100%;
    transition: border-color 0.15s;
    min-width: 0;
}
.app-select:hover:not(.app-select--disabled) {
    border-color: rgba(255, 255, 255, 0.2);
}
.app-select--open  { border-color: rgba(160, 160, 255, 0.45); }
.app-select--error { border-color: rgba(239, 68, 68, 0.5) !important; }
.app-select--disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }

.app-select__val {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    line-height: 1.4;
}
.app-select__val--ph { color: rgba(255, 255, 255, 0.28); }

.app-select__caret {
    flex-shrink: 0;
    color: rgba(255, 255, 255, 0.28);
    transition: transform 0.15s, color 0.15s;
}
.app-select--open .app-select__caret {
    color: rgba(160, 160, 255, 0.65);
}
.app-select__caret--up { transform: rotate(180deg); }
</style>

<style>
/* Global — Teleported dropdown lives outside scoped scope */
.app-select-drop {
    position: fixed;
    z-index: 9999;
    background: #0d0d1b;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-top-color: rgba(160, 160, 255, 0.3);
    border-radius: 3px;
    overflow-y: auto;
    max-height: 224px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.65), 0 0 0 1px rgba(160, 160, 255, 0.06);
    scrollbar-width: thin;
    scrollbar-color: rgba(160, 160, 255, 0.3) transparent;
}

.app-select-opt {
    padding: 0.5rem 0.75rem;
    font-size: 0.88rem;
    font-family: 'Figtree', sans-serif;
    color: rgba(255, 255, 255, 0.65);
    cursor: pointer;
    transition: background 0.1s, color 0.1s;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.app-select-opt:hover {
    background: rgba(160, 160, 255, 0.1);
    color: rgba(255, 255, 255, 0.95);
}
.app-select-opt--sel {
    background: rgba(160, 160, 255, 0.08);
    color: rgba(160, 160, 255, 0.9);
}
.app-select-opt--sel:hover {
    background: rgba(160, 160, 255, 0.16);
}
</style>
