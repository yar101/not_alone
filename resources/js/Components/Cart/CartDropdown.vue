<script setup>
import { ref, computed, watch, inject, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    cart: { type: Object, required: true },
    initialTab: { type: String, default: 'services' },
});
const emit = defineEmits(['update:modelValue', 'clear-services', 'clear-content', 'remove-service', 'remove-content', 'change-quantity']);

const openOrder = inject('openOrder', null);

// Active tab: 'services' | 'content'
const activeTab = ref(props.initialTab);
watch(() => props.initialTab, (val) => { activeTab.value = val; });

// Auto-switch to non-empty tab when one is empty
const servicesItems = computed(() => props.cart.services?.items ?? []);
const contentItems = computed(() => props.cart.content?.items ?? []);

const hasBothCarts = computed(() => servicesItems.value.length > 0 && contentItems.value.length > 0);

// Services tab state
const creating = ref(false);
const serviceError = ref('');
const confirmDeleteIdx = ref(null);

function askDelete(idx) { confirmDeleteIdx.value = idx; }
function cancelDelete() { confirmDeleteIdx.value = null; }
function confirmDelete(idx) { confirmDeleteIdx.value = null; emit('remove-service', idx); }

const isOpen = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const servicesTotal = computed(() =>
    servicesItems.value.reduce((sum, item) => sum + (item.price || 0) * (item.quantity || 1), 0)
);

const contentTotal = computed(() =>
    contentItems.value.reduce((sum, item) => sum + (item.price || 0), 0)
);

// ── Back-gesture ─────────────────────────────────────────
let cartPushed  = false;
let cartIgnoreTill = 0;

const onCartPopstate = (e) => {
    if (Date.now() < cartIgnoreTill) return;
    if (cartPushed && !e.state?.cart) {
        cartPushed = false;
        isOpen.value = false;
    }
};

watch(isOpen, (val, oldVal) => {
    if (val) {
        history.pushState({ cart: true }, '');
        cartPushed = true;
        window.scrollTo({ top: 0, behavior: 'instant' });
        document.documentElement.classList.add('chat-scroll-locked');
    }
    if (!val && oldVal && cartPushed) {
        cartPushed = false;
        cartIgnoreTill = Date.now() + 500;
        history.go(-1);
        document.documentElement.classList.remove('chat-scroll-locked');
    }
    if (!val && !cartPushed) {
        document.documentElement.classList.remove('chat-scroll-locked');
    }
});

onMounted(() => window.addEventListener('popstate', onCartPopstate));
onUnmounted(() => {
    window.removeEventListener('popstate', onCartPopstate);
    if (cartPushed) { cartPushed = false; history.go(-1); }
    document.documentElement.classList.remove('chat-scroll-locked');
});

function close() { isOpen.value = false; }

async function createOrder() {
    const sc = props.cart.services;
    if (creating.value || !sc.items.length) return;
    creating.value = true;
    serviceError.value = '';
    try {
        const res = await axios.post(route('orders.store'), {
            idol_id: sc.idol_id,
            services: sc.items.map(i => ({ id: i.service_id, quantity: i.quantity || 1 })),
        });
        emit('clear-services');
        isOpen.value = false;
        if (openOrder) openOrder(res.data.order_id);
        router.reload({ only: ['order_notifications_unread'] });
    } catch (e) {
        serviceError.value = e.response?.data?.error ?? __('cart.order.error');
    } finally {
        creating.value = false;
    }
}

// Content tab state
const purchasing = ref(false);
const contentError = ref('');

