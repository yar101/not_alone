<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { ElMessageBox } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    packs:   Object,
    filters: Object,
    counts:  Object,
});

const filterStatus = ref(props.filters?.status ?? 'pending_review');
const filterSearch = ref(props.filters?.search ?? '');
let searchDebounce = null;

function applyFilters() {
    router.get(route('admin.content-packs.index'), {
        status: filterStatus.value,
        search: filterSearch.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}

function switchStatus(status) {
    filterStatus.value = status;
    applyFilters();
}

function onSearchInput() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(applyFilters, 300);
}

// ─── Bulk selection ──────────────────────────────────────────────────────────
const selectedIds = ref(new Set());

const allSelected = computed(() => {
    const list = props.packs?.data ?? [];
    return list.length > 0 && list.every(p => selectedIds.value.has(p.id));
});

function toggleRow(id) {
    const next = new Set(selectedIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selectedIds.value = next;
}

function toggleSelectAll() {
    const list = props.packs?.data ?? [];
    if (allSelected.value) {
        selectedIds.value = new Set();
    } else {
        selectedIds.value = new Set(list.map(p => p.id));
    }
}

function bulkApprove() {
    ElMessageBox.confirm(`Одобрить выбранные контент-паки (${selectedIds.value.size} шт.)?`, 'Массовое одобрение', {
        confirmButtonText: 'Одобрить',
        cancelButtonText: 'Отмена',
        type: 'success',
    }).then(() => {
        router.post(route('admin.content-packs.bulk-approve'), {
            ids: [...selectedIds.value],
        }, {
            preserveScroll: true,
            onSuccess: () => { selectedIds.value = new Set(); },
        });
    }).catch(() => {});
}

function bulkReject() {
    ElMessageBox.confirm(`Отклонить и удалить файлы выбранных контент-паков (${selectedIds.value.size} шт.)?`, 'Массовое отклонение', {
        confirmButtonText: 'Отклонить',
        cancelButtonText: 'Отмена',
        type: 'warning',
        confirmButtonClass: 'el-button--danger',
    }).then(() => {
        router.post(route('admin.content-packs.bulk-reject'), {
            ids: [...selectedIds.value],
        }, {
            preserveScroll: true,
            onSuccess: () => { selectedIds.value = new Set(); },
        });
    }).catch(() => {});
}

const STATUS_LABELS = {
    pending_review: 'На рассмотрении',
    approved:       'Одобрен',
    published:      'Опубликован',
    has_remarks:    'Есть замечания',
    all:            'Все',
};

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
</script>

<template>
    <div class="cp-index">
        <AdminPageHeader
            title="Контент-паки"
            subtitle="Модерация приватных фото- и контент-паков айдолов"
            :count="packs.total"
        />

        <!-- Status tabs -->
        <div class="cp-tabs">
            <button
                v-for="(label, key) in STATUS_LABELS"
                :key="key"
                class="cp-tab"
                :class="{ 'cp-tab--active': filterStatus === key }"
                @click="switchStatus(key)"
            >
                {{ label }}
                <span v-if="counts[key] && key !== 'all'" class="cp-tab__badge">{{ counts[key] }}</span>
                <span v-if="key === 'pending_review' && counts.resubmitted" class="cp-tab__badge cp-tab__badge--resubmit" :title="counts.resubmitted + ' с исправлениями'">
                    {{ counts.resubmitted }} ✓
                </span>
            </button>
        </div>

        <!-- Search & Bulk bar -->
        <div class="cp-toolbar">
            <input
                v-model="filterSearch"
                class="cp-search"
                type="text"
                placeholder="Поиск по названию или айдолу..."
                @input="onSearchInput"
            />

            <!-- Bulk actions panel -->
            <transition name="fade">
                <div v-if="selectedIds.size > 0" class="cp-bulk-actions">
                    <span class="cp-bulk-count">Выбрано: {{ selectedIds.size }}</span>
                    <button class="cp-btn cp-btn--approve" @click="bulkApprove">
                        Одобрить выбранные
                    </button>
                    <button class="cp-btn cp-btn--reject" @click="bulkReject">
                        Отклонить выбранные
                    </button>
                </div>
            </transition>
        </div>

        <!-- Table -->
        <div class="cp-table-wrap">
            <table class="cp-table">
                <thead>
                    <tr>
                        <th style="width: 36px;">
                            <label class="mod-cb">
                                <input
                                    type="checkbox"
                                    class="mod-cb__input"
                                    :checked="allSelected"
                                    :indeterminate="selectedIds.size > 0 && !allSelected"
                                    @change="toggleSelectAll"
                                />
                                <span class="mod-cb__box">
                                    <svg v-if="allSelected" class="mod-cb__check" viewBox="0 0 10 8" fill="none">
                                        <path d="M1 4l3 3 5-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <svg v-else-if="selectedIds.size > 0" class="mod-cb__minus" viewBox="0 0 10 2" fill="none">
                                        <path d="M1 1h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    </svg>
                                </span>
                            </label>
                        </th>
                        <th>Обложка</th>
                        <th>Айдол</th>
                        <th>Название</th>
                        <th>Цена</th>
                        <th>Статус</th>
                        <th>Создан</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="pack in packs.data"
                        :key="pack.id"
                        :class="{ 'cp-tr--selected': selectedIds.has(pack.id) }"
                    >
                        <td>
                            <label class="mod-cb">
                                <input
                                    type="checkbox"
                                    class="mod-cb__input"
                                    :checked="selectedIds.has(pack.id)"
                                    @change="toggleRow(pack.id)"
                                />
                                <span class="mod-cb__box">
                                    <svg v-if="selectedIds.has(pack.id)" class="mod-cb__check" viewBox="0 0 10 8" fill="none">
                                        <path d="M1 4l3 3 5-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                            </label>
                        </td>
                        <td class="cp-td-cover">
                            <img v-if="pack.cover_url" :src="pack.cover_url" alt="" class="cp-cover-thumb" />
                            <div v-else class="cp-cover-empty">—</div>
                        </td>
                        <td class="cp-td-idol">
                            <div class="cp-idol-info">
                                <img v-if="pack.user?.avatar_url" :src="pack.user.avatar_url" class="cp-idol-avatar" alt="" />
                                <div v-else class="cp-idol-avatar cp-idol-avatar--empty">
                                    {{ pack.user?.name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <span>{{ pack.user?.name ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="cp-td-title">
                            {{ pack.title }}
                            <span v-if="pack.resubmitted_at" class="cp-resubmit-badge" :title="'Исправлено: ' + formatDate(pack.resubmitted_at)">
                                Исправлено
                            </span>
                        </td>
                        <td>{{ pack.price }} ₽</td>
                        <td>
                            <span class="cp-status" :class="'cp-status--' + pack.status">
                                {{ STATUS_LABELS[pack.status] || pack.status }}
                            </span>
                        </td>
                        <td>{{ formatDate(pack.created_at) }}</td>
                        <td>
                            <Link :href="route('admin.content-packs.show', pack.id)" class="cp-link">
                                Рассмотреть →
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="!packs.data?.length">
                        <td colspan="8" class="cp-empty">Нет паков</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <AdminPagination :links="packs.links" />
    </div>
</template>

<style scoped>
.cp-index { padding: 1.5rem; }

.cp-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; flex-wrap: wrap; }

.cp-tab {
    padding: 0.4rem 0.85rem;
    border-radius: 6px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.5);
    font-size: 0.85rem;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.cp-tab:hover { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.8); }
.cp-tab--active { background: rgba(255, 178, 239,0.15); border-color: rgba(255, 178, 239,0.4); color: rgba(200,200,255,0.9); }
.cp-tab__badge { background: rgba(255,100,100,0.25); color: #ff9a9a; border-radius: 10px; padding: 1px 7px; font-size: 0.78rem; }

.cp-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.cp-search {
    width: 100%;
    max-width: 360px;
    padding: 0.5rem 0.8rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 7px;
    color: rgba(255,255,255,0.85);
    font-size: 0.9rem;
    font-family: inherit;
    outline: none;
}
.cp-search:focus {
    border-color: rgba(155, 110, 232, 0.5);
}

.cp-bulk-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(155, 110, 232, 0.08);
    border: 1px solid rgba(155, 110, 232, 0.2);
    padding: 0.35rem 0.75rem;
    border-radius: 8px;
}

.cp-bulk-count {
    font-size: 0.85rem;
    font-weight: 600;
    color: #c49eff;
}

.cp-btn {
    padding: 0.4rem 0.85rem;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.15s;
    font-family: inherit;
}

.cp-btn--approve {
    background: #10b981;
    color: #fff;
}
.cp-btn--approve:hover {
    background: #059669;
}

.cp-btn--reject {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.cp-btn--reject:hover {
    background: rgba(239, 68, 68, 0.25);
}

.cp-table-wrap { overflow-x: auto; margin-bottom: 1.25rem; }

.cp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
}

.cp-table th {
    text-align: left;
    padding: 0.6rem 0.75rem;
    color: rgba(255,255,255,0.35);
    font-weight: 500;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.cp-table td {
    padding: 0.7rem 0.75rem;
    vertical-align: middle;
    color: rgba(255,255,255,0.75);
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.cp-tr--selected {
    background: rgba(155, 110, 232, 0.08);
}

/* Checkbox */
.mod-cb {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    user-select: none;
}
.mod-cb__input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}
.mod-cb__box {
    width: 15px;
    height: 15px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.04);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: border-color 0.15s, background 0.15s;
    color: #9B6EE8;
}
.mod-cb:hover .mod-cb__box {
    border-color: rgba(155, 110, 232, 0.5);
    background: rgba(155, 110, 232, 0.06);
}
.mod-cb__input:checked ~ .mod-cb__box,
.mod-cb__input:indeterminate ~ .mod-cb__box {
    border-color: rgba(155, 110, 232, 0.7);
    background: rgba(155, 110, 232, 0.15);
}
.mod-cb__check,
.mod-cb__minus {
    width: 10px;
    height: 10px;
}

.cp-td-cover { width: 64px; }
.cp-cover-thumb { width: 48px; height: 64px; object-fit: cover; border-radius: 4px; display: block; }
.cp-cover-empty { width: 48px; height: 64px; background: rgba(255,255,255,0.04); border-radius: 4px; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.2); font-size: 0.75rem; }

.cp-idol-info { display: flex; align-items: center; gap: 0.5rem; }
.cp-idol-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.cp-idol-avatar--empty { background: rgba(255, 178, 239,0.15); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; color: rgba(255, 178, 239,0.7); }

.cp-td-title { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.cp-status { padding: 2px 8px; border-radius: 10px; font-size: 0.78rem; font-weight: 500; }
.cp-status--pending_review { background: rgba(255, 178, 239,0.15); color: #ffb2ef; }
.cp-status--approved       { background: rgba(100,210,160,0.15); color: #64d2a0; }
.cp-status--published      { background: rgba(100,210,255,0.15); color: #64d2ff; }
.cp-status--has_remarks    { background: rgba(255,123,123,0.15); color: #ff7b7b; }
.cp-status--rejected       { background: rgba(180,50,50,0.15); color: #ff6060; }

.cp-resubmit-badge {
    display: inline-block;
    margin-left: 0.4rem;
    padding: 1px 6px;
    border-radius: 8px;
    background: rgba(100,210,160,0.15);
    color: #64d2a0;
    font-size: 0.72rem;
    font-weight: 600;
    vertical-align: middle;
    white-space: nowrap;
}

.cp-tab__badge--resubmit {
    background: rgba(100,210,160,0.2);
    color: #64d2a0;
}

.cp-link { color: rgba(255, 178, 239,0.8); font-size: 0.85rem; text-decoration: none; white-space: nowrap; }
.cp-link:hover { color: #ffb2ef; }

.cp-empty { text-align: center; color: rgba(255,255,255,0.25); padding: 2rem; }
</style>
