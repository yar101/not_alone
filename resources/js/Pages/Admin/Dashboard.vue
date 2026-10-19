<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminStatsCard from '@/Components/Admin/AdminStatsCard.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    metrics:               Object,
    recent_applications:   Array,
    recent_broadcasts:     Array,
    recent_admin_logs:     Array,
    registrations_by_day:  Array,
    top_idols:             Array,
    quiz_stats:            Object,
});

const maxReg = computed(() => {
    if (!props.registrations_by_day?.length) return 1;
    return Math.max(1, ...props.registrations_by_day.map(d => d.count));
});

function barHeight(count) {
    return Math.max(4, Math.round((count / maxReg.value) * 100));
}

function formatDate(str) {
    if (!str) return '—';
    return new Date(str).toLocaleString('ru', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatCurrency(val) {
    const num = Number(val) || 0;
    return num.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₽';
}

const statusLabel = {
    pending:  { text: 'На рассмотрении', cls: 'status--pending' },
    approved: { text: 'Одобрена',        cls: 'status--approved' },
    rejected: { text: 'Отклонена',       cls: 'status--rejected' },
};

const targetLabel = {
    all:      'Все',
    user:     'Пользователь',
    filtered: 'По фильтру',
};

const ACTION_LABELS = {
    ban_user: 'Блокировка пользователя',
    unban_user: 'Разблокировка пользователя',
    approve_application: 'Заявка одобрена',
    reject_application: 'Заявка отклонена',
    cooldown_update: 'Кулдаун изменён',
    cooldown_clear: 'Кулдаун сброшен',
    rating_adjust: 'Рейтинг изменён',
    approve_service: 'Услуга одобрена',
    reject_service: 'Услуга отклонена',
    bulk_approve_services: 'Массовое одобрение услуг',
    bulk_reject_services: 'Массовое отклонение услуг',
    update_platform_settings: 'Изменение настроек платформы',
    admin_adjustment: 'Корректировка баланса',
    clear_system_logs: 'Очистка системных логов',
    resolve_dispute: 'Решение по спору заказа',
    resolve_review_dispute: 'Решение по спору отзыва',
    dismiss_report: 'Жалоба отклонена',
    review_report: 'Жалоба рассмотрена',
    strike_create: 'Вынесение страйка',
    strike_cancel: 'Отмена страйка',
    chat_block_create: 'Блокировка в чате',
    chat_block_delete: 'Снятие блокировки в чате',
    content_pack_approve: 'Контент-пак одобрен',
    content_pack_reject: 'Контент-пак отклонен',
    broadcast_send: 'Отправка рассылки',
};

const TARGET_TYPE_LABELS = {
    user: 'Пользователь',
    order: 'Заказ',
    application: 'Заявка',
    idol_application: 'Заявка',
    service: 'Услуга',
    content_pack: 'Контент-пак',
    platform_setting: 'Настройки',
    system_log: 'Системный лог',
    transaction: 'Транзакция',
    wallet_transaction: 'Транзакция',
    report: 'Жалоба',
    dispute: 'Спор',
    review_dispute: 'Спор по отзыву',
    strike: 'Страйк',
    chat_block: 'Блокировка чата',
};

function getTargetLink(type, id) {
    if (!id || !type) return null;
    const t = String(type).toLowerCase();
    try {
        if (t === 'user') return route('admin.users.show', id);
        if (t === 'order') return route('admin.orders.show', id);
        if (t === 'application' || t === 'idol_application') return route('admin.applications.show', id);
        if (t === 'service') return route('admin.services.moderation.show', id);
        if (t === 'content_pack') return route('admin.content-packs.show', id);
        if (t === 'transaction' || t === 'wallet_transaction') return route('admin.transactions.index', { search: id });
        if (t === 'strike' || t === 'user_strike') return route('admin.strikes.index');
        if (t === 'dispute' || t === 'order_dispute') return route('admin.disputes.index');
        if (t === 'chat_block') return route('admin.chat-blocks.index');
    } catch {
        return null;
    }
    return null;
}
</script>

<template>
    <div class="dashboard-page">
        <AdminPageHeader
            title="Панель управления"
            subtitle="Операционные очереди, финансовые показатели и аудит действий платформы"
        >
            <template #actions>
                <Link :href="route('admin.system-logs.index')" class="header-btn header-btn--subtle">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="btn-icon">
                        <path fill-rule="evenodd" d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm3.293 1.293a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 01-1.414-1.414L7.586 10 5.293 7.707a1 1 0 010-1.414zM11 12a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd" />
                    </svg>
                    Системные логи
                </Link>
                <Link :href="route('admin.logs.index')" class="header-btn header-btn--primary">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="btn-icon">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                    </svg>
                    Журнал аудита
                </Link>
            </template>
        </AdminPageHeader>

        <!-- Operational Attention Grid -->
        <div class="section-title-row">
            <h2 class="subheading">Операционные задачи</h2>
            <span class="subheading-hint">Элементы, требующие внимания модераторов</span>
        </div>
        <div class="stats-grid">
            <AdminStatsCard
                title="Заявки айдолов"
                :value="metrics.pending_applications_count"
                subtitle="Ожидают проверки"
                :badge="metrics.pending_applications_count > 0 ? 'Требует внимания' : 'Чисто'"
                :badge-type="metrics.pending_applications_count > 0 ? 'warning' : 'success'"
                :accent="metrics.pending_applications_count > 0 ? 'warning' : 'default'"
                :href="route('admin.applications.index')"
            />
            <AdminStatsCard
                title="Открытые споры"
                :value="metrics.open_disputes_count"
                subtitle="Конфликты по заказам"
                :badge="metrics.open_disputes_count > 0 ? 'Срочно' : 'Нет'"
                :badge-type="metrics.open_disputes_count > 0 ? 'danger' : 'success'"
                :accent="metrics.open_disputes_count > 0 ? 'danger' : 'default'"
                :href="route('admin.disputes.index')"
            />
            <AdminStatsCard
                title="Модерация контента"
                :value="(metrics.pending_content_packs_count || 0) + (metrics.pending_changes_count || 0)"
                subtitle="Паки и запросы изменений"
                :badge="(metrics.pending_content_packs_count + metrics.pending_changes_count) > 0 ? 'В очереди' : 'Чисто'"
                :badge-type="(metrics.pending_content_packs_count + metrics.pending_changes_count) > 0 ? 'warning' : 'success'"
                :accent="(metrics.pending_content_packs_count + metrics.pending_changes_count) > 0 ? 'warning' : 'default'"
                :href="route('admin.content-packs.index')"
            />
            <AdminStatsCard
                title="Активные заказы"
                :value="metrics.active_orders_count"
                subtitle="Оплачены или выполняются"
                accent="info"
                :href="route('admin.orders.index')"
            />
        </div>

        <!-- Financial KPI Grid -->
        <div class="section-title-row" style="margin-top: 2rem;">
            <h2 class="subheading">Финансовые показатели</h2>
            <Link :href="route('admin.transactions.index')" class="subheading-link">Все транзакции →</Link>
        </div>
        <div class="stats-grid">
            <AdminStatsCard
                title="Комиссия платформы"
                :value="formatCurrency(metrics.platform_fee_total)"
                subtitle="Чистая прибыль сервиса"
                accent="success"
                :href="route('admin.transactions.index', { type: 'platform_fee' })"
            />
            <AdminStatsCard
                title="Оборот выплат"
                :value="formatCurrency(metrics.turnover_total)"
                subtitle="Выплаты за заказы и паки"
                accent="primary"
                :href="route('admin.transactions.index')"
            />
            <AdminStatsCard
                title="Пополнения балансов"
                :value="formatCurrency(metrics.deposits_total)"
                subtitle="Ввод средств клиентами"
                accent="primary"
                :href="route('admin.transactions.index', { type: 'deposit' })"
            />
            <AdminStatsCard
                title="Пользователи платформы"
                :value="metrics.total_users"
                :subtitle="`${metrics.total_idols} айдолов`"
                accent="default"
                :href="route('admin.users.index')"
            />
        </div>

        <!-- Two Column Tables: Applications & Broadcasts -->
        <div class="tables-row">
            <!-- Recent applications -->
            <div class="table-card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Последние заявки на айдола</h3>
                        <p class="card-desc">Кандидаты на статус исполнителя</p>
                    </div>
                    <Link :href="route('admin.applications.index')" class="card-link">Все заявки →</Link>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Пользователь</th>
                                <th>Статус</th>
                                <th>Дата</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="app in recent_applications" :key="app.id">
                                <td>
                                    <Link :href="route('admin.applications.show', app.id)" class="item-link">
                                        {{ app.user.name }}
                                    </Link>
                                </td>
                                <td>
                                    <span :class="['status-pill', statusLabel[app.status]?.cls]">
                                        {{ statusLabel[app.status]?.text ?? app.status }}
                                    </span>
                                </td>
                                <td class="muted">{{ formatDate(app.created_at) }}</td>
                            </tr>
                            <tr v-if="!recent_applications?.length">
                                <td colspan="3" class="empty-cell">Заявок нет</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent broadcasts -->
            <div class="table-card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Последние рассылки</h3>
                        <p class="card-desc">Массовые уведомления пользователям</p>
                    </div>
                    <Link :href="route('admin.messages.index')" class="card-link">Все рассылки →</Link>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Заголовок</th>
                                <th>Аудитория</th>
                                <th>Дата</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="b in recent_broadcasts" :key="b.id">
                                <td class="broadcast-cell font-medium">{{ b.title_text }}</td>
                                <td class="muted">{{ targetLabel[b.target] ?? b.target }}</td>
                                <td class="muted">{{ formatDate(b.created_at) }}</td>
                            </tr>
                            <tr v-if="!recent_broadcasts?.length">
                                <td colspan="3" class="empty-cell">Рассылок нет</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Admin Audit Logs Feed -->
        <div class="table-card" style="margin-top: 1.5rem;" v-if="recent_admin_logs?.length">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Лента действий администраторов</h3>
                    <p class="card-desc">Оперативный аудит последних изменений в панели управления</p>
                </div>
                <Link :href="route('admin.logs.index')" class="card-link">Полный журнал аудита →</Link>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Дата / Время</th>
                            <th>Администратор</th>
                            <th>Действие</th>
                            <th>Объект</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in recent_admin_logs" :key="log.id">
                            <td class="muted nowrap">{{ formatDate(log.created_at) }}</td>
                            <td class="font-medium">
                                <span class="admin-badge">{{ log.admin?.name || 'Система' }}</span>
                            </td>
                            <td>
                                <span class="action-tag">
                                    {{ ACTION_LABELS[log.action] || log.action }}
                                </span>
                            </td>
                            <td>
                                <template v-if="log.target_type">
                                    <span class="target-badge">{{ TARGET_TYPE_LABELS[log.target_type] || log.target_type }}</span>
                                    <Link
                                        v-if="getTargetLink(log.target_type, log.target_id)"
                                        :href="getTargetLink(log.target_type, log.target_id)"
                                        class="target-link"
                                    >
                                        #{{ log.target_id }} ↗
                                    </Link>
                                    <span v-else-if="log.target_id" class="target-id">#{{ log.target_id }}</span>
                                </template>
                                <span v-else class="muted">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Analytics sections -->
        <div class="analytics-row" style="margin-top: 1.5rem;">
            <!-- Registrations chart -->
            <div class="table-card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Регистрации за 14 дней</h3>
                        <p class="card-desc">Динамика новых пользователей платформы</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="bar-chart" v-if="registrations_by_day?.length">
                        <div
                            v-for="day in registrations_by_day"
                            :key="day.date"
                            class="bar-col"
                            :title="`${day.date}: ${day.count}`"
                        >
                            <div class="bar-fill" :style="{ height: barHeight(day.count) + '%' }"></div>
                            <div class="bar-label">{{ new Date(day.date).getDate() }}</div>
                        </div>
                    </div>
                    <div v-else class="empty-cell">Нет данных</div>
                </div>
            </div>

            <!-- Quiz funnel -->
            <div class="table-card" v-if="quiz_stats">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Воронка квиза айдолов</h3>
                        <p class="card-desc">Статистика прохождения квалификационного теста</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="quiz-stats">
                        <div class="quiz-stat">
                            <div class="quiz-stat__value">{{ quiz_stats.total }}</div>
                            <div class="quiz-stat__label">Всего сессий</div>
                        </div>
                        <div class="quiz-stat">
                            <div class="quiz-stat__value quiz-stat__value--pass">{{ quiz_stats.passed }}</div>
                            <div class="quiz-stat__label">Пройдено</div>
                        </div>
                        <div class="quiz-stat">
                            <div class="quiz-stat__value quiz-stat__value--fail">{{ quiz_stats.failed }}</div>
                            <div class="quiz-stat__label">Провалено</div>
                        </div>
                    </div>
                    <div class="quiz-rate">
                        <span>Процент успешного прохождения:</span>
                        <strong class="quiz-rate-val">{{ quiz_stats.pass_rate }}%</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top idols -->
        <div class="table-card" v-if="top_idols?.length" style="margin-top: 1.5rem;">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Топ-5 айдолов по рейтингу</h3>
                    <p class="card-desc">Лидеры платформы по удовлетворенности клиентов</p>
                </div>
                <Link :href="route('admin.users.index', { is_idol: 1 })" class="card-link">Все айдолы →</Link>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Айдол</th>
                            <th>Рейтинг</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(idol, i) in top_idols" :key="i">
                            <td class="muted">{{ i + 1 }}</td>
                            <td class="font-medium">{{ idol.name }}</td>
                            <td>
                                <div class="top-rating-row">
                                    <span class="top-rating-val">{{ idol.rating ?? '—' }}</span>
                                    <div class="top-rating-bar">
                                        <div class="top-rating-fill" :style="{ width: (idol.rating ?? 0) + '%' }"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
.dashboard-page {
    display: flex;
    flex-direction: column;
}

.section-title-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 0.85rem;
}

.subheading {
    font-size: 0.95rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
    letter-spacing: 0.02em;
}

.subheading-hint {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
}

.subheading-link {
    font-size: 0.8rem;
    color: #9B6EE8;
    text-decoration: none;
    transition: color 0.15s;
}

.subheading-link:hover {
    color: #B593F5;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.header-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.85rem;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.15s ease;
}

.btn-icon {
    width: 1rem;
    height: 1rem;
}

.header-btn--subtle {
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.header-btn--subtle:hover {
    background: rgba(255, 255, 255, 0.09);
    color: #fff;
}

.header-btn--primary {
    background: linear-gradient(135deg, rgba(155, 110, 232, 0.25), rgba(124, 77, 255, 0.35));
    color: #D6BCFA;
    border: 1px solid rgba(155, 110, 232, 0.4);
}

.header-btn--primary:hover {
    background: linear-gradient(135deg, rgba(155, 110, 232, 0.4), rgba(124, 77, 255, 0.5));
    color: #fff;
}

.tables-row, .analytics-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

.table-card {
    background: #0D0D18;
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 10px;
    overflow: hidden;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1.15rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    background: rgba(255, 255, 255, 0.015);
}

.card-title {
    font-size: 0.88rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
    margin: 0;
}

.card-desc {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.4);
    margin: 0.15rem 0 0;
}

.card-link {
    font-size: 0.78rem;
    color: rgba(155, 110, 232, 0.8);
    text-decoration: none;
    transition: color 0.15s;
    white-space: nowrap;
}

.card-link:hover {
    color: #B593F5;
}

.card-body {
    padding: 1.15rem;
}

.table-wrap {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    text-align: left;
    padding: 0.55rem 1.15rem;
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.35);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 600;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.data-table td {
    padding: 0.65rem 1.15rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.8);
}

