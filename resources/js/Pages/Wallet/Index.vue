<script setup>
import { ref } from 'vue';
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
        default: () => ({ data: [], links: [] }),
    },
    canDeposit: {
        type: Boolean,
        default: false,
    },
});

const currentWallet = ref({ ...props.wallet });
const depositAmount = ref(500);
const isDepositing = ref(false);

const quickAmounts = [100, 500, 1000, 2500, 5000];

async function handleDeposit() {
    if (!depositAmount.value || depositAmount.value < 10) {
        ElNotification({
            title: 'Ошибка',
            message: 'Минимальная сумма пополнения — 10 ₽',
            type: 'warning',
        });
        return;
    }

    isDepositing.value = true;
    try {
        const { data } = await axios.post(route('wallet.deposit'), {
            amount: depositAmount.value,
        });

        ElNotification({
            title: 'Успешно',
            message: `Баланс успешно пополнен на ${depositAmount.value} ₽`,
            type: 'success',
        });

        currentWallet.value.balance = data.balance;
        currentWallet.value.total_balance = Number(data.balance) + Number(currentWallet.value.held_balance);

        // Reload page data to get the updated transactions list
        router.reload({ only: ['transactions', 'wallet'] });
    } catch (e) {
        ElNotification({
            title: 'Ошибка',
            message: e.response?.data?.error || 'Не удалось выполнить пополнение',
            type: 'error',
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
    return d.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function isPositiveTx(type) {
    return ['deposit', 'order_payout', 'order_refund', 'pack_sale'].includes(type);
}
</script>

<template>
    <Head title="Мой кошелёк" />
    <AppLayout>
        <div class="wallet-container">
            <div class="wallet-header">
                <div class="wallet-header__title-wrap">
                    <h1 class="wallet-title">
                        <i class="fa-solid fa-coins wallet-title-icon"></i>
                        Мой кошелёк
                    </h1>
                    <p class="wallet-subtitle">Управление личным счётом, безопасные сделки (эскроу) и история операций</p>
                </div>
            </div>

            <!-- Balance Cards -->
            <div class="wallet-cards">
                <div class="wallet-card wallet-card--primary">
                    <div class="wallet-card__header">
                        <span class="wallet-card__label">Доступный баланс</span>
                        <div class="wallet-card__badge">Доступно</div>
                    </div>
                    <div class="wallet-card__value">
                        {{ formatMoney(currentWallet.balance) }} <span class="wallet-card__curr">₽</span>
                    </div>
                    <div class="wallet-card__desc">Свободные средства для оплаты услуг и паков</div>
                </div>

                <div class="wallet-card wallet-card--escrow">
                    <div class="wallet-card__header">
                        <span class="wallet-card__label">В резерве (Escrow)</span>
                        <div class="wallet-card__badge wallet-card__badge--amber">Заблокировано</div>
                    </div>
                    <div class="wallet-card__value">
                        {{ formatMoney(currentWallet.held_balance) }} <span class="wallet-card__curr">₽</span>
                    </div>
                    <div class="wallet-card__desc">Холд по активным заказам до их успешного завершения</div>
                </div>

                <div class="wallet-card wallet-card--total">
                    <div class="wallet-card__header">
                        <span class="wallet-card__label">Всего на счёте</span>
                        <div class="wallet-card__badge wallet-card__badge--neutral">Общий баланс</div>
                    </div>
                    <div class="wallet-card__value">
                        {{ formatMoney(currentWallet.total_balance) }} <span class="wallet-card__curr">₽</span>
                    </div>
                    <div class="wallet-card__desc">Доступные средства + средства в безопасных сделках</div>
                </div>
            </div>

            <!-- Test Deposit Panel -->
            <div v-if="canDeposit" class="wallet-deposit-panel">
                <div class="wallet-deposit-panel__header">
                    <div class="wallet-deposit-panel__title">
                        <i class="fa-solid fa-circle-plus"></i> Тестовое пополнение баланса
                    </div>
                    <span class="wallet-deposit-panel__note">Режим тестирования</span>
                </div>
                <div class="wallet-deposit-panel__body">
                    <div class="wallet-chips">
                        <button
                            v-for="amt in quickAmounts"
                            :key="amt"
                            type="button"
                            class="wallet-chip"
                            :class="{ 'wallet-chip--active': depositAmount === amt }"
                            @click="depositAmount = amt"
                        >
                            +{{ amt }} ₽
                        </button>
                    </div>

                    <div class="wallet-deposit-form">
                        <div class="wallet-deposit-input-wrap">
                            <input
                                v-model.number="depositAmount"
                                type="number"
                                min="10"
                                step="10"
                                class="wallet-deposit-input"
                                placeholder="Сумма в рублях"
                            />
                            <span class="wallet-deposit-input__curr">₽</span>
                        </div>
                        <button
                            type="button"
                            class="wallet-btn wallet-btn--primary"
                            :disabled="isDepositing"
                            @click="handleDeposit"
                        >
                            <i v-if="isDepositing" class="fa-solid fa-circle-notch fa-spin"></i>
                            <i v-else class="fa-solid fa-bolt"></i>
                            Пополнить счёт
                        </button>
                    </div>
                </div>
            </div>

            <!-- Transactions Section -->
            <div class="wallet-tx-section">
                <div class="wallet-tx-header">
                    <h2 class="wallet-tx-title">История транзакций</h2>
                    <span class="wallet-tx-count" v-if="transactions.data?.length">
                        Всего: {{ transactions.total ?? transactions.data.length }}
                    </span>
                </div>

                <div v-if="!transactions.data || transactions.data.length === 0" class="wallet-empty">
                    <div class="wallet-empty__icon">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <div class="wallet-empty__title">История транзакций пуста</div>
                    <div class="wallet-empty__desc">Когда вы совершите пополнение, заказ или покупку контента, операция отобразится здесь.</div>
                </div>

                <div v-else class="wallet-tx-list">
                    <div
                        v-for="tx in transactions.data"
                        :key="tx.id"
                        class="wallet-tx-item"
                    >
                        <div class="wallet-tx-item__left">
                            <div
                                class="wallet-tx-item__icon"
                                :class="isPositiveTx(tx.type) ? 'wallet-tx-item__icon--positive' : 'wallet-tx-item__icon--negative'"
                            >
                                <i :class="isPositiveTx(tx.type) ? 'fa-solid fa-arrow-down-left' : 'fa-solid fa-arrow-up-right'"></i>
                            </div>
                            <div class="wallet-tx-item__info">
                                <div class="wallet-tx-item__type">{{ tx.type_label }}</div>
                                <div class="wallet-tx-item__desc" v-if="tx.description">{{ tx.description }}</div>
                                <div class="wallet-tx-item__date">{{ formatDate(tx.created_at) }}</div>
                            </div>
                        </div>

                        <div class="wallet-tx-item__right">
                            <div
                                class="wallet-tx-item__amount"
                                :class="isPositiveTx(tx.type) ? 'wallet-tx-item__amount--positive' : 'wallet-tx-item__amount--negative'"
                            >
                                {{ isPositiveTx(tx.type) ? '+' : '' }}{{ formatMoney(tx.amount) }} ₽
                            </div>
                            <div class="wallet-tx-item__balance-after">
                                Остаток: {{ formatMoney(tx.balance_after) }} ₽
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination if links exist -->
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
    </AppLayout>
</template>

<style scoped>
.wallet-container {
    max-width: 960px;
    margin: 0 auto;
    padding: 24px 16px 64px;
}

.wallet-header {
    margin-bottom: 28px;
}

.wallet-title {
    font-size: 26px;
    font-weight: 700;
    color: var(--color-text-primary, #0f172a);
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0 0 6px 0;
}

.wallet-title-icon {
    color: #8b5cf6;
}

.wallet-subtitle {
    font-size: 14px;
    color: var(--color-text-secondary, #64748b);
    margin: 0;
}

/* Balance Cards */
.wallet-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 18px;
    margin-bottom: 30px;
}

.wallet-card {
    background: var(--color-surface, #ffffff);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 16px;
    padding: 22px 24px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.wallet-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
}

.wallet-card--primary {
    border-color: rgba(139, 92, 246, 0.3);
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.04) 0%, rgba(255, 255, 255, 1) 100%);
}

.wallet-card--escrow {
    border-color: rgba(245, 158, 11, 0.3);
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.04) 0%, rgba(255, 255, 255, 1) 100%);
}

.wallet-card__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}

.wallet-card__label {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-text-secondary, #64748b);
}

.wallet-card__badge {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 9999px;
    background: rgba(139, 92, 246, 0.12);
    color: #7c3aed;
}

.wallet-card__badge--amber {
    background: rgba(245, 158, 11, 0.15);
    color: #d97706;
}

.wallet-card__badge--neutral {
    background: rgba(100, 116, 139, 0.12);
    color: #475569;
}

.wallet-card__value {
    font-size: 30px;
    font-weight: 800;
    color: var(--color-text-primary, #0f172a);
    line-height: 1.2;
    margin-bottom: 8px;
}

.wallet-card__curr {
    font-size: 20px;
    font-weight: 600;
    color: var(--color-text-muted, #94a3b8);
}

.wallet-card__desc {
    font-size: 12px;
    color: var(--color-text-muted, #94a3b8);
}

/* Deposit Panel */
.wallet-deposit-panel {
    background: var(--color-surface, #ffffff);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 16px;
    padding: 22px 24px;
    margin-bottom: 32px;
}

.wallet-deposit-panel__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.wallet-deposit-panel__title {
    font-size: 16px;
    font-weight: 700;
    color: var(--color-text-primary, #0f172a);
    display: flex;
    align-items: center;
    gap: 8px;
}

.wallet-deposit-panel__note {
    font-size: 12px;
    padding: 3px 8px;
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
    border-radius: 6px;
    font-weight: 600;
}

.wallet-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 14px;
}

.wallet-chip {
    padding: 6px 14px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 8px;
    border: 1px solid var(--color-border, #e2e8f0);
    background: var(--color-surface, #ffffff);
    color: var(--color-text-secondary, #475569);
    cursor: pointer;
    transition: all 0.15s ease;
}

.wallet-chip:hover {
    border-color: #8b5cf6;
    color: #8b5cf6;
}

.wallet-chip--active {
    border-color: #8b5cf6;
    background: #8b5cf6;
    color: #ffffff;
}

.wallet-deposit-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.wallet-deposit-input-wrap {
    position: relative;
    flex: 1;
    min-width: 180px;
}

.wallet-deposit-input {
    width: 100%;
    padding: 10px 32px 10px 14px;
    font-size: 15px;
    font-weight: 600;
    border: 1px solid var(--color-border, #cbd5e1);
    border-radius: 10px;
    outline: none;
    transition: border-color 0.15s;
}

.wallet-deposit-input:focus {
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
}

.wallet-deposit-input__curr {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-weight: 600;
    color: #94a3b8;
}

.wallet-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    transition: background 0.15s, transform 0.1s;
}

.wallet-btn--primary {
    background: #8b5cf6;
    color: #ffffff;
}

.wallet-btn--primary:hover:not(:disabled) {
    background: #7c3aed;
}

.wallet-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Transaction List */
.wallet-tx-section {
    background: var(--color-surface, #ffffff);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 16px;
    padding: 24px;
}

.wallet-tx-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.wallet-tx-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--color-text-primary, #0f172a);
    margin: 0;
}

.wallet-tx-count {
    font-size: 13px;
    color: #94a3b8;
}

.wallet-empty {
    text-align: center;
    padding: 48px 16px;
}

.wallet-empty__icon {
    font-size: 40px;
    color: #cbd5e1;
    margin-bottom: 12px;
}

.wallet-empty__title {
    font-size: 16px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
}

.wallet-empty__desc {
    font-size: 13px;
    color: #94a3b8;
    max-width: 420px;
    margin: 0 auto;
}

.wallet-tx-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.wallet-tx-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 16px;
    background: var(--color-bg-alt, #f8fafc);
    border: 1px solid var(--color-border-subtle, #f1f5f9);
    border-radius: 12px;
    transition: background 0.15s;
}

.wallet-tx-item:hover {
    background: #f1f5f9;
}

.wallet-tx-item__left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.wallet-tx-item__icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.wallet-tx-item__icon--positive {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.wallet-tx-item__icon--negative {
    background: rgba(239, 68, 68, 0.12);
    color: #dc2626;
}

.wallet-tx-item__type {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-text-primary, #0f172a);
}

.wallet-tx-item__desc {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}

.wallet-tx-item__date {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 2px;
}

.wallet-tx-item__right {
    text-align: right;
}

.wallet-tx-item__amount {
    font-size: 16px;
    font-weight: 700;
}

.wallet-tx-item__amount--positive {
    color: #059669;
}

.wallet-tx-item__amount--negative {
    color: var(--color-text-primary, #0f172a);
}

.wallet-tx-item__balance-after {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 3px;
}

.wallet-pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    margin-top: 24px;
}

.wallet-page-link {
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 500;
    border-radius: 8px;
    border: 1px solid var(--color-border, #e2e8f0);
    color: #475569;
    text-decoration: none;
    transition: all 0.15s;
}

.wallet-page-link--active {
    background: #8b5cf6;
    border-color: #8b5cf6;
    color: #ffffff;
}

.wallet-page-link--disabled {
    opacity: 0.5;
    pointer-events: none;
}
</style>
