<script setup>
import { ref, watch, computed } from 'vue';
import { router, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminDrawer from '@/Components/Admin/UI/AdminDrawer.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import { formatMoney } from '@/Utils/money';
import {
    Coin, Plus, Search, Setting, Close, Check, Loading,
    User, Tickets, InfoFilled, ArrowRight, Wallet, Document, Bell,
    Warning, Download
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    transactions: Object,
    filters:      Object,
    typeCounts:   Object,
    kpi:          Object,
    selectedUser: Object,
    fees:         Object,
    types:        Array,
    statuses:     Array,
});

const statusOptions = computed(() => [
    { value: '', label: 'Все статусы' },
    ...(props.statuses ?? []),
]);

const createTypeOptions = [
    { value: 'admin_adjustment', label: 'Корректировка администратора' },
    { value: 'deposit', label: 'Пополнение счёта' },
    { value: 'withdrawal', label: 'Вывод средств' },
];

// ─── Filters State ────────────────────────────────────────────────────────────

const search   = ref(props.filters?.search   ?? '');
const type     = ref(props.filters?.type     ?? '');
const status   = ref(props.filters?.status   ?? '');
const userId   = ref(props.filters?.user_id  ?? '');
const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo   = ref(props.filters?.date_to   ?? '');

let searchTimer = null;

function applyFilters() {
    router.get(route('admin.transactions.index'), {
        user_id:   userId.value   || undefined,
        search:    search.value   || undefined,
        type:      type.value     || undefined,
        status:    status.value   || undefined,
        date_from: dateFrom.value || undefined,
        date_to:   dateTo.value   || undefined,
    }, { preserveState: false, preserveScroll: true });
}

function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
}

function setType(val) {
    type.value = val;
    applyFilters();
}

function clearUserFilter() {
    userId.value = '';
    applyFilters();
}

function resetAllFilters() {
    userId.value = '';
    search.value = '';
    type.value = '';
    status.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
}

const hasActiveFilters = computed(() => {
    return !!(userId.value || search.value || type.value || status.value || dateFrom.value || dateTo.value);
});

watch([status, dateFrom, dateTo], applyFilters);

// ─── Details Drawer ───────────────────────────────────────────────────────────

const selectedTx = ref(null);
const isDrawerOpen = ref(false);

function openDetails(tx) {
    selectedTx.value = tx;
    isDrawerOpen.value = true;
}

function closeDetails() {
    isDrawerOpen.value = false;
}

// ─── Create Transaction Modal ─────────────────────────────────────────────────

const showCreateModal = ref(false);
const userQuery = ref('');
const userSearchResults = ref([]);
const userSearching = ref(false);
const selectedUserForCreate = ref(null);
let userSearchTimer = null;

const amountPresets = [100, 500, 1000, 5000];
const reasonPresets = [
    'Бонус за активность',
    'Компенсация по тикету',
    'Корректировка баланса',
    'Тестовое начисление',
];

const createForm = useForm({
    user_id: '',
    direction: 'credit', // 'credit' | 'debit'
    type: 'admin_adjustment',
    amount: '',
    description: '',
    notify_user: false,
    idempotency_key: '',
});

const filteredCreateTypeOptions = computed(() => {
    if (createForm.direction === 'credit') {
        return [
            { value: 'admin_adjustment', label: 'Корректировка администратора' },
            { value: 'deposit', label: 'Пополнение счёта' },
        ];
    }
    return [
        { value: 'admin_adjustment', label: 'Корректировка администратора' },
        { value: 'withdrawal', label: 'Вывод средств' },
    ];
});

watch(() => createForm.direction, (newDir) => {
    if (newDir === 'credit' && createForm.type === 'withdrawal') {
        createForm.type = 'admin_adjustment';
    } else if (newDir === 'debit' && createForm.type === 'deposit') {
        createForm.type = 'admin_adjustment';
    }
});

const currentSelectedUserBalance = computed(() => {
    return Number(selectedUserForCreate.value?.balance ?? 0);
});

const parsedAmount = computed(() => {
    const val = Number(createForm.amount);
    return isNaN(val) ? 0 : val;
});

const projectedBalance = computed(() => {
    const current = currentSelectedUserBalance.value;
    const amount = parsedAmount.value;
    if (createForm.direction === 'credit') {
        return current + amount;
    } else {
        return Math.max(0, current - amount);
    }
});

const isInsufficientBalance = computed(() => {
    if (!selectedUserForCreate.value) return false;
    if (createForm.direction !== 'debit') return false;
    return parsedAmount.value > currentSelectedUserBalance.value;
});

