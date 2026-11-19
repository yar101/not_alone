<script setup>
import { ref, computed, onUnmounted } from 'vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import { formatMoney } from '@/Utils/money';
import { ElTooltip, ElIcon } from 'element-plus';
import { BottomLeft, TopRight, Clock, Check, CopyDocument, QuestionFilled, ArrowDown } from '@element-plus/icons-vue';

const props = defineProps({
    transactions: {
        type: Array,
        default: () => [],
    },
});

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
    if (isHoldTx(txOrType)) return false;
    const amount = typeof txOrType === 'object' ? Number(txOrType?.amount || 0) : null;
    if (amount !== null) {
        return amount > 0;
    }
    const type = typeof txOrType === 'object' ? txOrType?.type : txOrType;
    return ['deposit', 'order_payout', 'order_refund', 'pack_sale', 'donation_received', 'blue_from_pink'].includes(type);
}

function isHoldTx(txOrType) {
    if (!txOrType) return false;
    if (typeof txOrType === 'object') {
        if (txOrType.type === 'order_hold') return true;
        if (txOrType.type === 'order_payout' && (txOrType.status === 'pending' || txOrType.metadata?.held)) return true;
        return false;
    }
    return txOrType === 'order_hold';
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
    if (tx.type === 'order_hold_release') return 'Списание из заморозки';
    if (tx.type === 'order_hold_cancelled') return 'Отмена холда';
    if (tx.type === 'deposit') return 'Пополнение';
    if (tx.type === 'order_payout') {
        return 'Оплата за заказ';
    }
    if (tx.type === 'platform_fee') return 'Комиссия';
    if (tx.type === 'order_clawback') return 'Списание по спору';
    if (tx.type === 'pink_to_blue') return 'Обмен: списание розовых';
    if (tx.type === 'blue_from_pink') return 'Обмен: зачисление синих';
    if (tx.type === 'donation_sent') return 'Отправка доната';
    if (tx.type === 'donation_received') return 'Получение доната';
    if (tx.type === 'donation_fee') return 'Комиссия за донат';
    return tx.type_label || 'Операция';
}

function formatTxDescription(desc) {
    if (!desc) return '';
    return desc
        .replace(/\s*\(\d+%\)/g, '')
        .replace(/^Комиссия сервиса/g, 'Комиссия');
}

function getTxExplanation(tx) {
    if (!tx) return '';
    if (tx.type === 'order_hold') return 'Средства за услуги списаны с доступного баланса и заморожены до завершения заказа.';
    if (tx.type === 'customer_service_fee') return 'Сервисный сбор платформы. Не возвращается при отмене заказа.';
    if (tx.type === 'order_hold_release') return 'Завершение заказа: замороженные средства списаны из холда.';
    if (tx.type === 'order_payout') return 'Заработок за выполненный заказ. После окна спора поступит на доступный розовый баланс.';
    if (tx.type === 'platform_fee') return 'Комиссия платформы от суммы заказа.';
    if (tx.type === 'order_hold_cancelled') return 'Средства списаны из холда в связи с отменой или спором по заказу.';
    if (tx.type === 'order_clawback') return 'Списание за заказ, возвращённый клиенту по спору.';
    if (tx.type === 'order_refund') return 'Возврат 100% стоимости услуг по отменённому или оспоренному заказу.';
    if (tx.type === 'donation_sent') return 'Пожертвование любимому айдолу.';
    if (tx.type === 'donation_received') return 'Донат от пользователя. Зачисляется сразу на розовый баланс.';
    if (tx.type === 'donation_fee') return 'Комиссия сервиса за донат.';
    return '';
}

// ── Order Block Grouping ─────────────────────────────────────
function getTxOrderId(tx) {
    const fromReference = Number(tx?.order_id ?? 0);
    if (fromReference > 0) return fromReference;

    const fromMetadata = Number(tx?.metadata?.order_id ?? 0);
    if (fromMetadata > 0) return fromMetadata;

    const fromDescription = /(?:заказу|заказа) #(\d+)/.exec(tx?.description || '');
    if (fromDescription) return Number(fromDescription[1]);

    return null;
}

function pluralize(n, forms) {
    const mod10 = n % 10;
    const mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return forms[0];
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return forms[1];
    return forms[2];
}

function formatShortDate(value) {
    if (!value) return '';
    const d = new Date(value);
    return `${d.getDate()} ${SHORT_MONTHS[d.getMonth()]}`;
}

