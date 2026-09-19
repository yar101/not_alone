<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import AppSelect from '@/Components/AppSelect.vue';
import axios from 'axios';
import { ElNotification, ElTooltip, ElIcon } from 'element-plus';
import {
    Wallet,
    WalletFilled,
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
    CopyDocument,
    Check,
    Close,
    QuestionFilled,
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
    activeFilter: {
        type: String,
        default: 'all',
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

watch(
    () => props.wallet,
    (newWallet) => {
        if (newWallet) {
            currentWallet.value = { ...newWallet };
        }
    },
    { deep: true }
);

// ── Navigation View ──────────────────────────────────────────
const getInitialView = () => {
    if (typeof window === 'undefined') return 'overview';
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab') || params.get('view');
    if (tabParam === 'history') return 'history';
    return 'overview';
};

const activeView = ref(getInitialView()); // 'overview' | 'history'
const actionTab = ref('deposit'); // 'deposit' | 'withdraw'

function setView(view) {
    activeView.value = view;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        if (view === 'history') {
            url.searchParams.set('tab', 'history');
        } else {
            url.searchParams.delete('tab');
            url.searchParams.delete('view');
            url.searchParams.delete('page');
            url.searchParams.delete('filter');
        }
        const state = (typeof history !== 'undefined' && history.state) || {};
        if (state.page) {
            state.page.url = url.pathname + url.search;
        }
        window.history.replaceState(state, '', url.pathname + url.search);
    }
}

function syncViewFromUrl() {
    activeView.value = getInitialView();
}

onMounted(() => {
    window.addEventListener('popstate', syncViewFromUrl);
});

onUnmounted(() => {
    window.removeEventListener('popstate', syncViewFromUrl);
    if (copyTimer) clearTimeout(copyTimer);
});

// ── Financial Actions ────────────────────────────────────────
const depositAmount = ref(250);
const withdrawAmount = ref('');
const isDepositing = ref(false);
const isWithdrawing = ref(false);

const quickAmounts = [250, 550, 950];

// ── History Filter ───────────────────────────────────────────
const activeHistoryTab = ref(props.activeFilter || 'all');

watch(
    () => props.activeFilter,
    (newFilter) => {
        if (newFilter) {
            activeHistoryTab.value = newFilter;
        }
    }
);

function setHistoryFilter(tabId) {
    if (activeHistoryTab.value === tabId && activeView.value === 'history') return;
    activeHistoryTab.value = tabId;
    activeView.value = 'history';

    router.get(
        route('wallet.show'),
        { tab: 'history', filter: tabId },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

const historyTabs = computed(() => {
    const tabs = [
        { id: 'all', label: 'Все' },
        { id: 'deposit', label: 'Пополнения' },
        { id: 'orders', label: 'Заказы' },
        { id: 'packs', label: 'Контент' },
    ];
    if (props.isIdol) {
        tabs.push({ id: 'withdrawal', label: 'Вывод' });
    }
    return tabs;
});

const historyTabOptions = computed(() => {
    return historyTabs.value.map(tab => ({
        value: tab.id,
        label: tab.label,
    }));
});

const filteredTransactions = computed(() => {
    return props.transactions?.data || [];
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

function formatInteger(val) {
    return Number(val || 0).toLocaleString('ru-RU', {
        maximumFractionDigits: 0,
    });
}

const SHORT_MONTHS = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    const isToday = d.toDateString() === now.toDateString();
    
    const timeStr = d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    if (isToday) {
        return 'Сегодня ' + timeStr;
    }
    
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) {
        return 'Вчера ' + timeStr;
    }

    const day = d.getDate();
    const month = SHORT_MONTHS[d.getMonth()];
    if (d.getFullYear() !== now.getFullYear()) {
        return `${day} ${month} ${d.getFullYear()} ${timeStr}`;
    }
    return `${day} ${month} ${timeStr}`;
}

function isPositiveTx(txOrType) {
    const amount = typeof txOrType === 'object' ? Number(txOrType?.amount || 0) : null;
    if (amount !== null) {
        return amount > 0;
    }
    const type = typeof txOrType === 'object' ? txOrType?.type : txOrType;
    return ['deposit', 'order_payout', 'order_refund', 'pack_sale'].includes(type);
}

function isHoldTx(txOrType) {
    const type = typeof txOrType === 'object' ? txOrType?.type : txOrType;
    return type === 'order_hold';
}

// ── Transaction Helpers ───────────────────────────────────────
const copiedId = ref(null);
let copyTimer = null;

async function copyTxId(id) {
    try {
        await navigator.clipboard.writeText(String(id));
        copiedId.value = id;
        if (copyTimer) clearTimeout(copyTimer);
        copyTimer = setTimeout(() => {
            copiedId.value = null;
        }, 2000);
    } catch (e) {
        // Fallback if clipboard API not permitted
    }
}

function getTxLabel(tx) {
    if (tx.type === 'order_hold') return 'Заморозка';
    if (tx.type === 'deposit') return 'Пополнение';
    if (tx.type === 'order_payout') return 'Выплата';
    if (tx.type === 'platform_fee') return 'Комиссия';
    return tx.type_label || 'Операция';
}

function formatTxDescription(desc) {
    if (!desc) return '';
    return desc
        .replace(/\s*\(\d+%\)/g, '')
        .replace(/^Комиссия сервиса/g, 'Комиссия');
}

function formatSignedAmount(tx) {
    if (!tx) return '0,00 ₽';
    const num = Number(tx.amount || 0);
    const abs = formatMoney(Math.abs(num));
    if (num > 0) return `+${abs} ₽`;
    if (num < 0) return `−${abs} ₽`;
    return `${abs} ₽`;
}

function getStatusBadge(tx) {
    const s = tx.status;
    if (s === 'completed') return { class: 'wallet-status--completed', label: tx.status_label || 'Завершено' };
    if (s === 'pending') return { class: 'wallet-status--pending', label: tx.status_label || 'В обработке' };
    if (s === 'failed') return { class: 'wallet-status--failed', label: tx.status_label || 'Ошибка' };
    if (s === 'cancelled') return { class: 'wallet-status--cancelled', label: tx.status_label || 'Отменено' };
    return { class: 'wallet-status--completed', label: tx.status_label || 'Завершено' };
}

// ── Transaction Modal ─────────────────────────────────────────
const selectedTx = ref(null);
const showTxModal = ref(false);

function openTxDetails(tx) {
    selectedTx.value = tx;
    showTxModal.value = true;
}

function formatFullDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const day = d.getDate();
    const month = SHORT_MONTHS[d.getMonth()];
    const year = d.getFullYear();
    const time = d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    return `${day} ${month} ${year} ${time}`;
}
</script>

<template>
    <Head title="Кошелёк" />
    <AppLayout>
        <div class="wallet-viewport">
            <!-- Background Ambient Lighting -->
            <div class="wallet-ambient ambient-blue" />

            <div class="wallet-wrapper">
                <!-- Top Navigation & View Switcher -->
                <div class="wallet-topbar">
                    <div class="wallet-brand">
                        <h1 class="wallet-brand__title">
                            <el-icon class="wallet-brand__title-icon"><Wallet /></el-icon>
                            <span>Кошелёк</span>
                        </h1>
                    </div>

                    <!-- 2 Main Tabs: Balance Overview vs History -->
                    <div class="wallet-views">
                        <button
                            type="button"
                            class="wallet-view-btn"
                            :class="{ 'wallet-view-btn--active': activeView === 'overview' }"
                            @click="setView('overview')"
                        >
                            <el-icon><Wallet /></el-icon>
                            <span>Мой баланс</span>
                        </button>

                        <button
                            type="button"
                            class="wallet-view-btn"
                            :class="{ 'wallet-view-btn--active': activeView === 'history' }"
                            @click="setView('history')"
                        >
                            <el-icon><Tickets /></el-icon>
                            <span>История</span>
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
                        <el-tooltip
                            placement="top"
                            effect="dark"
                            popper-class="wallet-dark-tooltip"
                            trigger="click"
                            :hide-after="0"
                        >
                            <template #content>
                                <div class="wallet-tip-text">
                                    <span v-if="isIdol">Свободные средства.<br>Доступны для оплаты услуг<br>или вывода на карту.</span>
                                    <span v-else>Свободные средства.<br>Доступны для оплаты услуг<br>и контент-паков.</span>
                                </div>
                            </template>
                            <div class="wcard wcard--available" tabindex="0" role="button" aria-label="Карточка: Доступный баланс">
                                <div class="wcard__watermark wcard__watermark--emerald">
                                    <i class="fa-solid fa-wallet"></i>
                                </div>
                                <div class="wcard__top">
                                    <div class="wcard__badge wcard__badge--emerald">
                                        <el-icon class="wcard__badge-icon"><WalletFilled /></el-icon>
                                        Доступно
                                    </div>
                                    <div class="wcard__info-trigger" aria-hidden="true">
                                        <el-icon><InfoFilled /></el-icon>
                                    </div>
                                </div>
                                <div class="wcard__amount-row">
                                    <span class="wcard__amount wcard__amount--emerald">
                                        {{ formatMoney(currentWallet.balance) }}
                                    </span>
                                    <span class="wcard__currency">₽</span>
                                </div>
                            </div>
                        </el-tooltip>

                        <!-- Card 2: Frozen / Held -->
                        <el-tooltip
                            placement="top"
                            effect="dark"
                            popper-class="wallet-dark-tooltip"
                            trigger="click"
                            :hide-after="0"
                        >
                            <template #content>
                                <div class="wallet-tip-text">
                                    Средства заморожены до сдачи заказа.<br>
                                    Исполнитель получит оплату<br>
                                    только после подтверждения работы.
                                </div>
                            </template>
                            <div class="wcard wcard--frozen" tabindex="0" role="button" aria-label="Карточка: Замороженный баланс">
                                <div class="wcard__watermark wcard__watermark--cyan">
                                    <i class="fa-solid fa-snowflake"></i>
                                </div>
                                <div class="wcard__top">
                                    <div class="wcard__badge wcard__badge--cyan">
                                        <i class="fa-solid fa-snowflake wcard__badge-fa"></i>
                                        Заморожено
                                    </div>
                                    <div class="wcard__info-trigger" aria-hidden="true">
                                        <el-icon><InfoFilled /></el-icon>
                                    </div>
                                </div>
                                <div class="wcard__amount-row">
                                    <span class="wcard__amount wcard__amount--cyan">
                                        {{ formatMoney(currentWallet.held_balance) }}
                                    </span>
                                    <span class="wcard__currency">₽</span>
                                </div>
                            </div>
                        </el-tooltip>
                    </div>

                    <!-- Financial Actions Panel (Deposit / Withdraw) -->
                    <div class="wallet-action-box">
                        <!-- Idol Role Selector (Deposit / Withdraw) -->
                        <div v-if="isIdol" class="wallet-role-tabs">
                            <button
                                type="button"
                                class="wallet-role-tab"
                                :class="{ 'wallet-role-tab--active-deposit': actionTab === 'deposit' }"
                                @click="actionTab = 'deposit'"
                            >
                                <span>Пополнить</span>
                            </button>
                            <button
                                type="button"
                                class="wallet-role-tab"
                                :class="{ 'wallet-role-tab--active-withdraw': actionTab === 'withdraw' }"
                                @click="actionTab = 'withdraw'"
                            >
                                <span>Вывести</span>
                            </button>
                        </div>

                        <!-- DEPOSIT SECTION -->
                        <div v-if="!isIdol || actionTab === 'deposit'" class="wallet-deposit-section">
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
                                    {{ formatInteger(amt) }} ₽
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
                                        placeholder="Сумма от 10 ₽"
                                        @keydown.enter.prevent="handleDeposit"
                                    />
                                    <span class="wallet-input-currency">₽</span>
                                </div>
                                <button
                                    type="button"
                                    class="wallet-primary-btn wallet-primary-btn--deposit"
                                    :disabled="isDepositing"
                                    @click="handleDeposit"
                                >
                                    <el-icon v-if="isDepositing" class="is-loading"><Loading /></el-icon>
                                    <span>{{ isDepositing ? 'Пополнение...' : 'Пополнить' }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- WITHDRAW SECTION (Idol Only) -->
                        <div v-else-if="isIdol && actionTab === 'withdraw'" class="wallet-withdraw-section">
                            <div class="wallet-action-form">
                                <div class="wallet-input-container">
                                    <input
                                        v-model.number="withdrawAmount"
                                        type="number"
                                        min="100"
                                        :max="currentWallet.balance"
                                        step="100"
                                        class="wallet-glass-input"
                                        placeholder="Сумма от 100 ₽"
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
                                    <span>{{ isWithdrawing ? 'Обработка...' : 'Вывести' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════════════ VIEW 2: TRANSACTIONS HISTORY ════════════ -->
                <div v-else class="wallet-history">
                    <!-- Filter Tabs Bar -->
                    <div class="wallet-history-nav">
                        <!-- Desktop Tabs -->
                        <div class="wallet-history-tabs wallet-history-tabs--desktop">
                            <button
                                v-for="tab in historyTabs"
                                :key="tab.id"
                                type="button"
                                class="wallet-htab"
                                :class="{ 'wallet-htab--active': activeHistoryTab === tab.id }"
                                @click="setHistoryFilter(tab.id)"
                            >
                                {{ tab.label }}
                            </button>
                        </div>

                        <!-- Mobile Dropdown Select -->
                        <div class="wallet-history-select-wrap wallet-history-select--mobile">
                            <AppSelect
                                :model-value="activeHistoryTab"
                                :options="historyTabOptions"
                                class="wallet-app-select"
                                @update:model-value="setHistoryFilter"
                            />
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
                            @click="setView('overview')"
                        >
                            <el-icon><Plus /></el-icon>
                            <span>Пополнить</span>
                        </button>
                    </div>

                    <!-- Transaction Table / Ledger -->
                    <div v-else class="wallet-ledger">
                        <!-- Desktop Table Header -->
                        <div class="wallet-ledger__head">
                            <div class="wallet-col wallet-col--id">ID</div>
                            <div class="wallet-col wallet-col--type">Операция</div>
                            <div class="wallet-col wallet-col--date">Дата и время</div>
                            <div class="wallet-col wallet-col--amount">Сумма / Баланс</div>
                        </div>

                        <!-- Table Rows -->
                        <div class="wallet-ledger__body">
                            <div
                                v-for="tx in filteredTransactions"
                                :key="tx.id"
                                class="wallet-tx-row"
                                @click="openTxDetails(tx)"
                            >
                                <!-- Col: ID -->
                                <div class="wallet-col wallet-col--id">
                                    <el-tooltip content="Скопировать ID" placement="top" effect="dark" :show-after="300">
                                        <button
                                            type="button"
                                            class="wallet-tx-id-btn"
                                            @click.stop="copyTxId(tx.id)"
                                        >
                                            <span class="wallet-tx-id-hash">#</span>{{ tx.id }}
                                            <el-icon class="wallet-tx-id-icon">
                                                <Check v-if="copiedId === tx.id" />
                                                <CopyDocument v-else />
                                            </el-icon>
                                        </button>
                                    </el-tooltip>
                                </div>

                                <!-- Col: Operation & Icon -->
                                <div class="wallet-col wallet-col--type">
                                    <div
                                        class="wallet-tx-icon"
                                        :class="{
                                            'wallet-tx-icon--emerald': isPositiveTx(tx.type),
                                            'wallet-tx-icon--cyan': isHoldTx(tx.type),
                                            'wallet-tx-icon--coral': !isPositiveTx(tx.type) && !isHoldTx(tx.type),
                                        }"
                                    >
                                        <el-icon v-if="isPositiveTx(tx.type)"><BottomLeft /></el-icon>
                                        <i v-else-if="isHoldTx(tx.type)" class="fa-solid fa-snowflake"></i>
                                        <el-icon v-else><TopRight /></el-icon>
                                    </div>
                                    <div class="wallet-tx-title-group">
                                        <div class="wallet-tx-title-row">
                                            <span class="wallet-tx-title">{{ getTxLabel(tx) }}</span>
                                            <span
                                                v-if="tx.status !== 'completed'"
                                                class="wallet-tx-status-pill wallet-tx-status-pill--inline"
                                                :class="getStatusBadge(tx).class"
                                            >
                                                <span class="wallet-tx-status-dot"></span>
                                                {{ getStatusBadge(tx).label }}
                                            </span>
                                        </div>
                                        <span
                                            v-if="tx.description && tx.description !== getTxLabel(tx)"
                                            class="wallet-tx-subdesc"
                                            :title="formatTxDescription(tx.description)"
                                        >
                                            {{ formatTxDescription(tx.description) }}
                                        </span>
                                        <!-- Mobile-only compact subtitle with date & non-completed status -->
                                        <div class="wallet-tx-mobile-meta">
                                            <span class="wallet-tx-mobile-date">{{ formatDate(tx.created_at) }}</span>
                                            <template v-if="tx.status !== 'completed'">
                                                <span class="wallet-tx-dot">•</span>
                                                <span class="wallet-tx-status-pill" :class="getStatusBadge(tx).class">
                                                    <span class="wallet-tx-status-dot"></span>
                                                    {{ getStatusBadge(tx).label }}
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Col: Date -->
                                <div class="wallet-col wallet-col--date">
                                    <div class="wallet-tx-date">
                                        <el-icon class="wallet-tx-clock"><Clock /></el-icon>
                                        <span>{{ formatDate(tx.created_at) }}</span>
                                    </div>
                                </div>

                                <!-- Col: Amount & Balance -->
                                <div class="wallet-col wallet-col--amount">
                                    <div
                                        class="wallet-tx-amount"
                                        :class="{
                                            'wallet-tx-amount--emerald': isPositiveTx(tx.type),
                                            'wallet-tx-amount--cyan': isHoldTx(tx.type),
                                            'wallet-tx-amount--coral': !isPositiveTx(tx.type) && !isHoldTx(tx.type),
                                        }"
                                    >
                                        {{ formatSignedAmount(tx) }}
                                    </div>
                                    <div class="wallet-tx-remain">
                                        Баланс после: <span>{{ formatMoney(tx.balance_after) }} ₽</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="transactions.links && transactions.links.length > 3" class="wallet-pagination">
                        <template v-for="(link, idx) in transactions.links" :key="idx">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                class="wallet-page-link"
                                :class="{
                                    'wallet-page-link--active': link.active,
                                }"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="wallet-page-link wallet-page-link--disabled"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction Details Modal (Receipt) -->
        <SiteModal
            :show="showTxModal"
            variant="pink"
            compact
            max-width="440px"
            @close="showTxModal = false"
        >
            <div v-if="selectedTx" class="wallet-tx-modal">
                <div class="wallet-tx-modal__header">
                    <div
                        class="wallet-tx-modal__icon"
                        :class="{
                            'wallet-tx-icon--emerald': isPositiveTx(selectedTx.type),
                            'wallet-tx-icon--cyan': isHoldTx(selectedTx.type),
                            'wallet-tx-icon--coral': !isPositiveTx(selectedTx.type) && !isHoldTx(selectedTx.type),
                        }"
                    >
                        <el-icon v-if="isPositiveTx(selectedTx.type)"><BottomLeft /></el-icon>
                        <i v-else-if="isHoldTx(selectedTx.type)" class="fa-solid fa-snowflake"></i>
                        <el-icon v-else><TopRight /></el-icon>
                    </div>
                    <h3 class="wallet-tx-modal__title">{{ getTxLabel(selectedTx) }}</h3>
                    <div
                        class="wallet-tx-modal__amount"
                        :class="{
                            'wallet-tx-amount--emerald': isPositiveTx(selectedTx.type),
                            'wallet-tx-amount--cyan': isHoldTx(selectedTx.type),
                            'wallet-tx-amount--coral': !isPositiveTx(selectedTx.type) && !isHoldTx(selectedTx.type),
                        }"
                    >
                        {{ formatSignedAmount(selectedTx) }}
                    </div>
                    <span class="wallet-tx-status-pill" :class="getStatusBadge(selectedTx).class">
                        <span v-if="selectedTx.status !== 'completed'" class="wallet-tx-status-dot"></span>
                        {{ getStatusBadge(selectedTx).label }}
                    </span>
                </div>

                <div class="wallet-tx-modal__divider"></div>

                <div class="wallet-tx-modal__details">
                    <div class="wallet-tx-detail-row">
                        <span class="wallet-tx-detail-label">ID операции</span>
                        <button
                            type="button"
                            class="wallet-tx-id-btn"
                            @click="copyTxId(selectedTx.id)"
                        >
                            <span class="wallet-tx-id-hash">#</span>{{ selectedTx.id }}
                            <el-icon class="wallet-tx-id-icon">
                                <Check v-if="copiedId === selectedTx.id" />
                                <CopyDocument v-else />
                            </el-icon>
                        </button>
                    </div>
                    <div class="wallet-tx-detail-row">
                        <span class="wallet-tx-detail-label">Дата</span>
                        <span class="wallet-tx-detail-val">{{ formatFullDate(selectedTx.created_at) }}</span>
                    </div>
                    <div class="wallet-tx-detail-row">
                        <el-tooltip
                            placement="top"
                            effect="dark"
                            popper-class="wallet-dark-tooltip"
                            trigger="click"
                            :hide-after="0"
                        >
                            <template #content>
                                <div class="wallet-tip-text">
                                    Баланс после операции
                                </div>
                            </template>
                            <span class="wallet-tx-detail-label wallet-tx-detail-label--tip">
                                Остаток
                                <el-icon class="wallet-tx-detail-tip-icon"><QuestionFilled /></el-icon>
                            </span>
                        </el-tooltip>
                        <span class="wallet-tx-detail-val wallet-tx-detail-val--accent">
                            {{ formatMoney(selectedTx.balance_after) }} ₽
                        </span>
                    </div>
                    <div
                        v-if="selectedTx.description && selectedTx.description !== getTxLabel(selectedTx)"
                        class="wallet-tx-detail-row wallet-tx-detail-row--desc"
                    >
                        <span class="wallet-tx-detail-label">Назначение</span>
                        <span class="wallet-tx-detail-val">{{ formatTxDescription(selectedTx.description) }}</span>
                    </div>
                </div>

                <div class="wallet-tx-modal__actions">
                    <button
                        type="button"
                        class="wallet-tx-modal__btn"
                        @click="showTxModal = false"
                    >
                        Закрыть
                    </button>
                </div>
            </div>
        </SiteModal>
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
    display: none;
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
}

