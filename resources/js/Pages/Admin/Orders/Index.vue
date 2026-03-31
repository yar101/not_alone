<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    orders:     Object,
    counts:     Object,
    filters:    Object,
    categories: Array,
    statuses:   Array,
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
    }, { preserveState: false });
}

function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
}

function setStatus(val) {
    status.value = val;
    applyFilters();
}

watch([categoryId, dateFrom, dateTo], applyFilters);

// ─── Status modal ─────────────────────────────────────────────────────────────

const modalOrder    = ref(null);
const modalStatus   = ref('');
const modalNote     = ref('');
const modalLoading  = ref(false);
const modalHistory  = ref([]);
const historyLoading = ref(false);

async function openModal(order) {
    modalOrder.value  = order;
    modalStatus.value = order.status;
    modalNote.value   = '';
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
}

function submitStatus() {
    if (!modalOrder.value || modalLoading.value) return;
    modalLoading.value = true;
    router.patch(
        route('admin.orders.status', modalOrder.value.id),
        { status: modalStatus.value, admin_note: modalNote.value || undefined },
        {
            preserveScroll: true,
            onSuccess: () => { closeModal(); },
            onFinish: () => { modalLoading.value = false; },
        }
    );
}
</script>

<template>
    <div class="page">
        <div class="page-header">
            <h1 class="page-title">Заказы</h1>
        </div>

        <!-- Status tabs -->
        <div class="status-tabs">
            <button
                v-for="tab in [
                    { value: '',           label: 'Все',         count: counts.total },
                    { value: 'pending',    label: 'Ожидают',     count: counts.pending },
                    { value: 'accepted',   label: 'Приняты',     count: counts.accepted },
                    { value: 'paid',       label: 'Оплачены',    count: counts.paid },
                    { value: 'completed',  label: 'Выполнены',   count: counts.completed },
                    { value: 'cancelled',  label: 'Отменены',    count: counts.cancelled },
                    { value: 'refunded',   label: 'Возвращены',  count: counts.refunded },
                ]"
                :key="tab.value"
                class="status-tab"
                :class="{ 'status-tab--active': status === tab.value }"
                @click="setStatus(tab.value)"
            >
                {{ tab.label }}
                <span class="status-tab__count">{{ tab.count }}</span>
            </button>
        </div>

        <!-- Filters row -->
        <div class="filters">
            <input
                v-model="search"
                class="input filter-search"
                placeholder="Поиск по ID, имени, email…"
                @input="onSearchInput"
            />
            <select v-model="categoryId" class="input filter-select">
                <option value="">Все категории</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <input v-model="dateFrom" type="date" class="input filter-date" title="Дата от" />
            <input v-model="dateTo"   type="date" class="input filter-date" title="Дата до" />
        </div>

        <!-- Table -->
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Заказчик</th>
                        <th>Айдол</th>
                        <th>Категории</th>
                        <th>Услуг</th>
                        <th>Сумма</th>
                        <th>Статус</th>
                        <th>Дата</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="order in orders.data" :key="order.id">
                        <td class="td-id">{{ order.id }}</td>
                        <td class="td-user">
                            <div class="user-cell">
                                <div class="user-name">{{ order.customer.name }}</div>
                                <div class="user-email">{{ order.customer.email }}</div>
                            </div>
                        </td>
                        <td class="td-user">
                            <div class="user-cell">
                                <div class="user-name">{{ order.idol.name }}</div>
                                <div class="user-email">{{ order.idol.email }}</div>
                            </div>
                        </td>
                        <td class="td-cats">
                            <span v-if="order.categories.length" class="cats">
                                <span v-for="(cat, i) in order.categories" :key="i" class="cat-chip">{{ cat }}</span>
                            </span>
                            <span v-else class="no-val">—</span>
                        </td>
                        <td class="td-count">{{ order.items_count }}</td>
                        <td class="td-total">{{ order.total.toLocaleString('ru-RU') }} ₽</td>
                        <td class="td-status">
                            <span
                                class="badge"
                                :class="`badge--${order.status_color}`"
                            >{{ order.status_label }}</span>
                            <div v-if="order.cancel_reason" class="cancel-reason" :title="order.cancel_reason">
                                {{ order.cancel_reason }}
                            </div>
                        </td>
                        <td class="td-date">{{ order.created_at }}</td>
                        <td class="td-action">
                            <button class="btn-edit" @click="openModal(order)">Статус</button>
                        </td>
                    </tr>
                    <tr v-if="!orders.data.length">
                        <td colspan="9" class="empty-row">Заказов нет</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination" v-if="orders.last_page > 1">
            <a
                v-for="link in orders.links"
                :key="link.label"
                :href="link.url || '#'"
                class="page-link"
                :class="{ 'page-link--active': link.active, 'page-link--disabled': !link.url }"
                v-html="link.label"
                @click.prevent="link.url && router.get(link.url, {}, { preserveState: false })"
            />
        </div>
    </div>

    <!-- Status modal -->
    <Teleport to="body">
        <div v-if="modalOrder" class="overlay" @click.self="closeModal">
            <div class="modal">
                <div class="modal__header">
                    <span>Заказ #{{ modalOrder.id }}</span>
                    <button class="modal__close" @click="closeModal">✕</button>
                </div>
                <div class="modal__body">
                    <div class="field">
                        <label>Текущий статус</label>
                        <span class="badge" :class="`badge--${modalOrder.status_color}`">{{ modalOrder.status_label }}</span>
                    </div>
                    <div class="field">
                        <label>Новый статус</label>
                        <select v-model="modalStatus" class="input">
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Примечание администратора (необязательно)</label>
                        <textarea v-model="modalNote" class="input input--textarea" placeholder="Причина изменения статуса…" rows="3"></textarea>
                    </div>
                    <div class="modal__actions">
                        <button class="btn-cancel" @click="closeModal">Отмена</button>
                        <button
                            class="btn-submit"
                            :disabled="modalLoading || modalStatus === modalOrder.status"
                            @click="submitStatus"
                        >{{ modalLoading ? 'Сохраняем…' : 'Сохранить' }}</button>
                    </div>

                    <!-- History -->
                    <div class="history">
                        <div class="history__title">История статусов</div>
                        <div v-if="historyLoading" class="history__empty">Загрузка…</div>
                        <div v-else-if="!modalHistory.length" class="history__empty">Изменений не было</div>
                        <div v-else class="history__list">
                            <div v-for="(h, i) in modalHistory" :key="i" class="history__item">
                                <div class="history__arrow">
                                    <span v-if="h.from" class="history__from">{{ h.from }}</span>
                                    <span v-if="h.from" class="history__chevron">→</span>
                                    <span class="badge badge--sm" :class="`badge--${h.to_color}`">{{ h.to }}</span>
                                </div>
                                <div class="history__meta">
                                    <span class="history__actor">{{ h.actor_type === 'admin' ? 'Администратор' : 'Пользователь' }} #{{ h.actor_id }}</span>
                                    <span class="history__date">{{ h.created_at }}</span>
                                </div>
                                <div v-if="h.note" class="history__note">{{ h.note }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.page { max-width: none; }
.page-header { margin-bottom: 1.5rem; }
.page-title { font-size: 1.35rem; font-weight: 700; color: rgba(255,255,255,0.9); margin: 0; }

/* Status tabs */
.status-tabs { display: flex; gap: 0; margin-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.08); }
.status-tab { padding: 0.45rem 1rem; background: none; border: none; border-bottom: 2px solid transparent; color: rgba(255,255,255,0.4); font-size: 0.84rem; cursor: pointer; font-family: inherit; margin-bottom: -1px; transition: color 0.15s; display: flex; align-items: center; gap: 0.4rem; }
.status-tab:hover { color: rgba(255,255,255,0.7); }
.status-tab--active { color: #9B6EE8; border-bottom-color: #9B6EE8; }
.status-tab__count { display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 0.3rem; background: rgba(255,255,255,0.07); border-radius: 99px; font-size: 0.7rem; color: rgba(255,255,255,0.45); }

/* Filters */
.filters { display: flex; gap: 0.6rem; margin-bottom: 1rem; flex-wrap: wrap; }
.filter-search { flex: 1; min-width: 200px; }
.filter-select { width: 180px; }
.filter-date { width: 140px; }

/* Table */
.table-wrap { overflow-x: auto; }
.table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
.table th { padding: 0.55rem 0.75rem; text-align: left; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.07em; color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.07); white-space: nowrap; }
.table td { padding: 0.65rem 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: top; }
.table tr:hover td { background: rgba(255,255,255,0.02); }