const submitButtonLabel = computed(() => {
    if (createForm.processing) return 'Выполняем…';
    if (!parsedAmount.value || parsedAmount.value <= 0) return 'Провести операцию';
    const formatted = formatMoney(parsedAmount.value);
    if (createForm.direction === 'credit') {
        return `Начислить ${formatted} 🪙`;
    } else {
        return `Списать ${formatted} 🪙`;
    }
});

const isSubmitDisabled = computed(() => {
    return createForm.processing
        || !createForm.user_id
        || !parsedAmount.value
        || parsedAmount.value <= 0
        || !createForm.description?.trim()
        || isInsufficientBalance.value;
});

function setAmountPreset(val) {
    createForm.amount = val;
}

function setReasonPreset(reason) {
    createForm.description = reason;
}

function openCreateModal() {
    if (props.selectedUser) {
        selectUserForCreate(props.selectedUser);
    } else {
        selectedUserForCreate.value = null;
        createForm.user_id = '';
        userQuery.value = '';
        userSearchResults.value = [];
    }
    createForm.direction = 'credit';
    createForm.type = 'admin_adjustment';
    createForm.amount = '';
    createForm.description = '';
    createForm.notify_user = false;
    createForm.idempotency_key = '';
    createForm.clearErrors();
    showCreateModal.value = true;
}

function closeCreateModal() {
    createForm.idempotency_key = '';
    showCreateModal.value = false;
}

function onUserSearchInput() {
    clearTimeout(userSearchTimer);
    if (!userQuery.value.trim()) {
        userSearchResults.value = [];
        return;
    }
    userSearchTimer = setTimeout(async () => {
        userSearching.value = true;
        try {
            const res = await axios.get(route('admin.users.search'), {
                params: { q: userQuery.value }
            });
            userSearchResults.value = res.data?.data ?? [];
        } catch {
            userSearchResults.value = [];
        } finally {
            userSearching.value = false;
        }
    }, 300);
}

function selectUserForCreate(u) {
    selectedUserForCreate.value = u;
    createForm.user_id = u.id;
    userSearchResults.value = [];
    userQuery.value = '';
}

function deselectUserForCreate() {
    selectedUserForCreate.value = null;
    createForm.user_id = '';
    userQuery.value = '';
    userSearchResults.value = [];
}

function generateUuid() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

function submitCreate() {
    if (isSubmitDisabled.value) return;
    if (!createForm.idempotency_key) {
        createForm.idempotency_key = generateUuid();
    }
    createForm.post(route('admin.transactions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateModal();
        },
    });
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getTypeBadgeVariant(t) {
    switch (t) {
        case 'deposit':
        case 'order_payout':
            return 'success';
        case 'withdrawal':
            return 'warning';
        case 'order_hold':
            return 'primary';
        case 'order_hold_release':
        case 'order_refund':
            return 'info';
        case 'order_hold_cancelled':
        case 'order_clawback':
        case 'pack_purchase':
            return 'danger';
        case 'platform_fee':
        case 'admin_adjustment':
            return 'primary';
        default:
            return 'neutral';
    }
}

function getStatusBadgeVariant(s) {
    switch (s) {
        case 'completed':
            return 'success';
        case 'pending':
            return 'warning';
        case 'failed':
        case 'cancelled':
            return 'danger';
        default:
            return 'neutral';
    }
}