.wallet-brand__title {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 1.55rem;
    font-weight: 800;
    color: rgba(245, 245, 255, 0.98);
    margin: 0;
    line-height: 1.2;
    letter-spacing: -0.015em;
}

.wallet-brand__title-icon {
    font-size: 1.5rem;
    color: var(--color-base-1, #ffb2ef);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    filter: drop-shadow(0 2px 6px rgba(255, 178, 239, 0.3));
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
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 14px;
    padding: 1.3rem 1.45rem;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.08);
    overflow: hidden;
    cursor: pointer;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.wcard--available {
    background: radial-gradient(circle at 95% 90%, rgba(76, 222, 143, 0.09) 0%, transparent 60%),
                linear-gradient(135deg, rgba(16, 12, 28, 0.78) 0%, rgba(10, 7, 18, 0.88) 100%);
    border: 1px solid rgba(76, 222, 143, 0.22);
}

.wcard--frozen {
    background: radial-gradient(circle at 95% 90%, rgba(100, 210, 255, 0.1) 0%, transparent 60%),
                linear-gradient(135deg, rgba(16, 12, 28, 0.78) 0%, rgba(10, 7, 18, 0.88) 100%);
    border: 1px solid rgba(100, 210, 255, 0.22);
}

@media (hover: hover) and (pointer: fine) {
    .wcard--available:hover {
        border-color: rgba(76, 222, 143, 0.4);
        box-shadow: 0 10px 36px rgba(0, 0, 0, 0.5), 0 0 24px rgba(76, 222, 143, 0.12);
    }

    .wcard--frozen:hover {
        border-color: rgba(100, 210, 255, 0.4);
        box-shadow: 0 10px 36px rgba(0, 0, 0, 0.5), 0 0 24px rgba(100, 210, 255, 0.12);
    }
}

.wcard:hover,
.wcard:active {
    transform: none !important;
}

/* Watermark silhouettes */
.wcard__watermark {
    position: absolute;
    right: -10px;
    bottom: -15px;
    font-size: 6.5rem;
    line-height: 1;
    pointer-events: none;
    transform: rotate(-12deg);
    z-index: 0;
}

.wcard__watermark--emerald {
    color: rgba(76, 222, 143, 0.07);
}

.wcard__watermark--cyan {
    color: rgba(100, 210, 255, 0.08);
}

.wcard__top {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.95rem;
}

.wcard__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0;
    background: transparent;
    border: none;
}