async function purchaseContent() {
    if (purchasing.value || !contentItems.value.length) return;
    purchasing.value = true;
    contentError.value = '';
    try {
        await axios.post(route('content-packs.purchase'), {
            items: contentItems.value.map(i => i.pack_id),
        });
        emit('clear-content');
        isOpen.value = false;
    } catch (e) {
        contentError.value = e.response?.data?.error ?? __('cart.pay.error');
    } finally {
        purchasing.value = false;
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
                        <span class="rc-store-name">{{ __('cart.title') }}</span>
                        <button class="rc-close" @click="close" :aria-label="__('common.close')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>

                    <!-- Tab switcher — always shown so user can switch -->
                    <div class="rc-tabs">
                        <button class="rc-tab" :class="{ 'rc-tab--active': activeTab === 'services' }"
                            @click="activeTab = 'services'">
                            {{ __('cart.tab.services') }}
                            <span v-if="servicesItems.length" class="rc-tab__badge">{{ servicesItems.length }}</span>
                        </button>
                        <button class="rc-tab" :class="{ 'rc-tab--active': activeTab === 'content' }"
                            @click="activeTab = 'content'">
                            {{ __('cart.tab.content') }}
                            <span v-if="contentItems.length" class="rc-tab__badge">{{
                                contentItems.length }}</span>
                        </button>
                    </div>

                    <div class="rc-rule rc-rule--double"></div>
                </div>

                <!-- ═══ SERVICES tab ═══ -->
                <template v-if="activeTab === 'services'">
                    <span v-if="cart.services?.idol_name" class="rc-store-sub rc-store-sub--pad">{{ __('cart.idol') }} {{
                        cart.services.idol_name }}</span>

                    <div class="rc-body">
                        <div v-if="!servicesItems.length" class="rc-empty">
                            {{ __('cart.empty.services') }}
                        </div>
                        <template v-else>
                            <div v-for="(item, idx) in servicesItems" :key="item.service_id" class="rc-line">
                                <div class="rc-line__top">
                                    <span class="rc-line__name">{{ item.name }}</span>
                                    <template v-if="confirmDeleteIdx === idx">
                                        <div class="rc-line__confirm">
                                            <span class="rc-line__confirm-text">{{ __('cart.delete_confirm') }}</span>
                                            <button class="rc-line__confirm-yes" @click="confirmDelete(idx)">✓</button>
                                            <button class="rc-line__confirm-no" @click="cancelDelete">✕</button>
                                        </div>
                                    </template>
                                    <button v-else class="rc-line__del" @click="askDelete(idx)">✕</button>
                                </div>
                                <div class="rc-line__bottom">
                                    <span class="rc-line__price">{{ (item.price || 0).toLocaleString('ru-RU')
                                    }}&thinsp;₽<template v-if="item.time_unit">&thinsp;/&thinsp;{{ item.time_unit
                                        }}</template></span>
                                    <div class="rc-qty">
                                        <button class="rc-qty__btn" @click="emit('change-quantity', idx, -1)">−</button>
                                        <span class="rc-qty__val">{{ item.quantity || 1 }}</span>
                                        <button class="rc-qty__btn" @click="emit('change-quantity', idx, 1)">+</button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="rc-perf"><span class="rc-perf__line"></span></div>
                    <div class="rc-total">
                        <span class="rc-total__label">{{ __('cart.total') }}</span>
                        <span class="rc-total__sum">{{ servicesTotal.toLocaleString('ru-RU') }}&thinsp;₽</span>
                    </div>
                    <div class="rc-perf"><span class="rc-perf__line"></span></div>
                    <div class="rc-footer">
                        <p v-if="serviceError" class="rc-error">{{ serviceError }}</p>
                        <button class="rc-submit" :disabled="!servicesItems.length || creating" @click="createOrder">{{
                            creating ?
                                __('cart.order.submitting') : __('cart.order.submit') }}</button>
                    </div>
                </template>

                <!-- ═══ CONTENT tab ═══ -->
                <template v-else>
                    <div class="rc-body">
                        <div v-if="!contentItems.length" class="rc-empty">
                            {{ __('cart.empty.content') }}
                        </div>
                        <template v-else>
                            <div v-for="(item, idx) in contentItems" :key="item.pack_id"
                                class="rc-line rc-line--content">
                                <div class="rc-line__top">
                                    <div class="rc-content-item">
                                        <img v-if="item.cover_url" :src="item.cover_url" class="rc-content-item__cover"
                                            alt="" />
                                        <div v-else class="rc-content-item__cover rc-content-item__cover--empty"></div>
                                        <div class="rc-content-item__info">
                                            <span class="rc-line__name">{{ item.title }}</span>
                                            <span v-if="item.idol_name" class="rc-content-item__idol">{{ item.idol_name
                                            }}</span>
                                        </div>
                                    </div>
                                    <button class="rc-line__del" @click="emit('remove-content', idx)">✕</button>
                                </div>
                                <div class="rc-line__bottom">
                                    <span class="rc-line__price">{{ (item.price || 0).toLocaleString('ru-RU')
                                    }}&thinsp;₽</span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="rc-perf"><span class="rc-perf__line"></span></div>
                    <div class="rc-total">
                        <span class="rc-total__label">{{ __('cart.total') }}</span>
                        <span class="rc-total__sum">{{ contentTotal.toLocaleString('ru-RU') }}&thinsp;₽</span>
                    </div>
                    <div class="rc-perf"><span class="rc-perf__line"></span></div>
                    <div class="rc-footer">
                        <p v-if="contentError" class="rc-error">{{ contentError }}</p>
                        <button class="rc-submit" :disabled="!contentItems.length || purchasing"
                            @click="purchaseContent">{{ purchasing ?
                                __('cart.pay.loading') : __('cart.pay.submit') }}</button>
                    </div>
                </template>

            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* ── Transitions ──────────────────────────────────────── */
.rc-fade-enter-active,
.rc-fade-leave-active {
    transition: opacity 0.22s;
}

.rc-fade-enter-from,
.rc-fade-leave-to {
    opacity: 0;
}

.rc-slide-enter-active,
.rc-slide-leave-active {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.rc-slide-enter-from,
.rc-slide-leave-to {
    transform: translateX(100%);
}

/* ── Backdrop ─────────────────────────────────────────── */
.rc-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1100;
    background: rgba(0, 0, 0, 0.55);
}

/* ── Panel ────────────────────────────────────────────── */
.rc-panel {
    position: fixed;
    top: 60px;
    right: 0;
    bottom: 0;
    width: 480px;
    max-width: 100vw;
    z-index: 1101;
    background: #09090f;
    border-left: 1px solid rgba(120, 220, 255, 0.1);
    box-shadow: -12px 0 60px rgba(0, 0, 0, 0.75);
    display: flex;
    flex-direction: column;
    font-family: 'Courier New', Courier, monospace;
    color: rgba(210, 240, 255, 0.78);
    overflow: hidden;
}

.rc-panel::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
    pointer-events: none;
    opacity: 0.4;
    z-index: 0;
}

.rc-panel>* {
    position: relative;
    z-index: 1;
}

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

/* ── Tabs ─────────────────────────────────────────────── */
.rc-tabs {
    display: flex;
    gap: 0.4rem;
}

.rc-tab {
    flex: 1;
    padding: 0.4rem 0.75rem;
    border-radius: 4px;
    background: transparent;
    border: 1px solid rgba(120, 220, 255, 0.12);
    color: rgba(210, 240, 255, 0.4);
    font-family: inherit;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}

.rc-tab:hover {
    background: rgba(120, 220, 255, 0.05);
    color: rgba(210, 240, 255, 0.7);
}

.rc-tab--active {
    background: rgba(100, 210, 255, 0.08);
    border-color: rgba(100, 210, 255, 0.35);
    color: rgba(100, 210, 255, 0.9);
}

.rc-tab__badge {
    color: rgba(100, 210, 255, 0.9);
    border-radius: 3px;
    font-size: 0.8rem;
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

.rc-store-sub--pad {
    padding: 0.25rem 1.5rem 0;
}

/* ── Body ─────────────────────────────────────────────── */
.rc-body {
    flex: 1;
    overflow-y: auto;
    padding: 0.6rem 0;
    scrollbar-width: thin;
    scrollbar-color: rgba(120, 220, 255, 0.1) transparent;
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
    flex-direction: column;
    padding: 0.9rem 1.5rem;
    border-bottom: 1px solid rgba(120, 220, 255, 0.06);
    transition: background 0.12s;
}

.rc-line:hover {
    background: rgba(120, 220, 255, 0.03);
}

.rc-line__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.6rem;
}

.rc-line__name {
    font-size: 1.05rem;
    color: rgba(210, 240, 255, 0.82);
    letter-spacing: 0.02em;
    line-height: 1.35;
    flex: 1;
    min-width: 0;
}

.rc-line__del {
    flex-shrink: 0;
    border: 1px solid rgba(255, 100, 100, 0.2);
    border-radius: 3px;
    background: transparent;
    color: rgba(255, 100, 100, 0.55);
    cursor: pointer;
    font-size: 0.85rem;
    font-family: inherit;
    transition: color 0.12s, border-color 0.12s, background 0.12s;
    padding: 0.2rem 0.45rem;
    line-height: 1;
}

.rc-line__del:hover {
    color: rgba(255, 100, 100, 0.95);
    border-color: rgba(255, 100, 100, 0.5);
    background: rgba(255, 100, 100, 0.07);
}

.rc-line__confirm {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-shrink: 0;
}

.rc-line__confirm-text {
    font-size: 0.85rem;
    color: rgba(255, 100, 100, 0.75);
    letter-spacing: 0.03em;
    white-space: nowrap;
}

.rc-line__confirm-yes,
.rc-line__confirm-no {
    border-radius: 3px;
    background: transparent;
    cursor: pointer;
    font-size: 0.85rem;
    font-family: inherit;
    line-height: 1;
    padding: 0.2rem 0.45rem;
    transition: color 0.12s, border-color 0.12s, background 0.12s;
}

.rc-line__confirm-yes {
    border: 1px solid rgba(255, 100, 100, 0.35);
    color: rgba(255, 100, 100, 0.75);
}

.rc-line__confirm-yes:hover {
    color: rgba(255, 100, 100, 1);
    border-color: rgba(255, 100, 100, 0.7);
    background: rgba(255, 100, 100, 0.1);
}

.rc-line__confirm-no {
    border: 1px solid rgba(120, 220, 255, 0.2);
    color: rgba(120, 220, 255, 0.5);
}

.rc-line__confirm-no:hover {
    color: rgba(120, 220, 255, 0.9);
    border-color: rgba(120, 220, 255, 0.45);
    background: rgba(120, 220, 255, 0.06);
}

.rc-line__bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.5rem;
}

