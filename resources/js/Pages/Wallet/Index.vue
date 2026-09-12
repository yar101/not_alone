<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { ElNotification, ElTooltip, ElIcon } from 'element-plus';
import {
    Wallet,
    Lock,
    Coin,
    Plus,
    Upload,
    BottomLeft,
    TopRight,
    InfoFilled,
    Tickets,
    Clock,
    Loading,
} from '@element-plus/icons-vue';

const props = defineProps({
    wallet: {
        type: Object,
        required: true,
    },
    transactions: {
        type: Object,
        default: () => ({ data: [], links: [], total: 0 }),
    },
    canDeposit: {
        type: Boolean,
        default: false,
    },
    isIdol: {
        type: Boolean,
        default: false,
    },
    canWithdraw: {
        type: Boolean,
        default: false,
    },
});

const currentWallet = ref({ ...props.wallet });

// ── Navigation View ──────────────────────────────────────────
const activeView = ref('overview'); // 'overview' | 'history'
const actionTab = ref('deposit'); // 'deposit' | 'withdraw'

// ── Financial Actions ────────────────────────────────────────
const depositAmount = ref(250);
const withdrawAmount = ref('');
const isDepositing = ref(false);
const isWithdrawing = ref(false);

const quickAmounts = [250, 550, 950];

// ── History Filter ───────────────────────────────────────────
const activeHistoryTab = ref('all');

const historyTabs = computed(() => {
    const tabs = [
        { id: 'all', label: 'Все операции' },
        { id: 'deposit', label: 'Пополнения' },
        { id: 'orders', label: 'Заказы' },
        { id: 'packs', label: 'Контент' },
    ];
    if (props.isIdol) {
        tabs.push({ id: 'withdrawal', label: 'Вывод' });
    }
    return tabs;
});

const filteredTransactions = computed(() => {
    const list = props.transactions?.data || [];
    if (activeHistoryTab.value === 'all') return list;
    if (activeHistoryTab.value === 'deposit') return list.filter(t => t.type === 'deposit');
    if (activeHistoryTab.value === 'orders') return list.filter(t => ['order_hold', 'order_payout', 'order_refund'].includes(t.type));
    if (activeHistoryTab.value === 'packs') return list.filter(t => ['pack_purchase', 'pack_sale'].includes(t.type));
    if (activeHistoryTab.value === 'withdrawal') return list.filter(t => t.type === 'withdrawal');
    return list;
});

// ── Deposit ──────────────────────────────────────────────────
async function handleDeposit() {
    const amt = Number(depositAmount.value);
    if (!amt || amt < 10) {
        ElNotification({
            title: 'Неверная сумма',
            message: 'Минимальная сумма пополнения — 10 ₽',
            type: 'warning',
            customClass: 'app-notif app-notif--warn',
        });
        return;
    }

    isDepositing.value = true;
    try {
        const { data } = await axios.post(route('wallet.deposit'), {
            amount: amt,
        });

        ElNotification({
            title: 'Баланс пополнен',
            message: 'Счёт успешно пополнен на ' + formatMoney(amt) + ' ₽',
            type: 'success',
            customClass: 'app-notif',
        });

        currentWallet.value.balance = data.balance;
        currentWallet.value.total_balance = Number(data.balance) + Number(currentWallet.value.held_balance);

        router.reload({ only: ['transactions', 'wallet'] });
    } catch (e) {
        ElNotification({
            title: 'Ошибка пополнения',
            message: e.response?.data?.error || 'Не удалось выполнить пополнение',
            type: 'error',
            customClass: 'app-notif',
        });
    } finally {
        isDepositing.value = false;
    }
}

