<script setup>
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

const props = defineProps({
    cart: { type: Object, required: true },
});

const emit = defineEmits(['click']);

const count = computed(() => {
    const servicesCount = (props.cart.services?.items ?? []).reduce((sum, i) => sum + (i.quantity || 1), 0);
    const contentCount  = (props.cart.content?.items ?? []).length;
    return servicesCount + contentCount;
});
</script>

<template>
    <button class="cart-icon-btn" :class="{ 'cart-icon-btn--active': count > 0 }" @click="emit('click')" :title="__('cart.icon.title')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
            <line x1="3" y1="6" x2="21" y2="6"/>
            <path d="M16 10a4 4 0 01-8 0"/>
        </svg>
        <span v-if="count > 0" class="cart-icon-badge">{{ count }}</span>
    </button>
</template>

<style scoped>
.cart-icon-btn {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid transparent;
    background: transparent;
    color: rgba(255,255,255,0.4);
    cursor: pointer;
    transition: color 0.18s, background 0.18s, border-color 0.18s;
}
@media (hover: hover) {
    .cart-icon-btn:hover {
        color: rgba(255,255,255,0.8);
        background: rgba(110,110,210,0.08);
    }
}
.cart-icon-btn--active {
    color: #a0a0ff;
    border-color: rgba(160,160,255,0.22);
}
.cart-icon-badge {
    position: absolute;
    top: 2px;
    right: 2px;
    min-width: 16px;
    height: 16px;
    border-radius: 8px;
    background: #a0a0ff;
    color: #0a0a14;
    font-size: 0.62rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 3px;
    line-height: 1;
    pointer-events: none;
}
</style>
