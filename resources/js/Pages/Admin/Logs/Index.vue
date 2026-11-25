<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminDrawer from '@/Components/Admin/UI/AdminDrawer.vue';

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

const selectedLog = ref(null);

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
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
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
    <div class="space-y-6">
        <!-- Header -->
        <AdminPageHeader
            title="Журнал действий администраторов"
            subtitle="Полная история действий модераторов, изменений настроек, штрафов и финансовых корректировок"
            :count="logs.total"
        >
            <template #actions>
                <a
                    :href="route('admin.export.logs', undefined, false)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900/60 hover:bg-zinc-800 text-zinc-300 text-xs font-medium transition-colors"
                    download="admin_logs.csv"
                >
                    <svg class="w-4 h-4 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                    </svg>
                    <span>Экспорт в CSV</span>
                </a>
            </template>
        </AdminPageHeader>

        <!-- Filters Grid -->
        <div class="bg-zinc-900/60 border border-zinc-800 rounded-xl p-4 shadow-sm space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                <!-- Action -->
                <div>
                    <label class="block text-[11px] font-semibold uppercase text-zinc-400 mb-1">Действие</label>
                    <select
                        v-model="actionFilter"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs text-zinc-200 outline-none focus:border-violet-500"
                        @change="applyFilter"
                    >
                        <option value="">Все действия</option>
                        <option v-for="a in (available_actions || Object.keys(ACTION_LABELS))" :key="a" :value="a">
                            {{ ACTION_LABELS[a] || a }}
                        </option>
                    </select>
                </div>

                <!-- Admin -->
                <div>
                    <label class="block text-[11px] font-semibold uppercase text-zinc-400 mb-1">Администратор</label>
                    <select
                        v-model="adminFilter"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs text-zinc-200 outline-none focus:border-violet-500"
                        @change="applyFilter"
                    >
                        <option value="">Все администраторы</option>
                        <option v-for="admin in admins" :key="admin.id" :value="admin.id">{{ admin.name }}</option>
                    </select>
                </div>

                <!-- Target Type -->
                <div>
                    <label class="block text-[11px] font-semibold uppercase text-zinc-400 mb-1">Тип объекта</label>
                    <select
                        v-model="targetTypeFilter"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs text-zinc-200 outline-none focus:border-violet-500"
                        @change="applyFilter"
                    >
                        <option value="">Все объекты</option>
                        <option v-for="t in (available_target_types || Object.keys(TARGET_TYPE_LABELS))" :key="t" :value="t">
                            {{ TARGET_TYPE_LABELS[t] || t }}
                        </option>
                    </select>
                </div>

                <!-- Date from -->
                <div>
                    <label class="block text-[11px] font-semibold uppercase text-zinc-400 mb-1">Дата от</label>
                    <input
                        v-model="dateFrom"
                        type="date"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs text-zinc-200 outline-none focus:border-violet-500"
                        @change="applyFilter"
                    />
                </div>

                <!-- Date to -->
                <div>
                    <label class="block text-[11px] font-semibold uppercase text-zinc-400 mb-1">Дата до</label>
                    <input
                        v-model="dateTo"
                        type="date"
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs text-zinc-200 outline-none focus:border-violet-500"
                        @change="applyFilter"
                    />
                </div>

                <!-- Search -->
                <div>
                    <label class="block text-[11px] font-semibold uppercase text-zinc-400 mb-1">Поиск</label>
                    <form @submit.prevent="applyFilter" class="flex items-center gap-1">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="ID или ключевое слово..."
                            class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-2.5 py-1.5 text-xs text-zinc-200 outline-none focus:border-violet-500"
                        />
                        <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-500 text-white text-xs font-medium transition-colors">
                            Найти
                        </button>
                    </form>
                </div>
            </div>

            <div class="flex items-center justify-end pt-1">
                <button
                    type="button"
                    @click="resetFilters"
                    class="text-xs text-zinc-400 hover:text-zinc-200 transition-colors"
                >
                    Сбросить все фильтры
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-zinc-900/60 border border-zinc-800 rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-zinc-950/60 text-zinc-400 font-semibold uppercase tracking-wider border-b border-zinc-800">
                        <tr>
                            <th class="px-4 py-3 w-40">Дата и время</th>
                            <th class="px-4 py-3 w-40">Администратор</th>
                            <th class="px-4 py-3 w-52">Действие</th>
                            <th class="px-4 py-3 w-44">Объект</th>
                            <th class="px-4 py-3">Детали</th>
                            <th class="px-4 py-3 text-right w-16"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60">
                        <tr
                            v-for="log in logs.data"
                            :key="log.id"
                            class="hover:bg-zinc-800/20 transition-colors cursor-pointer"
                            @click="selectedLog = log"
                        >
                            <td class="px-4 py-3 font-mono text-zinc-400 whitespace-nowrap">
                                {{ formatDate(log.created_at) }}
                            </td>

                            <td class="px-4 py-3">
                                <AdminBadge variant="secondary" size="sm">
                                    {{ log.admin?.name ?? 'Система' }}
                                </AdminBadge>
                            </td>

                            <td class="px-4 py-3 font-medium text-zinc-200">
                                {{ ACTION_LABELS[log.action] ?? log.action }}
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-zinc-400">{{ TARGET_TYPE_LABELS[log.target_type] || log.target_type || '—' }}</span>
                                    <template v-if="log.target_id">
                                        <Link
                                            v-if="getTargetLink(log.target_type, log.target_id)"
                                            :href="getTargetLink(log.target_type, log.target_id)"
                                            @click.stop
                                            class="text-violet-400 hover:text-violet-300 font-mono text-[11px] underline"
                                            title="Перейти к объекту"
                                        >
                                            #{{ log.target_id }} ↗
                                        </Link>
                                        <span v-else class="text-zinc-500 font-mono text-[11px]">#{{ log.target_id }}</span>
                                    </template>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-zinc-400 max-w-xs truncate" :title="formatDetails(log.details)">
                                {{ formatDetails(log.details) }}
                            </td>

                            <td class="px-4 py-3 text-right">
                                <button
                                    class="text-zinc-500 hover:text-zinc-300 transition-colors p-1"
                                    title="Просмотреть запись"
                                >
                                    →
                                </button>
                            </td>
                        </tr>

                        <tr v-if="!logs.data.length">
                            <td colspan="6" class="px-4 py-12 text-center text-xs text-zinc-500 italic">
                                Записей действий не найдено по заданным фильтрам.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AdminPagination :pagination="logs" />

        <!-- Slide-over Drawer for Log Details -->
        <AdminDrawer
            :open="Boolean(selectedLog)"
            title="Запись журнала аудита"
            :description="`ID записи #${selectedLog?.id}`"
            @close="selectedLog = null"
        >
            <div v-if="selectedLog" class="space-y-5 text-xs">
                <!-- Meta Grid -->
                <div class="grid grid-cols-2 gap-3 p-3.5 bg-zinc-950/50 border border-zinc-800 rounded-lg">
                    <div>
                        <span class="text-zinc-500 block">Дата и время:</span>
                        <span class="font-mono text-zinc-200">{{ formatDate(selectedLog.created_at) }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 block">Администратор:</span>
                        <span class="font-medium text-zinc-200">{{ selectedLog.admin?.name ?? 'Система' }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 block">Действие:</span>
                        <span class="font-semibold text-violet-400">{{ ACTION_LABELS[selectedLog.action] ?? selectedLog.action }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 block">Объект:</span>
                        <span class="font-mono text-zinc-200">
                            {{ TARGET_TYPE_LABELS[selectedLog.target_type] || selectedLog.target_type }} #{{ selectedLog.target_id || '—' }}
                        </span>
                    </div>
                </div>

                <!-- Structured Details -->
                <div>
                    <h4 class="text-xs font-semibold uppercase text-zinc-400 mb-2">Детали операции</h4>
                    <div class="bg-zinc-950 rounded-lg p-3 border border-zinc-800 font-mono text-[11px] text-zinc-300 overflow-x-auto whitespace-pre-wrap leading-relaxed">
                        {{ typeof selectedLog.details === 'object' ? JSON.stringify(selectedLog.details, null, 2) : selectedLog.details }}
                    </div>
                </div>

                <!-- Navigation link if available -->
                <div v-if="getTargetLink(selectedLog.target_type, selectedLog.target_id)" class="pt-2">
                    <Link
                        :href="getTargetLink(selectedLog.target_type, selectedLog.target_id)"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 text-white font-medium transition-colors"
                    >
                        Открыть связанный объект ({{ TARGET_TYPE_LABELS[selectedLog.target_type] || selectedLog.target_type }}) →
                    </Link>
                </div>
            </div>
        </AdminDrawer>
    </div>
</template>