// ── Withdraw ─────────────────────────────────────────────────
async function handleWithdraw() {
    const amt = Number(withdrawAmount.value);
    if (!amt || amt < 100) {
        ElNotification({
            title: 'Неверная сумма',
            message: 'Минимальная сумма для вывода — 100 ₽',
            type: 'warning',
            customClass: 'app-notif app-notif--warn',
        });
        return;
    }

    if (amt > Number(currentWallet.value.balance)) {
        ElNotification({
            title: 'Недостаточно средств',
            message: 'Сумма вывода превышает доступный баланс',
            type: 'warning',
            customClass: 'app-notif app-notif--warn',
        });
        return;
    }

    isWithdrawing.value = true;
    try {
        const { data } = await axios.post(route('wallet.withdraw'), {
            amount: amt,
        });

        ElNotification({
            title: 'Заявка на вывод принята',
            message: 'Средства в размере ' + formatMoney(amt) + ' ₽ отправлены на вывод',
            type: 'success',
            customClass: 'app-notif',
        });

        currentWallet.value.balance = data.balance;
        currentWallet.value.total_balance = Number(data.balance) + Number(currentWallet.value.held_balance);
        withdrawAmount.value = '';

        router.reload({ only: ['transactions', 'wallet'] });
    } catch (e) {
        ElNotification({
            title: 'Ошибка вывода',
            message: e.response?.data?.error || 'Не удалось выполнить вывод средств',
            type: 'error',
            customClass: 'app-notif',
        });
    } finally {
        isWithdrawing.value = false;
    }
}

// ── Formatters ───────────────────────────────────────────────
function formatMoney(val) {
    return Number(val || 0).toLocaleString('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    const isToday = d.toDateString() === now.toDateString();
    
    const timeStr = d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    if (isToday) {
        return 'Сегодня, ' + timeStr;
    }
    
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) {
        return 'Вчера, ' + timeStr;
    }

    return d.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: 'short',
        year: d.getFullYear() !== now.getFullYear() ? 'numeric' : undefined,
        hour: '2-digit',
        minute: '2-digit',
    });
}

function isPositiveTx(type) {
    return ['deposit', 'order_payout', 'order_refund', 'pack_sale'].includes(type);
}

function isHoldTx(type) {
    return type === 'order_hold';
}
</script>

