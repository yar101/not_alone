<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: { type: String, required: true },
    options:    { type: Array, required: true }, // [{ value, label }]
});
const emit = defineEmits(['update:modelValue']);

const open    = ref(false);
const wrapEl  = ref(null);

const currentLabel = computed(() =>
    props.options.find(o => o.value === props.modelValue)?.label ?? props.options[0]?.label ?? ''
);

function select(value) {
    open.value = false;
    if (props.modelValue !== value) emit('update:modelValue', value);
}

function onDocClick(e) {
    if (open.value && wrapEl.value && !wrapEl.value.contains(e.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocClick, true));
onUnmounted(() => document.removeEventListener('click', onDocClick, true));
</script>

<template>
    <div class="sd-wrap" ref="wrapEl">
        <span class="sd-label">Сортировка:</span>
        <div class="sd-trigger">
            <button class="sd-btn" type="button" @click="open = !open">
                {{ currentLabel }}
                <svg class="sd-arrow" :class="{ 'sd-arrow--open': open }" width="11" height="11"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                    stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>
            <Transition name="sd-drop">
                <div v-if="open" class="sd-dropdown">
                    <button
                        v-for="opt in options"
                        :key="opt.value"
                        type="button"
                        class="sd-option"
                        :class="{ 'sd-option--active': modelValue === opt.value }"
                        @click="select(opt.value)"
                    >{{ opt.label }}</button>
                </div>
            </Transition>
        </div>
    </div>
</template>

<style scoped>
.sd-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
}

.sd-label {
    font-size: 0.92rem;
    color: rgba(255,255,255,0.55);
    white-space: nowrap;
}

.sd-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: none;
    border: none;
    padding: 0;
    font-size: 0.92rem;
    font-family: inherit;
    color: rgba(255,255,255,0.8);
    cursor: pointer;
    white-space: nowrap;
}
.sd-btn:hover { opacity: 0.8; }

.sd-arrow { transition: transform 0.18s ease; opacity: 0.7; }
.sd-arrow--open { transform: rotate(180deg); }

.sd-trigger { position: relative; }

.sd-dropdown {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    background: rgb(12, 10, 20);
    border: 1px solid rgba(160,160,255,0.2);
    border-radius: 6px;
    z-index: 20;
    width: max-content;
    box-shadow: 0 6px 24px rgba(0,0,0,0.5);
    overflow: hidden;
}
.sd-dropdown::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(255,140,175,0.5) 50%, transparent 100%);
    pointer-events: none;
}

.sd-option {
    display: block;
    width: 100%;
    padding: 0.5rem 0.9rem;
    background: none;
    border: none;
    text-align: left;
    font-size: 0.92rem;
    font-family: inherit;
    color: rgba(255,255,255,0.65);
    cursor: pointer;
    white-space: nowrap;
    transition: color 0.12s, background 0.12s;
}
.sd-option:hover { background: rgba(160,160,255,0.07); color: rgba(200,200,255,0.9); }
.sd-option--active { color: var(--color-base-1, #a0a0ff); }

.sd-drop-enter-active, .sd-drop-leave-active { transition: opacity 0.12s, transform 0.12s; }
.sd-drop-enter-from, .sd-drop-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
