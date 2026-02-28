<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <button class="faq-nav-item" :class="{ 'faq-nav-item--active': active }" @click="$emit('click')">
        <div class="faq-nav-item__icon-wrap">
            <slot name="icon" />
        </div>
        <span class="faq-nav-item__title">{{ title }}</span>
    </button>
</template>

<style scoped>
.faq-nav-item {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    width: 100%;
    padding: 0.6rem 0.9rem 0.6rem 0.75rem;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    color: rgba(255, 255, 255, 0.45);
    transition:
        background 0.22s ease,
        color 0.22s ease,
        box-shadow 0.25s ease;

}

.faq-nav-item:hover:not(.faq-nav-item--active) {
    background: linear-gradient(
        90deg,
        rgba(200, 70, 126, 0.08) 0%,
        rgba(200, 70, 126, 0.02) 60%,
        transparent 100%
    );
    color: rgba(255, 255, 255, 0.8);
}

.faq-nav-item--active {
    background: linear-gradient(
        90deg,
        rgba(200, 70, 126, 0.16) 0%,
        rgba(200, 70, 126, 0.06) 55%,
        transparent 100%
    );
    color: #fff;
    box-shadow: inset 3px 0 0 rgba(200, 70, 126, 0.9), inset 5px 0 12px rgba(200, 70, 126, 0.15);
}

/* Icon circle */
.faq-nav-item__icon-wrap {
    width: 32px;
    height: 32px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.4);
    transition:
        background 0.22s ease,
        color 0.22s ease,
        box-shadow 0.22s ease;
}

.faq-nav-item:hover:not(.faq-nav-item--active) .faq-nav-item__icon-wrap {
    background: rgba(200, 70, 126, 0.1);
    color: rgba(200, 70, 126, 0.7);
}

.faq-nav-item--active .faq-nav-item__icon-wrap {
    background: rgba(200, 70, 126, 0.2);
    color: rgba(200, 70, 126, 1);
    box-shadow:
        0 0 10px rgba(200, 70, 126, 0.35),
        inset 0 0 8px rgba(200, 70, 126, 0.1);
}

.faq-nav-item__title {
    font-size: 0.83rem;
    line-height: 1.4;
    color: inherit;
    letter-spacing: 0.01em;
}

/* Large screens */
@media (min-width: 1440px) {
    .faq-nav-item { padding: 0.75rem 1rem 0.75rem 0.85rem; gap: 0.75rem; }
    .faq-nav-item__icon-wrap { width: 36px; height: 36px; }
    .faq-nav-item__title { font-size: 0.95rem; }
}

@media (min-width: 2000px) {
    .faq-nav-item { padding: 0.9rem 1.1rem 0.9rem 1rem; }
    .faq-nav-item__icon-wrap { width: 40px; height: 40px; }
    .faq-nav-item__title { font-size: 1.05rem; }
}

/* Mobile pill */
@media (max-width: 767px) {
    .faq-nav-item {
        flex-shrink: 0;
        width: auto;
        border-left: none;
        border-radius: 3px;
        padding: 0.45rem 0.85rem 0.45rem 0.55rem;
        border: 1px solid rgba(255, 255, 255, 0.07);
        gap: 0.45rem;
    }
    .faq-nav-item::before { display: none; }
    .faq-nav-item:hover:not(.faq-nav-item--active) {
        background: rgba(200, 70, 126, 0.07);
        border-color: rgba(200, 70, 126, 0.25);
    }
    .faq-nav-item--active {
        background: rgba(200, 70, 126, 0.14);
        border-color: rgba(200, 70, 126, 0.45);
    }
    .faq-nav-item__icon-wrap {
        width: 22px;
        height: 22px;
        background: transparent;
    }
    .faq-nav-item--active .faq-nav-item__icon-wrap {
        background: transparent;
        box-shadow: none;
    }
    .faq-nav-item__title { font-size: 0.8rem; }
}
</style>