<template>
    <Head title="Мой кошелёк" />
    <AppLayout>
        <div class="wallet-viewport">
            <!-- Background Ambient Lighting -->
            <div class="wallet-ambient ambient-pink" />
            <div class="wallet-ambient ambient-blue" />

            <div class="wallet-wrapper">
                <!-- Top Navigation & View Switcher -->
                <div class="wallet-topbar">
                    <div class="wallet-brand">
                        <div class="wallet-brand__icon">
                            <el-icon><Wallet /></el-icon>
                        </div>
                        <div>
                            <h1 class="wallet-brand__title">Кошелёк</h1>
                            <p class="wallet-brand__sub">Личный счёт и история операций</p>
                        </div>
                    </div>

                    <!-- 2 Main Tabs: Balance Overview vs History -->
                    <div class="wallet-views">
                        <button
                            type="button"
                            class="wallet-view-btn"
                            :class="{ 'wallet-view-btn--active': activeView === 'overview' }"
                            @click="activeView = 'overview'"
                        >
                            <el-icon><Wallet /></el-icon>
                            <span>Мой баланс</span>
                        </button>

                        <button
                            type="button"
                            class="wallet-view-btn"
                            :class="{ 'wallet-view-btn--active': activeView === 'history' }"
                            @click="activeView = 'history'"
                        >
                            <el-icon><Tickets /></el-icon>
                            <span>История операций</span>
                            <span class="wallet-view-badge" v-if="transactions.data?.length">
                                {{ transactions.total ?? transactions.data.length }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- ════════════ VIEW 1: MY BALANCE (100% ONE-SCREEN FIT) ════════════ -->
                <div v-if="activeView === 'overview'" class="wallet-overview">
                    <!-- 3 Compact Glass Balance Cards -->
                    <div class="wallet-cards-grid">
                        <!-- Card 1: Available -->
                        <div class="wcard wcard--available">
                            <div class="wcard__top">
                                <div class="wcard__badge wcard__badge--emerald">
                                    <span class="wcard__dot wcard__dot--emerald"></span>
                                    Доступно
                                </div>
                                <el-tooltip
                                    placement="top"
                                    effect="dark"
                                    popper-class="wallet-dark-tooltip"
                                >
                                    <template #content>
                                        <div class="wallet-tip-text">
                                            <span v-if="isIdol">Свободные средства.<br>Доступны для оплаты услуг<br>или вывода на карту.</span>
                                            <span v-else>Свободные средства.<br>Доступны для оплаты услуг<br>и контент-паков.</span>
                                        </div>
                                    </template>
                                    <div class="wcard__info-trigger">
                                        <el-icon><InfoFilled /></el-icon>
                                    </div>
                                </el-tooltip>
                            </div>
                            <div class="wcard__amount-row">
                                <span class="wcard__amount wcard__amount--emerald">
                                    {{ formatMoney(currentWallet.balance) }}
                                </span>
                                <span class="wcard__currency">₽</span>
                            </div>
                            <div class="wcard__footer-note">
                                {{ isIdol ? 'Для заказов и вывода' : 'Для оплаты услуг и паков' }}
                            </div>
                        </div>

                        <!-- Card 2: Frozen / Held -->
                        <div class="wcard wcard--frozen">
                            <div class="wcard__top">
                                <div class="wcard__badge wcard__badge--amber">
                                    <el-icon class="wcard__badge-icon"><Lock /></el-icon>
                                    Заморожено
                                </div>
                                <el-tooltip
                                    placement="top"
                                    effect="dark"
                                    popper-class="wallet-dark-tooltip"
                                >
                                    <template #content>
                                        <div class="wallet-tip-text">
                                            Средства заморожены до сдачи заказа.<br>
                                            Исполнитель получит оплату<br>
                                            только после подтверждения работы.
                                        </div>
                                    </template>
                                    <div class="wcard__info-trigger">
                                        <el-icon><InfoFilled /></el-icon>
                                    </div>
                                </el-tooltip>
                            </div>
                            <div class="wcard__amount-row">
                                <span class="wcard__amount wcard__amount--amber">
                                    {{ formatMoney(currentWallet.held_balance) }}
                                </span>
                                <span class="wcard__currency">₽</span>
                            </div>
                            <div class="wcard__footer-note">
                                Безопасные сделки по заказам
                            </div>
                        </div>
                    </div>

                    <!-- Financial Actions Panel (Deposit / Withdraw) -->
                    <div class="wallet-action-box">
                        <!-- Idol Role Selector (Deposit / Withdraw) -->
                        <div v-if="isIdol" class="wallet-role-tabs">
                            <button
                                type="button"
                                class="wallet-role-tab"
                                :class="{ 'wallet-role-tab--active': actionTab === 'deposit' }"
                                @click="actionTab = 'deposit'"
                            >
                                <el-icon><Plus /></el-icon>
                                <span>Пополнить счёт</span>
                            </button>
                            <button
                                type="button"
                                class="wallet-role-tab"
                                :class="{ 'wallet-role-tab--active': actionTab === 'withdraw' }"
                                @click="actionTab = 'withdraw'"
                            >
                                <el-icon><Upload /></el-icon>
                                <span>Вывести средства</span>
                            </button>
                        </div>

                        <!-- DEPOSIT SECTION -->
                        <div v-if="!isIdol || actionTab === 'deposit'" class="wallet-deposit-section">
                            <div class="wallet-action-header">
                                <span class="wallet-action-title">Пополнение счёта</span>
                            </div>

                            <!-- 3 Presets exactly: 250, 550, 950 -->
                            <div class="wallet-preset-chips">
                                <button
                                    v-for="amt in quickAmounts"
                                    :key="amt"
                                    type="button"
                                    class="wallet-preset-chip"
                                    :class="{ 'wallet-preset-chip--active': depositAmount === amt }"
                                    @click="depositAmount = amt"
                                >
                                    {{ formatMoney(amt) }} ₽
                                </button>
                            </div>

                            <!-- Form row -->
                            <div class="wallet-action-form">
                                <div class="wallet-input-container">
                                    <input
                                        v-model.number="depositAmount"
                                        type="number"
                                        min="10"
                                        step="10"
                                        class="wallet-glass-input"
                                        placeholder="Сумма в рублях"
                                        @keydown.enter.prevent="handleDeposit"
                                    />
                                    <span class="wallet-input-currency">₽</span>
                                </div>
                                <button
                                    type="button"
                                    class="wallet-primary-btn"
                                    :disabled="isDepositing"
                                    @click="handleDeposit"
                                >
                                    <el-icon v-if="isDepositing" class="is-loading"><Loading /></el-icon>
                                    <el-icon v-else><Plus /></el-icon>
                                    <span>{{ isDepositing ? 'Пополнение...' : 'Пополнить баланс' }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- WITHDRAW SECTION (Idol Only) -->
                        <div v-else-if="isIdol && actionTab === 'withdraw'" class="wallet-withdraw-section">
                            <div class="wallet-action-header">
                                <span class="wallet-action-title">Вывод средств со счёта</span>
                                <span class="wallet-action-available">
                                    Доступно: <strong>{{ formatMoney(currentWallet.balance) }} ₽</strong>
                                </span>
                            </div>

                            <div class="wallet-action-form">
                                <div class="wallet-input-container">
                                    <input
                                        v-model.number="withdrawAmount"
                                        type="number"
                                        min="100"
                                        :max="currentWallet.balance"
                                        step="100"
                                        class="wallet-glass-input"
                                        placeholder="Сумма для вывода (от 100 ₽)"
                                        @keydown.enter.prevent="handleWithdraw"
                                    />
                                    <span class="wallet-input-currency">₽</span>
                                </div>
                                <button
                                    type="button"
                                    class="wallet-primary-btn wallet-primary-btn--withdraw"
                                    :disabled="isWithdrawing || !withdrawAmount || withdrawAmount > currentWallet.balance"
                                    @click="handleWithdraw"
                                >
                                    <el-icon v-if="isWithdrawing" class="is-loading"><Loading /></el-icon>
                                    <el-icon v-else><Upload /></el-icon>
                                    <span>{{ isWithdrawing ? 'Обработка...' : 'Вывести средства' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════════════ VIEW 2: TRANSACTIONS HISTORY ════════════ -->
                <div v-else class="wallet-history">
                    <!-- Filter Tabs Bar -->
                    <div class="wallet-history-nav">
                        <div class="wallet-history-tabs">
                            <button
                                v-for="tab in historyTabs"
                                :key="tab.id"
                                type="button"
                                class="wallet-htab"
                                :class="{ 'wallet-htab--active': activeHistoryTab === tab.id }"
                                @click="activeHistoryTab = tab.id"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="!filteredTransactions.length" class="wallet-empty-state">
                        <div class="wallet-empty-icon">
                            <el-icon><Tickets /></el-icon>
                        </div>
                        <h3 class="wallet-empty-title">Транзакции не найдены</h3>
                        <p class="wallet-empty-text">
                            {{ activeHistoryTab === 'all' 
                                ? 'В вашем кошельке пока нет завершённых операций.' 
                                : 'В выбранной категории операций записей не обнаружено.' }}
                        </p>
                        <button
                            type="button"
                            class="wallet-empty-btn"
                            @click="activeView = 'overview'"
                        >
                            <el-icon><Plus /></el-icon>
                            <span>Пополнить баланс</span>
                        </button>
                    </div>

                    <!-- Transaction List -->
                    <div v-else class="wallet-tx-items">
                        <div
                            v-for="tx in filteredTransactions"
                            :key="tx.id"
                            class="wallet-tx-card"
                        >
                            <div class="wallet-tx-card__main">
                                <div
                                    class="wallet-tx-icon"
                                    :class="{
                                        'wallet-tx-icon--emerald': isPositiveTx(tx.type),
                                        'wallet-tx-icon--amber': isHoldTx(tx.type),
                                        'wallet-tx-icon--coral': !isPositiveTx(tx.type) && !isHoldTx(tx.type),
                                    }"
                                >
                                    <!-- Element Plus Direction Icons -->
                                    <el-icon v-if="isPositiveTx(tx.type)"><BottomLeft /></el-icon>
                                    <el-icon v-else-if="isHoldTx(tx.type)"><Lock /></el-icon>
                                    <el-icon v-else><TopRight /></el-icon>
                                </div>
                                <div class="wallet-tx-info">
                                    <div class="wallet-tx-title">{{ tx.type_label }}</div>
                                    <div v-if="tx.description" class="wallet-tx-desc">{{ tx.description }}</div>
                                    <div class="wallet-tx-meta">
                                        <el-icon class="wallet-tx-clock"><Clock /></el-icon>
                                        <span>{{ formatDate(tx.created_at) }}</span>
                                        <span class="wallet-tx-id">#{{ tx.id }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="wallet-tx-card__aside">
                                <div
                                    class="wallet-tx-amount"
                                    :class="{
                                        'wallet-tx-amount--emerald': isPositiveTx(tx.type),
                                        'wallet-tx-amount--amber': isHoldTx(tx.type),
                                        'wallet-tx-amount--coral': !isPositiveTx(tx.type) && !isHoldTx(tx.type),
                                    }"
                                >
                                    {{ isPositiveTx(tx.type) ? '+' : isHoldTx(tx.type) ? '' : '−' }}
                                    {{ formatMoney(tx.amount) }} ₽
                                </div>
                                <div class="wallet-tx-remain">
                                    Остаток: <span>{{ formatMoney(tx.balance_after) }} ₽</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="transactions.links && transactions.links.length > 3" class="wallet-pagination">
                        <Link
                            v-for="(link, idx) in transactions.links"
                            :key="idx"
                            :href="link.url || '#'"
                            class="wallet-page-link"
                            :class="{
                                'wallet-page-link--active': link.active,
                                'wallet-page-link--disabled': !link.url,
                            }"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* ── Viewport & Canvas ─────────────────────────────────────── */
.wallet-viewport {
    position: relative;
    min-height: calc(100vh - 72px);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.wallet-ambient {
    position: absolute;
    pointer-events: none;
    z-index: 0;
    border-radius: 50%;
    filter: blur(80px);
}

.ambient-pink {
    top: -40px;
    left: 8%;
    width: 360px;
    height: 360px;
    background: radial-gradient(circle, rgba(255, 178, 239, 0.11) 0%, transparent 70%);
}

.ambient-blue {
    top: 180px;
    right: 8%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(100, 210, 255, 0.08) 0%, transparent 70%);
}

.wallet-wrapper {
    position: relative;
    z-index: 1;
    max-width: 960px;
    width: 100%;
    margin: 0 auto;
    padding: 1.5rem 1.25rem calc(4.5rem + env(safe-area-inset-bottom));
}

/* ── Topbar & Main Tabs Switcher ──────────────────────────── */
.wallet-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.wallet-brand {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.wallet-brand__icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: linear-gradient(135deg, rgba(255, 178, 239, 0.16) 0%, rgba(100, 210, 255, 0.12) 100%);
    border: 1px solid rgba(255, 178, 239, 0.28);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: var(--color-base-1);
    box-shadow: 0 4px 16px rgba(255, 178, 239, 0.15);
}

.wallet-brand__title {
    font-size: 1.45rem;
    font-weight: 800;
    color: rgba(240, 240, 255, 0.98);
    margin: 0;
    line-height: 1.2;
}

.wallet-brand__sub {
    font-size: 0.82rem;
    color: rgba(220, 220, 255, 0.5);
    margin: 0;
}

.wallet-views {
    display: flex;
    background: rgba(255, 178, 239, 0.05);
    border: 1px solid rgba(255, 178, 239, 0.14);
    border-radius: 8px;
    padding: 3px;
    gap: 3px;
}

.wallet-view-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.95rem;
    font-size: 0.84rem;
    font-weight: 600;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: rgba(255, 178, 239, 0.65);
    cursor: pointer;
    transition: all 0.15s ease;
}

