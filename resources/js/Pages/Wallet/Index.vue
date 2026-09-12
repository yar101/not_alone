<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { ElNotification } from 'element-plus';

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
});

const currentWallet = ref({ ...props.wallet });
const depositAmount = ref(500);
const isDepositing = ref(false);
const activeTab = ref('all');

const quickAmounts = [100, 500, 1000, 2500, 5000];

const txTabs = [
    { id: 'all', label: 'Все операции' },
    { id: 'deposit', label: 'Пополнения' },
    { id: 'orders', label: 'Заказы & Escrow' },
    { id: 'packs', label: 'Контент' },
];

const filteredTransactions = computed(() => {
    const list = props.transactions?.data || [];
    if (activeTab.value === 'all') return list;
    if (activeTab.value === 'deposit') return list.filter(t => t.type === 'deposit');
    if (activeTab.value === 'orders') return list.filter(t => ['order_hold', 'order_payout', 'order_refund'].includes(t.type));
    if (activeTab.value === 'packs') return list.filter(t => ['pack_purchase', 'pack_sale'].includes(t.type));
    return list;
});

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
            title: 'Ошибка операции',
            message: e.response?.data?.error || 'Не удалось выполнить пополнение',
            type: 'error',
            customClass: 'app-notif',
        });
    } finally {
        isDepositing.value = false;
    }
}

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

function getTxIcon(type) {
    if (isHoldTx(type)) return 'fa-solid fa-shield-halved';
    if (isPositiveTx(type)) return 'fa-solid fa-arrow-down-left';
    return 'fa-solid fa-arrow-up-right';
}
</script>

