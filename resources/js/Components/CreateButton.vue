<script setup>
defineOptions({ inheritAttrs: true });
</script>

<template>
    <button class="create-btn" v-bind="$attrs">
        <span v-if="$slots.icon" class="create-btn__icon">
            <slot name="icon" />
        </span>
        <slot />
    </button>
</template>

<style scoped>
.create-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 1rem;
    background: linear-gradient(135deg, rgba(110, 110, 210, 0.12) 0%, rgba(200, 70, 126, 0.08) 100%);
    border: 1px solid rgba(110, 110, 210, 0.35);
    border-radius: 6px;
    color: rgba(160, 160, 255, 0.9);
    font-family: inherit;
    font-size: 0.92rem;
    font-weight: 500;
    letter-spacing: 0.01em;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.18s, border-color 0.18s, color 0.18s, box-shadow 0.18s;
    position: relative;
    overflow: hidden;
}

.create-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(110, 110, 210, 0.0) 0%, rgba(200, 70, 126, 0.0) 100%);
    transition: background 0.18s;
    pointer-events: none;
}

.create-btn:hover {
    background: linear-gradient(135deg, rgba(110, 110, 210, 0.22) 0%, rgba(200, 70, 126, 0.14) 100%);
    border-color: rgba(160, 160, 255, 0.6);
    color: #fff;
    box-shadow: 0 0 16px rgba(110, 110, 210, 0.22), 0 0 4px rgba(200, 70, 126, 0.12);
}

.create-btn:active {
    transform: scale(0.97);
}

.create-btn__icon {
    display: flex;
    align-items: center;
    font-size: 0.95rem;
    opacity: 0.9;
}
</style>