.wallet-view-btn:hover {
    color: #ffffff;
}

.wallet-view-btn--active {
    background: rgba(255, 178, 239, 0.18);
    color: var(--color-base-1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
}

.wallet-view-badge {
    font-family: var(--font-receipt);
    font-size: 0.72rem;
    padding: 0.1rem 0.45rem;
    border-radius: 9999px;
    background: rgba(255, 178, 239, 0.15);
    color: var(--color-base-1);
}

/* ── 2 Compact Balance Cards ──────────────────────────────── */
.wallet-cards-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}

.wcard {
    position: relative;
    background: rgba(14, 10, 24, 0.7);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 178, 239, 0.12);
    border-radius: 12px;
    padding: 1.25rem 1.4rem;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.08);
    overflow: hidden;
    transition: transform 0.15s ease;
}

.wcard:hover {
    transform: translateY(-1px);
}

.wcard::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    pointer-events: none;
}

.wcard--available::before {
    background: linear-gradient(90deg, transparent 0%, rgba(76, 222, 143, 0.6) 50%, transparent 100%);
}

.wcard--frozen::before {
    background: linear-gradient(90deg, transparent 0%, rgba(251, 191, 36, 0.6) 50%, transparent 100%);
}

.wcard__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.85rem;
}

.wcard__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.2rem 0.55rem;
    border-radius: 9999px;
}