.data-table tr:last-child td {
    border-bottom: none;
}

.item-link {
    color: #fff;
    font-weight: 500;
    text-decoration: none;
}

.item-link:hover {
    color: #9B6EE8;
}

.status-pill {
    display: inline-block;
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    white-space: nowrap;
}

.status--pending {
    background: rgba(234, 179, 8, 0.12);
    color: #FACC15;
    border: 1px solid rgba(234, 179, 8, 0.25);
}

.status--approved {
    background: rgba(34, 197, 94, 0.12);
    color: #4ADE80;
    border: 1px solid rgba(34, 197, 94, 0.25);
}

.status--rejected {
    background: rgba(239, 68, 68, 0.12);
    color: #F87171;
    border: 1px solid rgba(239, 68, 68, 0.25);
}

.action-tag {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.85);
}

.admin-badge {
    display: inline-block;
    padding: 0.15rem 0.45rem;
    background: rgba(155, 110, 232, 0.1);
    color: #D6BCFA;
    border-radius: 4px;
    font-size: 0.75rem;
}

.target-badge {
    display: inline-block;
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.45);
    background: rgba(255, 255, 255, 0.05);
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    margin-right: 0.35rem;
}

.target-link {
    color: #9B6EE8;
    text-decoration: none;
    font-size: 0.76rem;
    font-weight: 600;
}

