<script setup>
import { ref, computed, inject } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    cart: { type: Object, required: true },
});
const emit = defineEmits(['update:modelValue', 'clear', 'remove-item']);

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
        emit('clear');
        isOpen.value = false;
        if (openOrder) openOrder(res.data.order_id);
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
        <Transition name="rc-fade">
            <div v-if="isOpen" class="rc-backdrop" @click="close" />
        </Transition>
        <Transition name="rc-slide">
            <div v-if="isOpen" class="rc-panel">

                <!-- ═══ Шапка ═══ -->
                <div class="rc-header">
                    <div class="rc-header-top">
                        <span class="rc-store-name">КОРЗИНА</span>
                        <button class="rc-close" @click="close" aria-label="Закрыть">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <span v-if="cart.idol_name" class="rc-store-sub">Айдол: {{ cart.idol_name }}</span>
                    <div class="rc-rule rc-rule--double"></div>
                </div>

                <!-- ═══ Позиции ═══ -->
                <div class="rc-body">
                    <div v-if="!cart.items.length" class="rc-empty">
                        — &nbsp;корзина пуста&nbsp; —
                    </div>
                    <template v-else>
                        <div v-for="(item, idx) in cart.items" :key="item.service_id" class="rc-line">
                            <span class="rc-line__name">{{ item.name }}</span>
                            <span class="rc-line__dots" aria-hidden="true"></span>
                            <span class="rc-line__price">{{ item.price?.toLocaleString('ru-RU') }}&thinsp;₽<template v-if="item.time_unit">&thinsp;/&thinsp;{{ item.time_unit }}</template></span>
                            <button class="rc-line__del" @click="emit('remove-item', idx)" aria-label="Удалить">✕</button>
                        </div>
                    </template>
                </div>

                <!-- ═══ Перфорация ═══ -->
                <div class="rc-perf"><span class="rc-perf__line"></span></div>

                <!-- ═══ Итого ═══ -->
                <div class="rc-total">
                    <span class="rc-total__label">ИТОГО</span>
                    <span class="rc-total__sum">{{ total.toLocaleString('ru-RU') }}&thinsp;₽</span>
                </div>

                <!-- ═══ Перфорация ═══ -->
                <div class="rc-perf"><span class="rc-perf__line"></span></div>

                <!-- ═══ Футер ═══ -->
                <div class="rc-footer">
                    <p v-if="error" class="rc-error">{{ error }}</p>
                    <button
                        class="rc-submit"
                        :disabled="!cart.items.length || creating"
                        @click="createOrder"
                    >{{ creating ? 'ОФОРМЛЯЕМ…' : 'СОЗДАТЬ ЗАКАЗ' }}</button>
                </div>

            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* ── Transitions ──────────────────────────────────────── */
.rc-fade-enter-active, .rc-fade-leave-active { transition: opacity 0.22s; }
.rc-fade-enter-from, .rc-fade-leave-to       { opacity: 0; }

.rc-slide-enter-active, .rc-slide-leave-active { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.rc-slide-enter-from, .rc-slide-leave-to       { transform: translateX(100%); }

/* ── Backdrop ─────────────────────────────────────────── */
.rc-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1049;
    background: rgba(0, 0, 0, 0.55);
}

/* ── Panel ────────────────────────────────────────────── */
.rc-panel {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: 480px;
    max-width: 100vw;
    z-index: 1050;
    background: #09090f;
    border-left: 1px solid rgba(120, 220, 255, 0.1);
    box-shadow: -12px 0 60px rgba(0, 0, 0, 0.75);
    display: flex;
    flex-direction: column;
    font-family: 'Courier New', Courier, monospace;
    color: rgba(210, 240, 255, 0.78);
    overflow: hidden;
}

/* subtle grain overlay */
.rc-panel::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
    pointer-events: none;
    opacity: 0.4;
    z-index: 0;
}
.rc-panel > * { position: relative; z-index: 1; }

/* ── Header ───────────────────────────────────────────── */
.rc-header {
    padding: 1.25rem 1.5rem 0.85rem;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}
.rc-header-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.rc-close {
    width: 30px;
    height: 30px;
    border: 1px solid rgba(120, 220, 255, 0.18);
    background: transparent;
    color: rgba(210, 240, 255, 0.4);
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
    font-family: inherit;
    flex-shrink: 0;
}
.rc-close:hover {
    color: rgba(210, 240, 255, 0.9);
    border-color: rgba(120, 220, 255, 0.45);
    background: rgba(120, 220, 255, 0.06);
}