.wcard__badge--emerald {
    background: rgba(76, 222, 143, 0.12);
    border: 1px solid rgba(76, 222, 143, 0.25);
    color: #4cde8f;
}

.wcard__badge--amber {
    background: rgba(251, 191, 36, 0.12);
    border: 1px solid rgba(251, 191, 36, 0.25);
    color: #fbbf24;
}

.wcard__badge-icon {
    font-size: 0.75rem;
}

.wcard__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.wcard__dot--emerald {
    background: #4cde8f;
    box-shadow: 0 0 8px #4cde8f;
}

.wcard__info-trigger {
    color: rgba(220, 220, 255, 0.4);
    font-size: 0.95rem;
    cursor: pointer;
    transition: color 0.15s ease;
    display: flex;
    align-items: center;
}

.wcard__info-trigger:hover {
    color: rgba(255, 255, 255, 0.9);
}

.wcard__amount-row {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
    margin-bottom: 0.35rem;
}

.wcard__amount {
    font-family: var(--font-receipt);
    font-size: 1.7rem;
    font-weight: 800;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

.wcard__amount--emerald {
    color: #4cde8f;
    text-shadow: 0 0 20px rgba(76, 222, 143, 0.2);
}

.wcard__amount--amber {
    color: #fbbf24;
    text-shadow: 0 0 20px rgba(251, 191, 36, 0.2);
}

.wcard__currency {
    font-family: var(--font-receipt);
    font-size: 1.15rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.4);
}

