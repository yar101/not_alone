<script setup>
defineProps({
    checked:  { type: Boolean, required: true },
    disabled: { type: Boolean, default: false },
});
defineEmits(['update:checked']);
</script>

<template>
    <span class="app-checkbox" :class="{ 'app-checkbox--checked': checked, 'app-checkbox--disabled': disabled }">
        <input
            type="checkbox"
            class="app-checkbox__input"
            :checked="checked"
            :disabled="disabled"
            @change="$emit('update:checked', $event.target.checked)"
        />
        <span class="app-checkbox__box">
            <svg v-if="checked" class="app-checkbox__tick" viewBox="0 0 10 10" fill="none">
                <polyline points="1.5,5.5 4,8 8.5,2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
    </span>
</template>

<style scoped>
.app-checkbox {
    display: inline-flex;
    align-items: center;
    position: relative;
    cursor: pointer;
    flex-shrink: 0;
}
.app-checkbox--disabled { cursor: not-allowed; opacity: 0.35; }

.app-checkbox__input {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    margin: 0;
    cursor: inherit;
}

.app-checkbox__box {
    width: 15px;
    height: 15px;
    border: 1.5px solid rgba(110,110,210,0.35);
    border-radius: 3px;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: border-color 0.15s, background 0.15s;
    pointer-events: none;
}

.app-checkbox--checked .app-checkbox__box {
    background: rgba(110,110,210,0.5);
    border-color: rgba(160,160,255,0.7);
}

.app-checkbox__tick {
    width: 10px;
    height: 10px;
    color: #fff;
}

.app-checkbox:hover:not(.app-checkbox--disabled) .app-checkbox__box {
    border-color: rgba(160,160,255,0.55);
}
</style>
