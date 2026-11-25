<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import AdminTable from '@/Components/Admin/UI/AdminTable.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminInput from '@/Components/Admin/UI/AdminInput.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import { formatMoney } from '@/Utils/money';
import {
    Download, Search, Filter, View, EditPen,
    Clock, Warning, Check, Close, ArrowRight
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    orders:             Object,
    counts:             Object,
    filters:            Object,
    categories:         Array,
    statuses:           Array,
    status_transitions: Object,
});

// ─── Filters ─────────────────────────────────────────────────────────────────
const search      = ref(props.filters?.search      ?? '');
const status      = ref(props.filters?.status      ?? '');
const categoryId  = ref(props.filters?.category_id ?? '');
const dateFrom    = ref(props.filters?.date_from   ?? '');
const dateTo      = ref(props.filters?.date_to     ?? '');

let searchTimer = null;

function applyFilters() {
    router.get(route('admin.orders.index'), {
        search:      search.value      || undefined,
        status:      status.value      || undefined,
        category_id: categoryId.value  || undefined,
        date_from:   dateFrom.value    || undefined,
        date_to:     dateTo.value      || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function onSearchInput(val) {
    search.value = val;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
}

function setStatus(val) {
    status.value = val;
    applyFilters();
}

watch([categoryId, dateFrom, dateTo], applyFilters);

// ─── Status change modal ──────────────────────────────────────────────────────
const modalOrder     = ref(null);
const modalStatus    = ref('');
const modalNote      = ref('');
const modalLoading   = ref(false);
const modalHistory   = ref([]);
const historyLoading  = ref(false);
const statusError    = ref('');
const isHistoryOnly  = ref(false);

const availableStatuses = computed(() => {
    if (!modalOrder.value) return [];
    return props.status_transitions?.[modalOrder.value.status] ?? [];
});

async function openModal(order, historyOnly = false) {
    isHistoryOnly.value = historyOnly;
    modalOrder.value  = order;
    const transitions = props.status_transitions?.[order.status] ?? [];
    modalStatus.value = transitions[0]?.value || '';
    modalNote.value   = '';
    statusError.value = '';
    modalHistory.value = [];
    historyLoading.value = true;

    try {
        const res = await fetch(route('admin.orders.history', order.id));
        modalHistory.value = await res.json();
    } finally {
        historyLoading.value = false;
    }
}

function closeModal() {
    modalOrder.value = null;
    modalHistory.value = [];
    statusError.value = '';
    isHistoryOnly.value = false;
}

function submitStatus() {
    if (!modalOrder.value || modalLoading.value) return;
    modalLoading.value = true;
    statusError.value = '';
    router.patch(
        route('admin.orders.status', modalOrder.value.id),
        { status: modalStatus.value, admin_note: modalNote.value || undefined },
        {
            preserveScroll: true,
            onSuccess: () => { closeModal(); },
            onError: (errors) => {
                statusError.value = Object.values(errors).flat()[0] || 'Произошла ошибка при изменении статуса';
            },
            onFinish: () => { modalLoading.value = false; },
        }
    );
}

const statusVariant = {
    pending:   'warning',
    accepted:  'info',
    paid:      'primary',
    completed: 'info',
    cancelled: 'neutral',
    refunded:  'danger',
    disputed:  'danger',
    closed:    'success',
};

const statusLabelsMap = {
    pending:   'Ожидает',
    accepted:  'Принят',
    paid:      'Оплачен (холд)',
    completed: 'Завершен',
    cancelled: 'Отменен',
    refunded:  'Возвращен',
    disputed:  'Оспаривается',
    closed:    'Закрыт',
};
</script>

<template>
    <div class="space-y-6 max-w-7xl mx-auto">
        <!-- Header -->
        <AdminPageHeader
            title="Заказы"
            subtitle="Управление заказами платформы, эскроу-холдами, историей статусов и арбитражем"
            :count="counts.total"
        >
            <template #actions>
                <a
                    :href="route('admin.export.orders', undefined, false)"
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-medium rounded-lg bg-zinc-900 border border-zinc-800 hover:border-zinc-700 text-zinc-300 hover:text-zinc-100 transition-colors shadow-sm"
                    download="orders.csv"
                >
                    <el-icon :size="14"><Download /></el-icon>
                    <span>Экспорт CSV</span>
                </a>
            </template>
        </AdminPageHeader>

        <!-- Status Filter Tabs -->
        <div class="flex items-center gap-2 border-b border-zinc-800 pb-3 overflow-x-auto">
            <button
                v-for="tab in [
                    { value: '',           label: 'Все заказы',    count: counts.total },
                    { value: 'paid',       label: 'Оплачены',     count: counts.paid },
                    { value: 'completed',  label: 'Завершены',    count: counts.completed },
                    { value: 'disputed',   label: 'Оспариваются', count: counts.disputed ?? 0 },
                    { value: 'closed',     label: 'Закрыты',      count: counts.closed ?? 0 },
                    { value: 'pending',    label: 'Ожидают',      count: counts.pending },
                    { value: 'accepted',   label: 'Приняты',      count: counts.accepted },
                    { value: 'refunded',   label: 'Возвращены',   count: counts.refunded },
                    { value: 'cancelled',  label: 'Отменены',     count: counts.cancelled },
                ]"
                :key="tab.value"
                type="button"
                class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-2 shrink-0"
                :class="[status === tab.value ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60']"
                @click="setStatus(tab.value)"
            >
                <span>{{ tab.label }}</span>
                <span
                    v-if="tab.count > 0"
                    class="text-[11px] font-bold"
                    :class="[status === tab.value ? 'text-white' : (tab.value === 'disputed' ? 'text-rose-400' : 'text-zinc-500')]"
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>

        <!-- Filter Toolbar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 flex-1 flex-wrap">
                <!-- Search input -->
                <div class="w-full sm:w-72">
                    <AdminInput
                        :model-value="search"
                        placeholder="Поиск по номеру #, клиенту, айдолу..."
                        size="md"
                        @update:model-value="onSearchInput"
                    >
                        <template #prefix>
                            <el-icon><Search /></el-icon>
                        </template>
                    </AdminInput>
                </div>

                <!-- Category select -->
                <div class="w-48">
                    <select
                        v-model="categoryId"
                        class="block w-full bg-zinc-900 border border-zinc-800 hover:border-zinc-700 text-zinc-100 rounded-lg text-xs py-2 px-3 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors"
                    >
                        <option value="">Все категории</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                </div>

                <!-- Date pickers -->
                <div class="flex items-center gap-1.5 text-xs text-zinc-400">
                    <input
                        v-model="dateFrom"
                        type="date"
                        class="bg-zinc-900 border border-zinc-800 rounded-lg py-1.5 px-2.5 text-xs text-zinc-200 focus:outline-none focus:border-violet-500"
                        title="Дата с"
                    />
                    <span>—</span>
                    <input
                        v-model="dateTo"
                        type="date"
                        class="bg-zinc-900 border border-zinc-800 rounded-lg py-1.5 px-2.5 text-xs text-zinc-200 focus:outline-none focus:border-violet-500"
                        title="Дата по"
                    />
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <AdminTable :empty="!orders.data?.length" empty-title="Заказов не найдено">
            <template #header>
                <tr>
                    <th class="py-3 px-4">Заказ #</th>
                    <th class="py-3 px-4">Клиент</th>
                    <th class="py-3 px-4">Айдол</th>
                    <th class="py-3 px-4">Услуги</th>
                    <th class="py-3 px-4">Сумма сделки</th>
                    <th class="py-3 px-4">Статус</th>
                    <th class="py-3 px-4">Дата</th>
                    <th class="py-3 px-4 text-right">Действия</th>
                </tr>
            </template>

            <tr
                v-for="order in orders.data"
                :key="order.id"
                class="hover:bg-zinc-800/40 transition-colors cursor-pointer group"
                @click="router.visit(route('admin.orders.show', order.id))"
            >
                <!-- Order ID -->
                <td class="py-3 px-4 font-mono font-bold text-violet-400">
                    <Link
                        :href="route('admin.orders.show', order.id)"
                        class="hover:underline text-violet-400"
                        @click.stop
                    >
                        #{{ order.id }}
                    </Link>
                </td>

                <!-- Client -->
                <td class="py-3 px-4" @click.stop>
                    <div class="flex items-center gap-2" v-if="order.customer && order.customer.name !== '—'">
                        <img
                            v-if="order.customer.avatar_url"
                            :src="order.customer.avatar_url"
                            class="w-7 h-7 rounded-full object-cover shrink-0 border border-zinc-700/60"
                            alt=""
                        />
                        <div v-else class="w-7 h-7 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center font-bold text-[10px] text-zinc-400 shrink-0">
                            {{ (order.customer.name?.[0] || '?').toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <Link :href="route('admin.users.show', order.customer.id)" class="text-xs font-medium text-zinc-200 hover:text-violet-400 truncate block">
                                {{ order.customer.name }}
                            </Link>
                            <div class="text-[10px] text-zinc-500 font-mono truncate">{{ order.customer.email }}</div>
                        </div>
                    </div>
                    <span v-else class="text-zinc-500 text-xs">—</span>
                </td>

                <!-- Idol -->
                <td class="py-3 px-4" @click.stop>
                    <div class="flex items-center gap-2" v-if="order.idol && order.idol.name !== '—'">
                        <img
                            v-if="order.idol.avatar_url"
                            :src="order.idol.avatar_url"
                            class="w-7 h-7 rounded-full object-cover shrink-0 border border-zinc-700/60"
                            alt=""
                        />
                        <div v-else class="w-7 h-7 rounded-full bg-violet-950/60 border border-violet-800/40 flex items-center justify-center font-bold text-[10px] text-violet-300 shrink-0">
                            {{ (order.idol.name?.[0] || '?').toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <Link :href="route('admin.users.show', order.idol.id)" class="text-xs font-medium text-zinc-200 hover:text-violet-400 truncate block">
                                {{ order.idol.name }}
                            </Link>
                            <div class="text-[10px] text-zinc-500 font-mono truncate">{{ order.idol.email }}</div>
                        </div>
                    </div>
                    <span v-else class="text-zinc-500 text-xs">—</span>
                </td>

                <!-- Services summary -->
                <td class="py-3 px-4 text-xs text-zinc-300 max-w-xs truncate">
                    {{ order.services_summary || '—' }}
                </td>

                <!-- Amount -->
                <td class="py-3 px-4 whitespace-nowrap">
                    <div class="flex items-center gap-1.5 font-bold text-zinc-100 text-xs">
                        <span>{{ formatMoney(order.total) }}</span>
                        <CoinIcon color="blue" size="xs" />
                    </div>
                    <div class="text-[10px] text-zinc-500">
                        {{ formatMoney(order.total) }} ₽
                    </div>
                </td>

                <!-- Status -->
                <td class="py-3 px-4 whitespace-nowrap">
                    <AdminBadge :variant="statusVariant[order.status] || 'neutral'" dot size="sm">
                        {{ statusLabelsMap[order.status] ?? order.status }}
                    </AdminBadge>
                </td>

                <!-- Date -->
                <td class="py-3 px-4 text-xs text-zinc-400 whitespace-nowrap">
                    {{ order.created_at_formatted || new Date(order.created_at).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                </td>

                <!-- Actions -->
                <td class="py-3 px-4 text-right whitespace-nowrap" @click.stop>
                    <div class="flex items-center justify-end gap-1.5">
                        <button
                            type="button"
                            class="h-7 w-7 rounded-lg border border-zinc-700 bg-zinc-800/90 text-zinc-300 hover:text-amber-400 hover:border-amber-500/50 hover:bg-zinc-700 transition flex items-center justify-center shrink-0 shadow-sm"
                            title="История изменений статуса"
                            @click="openModal(order, true)"
                        >
                            <el-icon :size="15"><Clock /></el-icon>
                        </button>
                        <AdminButton variant="secondary" size="sm" @click="openModal(order, false)" title="Сменить статус">
                            <template #icon><el-icon :size="12"><EditPen /></el-icon></template>
                            Статус
                        </AdminButton>
                        <AdminButton
                            variant="outline"
                            size="sm"
                            as="Link"
                            :href="route('admin.orders.show', order.id)"
                            title="Открыть карточку заказа"
                        >
                            <template #icon><el-icon :size="13"><View /></el-icon></template>
                            Детали
                        </AdminButton>
                    </div>
                </td>
            </tr>

            <template #footer>
                <AdminPagination :pagination="orders" />
            </template>
        </AdminTable>

        <!-- Status Change & History Modal -->
        <AdminModal
            :show="Boolean(modalOrder)"
            :title="isHistoryOnly ? `История переходов заказа #${modalOrder?.id}` : `Смена статуса заказа #${modalOrder?.id}`"
            @close="closeModal"
        >
            <div class="space-y-4">
                <!-- Change Status Form (if not history only) -->
                <div v-if="!isHistoryOnly" class="space-y-3 p-4 rounded-xl bg-zinc-950 border border-zinc-800">
                    <!-- Current Status Display -->
                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-zinc-900 border border-zinc-800 text-xs">
                        <span class="text-zinc-400">Текущий статус заказа:</span>
                        <AdminBadge :variant="statusVariant[modalOrder.status] || 'neutral'" dot size="sm">
                            {{ statusLabelsMap[modalOrder.status] ?? modalOrder.status }}
                        </AdminBadge>
                    </div>

                    <!-- Available State Machine Transitions -->
                    <template v-if="availableStatuses.length > 0">
                        <div>
                            <label class="block text-xs font-semibold text-zinc-300 mb-1">Допустимый новый статус (State Machine):</label>
                            <select
                                v-model="modalStatus"
                                class="block w-full bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg text-xs py-2.5 px-3 focus:outline-none focus:ring-1 focus:ring-violet-500"
                            >
                                <option v-for="s in availableStatuses" :key="s.value" :value="s.value">
                                    {{ s.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-zinc-300 mb-1">Заметка администратора (причина смены):</label>
                            <textarea
                                v-model="modalNote"
                                rows="2"
                                class="w-full bg-zinc-900 border border-zinc-800 rounded-lg p-2.5 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                                placeholder="Обязательно укажите причину ручной смены статуса..."
                            />
                        </div>

                        <p v-if="statusError" class="text-xs text-rose-400 font-semibold">{{ statusError }}</p>

                        <div class="flex justify-end pt-1">
                            <AdminButton
                                variant="primary"
                                size="sm"
                                :loading="modalLoading"
                                :disabled="!modalStatus"
                                @click="submitStatus"
                            >
                                Применить новый статус
                            </AdminButton>
                        </div>
                    </template>

                    <!-- Terminal State Warning -->
                    <div v-else class="p-3 rounded-lg bg-zinc-900/60 border border-zinc-800 text-xs text-zinc-400 flex items-center gap-2">
                        <el-icon class="text-amber-400 shrink-0"><Warning /></el-icon>
                        <span>Заказ находится в конечном статусе. Дальнейшие переходы по стейт-машине заблокированы.</span>
                    </div>
                </div>

                <!-- History Timeline -->
                <div class="space-y-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                        Хронология переходов статусов
                    </h4>

                    <div v-if="historyLoading" class="p-6 text-center text-xs text-zinc-500">
                        Загрузка истории...
                    </div>

                    <div v-else-if="modalHistory.length" class="space-y-2 max-h-60 overflow-y-auto pr-1">
                        <div
                            v-for="h in modalHistory"
                            :key="h.id || `${h.from}-${h.to}-${h.created_at}`"
                            class="p-2.5 rounded-lg bg-zinc-950 border border-zinc-800 text-xs flex items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-zinc-400 font-medium">{{ h.from || 'Создание' }}</span>
                                    <span class="text-zinc-600">→</span>
                                    <span class="font-bold text-violet-300">{{ h.to }}</span>
                                </div>
                                <div v-if="h.note" class="text-[11px] text-zinc-400 italic mt-1 bg-zinc-900/60 px-2 py-1 rounded border border-zinc-800/80">
                                    «{{ h.note }}»
                                </div>
                            </div>

                            <div class="text-right shrink-0 text-[10px] text-zinc-500">
                                <div class="font-mono text-zinc-400">{{ h.created_at }}</div>
                                <div class="text-zinc-500 font-medium">{{ h.actor_type }}<template v-if="h.actor_id && h.actor_id !== 0"> #{{ h.actor_id }}</template></div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-xs text-zinc-500 py-3 text-center">
                        История переходов пока пуста
                    </div>
                </div>
            </div>

            <template #footer>
                <AdminButton variant="ghost" @click="closeModal">Закрыть</AdminButton>
            </template>
        </AdminModal>
    </div>
</template>
