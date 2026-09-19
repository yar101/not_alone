<script setup>
import { ref, watch, computed } from 'vue';
import { router, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import { formatMoney } from '@/Utils/money';
import {
    Coin, Plus, Search, Setting, Close, Check, Loading,
    User, Tickets, InfoFilled, ArrowRight, Wallet, Document, Bell,
    Warning
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

// ─── Details Modal ────────────────────────────────────────────────────────────

const modalTx = ref(null);

function openDetails(tx) {
    modalTx.value = tx;
}

function closeDetails() {
    modalTx.value = null;
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
    const formatted = formatMoney(parsedAmount.value) + ' ₽';
    if (createForm.direction === 'credit') {
        return `Начислить ${formatted}`;
    } else {
        return `Списать ${formatted}`;
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
    createForm.clearErrors();
    showCreateModal.value = true;
}

function closeCreateModal() {
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

function submitCreate() {
    if (isSubmitDisabled.value) return;
    createForm.post(route('admin.transactions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateModal();
        },
    });
}

// ─── Formatting Helpers ───────────────────────────────────────────────────────

function typeBadgeClass(t) {
    switch (t) {
        case 'deposit': return 'badge--emerald';
        case 'withdrawal': return 'badge--amber';
        case 'order_hold': return 'badge--cyan';
        case 'order_payout': return 'badge--emerald';
        case 'order_refund': return 'badge--blue';
        case 'platform_fee': return 'badge--violet';
        case 'admin_adjustment': return 'badge--purple';
        case 'pack_sale': return 'badge--teal';
        case 'pack_purchase': return 'badge--rose';
        default: return 'badge--default';
    }
}

function statusBadgeClass(s) {
    switch (s) {
        case 'completed': return 'badge--status-completed';
        case 'pending': return 'badge--status-pending';
        case 'failed': return 'badge--status-failed';
        case 'cancelled': return 'badge--status-cancelled';
        default: return 'badge--default';
    }
}

function isCredit(tx) {
    return Number(tx.amount || 0) > 0;
}
</script>

<template>
    <div class="page">
        <!-- Header -->
        <div class="page-header">
            <div class="page-header__left">
                <h1 class="page-title">
                    <el-icon class="page-title__icon"><Coin /></el-icon>
                    <span>Транзакции</span>
                </h1>
                <span class="page-subtitle">Всего операций: {{ kpi?.total_count ?? 0 }}</span>
            </div>
            <div class="page-header__actions">
                <Link :href="route('admin.settings.index')" class="btn-secondary" title="Регулирование комиссий платформы">
                    <el-icon><Setting /></el-icon>
                    <span>Комиссии: ввод {{ fees?.deposit_fee_percent }}% / вывод {{ fees?.withdrawal_fee_percent }}%</span>
                </Link>
                <button class="btn-primary" @click="openCreateModal">
                    <el-icon class="btn-icon"><Plus /></el-icon>
                    <span>Создать транзакцию</span>
                </button>
            </div>
        </div>

        <!-- KPI Cards Row -->
        <div class="kpi-grid">
            <div class="kpi-card kpi-card--deposits">
                <div class="kpi-card__top">
                    <span class="kpi-card__label">Пополнения (депозиты)</span>
                    <span class="kpi-card__icon"><Coin /></span>
                </div>
                <div class="kpi-card__value">+{{ formatMoney(kpi?.total_deposits ?? 0) }} ₽</div>
            </div>

            <div class="kpi-card kpi-card--withdrawals">
                <div class="kpi-card__top">
                    <span class="kpi-card__label">Выводы средств</span>
                    <span class="kpi-card__icon"><Wallet /></span>
                </div>
                <div class="kpi-card__value">−{{ formatMoney(kpi?.total_withdrawals ?? 0) }} ₽</div>
            </div>

            <div class="kpi-card kpi-card--fees">
                <div class="kpi-card__top">
                    <span class="kpi-card__label">Комиссии платформы</span>
                    <span class="kpi-card__icon"><Tickets /></span>
                </div>
                <div class="kpi-card__value">{{ formatMoney(kpi?.total_fees ?? 0) }} ₽</div>
            </div>

            <div class="kpi-card kpi-card--total">
                <div class="kpi-card__top">
                    <span class="kpi-card__label">Всего транзакций</span>
                    <span class="kpi-card__icon"><Document /></span>
                </div>
                <div class="kpi-card__value">{{ kpi?.total_count ?? 0 }}</div>
            </div>
        </div>

        <!-- Selected User Filter Banner (if active) -->
        <div v-if="selectedUser" class="user-filter-banner">
            <div class="user-filter-banner__info">
                <span class="user-filter-banner__tag">Фильтр по пользователю:</span>
                <div class="user-pill">
                    <img v-if="selectedUser.avatar_url" :src="selectedUser.avatar_url" class="user-pill__avatar" alt="" />
                    <div v-else class="user-pill__avatar user-pill__avatar--placeholder">
                        {{ selectedUser.name?.charAt(0).toUpperCase() }}
                    </div>
                    <span class="user-pill__name">{{ selectedUser.name }}</span>
                    <span class="user-pill__email">({{ selectedUser.email }})</span>
                </div>
                <div class="user-filter-banner__balance">
                    Доступно: <strong>{{ formatMoney(selectedUser.balance) }} ₽</strong>
                    <span v-if="selectedUser.held_balance > 0"> (Заморожено: {{ formatMoney(selectedUser.held_balance) }} ₽)</span>
                </div>
            </div>
            <button class="btn-clear-user" @click="clearUserFilter" title="Сбросить фильтр по пользователю">
                <el-icon><Close /></el-icon>
                <span>Сбросить</span>
            </button>
        </div>

        <!-- Type Tabs -->
        <div class="type-tabs">
            <button
                v-for="tab in [
                    { value: '',                 label: 'Все',           count: typeCounts?.all ?? 0 },
                    { value: 'deposit',          label: 'Пополнения',    count: typeCounts?.deposit ?? 0 },
                    { value: 'withdrawal',       label: 'Выводы',        count: typeCounts?.withdrawal ?? 0 },
                    { value: 'order_hold',       label: 'Заморозки',     count: typeCounts?.order_hold ?? 0 },
                    { value: 'order_payout',     label: 'Выплаты',       count: typeCounts?.order_payout ?? 0 },
                    { value: 'order_refund',     label: 'Возвраты',      count: typeCounts?.order_refund ?? 0 },
                    { value: 'platform_fee',     label: 'Комиссии',      count: typeCounts?.platform_fee ?? 0 },
                    { value: 'admin_adjustment', label: 'Корректировки', count: typeCounts?.admin_adjustment ?? 0 },
                ]"
                :key="tab.value"
                class="type-tab"
                :class="{ 'type-tab--active': type === tab.value }"
                @click="setType(tab.value)"
            >
                <span>{{ tab.label }}</span>
                <span class="type-tab__count">{{ tab.count }}</span>
            </button>
        </div>

        <!-- Filters Row -->
        <div class="filters">
            <div class="search-wrap">
                <el-icon class="search-icon"><Search /></el-icon>
                <input
                    v-model="search"
                    class="input filter-search"
                    placeholder="Поиск по ID, описанию, имени или email…"
                    @input="onSearchInput"
                />
                <button v-if="search" class="search-clear" @click="search = ''; applyFilters();">✕</button>
            </div>

            <div class="filter-select-wrap">
                <AppSelect
                    v-model="status"
                    :options="statusOptions"
                    placeholder="Все статусы"
                    class="filter-app-select"
                />
            </div>

            <input v-model="dateFrom" type="date" class="input filter-date" title="Дата с" />
            <input v-model="dateTo"   type="date" class="input filter-date" title="Дата по" />

            <button v-if="hasActiveFilters" class="btn-reset" @click="resetAllFilters" title="Сбросить все фильтры">
                Сбросить фильтры
            </button>
        </div>

        <!-- Transactions Table -->
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="th-id">#</th>
                        <th class="th-user">Пользователь</th>
                        <th class="th-type">Тип</th>
                        <th class="th-amount">Сумма</th>
                        <th class="th-balance">Баланс (до → после)</th>
                        <th class="th-status">Статус</th>
                        <th class="th-desc">Описание</th>
                        <th class="th-date">Дата</th>
                        <th class="th-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="tx in transactions.data" :key="tx.id">
                        <td class="td-id">#{{ tx.id }}</td>
                        <td class="td-user">
                            <div v-if="tx.user" class="user-cell">
                                <img
                                    v-if="tx.user.avatar_url"
                                    :src="tx.user.avatar_url"
                                    class="user-cell__avatar"
                                    alt=""
                                />
                                <div v-else class="user-cell__avatar user-cell__avatar--placeholder">
                                    {{ tx.user.name?.charAt(0).toUpperCase() }}
                                </div>
                                <div class="user-cell__info">
                                    <Link :href="route('admin.users.show', tx.user.id)" class="user-cell__name">
                                        {{ tx.user.name }}
                                    </Link>
                                    <span class="user-cell__email">{{ tx.user.email }}</span>
                                </div>
                            </div>
                            <span v-else class="no-val">—</span>
                        </td>
                        <td class="td-type">
                            <span class="badge" :class="typeBadgeClass(tx.type)">
                                {{ tx.type_label }}
                            </span>
                        </td>
                        <td class="td-amount">
                            <span
                                class="amount-val"
                                :class="isCredit(tx) ? 'amount-val--credit' : 'amount-val--debit'"
                            >
                                {{ isCredit(tx) ? '+' : '' }}{{ formatMoney(tx.amount) }} ₽
                            </span>
                        </td>
                        <td class="td-balance">
                            <div class="balance-flow">
                                <span>{{ formatMoney(tx.balance_before) }} ₽</span>
                                <span class="flow-arrow">→</span>
                                <strong class="balance-after">{{ formatMoney(tx.balance_after) }} ₽</strong>
                            </div>
                            <div v-if="tx.held_balance_before !== tx.held_balance_after" class="held-sub">
                                Холд: {{ formatMoney(tx.held_balance_before) }} → {{ formatMoney(tx.held_balance_after) }} ₽
                            </div>
                        </td>
                        <td class="td-status">
                            <span class="badge" :class="statusBadgeClass(tx.status)">
                                {{ tx.status_label }}
                            </span>
                        </td>
                        <td class="td-desc">
                            <span class="desc-text" :title="tx.description">{{ tx.description || '—' }}</span>
                        </td>
                        <td class="td-date">{{ tx.created_at }}</td>
                        <td class="td-actions">
                            <button class="btn-details" @click="openDetails(tx)">
                                Детали
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!transactions.data.length">
                        <td colspan="9" class="empty-row">
                            <div class="empty-state">
                                <el-icon class="empty-state__icon"><Coin /></el-icon>
                                <span>Транзакций не найдено</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination" v-if="transactions.last_page > 1">
            <a
                v-for="link in transactions.links"
                :key="link.label"
                :href="link.url || '#'"
                class="page-link"
                :class="{ 'page-link--active': link.active, 'page-link--disabled': !link.url }"
                v-html="link.label"
                @click.prevent="link.url && router.get(link.url, {}, { preserveState: false, preserveScroll: true })"
            />
        </div>
    </div>

    <!-- ════════════ MODAL: TRANSACTION DETAILS ════════════ -->
    <Teleport to="body">
        <div v-if="modalTx" class="overlay" @click.self="closeDetails">
            <div class="modal modal--details">
                <div class="modal__header">
                    <div class="modal__title-group">
                        <span class="modal__title">Транзакция #{{ modalTx.id }}</span>
                        <span class="badge" :class="statusBadgeClass(modalTx.status)">{{ modalTx.status_label }}</span>
                    </div>
                    <button class="modal__close" @click="closeDetails">✕</button>
                </div>

                <div class="modal__body">
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="detail-label">Тип операции</span>
                            <span class="badge" :class="typeBadgeClass(modalTx.type)">{{ modalTx.type_label }}</span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Сумма</span>
                            <strong class="detail-amount" :class="isCredit(modalTx) ? 'amount-val--credit' : 'amount-val--debit'">
                                {{ isCredit(modalTx) ? '+' : '' }}{{ formatMoney(modalTx.amount) }} ₽
                            </strong>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Пользователь</span>
                            <div v-if="modalTx.user" class="user-detail-link">
                                <Link :href="route('admin.users.show', modalTx.user.id)">
                                    {{ modalTx.user.name }} ({{ modalTx.user.email }}) ↗
                                </Link>
                            </div>
                            <span v-else class="no-val">—</span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Кошелёк #</span>
                            <span>ID: {{ modalTx.wallet_id }}</span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Баланс (до / после)</span>
                            <span>{{ formatMoney(modalTx.balance_before) }} ₽ → <strong>{{ formatMoney(modalTx.balance_after) }} ₽</strong></span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Замороженный баланс</span>
                            <span>{{ formatMoney(modalTx.held_balance_before) }} ₽ → <strong>{{ formatMoney(modalTx.held_balance_after) }} ₽</strong></span>
                        </div>

                        <div class="detail-item detail-item--full">
                            <span class="detail-label">Описание операции</span>
                            <p class="detail-text">{{ modalTx.description || '—' }}</p>
                        </div>

                        <div v-if="modalTx.reference_type" class="detail-item detail-item--full">
                            <span class="detail-label">Связанный объект (Reference)</span>
                            <div class="reference-chip">
                                <span>{{ modalTx.reference_type }} #{{ modalTx.reference_id }}</span>
                            </div>
                        </div>

                        <div v-if="modalTx.idempotency_key" class="detail-item detail-item--full">
                            <span class="detail-label">Ключ идемпотентности</span>
                            <code class="code-box">{{ modalTx.idempotency_key }}</code>
                        </div>

                        <div v-if="modalTx.metadata && Object.keys(modalTx.metadata).length" class="detail-item detail-item--full">
                            <span class="detail-label">Метаданные (JSON)</span>
                            <pre class="metadata-pre">{{ JSON.stringify(modalTx.metadata, null, 2) }}</pre>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Дата создания</span>
                            <span>{{ modalTx.created_at }}</span>
                        </div>
                    </div>
                </div>

                <div class="modal__actions">
                    <button class="btn-cancel" @click="closeDetails">Закрыть</button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ════════════ MODAL: CREATE MANUAL TRANSACTION ════════════ -->
    <Teleport to="body">
        <div v-if="showCreateModal" class="overlay" @click.self="closeCreateModal">
            <div class="modal modal--create">
                <div class="modal__header">
                    <div class="modal__title-wrap">
                        <div class="modal__icon-badge">
                            <el-icon><Coin /></el-icon>
                        </div>
                        <div>
                            <h3 class="modal__title">Создать транзакцию вручную</h3>
                            <p class="modal__subtitle">Прямое начисление или списание средств с баланса пользователя</p>
                        </div>
                    </div>
                    <button type="button" class="modal__close" @click="closeCreateModal" title="Закрыть">✕</button>
                </div>

                <form @submit.prevent="submitCreate" class="modal__body">
                    <!-- Step 1: User Picker -->
                    <div class="field">
                        <label class="field-title">Пользователь <span class="req">*</span></label>

                        <div v-if="selectedUserForCreate" class="selected-user-card">
                            <div class="selected-user-card__left">
                                <img
                                    v-if="selectedUserForCreate.avatar_url"
                                    :src="selectedUserForCreate.avatar_url"
                                    class="selected-user-avatar"
                                    alt=""
                                />
                                <div v-else class="selected-user-avatar selected-user-avatar--placeholder">
                                    {{ selectedUserForCreate.name?.charAt(0).toUpperCase() }}
                                </div>
                                <div class="selected-user-card__info">
                                    <div class="selected-user-card__topline">
                                        <strong class="selected-user-card__name">{{ selectedUserForCreate.name }}</strong>
                                        <span v-if="selectedUserForCreate.is_idol" class="badge-idol">Айдол</span>
                                    </div>
                                    <span class="selected-user-card__email">{{ selectedUserForCreate.email }}</span>
                                    <div class="selected-user-card__balance">
                                        Текущий баланс: <strong>{{ formatMoney(currentSelectedUserBalance) }} ₽</strong>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn-change-user" @click="deselectUserForCreate">
                                Сменить
                            </button>
                        </div>

                        <div v-else class="user-search-box">
                            <div class="user-search-input-wrap">
                                <el-icon class="user-search-icon"><Search /></el-icon>
                                <input
                                    v-model="userQuery"
                                    class="user-search-input"
                                    placeholder="Поиск по имени, email или ID…"
                                    @input="onUserSearchInput"
                                />
                                <span v-if="userSearching" class="user-search-spinner">
                                    <el-icon class="is-loading"><Loading /></el-icon>
                                </span>
                            </div>

                            <div v-if="userSearchResults.length" class="user-dropdown">
                                <div
                                    v-for="u in userSearchResults"
                                    :key="u.id"
                                    class="user-dropdown__item"
                                    @click="selectUserForCreate(u)"
                                >
                                    <img v-if="u.avatar_url" :src="u.avatar_url" class="dropdown-avatar" alt="" />
                                    <div v-else class="dropdown-avatar dropdown-avatar--placeholder">
                                        {{ u.name?.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="dropdown-user-info">
                                        <div class="dropdown-name-row">
                                            <span class="dropdown-name">{{ u.name }}</span>
                                            <span v-if="u.is_idol" class="badge-idol badge-idol--mini">Айдол</span>
                                        </div>
                                        <span class="dropdown-email">{{ u.email }}</span>
                                    </div>
                                    <div class="dropdown-user-balance">
                                        <span class="dropdown-balance-label">Баланс</span>
                                        <span class="dropdown-balance-val">{{ formatMoney(u.balance ?? 0) }} ₽</span>
                                    </div>
                                </div>
                            </div>
                            <div v-else-if="userQuery.trim() && !userSearching" class="user-dropdown-empty">
                                Пользователи не найдены
                            </div>
                        </div>
                        <p v-if="createForm.errors.user_id" class="err">{{ createForm.errors.user_id }}</p>
                    </div>

                    <!-- Step 2: Direction Toggle -->
                    <div class="field">
                        <label class="field-title">Направление операции <span class="req">*</span></label>
                        <div class="direction-toggle">
                            <button
                                type="button"
                                class="dir-btn dir-btn--credit"
                                :class="{ 'dir-btn--active': createForm.direction === 'credit' }"
                                @click="createForm.direction = 'credit'"
                            >
                                <span class="dir-btn__icon">+</span>
                                <div class="dir-btn__text">
                                    <span class="dir-btn__title">Начисление</span>
                                    <span class="dir-btn__hint">Пополнение счёта</span>
                                </div>
                            </button>
                            <button
                                type="button"
                                class="dir-btn dir-btn--debit"
                                :class="{ 'dir-btn--active': createForm.direction === 'debit' }"
                                @click="createForm.direction = 'debit'"
                            >
                                <span class="dir-btn__icon">−</span>
                                <div class="dir-btn__text">
                                    <span class="dir-btn__title">Списание</span>
                                    <span class="dir-btn__hint">Снятие со счёта</span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Transaction Type -->
                    <div class="field">
                        <label class="field-title">Тип транзакции <span class="req">*</span></label>
                        <AppSelect
                            v-model="createForm.type"
                            :options="createTypeOptions"
                            class="modal-app-select"
                        />
                    </div>

                    <!-- Step 4: Amount -->
                    <div class="field">
                        <div class="field-title-row">
                            <label class="field-title">Сумма операции <span class="req">*</span></label>
                            <span v-if="selectedUserForCreate" class="field-subtitle">
                                Доступно: <span class="available-val">{{ formatMoney(currentSelectedUserBalance) }} ₽</span>
                            </span>
                        </div>
                        <div class="amount-input-wrap">
                            <input
                                v-model.number="createForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                max="10000000"
                                class="input input--amount"
                                :class="{ 'input--error': isInsufficientBalance }"
                                placeholder="0.00"
                            />
                            <span class="currency-label">₽</span>
                        </div>

                        <!-- Amount Presets -->
                        <div class="amount-presets">
                            <button
                                v-for="val in amountPresets"
                                :key="val"
                                type="button"
                                class="preset-btn"
                                :class="{ 'preset-btn--active': createForm.amount === val }"
                                @click="setAmountPreset(val)"
                            >
                                +{{ formatMoney(val) }} ₽
                            </button>
                        </div>

                        <!-- Live Outcome Preview Banner -->
                        <div v-if="selectedUserForCreate && parsedAmount > 0" class="balance-outcome-banner" :class="{
                            'balance-outcome-banner--credit': createForm.direction === 'credit',
                            'balance-outcome-banner--debit': createForm.direction === 'debit' && !isInsufficientBalance,
                            'balance-outcome-banner--error': isInsufficientBalance,
                        }">
                            <div class="outcome-icon">
                                <el-icon v-if="isInsufficientBalance"><Warning /></el-icon>
                                <el-icon v-else-if="createForm.direction === 'credit'"><Check /></el-icon>
                                <el-icon v-else><Wallet /></el-icon>
                            </div>
                            <div class="outcome-content">
                                <template v-if="isInsufficientBalance">
                                    <div class="outcome-title">Недостаточно средств для списания</div>
                                    <div class="outcome-desc">
                                        На балансе пользователя <strong>{{ formatMoney(currentSelectedUserBalance) }} ₽</strong>, а запрошено к списанию <strong>{{ formatMoney(parsedAmount) }} ₽</strong>. Баланс не может быть отрицательным.
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="outcome-title">Баланс после операции:</div>
                                    <div class="outcome-flow">
                                        <span>{{ formatMoney(currentSelectedUserBalance) }} ₽</span>
                                        <span class="outcome-arrow">→</span>
                                        <strong class="outcome-target">{{ formatMoney(projectedBalance) }} ₽</strong>
                                        <span class="outcome-delta" :class="createForm.direction === 'credit' ? 'delta--plus' : 'delta--minus'">
                                            ({{ createForm.direction === 'credit' ? '+' : '−' }}{{ formatMoney(parsedAmount) }} ₽)
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <p v-if="createForm.errors.amount" class="err">{{ createForm.errors.amount }}</p>
                    </div>

                    <!-- Step 5: Description / Reason -->
                    <div class="field">
                        <label class="field-title">Причина / Примечание к операции <span class="req">*</span></label>
                        <textarea
                            v-model="createForm.description"
                            class="input input--textarea"
                            rows="2"
                            placeholder="Обязательно укажите причину для аудита (например: 'Бонус за активность', 'Компенсация по тикету #123')…"
                        ></textarea>

                        <!-- Quick Reason Tags -->
                        <div class="reason-tags">
                            <button
                                v-for="r in reasonPresets"
                                :key="r"
                                type="button"
                                class="reason-tag-btn"
                                :class="{ 'reason-tag-btn--active': createForm.description === r }"
                                @click="setReasonPreset(r)"
                            >
                                {{ r }}
                            </button>
                        </div>

                        <p v-if="createForm.errors.description" class="err">{{ createForm.errors.description }}</p>
                    </div>

                    <!-- Step 6: Notify User Checkbox -->
                    <div class="field field--checkbox-card" :class="{ 'field--checkbox-card--active': createForm.notify_user }">
                        <label class="checkbox-label">
                            <input
                                v-model="createForm.notify_user"
                                type="checkbox"
                                class="checkbox-input"
                            />
                            <span class="checkbox-custom">
                                <el-icon v-if="createForm.notify_user"><Check /></el-icon>
                            </span>
                            <span class="checkbox-title">
                                <el-icon class="checkbox-bell-icon"><Bell /></el-icon>
                                Отправить уведомление пользователю в колокольчик
                            </span>
                        </label>
                        <p class="checkbox-hint">Пользователь получит уведомление в колокольчик и WebPush с описанием операции</p>
                    </div>

                    <p v-if="createForm.errors.general" class="err err--box">{{ createForm.errors.general }}</p>

                    <div class="modal__actions">
                        <button type="button" class="btn-cancel" @click="closeCreateModal">Отмена</button>
                        <button
                            type="submit"
                            class="btn-submit"
                            :class="{
                                'btn-submit--credit': createForm.direction === 'credit',
                                'btn-submit--debit': createForm.direction === 'debit',
                            }"
                            :disabled="isSubmitDisabled"
                        >
                            <el-icon v-if="createForm.processing" class="is-loading"><Loading /></el-icon>
                            <span>{{ submitButtonLabel }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.page { max-width: none; }

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.page-header__left {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.page-title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 1.35rem;
    font-weight: 700;
    color: rgba(255,255,255,0.92);
    margin: 0;
}
.page-title__icon {
    font-size: 1.35rem;
    color: #9B6EE8;
}
.page-subtitle {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.4);
}
.page-header__actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Buttons */
.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.58rem 1.15rem;
    background: linear-gradient(135deg, #a855f7 0%, #8b5cf6 50%, #7c3aed 100%);
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 9px;
    font-size: 0.86rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.25);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.btn-primary:hover {
    background: linear-gradient(135deg, #b366f8 0%, #9366f7 50%, #8542f5 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(139, 92, 246, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.35);
}
.btn-primary:active {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(139, 92, 246, 0.4);
}
.btn-primary .btn-icon {
    font-size: 0.95rem;
}
.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.55rem 0.9rem;
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.75);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    font-size: 0.82rem;
    text-decoration: none;
    transition: all 0.15s;
}
.btn-secondary:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
}
.btn-reset {
    padding: 0.45rem 0.8rem;
    background: none;
    border: 1px solid rgba(239,68,68,0.3);
    color: rgba(239,68,68,0.8);
    border-radius: 7px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-reset:hover {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
}

/* KPI Cards */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 0.9rem;
    margin-bottom: 1.25rem;
}
.kpi-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    padding: 0.9rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.kpi-card__top {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.kpi-card__label {
    font-size: 0.76rem;
    color: rgba(255,255,255,0.45);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.kpi-card__icon {
    font-size: 1rem;
    color: rgba(255,255,255,0.3);
}
.kpi-card__value {
    font-size: 1.25rem;
    font-weight: 700;
    color: rgba(255,255,255,0.9);
}
.kpi-card--deposits .kpi-card__value { color: #34d399; }
.kpi-card--withdrawals .kpi-card__value { color: #f87171; }
.kpi-card--fees .kpi-card__value { color: #c084fc; }

/* User Filter Banner */
.user-filter-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(155, 110, 232, 0.08);
    border: 1px solid rgba(155, 110, 232, 0.25);
    border-radius: 8px;
    padding: 0.65rem 1rem;
    margin-bottom: 1rem;
}
.user-filter-banner__info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    font-size: 0.85rem;
}
.user-filter-banner__tag {
    color: rgba(255,255,255,0.5);
}
.user-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(255,255,255,0.07);
    padding: 0.2rem 0.6rem;
    border-radius: 99px;
}
.user-pill__avatar {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    object-fit: cover;
}
.user-pill__avatar--placeholder {
    background: #9B6EE8;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.68rem;
    font-weight: 700;
}
.user-pill__name { font-weight: 600; color: #fff; }
.user-pill__email { color: rgba(255,255,255,0.4); font-size: 0.78rem; }
.user-filter-banner__balance {
    color: rgba(255,255,255,0.8);
    font-size: 0.82rem;
}
.user-filter-banner__balance strong { color: #34d399; }
.btn-clear-user {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: none;
    border: none;
    color: rgba(255,255,255,0.5);
    cursor: pointer;
    font-size: 0.8rem;
}
.btn-clear-user:hover { color: #ef4444; }

/* Type Tabs */
.type-tabs {
    display: flex;
    gap: 0;
    margin-bottom: 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.type-tabs::-webkit-scrollbar {
    display: none;
}
.type-tab {
    padding: 0.5rem 0.9rem;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    color: rgba(255,255,255,0.4);
    font-size: 0.84rem;
    cursor: pointer;
    font-family: inherit;
    margin-bottom: -1px;
    transition: color 0.15s;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    white-space: nowrap;
}
.type-tab:hover { color: rgba(255,255,255,0.7); }
.type-tab--active { color: #9B6EE8; border-bottom-color: #9B6EE8; }
.type-tab__count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 0.3rem;
    background: rgba(255,255,255,0.07);
    border-radius: 99px;
    font-size: 0.7rem;
    color: rgba(255,255,255,0.45);
}

/* Filters */
.filters {
    display: flex;
    gap: 0.6rem;
    margin-bottom: 1.1rem;
    flex-wrap: wrap;
    align-items: center;
}
.search-wrap {
    position: relative;
    flex: 1;
    min-width: 240px;
}
.search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255,255,255,0.3);
    font-size: 0.9rem;
}
.filter-search {
    width: 100%;
    padding-left: 2.1rem;
    padding-right: 1.8rem;
}
.search-clear {
    position: absolute;
    right: 0.6rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: rgba(255,255,255,0.3);
    cursor: pointer;
    font-size: 0.8rem;
}
.filter-select-wrap {
    width: 170px;
}
:deep(.filter-app-select.app-select) {
    padding: 0.45rem 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 7px;
    font-size: 0.84rem;
    min-height: 33px;
    height: 33px;
}
:deep(.filter-app-select.app-select:hover:not(.app-select--disabled)) {
    border-color: rgba(255, 255, 255, 0.2);
}
:deep(.filter-app-select.app-select--open) {
    border-color: #9B6EE8;
}

.filter-date {
    width: 140px;
    color-scheme: dark;
}

:deep(.modal-app-select.app-select) {
    padding: 0.52rem 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 7px;
    font-size: 0.84rem;
}
:deep(.modal-app-select.app-select:hover:not(.app-select--disabled)) {
    border-color: rgba(255, 255, 255, 0.2);
}
:deep(.modal-app-select.app-select--open) {
    border-color: #9B6EE8;
}

/* Table */
.table-wrap {
    overflow-x: auto;
    overflow-y: hidden;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 10px;
}
.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.84rem;
}
.table th {
    padding: 0.6rem 0.75rem;
    text-align: left;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgba(255,255,255,0.35);
    border-bottom: 1px solid rgba(255,255,255,0.07);
    white-space: nowrap;
}
.table td {
    padding: 0.65rem 0.75rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: middle;
}
.table tr:hover td { background: rgba(255,255,255,0.025); }

.td-id { color: rgba(255,255,255,0.35); font-size: 0.78rem; white-space: nowrap; }
.td-user { min-width: 180px; }
.user-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.user-cell__avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
}
.user-cell__avatar--placeholder {
    background: #9B6EE8;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    font-weight: 700;
}
.user-cell__info {
    display: flex;
    flex-direction: column;
}
.user-cell__name {
    font-weight: 600;
    color: rgba(255,255,255,0.9);
    text-decoration: none;
    font-size: 0.82rem;
}
.user-cell__name:hover { color: #9B6EE8; }
.user-cell__email {
    font-size: 0.72rem;
    color: rgba(255,255,255,0.35);
}

.amount-val {
    font-weight: 700;
    white-space: nowrap;
    font-size: 0.88rem;
}
.amount-val--credit { color: #34d399; }
.amount-val--debit { color: #f87171; }

.balance-flow {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    color: rgba(255,255,255,0.45);
    white-space: nowrap;
}
.balance-after { color: rgba(255,255,255,0.85); }
.flow-arrow { color: rgba(255,255,255,0.25); }
.held-sub { font-size: 0.7rem; color: rgba(255,255,255,0.3); }

.desc-text {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: inline-block;
    color: rgba(255,255,255,0.6);
    font-size: 0.8rem;
}
.td-date { white-space: nowrap; color: rgba(255,255,255,0.35); font-size: 0.78rem; }
.td-actions { white-space: nowrap; text-align: right; }

.btn-details {
    padding: 0.3rem 0.65rem;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.8);
    border-radius: 6px;
    font-size: 0.76rem;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-details:hover {
    background: rgba(155, 110, 232, 0.15);
    border-color: #9B6EE8;
    color: #fff;
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
    white-space: nowrap;
}
.badge--emerald { background: rgba(52, 211, 153, 0.12); color: #34d399; }
.badge--cyan    { background: rgba(6, 182, 212, 0.12); color: #22d3ee; }
.badge--amber   { background: rgba(245, 158, 11, 0.12); color: #fbbf24; }
.badge--blue    { background: rgba(59, 130, 246, 0.12); color: #60a5fa; }
.badge--violet  { background: rgba(155, 110, 232, 0.15); color: #c084fc; }
.badge--purple  { background: rgba(168, 85, 247, 0.15); color: #d8b4fe; }
.badge--rose    { background: rgba(244, 63, 94, 0.12); color: #fb7185; }
.badge--teal    { background: rgba(20, 184, 166, 0.12); color: #2dd4bf; }
.badge--default { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.6); }

.badge--status-completed { background: rgba(52, 211, 153, 0.12); color: #34d399; }
.badge--status-pending   { background: rgba(245, 158, 11, 0.12); color: #fbbf24; }
.badge--status-failed    { background: rgba(239, 68, 68, 0.12); color: #f87171; }
.badge--status-cancelled { background: rgba(255, 255, 255, 0.08); color: rgba(255,255,255,0.4); }

/* Empty State */
.empty-row { padding: 3rem 1rem !important; text-align: center; }
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255,255,255,0.3);
    font-size: 0.9rem;
}
.empty-state__icon { font-size: 2rem; }

/* Inputs */
.input {
    padding: 0.45rem 0.75rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 7px;
    color: rgba(255,255,255,0.9);
    font-size: 0.84rem;
    font-family: inherit;
    outline: none;
    box-sizing: border-box;
}
.input:focus {
    border-color: #9B6EE8;
    background: rgba(255,255,255,0.08);
}
.input--textarea { width: 100%; resize: vertical; }

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 0.35rem;
    margin-top: 1.25rem;
}
.page-link {
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    font-size: 0.8rem;
    color: rgba(255,255,255,0.6);
    text-decoration: none;
    border: 1px solid rgba(255,255,255,0.08);
}
.page-link:hover:not(.page-link--disabled) {
    background: rgba(255,255,255,0.08);
    color: #fff;
}
.page-link--active {
    background: #9B6EE8;
    border-color: #9B6EE8;
    color: #fff;
}
.page-link--disabled {
    opacity: 0.3;
    cursor: default;
}

/* Modals */
.overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.75);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 1rem;
}
.modal {
    background: #181926;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 40px rgba(0,0,0,0.6);
}
.modal--details { max-width: 580px; }
.modal--create {
    max-width: 550px;
    background: linear-gradient(180deg, #1e2030 0%, #151622 100%);
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.75), 0 0 0 1px rgba(255, 255, 255, 0.05);
    border-radius: 14px;
}

.modal__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.1rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.modal__title-group {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.modal__title-wrap {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}
.modal__icon-badge {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: rgba(168, 85, 247, 0.16);
    border: 1px solid rgba(168, 85, 247, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #c084fc;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.modal__title {
    font-size: 1.08rem;
    font-weight: 700;
    color: rgba(255,255,255,0.95);
    margin: 0;
}
.modal__subtitle {
    margin: 0.15rem 0 0;
    font-size: 0.76rem;
    color: rgba(255, 255, 255, 0.45);
}
.modal__close {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.5);
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.15s ease;
}
.modal__close:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.18);
    color: #fff;
}

.modal__body {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.modal__actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.6rem;
    padding: 1rem 1.25rem;
    border-top: 1px solid rgba(255,255,255,0.08);
    background: rgba(0,0,0,0.15);
}

.btn-cancel {
    padding: 0.55rem 1rem;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.7);
    border-radius: 8px;
    font-size: 0.84rem;
    cursor: pointer;
    transition: all 0.15s ease;
}
.btn-cancel:hover { background: rgba(255,255,255,0.1); color: #fff; }

.btn-submit {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.58rem 1.25rem;
    background: linear-gradient(135deg, #a855f7 0%, #8b5cf6 50%, #7c3aed 100%);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #fff;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}
.btn-submit:hover:not(:disabled) {
    background: linear-gradient(135deg, #b366f8 0%, #9366f7 50%, #8542f5 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(139, 92, 246, 0.45);
}
.btn-submit--credit {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-color: rgba(52, 211, 153, 0.3);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}
.btn-submit--credit:hover:not(:disabled) {
    background: linear-gradient(135deg, #18c58d 0%, #06a372 100%);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.45);
}
.btn-submit--debit {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    border-color: rgba(248, 113, 113, 0.3);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}
.btn-submit--debit:hover:not(:disabled) {
    background: linear-gradient(135deg, #f55555 0%, #e53232 100%);
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.45);
}
.btn-submit:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}

/* Details Grid */
.details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.9rem 1.1rem;
}
.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.84rem;
}
.detail-item--full { grid-column: span 2; }
.detail-label {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255,255,255,0.4);
}
.detail-amount { font-size: 1.1rem; }
.detail-text {
    margin: 0;
    color: rgba(255,255,255,0.85);
    background: rgba(255,255,255,0.03);
    padding: 0.6rem;
    border-radius: 6px;
    font-size: 0.82rem;
}
.user-detail-link a {
    color: #9B6EE8;
    text-decoration: none;
    font-weight: 600;
}
.user-detail-link a:hover { text-decoration: underline; }
.code-box {
    background: rgba(0,0,0,0.3);
    padding: 0.35rem 0.55rem;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.76rem;
    color: rgba(255,255,255,0.7);
    word-break: break-all;
}
.metadata-pre {
    margin: 0;
    background: rgba(0,0,0,0.35);
    padding: 0.65rem;
    border-radius: 6px;
    font-family: monospace;
    font-size: 0.74rem;
    color: #34d399;
    max-height: 180px;
    overflow-y: auto;
}
.reference-chip {
    display: inline-flex;
    align-items: center;
    background: rgba(255,255,255,0.06);
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    color: rgba(255,255,255,0.8);
    font-size: 0.8rem;
}

/* Create Form Specifics */
.field {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.field-title-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}
.field-title {
    font-size: 0.82rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.85);
}
.field-subtitle {
    font-size: 0.74rem;
    color: rgba(255, 255, 255, 0.45);
}
.available-val {
    color: #34d399;
    font-weight: 600;
}
.req { color: #f87171; }

/* User search in create modal */
.user-search-box {
    position: relative;
    width: 100%;
}
.user-search-input-wrap {
    position: relative;
    width: 100%;
}
.user-search-icon {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.95rem;
    pointer-events: none;
}
.user-search-spinner {
    position: absolute;
    right: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: #a855f7;
    font-size: 0.95rem;
}
.user-search-input {
    width: 100%;
    height: 42px;
    padding: 0 2.2rem 0 2.4rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 9px;
    color: rgba(255, 255, 255, 0.95);
    font-size: 0.86rem;
    font-family: inherit;
    outline: none;
    box-sizing: border-box;
    transition: all 0.15s ease;
}
.user-search-input:focus {
    border-color: #a855f7;
    background: rgba(255, 255, 255, 0.07);
    box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.18);
}
.user-search-input::placeholder {
    color: rgba(255, 255, 255, 0.35);
}
.user-dropdown {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    background: #1e2030;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 10px;
    max-height: 220px;
    overflow-y: auto;
    z-index: 100;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.65);
}
.user-dropdown__item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    cursor: pointer;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    transition: background 0.12s ease;
}
.user-dropdown__item:last-child {
    border-bottom: none;
}
.user-dropdown__item:hover {
    background: rgba(168, 85, 247, 0.14);
}
.dropdown-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}
.dropdown-avatar--placeholder {
    background: linear-gradient(135deg, #a855f7, #7c3aed);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    flex-shrink: 0;
}
.dropdown-user-info {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    flex: 1;
    min-width: 0;
}
.dropdown-name-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.dropdown-name {
    font-size: 0.84rem;
    font-weight: 600;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dropdown-email {
    font-size: 0.74rem;
    color: rgba(255, 255, 255, 0.45);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dropdown-user-balance {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    flex-shrink: 0;
}
.dropdown-balance-label {
    color: rgba(255, 255, 255, 0.35);
    font-size: 0.68rem;
}
.dropdown-balance-val {
    color: #34d399;
    font-weight: 600;
    font-size: 0.8rem;
}
.user-dropdown-empty {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    background: #1e2030;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 10px;
    padding: 0.85rem;
    text-align: center;
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.5);
    z-index: 100;
}

/* Selected User Card */
.selected-user-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(168, 85, 247, 0.08);
    border: 1px solid rgba(168, 85, 247, 0.28);
    border-radius: 10px;
    padding: 0.75rem 0.95rem;
}
.selected-user-card__left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.selected-user-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}
.selected-user-avatar--placeholder {
    background: linear-gradient(135deg, #a855f7, #7c3aed);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.88rem;
    font-weight: 700;
    flex-shrink: 0;
}
.selected-user-card__info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.selected-user-card__topline {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.selected-user-card__name {
    font-size: 0.88rem;
    color: #fff;
    font-weight: 600;
}
.badge-idol {
    padding: 0.1rem 0.35rem;
    background: rgba(255, 178, 239, 0.2);
    border: 1px solid rgba(255, 178, 239, 0.4);
    border-radius: 4px;
    color: #ffb2ef;
    font-size: 0.68rem;
    font-weight: 600;
}
.badge-idol--mini {
    font-size: 0.64rem;
    padding: 0.05rem 0.28rem;
}
.selected-user-card__email {
    font-size: 0.76rem;
    color: rgba(255, 255, 255, 0.5);
}
.selected-user-card__balance {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.65);
    margin-top: 0.15rem;
}
.selected-user-card__balance strong {
    color: #34d399;
}
.btn-change-user {
    padding: 0.4rem 0.75rem;
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 7px;
    color: #c084fc;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}
.btn-change-user:hover {
    background: rgba(168, 85, 247, 0.16);
    border-color: rgba(168, 85, 247, 0.35);
    color: #fff;
}

/* Direction Toggle */
.direction-toggle {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}
.dir-btn {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.7rem 0.85rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.09);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    text-align: left;
}
.dir-btn__icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    font-weight: 700;
    line-height: 1;
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.5);
    flex-shrink: 0;
    transition: all 0.15s ease;
}
.dir-btn__text {
    display: flex;
    flex-direction: column;
}
.dir-btn__title {
    font-size: 0.84rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.8);
    transition: color 0.15s ease;
}
.dir-btn__hint {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.4);
    transition: color 0.15s ease;
}

/* Active Credit */
.dir-btn--credit.dir-btn--active {
    background: rgba(16, 185, 129, 0.12);
    border-color: #10b981;
    box-shadow: 0 2px 14px rgba(16, 185, 129, 0.16);
}
.dir-btn--credit.dir-btn--active .dir-btn__icon {
    background: #10b981;
    color: #0b2e21;
}
.dir-btn--credit.dir-btn--active .dir-btn__title {
    color: #34d399;
}
.dir-btn--credit.dir-btn--active .dir-btn__hint {
    color: rgba(52, 211, 153, 0.85);
}

/* Active Debit */
.dir-btn--debit.dir-btn--active {
    background: rgba(239, 68, 68, 0.12);
    border-color: #ef4444;
    box-shadow: 0 2px 14px rgba(239, 68, 68, 0.16);
}
.dir-btn--debit.dir-btn--active .dir-btn__icon {
    background: #ef4444;
    color: #3b0d0d;
}
.dir-btn--debit.dir-btn--active .dir-btn__title {
    color: #f87171;
}
.dir-btn--debit.dir-btn--active .dir-btn__hint {
    color: rgba(248, 113, 113, 0.85);
}

/* Amount Input Wrap */
.amount-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}
.input--amount {
    width: 100%;
    height: 44px;
    padding-right: 2.2rem;
    font-size: 1.15rem;
    font-weight: 700;
    border-radius: 9px;
    color: #fff;
    transition: all 0.15s ease;
}
.input--amount.input--error {
    border-color: #ef4444 !important;
    background: rgba(239, 68, 68, 0.08) !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
}
.currency-label {
    position: absolute;
    right: 0.85rem;
    color: rgba(255, 255, 255, 0.45);
    font-weight: 700;
    font-size: 1rem;
    pointer-events: none;
}

/* Amount Presets */
.amount-presets {
    display: flex;
    gap: 0.45rem;
    margin-top: 0.15rem;
    flex-wrap: wrap;
}
.preset-btn {
    padding: 0.32rem 0.65rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.78rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}
.preset-btn:hover {
    background: rgba(168, 85, 247, 0.15);
    border-color: rgba(168, 85, 247, 0.35);
    color: #fff;
}
.preset-btn--active {
    background: rgba(168, 85, 247, 0.25);
    border-color: #a855f7;
    color: #c084fc;
}

/* Balance Outcome Banner */
.balance-outcome-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    padding: 0.65rem 0.85rem;
    border-radius: 8px;
    margin-top: 0.35rem;
    font-size: 0.82rem;
    transition: all 0.2s ease;
}
.balance-outcome-banner--credit {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.25);
    color: rgba(255, 255, 255, 0.9);
}
.balance-outcome-banner--credit .outcome-icon {
    color: #34d399;
}
.balance-outcome-banner--debit {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.25);
    color: rgba(255, 255, 255, 0.9);
}
.balance-outcome-banner--debit .outcome-icon {
    color: #60a5fa;
}
.balance-outcome-banner--error {
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.35);
    color: #fca5a5;
}
.balance-outcome-banner--error .outcome-icon {
    color: #ef4444;
}
.outcome-icon {
    font-size: 1.15rem;
    margin-top: 0.1rem;
    flex-shrink: 0;
}
.outcome-content {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.outcome-title {
    font-weight: 600;
    font-size: 0.78rem;
    opacity: 0.85;
}
.outcome-flow {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.84rem;
}
.outcome-arrow {
    color: rgba(255, 255, 255, 0.4);
}
.outcome-target {
    color: #fff;
}
.outcome-delta {
    font-size: 0.78rem;
    font-weight: 600;
}
.delta--plus { color: #34d399; }
.delta--minus { color: #f87171; }
.outcome-desc {
    font-size: 0.78rem;
    line-height: 1.35;
}

/* Reason Tags */
.reason-tags {
    display: flex;
    gap: 0.45rem;
    flex-wrap: wrap;
    margin-top: 0.15rem;
}
.reason-tag-btn {
    padding: 0.28rem 0.55rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.74rem;
    cursor: pointer;
    transition: all 0.15s ease;
}
.reason-tag-btn:hover {
    background: rgba(168, 85, 247, 0.12);
    border-color: rgba(168, 85, 247, 0.3);
    color: #fff;
}
.reason-tag-btn--active {
    background: rgba(168, 85, 247, 0.2);
    border-color: #a855f7;
    color: #c084fc;
}

/* Notification Checkbox Card */
.field--checkbox-card {
    padding: 0.75rem 0.95rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 9px;
    transition: all 0.18s ease;
}
.field--checkbox-card:hover {
    border-color: rgba(255, 255, 255, 0.16);
    background: rgba(255, 255, 255, 0.045);
}
.field--checkbox-card--active {
    border-color: rgba(255, 178, 239, 0.4);
    background: rgba(255, 178, 239, 0.06);
    box-shadow: 0 2px 12px rgba(255, 178, 239, 0.08);
}
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    cursor: pointer;
    user-select: none;
}
.checkbox-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}
.checkbox-custom {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    background: rgba(255, 255, 255, 0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    color: #000;
    flex-shrink: 0;
    transition: all 0.15s ease;
}
.checkbox-label:hover .checkbox-custom {
    border-color: rgba(255, 178, 239, 0.6);
}
.checkbox-input:checked + .checkbox-custom {
    background: #ffb2ef;
    border-color: #ffb2ef;
    color: #0d0f1a;
}
.checkbox-title {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.85rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.9);
}
.checkbox-bell-icon {
    font-size: 0.95rem;
    color: #ffb2ef;
}
.checkbox-hint {
    margin: 0.25rem 0 0 1.8rem;
    font-size: 0.76rem;
    color: rgba(255, 255, 255, 0.45);
    line-height: 1.35;
}

/* Errors */
.err {
    font-size: 0.75rem;
    color: #f87171;
    margin: 0.15rem 0 0 0;
}
.err--box {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
}
</style>