.target-link:hover {
    color: #B593F5;
    text-decoration: underline;
}

.target-id {
    font-size: 0.76rem;
    color: rgba(255, 255, 255, 0.5);
}

.muted {
    color: rgba(255, 255, 255, 0.4);
}

.nowrap {
    white-space: nowrap;
}

.font-medium {
    font-weight: 500;
}

.empty-cell {
    text-align: center;
    padding: 1.5rem !important;
    color: rgba(255, 255, 255, 0.3);
    font-size: 0.8rem;
}

/* Registrations chart */
.bar-chart {
    display: flex;
    align-items: flex-end;
    gap: 0.4rem;
    height: 100px;
    padding-top: 0.5rem;
}

.bar-col {
    flex: 1;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    align-items: center;
    gap: 0.25rem;
}

.bar-fill {
    width: 100%;
    background: linear-gradient(180deg, #9B6EE8 0%, rgba(155, 110, 232, 0.2) 100%);
    border-radius: 3px 3px 0 0;
    min-height: 4px;
    transition: opacity 0.15s;
}

.bar-col:hover .bar-fill {
    opacity: 0.8;
}

.bar-label {
    font-size: 0.65rem;
    color: rgba(255, 255, 255, 0.3);
}

/* Quiz stats */
.quiz-stats {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 0.85rem;
}

.quiz-stat {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.quiz-stat__value {
    font-size: 1.35rem;
    font-weight: 700;
    color: #fff;
    line-height: 1;
}

.quiz-stat__value--pass {
    color: #4ADE80;
}

.quiz-stat__value--fail {
    color: #F87171;
}

.quiz-stat__label {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.4);
}

.quiz-rate {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.quiz-rate-val {
    color: #9B6EE8;
    font-size: 0.95rem;
}

/* Top idols */
.top-rating-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.top-rating-val {
    font-size: 0.82rem;
    font-weight: 600;
    color: #fff;
    min-width: 2rem;
}

.top-rating-bar {
    flex: 1;
    max-width: 180px;
    height: 6px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 9999px;
    overflow: hidden;
}

.top-rating-fill {
    height: 100%;
    background: linear-gradient(90deg, #9B6EE8, #4ADE80);
    border-radius: 9999px;
}

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .tables-row, .analytics-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>
