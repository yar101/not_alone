<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    logs: Object,
    filter: Object,
    admins: Array,
    available_actions: Array,
    available_target_types: Array,
});

const actionFilter = ref(props.filter?.action ?? '');
const adminFilter = ref(props.filter?.admin_id ?? '');
const targetTypeFilter = ref(props.filter?.target_type ?? '');
const dateFrom = ref(props.filter?.date_from ?? '');
const dateTo = ref(props.filter?.date_to ?? '');
const search = ref(props.filter?.search ?? '');

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
    admin_adjustment: 'Корректировка баланса кошелька',
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

function applyFilter() {
    router.get(route('admin.logs.index'), {
        action: actionFilter.value || undefined,
        admin_id: adminFilter.value || undefined,
        target_type: targetTypeFilter.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        search: search.value || undefined,
    }, { preserveState: true, replace: true });
}

function resetFilters() {
    actionFilter.value = '';
    adminFilter.value = '';
    targetTypeFilter.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    search.value = '';
    router.get(route('admin.logs.index'), {}, { preserveState: true, replace: true });
}

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('ru', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatDetails(details) {
    if (!details) return '—';
    if (typeof details === 'string') return details;
    return Object.entries(details)
        .filter(([, v]) => v !== null && v !== undefined)
        .map(([k, v]) => `${k}: ${typeof v === 'object' ? JSON.stringify(v) : v}`)
        .join(', ');
}

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
    } catch {
        return null;
    }
    return null;
}
</script>

<template>
    <div class="admin-logs-page">
        <AdminPageHeader
            title="Журнал действий администраторов"
            subtitle="Полная история действий модераторов, финансовых операций и системных настроек"
            :count="logs.total"
        >
            <template #actions>
                <a :href="route('admin.export.logs')" class="export-btn" download>
                    <svg class="export-btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                    </svg>
                    <span>Экспорт в CSV</span>
                </a>
            </template>
        </AdminPageHeader>

        <!-- Filter Bar -->
        <div class="filters-card">
            <div class="filters-grid">
                <div class="filter-item">
                    <label class="filter-label">Действие</label>
                    <select v-model="actionFilter" class="filter-select" @change="applyFilter">
                        <option value="">Все действия</option>
                        <option v-for="a in (available_actions || Object.keys(ACTION_LABELS))" :key="a" :value="a">
                            {{ ACTION_LABELS[a] || a }}
                        </option>
                    </select>
                </div>

                <div class="filter-item">
                    <label class="filter-label">Администратор</label>
                    <select v-model="adminFilter" class="filter-select" @change="applyFilter">
                        <option value="">Все администраторы</option>
                        <option v-for="admin in admins" :key="admin.id" :value="admin.id">{{ admin.name }}</option>
                    </select>
                </div>

                <div class="filter-item">
                    <label class="filter-label">Тип объекта</label>
                    <select v-model="targetTypeFilter" class="filter-select" @change="applyFilter">
                        <option value="">Все объекты</option>
                        <option v-for="t in (available_target_types || Object.keys(TARGET_TYPE_LABELS))" :key="t" :value="t">
                            {{ TARGET_TYPE_LABELS[t] || t }}
                        </option>
                    </select>
                </div>

                <div class="filter-item">
                    <label class="filter-label">Дата от</label>
                    <input v-model="dateFrom" type="date" class="filter-input" @change="applyFilter" />
                </div>

                <div class="filter-item">
                    <label class="filter-label">Дата до</label>
                    <input v-model="dateTo" type="date" class="filter-input" @change="applyFilter" />
                </div>

                <div class="filter-item search-item">
                    <label class="filter-label">Поиск по ID или деталям</label>
                    <form @submit.prevent="applyFilter" class="search-form">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="ID объекта или ключевые слова..."
                            class="filter-input search-input"
                        />
                        <button type="submit" class="btn btn-search">Найти</button>
                    </form>
                </div>
            </div>

            <div class="filters-footer">
                <button class="btn-reset" @click="resetFilters">Сбросить все фильтры</button>
            </div>
        </div>

        <!-- Table Card -->
        <div class="table-card">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Дата</th>
                            <th style="width: 150px;">Администратор</th>
                            <th style="width: 220px;">Действие</th>
                            <th style="width: 180px;">Объект</th>
                            <th>Детали</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs.data" :key="log.id">
                            <td class="date-cell">{{ formatDate(log.created_at) }}</td>
                            <td class="admin-cell">
                                <span class="admin-badge">{{ log.admin?.name ?? 'Система' }}</span>
                            </td>
                            <td>
                                <span class="action-pill">
                                    {{ ACTION_LABELS[log.action] ?? log.action }}
                                </span>
                            </td>
                            <td>
                                <div class="target-box">
                                    <span class="target-type">{{ TARGET_TYPE_LABELS[log.target_type] || log.target_type || '—' }}</span>
                                    <template v-if="log.target_id">
                                        <Link
                                            v-if="getTargetLink(log.target_type, log.target_id)"
                                            :href="getTargetLink(log.target_type, log.target_id)"
                                            class="target-link"
                                            title="Перейти к объекту"
                                        >
                                            #{{ log.target_id }} ↗
                                        </Link>
                                        <span v-else class="target-id">#{{ log.target_id }}</span>
                                    </template>
                                </div>
                            </td>
                            <td class="details-cell">
                                <span :title="formatDetails(log.details)">{{ formatDetails(log.details) }}</span>
                            </td>
                        </tr>
                        <tr v-if="!logs.data.length">
                            <td colspan="5" class="empty-row">Записей действий не найдено</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AdminPagination :pagination="logs" />
    </div>
