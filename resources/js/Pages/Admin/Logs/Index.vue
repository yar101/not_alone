<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    logs:   Object,
    filter: Object,
    admins: Array,
});

const actionFilter = ref(props.filter?.action ?? '');
const adminFilter  = ref(props.filter?.admin_id ?? '');

const ACTION_LABELS = {
    ban_user:             'Блокировка',
    unban_user:           'Разблокировка',
    approve_application:  'Заявка одобрена',
    reject_application:   'Заявка отклонена',
    cooldown_update:      'Кулдаун изменён',
    cooldown_clear:       'Кулдаун сброшен',
    rating_adjust:        'Рейтинг изменён',
    approve_service:      'Услуга одобрена',
    reject_service:       'Услуга отклонена',
};

const ALL_ACTIONS = Object.keys(ACTION_LABELS);

function applyFilter() {
    router.get(route('admin.logs.index'), {
        action:   actionFilter.value || undefined,
        admin_id: adminFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('ru', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function formatDetails(details) {
    if (!details) return '—';
    return Object.entries(details)
        .filter(([, v]) => v !== null && v !== undefined)
        .map(([k, v]) => `${k}: ${v}`)
        .join(', ');
}
</script>

<template>
    <div>
        <h1 class="page-title">Логи действий</h1>

        <!-- Filters -->
        <div class="filters">
            <select v-model="actionFilter" class="filter-select" @change="applyFilter">
                <option value="">Все действия</option>
                <option v-for="a in ALL_ACTIONS" :key="a" :value="a">{{ ACTION_LABELS[a] }}</option>
            </select>
            <select v-model="adminFilter" class="filter-select" @change="applyFilter">
                <option value="">Все администраторы</option>
                <option v-for="admin in admins" :key="admin.id" :value="admin.id">{{ admin.name }}</option>
            </select>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Администратор</th>
                        <th>Действие</th>
                        <th>Объект</th>
                        <th>Детали</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs.data" :key="log.id">
                        <td class="muted">{{ formatDate(log.created_at) }}</td>
                        <td>{{ log.admin?.name ?? '—' }}</td>
                        <td>
                            <span class="action-label">{{ ACTION_LABELS[log.action] ?? log.action }}</span>
                        </td>
                        <td class="muted">{{ log.target_type }} #{{ log.target_id }}</td>
                        <td class="details-cell">{{ formatDetails(log.details) }}</td>
                    </tr>
                    <tr v-if="!logs.data.length">
                        <td colspan="5" class="empty-row">Записей нет</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pagination" v-if="logs.last_page > 1">
            <a
                v-for="link in logs.links"
                :key="link.label"
                :href="link.url || '#'"
                class="page-link"
                :class="{ 'page-link--active': link.active, 'page-link--disabled': !link.url }"
                v-html="link.label"
                @click.prevent="link.url && router.get(link.url, {}, { preserveState: true })"
            />
        </div>
    </div>
</template>

<style scoped>
.page-title { font-size: 1.4rem; color: #fff; margin: 0 0 1.5rem; }

.filters { display: flex; gap: 0.75rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
.filter-select {
    padding: 0.45rem 0.7rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.8);
    font-family: inherit;
    font-size: 0.85rem;
    outline: none;
    cursor: pointer;
}
.filter-select:focus { border-color: rgba(155,110,232,0.5); }
.filter-select option { background: #0a0a0f; }

.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
    text-align: left;
    padding: 0.75rem 1.25rem;
    font-size: 0.82rem;
    color: rgba(255,255,255,0.35);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    border-bottom: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.025);
}
.data-table td {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    font-size: 0.93rem;
    color: rgba(255,255,255,0.75);
    vertical-align: middle;
}
.data-table tr:hover td { background: rgba(255,255,255,0.03); }

.muted { color: rgba(255,255,255,0.35); font-size: 0.8rem; }
.action-label { font-size: 0.82rem; color: rgba(190,145,255,0.85); }
.details-cell { font-size: 0.78rem; color: rgba(255,255,255,0.35); max-width: 280px; }
.empty-row { text-align: center; color: rgba(255,255,255,0.25); padding: 2.5rem; }

.pagination { display: flex; gap: 0.25rem; margin-top: 1.5rem; flex-wrap: wrap; }
.page-link { padding: 0.28rem 0.6rem; border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); font-size: 0.8rem; text-decoration: none; cursor: pointer; }
.page-link--active   { border-color: rgba(155,110,232,0.6); color: #9B6EE8; background: rgba(155,110,232,0.1); }
.page-link--disabled { opacity: 0.3; pointer-events: none; }
</style>