<template>
    <Head title="Мой кошелёк" />
    <AppLayout>
        <div class="wallet-page">
            <!-- Ambient glow behind page header -->
            <div class="wallet-ambient ambient-pink" />
            <div class="wallet-ambient ambient-blue" />

            <div class="wallet-container">
                <!-- Page Header -->
                <div class="wallet-header">
                    <div class="wallet-header__main">
                        <div class="wallet-icon-box">
                            <i class="fa-solid fa-coins"></i>
                        </div>
                        <div class="wallet-header__info">
                            <div class="wallet-header__top">
                                <h1 class="wallet-title">Мой кошелёк</h1>
                                <span class="wallet-escrow-badge">
                                    <i class="fa-solid fa-shield-check"></i> Escrow Protected
                                </span>
                            </div>
                            <p class="wallet-subtitle">
                                Управление личным счётом, безопасные сделки с заморозкой средств и прозрачный аудит операций
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 3 Glass Balance Cards -->
                <div class="wallet-cards">
                    <!-- Available Balance -->
                    <div class="wcard wcard--available">
                        <div class="wcard__top">
                            <div class="wcard__icon-wrap wcard__icon-wrap--emerald">
                                <i class="fa-solid fa-wallet"></i>
                            </div>
                            <div class="wcard__badge wcard__badge--emerald">Свободно</div>
                        </div>
                        <div class="wcard__amount-wrap">
                            <span class="wcard__amount wcard__amount--emerald">
                                {{ formatMoney(currentWallet.balance) }}
                            </span>
                            <span class="wcard__currency">₽</span>
                        </div>
                        <div class="wcard__label">Доступный баланс</div>
                        <p class="wcard__desc">Свободные средства для вывода, оплаты услуг и контента</p>
                    </div>

                    <!-- Escrow Hold Balance -->
                    <div class="wcard wcard--escrow">
                        <div class="wcard__top">
                            <div class="wcard__icon-wrap wcard__icon-wrap--amber">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                            <div class="wcard__badge wcard__badge--amber">
                                <i class="fa-solid fa-lock"></i> Escrow сделки
                            </div>
                        </div>
                        <div class="wcard__amount-wrap">
                            <span class="wcard__amount wcard__amount--amber">
                                {{ formatMoney(currentWallet.held_balance) }}
                            </span>
                            <span class="wcard__currency">₽</span>
                        </div>
                        <div class="wcard__label">В резерве (Холд)</div>
                        <p class="wcard__desc">Заморожено по активным заказам до их успешного завершения</p>
                    </div>

                    <!-- Total Balance -->
                    <div class="wcard wcard--total">
                        <div class="wcard__top">
                            <div class="wcard__icon-wrap wcard__icon-wrap--total">
                                <i class="fa-solid fa-layer-group"></i>
                            </div>
                            <div class="wcard__badge wcard__badge--neutral">Всего на счёте</div>
                        </div>
                        <div class="wcard__amount-wrap">
                            <span class="wcard__amount wcard__amount--total">
                                {{ formatMoney(currentWallet.total_balance) }}
                            </span>
                            <span class="wcard__currency">₽</span>
                        </div>
                        <div class="wcard__label">Общий капитал</div>
                        <p class="wcard__desc">Доступный баланс + средства в безопасных сделках</p>
                    </div>
                </div>

                <!-- Test Deposit Panel -->
                <div v-if="canDeposit" class="wallet-deposit-card">
                    <div class="wallet-deposit-card__header">
                        <div class="wallet-deposit-card__title">
                            <div class="wallet-deposit-card__icon">
                                <i class="fa-solid fa-bolt"></i>
                            </div>
                            <span>Тестовое пополнение счёта</span>
                        </div>
                        <span class="wallet-deposit-card__tag">Sandbox Dev</span>
                    </div>

                    <p class="wallet-deposit-card__hint">
                        В режиме тестирования средства зачисляются моментально без реального списания с банковской карты.
                    </p>

                    <!-- Quick amount chips -->
                    <div class="wallet-chips-bar">
                        <button
                            v-for="amt in quickAmounts"
                            :key="amt"
                            type="button"
                            class="wallet-chip"
                            :class="{ 'wallet-chip--active': depositAmount === amt }"
                            @click="depositAmount = amt"
                        >
                            +{{ formatMoney(amt) }} ₽
                        </button>
                    </div>

                    <!-- Deposit form -->
                    <div class="wallet-deposit-form">
                        <div class="wallet-input-wrapper">
                            <input
                                v-model.number="depositAmount"
                                type="number"
                                min="10"
                                step="10"
                                class="wallet-input"
                                placeholder="Сумма пополнения"
                                @keydown.enter.prevent="handleDeposit"
                            />
                            <span class="wallet-input__suffix">₽</span>
                        </div>
                        <button
                            type="button"
                            class="wallet-submit-btn"
                            :disabled="isDepositing"
                            @click="handleDeposit"
                        >
                            <i v-if="isDepositing" class="fa-solid fa-circle-notch fa-spin"></i>
                            <i v-else class="fa-solid fa-arrow-down-to-line"></i>
                            <span>{{ isDepositing ? 'Пополнение...' : 'Пополнить баланс' }}</span>
                        </button>
                    </div>
                </div>

                <!-- Transactions Ledger -->
                <div class="wallet-ledger">
                    <div class="wallet-ledger__header">
                        <div class="wallet-ledger__title-group">
                            <h2 class="wallet-ledger__title">История транзакций</h2>
                            <span class="wallet-ledger__count" v-if="transactions.data?.length">
                                {{ transactions.total ?? transactions.data.length }} операций
                            </span>
                        </div>

                        <!-- Filter Tabs -->
                        <div class="wallet-tabs">
                            <button
                                v-for="tab in txTabs"
                                :key="tab.id"
                                type="button"
                                class="wallet-tab"
                                :class="{ 'wallet-tab--active': activeTab === tab.id }"
                                @click="activeTab = tab.id"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="!filteredTransactions.length" class="wallet-empty">
                        <div class="wallet-empty__icon-box">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                        <h3 class="wallet-empty__title">Транзакции не найдены</h3>
                        <p class="wallet-empty__desc">
                            {{ activeTab === 'all' 
                                ? 'Когда вы совершите пополнение, оформите заказ или купите контент-пак, операционный чек отобразится в этом списке.' 
                                : 'В выбранной категории операций пока нет записей.' }}
                        </p>
                        <button
                            v-if="canDeposit && activeTab !== 'deposit'"
                            type="button"
                            class="wallet-empty__btn"
                            @click="depositAmount = 500; handleDeposit()"
                        >
                            <i class="fa-solid fa-bolt"></i> Пополнить тестовый счёт
                        </button>
                    </div>

                    <!-- Transaction List -->
                    <div v-else class="wallet-tx-list">
                        <div
                            v-for="tx in filteredTransactions"
                            :key="tx.id"
                            class="wallet-tx-row"
                            :class="{
                                'wallet-tx-row--positive': isPositiveTx(tx.type),
                                'wallet-tx-row--hold': isHoldTx(tx.type),
                            }"
                        >
                            <!-- Left: Icon & Details -->
                            <div class="wallet-tx-row__left">
                                <div
                                    class="wallet-tx-row__icon-box"
                                    :class="{
                                        'icon--emerald': isPositiveTx(tx.type),
                                        'icon--amber': isHoldTx(tx.type),
                                        'icon--coral': !isPositiveTx(tx.type) && !isHoldTx(tx.type),
                                    }"
                                >
                                    <i :class="getTxIcon(tx.type)"></i>
                                </div>
                                <div class="wallet-tx-row__details">
                                    <div class="wallet-tx-row__type">
                                        {{ tx.type_label }}
                                    </div>
                                    <div v-if="tx.description" class="wallet-tx-row__desc">
                                        {{ tx.description }}
                                    </div>
                                    <div class="wallet-tx-row__meta">
                                        <span class="wallet-tx-row__date">
                                            <i class="fa-regular fa-clock"></i> {{ formatDate(tx.created_at) }}
                                        </span>
                                        <span class="wallet-tx-row__id">#{{ tx.id }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Amount & Balance After -->
                            <div class="wallet-tx-row__right">
                                <div
                                    class="wallet-tx-row__amount"
                                    :class="{
                                        'amount--emerald': isPositiveTx(tx.type),
                                        'amount--amber': isHoldTx(tx.type),
                                        'amount--coral': !isPositiveTx(tx.type) && !isHoldTx(tx.type),
                                    }"
                                >
                                    {{ isPositiveTx(tx.type) ? '+' : isHoldTx(tx.type) ? '' : '−' }}
                                    {{ formatMoney(tx.amount) }} ₽
                                </div>
                                <div class="wallet-tx-row__after">
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
/* ── Global Canvas & Ambient Lighting ─────────────────────── */
.wallet-page {
    position: relative;
    min-height: calc(100vh - 72px);
    overflow: hidden;
}