</template>

<style scoped>
.admin-logs-page {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* Filters */
.filters-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    padding: 1.15rem 1.25rem;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 1rem;
    align-items: flex-end;
}

.search-item {
    grid-column: span 2;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.filter-label {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255, 255, 255, 0.45);
}

.filter-select,
.filter-input {
    width: 100%;
    padding: 0.45rem 0.65rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.85);
    font-family: inherit;
    font-size: 0.83rem;
    outline: none;
    transition: border-color 0.15s;
}

.filter-select:focus,
.filter-input:focus {
    border-color: #9b6ee8;
}

.filter-select option {
    background: #0d0d18;
}

.search-form {
    display: flex;
    gap: 0.5rem;
}

.search-input {
    flex: 1;
}

.btn-search {
    padding: 0.45rem 0.85rem;
    background: rgba(155, 110, 232, 0.15);
    border: 1px solid rgba(155, 110, 232, 0.35);
    color: #c084fc;
    font-weight: 500;
    font-size: 0.82rem;
    cursor: pointer;
    transition: all 0.15s;
    font-family: inherit;
}

.btn-search:hover {
    background: rgba(155, 110, 232, 0.25);
    color: #fff;
}

.filters-footer {
    margin-top: 0.85rem;
    padding-top: 0.65rem;
    border-top: 1px solid rgba(255, 255, 255, 0.04);
    display: flex;
    justify-content: flex-end;
}

.btn-reset {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.35);
    font-size: 0.78rem;
    cursor: pointer;
    text-decoration: underline;
    text-underline-offset: 3px;
    padding: 0;
}

.btn-reset:hover {
    color: #fff;
}

/* Table */
.table-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    overflow: hidden;
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
    padding: 0.75rem 1.15rem;
    font-size: 0.74rem;
    color: rgba(255, 255, 255, 0.4);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.02);
}

.data-table td {
    padding: 0.85rem 1.15rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.8);
    vertical-align: middle;
}

.data-table tr:hover td {
    background: rgba(255, 255, 255, 0.025);
}

.date-cell {
    color: rgba(255, 255, 255, 0.45);
    font-family: monospace;
    font-size: 0.8rem;
}

.admin-badge {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.85);
}

.action-pill {
    display: inline-block;
    font-size: 0.82rem;
    font-weight: 500;
    color: #c084fc;
}

.target-box {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.target-type {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.5);
}

.target-link {
    font-size: 0.8rem;
    font-weight: 600;
    color: #38bdf8;
    text-decoration: none;
    border-bottom: 1px dashed rgba(56, 189, 248, 0.5);
    transition: color 0.15s;
}

.target-link:hover {
    color: #7dd3fc;
    border-bottom-color: #7dd3fc;
}

.target-id {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    font-family: monospace;
}

.details-cell {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.45);
    max-width: 380px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-family: monospace;
}

.empty-row {
    text-align: center;
    padding: 3rem;
    color: rgba(255, 255, 255, 0.35);
}

.export-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.85rem;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.82rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.15s ease;
}
.export-btn:hover {
    background: rgba(190, 145, 255, 0.1);
    border-color: rgba(190, 145, 255, 0.35);
    color: #fff;
}
.export-btn__icon {
    width: 15px;
    height: 15px;
    stroke: currentColor;
}

@media (max-width: 768px) {
    .search-item {
        grid-column: span 1;
    }
}
</style>