.wcard__footer-note {
    font-size: 0.74rem;
    color: rgba(220, 220, 255, 0.45);
}

/* ── Financial Actions Panel ──────────────────────────────── */
.wallet-action-box {
    position: relative;
    background: rgba(14, 10, 24, 0.7);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 178, 239, 0.14);
    border-radius: 12px;
    padding: 1.35rem 1.5rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.08);
    overflow: hidden;
}

.wallet-action-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(100, 210, 255, 0.4) 50%, transparent 100%);
    pointer-events: none;
}

/* Idol subtabs */
.wallet-role-tabs {
    display: flex;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    padding: 3px;
    gap: 3px;
    margin-bottom: 1.15rem;
    width: fit-content;
}

.wallet-role-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.85rem;
    font-size: 0.82rem;
    font-weight: 600;
    border-radius: 4px;
    border: none;
    background: transparent;
    color: rgba(220, 220, 255, 0.6);
    cursor: pointer;
    transition: all 0.15s ease;
}

.wallet-role-tab--active {
    background: rgba(255, 178, 239, 0.15);
    color: var(--color-base-1);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.25);
}

.wallet-action-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.95rem;
}

.wallet-action-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: rgba(240, 240, 255, 0.95);
}

.wallet-action-available {
    font-size: 0.82rem;
    color: rgba(220, 220, 255, 0.5);
}

.wallet-action-available strong {
    font-family: var(--font-receipt);
    color: #4cde8f;
}