.td-id    { color: rgba(255,255,255,0.3); font-size: 0.78rem; white-space: nowrap; }
.td-user  { min-width: 140px; }
.td-cats  { min-width: 120px; }
.td-count { white-space: nowrap; color: rgba(255,255,255,0.6); text-align: center; }
.td-total { white-space: nowrap; color: rgba(255,255,255,0.85); font-weight: 600; }
.td-date  { white-space: nowrap; color: rgba(255,255,255,0.3); font-size: 0.78rem; }
.td-action { white-space: nowrap; }
.td-status { min-width: 100px; }

.user-cell { display: flex; flex-direction: column; gap: 0.1rem; }
.user-name  { font-size: 0.84rem; color: rgba(255,255,255,0.8); }
.user-email { font-size: 0.72rem; color: rgba(255,255,255,0.3); }

.cats { display: flex; flex-wrap: wrap; gap: 0.25rem; }
.cat-chip { display: inline-block; padding: 0.15rem 0.45rem; background: rgba(155,110,232,0.1); border: 1px solid rgba(155,110,232,0.2); border-radius: 99px; font-size: 0.72rem; color: rgba(190,145,255,0.75); white-space: nowrap; }

.cancel-reason { font-size: 0.7rem; color: rgba(239,68,68,0.5); margin-top: 0.25rem; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: help; }