function isCredit(tx) {
    return Number(tx.amount || 0) > 0;
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-100 flex items-center gap-2.5">
                    <el-icon class="text-violet-400"><Coin /></el-icon>
                    <span>Транзакции платформы</span>
                </h1>
                <p class="text-xs text-zinc-400 mt-0.5">
                    Реестр финансовых операций по потребительским и заработанным счетам (BaaS)
                </p>
            </div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <a
                    :href="route('admin.export.transactions', undefined, false)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-xs font-medium text-zinc-300 hover:text-zinc-100 hover:bg-zinc-800 transition"
                    download="transactions.csv"
                >
                    <el-icon><Download /></el-icon>
                    Экспорт CSV
                </a>
                <Link
                    :href="route('admin.settings.index')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-xs font-medium text-zinc-300 hover:text-zinc-100 hover:bg-zinc-800 transition"
                >
                    <el-icon><Setting /></el-icon>
                    <span>Комиссии: ввод {{ fees?.deposit_fee_percent }}% / вывод {{ fees?.withdrawal_fee_percent }}%</span>
                </Link>
                <AdminButton variant="primary" size="sm" @click="openCreateModal">
                    <el-icon class="mr-1.5"><Plus /></el-icon>
                    Создать транзакцию
                </AdminButton>
            </div>
        </div>

        <!-- KPI Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm">
                <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                    <span>Пополнения (депозиты)</span>
                    <el-icon class="text-emerald-400"><Coin /></el-icon>
                </div>
                <div class="flex items-baseline gap-1.5 text-xl font-bold font-mono text-emerald-400">
                    <span>+{{ formatMoney(kpi?.total_deposits ?? 0) }}</span>
                    <CoinIcon color="blue" size="xs" />
                </div>
                <div class="text-[11px] text-zinc-500 mt-1">Ввод на синий баланс клиентов</div>
            </div>

            <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm">
                <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                    <span>Выводы средств (СБП)</span>
                    <el-icon class="text-pink-400"><Wallet /></el-icon>
                </div>
                <div class="flex items-baseline gap-1.5 text-xl font-bold font-mono text-pink-400">
                    <span>−{{ formatMoney(kpi?.total_withdrawals ?? 0) }}</span>
                    <CoinIcon color="pink" size="xs" />
                </div>
                <div class="text-[11px] text-zinc-500 mt-1">Выплаты самозанятым айдолам</div>
            </div>

            <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm">
                <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                    <span>Комиссии платформы</span>
                    <el-icon class="text-violet-400"><Tickets /></el-icon>
                </div>
                <div class="flex items-baseline gap-1.5 text-xl font-bold font-mono text-violet-400">
                    <span>{{ formatMoney(kpi?.total_fees ?? 0) }}</span>
                    <CoinIcon color="blue" size="xs" />
                </div>
                <div class="text-[11px] text-zinc-500 mt-1">Сбор 5% + комиссия с заказов 12%</div>
            </div>

            <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm">
                <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                    <span>Всего проводок</span>
                    <el-icon class="text-zinc-400"><Document /></el-icon>
                </div>
                <div class="text-xl font-bold font-mono text-zinc-100">
                    {{ (kpi?.total_count ?? 0).toLocaleString() }}
                </div>
                <div class="text-[11px] text-zinc-500 mt-1">В главной бухгалтерской книге</div>
            </div>
        </div>

        <!-- Filter Banner by User (if selected) -->
        <div v-if="selectedUser" class="p-3.5 rounded-xl border border-violet-800/40 bg-violet-950/20 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <img
                    v-if="selectedUser.avatar_url"
                    :src="selectedUser.avatar_url"
                    class="w-9 h-9 rounded-full object-cover border border-violet-500/30"
                />
                <div v-else class="w-9 h-9 rounded-full bg-violet-800/40 text-violet-300 flex items-center justify-center font-bold text-xs">
                    {{ selectedUser.name?.charAt(0).toUpperCase() }}
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-zinc-200">{{ selectedUser.name }}</span>
                        <span class="text-xs text-zinc-400 font-mono">({{ selectedUser.email }})</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-zinc-400 mt-0.5">
                        <span>Синий: <strong class="text-zinc-200 font-mono">{{ formatMoney(selectedUser.balance) }} 🪙</strong></span>
                        <span v-if="selectedUser.pink_balance !== undefined" class="text-pink-400">
                            Розовый: <strong class="font-mono">{{ formatMoney(selectedUser.pink_balance) }} 🪙</strong>
                        </span>
                        <span v-if="selectedUser.held_balance > 0" class="text-amber-400">
                            Холд: <strong class="font-mono">{{ formatMoney(selectedUser.held_balance) }} 🪙</strong>
                        </span>
                    </div>
                </div>
            </div>
            <AdminButton variant="outline" size="sm" @click="clearUserFilter">
                <el-icon class="mr-1"><Close /></el-icon>
                Снять фильтр
            </AdminButton>
        </div>

        <!-- Type Filter Chips (Multi-row Wrap without horizontal scroll) -->
        <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <button
                v-for="tab in [
                    { value: '',                   label: 'Все', count: typeCounts?.all ?? 0 },
                    { value: 'deposit',            label: 'Пополнения', count: typeCounts?.deposit ?? 0 },
                    { value: 'withdrawal',         label: 'Выводы', count: typeCounts?.withdrawal ?? 0 },
                    { value: 'order_hold',         label: 'Заморозки', count: typeCounts?.order_hold ?? 0 },
                    { value: 'order_hold_release', label: 'Списания из холда', count: typeCounts?.order_hold_release ?? 0 },
                    { value: 'order_hold_cancelled', label: 'Отмена холда', count: typeCounts?.order_hold_cancelled ?? 0 },
                    { value: 'order_payout',       label: 'Выплаты айдолам', count: typeCounts?.order_payout ?? 0 },
                    { value: 'order_refund',       label: 'Возвраты клиентам', count: typeCounts?.order_refund ?? 0 },
                    { value: 'order_clawback',     label: 'Списания по спорам', count: typeCounts?.order_clawback ?? 0 },
                    { value: 'pack_purchase',      label: 'Покупки паков', count: typeCounts?.pack_purchase ?? 0 },
                    { value: 'pack_sale',          label: 'Продажи паков', count: typeCounts?.pack_sale ?? 0 },
                    { value: 'platform_fee',       label: 'Комиссии', count: typeCounts?.platform_fee ?? 0 },
                    { value: 'admin_adjustment',   label: 'Корректировки', count: typeCounts?.admin_adjustment ?? 0 },
                    { value: 'pink_to_blue',       label: 'Конвертации', count: (typeCounts?.pink_to_blue ?? 0) },
                ]"
                :key="tab.value"
                class="px-2.5 py-1.5 rounded-lg border text-xs font-medium whitespace-nowrap transition flex items-center gap-1.5 shadow-sm"
                :class="type === tab.value
                    ? 'border-admin-primary bg-admin-primary-light text-admin-accent font-semibold'
                    : 'border-admin-subtle bg-admin-card text-zinc-400 hover:text-zinc-200 hover:bg-admin-surface'"
                @click="setType(tab.value)"
            >
                <span>{{ tab.label }}</span>
                <span
                    class="px-1.5 py-0.5 rounded text-[10px] font-mono"
                    :class="type === tab.value ? 'bg-admin-primary text-white' : 'bg-zinc-800 text-zinc-500'"
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>

        <!-- Filter Bar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
            <div class="relative flex-1">
                <el-icon class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500"><Search /></el-icon>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Поиск по ID транзакции, описанию, имени пользователя..."
                    class="w-full h-9 pl-9 pr-8 rounded-lg border border-admin-subtle bg-admin-card text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-admin-primary"
                    @input="onSearchInput"
                />
                <button
                    v-if="search"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-300 text-xs"
                    @click="search = ''; applyFilters();"
                >
                    ✕
                </button>
            </div>

            <select
                v-model="status"
                class="h-9 px-3 rounded-lg border border-admin-subtle bg-admin-card text-xs text-zinc-200 focus:outline-none focus:border-admin-primary"
            >
                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                </option>
            </select>

            <div class="flex items-center gap-1.5">
                <input
                    v-model="dateFrom"
                    type="date"
                    class="h-9 px-2.5 rounded-lg border border-admin-subtle bg-admin-card text-xs text-zinc-200 focus:outline-none focus:border-admin-primary"
                    title="Дата с"
                />
                <span class="text-zinc-600 text-xs">—</span>
                <input
                    v-model="dateTo"
                    type="date"
                    class="h-9 px-2.5 rounded-lg border border-admin-subtle bg-admin-card text-xs text-zinc-200 focus:outline-none focus:border-admin-primary"
                    title="Дата по"
                />
            </div>

            <AdminButton
                v-if="hasActiveFilters"
                variant="ghost"
                size="sm"
                @click="resetAllFilters"
            >
                Сбросить
            </AdminButton>
        </div>

        <!-- Transactions Table -->
        <div class="rounded-xl border border-admin-subtle bg-admin-card overflow-hidden backdrop-blur-sm shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-admin-theme bg-admin-surface/90 text-zinc-400">
                            <th class="py-3 px-4 font-medium">ID</th>
                            <th class="py-3 px-4 font-medium">Пользователь</th>
                            <th class="py-3 px-4 font-medium">Тип проводки</th>
                            <th class="py-3 px-4 font-medium text-right">Сумма</th>
                            <th class="py-3 px-4 font-medium text-right">Баланс (до → после)</th>
                            <th class="py-3 px-4 font-medium">Статус</th>
                            <th class="py-3 px-4 font-medium">Описание</th>
                            <th class="py-3 px-4 font-medium">Дата</th>
                            <th class="py-3 px-4 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                        <tr
                            v-for="tx in transactions.data"
                            :key="tx.id"
                            class="hover:bg-zinc-800/30 transition cursor-pointer"
                            @click="openDetails(tx)"
                        >
                            <td class="py-3 px-4 font-mono text-zinc-400">
                                #{{ tx.id }}
                            </td>
                            <td class="py-3 px-4" @click.stop>
                                <div v-if="tx.user" class="flex items-center gap-2.5">
                                    <img
                                        v-if="tx.user.avatar_url"
                                        :src="tx.user.avatar_url"
                                        class="w-6 h-6 rounded-full object-cover border border-zinc-700"
                                    />
                                    <div v-else class="w-6 h-6 rounded-full bg-zinc-800 text-zinc-400 flex items-center justify-center font-bold text-[10px]">
                                        {{ tx.user.name?.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <Link
                                            :href="route('admin.users.show', tx.user.id)"
                                            class="text-zinc-200 hover:text-violet-400 font-medium truncate block max-w-[140px]"
                                        >
                                            {{ tx.user.name }}
                                        </Link>
                                        <span class="text-[11px] text-zinc-500 truncate block max-w-[140px]">{{ tx.user.email }}</span>
                                    </div>
                                </div>
                                <span v-else class="text-zinc-600">—</span>
                            </td>
                            <td class="py-3 px-4">
                                <AdminBadge :variant="getTypeBadgeVariant(tx.type)" size="sm">
                                    {{ tx.type_label }}
                                </AdminBadge>
                            </td>
                            <td
                                class="py-3 px-4 text-right font-mono font-bold whitespace-nowrap"
                                :class="isCredit(tx) ? 'text-emerald-400' : 'text-rose-400'"
                            >
                                <span class="inline-flex items-center gap-1 justify-end">
                                    <span>{{ isCredit(tx) ? '+' : '' }}{{ formatMoney(tx.amount) }}</span>
                                    <CoinIcon :color="tx.currency === 'pink' ? 'pink' : 'blue'" size="xs" />
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono whitespace-nowrap">
                                <div class="inline-flex items-center gap-1 justify-end text-zinc-400">
                                    <span>{{ formatMoney(tx.balance_before) }}</span>
                                    <span class="text-zinc-600">→</span>
                                    <strong class="text-zinc-200">{{ formatMoney(tx.balance_after) }}</strong>
                                    <CoinIcon :color="tx.currency === 'pink' ? 'pink' : 'blue'" size="xs" />
                                </div>
                                <div v-if="tx.held_balance_before !== tx.held_balance_after" class="text-[10px] text-amber-400/80">
                                    холд: {{ formatMoney(tx.held_balance_before) }} → {{ formatMoney(tx.held_balance_after) }}
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <AdminBadge :variant="getStatusBadgeVariant(tx.status)" size="sm">
                                    {{ tx.status_label }}
                                </AdminBadge>
                            </td>
                            <td class="py-3 px-4 max-w-[180px] truncate text-zinc-400" :title="tx.description">
                                {{ tx.description || '—' }}
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-500 whitespace-nowrap">
                                {{ tx.created_at }}
                            </td>
                            <td class="py-3 px-4 text-right" @click.stop>
                                <button
                                    class="text-violet-400 hover:text-violet-300 font-medium text-xs hover:underline"
                                    @click="openDetails(tx)"
                                >
                                    Детали
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!transactions.data || !transactions.data.length">
                            <td colspan="9" class="py-12 text-center text-zinc-500">
                                <el-icon :size="24" class="mx-auto mb-2 text-zinc-600"><Coin /></el-icon>
                                <div>Транзакций не найдено</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4 border-t border-zinc-800">
                <AdminPagination :pagination="transactions" />
            </div>
        </div>

        <!-- ════════════ DRAWER: TRANSACTION DETAILS ════════════ -->
        <AdminDrawer
            :show="isDrawerOpen"
            :title="`Транзакция #${selectedTx?.id || ''}`"
            :subtitle="selectedTx ? `Создана ${selectedTx.created_at}` : ''"
            width="540px"
            @close="closeDetails"
        >
            <div v-if="selectedTx" class="space-y-5 text-xs">
                <!-- Summary Card -->
                <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 space-y-3">
                    <div class="flex items-center justify-between">
                        <AdminBadge :variant="getTypeBadgeVariant(selectedTx.type)" size="md">
                            {{ selectedTx.type_label }}
                        </AdminBadge>
                        <AdminBadge :variant="getStatusBadgeVariant(selectedTx.status)" size="md">
                            {{ selectedTx.status_label }}
                        </AdminBadge>
                    </div>

                    <div class="flex items-baseline justify-between pt-2 border-t border-zinc-800">
                        <span class="text-zinc-400">Сумма операции:</span>
                        <div
                            class="text-lg font-bold font-mono inline-flex items-center gap-1.5"
                            :class="isCredit(selectedTx) ? 'text-emerald-400' : 'text-rose-400'"
                        >
                            <span>{{ isCredit(selectedTx) ? '+' : '' }}{{ formatMoney(selectedTx.amount) }}</span>
                            <CoinIcon :color="selectedTx.currency === 'pink' ? 'pink' : 'blue'" size="xs" />
                            <span class="text-xs font-normal text-zinc-500">({{ formatMoney(selectedTx.amount) }} ₽)</span>
                        </div>
                    </div>
                </div>

                <!-- User Info -->
                <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 space-y-2">
                    <div class="text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">Владелец счёта</div>
                    <div v-if="selectedTx.user" class="flex items-center gap-3">
                        <img
                            v-if="selectedTx.user.avatar_url"
                            :src="selectedTx.user.avatar_url"
                            class="w-8 h-8 rounded-full object-cover border border-zinc-700"
                        />
                        <div v-else class="w-8 h-8 rounded-full bg-zinc-800 text-zinc-300 flex items-center justify-center font-bold text-xs">
                            {{ selectedTx.user.name?.charAt(0).toUpperCase() }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <Link
                                :href="route('admin.users.show', selectedTx.user.id)"
                                class="text-zinc-200 hover:text-violet-400 font-semibold truncate block"
                            >
                                {{ selectedTx.user.name }} ↗
                            </Link>
                            <span class="text-zinc-400 truncate block">{{ selectedTx.user.email }}</span>
                        </div>
                    </div>
                    <div v-else class="text-zinc-500">Пользователь удален или системная операция</div>
                </div>

                <!-- Ledger Balance Details -->
                <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 space-y-2.5">
                    <div class="text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">Изменение баланса</div>

                    <div class="flex items-center justify-between text-zinc-300">
                        <span class="text-zinc-400">Основной баланс:</span>
                        <div class="font-mono inline-flex items-center gap-1.5">
                            <span class="text-zinc-400">{{ formatMoney(selectedTx.balance_before) }}</span>
                            <span class="text-zinc-600">→</span>
                            <strong class="text-white">{{ formatMoney(selectedTx.balance_after) }}</strong>
                            <CoinIcon :color="selectedTx.currency === 'pink' ? 'pink' : 'blue'" size="xs" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-zinc-300">
                        <span class="text-zinc-400">Замороженный баланс:</span>
                        <div class="font-mono inline-flex items-center gap-1.5">
                            <span class="text-zinc-400">{{ formatMoney(selectedTx.held_balance_before) }}</span>
                            <span class="text-zinc-600">→</span>
                            <strong class="text-amber-300">{{ formatMoney(selectedTx.held_balance_after) }}</strong>
                            <CoinIcon :color="selectedTx.currency === 'pink' ? 'pink' : 'blue'" size="xs" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-zinc-300 pt-2 border-t border-zinc-800/60">
                        <span class="text-zinc-400">ID кошелька:</span>
                        <span class="font-mono text-zinc-300">#{{ selectedTx.wallet_id }}</span>
                    </div>
                </div>

                <!-- Description & Reference -->
                <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 space-y-2">
                    <div class="text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">Назначение платежа</div>
                    <p class="text-zinc-200 leading-relaxed">{{ selectedTx.description || '—' }}</p>

                    <div v-if="selectedTx.reference_type" class="pt-2 border-t border-zinc-800 flex items-center justify-between">
                        <span class="text-zinc-400">Связанный объект:</span>
                        <span class="px-2 py-0.5 rounded bg-zinc-800 text-violet-300 font-mono text-[11px]">
                            {{ selectedTx.reference_type }} #{{ selectedTx.reference_id }}
                        </span>
                    </div>
                </div>

                <!-- Metadata / Idempotency Key -->
                <div v-if="selectedTx.idempotency_key" class="p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 space-y-1.5">
                    <div class="text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">Ключ идемпотентности</div>
                    <div class="p-2 rounded bg-zinc-900 border border-zinc-800 font-mono text-[11px] text-zinc-400 break-all select-all">
                        {{ selectedTx.idempotency_key }}
                    </div>
                </div>

                <!-- Raw JSON Metadata -->
                <div v-if="selectedTx.metadata && Object.keys(selectedTx.metadata).length" class="p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 space-y-2">
                    <div class="text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">Метаданные Mandarin BaaS</div>
                    <pre class="p-3 rounded-lg bg-zinc-900 border border-zinc-800 font-mono text-[11px] text-zinc-300 overflow-x-auto">{{ JSON.stringify(selectedTx.metadata, null, 2) }}</pre>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end">
                    <AdminButton variant="outline" size="sm" @click="closeDetails">
                        Закрыть
                    </AdminButton>
                </div>
            </template>
        </AdminDrawer>

        <!-- ════════════ MODAL: CREATE MANUAL TRANSACTION ════════════ -->
        <AdminModal
            :show="showCreateModal"
            title="Создать транзакцию вручную"
            @close="closeCreateModal"
        >
            <form @submit.prevent="submitCreate" class="space-y-4">
                <!-- User Picker -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-medium text-zinc-300">
                        Пользователь <span class="text-rose-400">*</span>
                    </label>

                    <div v-if="selectedUserForCreate" class="p-3 rounded-lg border border-violet-800/40 bg-violet-950/20 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <img
                                v-if="selectedUserForCreate.avatar_url"
                                :src="selectedUserForCreate.avatar_url"
                                class="w-8 h-8 rounded-full object-cover border border-violet-500/30"
                            />
                            <div v-else class="w-8 h-8 rounded-full bg-violet-800 text-violet-300 flex items-center justify-center font-bold text-xs">
                                {{ selectedUserForCreate.name?.charAt(0).toUpperCase() }}
                            </div>
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <span class="font-semibold text-xs text-white">{{ selectedUserForCreate.name }}</span>
                                    <AdminBadge v-if="selectedUserForCreate.is_idol" variant="primary" size="sm">Айдол</AdminBadge>
                                </div>
                                <div class="text-[11px] text-zinc-400">
                                    Баланс: <strong class="text-zinc-200 font-mono">{{ formatMoney(currentSelectedUserBalance) }} 🪙</strong>
                                </div>
                            </div>
                        </div>
                        <AdminButton variant="ghost" size="sm" type="button" @click="deselectUserForCreate">
                            Сменить
                        </AdminButton>
                    </div>

                    <div v-else class="relative">
                        <div class="relative">
                            <el-icon class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500"><Search /></el-icon>
                            <input
                                v-model="userQuery"
                                type="text"
                                placeholder="Поиск по имени, email или ID..."
                                class="w-full h-9 pl-9 pr-8 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                                @input="onUserSearchInput"
                            />
                            <span v-if="userSearching" class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400">
                                <el-icon class="is-loading"><Loading /></el-icon>
                            </span>
                        </div>

                        <!-- Dropdown Results -->
                        <div
                            v-if="userSearchResults.length"
                            class="absolute left-0 right-0 top-full mt-1 max-h-48 overflow-y-auto rounded-lg border border-zinc-800 bg-zinc-900 shadow-xl z-20 divide-y divide-zinc-800"
                        >
                            <div
                                v-for="u in userSearchResults"
                                :key="u.id"
                                class="p-2 hover:bg-zinc-800/80 cursor-pointer flex items-center justify-between text-xs"
                                @click="selectUserForCreate(u)"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-zinc-200">{{ u.name }}</span>
                                    <span class="text-zinc-500 text-[11px]">({{ u.email }})</span>
                                </div>
                                <span class="font-mono text-zinc-300">{{ formatMoney(u.balance ?? 0) }} 🪙</span>
                            </div>
                        </div>
                    </div>
                    <p v-if="createForm.errors.user_id" class="text-xs text-rose-400">{{ createForm.errors.user_id }}</p>
                </div>

                <!-- Direction Toggle -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-medium text-zinc-300">
                        Направление операции <span class="text-rose-400">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            class="p-3 rounded-lg border text-left transition flex items-center gap-3"
                            :class="createForm.direction === 'credit'
                                ? 'border-emerald-500/50 bg-emerald-500/10 text-emerald-300'
                                : 'border-zinc-800 bg-zinc-950 text-zinc-400 hover:bg-zinc-900'"
                            @click="createForm.direction = 'credit'"
                        >
                            <div class="w-7 h-7 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-sm">+</div>
                            <div>
                                <div class="font-semibold text-xs text-zinc-200">Начисление</div>
                                <div class="text-[10px] text-zinc-500">Пополнение счёта</div>
                            </div>
                        </button>
                        <button
                            type="button"
                            class="p-3 rounded-lg border text-left transition flex items-center gap-3"
                            :class="createForm.direction === 'debit'
                                ? 'border-rose-500/50 bg-rose-500/10 text-rose-300'
                                : 'border-zinc-800 bg-zinc-950 text-zinc-400 hover:bg-zinc-900'"
                            @click="createForm.direction = 'debit'"
                        >
                            <div class="w-7 h-7 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center font-bold text-sm">−</div>
                            <div>
                                <div class="font-semibold text-xs text-zinc-200">Списание</div>
                                <div class="text-[10px] text-zinc-500">Снятие со счёта</div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Type Select -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-medium text-zinc-300">
                        Тип транзакции <span class="text-rose-400">*</span>
                    </label>
                    <select
                        v-model="createForm.type"
                        class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 focus:outline-none focus:border-violet-500"
                    >
                        <option v-for="opt in filteredCreateTypeOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </div>

                <!-- Amount Input & Presets -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-xs">
                        <label class="font-medium text-zinc-300">
                            Сумма операции <span class="text-rose-400">*</span>
                        </label>
                        <span v-if="selectedUserForCreate" class="text-zinc-500 font-mono">
                            Доступно: {{ formatMoney(currentSelectedUserBalance) }} 🪙
                        </span>
                    </div>

                    <div class="relative">
                        <input
                            v-model.number="createForm.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            class="w-full h-9 px-3 pr-10 rounded-lg border bg-zinc-950 text-xs text-zinc-100 font-mono focus:outline-none focus:border-violet-500"
                            :class="isInsufficientBalance ? 'border-rose-500' : 'border-zinc-800'"
                        />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 text-xs">🪙</span>
                    </div>

                    <div class="flex items-center gap-1.5 pt-1">
                        <button
                            v-for="val in amountPresets"
                            :key="val"
                            type="button"
                            class="px-2.5 py-1 rounded border text-xs font-mono transition"
                            :class="createForm.amount === val
                                ? 'border-violet-500 bg-violet-500/20 text-violet-300'
                                : 'border-zinc-800 bg-zinc-950 text-zinc-400 hover:text-zinc-200'"
                            @click="setAmountPreset(val)"
                        >
                            +{{ formatMoney(val) }}
                        </button>
                    </div>

                    <!-- Insufficient Balance Warning -->
                    <div v-if="isInsufficientBalance" class="p-2.5 rounded-lg border border-rose-900/40 bg-rose-950/20 text-xs text-rose-300 flex items-start gap-2">
                        <el-icon class="mt-0.5 shrink-0"><Warning /></el-icon>
                        <span>
                            Недостаточно средств: на балансе пользователя {{ formatMoney(currentSelectedUserBalance) }} 🪙. Овердрафт строго запрещён.
                        </span>
                    </div>

                    <!-- Projected Balance Outcome -->
                    <div v-else-if="selectedUserForCreate && parsedAmount > 0" class="p-2.5 rounded-lg border border-zinc-800 bg-zinc-950/60 text-xs flex items-center justify-between">
                        <span class="text-zinc-400">Баланс после операции:</span>
                        <div class="font-mono text-zinc-200">
                            {{ formatMoney(currentSelectedUserBalance) }} → <strong class="text-emerald-400">{{ formatMoney(projectedBalance) }} 🪙</strong>
                        </div>
                    </div>
                </div>

                <!-- Reason / Description -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-medium text-zinc-300">
                        Причина / Примечание к операции <span class="text-rose-400">*</span>
                    </label>
                    <textarea
                        v-model="createForm.description"
                        rows="2"
                        placeholder="Обязательно укажите причину для аудита..."
                        class="w-full px-3 py-2 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                    ></textarea>

                    <div class="flex flex-wrap gap-1.5 pt-0.5">
                        <button
                            v-for="r in reasonPresets"
                            :key="r"
                            type="button"
                            class="px-2 py-0.5 rounded text-[11px] border border-zinc-800 bg-zinc-950 text-zinc-400 hover:text-zinc-200 hover:border-zinc-700 transition"
                            @click="setReasonPreset(r)"
                        >
                            {{ r }}
                        </button>
                    </div>
                </div>

                <!-- Bell Notification Checkbox -->
                <label class="flex items-start gap-2.5 p-3 rounded-lg border border-zinc-800 bg-zinc-950 cursor-pointer">
                    <input
                        v-model="createForm.notify_user"
                        type="checkbox"
                        class="mt-0.5 rounded border-zinc-700 bg-zinc-900 text-violet-500 focus:ring-0"
                    />
                    <div class="text-xs">
                        <span class="font-medium text-zinc-200">Отправить уведомление в колокольчик</span>
                        <p class="text-[11px] text-zinc-500 mt-0.5">Пользователь получит сообщение и WebPush с деталями операции</p>
                    </div>
                </label>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-800">
                    <AdminButton variant="outline" size="sm" type="button" @click="closeCreateModal">
                        Отмена
                    </AdminButton>
                    <AdminButton
                        :variant="createForm.direction === 'credit' ? 'primary' : 'destructive'"
                        size="sm"
                        type="submit"
                        :loading="createForm.processing"
                        :disabled="isSubmitDisabled"
                    >
                        {{ submitButtonLabel }}
                    </AdminButton>
                </div>
            </form>
        </AdminModal>
    </div>
</template>