/* 3 Preset Chips (250, 550, 950) */
.wallet-preset-chips {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.wallet-preset-chip {
    font-family: var(--font-receipt);
    height: 42px;
    font-size: 0.9rem;
    font-weight: 700;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.09);
    color: rgba(230, 230, 255, 0.85);
    cursor: pointer;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wallet-preset-chip:hover {
    background: rgba(255, 178, 239, 0.08);
    border-color: rgba(255, 178, 239, 0.3);
    color: #ffffff;
}

.wallet-preset-chip--active {
    background: rgba(255, 178, 239, 0.16);
    border-color: var(--color-base-1);
    color: var(--color-base-1);
    box-shadow: 0 0 16px rgba(255, 178, 239, 0.2);
}

/* Form row */
.wallet-action-form {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.wallet-input-container {
    position: relative;
    flex: 1;
}

.wallet-glass-input {
    width: 100%;
    height: 44px;
    padding: 0 2rem 0 1rem;
    font-family: var(--font-receipt);
    font-size: 0.98rem;
    font-weight: 600;
    background: rgba(10, 7, 20, 0.7);
    border: 1px solid rgba(255, 178, 239, 0.2);
    border-radius: 8px;
    color: #ffffff;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.wallet-glass-input:focus {
    border-color: var(--color-base-1);
    box-shadow: 0 0 0 3px rgba(255, 178, 239, 0.15);
}

.wallet-input-currency {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    font-family: var(--font-receipt);
    font-size: 0.95rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.4);
    pointer-events: none;
}

.wallet-primary-btn {
    height: 44px;
    padding: 0 1.5rem;
    font-size: 0.86rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    border-radius: 8px;
    border: 1px solid rgba(255, 178, 239, 0.4);
    background: linear-gradient(135deg, rgba(255, 178, 239, 0.24) 0%, rgba(100, 210, 255, 0.16) 100%);
    color: var(--color-base-1);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    transition: all 0.2s ease;
}

.wallet-primary-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, rgba(255, 178, 239, 0.35) 0%, rgba(100, 210, 255, 0.24) 100%);
    border-color: var(--color-base-1);
    color: #ffffff;
    box-shadow: 0 0 20px rgba(255, 178, 239, 0.3);
    transform: translateY(-1px);
}

.wallet-primary-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    transform: none;
}

.wallet-primary-btn--withdraw {
    border-color: rgba(100, 210, 255, 0.4);
    color: var(--color-base-2);
}

.wallet-primary-btn--withdraw:hover:not(:disabled) {
    border-color: var(--color-base-2);
    box-shadow: 0 0 20px rgba(100, 210, 255, 0.3);
}

/* ── VIEW 2: History Ledger ───────────────────────────────── */
.wallet-history {
    position: relative;
    background: rgba(14, 10, 24, 0.7);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 178, 239, 0.14);
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.wallet-history-nav {
    display: flex;
    justify-content: flex-start;
    margin-bottom: 1.15rem;
    overflow-x: auto;
}

.wallet-history-tabs {
    display: flex;
    background: rgba(255, 178, 239, 0.04);
    border: 1px solid rgba(255, 178, 239, 0.12);
    border-radius: 6px;
    padding: 3px;
    gap: 2px;
}

.wallet-htab {
    background: transparent;
    border: none;
    color: rgba(255, 178, 239, 0.6);
    font-size: 0.82rem;
    font-weight: 600;
    padding: 0.35rem 0.75rem;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.wallet-htab:hover {
    color: #ffffff;
}

.wallet-htab--active {
    background: rgba(255, 178, 239, 0.16);
    color: var(--color-base-1);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
}

/* Empty State */
.wallet-empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.wallet-empty-icon {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    margin: 0 auto 1rem;
    background: rgba(255, 178, 239, 0.06);
    border: 1px solid rgba(255, 178, 239, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: rgba(255, 178, 239, 0.5);
}

.wallet-empty-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: rgba(240, 240, 255, 0.9);
    margin: 0 0 0.35rem 0;
}

.wallet-empty-text {
    font-size: 0.84rem;
    color: rgba(220, 220, 255, 0.45);
    max-width: 380px;
    margin: 0 auto 1.25rem;
    line-height: 1.4;
}

.wallet-empty-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 1.1rem;
    font-size: 0.82rem;
    font-weight: 700;
    border-radius: 6px;
    background: rgba(255, 178, 239, 0.12);
    border: 1px solid rgba(255, 178, 239, 0.3);
    color: var(--color-base-1);
    cursor: pointer;
    transition: all 0.15s ease;
}