.rc-rule {
    width: 100%;
    border: none;
    height: 0;
    margin-top: 0.3rem;
}
.rc-rule--double {
    border-top: 2px double rgba(120, 220, 255, 0.35);
}

.rc-store-name {
    font-size: 1.45rem;
    font-weight: 700;
    letter-spacing: 0.3em;
    color: rgba(210, 240, 255, 0.95);
}
.rc-store-sub {
    font-size: 0.9rem;
    letter-spacing: 0.08em;
    color: rgba(100, 200, 255, 0.7);
}

/* ── Body ─────────────────────────────────────────────── */
.rc-body {
    flex: 1;
    overflow-y: auto;
    padding: 0.6rem 0;
    scrollbar-width: thin;
    scrollbar-color: rgba(120,220,255,0.1) transparent;
}

.rc-empty {
    text-align: center;
    font-size: 0.9rem;
    color: rgba(210, 240, 255, 0.22);
    padding: 3rem 0;
    letter-spacing: 0.1em;
}

.rc-line {
    display: flex;
    align-items: baseline;
    padding: 0.55rem 1.5rem;
    border-bottom: 1px solid rgba(120, 220, 255, 0.06);
    transition: background 0.12s;
}
.rc-line:hover { background: rgba(120, 220, 255, 0.03); }

.rc-line__name {
    font-size: 0.95rem;
    color: rgba(210, 240, 255, 0.78);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 45%;
    flex-shrink: 0;
    letter-spacing: 0.02em;
}
.rc-line__dots {
    flex: 1;
    border-bottom: 1px dotted rgba(120, 220, 255, 0.35);
    margin: 0 0.5rem;
    position: relative;
    top: -4px;
    min-width: 1rem;
}
.rc-line__price {
    font-size: 0.98rem;
    font-weight: 700;
    color: rgba(100, 210, 255, 0.95);
    white-space: nowrap;
    flex-shrink: 0;
    letter-spacing: 0.03em;
}
.rc-line__del {
    flex-shrink: 0;
    margin-left: 0.65rem;
    width: 20px;
    height: 20px;
    border: none;
    background: transparent;
    color: rgba(210, 240, 255, 0.18);
    cursor: pointer;
    font-size: 0.7rem;
    font-family: inherit;
    transition: color 0.12s;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.rc-line__del:hover { color: rgba(255, 110, 110, 0.75); }

/* ── Perforation ──────────────────────────────────────── */
.rc-perf {
    display: flex;
    align-items: center;
    margin: 0.7rem 0;
    position: relative;
}
.rc-perf::before,
.rc-perf::after {
    content: '';
    width: 13px;
    height: 13px;
    border-radius: 50%;
    background: #09090f;
    border: 1px solid rgba(120, 220, 255, 0.15);
    flex-shrink: 0;
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    box-shadow: inset 0 0 4px rgba(0,0,0,0.6);
}
.rc-perf::before { left: -6px; }
.rc-perf::after  { right: -6px; }
.rc-perf__line {
    flex: 1;
    display: block;
    border-top: 1px dashed rgba(120, 220, 255, 0.35);
    margin: 0 9px;
}

/* ── Total ────────────────────────────────────────────── */
.rc-total {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding: 0.35rem 1.5rem;
}
.rc-total__label {
    font-size: 0.9rem;
    letter-spacing: 0.2em;
    color: rgba(210, 240, 255, 0.45);
}
.rc-total__sum {
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: rgba(100, 210, 255, 1);
    font-variant-numeric: tabular-nums;
}

/* ── Footer ───────────────────────────────────────────── */
.rc-footer {
    padding: 0.8rem 1.5rem 1.4rem;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}
.rc-error {
    font-size: 0.82rem;
    color: rgba(255, 110, 110, 0.85);
    margin: 0;
    letter-spacing: 0.04em;
}
.rc-submit {
    width: 100%;
    padding: 0.8rem;
    border: 1px solid rgba(100, 210, 255, 0.35);
    border-radius: 3px;
    background: rgba(100, 210, 255, 0.07);
    color: rgba(100, 210, 255, 0.95);
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.9rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.rc-submit:hover:not(:disabled) {
    background: rgba(100, 210, 255, 0.13);
    border-color: rgba(100, 210, 255, 0.6);
    color: rgba(100, 210, 255, 1);
}
.rc-submit:disabled {
    opacity: 0.25;
    cursor: not-allowed;
}
</style>