const groupedTransactions = computed(() => {
    const orders = new Map();
    const singleTxs = [];

    for (const tx of props.transactions) {
        const orderId = getTxOrderId(tx);
        if (orderId === null) {
            singleTxs.push(tx);
            continue;
        }
        if (!orders.has(orderId)) orders.set(orderId, []);
        orders.get(orderId).push(tx);
    }

    const blocks = [];
    for (const [orderId, txs] of orders) {
        const sorted = [...txs].sort((a, b) => a.id - b.id);
        blocks.push({
            key: `order-${orderId}`,
            orderId,
            sortId: sorted[sorted.length - 1].id,
            txs: sorted,
        });
    }
    for (const tx of singleTxs) {
        blocks.push({ key: `tx-${tx.id}`, orderId: null, sortId: tx.id, txs: [tx] });
    }

    return blocks.sort((a, b) => b.sortId - a.sortId);
});

function isBlockGroup(block) {
    return block.orderId !== null && block.txs.length >= 2;
}

function getBlockTag(block) {
    const count = block.txs.length;
    const times = block.txs.map((tx) => new Date(tx.created_at).getTime());
    const firstDate = formatShortDate(Math.min(...times));
    const lastDate = formatShortDate(Math.max(...times));
    const period = firstDate === lastDate ? firstDate : `${firstDate} – ${lastDate}`;
    return `Заказ #${block.orderId} · ${count} ${pluralize(count, ['операция', 'операции', 'операций'])} · ${period}`;
}

function formatSignedAmount(tx) {
    if (!tx) return '0,00';
    if (tx.type === 'order_hold_release' || tx.type === 'order_hold_cancelled') {
        const heldAmt = tx.metadata?.held_amount ?? (Number(tx.held_balance_before) - Number(tx.held_balance_after));
        if (heldAmt) {
            return `−${formatMoney(Math.abs(heldAmt))}`;
        }
    }
    const num = Number(tx.amount || 0);
    const abs = formatMoney(Math.abs(num));
    if (num > 0) return `+${abs}`;
    if (num < 0) return `−${abs}`;
    return abs;
}

function getStatusBadge(tx) {
    const s = tx?.status;
    if (tx?.metadata?.held || (tx?.type === 'order_payout' && s === 'pending')) {
        return { class: 'wallet-status--held', label: 'Холд' };
    }
    if (s === 'completed') return { class: 'wallet-status--completed', label: tx.status_label || 'Завершено' };
    if (s === 'pending') return { class: 'wallet-status--pending', label: tx.status_label || 'В обработке' };
    if (s === 'failed') return { class: 'wallet-status--failed', label: tx.status_label || 'Ошибка' };
    if (s === 'cancelled') return { class: 'wallet-status--cancelled', label: tx.status_label || 'Отменено' };
    return { class: 'wallet-status--completed', label: tx.status_label || 'Завершено' };
}

// ── Transaction Modal ─────────────────────────────────────────
const selectedTx = ref(null);
const showTxModal = ref(false);
const txMoreOpen = ref(false);