.wallet-empty-btn:hover {
    background: rgba(255, 178, 239, 0.2);
    color: #ffffff;
}

/* Transactions Items */
.wallet-tx-items {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.wallet-tx-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.85rem 1rem;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: background 0.15s ease, border-color 0.15s ease;
}

.wallet-tx-card:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 178, 239, 0.16);
}

.wallet-tx-card__main {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-width: 0;
}

.wallet-tx-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    flex-shrink: 0;
}

.wallet-tx-icon--emerald {
    background: rgba(76, 222, 143, 0.12);
    border: 1px solid rgba(76, 222, 143, 0.25);
    color: #4cde8f;
}

.wallet-tx-icon--amber {
    background: rgba(251, 191, 36, 0.12);
    border: 1px solid rgba(251, 191, 36, 0.25);
    color: #fbbf24;
}

.wallet-tx-icon--coral {
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.25);
    color: #f87171;
}

.wallet-tx-info {
    min-width: 0;
}

.wallet-tx-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: rgba(240, 240, 255, 0.95);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.wallet-tx-desc {
    font-size: 0.78rem;
    color: rgba(220, 220, 255, 0.5);
    margin-top: 0.1rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.wallet-tx-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.2rem;
    font-size: 0.72rem;
    color: rgba(220, 220, 255, 0.4);
}

.wallet-tx-clock {
    font-size: 0.75rem;
}

.wallet-tx-id {
    font-family: var(--font-receipt);
    opacity: 0.65;
}

.wallet-tx-card__aside {
    text-align: right;
    flex-shrink: 0;
    margin-left: 0.85rem;
}

.wallet-tx-amount {
    font-family: var(--font-receipt);
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
}

.wallet-tx-amount--emerald {
    color: #4cde8f;
}

.wallet-tx-amount--amber {
    color: #fbbf24;
}

.wallet-tx-amount--coral {
    color: rgba(240, 240, 255, 0.85);
}

.wallet-tx-remain {
    font-family: var(--font-receipt);
    font-size: 0.72rem;
    color: rgba(220, 220, 255, 0.4);
    margin-top: 0.2rem;
}

.wallet-tx-remain span {
    color: rgba(220, 220, 255, 0.65);
}

/* Pagination */
.wallet-pagination {
    display: flex;
    justify-content: center;
    gap: 0.35rem;
    margin-top: 1.25rem;
    flex-wrap: wrap;
}

.wallet-page-link {
    font-family: var(--font-receipt);
    padding: 0.3rem 0.65rem;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 178, 239, 0.12);
    color: rgba(220, 220, 255, 0.75);
    text-decoration: none;
    transition: all 0.15s ease;
}

.wallet-page-link:hover:not(.wallet-page-link--disabled) {
    background: rgba(255, 178, 239, 0.1);
    color: #ffffff;
}

.wallet-page-link--active {
    background: rgba(255, 178, 239, 0.2);
    border-color: var(--color-base-1);
    color: var(--color-base-1);
}

.wallet-page-link--disabled {
    opacity: 0.35;
    pointer-events: none;
}

/* ── Responsiveness ───────────────────────────────────────── */
@media (max-width: 768px) {
    .wallet-cards-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .wallet-preset-chips {
        grid-template-columns: repeat(3, 1fr);
    }

    .wallet-action-form {
        flex-direction: column;
    }

    .wallet-primary-btn {
        width: 100%;
        justify-content: center;
    }

    .wallet-topbar {
        flex-direction: column;
        align-items: flex-start;
    }

    .wallet-views {
        width: 100%;
    }

    .wallet-view-btn {
        flex: 1;
        justify-content: center;
    }
}
</style>