.no-val { color: rgba(255,255,255,0.2); }
.empty-row { text-align: center; padding: 2rem; color: rgba(255,255,255,0.2); font-size: 0.88rem; }

/* Badges */
.badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 99px; font-size: 0.72rem; font-weight: 600; }
.badge--sm { padding: 0.15rem 0.5rem; font-size: 0.68rem; }
.badge--yellow { background: rgba(251,191,36,0.12);  color: rgba(251,191,36,0.9); }
.badge--green  { background: rgba(74,222,128,0.1);   color: rgba(74,222,128,0.8); }
.badge--blue   { background: rgba(96,165,250,0.12);  color: rgba(96,165,250,0.9); }
.badge--purple { background: rgba(167,139,250,0.12); color: rgba(167,139,250,0.9); }
.badge--red    { background: rgba(239,68,68,0.1);    color: rgba(239,68,68,0.7); }
.badge--orange { background: rgba(251,146,60,0.12);  color: rgba(251,146,60,0.9); }

/* History */
.history { border-top: 1px solid rgba(255,255,255,0.07); padding-top: 0.8rem; margin-top: 0.2rem; }
.history__title { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.07em; color: rgba(255,255,255,0.25); margin-bottom: 0.6rem; }
.history__empty { font-size: 0.8rem; color: rgba(255,255,255,0.2); }
.history__list { display: flex; flex-direction: column; gap: 0.6rem; }
.history__item { display: flex; flex-direction: column; gap: 0.2rem; }
.history__arrow { display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; }
.history__from { font-size: 0.78rem; color: rgba(255,255,255,0.35); }
.history__chevron { font-size: 0.7rem; color: rgba(255,255,255,0.2); }
.history__meta { display: flex; gap: 0.75rem; font-size: 0.72rem; color: rgba(255,255,255,0.25); }
.history__note { font-size: 0.75rem; color: rgba(255,255,255,0.4); font-style: italic; padding-left: 0.25rem; border-left: 2px solid rgba(255,255,255,0.1); }

/* Action buttons */
.btn-edit { padding: 0.3rem 0.7rem; border-radius: 3px; font-size: 0.78rem; cursor: pointer; font-family: inherit; transition: background 0.15s; border: 1px solid rgba(255,255,255,0.15); background: transparent; color: rgba(255,255,255,0.6); }
.btn-edit:hover { background: rgba(255,255,255,0.08); }

/* Pagination */
.pagination { display: flex; gap: 0.25rem; margin-top: 1.5rem; flex-wrap: wrap; }
.page-link { padding: 0.28rem 0.6rem; border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); font-size: 0.8rem; text-decoration: none; cursor: pointer; }
.page-link--active   { border-color: rgba(155,110,232,0.6); color: #9B6EE8; background: rgba(155,110,232,0.1); }
.page-link--disabled { opacity: 0.3; pointer-events: none; }

/* Modal */
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #0a0a0f; border: 1px solid rgba(255,255,255,0.12); border-radius: 3px; width: 100%; max-width: 400px; margin: 1rem; }
.modal__header { display: flex; align-items: center; justify-content: space-between; padding: 0.8rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.07); font-size: 0.88rem; color: rgba(255,255,255,0.85); font-weight: 600; }
.modal__close { background: none; border: none; color: rgba(255,255,255,0.4); cursor: pointer; font-size: 1rem; }
.modal__body { padding: 1rem; display: flex; flex-direction: column; gap: 0.8rem; }
.modal__actions { display: flex; justify-content: flex-end; gap: 0.5rem; padding-top: 0.25rem; }

.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.07em; color: rgba(255,255,255,0.35); }

.input { padding: 0.45rem 0.7rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 3px; color: rgba(255,255,255,0.85); font-family: inherit; font-size: 0.88rem; outline: none; transition: border-color 0.15s; box-sizing: border-box; width: 100%; }
.input:focus { border-color: rgba(190,145,255,0.45); }
.input--textarea { resize: vertical; min-height: 72px; }

.btn-cancel { padding: 0.45rem 0.9rem; border: 1px solid rgba(255,255,255,0.12); border-radius: 3px; background: transparent; color: rgba(255,255,255,0.4); font-family: inherit; font-size: 0.82rem; cursor: pointer; }
.btn-submit { padding: 0.45rem 1rem; border: 1px solid rgba(190,145,255,0.45); border-radius: 3px; background: rgba(190,145,255,0.1); color: rgba(255,255,255,0.9); font-family: inherit; font-size: 0.82rem; cursor: pointer; }
.btn-submit:disabled { opacity: 0.5; cursor: default; }
</style>