.wallet-ambient {
    position: absolute;
    pointer-events: none;
    z-index: 0;
    border-radius: 50%;
    filter: blur(80px);
}

.ambient-pink {
    top: -60px;
    left: 5%;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(255, 178, 239, 0.12) 0%, transparent 70%);
}

.ambient-blue {
    top: 220px;
    right: 5%;
    width: 440px;
    height: 440px;
    background: radial-gradient(circle, rgba(100, 210, 255, 0.09) 0%, transparent 70%);
}

.wallet-container {
    position: relative;
    z-index: 1;
    max-width: 980px;
    margin: 0 auto;
    padding: 2.2rem 1.5rem calc(5rem + env(safe-area-inset-bottom));
}

/* ── Header ───────────────────────────────────────────────── */
.wallet-header {
    margin-bottom: 2rem;
}

.wallet-header__main {
    display: flex;
    align-items: flex-start;
    gap: 1.25rem;
}

.wallet-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(255, 178, 239, 0.16) 0%, rgba(100, 210, 255, 0.1) 100%);
    border: 1px solid rgba(255, 178, 239, 0.3);
    box-shadow: 0 4px 20px rgba(255, 178, 239, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: var(--color-base-1);
    flex-shrink: 0;
}

.wallet-header__info {
    flex: 1;
    min-width: 0;
}

.wallet-header__top {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
    margin-bottom: 0.35rem;
}

.wallet-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: rgba(240, 240, 255, 0.96);
    letter-spacing: -0.01em;
    margin: 0;
    line-height: 1.2;
}

.wallet-escrow-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.25rem 0.65rem;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    border-radius: 9999px;
    background: rgba(100, 210, 255, 0.1);
    border: 1px solid rgba(100, 210, 255, 0.25);
    color: var(--color-base-2);
}

.wallet-subtitle {
    font-size: 0.92rem;
    color: rgba(220, 220, 255, 0.55);
    line-height: 1.45;
    margin: 0;
    max-width: 720px;
}

/* ── 3 Glass Balance Cards ────────────────────────────────── */
.wallet-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.wcard {
    position: relative;
    background: rgba(14, 10, 24, 0.68);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 178, 239, 0.12);
    border-radius: 12px;
    padding: 1.5rem 1.4rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    overflow: hidden;
}

.wcard:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(255, 255, 255, 0.14);
}

/* Top sheen highlight */
.wcard::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    pointer-events: none;
    z-index: 2;
}