.rc-line__price {
    font-size: 1.05rem;
    font-weight: 700;
    color: rgba(100, 210, 255, 0.95);
    white-space: nowrap;
    letter-spacing: 0.03em;
}

/* Content item layout */
.rc-content-item {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    flex: 1;
    min-width: 0;
}

.rc-content-item__cover {
    width: 40px;
    height: 54px;
    object-fit: cover;
    border-radius: 3px;
    flex-shrink: 0;
    border: 1px solid rgba(255, 255, 255, 0.06);
}

.rc-content-item__cover--empty {
    background: rgba(255, 255, 255, 0.04);
}

.rc-content-item__info {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.rc-content-item__idol {
    font-size: 0.8rem;
    color: rgba(180, 130, 255, 0.7);
    letter-spacing: 0.03em;
}

.rc-qty {
    display: flex;
    align-items: center;
    border: 1px solid rgba(100, 210, 255, 0.18);
    border-radius: 4px;
    overflow: hidden;
}

.rc-qty__btn {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: rgba(100, 210, 255, 0.65);
    cursor: pointer;
    font-size: 1.1rem;
    line-height: 1;
    font-family: inherit;
    transition: background 0.12s, color 0.12s;
}

.rc-qty__btn:hover {
    background: rgba(100, 210, 255, 0.08);
    color: rgba(100, 210, 255, 0.95);
}

.rc-qty__val {
    min-width: 34px;
    text-align: center;
    color: rgba(210, 240, 255, 0.9);
    font-weight: 600;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 28px;
    border-left: 1px solid rgba(100, 210, 255, 0.12);
    border-right: 1px solid rgba(100, 210, 255, 0.12);
}

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
    box-shadow: inset 0 0 4px rgba(0, 0, 0, 0.6);
}

.rc-perf::before {
    left: -6px;
}

.rc-perf::after {
    right: -6px;
}

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

@media (max-width: 768px) {
    .rc-panel { top: 68px; }
}
</style>