function openTxDetails(tx) {
    selectedTx.value = tx;
    txMoreOpen.value = false;
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

onUnmounted(() => {
    if (copyTimer) clearTimeout(copyTimer);
});
</script>

<template>
    <div class="wallet-ledger">
        <div class="wallet-ledger__body">
            <div
                v-for="block in groupedTransactions"
                :key="block.key"
                :class="isBlockGroup(block) ? 'wallet-tx-group' : 'wallet-tx-group--plain'"
            >
                <span v-if="isBlockGroup(block)" class="wallet-tx-group__tag">{{ getBlockTag(block) }}</span>
                <div
                    v-for="tx in block.txs"
                    :key="tx.id"
                    class="wallet-tx-row"
                    @click="openTxDetails(tx)"
                >
                <!-- Col: Operation & Icon -->
                <div class="wallet-col wallet-col--type">
                    <div
                        class="wallet-tx-icon"
                        :class="{
                            'wallet-tx-icon--cyan': isHoldTx(tx),
                            'wallet-tx-icon--emerald': !isHoldTx(tx) && isPositiveTx(tx),
                            'wallet-tx-icon--coral': !isHoldTx(tx) && !isPositiveTx(tx),
                        }"
                    >
                        <i v-if="isHoldTx(tx)" class="fa-solid fa-snowflake"></i>
                        <el-icon v-else-if="isPositiveTx(tx)"><BottomLeft /></el-icon>
                        <el-icon v-else><TopRight /></el-icon>
                    </div>
                    <div class="wallet-tx-title-group">
                        <div class="wallet-tx-title-row">
                            <span class="wallet-tx-title" :title="getTxExplanation(tx) || null">{{ getTxLabel(tx) }}</span>
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
                        class="wallet-tx-amount flex items-center justify-end gap-1"
                        :class="{
                            'wallet-tx-amount--cyan': isHoldTx(tx),
                            'wallet-tx-amount--emerald': !isHoldTx(tx) && isPositiveTx(tx),
                            'wallet-tx-amount--coral': !isHoldTx(tx) && !isPositiveTx(tx),
                        }"
                    >
                        <span>{{ formatSignedAmount(tx) }}</span>
                        <CoinIcon :color="tx.currency === 'pink' ? 'pink' : 'blue'" size="xs" />
                    </div>
                    <div class="wallet-tx-remain flex items-center justify-end gap-1">
                        <template v-if="tx.metadata?.held">
                            <span>В холде:</span>
                            <span style="color: #38bdf8;">{{ formatMoney(tx.held_balance_after) }}</span>
                            <CoinIcon :color="tx.currency === 'pink' ? 'pink' : 'blue'" size="xs" />
                        </template>
                        <template v-else>
                            <span>Остаток:</span>
                            <span>{{ formatMoney(tx.balance_after) }}</span>
                            <CoinIcon :color="tx.currency === 'pink' ? 'pink' : 'blue'" size="xs" />
                        </template>
                    </div>
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
            no-history
            @close="showTxModal = false"
        >
            <div v-if="selectedTx" class="wallet-tx-modal">
                <div class="wallet-tx-modal__header">
                    <div
                        class="wallet-tx-modal__icon"
                        :class="{
                            'wallet-tx-icon--cyan': isHoldTx(selectedTx),
                            'wallet-tx-icon--emerald': !isHoldTx(selectedTx) && isPositiveTx(selectedTx),
                            'wallet-tx-icon--coral': !isHoldTx(selectedTx) && !isPositiveTx(selectedTx),
                        }"
                    >
                        <i v-if="isHoldTx(selectedTx)" class="fa-solid fa-snowflake"></i>
                        <el-icon v-else-if="isPositiveTx(selectedTx)"><BottomLeft /></el-icon>
                        <el-icon v-else><TopRight /></el-icon>
                    </div>
                    <h3 class="wallet-tx-modal__title">{{ getTxLabel(selectedTx) }}</h3>
                    <p v-if="getTxExplanation(selectedTx)" class="wallet-tx-modal__note">
                        {{ getTxExplanation(selectedTx) }}
                    </p>
                    <div
                        class="wallet-tx-modal__amount flex items-center justify-center gap-1.5"
                        :class="{
                            'wallet-tx-amount--cyan': isHoldTx(selectedTx),
                            'wallet-tx-amount--emerald': !isHoldTx(selectedTx) && isPositiveTx(selectedTx),
                            'wallet-tx-amount--coral': !isHoldTx(selectedTx) && !isPositiveTx(selectedTx),
                        }"
                    >
                        <span>{{ formatSignedAmount(selectedTx) }}</span>
                        <CoinIcon :color="selectedTx.currency === 'pink' ? 'pink' : 'blue'" size="sm" />
                    </div>
                    <span class="wallet-tx-status-pill" :class="getStatusBadge(selectedTx).class">
                        <span v-if="selectedTx.status !== 'completed'" class="wallet-tx-status-dot"></span>
                        {{ getStatusBadge(selectedTx).label }}
                    </span>
                </div>

                <div class="wallet-tx-modal__divider"></div>

                <div class="wallet-tx-modal__details">
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
                        <span class="wallet-tx-detail-val wallet-tx-detail-val--accent flex items-center gap-1">
                            <span>{{ formatMoney(selectedTx.balance_after) }}</span>
                            <CoinIcon :color="selectedTx.currency === 'pink' ? 'pink' : 'blue'" size="xs" />
                        </span>
                    </div>
                    <div
                        v-if="selectedTx.held_balance_after !== null && selectedTx.held_balance_after !== undefined && (isHoldTx(selectedTx) || Number(selectedTx.held_balance_after) > 0)"
                        class="wallet-tx-detail-row"
                    >
                        <el-tooltip
                            placement="top"
                            effect="dark"
                            popper-class="wallet-dark-tooltip"
                            trigger="click"
                            :hide-after="0"
                        >
                            <template #content>
                                <div class="wallet-tip-text">
                                    Замороженный баланс после операции
                                </div>
                            </template>
                            <span class="wallet-tx-detail-label wallet-tx-detail-label--tip">
                                В холде
                                <el-icon class="wallet-tx-detail-tip-icon"><QuestionFilled /></el-icon>
                            </span>
                        </el-tooltip>
                        <span class="wallet-tx-detail-val flex items-center gap-1" style="color: #38bdf8;">
                            <span>{{ formatMoney(selectedTx.held_balance_after) }}</span>
                            <CoinIcon :color="selectedTx.currency === 'pink' ? 'pink' : 'blue'" size="xs" />
                        </span>
                    </div>
                    <button
                        type="button"
                        class="wallet-tx-modal__more"
                        :aria-expanded="txMoreOpen"
                        @click="txMoreOpen = !txMoreOpen"
                    >
                        <span>{{ txMoreOpen ? 'Скрыть' : 'Подробнее' }}</span>
                        <el-icon class="wallet-tx-modal__more-icon" :class="{ 'wallet-tx-modal__more-icon--open': txMoreOpen }">
                            <ArrowDown />
                        </el-icon>
                    </button>
                    <div v-show="txMoreOpen" class="wallet-tx-modal__more-panel">
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
                        <div
                            v-if="selectedTx.metadata?.held && selectedTx.metadata?.held_until"
                            class="wallet-tx-detail-row"
                        >
                        <span class="wallet-tx-detail-label">Разморозка</span>
                        <span class="wallet-tx-detail-val" style="color: #38bdf8;">
                            {{ formatFullDate(selectedTx.metadata.held_until) }}
                        </span>
                    </div>
                    <div
                        v-if="selectedTx.metadata?.gross_amount"
                        class="wallet-tx-detail-row"
                    >
                        <span class="wallet-tx-detail-label">Сумма заказа</span>
                        <span class="wallet-tx-detail-val flex items-center gap-1">
                            <span>{{ formatMoney(selectedTx.metadata.gross_amount) }}</span>
                            <CoinIcon :color="selectedTx.currency === 'pink' ? 'pink' : 'blue'" size="xs" />
                        </span>
                    </div>
                    <div
                        v-if="selectedTx.metadata?.fee_amount"
                        class="wallet-tx-detail-row"
                    >
                        <span class="wallet-tx-detail-label">Комиссия сервиса</span>
                        <span class="wallet-tx-detail-val flex items-center gap-1" style="color: #fb7185;">
                            <span>-{{ formatMoney(selectedTx.metadata.fee_amount) }}</span>
                            <CoinIcon :color="selectedTx.currency === 'pink' ? 'pink' : 'blue'" size="xs" />
                        </span>
                    </div>
                    <div
                        v-if="selectedTx.metadata?.reason"
                        class="wallet-tx-detail-row"
                    >
                        <span class="wallet-tx-detail-label">Причина</span>
                        <span class="wallet-tx-detail-val">{{ selectedTx.metadata.reason }}</span>
                    </div>
                    <div
                        v-if="selectedTx.description && selectedTx.description !== getTxLabel(selectedTx)"
                        class="wallet-tx-detail-row wallet-tx-detail-row--desc"
                    >
                        <span class="wallet-tx-detail-label">Назначение</span>
                        <span class="wallet-tx-detail-val">{{ formatTxDescription(selectedTx.description) }}</span>
                    </div>
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
    </div>
