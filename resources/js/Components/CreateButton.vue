<script setup>
defineOptions({ inheritAttrs: true });
</script>

<template>
    <button class="create-btn" v-bind="$attrs">
        <svg class="create-btn__icon" viewBox="0 0 16 16" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <line x1="8" y1="2" x2="8" y2="14"/>
            <line x1="2" y1="8" x2="14" y2="8"/>
        </svg>
        <slot />
    </button>
</template>

<style scoped>
.create-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 1rem;
    background: rgba(130, 125, 255, 0.08);
    border: 1px solid rgba(160, 160, 255, 0.28);
    border-top: none;
    border-radius: 6px;
    color: rgba(180, 178, 255, 0.88);
    font-family: inherit;
    font-size: 0.92rem;
    font-weight: 500;
    letter-spacing: 0.01em;
    cursor: pointer;
    white-space: nowrap;
    position: relative;
    overflow: hidden;
    transition: background 0.16s, border-color 0.16s, color 0.16s, box-shadow 0.16s;
    box-shadow:
        inset 0 1px 0 rgba(180, 175, 255, 0.38),
        inset 0 -1px 0 rgba(0, 0, 0, 0.18);
}

.create-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(105deg,
        transparent 30%,
        rgba(255, 255, 255, 0.07) 50%,
        transparent 70%);
    transform: translateX(-100%);
    transition: transform 0.5s ease;
}

.create-btn:hover::before {
    transform: translateX(100%);
}

.create-btn:hover {
    background: rgba(130, 125, 255, 0.16);
    border-color: rgba(160, 160, 255, 0.52);
    color: var(--color-base-1, #a0a0ff);
    box-shadow:
        inset 0 1px 0 rgba(180, 175, 255, 0.48),
        inset 0 -1px 0 rgba(0, 0, 0, 0.14),
        0 0 18px rgba(140, 130, 255, 0.18),
        inset 0 0 10px rgba(140, 130, 255, 0.07);
}

.create-btn:active {
    background: rgba(100, 95, 220, 0.18);
    border-color: rgba(160, 160, 255, 0.45);
    color: var(--color-base-1, #a0a0ff);
    box-shadow:
        inset 0 1px 0 rgba(180, 175, 255, 0.38),
        inset 0 -1px 0 rgba(0, 0, 0, 0.18);
    transform: scale(0.97);
    transition-duration: 0.06s;
}

.create-btn__icon {
    width: 12px;
    height: 12px;
    flex-shrink: 0;
    opacity: 0.7;
    transition: opacity 0.16s;
}

.create-btn:hover .create-btn__icon {
    opacity: 1;
}
</style>
