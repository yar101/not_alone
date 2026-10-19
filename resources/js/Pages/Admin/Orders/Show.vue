<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminModal from '@/Components/Admin/AdminModal.vue';
import AppSelect from '@/Components/AppSelect.vue';
import { ElNotification } from 'element-plus';
import {
    ArrowLeft, ChatDotRound, User, Coin, Clock, Check,
    CircleClose, Warning, Document, EditPen
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    statuses: {
        type: Array,
        required: true,
    },
});

// ─── Status change modal ──────────────────────────────────────────────────────
const showStatusModal = ref(false);
const newStatus = ref(props.order.status);
const adminNote = ref('');
const isSubmitting = ref(false);

const statusSelectOptions = props.statuses.map(s => ({
    value: s.value,
    label: s.label,
}));

function openStatusModal() {
    newStatus.value = props.order.status;
    adminNote.value = '';
    showStatusModal.value = true;
}

function submitStatusChange() {
    if (isSubmitting.value) return;
    isSubmitting.value = true;

    router.patch(
        route('admin.orders.status', props.order.id),
        {
            status: newStatus.value,
            admin_note: adminNote.value || undefined,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showStatusModal.value = false;
                ElNotification.success({
                    title: 'Статус изменен',
                    message: `Заказ #${props.order.id} успешно переведен в новый статус`,
                });
            },
            onError: (err) => {
                ElNotification.error({
                    title: 'Ошибка смены статуса',
                    message: Object.values(err)[0] || 'Не удалось обновить статус заказа',
                });
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        }
    );
}

function getTxTypeLabel(type) {
    const labels = {
        deposit: 'Пополнение',
        withdrawal: 'Вывод',
        order_hold: 'Холдирование',
        order_payout: 'Выплата айдолу',
        order_refund: 'Возврат клиенту',
        order_hold_release: 'Снятие холда',
        platform_fee: 'Комиссия сервиса',
        admin_adjustment: 'Корректировка',
        order_clawback: 'Удержание',
        pack_purchase: 'Покупка пака',
        pack_sale: 'Продажа пака',
    };
    return labels[type] || type;
}
</script>