.wcard--available::before {
    background: linear-gradient(90deg, transparent 0%, rgba(76, 222, 143, 0.6) 50%, transparent 100%);
}

.wcard--escrow::before {
    background: linear-gradient(90deg, transparent 0%, rgba(251, 191, 36, 0.6) 50%, transparent 100%);
}

.wcard--total::before {
    background: linear-gradient(90deg, transparent 0%, rgba(255, 178, 239, 0.6) 50%, transparent 100%);
}

.wcard__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.1rem;
}

.wcard__icon-wrap {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.wcard__icon-wrap--emerald {
    background: rgba(76, 222, 143, 0.12);
    border: 1px solid rgba(76, 222, 143, 0.25);
    color: #4cde8f;
}

.wcard__icon-wrap--amber {
    background: rgba(251, 191, 36, 0.12);
    border: 1px solid rgba(251, 191, 36, 0.25);
    color: #fbbf24;
}

.wcard__icon-wrap--total {
    background: rgba(255, 178, 239, 0.12);
    border: 1px solid rgba(255, 178, 239, 0.25);
    color: var(--color-base-1);
}

.wcard__badge {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    padding: 0.2rem 0.6rem;
    border-radius: 9999px;
    text-transform: uppercase;
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

.wcard__badge--neutral {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: rgba(220, 220, 255, 0.75);
}

.wcard__amount-wrap {
    display: flex;
    align-items: baseline;
    gap: 0.4rem;
    margin-bottom: 0.35rem;
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
    text-shadow: 0 0 24px rgba(76, 222, 143, 0.25);
}

.wcard__amount--amber {
    color: #fbbf24;
    text-shadow: 0 0 24px rgba(251, 191, 36, 0.25);
}

.wcard__amount--total {
    color: rgba(250, 250, 255, 0.98);
}

.wcard__currency {
    font-family: var(--font-receipt);
    font-size: 1.25rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.4);
}

.wcard__label {
    font-size: 0.82rem;
    font-weight: 600;
    color: rgba(220, 220, 255, 0.65);
    margin-bottom: 0.3rem;
}

.wcard__desc {
    font-size: 0.76rem;
    color: rgba(220, 220, 255, 0.4);
    line-height: 1.35;
    margin: 0;
}

/* ── Deposit Panel (Glass + Neon) ─────────────────────────── */
.wallet-deposit-card {
    position: relative;
    background: rgba(14, 10, 24, 0.68);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 178, 239, 0.16);
    border-radius: 12px;
    padding: 1.5rem 1.75rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.08);
    overflow: hidden;
}

.wallet-deposit-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(100, 210, 255, 0.4) 50%, transparent 100%);
    pointer-events: none;
}

.wallet-deposit-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.4rem;
}

.wallet-deposit-card__title {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-size: 1.05rem;
    font-weight: 700;
    color: rgba(240, 240, 255, 0.95);
}

.wallet-deposit-card__icon {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: rgba(100, 210, 255, 0.12);
    border: 1px solid rgba(100, 210, 255, 0.25);
    color: var(--color-base-2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
}

.wallet-deposit-card__tag {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.2rem 0.55rem;
    border-radius: 4px;
    background: rgba(76, 222, 143, 0.1);
    border: 1px solid rgba(76, 222, 143, 0.25);
    color: #4cde8f;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.wallet-deposit-card__hint {
    font-size: 0.84rem;
    color: rgba(220, 220, 255, 0.5);
    margin: 0 0 1.25rem 0;
    line-height: 1.4;
}

/* Chips Bar */
.wallet-chips-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1.25rem;
}

.wallet-chip {
    font-family: var(--font-receipt);
    padding: 0.45rem 0.9rem;
    font-size: 0.84rem;
    font-weight: 600;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.09);
    color: rgba(220, 220, 255, 0.75);
    cursor: pointer;
    transition: all 0.15s ease;
    min-height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wallet-chip:hover {
    background: rgba(255, 178, 239, 0.08);
    border-color: rgba(255, 178, 239, 0.3);
    color: rgba(255, 255, 255, 0.95);
}

.wallet-chip--active {
    background: rgba(255, 178, 239, 0.16);
    border-color: var(--color-base-1);
    color: var(--color-base-1);
    box-shadow: 0 0 16px rgba(255, 178, 239, 0.25);
}