.wcard__badge--emerald {
    color: #4cde8f;
}

.wcard__badge--cyan {
    color: var(--color-base-2, #64d2ff);
}

.wcard__badge-icon {
    font-size: 0.95rem;
}

.wcard__badge-fa {
    font-size: 0.9rem;
}

.wcard__info-trigger {
    background: transparent;
    border: none;
    padding: 0;
    outline: none;
    color: rgba(220, 220, 255, 0.4);
    font-size: 1.18rem;
    cursor: pointer;
    transition: color 0.15s ease, background 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    -webkit-tap-highlight-color: transparent;
}

.wcard__info-trigger:hover {
    color: rgba(255, 255, 255, 0.95);
    background: rgba(255, 255, 255, 0.06);
}

.wcard__amount-row {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
    margin-bottom: 0;
}

.wcard__amount {
    font-family: var(--font-receipt);
    font-size: 1.85rem;
    font-weight: 800;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

.wcard__amount--emerald {
    color: #4cde8f;
    text-shadow: 0 0 20px rgba(76, 222, 143, 0.2);
}

.wcard__amount--cyan {
    color: var(--color-base-2, #64d2ff);
    text-shadow: 0 0 20px rgba(100, 210, 255, 0.25);
}

.wcard__currency {
    font-family: var(--font-receipt);
    font-size: 1.2rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.4);
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

.wallet-role-tab--active-deposit {
    background: rgba(255, 178, 239, 0.18);
    border: 1px solid rgba(255, 178, 239, 0.35);
    color: var(--color-base-1);
    box-shadow: 0 1px 6px rgba(255, 178, 239, 0.15);
}

.wallet-role-tab--active-withdraw {
    background: rgba(100, 210, 255, 0.18);
    border: 1px solid rgba(100, 210, 255, 0.35);
    color: var(--color-base-2, #64d2ff);
    box-shadow: 0 1px 6px rgba(100, 210, 255, 0.15);
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
    transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    -webkit-tap-highlight-color: transparent;
    user-select: none;
}

@media (hover: hover) and (pointer: fine) {
    .wallet-preset-chip:hover {
        background: rgba(255, 255, 255, 0.07);
        border-color: rgba(255, 255, 255, 0.18);
        color: #ffffff;
    }
}

.wallet-preset-chip--active {
    background: rgba(255, 178, 239, 0.14);
    border-color: rgba(255, 178, 239, 0.45);
    color: var(--color-base-1, #ffb2ef);
    box-shadow: none;
}

@media (hover: hover) and (pointer: fine) {
    .wallet-preset-chip--active:hover {
        background: rgba(255, 178, 239, 0.18);
        border-color: rgba(255, 178, 239, 0.6);
        color: #ffffff;
    }
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
    min-width: 0;
}

.wallet-glass-input {
    width: 100%;
    box-sizing: border-box;
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
    -moz-appearance: textfield;
}

.wallet-glass-input::-webkit-outer-spin-button,
.wallet-glass-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
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
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    transition: all 0.2s ease;
}

.wallet-primary-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}

.wallet-primary-btn--deposit {
    background: linear-gradient(135deg, rgba(255, 178, 239, 0.15) 0%, rgba(244, 114, 182, 0.08) 100%);
    border: 1px solid rgba(255, 178, 239, 0.35);
    color: var(--color-base-1);
    font-weight: 700;
    letter-spacing: 0.04em;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.15);
}

.wallet-primary-btn--deposit:hover:not(:disabled) {
    background: linear-gradient(135deg, rgba(255, 178, 239, 0.22) 0%, rgba(244, 114, 182, 0.12) 100%);
    border-color: rgba(255, 178, 239, 0.55);
    color: #ffffff;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.wallet-primary-btn--withdraw {
    background: linear-gradient(135deg, rgba(100, 210, 255, 0.15) 0%, rgba(56, 189, 248, 0.08) 100%);
    border: 1px solid rgba(100, 210, 255, 0.35);
    color: var(--color-base-2, #64d2ff);
    font-weight: 700;
    letter-spacing: 0.04em;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.15);
}

.wallet-primary-btn--withdraw:hover:not(:disabled) {
    background: linear-gradient(135deg, rgba(100, 210, 255, 0.22) 0%, rgba(56, 189, 248, 0.12) 100%);
    border-color: rgba(100, 210, 255, 0.55);
    color: #ffffff;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.2);
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
}

.wallet-history-tabs--desktop {
    display: flex;
}

.wallet-history-select--mobile {
    display: none;
}

.wallet-history-select-wrap {
    width: 100%;
}

.wallet-app-select {
    width: 100% !important;
    min-height: 42px !important;
    border-radius: 8px !important;
    background: rgba(255, 178, 239, 0.05) !important;
    border: 1px solid rgba(255, 178, 239, 0.2) !important;
    color: #ffffff !important;
    font-size: 0.92rem !important;
    font-weight: 600 !important;
    padding: 0.55rem 0.85rem !important;
    box-sizing: border-box !important;
    transition: all 0.2s ease !important;
}

.wallet-app-select:hover {
    border-color: rgba(255, 178, 239, 0.4) !important;
    background: rgba(255, 178, 239, 0.08) !important;
}

.wallet-app-select.app-select--open {
    border-color: rgba(255, 178, 239, 0.6) !important;
    box-shadow: 0 0 12px rgba(255, 178, 239, 0.2) !important;
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

/* ── Ledger Table ─────────────────────────────────────────── */
.wallet-ledger {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.wallet-ledger__head {
    display: grid;
    grid-template-columns: 80px minmax(260px, 1fr) 175px 170px;
    gap: 1rem;
    padding: 0.5rem 1rem;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(220, 220, 255, 0.45);
    border-bottom: 1px solid rgba(255, 178, 239, 0.1);
    margin-bottom: 0.35rem;
}

.wallet-ledger__body {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.wallet-tx-row {
    display: grid;
    grid-template-columns: 80px minmax(260px, 1fr) 175px 170px;
    gap: 1rem;
    align-items: center;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: background 0.15s ease, border-color 0.15s ease;
    cursor: pointer;
}

.wallet-tx-row:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 178, 239, 0.18);
}

.wallet-tx-row:hover,
.wallet-tx-row:active {
    transform: none !important;
}

.wallet-col {
    min-width: 0;
}

/* Col: ID */
.wallet-tx-id-btn {
    font-family: var(--font-receipt);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(220, 220, 255, 0.75);
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 4px;
    padding: 0.25rem 0.5rem;
    cursor: pointer;
    transition: all 0.15s ease;
}

.wallet-tx-id-btn:hover {
    background: rgba(255, 178, 239, 0.15);
    border-color: rgba(255, 178, 239, 0.35);
    color: var(--color-base-1);
}

.wallet-tx-id-hash {
    color: rgba(255, 178, 239, 0.6);
}

.wallet-tx-id-icon {
    font-size: 0.7rem;
    opacity: 0.6;
}

/* Col: Type */
.wallet-col--type {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.wallet-tx-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.wallet-tx-icon--emerald {
    background: rgba(76, 222, 143, 0.12);
    border: 1px solid rgba(76, 222, 143, 0.25);
    color: #4cde8f;
}

.wallet-tx-icon--cyan {
    background: rgba(100, 210, 255, 0.12);
    border: 1px solid rgba(100, 210, 255, 0.3);
    color: var(--color-base-2, #64d2ff);
}

.wallet-tx-icon--coral {
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.25);
    color: #f87171;
}

.wallet-tx-title-group {
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.wallet-tx-title-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.wallet-tx-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: rgba(240, 240, 255, 0.95);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.wallet-tx-subdesc {
    font-size: 0.76rem;
    color: rgba(220, 220, 255, 0.5);
    margin-top: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    max-width: 100%;
}

.wallet-tx-mobile-meta {
    display: none;
}

/* Col: Date */
.wallet-tx-date {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.76rem;
    color: rgba(220, 220, 255, 0.5);
}

.wallet-tx-clock {
    font-size: 0.78rem;
    opacity: 0.7;
}

/* Col: Status */
.wallet-tx-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.2rem 0.55rem;
    border-radius: 9999px;
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.wallet-tx-status-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
}

.wallet-status--completed {
    background: rgba(76, 222, 143, 0.12);
    border: 1px solid rgba(76, 222, 143, 0.25);
    color: #4cde8f;
}
.wallet-status--completed .wallet-tx-status-dot {
    background: #4cde8f;
}

.wallet-status--pending {
    background: rgba(251, 191, 36, 0.12);
    border: 1px solid rgba(251, 191, 36, 0.25);
    color: #fbbf24;
}
.wallet-status--pending .wallet-tx-status-dot {
    background: #fbbf24;
}

.wallet-status--failed {
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.25);
    color: #f87171;
}
.wallet-status--failed .wallet-tx-status-dot {
    background: #f87171;
}

.wallet-status--cancelled {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: rgba(220, 220, 255, 0.5);
}
.wallet-status--cancelled .wallet-tx-status-dot {
    background: rgba(220, 220, 255, 0.5);
}

/* Col: Amount */
.wallet-col--amount {
    text-align: right;
}

.wallet-tx-amount {
    font-family: var(--font-receipt);
    font-size: 0.98rem;
    font-weight: 800;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
}

.wallet-tx-amount--emerald {
    color: #4cde8f;
}

.wallet-tx-amount--cyan {
    color: var(--color-base-2, #64d2ff);
}

.wallet-tx-amount--coral {
    color: rgba(240, 240, 255, 0.88);
}

.wallet-tx-remain {
    font-family: var(--font-receipt);
    font-size: 0.7rem;
    color: rgba(220, 220, 255, 0.4);
    margin-top: 0.15rem;
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

/* ── Transaction Receipt Modal ────────────────────────────── */
:deep(.site-modal-sheet--compact) {
    height: auto !important;
    max-height: 88svh !important;
}

.wallet-tx-modal {
    position: relative;
    padding: 1.5rem 1.25rem 1.25rem;
    color: #ffffff;
    display: flex;
    flex-direction: column;
}

.wallet-tx-modal__header {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    margin-bottom: 1.2rem;
}

.wallet-tx-modal__icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    margin-bottom: 0.75rem;
}

.wallet-tx-modal__title {
    font-size: 1.1rem;
    font-weight: 700;
    color: rgba(240, 240, 255, 0.95);
    margin: 0 0 0.4rem 0;
    line-height: 1.3;
}

.wallet-tx-modal__amount {
    font-family: var(--font-receipt);
    font-size: 1.75rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    margin-bottom: 0.6rem;
    font-variant-numeric: tabular-nums;
}

.wallet-tx-modal__divider {
    height: 1px;
    background: radial-gradient(circle, rgba(255, 178, 239, 0.3) 0%, rgba(255, 178, 239, 0.05) 80%, transparent 100%);
    margin: 0 0 1.1rem 0;
}

.wallet-tx-modal__details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 10px;
    padding: 0.9rem 1.05rem;
    margin-bottom: 1.25rem;
}

.wallet-tx-detail-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    font-size: 0.86rem;
    padding: 0.45rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

.wallet-tx-detail-row:last-child {
    border-bottom: none;
}

.wallet-tx-detail-label {
    color: rgba(220, 220, 255, 0.5);
    font-size: 0.84rem;
    font-weight: 500;
    white-space: nowrap;
    flex-shrink: 0;
}

.wallet-tx-detail-label--tip {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-bottom: 1px dotted rgba(220, 220, 255, 0.35);
    transition: color 0.15s ease, border-color 0.15s ease;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

@media (hover: hover) and (pointer: fine) {
    .wallet-tx-detail-label--tip:hover {
        color: rgba(245, 245, 255, 0.95);
        border-bottom-color: var(--color-base-1, #ffb2ef);
    }
}

.wallet-tx-detail-tip-icon {
    font-size: 0.85rem;
    color: rgba(255, 178, 239, 0.7);
}

.wallet-tx-detail-val {
    color: rgba(240, 240, 255, 0.95);
    font-weight: 600;
    text-align: right;
    white-space: nowrap;
}

.wallet-tx-detail-val--accent {
    font-family: var(--font-receipt);
    color: var(--color-base-1, #ffb2ef);
    font-weight: 700;
}

.wallet-tx-detail-row--desc {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.35rem;
    padding-top: 0.5rem;
    border-top: none;
    border-bottom: none;
}

.wallet-tx-detail-row--desc .wallet-tx-detail-label {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: rgba(255, 178, 239, 0.6);
}

.wallet-tx-detail-row--desc .wallet-tx-detail-val {
    text-align: left;
    white-space: normal;
    font-size: 0.85rem;
    color: rgba(220, 220, 255, 0.85);
    line-height: 1.45;
    word-break: normal;
    overflow-wrap: break-word;
}

.wallet-tx-modal__actions {
    display: flex;
    justify-content: center;
}

.wallet-tx-modal__btn {
    width: 100%;
    height: 42px;
    border-radius: 8px;
    background: rgba(255, 178, 239, 0.12);
    border: 1px solid rgba(255, 178, 239, 0.28);
    color: var(--color-base-1, #ffb2ef);
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.wallet-tx-modal__btn:hover {
    background: rgba(255, 178, 239, 0.22);
    color: #ffffff;
    border-color: rgba(255, 178, 239, 0.45);
}

/* ── Responsiveness ───────────────────────────────────────── */
@media (max-width: 860px) {
    .wallet-history-tabs--desktop {
        display: none;
    }

    .wallet-history-select--mobile {
        display: block;
        width: 100%;
    }

    .wallet-ledger__head {
        display: none;
    }

    .wallet-tx-row {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 0.85rem 0.95rem;
        cursor: pointer;
    }

    .wallet-col--id,
    .wallet-col--date {
        display: none;
    }

    .wallet-tx-status-pill--inline {
        display: none;
    }

    .wallet-col--type {
        flex: 1;
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .wallet-tx-subdesc {
        display: none;
    }

    .wallet-tx-title {
        font-size: 0.94rem;
        font-weight: 700;
        white-space: normal;
        line-height: 1.35;
        word-break: normal;
        overflow-wrap: normal;
    }

    .wallet-tx-mobile-meta {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.35rem;
        margin-top: 0.25rem;
        font-size: 0.74rem;
        color: rgba(220, 220, 255, 0.5);
    }

    .wallet-tx-mobile-date {
        font-size: 0.74rem;
        color: rgba(220, 220, 255, 0.55);
    }

    .wallet-tx-mobile-id {
        display: none;
    }

    .wallet-tx-dot {
        font-size: 0.6rem;
        color: rgba(255, 255, 255, 0.25);
    }

    .wallet-col--amount {
        flex-shrink: 0;
        text-align: right;
    }

    .wallet-tx-amount {
        font-size: 1.05rem;
        font-weight: 800;
    }

    .wallet-tx-remain {
        display: none;
    }
}

@media (max-width: 768px) {
    .wallet-wrapper {
        padding: 1rem 0.75rem calc(5rem + env(safe-area-inset-bottom));
    }

    .wallet-topbar {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 1.15rem;
        margin-bottom: 1.25rem;
    }

    .wallet-brand {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    .wallet-brand__title {
        font-size: 1.4rem;
    }

    .wallet-brand__title-icon {
        font-size: 1.35rem;
    }

    .wallet-views {
        width: 100%;
    }

    .wallet-view-btn {
        flex: 1;
        justify-content: center;
        height: 42px;
    }

    .wallet-cards-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .wcard {
        padding: 1.15rem 1.15rem;
    }

    .wcard__watermark {
        font-size: 5.2rem;
        right: -8px;
        bottom: -10px;
    }

    .wallet-action-box {
        padding: 1.25rem 1rem;
    }

    .wallet-role-tabs {
        width: 100%;
        display: flex;
        box-sizing: border-box;
        margin-bottom: 1.2rem;
    }

    .wallet-role-tab {
        flex: 1;
        justify-content: center;
        text-align: center;
        height: 40px;
        font-size: 0.86rem;
    }

    .wallet-action-header {
        justify-content: center;
        text-align: center;
        margin-bottom: 1.1rem;
    }

    .wallet-action-title {
        text-align: center;
        font-size: 1rem;
    }

    .wallet-preset-chips {
        width: 100%;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
        margin-bottom: 1.1rem;
    }

    .wallet-preset-chip {
        height: 44px;
        font-size: 0.92rem;
    }

    .wallet-action-form {
        flex-direction: column;
        align-items: stretch;
        width: 100%;
        gap: 0.85rem;
    }

    .wallet-input-container {
        width: 100%;
        flex: none;
    }

    .wallet-glass-input {
        width: 100%;
        box-sizing: border-box;
        height: 46px;
        font-size: 1rem;
    }

    .wallet-primary-btn {
        width: 100%;
        height: 46px;
        justify-content: center;
        font-size: 0.92rem;
    }

    .wallet-history {
        padding: 1rem 0.75rem;
    }
}
</style>