<template>
    <div class="order-show-page">
        <!-- Page Header -->
        <AdminPageHeader
            :title="`Заказ #${order.id}`"
            :description="`Создан ${order.created_at}`"
        >
            <template #badge>
                <span class="order-status-badge" :style="{ backgroundColor: order.status_color + '22', color: order.status_color, borderColor: order.status_color + '55' }">
                    {{ order.status_label }}
                </span>
            </template>
            <template #actions>
                <div class="header-actions">
                    <Link :href="route('admin.orders.index')" class="btn-back">
                        <el-icon><ArrowLeft /></el-icon>
                        Назад к списку
                    </Link>
                    <button class="btn-change-status" @click="openStatusModal">
                        <el-icon><EditPen /></el-icon>
                        Изменить статус
                    </button>
                </div>
            </template>
        </AdminPageHeader>

        <!-- Main Grid Layout -->
        <div class="order-grid">
            <!-- Left Column: Items, Transactions, History -->
            <div class="order-main-col">
                <!-- Order Items Card -->
                <div class="order-card">
                    <div class="card-header">
                        <h2 class="card-title">Состав заказа</h2>
                        <span class="items-count-badge">{{ order.items.length }} поз.</span>
                    </div>

                    <div class="items-table-wrap">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Услуга</th>
                                    <th>Категория</th>
                                    <th>Ед. времени</th>
                                    <th class="text-right">Цена</th>
                                    <th class="text-center">Кол-во</th>
                                    <th class="text-right">Итого</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in order.items" :key="item.id">
                                    <td>
                                        <div class="item-name">{{ item.title }}</div>
                                        <div v-if="item.options && Object.keys(item.options).length" class="item-options">
                                            <span v-for="(val, key) in item.options" :key="key" class="item-option-pill">
                                                {{ key }}: {{ val }}
                                            </span>
                                        </div>
                                    </td>
                                    <td>{{ item.category_name }}</td>
                                    <td>{{ item.time_unit }}</td>
                                    <td class="text-right font-mono">{{ item.price.toLocaleString('ru') }} ₽</td>
                                    <td class="text-center">{{ item.quantity }}</td>
                                    <td class="text-right font-mono font-bold">{{ item.subtotal.toLocaleString('ru') }} ₽</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5" class="text-right font-bold total-label">Итоговая сумма:</td>
                                    <td class="text-right font-mono font-bold total-value">{{ order.total.toLocaleString('ru') }} ₽</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Financial Transactions Card -->
                <div class="order-card">
                    <div class="card-header">
                        <h2 class="card-title">Финансовые проводки по заказу</h2>
                        <span class="items-count-badge">{{ order.transactions.length }} транз.</span>
                    </div>

                    <div v-if="order.transactions.length === 0" class="empty-hint">
                        Финансовых транзакций по данному заказу пока нет.
                    </div>
                    <div v-else class="tx-table-wrap">
                        <table class="tx-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Пользователь</th>
                                    <th>Тип проводки</th>
                                    <th class="text-right">Сумма</th>
                                    <th class="text-right">Баланс после</th>
                                    <th>Дата</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="tx in order.transactions" :key="tx.id">
                                    <td class="font-mono">
                                        <Link :href="route('admin.transactions.index', { search: tx.id })" class="tx-link">
                                            #{{ tx.id }}
                                        </Link>
                                    </td>
                                    <td>
                                        <Link v-if="tx.user.id" :href="route('admin.users.show', tx.user.id)" class="user-link">
                                            {{ tx.user.name }}
                                        </Link>
                                        <span v-else>—</span>
                                    </td>
                                    <td>
                                        <span class="tx-type-tag">{{ getTxTypeLabel(tx.type) }}</span>
                                    </td>
                                    <td class="text-right font-mono font-bold" :class="tx.amount >= 0 ? 'text-success' : 'text-danger'">
                                        {{ tx.amount > 0 ? '+' : '' }}{{ tx.amount.toLocaleString('ru') }} ₽
                                    </td>
                                    <td class="text-right font-mono">{{ tx.balance_after.toLocaleString('ru') }} ₽</td>
                                    <td class="text-muted text-sm">{{ tx.created_at }}</td>
                                    <td>
                                        <span class="tx-status-pill" :class="'status--' + tx.status">
                                            {{ tx.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Disputes Card (if exists) -->
                <div v-if="order.disputes && order.disputes.length" class="order-card dispute-card">
                    <div class="card-header">
                        <h2 class="card-title text-danger">
                            <el-icon><Warning /></el-icon>
                            Споры по заказу
                        </h2>
                    </div>
                    <div v-for="d in order.disputes" :key="d.id" class="dispute-item">
                        <div class="dispute-top">
                            <span class="dispute-author">Открыл: <strong>{{ d.customer_name }}</strong> ({{ d.created_at }})</span>
                            <span class="dispute-status" :class="'dispute--' + d.status">{{ d.status }}</span>
                        </div>
                        <div class="dispute-reason"><strong>Причина:</strong> {{ d.reason }}</div>
                        <div v-if="d.details" class="dispute-details">{{ d.details }}</div>
                        <div v-if="d.admin_note" class="dispute-admin-note">
                            <strong>Решение администратора:</strong> {{ d.admin_note }}
                        </div>
                    </div>
                </div>

                <!-- Status History Timeline Card -->
                <div class="order-card">
                    <div class="card-header">
                        <h2 class="card-title">История изменения статусов</h2>
                    </div>

                    <div v-if="order.history.length === 0" class="empty-hint">
                        История переходов пока пуста.
                    </div>
                    <div v-else class="timeline">
                        <div v-for="h in order.history" :key="h.id" class="timeline-step">
                            <div class="timeline-dot" :style="{ borderColor: h.to_color }"></div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <span class="timeline-to" :style="{ color: h.to_color }">
                                        {{ h.to }}
                                    </span>
                                    <span v-if="h.from" class="timeline-from text-muted">
                                        (из: {{ h.from }})
                                    </span>
                                    <span class="timeline-date">{{ h.created_at }}</span>
                                </div>
                                <div class="timeline-actor">
                                    Инициатор: <span class="actor-badge">{{ h.actor_type }} #{{ h.actor_id }}</span>
                                </div>
                                <div v-if="h.note" class="timeline-note">
                                    «{{ h.note }}»
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Customer, Idol, Metadata -->
            <div class="order-side-col">
                <!-- Customer Dossier -->
                <div class="order-card">
                    <h3 class="card-subtitle">Заказчик</h3>
                    <div v-if="order.customer" class="user-dossier">
                        <img v-if="order.customer.avatar_url" :src="order.customer.avatar_url" class="dossier-avatar" />
                        <div v-else class="dossier-avatar-placeholder">
                            {{ order.customer.name.charAt(0).toUpperCase() }}
                        </div>
                        <div class="dossier-info">
                            <Link :href="route('admin.users.show', order.customer.id)" class="dossier-name">
                                {{ order.customer.name }}
                            </Link>
                            <div class="dossier-email">{{ order.customer.email }}</div>
                            <div class="dossier-badges">
                                <span v-if="order.customer.is_banned" class="badge-danger">Заблокирован</span>
                                <span v-if="order.customer.is_deleted" class="badge-muted">Удален</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="empty-hint">Заказчик удален</div>
                </div>

                <!-- Idol Dossier -->
                <div class="order-card">
                    <h3 class="card-subtitle">Исполнитель (Айдол)</h3>
                    <div v-if="order.idol" class="user-dossier">
                        <img v-if="order.idol.avatar_url" :src="order.idol.avatar_url" class="dossier-avatar" />
                        <div v-else class="dossier-avatar-placeholder">
                            {{ order.idol.name.charAt(0).toUpperCase() }}
                        </div>
                        <div class="dossier-info">
                            <Link :href="route('admin.users.show', order.idol.id)" class="dossier-name">
                                {{ order.idol.name }}
                            </Link>
                            <div class="dossier-email">{{ order.idol.email }}</div>
                            <div class="dossier-rating">
                                ⭐ {{ order.idol.rating ?? '0.00' }}
                            </div>
                            <div class="dossier-badges">
                                <span v-if="order.idol.is_banned" class="badge-danger">Заблокирован</span>
                                <span v-if="order.idol.is_deleted" class="badge-muted">Удален</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="empty-hint">Исполнитель удален</div>
                </div>

                <!-- Communication -->
                <div class="order-card" v-if="order.conversation_id">
                    <h3 class="card-subtitle">Коммуникация</h3>
                    <p class="text-sm text-muted">Заказ привязан к личному диалогу между клиентом и айдолом.</p>
                    <Link :href="route('admin.conversations.index', { id: order.conversation_id, order_id: order.id })" class="btn-chat-link">
                        <el-icon><ChatDotRound /></el-icon>
                        Перейти к переписке
                    </Link>
                </div>

                <!-- Order Milestones / Timestamps -->
                <div class="order-card">
                    <h3 class="card-subtitle">Временные метки</h3>
                    <div class="milestones-list">
                        <div class="milestone-row">
                            <span class="milestone-label">Создан:</span>
                            <span class="milestone-val">{{ order.created_at }}</span>
                        </div>
                        <div class="milestone-row" v-if="order.paid_at">
                            <span class="milestone-label">Оплачен:</span>
                            <span class="milestone-val">{{ order.paid_at }}</span>
                        </div>
                        <div class="milestone-row" v-if="order.completed_at">
                            <span class="milestone-label">Завершен:</span>
                            <span class="milestone-val">{{ order.completed_at }}</span>
                        </div>
                        <div class="milestone-row" v-if="order.cancel_reason">
                            <span class="milestone-label text-danger">Причина отмены:</span>
                            <span class="milestone-val text-danger">{{ order.cancel_reason }}</span>
                        </div>
                        <div class="milestone-row" v-if="order.cancelled_by">
                            <span class="milestone-label">Отменил:</span>
                            <span class="milestone-val">{{ order.cancelled_by.name }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Change Modal -->
        <AdminModal
            :show="showStatusModal"
            title="Изменение статуса заказа"
            @close="showStatusModal = false"
        >
            <div class="status-modal-body">
                <p class="modal-notice">
                    Внимание: перевод статуса заказа запускает бизнес-логику платформы (холдирование, начисление выплат айдолу или возврат клиенту).
                </p>

                <div class="form-group">
                    <label class="form-label">Новый статус</label>
                    <AppSelect
                        v-model="newStatus"
                        :options="statusSelectOptions"
                        class="w-full"
                    />
                </div>

                <div class="form-group">
                    <label class="form-label">Примечание администратора (причина)</label>
                    <textarea
                        v-model="adminNote"
                        class="form-textarea"
                        rows="3"
                        placeholder="Укажите комментарий для аудита..."
                    ></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" @click="showStatusModal = false">
                        Отмена
                    </button>
                    <button type="button" class="btn-confirm" :disabled="isSubmitting" @click="submitStatusChange">
                        {{ isSubmitting ? 'Сохранение...' : 'Применить статус' }}
                    </button>
                </div>
            </div>
        </AdminModal>
    </div>
</template>

<style scoped>
.order-show-page {
    padding: 1.5rem;
}

.order-status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 9999px;
    border: 1px solid;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 0.9rem;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-back:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.btn-change-status {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 1rem;
    border-radius: 8px;
    background: linear-gradient(135deg, #ec4899, #8b5cf6);
    color: #fff;
    border: none;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: opacity 0.2s;
}
.btn-change-status:hover {
    opacity: 0.9;
}

.order-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.25rem;
    margin-top: 1.25rem;
}
@media (max-width: 1024px) {
    .order-grid {
        grid-template-columns: 1fr;
    }
}

.order-main-col, .order-side-col {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.order-card {
    background: #18181b;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 1.25rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.card-title {
    font-size: 1.05rem;
    font-weight: 600;
    color: #f4f4f5;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-subtitle {
    font-size: 0.9rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0 0 0.85rem 0;
}

.items-count-badge {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.6);
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
}

.items-table-wrap, .tx-table-wrap {
    overflow-x: auto;
}

.items-table, .tx-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
}

.items-table th, .tx-table th {
    text-align: left;
    padding: 0.65rem 0.75rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.5);
    font-weight: 500;
}

.items-table td, .tx-table td {
    padding: 0.75rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    color: #e4e4e7;
}

.items-table tfoot td {
    padding: 1rem 0.75rem 0.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    border-bottom: none;
}

.item-name {
    font-weight: 500;
    color: #fff;
}

.item-options {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
    margin-top: 0.25rem;
}

.item-option-pill {
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.6);
    border-radius: 4px;
    padding: 0.1rem 0.4rem;
    font-size: 0.72rem;
}