/* Deposit Form */
.wallet-deposit-form {
    display: flex;
    gap: 0.85rem;
    align-items: center;
}

.wallet-input-wrapper {
    position: relative;
    flex: 1;
    min-width: 180px;
}

.wallet-input {
    width: 100%;
    height: 44px;
    padding: 0 2.2rem 0 1rem;
    font-family: var(--font-receipt);
    font-size: 0.98rem;
    font-weight: 600;
    background: rgba(10, 7, 20, 0.65);
    border: 1px solid rgba(255, 178, 239, 0.18);
    border-radius: 6px;
    color: #ffffff;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.wallet-input::placeholder {
    color: rgba(255, 255, 255, 0.25);
    font-family: inherit;
}

.wallet-input:focus {
    border-color: var(--color-base-1);
    box-shadow: 0 0 0 3px rgba(255, 178, 239, 0.15);
}

.wallet-input__suffix {
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

.wallet-submit-btn {
    height: 44px;
    padding: 0 1.6rem;
    font-size: 0.88rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    border-radius: 6px;
    border: 1px solid rgba(255, 178, 239, 0.4);
    background: linear-gradient(135deg, rgba(255, 178, 239, 0.22) 0%, rgba(100, 210, 255, 0.16) 100%);
    color: var(--color-base-1);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.12);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    white-space: nowrap;
    transition: all 0.2s ease;
}

.wallet-submit-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, rgba(255, 178, 239, 0.32) 0%, rgba(100, 210, 255, 0.24) 100%);
    border-color: var(--color-base-1);
    color: #ffffff;
    box-shadow: 0 0 20px rgba(255, 178, 239, 0.35);
    transform: translateY(-1px);
}

.wallet-submit-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* ── Transactions Ledger ──────────────────────────────────── */
.wallet-ledger {
    position: relative;
    background: rgba(14, 10, 24, 0.68);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 178, 239, 0.12);
    border-radius: 12px;
    padding: 1.75rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.08);
    overflow: hidden;
}

.wallet-ledger::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(255, 178, 239, 0.25) 50%, transparent 100%);
    pointer-events: none;
}

.wallet-ledger__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.wallet-ledger__title-group {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.wallet-ledger__title {
    font-size: 1.25rem;
    font-weight: 700;
    color: rgba(240, 240, 255, 0.95);
    margin: 0;
}

.wallet-ledger__count {
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.2rem 0.6rem;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(220, 220, 255, 0.65);
}

/* Tabs */
.wallet-tabs {
    display: flex;
    background: rgba(255, 178, 239, 0.04);
    border: 1px solid rgba(255, 178, 239, 0.12);
    border-radius: 6px;
    padding: 3px;
    gap: 2px;
}

.wallet-tab {
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

.wallet-tab:hover {
    color: rgba(255, 255, 255, 0.9);
}

.wallet-tab--active {
    background: rgba(255, 178, 239, 0.14);
    color: var(--color-base-1);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
}

/* Empty State */
.wallet-empty {
    text-align: center;
    padding: 3.5rem 1.5rem;
}

.wallet-empty__icon-box {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    margin: 0 auto 1.25rem;
    background: rgba(255, 178, 239, 0.06);
    border: 1px solid rgba(255, 178, 239, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: rgba(255, 178, 239, 0.5);
    box-shadow: 0 0 30px rgba(255, 178, 239, 0.1);
}

.wallet-empty__title {
    font-size: 1.1rem;
    font-weight: 700;
    color: rgba(240, 240, 255, 0.85);
    margin: 0 0 0.5rem 0;
}

.wallet-empty__desc {
    font-size: 0.88rem;
    color: rgba(220, 220, 255, 0.45);
    max-width: 440px;
    margin: 0 auto 1.5rem auto;
    line-height: 1.45;
}

.wallet-empty__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.84rem;
    font-weight: 700;
    border-radius: 6px;
    background: rgba(255, 178, 239, 0.12);
    border: 1px solid rgba(255, 178, 239, 0.3);
    color: var(--color-base-1);
    cursor: pointer;
    transition: all 0.15s ease;
}

.wallet-empty__btn:hover {
    background: rgba(255, 178, 239, 0.2);
    color: #ffffff;
    box-shadow: 0 0 16px rgba(255, 178, 239, 0.25);
}

/* Transactions List */
.wallet-tx-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.wallet-tx-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.95rem 1.15rem;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: background 0.15s ease, border-color 0.15s ease;
}

.wallet-tx-row:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 178, 239, 0.16);
}

