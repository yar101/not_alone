<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminStatsCard from '@/Components/Admin/AdminStatsCard.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import { formatMoney } from '@/Utils/money';
import {
    List, ScaleToOriginal, Picture, Tickets,
    Coin, Monitor, Document, TrendCharts, User,
    ArrowRight
} from '@element-plus/icons-vue';

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
    return Math.max(8, Math.round((count / maxReg.value) * 100));
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
    return formatMoney(val);
}

const statusVariant = {
    pending:  'warning',
    approved: 'success',
    rejected: 'danger',
};

const statusLabel = {
    pending:  'На рассмотрении',
    approved: 'Одобрена',
    rejected: 'Отклонена',
};

const targetLabel = {
    all:      'Все пользователи',
    user:     'Лично',
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
    <div class="space-y-8 max-w-7xl mx-auto">
        <!-- Page Header -->
        <AdminPageHeader
            title="Панель управления"
            subtitle="Операционные очереди, ключевые финансовые показатели и аудит действий платформы"
        >
            <template #actions>
                <Link
                    :href="route('admin.system-logs.index')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-medium rounded-lg bg-zinc-900 border border-zinc-800 hover:border-zinc-700 text-zinc-300 hover:text-zinc-100 transition-colors shadow-sm"
                >
                    <el-icon :size="14"><Document /></el-icon>
                    <span>Системные логи</span>
                </Link>
                <Link
                    :href="route('admin.logs.index')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-medium rounded-lg bg-violet-600 hover:bg-violet-500 text-white transition-colors shadow-sm shadow-violet-950/30"
                >
                    <el-icon :size="14"><Monitor /></el-icon>
                    <span>Журнал аудита</span>
                </Link>
            </template>
        </AdminPageHeader>

        <!-- ─── 1. Операционные задачи (Action Center) ────────────────────────── -->
        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-zinc-400">
                        Операционные задачи
                    </h2>
                    <p class="text-xs text-zinc-500">Элементы платформы, требующие оперативного решения модераторов</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <AdminStatsCard
                    title="Заявки айдолов"
                    :value="metrics.pending_applications_count"
                    subtitle="Кандидаты на проверку"
                    :badge="metrics.pending_applications_count > 0 ? 'Требует внимания' : 'Чисто'"
                    :badge-type="metrics.pending_applications_count > 0 ? 'warning' : 'success'"
                    :accent="metrics.pending_applications_count > 0 ? 'warning' : 'primary'"
                    :href="route('admin.applications.index', { status: 'pending' })"
                >
                    <template #icon>
                        <el-icon :size="20"><List /></el-icon>
                    </template>
                </AdminStatsCard>

                <AdminStatsCard
                    title="Открытые споры"
                    :value="metrics.open_disputes_count"
                    subtitle="Конфликты по заказам"
                    :badge="metrics.open_disputes_count > 0 ? 'Срочно' : 'Нет'"
                    :badge-type="metrics.open_disputes_count > 0 ? 'danger' : 'success'"
                    :accent="metrics.open_disputes_count > 0 ? 'danger' : 'primary'"
                    :href="route('admin.disputes.index')"
                >
                    <template #icon>
                        <el-icon :size="20"><ScaleToOriginal /></el-icon>
                    </template>
                </AdminStatsCard>

                <AdminStatsCard
                    title="Модерация контента"
                    :value="(metrics.pending_content_packs_count || 0) + (metrics.pending_changes_count || 0)"
                    subtitle="Паки и запросы изменений"
                    :badge="(metrics.pending_content_packs_count + metrics.pending_changes_count) > 0 ? 'В очереди' : 'Чисто'"
                    :badge-type="(metrics.pending_content_packs_count + metrics.pending_changes_count) > 0 ? 'warning' : 'success'"
                    :accent="(metrics.pending_content_packs_count + metrics.pending_changes_count) > 0 ? 'warning' : 'primary'"
                    :href="route('admin.content-packs.index')"
                >
                    <template #icon>
                        <el-icon :size="20"><Picture /></el-icon>
                    </template>
                </AdminStatsCard>

                <AdminStatsCard
                    title="Активные заказы"
                    :value="metrics.active_orders_count"
                    subtitle="Оплачены или выполняются"
                    accent="primary"
                    :href="route('admin.orders.index')"
                >
                    <template #icon>
                        <el-icon :size="20"><Tickets /></el-icon>
                    </template>
                </AdminStatsCard>
            </div>
        </section>

        <!-- ─── 2. Финансовые показатели платформы ────────────────────────────── -->
        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-zinc-400">
                        Финансовые показатели
                    </h2>
                    <p class="text-xs text-zinc-500">Доходы сервиса, оборот кошельков и балансы</p>
                </div>
                <Link
                    :href="route('admin.transactions.index')"
                    class="text-xs font-medium text-violet-400 hover:text-violet-300 transition-colors flex items-center gap-1"
                >
                    <span>Все транзакции</span>
                    <el-icon :size="12"><ArrowRight /></el-icon>
                </Link>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <AdminStatsCard
                    title="Комиссия платформы"
                    :value="formatCurrency(metrics.platform_fee_total)"
                    subtitle="Чистый доход сервиса (12% + 5%)"
                    accent="success"
                    :href="route('admin.transactions.index', { type: 'platform_fee' })"
                >
                    <template #icon>
                        <CoinIcon color="blue" size="sm" />
                    </template>
                </AdminStatsCard>

                <AdminStatsCard
                    title="Оборот выплат"
                    :value="formatCurrency(metrics.turnover_total)"
                    subtitle="Выплаты за заказы и паки"
                    accent="primary"
                    :href="route('admin.transactions.index')"
                >
                    <template #icon>
                        <CoinIcon color="pink" size="sm" />
                    </template>
                </AdminStatsCard>

                <AdminStatsCard
                    title="Пополнения балансов"
                    :value="formatCurrency(metrics.deposits_total)"
                    subtitle="Покупка CoinPack клиентами"
                    accent="primary"
                    :href="route('admin.transactions.index', { type: 'deposit' })"
                >
                    <template #icon>
                        <CoinIcon color="blue" size="sm" />
                    </template>
                </AdminStatsCard>

                <AdminStatsCard
                    title="Доход с донатов"
                    :value="formatCurrency(metrics.donation_income)"
                    :subtitle="`Оборот: ${formatCurrency(metrics.donation_turnover)}`"
                    accent="success"
                    :href="route('admin.transactions.index', { type: 'donation_sent' })"
                >
                    <template #icon>
                        <CoinIcon color="pink" size="sm" />
                    </template>
                </AdminStatsCard>

                <AdminStatsCard
                    title="Пользователи"
                    :value="metrics.total_users"
                    :subtitle="`${metrics.total_idols} верифицированных айдолов`"
                    accent="primary"
                    :href="route('admin.users.index')"
                >
                    <template #icon>
                        <el-icon :size="20"><User /></el-icon>
                    </template>
                </AdminStatsCard>
            </div>
        </section>

        <!-- ─── 3. Две таблицы: Заявки и Рассылки ─────────────────────────────── -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Последние заявки -->
            <div class="bg-zinc-900/50 border border-zinc-800 rounded-xl overflow-hidden shadow-sm flex flex-col">
                <div class="p-4 border-b border-zinc-800 flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-bold text-zinc-100">Последние заявки на айдола</h3>
                        <p class="text-xs text-zinc-500">Кандидаты на статус исполнителя</p>
                    </div>
                    <Link
                        :href="route('admin.applications.index')"
                        class="text-xs font-medium text-violet-400 hover:text-violet-300 transition-colors flex items-center gap-1"
                    >
                        <span>Все заявки</span>
                        <el-icon :size="12"><ArrowRight /></el-icon>
                    </Link>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-900/80 text-zinc-500 uppercase tracking-wider font-semibold border-b border-zinc-800/80">
                            <tr>
                                <th class="py-2.5 px-4">Пользователь</th>
                                <th class="py-2.5 px-4">Статус</th>
                                <th class="py-2.5 px-4 text-right">Дата</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/60">
                            <tr
                                v-for="app in recent_applications"
                                :key="app.id"
                                class="hover:bg-zinc-800/40 transition-colors"
                            >
                                <td class="py-3 px-4">
                                    <Link :href="route('admin.applications.show', app.id)" class="font-medium text-zinc-200 hover:text-violet-400 transition-colors">
                                        {{ app.user?.name ?? 'Пользователь' }}
                                    </Link>
                                    <div class="text-[11px] text-zinc-500">{{ app.user?.email }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <AdminBadge :variant="statusVariant[app.status] || 'neutral'" size="sm">
                                        {{ statusLabel[app.status] ?? app.status }}
                                    </AdminBadge>
                                </td>
                                <td class="py-3 px-4 text-right text-zinc-500 whitespace-nowrap">
                                    {{ formatDate(app.created_at) }}
                                </td>
                            </tr>
                            <tr v-if="!recent_applications?.length">
                                <td colspan="3" class="py-8 text-center text-zinc-500 text-xs">
                                    Новых заявок пока нет
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Последние рассылки -->
            <div class="bg-zinc-900/50 border border-zinc-800 rounded-xl overflow-hidden shadow-sm flex flex-col">
                <div class="p-4 border-b border-zinc-800 flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-bold text-zinc-100">Последние рассылки</h3>
                        <p class="text-xs text-zinc-500">Массовые уведомления пользователям</p>
                    </div>
                    <Link
                        :href="route('admin.messages.index')"
                        class="text-xs font-medium text-violet-400 hover:text-violet-300 transition-colors flex items-center gap-1"
                    >
                        <span>Все рассылки</span>
                        <el-icon :size="12"><ArrowRight /></el-icon>
                    </Link>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-900/80 text-zinc-500 uppercase tracking-wider font-semibold border-b border-zinc-800/80">
                            <tr>
                                <th class="py-2.5 px-4">Заголовок</th>
                                <th class="py-2.5 px-4">Аудитория</th>
                                <th class="py-2.5 px-4 text-right">Дата</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/60">
                            <tr
                                v-for="b in recent_broadcasts"
                                :key="b.id"
                                class="hover:bg-zinc-800/40 transition-colors"
                            >
                                <td class="py-3 px-4 font-medium text-zinc-200">
                                    {{ b.title_text }}
                                </td>
                                <td class="py-3 px-4 text-zinc-400">
                                    {{ targetLabel[b.target] ?? b.target }}
                                </td>
                                <td class="py-3 px-4 text-right text-zinc-500 whitespace-nowrap">
                                    {{ formatDate(b.created_at) }}
                                </td>
                            </tr>
                            <tr v-if="!recent_broadcasts?.length">
                                <td colspan="3" class="py-8 text-center text-zinc-500 text-xs">
                                    Рассылок не отправлялось
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ─── 4. Лента оперативного аудита действий администраторов ─────────── -->
        <div v-if="recent_admin_logs?.length" class="bg-zinc-900/50 border border-zinc-800 rounded-xl overflow-hidden shadow-sm">
            <div class="p-4 border-b border-zinc-800 flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-bold text-zinc-100">Лента действий администраторов</h3>
                    <p class="text-xs text-zinc-500">Оперативный аудит последних изменений в панели управления</p>
                </div>
                <Link
                    :href="route('admin.logs.index')"
                    class="text-xs font-medium text-violet-400 hover:text-violet-300 transition-colors flex items-center gap-1"
                >
                    <span>Полный журнал аудита</span>
                    <el-icon :size="12"><ArrowRight /></el-icon>
                </Link>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-900/80 text-zinc-500 uppercase tracking-wider font-semibold border-b border-zinc-800/80">
                        <tr>
                            <th class="py-2.5 px-4 whitespace-nowrap">Дата / Время</th>
                            <th class="py-2.5 px-4">Администратор</th>
                            <th class="py-2.5 px-4">Действие</th>
                            <th class="py-2.5 px-4">Объект</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60">
                        <tr
                            v-for="log in recent_admin_logs"
                            :key="log.id"
                            class="hover:bg-zinc-800/40 transition-colors"
                        >
                            <td class="py-3 px-4 text-zinc-500 whitespace-nowrap">
                                {{ formatDate(log.created_at) }}
                            </td>
                            <td class="py-3 px-4 font-semibold text-zinc-200">
                                {{ log.admin?.name || 'Система' }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-zinc-300">
                                    {{ ACTION_LABELS[log.action] || log.action }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <template v-if="log.target_type">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-zinc-800 border border-zinc-700 text-zinc-300 mr-2">
                                        {{ TARGET_TYPE_LABELS[log.target_type] || log.target_type }}
                                    </span>
                                    <Link
                                        v-if="getTargetLink(log.target_type, log.target_id)"
                                        :href="getTargetLink(log.target_type, log.target_id)"
                                        class="font-mono text-violet-400 hover:underline"
                                    >
                                        #{{ log.target_id }} ↗
                                    </Link>
                                    <span v-else-if="log.target_id" class="font-mono text-zinc-500">
                                        #{{ log.target_id }}
                                    </span>
                                </template>
                                <span v-else class="text-zinc-600">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ─── 5. Аналитика: График регистраций и Квиз ───────────────────────── -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- График регистраций -->
            <div class="bg-zinc-900/50 border border-zinc-800 rounded-xl p-5 shadow-sm space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-zinc-100">Регистрации за 14 дней</h3>
                    <p class="text-xs text-zinc-500">Динамика притока новых пользователей</p>
                </div>

                <div v-if="registrations_by_day?.length" class="flex items-end gap-2 h-44 pt-4 pb-2 px-1 border-b border-zinc-800">
                    <div
                        v-for="day in registrations_by_day"
                        :key="day.date"
                        class="flex-1 flex flex-col items-center gap-1.5 h-full justify-end group cursor-pointer"
                        :title="`${day.date}: ${day.count} рег.`"
                    >
                        <span class="text-[10px] text-zinc-500 opacity-0 group-hover:opacity-100 transition-opacity">
                            {{ day.count }}
                        </span>
                        <div
                            class="w-full max-w-[20px] rounded-t bg-violet-600/70 group-hover:bg-violet-500 transition-all duration-200"
                            :style="{ height: barHeight(day.count) + '%' }"
                        />
                        <span class="text-[10px] text-zinc-500 font-mono">
                            {{ new Date(day.date).getDate() }}
                        </span>
                    </div>
                </div>
                <div v-else class="py-12 text-center text-xs text-zinc-500">
                    Нет данных за выбранный период
                </div>
            </div>

            <!-- Воронка квиза айдолов -->
            <div v-if="quiz_stats" class="bg-zinc-900/50 border border-zinc-800 rounded-xl p-5 shadow-sm space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-zinc-100">Воронка квиза айдолов</h3>
                    <p class="text-xs text-zinc-500">Статистика прохождения квалификационного тестирования</p>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-zinc-900 border border-zinc-800 p-3 rounded-lg text-center">
                        <div class="text-xl font-bold text-zinc-100">{{ quiz_stats.total }}</div>
                        <div class="text-[11px] text-zinc-500 mt-0.5">Всего сессий</div>
                    </div>
                    <div class="bg-emerald-950/30 border border-emerald-900/50 p-3 rounded-lg text-center">
                        <div class="text-xl font-bold text-emerald-400">{{ quiz_stats.passed }}</div>
                        <div class="text-[11px] text-emerald-500 mt-0.5">Сдали тест</div>
                    </div>
                    <div class="bg-rose-950/30 border border-rose-900/50 p-3 rounded-lg text-center">
                        <div class="text-xl font-bold text-rose-400">{{ quiz_stats.failed }}</div>
                        <div class="text-[11px] text-rose-500 mt-0.5">Провалили</div>
                    </div>
                </div>

                <div class="pt-2">
                    <div class="flex items-center justify-between text-xs text-zinc-400 mb-1.5 font-medium">
                        <span>Процент успешной сдачи</span>
                        <span class="text-emerald-400 font-bold">{{ quiz_stats.pass_rate }}%</span>
                    </div>
                    <div class="w-full bg-zinc-800 h-2.5 rounded-full overflow-hidden">
                        <div
                            class="bg-emerald-500 h-full rounded-full transition-all duration-500"
                            :style="{ width: `${quiz_stats.pass_rate}%` }"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── 6. Топ-5 айдолов по рейтингу ──────────────────────────────────── -->
        <div v-if="top_idols?.length" class="bg-zinc-900/50 border border-zinc-800 rounded-xl overflow-hidden shadow-sm">
            <div class="p-4 border-b border-zinc-800 flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-bold text-zinc-100">Топ-5 айдолов по рейтингу</h3>
                    <p class="text-xs text-zinc-500">Лидеры платформы по качеству и оценкам клиентов</p>
                </div>
                <Link
                    :href="route('admin.users.index', { is_idol: 1 })"
                    class="text-xs font-medium text-violet-400 hover:text-violet-300 transition-colors flex items-center gap-1"
                >
                    <span>Все айдолы</span>
                    <el-icon :size="12"><ArrowRight /></el-icon>
                </Link>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-900/80 text-zinc-500 uppercase tracking-wider font-semibold border-b border-zinc-800/80">
                        <tr>
                            <th class="py-2.5 px-4 w-12 text-center">#</th>
                            <th class="py-2.5 px-4">Айдол</th>
                            <th class="py-2.5 px-4">Рейтинг (0–100)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60">
                        <tr
                            v-for="(idol, i) in top_idols"
                            :key="i"
                            class="hover:bg-zinc-800/40 transition-colors"
                        >
                            <td class="py-3 px-4 text-center font-bold text-zinc-500">
                                {{ i + 1 }}
                            </td>
                            <td class="py-3 px-4 font-semibold text-zinc-100">
                                {{ idol.name }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-32 bg-zinc-800 h-2 rounded-full overflow-hidden">
                                        <div
                                            class="h-full rounded-full"
                                            :class="idol.rating >= 60 ? 'bg-emerald-400' : (idol.rating >= 30 ? 'bg-amber-400' : 'bg-rose-400')"
                                            :style="{ width: `${Math.min(100, Math.max(0, idol.rating))}%` }"
                                        />
                                    </div>
                                    <span class="font-mono font-bold text-zinc-200">{{ Number(idol.rating).toFixed(1) }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
