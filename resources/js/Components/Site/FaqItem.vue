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
        <div class="faq-nav-item__icon">
            <slot name="icon" />
        </div>
        <span class="faq-nav-item__title">{{ title }}</span>
    </button>
</template>

<style scoped>
.faq-nav-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    width: 100%;
    padding: 0.65rem 0.85rem;
    background: transparent;
    border: none;
    border-left: 2px solid transparent;
    cursor: pointer;
    text-align: left;
    color: rgba(255, 255, 255, 0.55);
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    border-radius: 0 6px 6px 0;
}

.faq-nav-item:hover {
    background: rgba(200, 70, 126, 0.08);
    color: rgba(255, 255, 255, 0.85);
}

.faq-nav-item--active {
    background: rgba(200, 70, 126, 0.12);
    color: #fff;
    border-left-color: rgba(200, 70, 126, 0.7);
}

.faq-nav-item__icon {
    width: 32px;
    height: 32px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: inherit;
    transition: color 0.2s ease;
}

.faq-nav-item--active .faq-nav-item__icon {
    color: rgba(200, 70, 126, 0.9);
}

.faq-nav-item__title {
    font-size: 0.85rem;
    line-height: 1.4;
    color: inherit;
}

/* Large screens */
@media (min-width: 1440px) {
    .faq-nav-item {
        padding: 0.8rem 1rem;
        gap: 0.75rem;
    }

    .faq-nav-item__icon {
        width: 38px;
        height: 38px;
        font-size: 1.25rem;
    }

    .faq-nav-item__title {
        font-size: 1rem;
    }
}

@media (min-width: 2000px) {
    .faq-nav-item {
        padding: 0.95rem 1.1rem;
    }

    .faq-nav-item__icon {
        width: 42px;
        height: 42px;
        font-size: 1.4rem;
    }

    .faq-nav-item__title {
        font-size: 1.1rem;
    }
}

/* Mobile pill style */
@media (max-width: 767px) {
    .faq-nav-item {
        flex-shrink: 0;
        width: auto;
        border-left: none;
        border-radius: 20px;
        padding: 0.4rem 0.8rem;
        border: 1px solid rgba(255, 255, 255, 0.08);
        gap: 0.4rem;
    }

    .faq-nav-item:hover {
        border-color: rgba(200, 70, 126, 0.3);
    }

    .faq-nav-item--active {
        border-color: rgba(200, 70, 126, 0.5);
        background: rgba(200, 70, 126, 0.15);
    }

    .faq-nav-item__icon {
        width: 20px;
        height: 20px;
        font-size: 0.9rem;
    }

    .faq-nav-item__title {
        font-size: 0.8rem;
    }
}
</style>