.wallet-tx-row__left {
    display: flex;
    align-items: center;
    gap: 0.95rem;
    min-width: 0;
}

.wallet-tx-row__icon-box {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}

.icon--emerald {
    background: rgba(76, 222, 143, 0.12);
    border: 1px solid rgba(76, 222, 143, 0.25);
    color: #4cde8f;
}

.icon--amber {
    background: rgba(251, 191, 36, 0.12);
    border: 1px solid rgba(251, 191, 36, 0.25);
    color: #fbbf24;
}

.icon--coral {
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.25);
    color: #f87171;
}

.wallet-tx-row__details {
    min-width: 0;
}

.wallet-tx-row__type {
    font-size: 0.94rem;
    font-weight: 700;
    color: rgba(240, 240, 255, 0.95);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.wallet-tx-row__desc {
    font-size: 0.8rem;
    color: rgba(220, 220, 255, 0.55);
    margin-top: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.wallet-tx-row__meta {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin-top: 0.25rem;
    font-size: 0.74rem;
    color: rgba(220, 220, 255, 0.4);
}

.wallet-tx-row__date i {
    font-size: 0.7rem;
    margin-right: 0.2rem;
}

.wallet-tx-row__id {
    font-family: var(--font-receipt);
    opacity: 0.7;
}

.wallet-tx-row__right {
    text-align: right;
    flex-shrink: 0;
    margin-left: 1rem;
}

.wallet-tx-row__amount {
    font-family: var(--font-receipt);
    font-size: 1.05rem;
    font-weight: 700;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
}

.amount--emerald {
    color: #4cde8f;
    text-shadow: 0 0 16px rgba(76, 222, 143, 0.2);
}

.amount--amber {
    color: #fbbf24;
}

.amount--coral {
    color: rgba(240, 240, 255, 0.85);
}

.wallet-tx-row__after {
    font-family: var(--font-receipt);
    font-size: 0.74rem;
    color: rgba(220, 220, 255, 0.4);
    margin-top: 0.25rem;
}

.wallet-tx-row__after span {
    color: rgba(220, 220, 255, 0.65);
}

/* ── Pagination ───────────────────────────────────────────── */
.wallet-pagination {
    display: flex;
    justify-content: center;
    gap: 0.4rem;
    margin-top: 1.75rem;
    flex-wrap: wrap;
}

.wallet-page-link {
    font-family: var(--font-receipt);
    padding: 0.35rem 0.75rem;
    font-size: 0.82rem;
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
    border-color: rgba(255, 178, 239, 0.3);
    color: #ffffff;
}

.wallet-page-link--active {
    background: rgba(255, 178, 239, 0.2);
    border-color: var(--color-base-1);
    color: var(--color-base-1);
    box-shadow: 0 0 12px rgba(255, 178, 239, 0.2);
}

.wallet-page-link--disabled {
    opacity: 0.35;
    pointer-events: none;
}

/* ── Mobile Responsive Breakpoints ────────────────────────── */
@media (max-width: 860px) {
    .wallet-cards {
        grid-template-columns: 1fr;
        gap: 0.85rem;
    }
    
    .wallet-deposit-form {
        flex-direction: column;
    }
    
    .wallet-submit-btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 640px) {
    .wallet-container {
        padding: 1.25rem 1rem calc(5rem + env(safe-area-inset-bottom));
    }

    .wallet-header__main {
        flex-direction: column;
        gap: 0.75rem;
    }

    .wallet-icon-box {
        width: 44px;
        height: 44px;
        font-size: 1.2rem;
    }

    .wallet-title {
        font-size: 1.45rem;
    }

    .wcard {
        padding: 1.2rem 1.1rem;
    }

    .wcard__amount {
        font-size: 1.55rem;
    }

    .wallet-deposit-card {
        padding: 1.25rem 1.1rem;
    }

    .wallet-ledger {
        padding: 1.25rem 1rem;
    }

    .wallet-tabs {
        width: 100%;
        overflow-x: auto;
        justify-content: flex-start;
    }

    .wallet-tx-row {
        padding: 0.85rem 0.9rem;
    }

    .wallet-tx-row__type {
        font-size: 0.88rem;
    }

    .wallet-tx-row__amount {
        font-size: 0.95rem;
    }
}
</style>