.total-label {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.8);
}

.total-value {
    font-size: 1.25rem;
    color: #ec4899;
}

.tx-link, .user-link {
    color: #38bdf8;
    text-decoration: none;
}
.tx-link:hover, .user-link:hover {
    text-decoration: underline;
}

.tx-type-tag {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    padding: 0.2rem 0.5rem;
    font-size: 0.75rem;
}

.tx-status-pill {
    font-size: 0.75rem;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    text-transform: capitalize;
}
.status--completed { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
.status--pending { background: rgba(234, 179, 8, 0.15); color: #facc15; }
.status--failed, .status--cancelled { background: rgba(239, 68, 68, 0.15); color: #f87171; }

.timeline {
    position: relative;
    padding-left: 1.5rem;
}
.timeline::before {
    content: '';
    position: absolute;
    top: 0.5rem;
    bottom: 0.5rem;
    left: 0.45rem;
    width: 2px;
    background: rgba(255, 255, 255, 0.1);
}

.timeline-step {
    position: relative;
    margin-bottom: 1.25rem;
}
.timeline-step:last-child {
    margin-bottom: 0;
}

.timeline-dot {
    position: absolute;
    left: -1.5rem;
    top: 0.25rem;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #18181b;
    border: 2px solid;
}

.timeline-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    font-size: 0.88rem;
}

.timeline-to {
    font-weight: 600;
}

.timeline-date {
    margin-left: auto;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
}

.timeline-actor {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 0.2rem;
}

.actor-badge {
    background: rgba(255, 255, 255, 0.06);
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    color: rgba(255, 255, 255, 0.8);
}

.timeline-note {
    font-size: 0.82rem;
    color: #e4e4e7;
    margin-top: 0.35rem;
    background: rgba(255, 255, 255, 0.03);
    border-left: 2px solid rgba(255, 255, 255, 0.2);
    padding: 0.35rem 0.6rem;
    border-radius: 0 4px 4px 0;
}

.user-dossier {
    display: flex;
    gap: 0.85rem;
    align-items: center;
}

.dossier-avatar, .dossier-avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
}

.dossier-avatar-placeholder {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.1rem;
}

.dossier-name {
    font-weight: 600;
    color: #f4f4f5;
    text-decoration: none;
    font-size: 0.95rem;
}
.dossier-name:hover {
    color: #38bdf8;
}

.dossier-email {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 0.1rem;
}

.dossier-rating {
    font-size: 0.8rem;
    color: #fbbf24;
    font-weight: 600;
    margin-top: 0.15rem;
}

.dossier-badges {
    display: flex;
    gap: 0.35rem;
    margin-top: 0.35rem;
}

.badge-danger {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    font-size: 0.7rem;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
}

.badge-muted {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.7rem;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
}

.btn-chat-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.75rem;
    padding: 0.5rem 0.85rem;
    background: rgba(56, 189, 248, 0.15);
    border: 1px solid rgba(56, 189, 248, 0.3);
    color: #38bdf8;
    border-radius: 8px;
    font-size: 0.85rem;
    text-decoration: none;
    transition: background 0.2s;
}
.btn-chat-link:hover {
    background: rgba(56, 189, 248, 0.25);
}

