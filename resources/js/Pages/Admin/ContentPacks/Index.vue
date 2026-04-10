<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

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
    }, { preserveState: false });
}

function switchStatus(status) {
    filterStatus.value = status;
    applyFilters();
}

function onSearchInput() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(applyFilters, 300);
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
        <div class="cp-header">
            <h1 class="cp-heading">Контент-паки</h1>
        </div>

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

        <!-- Search -->
        <div class="cp-toolbar">
            <input
                v-model="filterSearch"
                class="cp-search"
                type="text"
                placeholder="Поиск по названию или айдолу..."
                @input="onSearchInput"
            />
        </div>

        <!-- Table -->
        <div class="cp-table-wrap">
            <table class="cp-table">
                <thead>
                    <tr>
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
                    <tr v-for="pack in packs.data" :key="pack.id">
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
                        <td colspan="7" class="cp-empty">Нет паков</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="packs.last_page > 1" class="cp-pagination">
            <Link
                v-for="page in packs.last_page"
                :key="page"
                :href="packs.links?.find(l => l.label == page)?.url"
                class="cp-page-btn"
                :class="{ 'cp-page-btn--active': page === packs.current_page }"
            >{{ page }}</Link>
        </div>
    </div>
</template>

<style scoped>
.cp-index { padding: 1.5rem; }

.cp-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }

.cp-heading { font-size: 1.4rem; font-weight: 700; color: rgba(255,255,255,0.9); margin: 0; }

.cp-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }

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
.cp-tab--active { background: rgba(160,160,255,0.15); border-color: rgba(160,160,255,0.4); color: rgba(200,200,255,0.9); }
.cp-tab__badge { background: rgba(255,100,100,0.25); color: #ff9a9a; border-radius: 10px; padding: 1px 7px; font-size: 0.78rem; }

.cp-toolbar { margin-bottom: 1rem; }

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

.cp-table-wrap { overflow-x: auto; }

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

.cp-td-cover { width: 64px; }
.cp-cover-thumb { width: 48px; height: 64px; object-fit: cover; border-radius: 4px; display: block; }
.cp-cover-empty { width: 48px; height: 64px; background: rgba(255,255,255,0.04); border-radius: 4px; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.2); font-size: 0.75rem; }

.cp-idol-info { display: flex; align-items: center; gap: 0.5rem; }
.cp-idol-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.cp-idol-avatar--empty { background: rgba(160,160,255,0.15); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; color: rgba(160,160,255,0.7); }

.cp-td-title { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.cp-status { padding: 2px 8px; border-radius: 10px; font-size: 0.78rem; font-weight: 500; }
.cp-status--pending_review { background: rgba(160,160,255,0.15); color: #a0a0ff; }
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

.cp-link { color: rgba(160,160,255,0.8); font-size: 0.85rem; text-decoration: none; white-space: nowrap; }
.cp-link:hover { color: #a0a0ff; }

.cp-empty { text-align: center; color: rgba(255,255,255,0.25); padding: 2rem; }

.cp-pagination { display: flex; gap: 0.4rem; margin-top: 1.25rem; }
.cp-page-btn { padding: 0.35rem 0.7rem; border-radius: 5px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); color: rgba(255,255,255,0.6); font-size: 0.85rem; cursor: pointer; text-decoration: none; }
.cp-page-btn--active { background: rgba(160,160,255,0.2); border-color: rgba(160,160,255,0.4); color: #a0a0ff; }
</style>
