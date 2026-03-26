<script setup>
import { ref, computed, inject } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    cart: { type: Object, required: true },
});
const emit = defineEmits(['update:modelValue', 'clear', 'remove-item']);

const page = usePage();
const openOrder = inject('openOrder', null);

const creating = ref(false);
const error    = ref('');

const isOpen = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const total = computed(() =>
    props.cart.items.reduce((sum, item) => sum + (item.price || 0), 0)
);

function close() { isOpen.value = false; }

async function createOrder() {
    if (creating.value || !props.cart.items.length) return;
    creating.value = true;
    error.value = '';
    try {
        const res = await axios.post(route('orders.store'), {
            idol_id: props.cart.idol_id,
            service_ids: props.cart.items.map(i => i.service_id),
        });
        // Clear cart
        emit('clear');
        isOpen.value = false;
        // Navigate to the order conversation
        if (openOrder) {
            openOrder(res.data.order_id);
        }
        router.reload({ only: ['order_notifications_unread'] });
    } catch (e) {
        error.value = e.response?.data?.error ?? 'Ошибка при создании заказа';
    } finally {
        creating.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition name="cd-fade">
            <div v-if="isOpen" class="cd-backdrop" @click="close" />
        </Transition>
        <Transition name="cd-drop">
            <div v-if="isOpen" class="cd-panel">
                <div class="cd-header">
                    <span class="cd-title">Корзина</span>
                    <span v-if="cart.idol_name" class="cd-idol-name">{{ cart.idol_name }}</span>
                    <button class="cd-close" @click="close">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>

                <div class="cd-body">
                    <template v-if="cart.items.length === 0">
                        <p class="cd-empty">Корзина пуста</p>
                    </template>
                    <template v-else>
                        <div v-for="(item, idx) in cart.items" :key="item.service_id" class="cd-item">
                            <div class="cd-item__info">
                                <span class="cd-item__name">{{ item.name }}</span>
                                <span class="cd-item__price">{{ item.price?.toLocaleString('ru-RU') }} ₽<template v-if="item.time_unit"> / {{ item.time_unit }}</template></span>
                            </div>
                            <button class="cd-item__remove" @click="emit('remove-item', idx)">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>

                        <div class="cd-total">
                            <span>Итого</span>
                            <span class="cd-total__sum">{{ total.toLocaleString('ru-RU') }} ₽</span>
                        </div>
                    </template>
                </div>

                <div class="cd-footer">
                    <p v-if="error" class="cd-error">{{ error }}</p>
                    <button
                        class="cd-order-btn"
                        :disabled="!cart.items.length || creating"
                        @click="createOrder"
                    >{{ creating ? 'Создаём…' : 'Создать заказ' }}</button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.cd-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1050;
    background: transparent;
}

.cd-panel {
    position: fixed;
    top: 64px;
    right: 1rem;
    width: 320px;
    max-height: 480px;
    z-index: 1051;
    background: linear-gradient(160deg, #12122a 0%, #0a0a18 100%);
    border: 1px solid rgba(110,110,210,0.22);
    border-radius: 8px;
    box-shadow: 0 8px 48px rgba(0,0,0,0.6), 0 0 0 1px rgba(190,145,255,0.04);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.cd-fade-enter-active, .cd-fade-leave-active { transition: opacity 0.18s; }
.cd-fade-enter-from, .cd-fade-leave-to { opacity: 0; }
.cd-drop-enter-active, .cd-drop-leave-active { transition: opacity 0.18s, transform 0.18s; }
.cd-drop-enter-from, .cd-drop-leave-to { opacity: 0; transform: translateY(-8px) scale(0.97); }

.cd-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.85rem 1rem 0.7rem;
    border-bottom: 1px solid rgba(110,110,210,0.12);
    flex-shrink: 0;
}
.cd-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: rgba(255,255,255,0.88);
    letter-spacing: 0.02em;
}
.cd-idol-name {
    font-size: 0.75rem;
    color: rgba(190,145,255,0.7);
    margin-left: auto;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100px;
}
.cd-close {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border: none;
    background: transparent;
    color: rgba(255,255,255,0.3);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: color 0.15s, background 0.15s;
}
.cd-close:hover { color: rgba(255,255,255,0.8); background: rgba(110,110,210,0.1); }

.cd-body {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem 0;
}
.cd-empty {
    text-align: center;
    color: rgba(255,255,255,0.25);
    font-size: 0.85rem;
    padding: 1.5rem 0;
    margin: 0;
}

.cd-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.cd-item__info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}
.cd-item__name {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.82);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cd-item__price {
    font-size: 0.75rem;
    color: rgba(190,145,255,0.65);
}
.cd-item__remove {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    border: none;
    background: transparent;
    color: rgba(255,255,255,0.2);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: color 0.15s, background 0.15s;
}
.cd-item__remove:hover { color: rgba(255,140,140,0.8); background: rgba(200,50,50,0.1); }

.cd-total {
    display: flex;
    justify-content: space-between;
    padding: 0.6rem 1rem 0.3rem;
    font-size: 0.85rem;
    color: rgba(255,255,255,0.5);
    border-top: 1px solid rgba(110,110,210,0.1);
    margin-top: 0.25rem;
}
.cd-total__sum {
    font-weight: 700;
    color: rgba(190,145,255,0.85);
}

.cd-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid rgba(110,110,210,0.12);
    flex-shrink: 0;
}
.cd-error {
    font-size: 0.8rem;
    color: rgba(255,140,140,0.85);
    margin: 0 0 0.5rem;
}
.cd-order-btn {
    width: 100%;
    padding: 0.55rem;
    border-radius: 6px;
    border: 1px solid rgba(190,145,255,0.35);
    background: rgba(190,145,255,0.1);
    color: #be91ff;
    font-size: 0.875rem;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}
.cd-order-btn:hover:not(:disabled) {
    background: rgba(190,145,255,0.18);
    border-color: rgba(190,145,255,0.55);
}
.cd-order-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}
</style>