.milestones-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    font-size: 0.85rem;
}

.milestone-row {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
}

.milestone-label {
    color: rgba(255, 255, 255, 0.5);
}

.milestone-val {
    color: #f4f4f5;
    font-weight: 500;
}

.empty-hint {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.85rem;
    font-style: italic;
    padding: 0.5rem 0;
}

.status-modal-body {
    padding: 0.5rem 0;
}

.modal-notice {
    font-size: 0.82rem;
    color: #fbbf24;
    background: rgba(234, 179, 8, 0.1);
    border: 1px solid rgba(234, 179, 8, 0.2);
    border-radius: 6px;
    padding: 0.6rem 0.75rem;
    margin-bottom: 1.25rem;
}

.form-group {
    margin-bottom: 1.15rem;
}

.form-label {
    display: block;
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 0.4rem;
}

.form-textarea {
    width: 100%;
    background: #09090b;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    padding: 0.6rem 0.75rem;
    color: #fff;
    font-size: 0.88rem;
    box-sizing: border-box;
    font-family: inherit;
}
.form-textarea:focus {
    outline: none;
    border-color: #8b5cf6;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1.5rem;
}

.btn-cancel {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.8);
    border: none;
    cursor: pointer;
}

.btn-confirm {
    padding: 0.5rem 1.25rem;
    border-radius: 8px;
    background: #ec4899;
    color: #fff;
    border: none;
    font-weight: 500;
    cursor: pointer;
}
.btn-confirm:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.text-right { text-align: right; }
.text-center { text-align: center; }
.font-mono { font-family: ui-monospace, monospace; }
.font-bold { font-weight: 600; }
.text-success { color: #4ade80; }
.text-danger { color: #f87171; }
.text-muted { color: rgba(255, 255, 255, 0.5); }
.text-sm { font-size: 0.8rem; }
.w-full { width: 100%; }
</style>