</template>

<style scoped>
/* ── Ledger Table ─────────────────────────────────────────── */
.wallet-ledger {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.wallet-ledger__body {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.wallet-tx-row {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) 175px 170px;
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

/* Order Block Group */
.wallet-tx-group {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding: 1.6rem 0.5rem 0.5rem;
    border: 1px solid rgba(255, 178, 239, 0.18);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.02);
}

.wallet-tx-group--plain {
    display: contents;
}

.wallet-tx-group__tag {
    position: absolute;
    top: 0.45rem;
    left: 0.85rem;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    color: rgba(220, 220, 255, 0.75);
}

/* Transaction ID (receipt modal) */
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

.wallet-status--held {
    background: rgba(100, 210, 255, 0.08);
    border: 1px solid rgba(100, 210, 255, 0.22);
    color: var(--color-base-2, #64d2ff);
    border-radius: 6px;
    padding: 0.15rem 0.45rem;
    letter-spacing: 0.04em;
}
.wallet-status--held .wallet-tx-status-dot {
    display: none;
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

/* ── Transaction Receipt Modal ────────────────────────────── */
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

.wallet-tx-modal__note {
    font-size: 0.8rem;
    line-height: 1.4;
    color: rgba(220, 220, 255, 0.7);
    text-align: center;
    margin: 0 0 0.6rem 0;
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

.wallet-tx-modal__more {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    align-self: center;
    background: none;
    border: none;
    padding: 0.2rem 0;
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 178, 239, 0.9);
    cursor: pointer;
    transition: color 0.15s ease;
}

.wallet-tx-modal__more:hover {
    color: #ffffff;
}

.wallet-tx-modal__more-icon {
    transition: transform 0.2s ease;
}

.wallet-tx-modal__more-icon--open {
    transform: rotate(180deg);
}

.wallet-tx-modal__more-panel {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
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
    .wallet-tx-row {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 0.85rem 0.95rem;
        cursor: pointer;
    }

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
</style>
