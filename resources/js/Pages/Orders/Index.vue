<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { usePage, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import axios from 'axios';

const props = defineProps({ orders: Array });

const page = usePage();

const authUser = computed(() => page.props.auth.user);
const isIdol   = computed(() => !!page.props.is_idol);

// ── Local orders (real-time patched) ─────────────────────────
const localOrders = ref(props.orders.map(o => ({ ...o })));

// ── Filters ───────────────────────────────────────────────────
const subTab       = ref('mine');
const statusFilter = ref('all');
const search       = ref('');

// ── Side panel ────────────────────────────────────────────────
const selectedOrder = ref(null);

// ── Cancel modal ──────────────────────────────────────────────
const cancelModal       = ref(false);
const cancelReason      = ref('');
const cancelSubmitting  = ref(false);
const cancelOrderId     = ref(null);

const cancelTemplatesCustomer = [
    'Изменились планы',
    'Нашёл другого исполнителя',
    'Сделал заказ по ошибке',
    'Не устраивают условия',
    'Не получил ответа от исполнителя',
    'По личным причинам',
];
const cancelTemplatesIdol = [
    'Изменились планы',
    'Не смогу выполнить этот заказ',
    'Не хватает времени',
    'Слишком большой объём работы',
    'Это не моя специализация',
    'По личным причинам',
];

// ── Helpers ───────────────────────────────────────────────────
function partner(order) {
    return order.is_customer ? order.idol : order.customer;
}

function orderTotal(order) {
    return order.items.reduce((sum, item) => sum + (item.service?.price ?? 0) * (item.quantity ?? 1), 0);
}

function servicesNoun(n) {
    if (n === 1) return 'услуга';
    if (n >= 2 && n <= 4) return 'услуги';
    return 'услуг';
}

function formatDate(iso) {
    if (!iso) return '';
    const d   = new Date(iso);
    const now = new Date();
    if (d.toDateString() === now.toDateString())
        return d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short', year: d.getFullYear() !== now.getFullYear() ? 'numeric' : undefined });
}

// ── Filtered list ─────────────────────────────────────────────
const baseOrders = computed(() => {
    if (!isIdol.value) return localOrders.value;
    return subTab.value === 'mine'
        ? localOrders.value.filter(o => o.is_customer)
        : localOrders.value.filter(o => !o.is_customer);
});

const filteredOrders = computed(() => {
    let list = baseOrders.value;
    if (statusFilter.value !== 'all') list = list.filter(o => o.status === statusFilter.value);
    if (search.value.trim()) {
        const q = search.value.trim().toLowerCase();
        list = list.filter(o => partner(o).name?.toLowerCase().includes(q));
    }
    return list;
});

const statusCounts = computed(() => ({
    all:       baseOrders.value.length,
    pending:   baseOrders.value.filter(o => o.status === 'pending').length,
    accepted:  baseOrders.value.filter(o => o.status === 'accepted').length,
    cancelled: baseOrders.value.filter(o => o.status === 'cancelled').length,
}));

// ── Panel ─────────────────────────────────────────────────────
function selectOrder(order) { selectedOrder.value = order; }
function closePanel()       { selectedOrder.value = null;  }

// ── Actions ───────────────────────────────────────────────────
async function acceptOrder(order) {
    await axios.patch(route('orders.accept', order.id));
    patchOrder(order.id, { status: 'accepted' });
}

function openCancelModal(order) {
    cancelOrderId.value = order.id;
    cancelReason.value  = '';
    cancelModal.value   = true;
}

async function submitCancel() {
    if (!cancelReason.value || cancelSubmitting.value) return;
    cancelSubmitting.value = true;
    try {
        await axios.patch(route('orders.cancel', cancelOrderId.value), { cancel_reason: cancelReason.value });
        patchOrder(cancelOrderId.value, { status: 'cancelled', cancel_reason: cancelReason.value });
        cancelModal.value = false;
    } finally {
        cancelSubmitting.value = false;
    }
}

function patchOrder(id, patch) {
    const idx = localOrders.value.findIndex(o => o.id === id);
    if (idx >= 0) {
        localOrders.value[idx] = { ...localOrders.value[idx], ...patch };
        if (selectedOrder.value?.id === id)
            selectedOrder.value = { ...selectedOrder.value, ...patch };
    }
}

function openChat(orderId) {
    closePanel();
    window.dispatchEvent(new CustomEvent('noalone:open-order', { detail: orderId }));
}

// ── Lazy loading ─────────────────────────────────────────────
const BATCH      = 15;
const visibleCount = ref(BATCH);
const visibleOrders = computed(() => filteredOrders.value.slice(0, visibleCount.value));
const sentinel   = ref(null);

watch([subTab, statusFilter, search], () => { visibleCount.value = BATCH; });

// ── Real-time + URL auto-open ─────────────────────────────────
let echoChannel  = null;
let lazyObserver = null;

onMounted(() => {
    const params  = new URLSearchParams(location.search);
    const orderId = parseInt(params.get('order'));
    if (orderId) {
        const found = localOrders.value.find(o => o.id === orderId);
        if (found) selectedOrder.value = found;
    }

    if (window.Echo && authUser.value?.id) {
        echoChannel = window.Echo.private(`orders.${authUser.value.id}`)
            .listen('.order.changed', ({ change_type, order }) => {
                const idx = localOrders.value.findIndex(o => o.id === order.id);
                if (idx >= 0) {
                    localOrders.value[idx] = order;
                    if (selectedOrder.value?.id === order.id) selectedOrder.value = order;
                } else if (change_type === 'created') {
                    localOrders.value.unshift(order);
                }
            });
    }

    lazyObserver = new IntersectionObserver(([entry]) => {
        if (entry.isIntersecting && visibleCount.value < filteredOrders.value.length) {
            visibleCount.value = Math.min(visibleCount.value + 10, filteredOrders.value.length);
        }
    }, { rootMargin: '200px' });
    if (sentinel.value) lazyObserver.observe(sentinel.value);
});

onUnmounted(() => {
    echoChannel?.stopListening('.order.changed');
    lazyObserver?.disconnect();
});
</script>

<template>
    <Head title="Заказы" />
    <AppLayout>
        <div class="orders-page">

            <!-- ── Header ──────────────────────────────────────── -->
            <div class="orders-header">
                <h1 class="orders-title">Заказы</h1>

                <div class="orders-controls">
                    <!-- Subtabs (idol only) -->
                    <div v-if="isIdol" class="orders-subtabs">
                        <button
                            class="orders-subtab"
                            :class="{ 'orders-subtab--active': subTab === 'mine' }"
                            @click="subTab = 'mine'; statusFilter = 'all'"
                        >Мои</button>
                        <button
                            class="orders-subtab"
                            :class="{ 'orders-subtab--active': subTab === 'incoming' }"
                            @click="subTab = 'incoming'; statusFilter = 'all'"
                        >Входящие</button>
                    </div>

                    <!-- Status filters -->
                    <div class="orders-status-filters">
                        <button
                            v-for="s in ['all','pending','accepted','cancelled']"
                            :key="s"
                            class="orders-status-btn"
                            :class="[`orders-status-btn--${s}`, { 'orders-status-btn--active': statusFilter === s }]"
                            @click="statusFilter = s"
                        >
                            {{ { all: 'Все', pending: 'Ожидает', accepted: 'Принят', cancelled: 'Отменён' }[s] }}
                            <span class="orders-status-btn__count">{{ statusCounts[s] }}</span>
                        </button>
                    </div>
                </div>

                <!-- Search -->
                <div class="orders-search">
                    <input
                        v-model="search"
                        class="orders-search__input"
                        type="text"
                        placeholder="Поиск по имени..."
                    />
                </div>
            </div>

            <!-- ── Grid ────────────────────────────────────────── -->
            <div v-if="filteredOrders.length > 0" class="orders-grid">
                <div
                    v-for="order in visibleOrders"
                    :key="order.id"
                    class="ocard"
                    :class="[`ocard--${order.status}`, { 'ocard--selected': selectedOrder?.id === order.id }]"
                    @click="selectOrder(order)"
                >
                    <!-- Head -->
                    <div class="ocard__head">
                        <div class="ocard__avatar">
                            <img v-if="partner(order).avatar_url" :src="partner(order).avatar_url" alt="" />
                            <span v-else>{{ partner(order).name?.charAt(0) ?? '?' }}</span>
                        </div>
                        <div class="ocard__who">
                            <span class="ocard__name">{{ partner(order).name }}</span>
                            <span class="ocard__date">{{ formatDate(order.created_at) }}</span>
                        </div>
                        <span class="ocard__badge" :class="`ocard__badge--${order.status}`">
                            {{ { pending: 'Ожидает', accepted: 'Принят', cancelled: 'Отменён' }[order.status] }}
                        </span>
                    </div>

                    <!-- Perf -->
                    <div class="ocard__perf">
                        <span v-for="n in 18" :key="n" class="ocard__perf-dot"></span>
                    </div>

                    <!-- Items preview -->
                    <div class="ocard__items">
                        <div v-for="item in order.items" :key="item.id" class="ocard__item">
                            <span class="ocard__item-name">{{ item.service?.name }}</span>
                            <span v-if="(item.quantity ?? 1) > 1" class="ocard__item-qty">×{{ item.quantity }}</span>
                            <span class="ocard__item-price">{{ ((item.service?.price ?? 0) * (item.quantity ?? 1)).toLocaleString('ru-RU') }}&thinsp;₽</span>
                        </div>
                    </div>

                    <!-- Foot -->
                    <div class="ocard__foot">
                        <span class="ocard__count">{{ order.items.length }}&thinsp;{{ servicesNoun(order.items.length) }}</span>
                        <span class="ocard__total">{{ orderTotal(order).toLocaleString('ru-RU') }}&thinsp;₽</span>
                    </div>

                    <!-- Quick action -->
                    <div class="ocard__actions" v-if="order.status !== 'cancelled'" @click.stop>
                        <button
                            v-if="!order.is_customer && order.status === 'pending'"
                            class="ocard__btn ocard__btn--accept"
                            @click="acceptOrder(order)"
                        >Принять</button>
                        <button
                            class="ocard__btn ocard__btn--cancel"
                            @click="openCancelModal(order)"
                        >Отменить</button>
                    </div>
                    <div v-else class="ocard__cancelled-note">
                        <span v-if="order.cancel_reason">{{ order.cancel_reason }}</span>
                    </div>
                </div>
            </div>

            <!-- Lazy sentinel -->
            <div
                v-if="visibleCount < filteredOrders.length"
                ref="sentinel"
                class="orders-sentinel"
            >
                <span class="orders-sentinel__dot" v-for="n in 3" :key="n"></span>
            </div>

            <!-- Empty state -->
            <div v-if="filteredOrders.length === 0" class="orders-empty">
                <p>Заказов нет</p>
            </div>

        </div>

        <!-- ── Side panel ──────────────────────────────────────── -->
        <Transition name="panel">
            <div v-if="selectedOrder" class="orders-panel-backdrop" @click.self="closePanel">
                <div class="orders-panel">

                    <!-- Header -->
                    <div class="opanel__header">
                        <button class="opanel__back" @click="closePanel">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M10 3L5 8L10 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <div class="opanel__header-text">
                            <span class="opanel__title">Заказ #{{ selectedOrder.id }}</span>
                            <span class="opanel__date">{{ formatDate(selectedOrder.created_at) }}</span>
                        </div>
                        <span class="opanel__status-badge" :class="`opanel__status-badge--${selectedOrder.status}`">
                            {{ { pending: 'Ожидает', accepted: 'Принят', cancelled: 'Отменён' }[selectedOrder.status] }}
                        </span>
                    </div>

                    <!-- Partner -->
                    <div class="opanel__partner">
                        <div class="opanel__avatar">
                            <img v-if="partner(selectedOrder).avatar_url" :src="partner(selectedOrder).avatar_url" alt="" />
                            <span v-else>{{ partner(selectedOrder).name?.charAt(0) ?? '?' }}</span>
                        </div>
                        <div class="opanel__partner-info">
                            <span class="opanel__partner-name">{{ partner(selectedOrder).name }}</span>
                            <span class="opanel__partner-role">{{ selectedOrder.is_customer ? 'Исполнитель' : 'Заказчик' }}</span>
                        </div>
                    </div>

                    <!-- Items -->
                    <div class="opanel__lines">
                        <div v-for="item in selectedOrder.items" :key="item.id" class="opanel__line">
                            <div class="opanel__line-left">
                                <span class="opanel__line-name">{{ item.service?.name }}</span>
                                <span v-if="(item.quantity ?? 1) > 1" class="opanel__line-qty">× {{ item.quantity }}</span>
                            </div>
                            <span class="opanel__line-price">
                                {{ ((item.service?.price ?? 0) * (item.quantity ?? 1)).toLocaleString('ru-RU') }}&thinsp;₽<template v-if="item.service?.time_unit">&thinsp;/&thinsp;{{ item.service.time_unit }}</template>
                            </span>
                        </div>
                    </div>

                    <!-- Total -->
                    <div class="opanel__total">
                        <span class="opanel__total-label">Итого</span>
                        <span class="opanel__total-value">{{ orderTotal(selectedOrder).toLocaleString('ru-RU') }}&thinsp;₽</span>
                    </div>

                    <!-- Cancel note -->
                    <div v-if="selectedOrder.status === 'cancelled'" class="opanel__cancel-note">
                        <span class="opanel__cancel-by">
                            {{ selectedOrder.cancelled_by === authUser?.id ? 'Отменили вы' : 'Отменил ' + (selectedOrder.cancelled_by_name ?? 'другая сторона') }}
                        </span>
                        <span v-if="selectedOrder.cancel_reason" class="opanel__cancel-reason">{{ selectedOrder.cancel_reason }}</span>
                    </div>

                    <!-- Actions -->
                    <div class="opanel__actions">
                        <button
                            v-if="!selectedOrder.is_customer && selectedOrder.status === 'pending'"
                            class="opanel__action-btn opanel__action-btn--accept"
                            @click="acceptOrder(selectedOrder)"
                        >
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7l4 4 6-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Принять заказ
                        </button>
                        <button
                            v-if="selectedOrder.conversation_id"
                            class="opanel__action-btn opanel__action-btn--chat"
                            @click="openChat(selectedOrder.id)"
                        >
                            <svg width="14" height="14" viewBox="0 0 15 15" fill="none"><path d="M1 1h13v9H8.5L5 13.5V10H1V1z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>
                            Открыть чат
                        </button>
                        <button
                            v-if="selectedOrder.status !== 'cancelled'"
                            class="opanel__action-btn opanel__action-btn--cancel"
                            @click="openCancelModal(selectedOrder)"
                        >
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 2l10 10M12 2L2 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                            Отменить
                        </button>
                    </div>

                </div>
            </div>
        </Transition>

        <!-- ── Cancel modal ────────────────────────────────────── -->
        <SiteModal :show="cancelModal" variant="pink" compact @close="cancelModal = false">
            <div class="cm-wrap">
                <div class="cm-rule cm-rule--double cm-rule--red"></div>
                <h2 class="cm-title">ОТМЕНА ЗАКАЗА</h2>
                <div class="cm-rule cm-rule--double cm-rule--red"></div>

                <div class="cm-section-label">// ВЫБЕРИТЕ ПРИЧИНУ</div>
                <div class="cm-tags">
                    <button
                        v-for="t in (localOrders.find(o => o.id === cancelOrderId)?.is_customer ? cancelTemplatesCustomer : cancelTemplatesIdol)"
                        :key="t"
                        class="cm-tag"
                        :class="{ 'cm-tag--selected': cancelReason === t }"
                        @click="cancelReason = t"
                    >{{ t }}</button>
                </div>

                <div class="cm-section-label">// ИЛИ НАПИШИТЕ СВОЮ</div>
                <textarea
                    v-model="cancelReason"
                    class="cm-textarea"
                    placeholder="причина отмены…"
                    rows="3"
                    maxlength="1000"
                ></textarea>

                <div class="cm-perf"><span class="cm-perf__line"></span></div>

                <div class="cm-footer">
                    <button class="cm-btn cm-btn--back" @click="cancelModal = false">НАЗАД</button>
                    <button
                        class="cm-btn cm-btn--confirm"
                        :disabled="!cancelReason.trim() || cancelSubmitting"
                        @click="submitCancel"
                    >{{ cancelSubmitting ? 'ОТМЕНЯЕМ…' : 'ПОДТВЕРДИТЬ' }}</button>
                </div>
            </div>
        </SiteModal>

    </AppLayout>
</template>

<style scoped>
/* ── Page layout ──────────────────────────────────────────── */
.orders-page {
    max-width: 860px;
    margin: 0 auto;
    padding: 2rem 1.5rem 4rem;
}

/* ── Header ───────────────────────────────────────────────── */
.orders-header {
    margin-bottom: 2rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.orders-title {
    font-size: 1.6rem;
    font-weight: 700;
    color: rgba(220,220,255,0.9);
    letter-spacing: 0.02em;
    margin: 0;
}
.orders-controls {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}

/* Subtabs */
.orders-subtabs {
    display: flex;
    background: rgba(160,160,255,0.05);
    border: 1px solid rgba(160,160,255,0.12);
    border-radius: 3px;
    padding: 3px;
    gap: 2px;
}
.orders-subtab {
    background: transparent;
    border: none;
    color: rgba(160,160,255,0.5);
    font-size: 0.92rem;
    padding: 0.3rem 0.9rem;
    border-radius: 3px;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.orders-subtab--active {
    background: rgba(160,160,255,0.12);
    color: rgba(200,200,255,0.95);
}

/* Status filters */
.orders-status-filters {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
}
.orders-status-btn {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    background: transparent;
    border: 1px solid rgba(160,160,255,0.15);
    border-radius: 3px;
    color: rgba(160,160,255,0.5);
    font-size: 1rem;
    padding: 0.25rem 0.75rem;
    cursor: pointer;
    transition: all 0.15s;
}
.orders-status-btn__count {
    font-size: 1rem;
    background: rgba(160,160,255,0.1);
    border-radius: 3px;
    padding: 0 0.35rem;
    min-width: 1.2rem;
    text-align: center;
}
.orders-status-btn--active {
    color: rgba(200,200,255,0.9);
    border-color: rgba(160,160,255,0.35);
    background: rgba(160,160,255,0.08);
}
.orders-status-btn--pending.orders-status-btn--active  { border-color: rgba(255,200,80,0.45); color: rgba(255,200,80,0.9); }
.orders-status-btn--accepted.orders-status-btn--active { border-color: rgba(80,240,160,0.45); color: rgba(80,240,160,0.9); }
.orders-status-btn--cancelled.orders-status-btn--active { border-color: rgba(255,110,110,0.45); color: rgba(255,110,110,0.85); }

/* Search */
.orders-search__input {
    width: 100%;
    max-width: 320px;
    background: rgba(160,160,255,0.04);
    border: 1px solid rgba(160,160,255,0.15);
    border-radius: 3px;
    color: rgba(220,220,255,0.85);
    font-size: 1.05rem;
    padding: 0.45rem 0.85rem;
    outline: none;
    transition: border-color 0.15s;
}
.orders-search__input::placeholder { color: rgba(160,160,255,0.3); }
.orders-search__input:focus { border-color: rgba(160,160,255,0.45); }

/* ── List ─────────────────────────────────────────────────── */
.orders-grid {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

@keyframes ocard-in {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Order card ───────────────────────────────────────────── */
.ocard {
    display: flex;
    flex-direction: column;
    background: rgba(160,160,255,0.03);
    border: 1px solid rgba(160,160,255,0.1);
    border-radius: 3px;
    padding: 1.5rem 2rem;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
    animation: ocard-in 0.2s ease both;
    position: relative;
}
.ocard::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg,
        transparent 0%,
        rgba(160,160,255,0.15) 10%,
        rgba(160,160,255,0.7) 50%,
        rgba(160,160,255,0.15) 90%,
        transparent 100%
    );
    pointer-events: none;
    z-index: 1;
}
.ocard:hover    { background: rgba(160,160,255,0.055); }
.ocard--selected { background: rgba(160,160,255,0.08); border-color: rgba(160,160,255,0.3); }
.ocard--cancelled { opacity: 0.65; }

/* Head — partner */
.ocard__head {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    margin-bottom: 1rem;
}
.ocard__avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    overflow: hidden;
    background: rgba(160,160,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: rgba(160,160,255,0.6);
    flex-shrink: 0;
}
.ocard__avatar img { width: 100%; height: 100%; object-fit: cover; }
.ocard__who {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1;
    min-width: 0;
}
.ocard__name {
    font-size: 1rem;
    font-weight: 600;
    color: rgba(220,220,255,0.9);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ocard__date {
    font-size: 0.85rem;
    color: rgba(160,160,255,0.42);
}
.ocard__badge {
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.22rem 0.6rem;
    border-radius: 3px;
    white-space: nowrap;
    flex-shrink: 0;
}
.ocard__badge--pending  { background: rgba(255,200,80,0.1);  color: rgba(255,200,80,0.9); }
.ocard__badge--accepted { background: rgba(80,240,160,0.1);  color: rgba(80,240,160,0.9); }
.ocard__badge--cancelled { background: rgba(255,110,110,0.08); color: rgba(255,110,110,0.75); }

/* Perf + Items — скрыты */
.ocard__perf  { display: none; }
.ocard__items { display: none; }

/* Foot — count + total */
.ocard__foot {
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: baseline;
    border-top: 1px solid rgba(160,160,255,0.08);
    padding-top: 0.85rem;
    padding-bottom: 0.85rem;
}
.ocard__count {
    font-size: 0.9rem;
    color: rgba(160,160,255,0.45);
}
.ocard__total {
    font-size: 1.15rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: rgba(255,210,80,0.9);
}
.ocard--accepted .ocard__total { color: rgba(80,240,160,0.9); }
.ocard--cancelled .ocard__total { color: rgba(255,130,130,0.65); }

/* Actions */
.ocard__actions {
    display: flex;
    flex-direction: row;
    gap: 0.65rem;
}
.ocard__btn {
    flex: 1;
    padding: 0.55rem 1rem;
    border-radius: 3px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.15s;
    text-align: center;
}
.ocard__btn--accept {
    background: rgba(80,240,160,0.08);
    border-color: rgba(80,240,160,0.25);
    color: rgba(80,240,160,0.9);
}
.ocard__btn--accept:hover { background: rgba(80,240,160,0.15); }
.ocard__btn--cancel {
    background: rgba(255,110,110,0.06);
    border-color: rgba(255,110,110,0.2);
    color: rgba(255,110,110,0.78);
}
.ocard__btn--cancel:hover { background: rgba(255,110,110,0.12); }
.ocard__cancelled-note {
    font-size: 0.88rem;
    color: rgba(160,160,255,0.3);
    font-style: italic;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    border-top: 1px solid rgba(160,160,255,0.08);
    padding-top: 0.85rem;
}

/* ── Lazy sentinel ────────────────────────────────────────── */
.orders-sentinel {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    padding: 2rem 0 1rem;
}
.orders-sentinel__dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: rgba(160,160,255,0.25);
    animation: sentinel-pulse 1.1s ease-in-out infinite;
}
.orders-sentinel__dot:nth-child(2) { animation-delay: 0.18s; }
.orders-sentinel__dot:nth-child(3) { animation-delay: 0.36s; }
@keyframes sentinel-pulse {
    0%, 100% { opacity: 0.2; transform: scale(0.8); }
    50%       { opacity: 0.8; transform: scale(1.2); }
}

/* Empty state */
.orders-empty {
    text-align: center;
    padding: 4rem 0;
    color: rgba(160,160,255,0.3);
    font-size: 1rem;
}

/* ── Side panel ───────────────────────────────────────────── */
.orders-panel-backdrop {
    position: fixed;
    inset: 0;
    z-index: 200;
    background: rgba(0,0,0,0.45);
    backdrop-filter: blur(4px);
    display: flex;
    justify-content: flex-end;
}
.orders-panel {
    width: min(680px, 100vw);
    height: 100%;
    background: #080815;
    border-left: 1px solid rgba(160,160,255,0.1);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

/* Header */
.opanel__header {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1.25rem 2rem;
    border-bottom: 1px solid rgba(160,160,255,0.08);
    flex-shrink: 0;
}
.opanel__back {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    background: transparent;
    border: 1px solid rgba(160,160,255,0.15);
    border-radius: 3px;
    color: rgba(160,160,255,0.5);
    cursor: pointer;
    flex-shrink: 0;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
}
.opanel__back:hover { color: rgba(200,200,255,0.9); border-color: rgba(160,160,255,0.4); background: rgba(160,160,255,0.06); }
.opanel__header-text {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    flex: 1;
    min-width: 0;
}
.opanel__title {
    font-size: 1.05rem;
    font-weight: 600;
    color: rgba(210,210,255,0.9);
}
.opanel__date {
    font-size: 0.92rem;
    color: rgba(160,160,255,0.35);
}
.opanel__status-badge {
    font-size: 1rem;
    font-weight: 600;
    padding: 0.22rem 0.6rem;
    border-radius: 3px;
    white-space: nowrap;
    flex-shrink: 0;
}
.opanel__status-badge--pending  { background: rgba(255,200,80,0.1); color: rgba(255,200,80,0.9); }
.opanel__status-badge--accepted { background: rgba(80,240,160,0.1); color: rgba(80,240,160,0.9); }
.opanel__status-badge--cancelled { background: rgba(255,110,110,0.08); color: rgba(255,110,110,0.75); }

/* Partner */
.opanel__partner {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1.25rem 2rem;
    border-bottom: 1px solid rgba(160,160,255,0.07);
}
.opanel__avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    overflow: hidden;
    background: rgba(160,160,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: rgba(160,160,255,0.6);
    flex-shrink: 0;
}
.opanel__avatar img { width: 100%; height: 100%; object-fit: cover; }
.opanel__partner-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.opanel__partner-name {
    font-size: 1.05rem;
    font-weight: 600;
    color: rgba(220,220,255,0.88);
}
.opanel__partner-role {
    font-size: 0.92rem;
    color: rgba(160,160,255,0.4);
}

/* Lines */
.opanel__lines {
    display: flex;
    flex-direction: column;
    padding: 1rem 2rem;
    gap: 0;
    border-bottom: 1px solid rgba(160,160,255,0.07);
}
.opanel__line {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.65rem 0;
    border-bottom: 1px solid rgba(160,160,255,0.05);
}
.opanel__line:last-child { border-bottom: none; }
.opanel__line-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
    min-width: 0;
    flex-wrap: wrap;
}
.opanel__line-name {
    font-size: 0.975rem;
    color: rgba(210,210,255,0.75);
    line-height: 1.4;
}
.opanel__line-qty {
    font-size: 0.92rem;
    color: rgba(160,160,255,0.5);
    background: rgba(160,160,255,0.08);
    border-radius: 3px;
    padding: 0.1rem 0.4rem;
    flex-shrink: 0;
}
.opanel__line-price {
    font-size: 0.975rem;
    font-weight: 600;
    color: rgba(200,200,255,0.8);
    white-space: nowrap;
    flex-shrink: 0;
    font-variant-numeric: tabular-nums;
}

/* Total */
.opanel__total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
}
.opanel__total-label {
    font-size: 1rem;
    color: rgba(160,160,255,0.4);
}
.opanel__total-value {
    font-size: 1.35rem;
    font-weight: 700;
    color: rgba(220,220,255,0.95);
    font-variant-numeric: tabular-nums;
}

/* Cancel note */
.opanel__cancel-note {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding: 1rem 2rem;
    background: rgba(255,100,100,0.04);
    border-top: 1px solid rgba(255,100,100,0.08);
}
.opanel__cancel-by {
    font-size: 0.98rem;
    font-weight: 600;
    color: rgba(255,130,130,0.65);
}
.opanel__cancel-reason {
    font-size: 0.975rem;
    color: rgba(255,180,180,0.55);
    line-height: 1.5;
}

/* Panel actions */
.opanel__actions {
    display: flex;
    gap: 0.65rem;
    padding: 1rem 2rem 0.5rem;
}
.opanel__action-btn {
    flex: 1;
    padding: 0.65rem 1rem;
    border-radius: 3px;
    font-size: 0.93rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.15s;
}
.opanel__action-btn--accept {
    background: rgba(80,240,160,0.1);
    border-color: rgba(80,240,160,0.25);
    color: rgba(80,240,160,0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}
.opanel__action-btn--accept:hover { background: rgba(80,240,160,0.18); }
.opanel__action-btn--chat {
    flex: 1;
    background: rgba(160,160,255,0.08);
    border-color: rgba(160,160,255,0.25);
    color: rgba(190,190,255,0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}
.opanel__action-btn--chat:hover { background: rgba(160,160,255,0.16); border-color: rgba(160,160,255,0.45); }
.opanel__action-btn--cancel {
    background: rgba(255,110,110,0.06);
    border-color: rgba(255,110,110,0.2);
    color: rgba(255,110,110,0.65);
    flex: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}
.opanel__action-btn--cancel:hover { background: rgba(255,110,110,0.12); color: rgba(255,110,110,0.85); }

/* ── Cancel modal (cm-* скопировано из ChatPanel, шрифты увеличены) ── */
.cm-wrap {
    font-family: 'Courier New', Courier, monospace;
    color: rgba(210,240,255,0.78);
    display: flex;
    flex-direction: column;
    gap: 0;
}
.cm-rule {
    width: 100%;
    height: 0;
    border: none;
    border-top: 2px double rgba(100,210,255,0.3);
    margin: 0.5rem 0;
}
.cm-rule--red { border-color: rgba(220,80,80,0.45); }
.cm-title {
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.22em;
    color: rgba(255,120,120,0.9);
    text-align: center;
    margin: 0.3rem 0;
}
.cm-section-label {
    font-size: 0.82rem;
    letter-spacing: 0.12em;
    color: rgba(210,240,255,0.3);
    margin: 0.9rem 0 0.45rem;
}
.cm-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 0.25rem;
}
.cm-tag {
    padding: 0.38rem 0.8rem;
    border-radius: 3px;
    border: 1px dashed rgba(210,240,255,0.2);
    background: transparent;
    color: rgba(210,240,255,0.5);
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.9rem;
    letter-spacing: 0.04em;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.cm-tag:hover { border-color: rgba(210,240,255,0.45); color: rgba(210,240,255,0.85); background: rgba(100,210,255,0.05); }
.cm-tag--selected { border-color: rgba(100,210,255,0.55); color: rgba(100,210,255,0.95); background: rgba(100,210,255,0.08); border-style: solid; }
.cm-textarea {
    width: 100%;
    box-sizing: border-box;
    background: rgba(100,210,255,0.04);
    border: 1px dashed rgba(100,210,255,0.22);
    border-radius: 3px;
    color: rgba(210,240,255,0.82);
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.95rem;
    padding: 0.65rem 0.85rem;
    resize: none;
    outline: none;
    transition: border-color 0.15s;
    margin-top: 0.1rem;
}
.cm-textarea::placeholder { color: rgba(210,240,255,0.2); }
.cm-textarea:focus { border-color: rgba(100,210,255,0.45); border-style: solid; }
.cm-perf {
    display: flex;
    align-items: center;
    margin: 1rem -0.1rem 0.85rem;
    position: relative;
}
.cm-perf::before,
.cm-perf::after {
    content: '';
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #12122a;
    border: 1px solid rgba(220,80,80,0.15);
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
}
.cm-perf::before { left: -5px; }
.cm-perf::after  { right: -5px; }
.cm-perf__line { flex: 1; display: block; border-top: 1px dashed rgba(220,80,80,0.3); margin: 0 7px; }
.cm-footer { display: flex; gap: 0.55rem; }
.cm-btn {
    flex: 1;
    padding: 0.65rem 1rem;
    border-radius: 3px;
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.92rem;
    font-weight: 700;
    letter-spacing: 0.15em;
    cursor: pointer;
    line-height: 1;
    transition: background 0.15s, border-color 0.15s;
}
.cm-btn--back { background: transparent; border: 1px dashed rgba(210,240,255,0.18); color: rgba(210,240,255,0.4); }
.cm-btn--back:hover { border-color: rgba(210,240,255,0.4); color: rgba(210,240,255,0.75); }
.cm-btn--confirm { background: rgba(220,60,60,0.08); border: 1px solid rgba(220,60,60,0.4); color: rgba(255,120,120,0.9); }
.cm-btn--confirm:hover:not(:disabled) { background: rgba(220,60,60,0.18); border-color: rgba(220,60,60,0.65); }
.cm-btn--confirm:disabled { opacity: 0.3; cursor: not-allowed; }

/* ── Transitions ──────────────────────────────────────────── */
.panel-enter-active, .panel-leave-active { transition: opacity 0.2s ease; }
.panel-enter-active .orders-panel, .panel-leave-active .orders-panel { transition: transform 0.25s cubic-bezier(0.4,0,0.2,1); }
.panel-enter-from, .panel-leave-to { opacity: 0; }
.panel-enter-from .orders-panel, .panel-leave-to .orders-panel { transform: translateX(100%); }
</style>
