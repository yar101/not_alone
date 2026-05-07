<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    suggestions:   Object,
    currentStatus: String,
    pendingCount:  Number,
    search:        String,
});

const STATUS_TABS = [
    { key: 'pending',     label: 'Ожидают' },
    { key: 'approved',    label: 'Одобрены' },
    { key: 'implemented', label: 'Реализованы' },
    { key: 'rejected',    label: 'Отклонены' },
    { key: 'all',         label: 'Все' },
];

const search = ref(props.search ?? '');

watch(search, (val) => {
    router.get(route('admin.traits.suggestions.index'), { status: props.currentStatus, search: val }, { preserveState: true, replace: true });
});

function switchStatus(status) {
    router.get(route('admin.traits.suggestions.index'), { status, search: search.value }, { preserveState: false });
}

function approve(id) {
    router.patch(route('admin.traits.suggestions.approve', id), {}, { preserveScroll: true });
}

function reject(id) {
    router.patch(route('admin.traits.suggestions.reject', id), {}, { preserveScroll: true });
}

function implement(id) {
    router.patch(route('admin.traits.suggestions.implement', id), {}, { preserveScroll: true });
}

function reopen(id) {
    router.patch(route('admin.traits.suggestions.reopen', id), {}, { preserveScroll: true });
}

function destroy(id) {
    router.delete(route('admin.traits.suggestions.destroy', id), { preserveScroll: true });
}

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('ru', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function statusClass(status) {
    if (status === 'pending') return 'status--pending';
    if (status === 'approved') return 'status--approved';
    if (status === 'implemented') return 'status--implemented';
    return 'status--rejected';
}

function statusLabel(status) {
    if (status === 'pending') return 'Ожидает';
    if (status === 'approved') return 'Одобрено';
    if (status === 'implemented') return 'Реализовано';
    return 'Отклонено';
}
</script>

<template>
    <div>
        <h1 class="page-title">
            Предложения черт
            <span v-if="pendingCount > 0" class="title-badge">{{ pendingCount }}</span>
        </h1>

        <div class="tabs-nav">
            <button
                v-for="t in STATUS_TABS"
                :key="t.key"
                class="tab-btn"
                :class="{ 'tab-btn--active': currentStatus === t.key }"
                @click="switchStatus(t.key)"
            >{{ t.label }}</button>
        </div>

        <div class="search-bar">
            <input v-model="search" class="search-input" placeholder="Поиск по названию..." />
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Пользователь</th>
                        <th>Предложение</th>
                        <th>Дата</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in suggestions.data" :key="s.id">
                        <td class="muted">{{ s.id }}</td>
                        <td>
                            <a
                                v-if="s.user"
                                :href="route('admin.users.show', s.user.id)"
                                class="user-link"
                                @click.stop
                            >{{ s.user.name }}</a>
                            <span v-else class="muted">—</span>
                        </td>
                        <td class="suggestion-cell">{{ s.name }}</td>
                        <td class="muted">{{ formatDate(s.created_at) }}</td>
                        <td>
                            <span :class="['status-badge', statusClass(s.status)]">
                                {{ statusLabel(s.status) }}
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <template v-if="s.status === 'pending'">
                                    <button class="btn-approve" @click="approve(s.id)">Одобрить</button>
                                    <button class="btn-danger"  @click="reject(s.id)">Отклонить</button>
                                </template>
                                <button v-if="s.status === 'approved'" class="btn-implement" @click="implement(s.id)">Реализовать</button>
                                <button v-if="s.status === 'implemented'" class="btn-reopen" @click="reopen(s.id)">На рассмотрение</button>
                                <button class="btn-delete" @click="destroy(s.id)">Удалить</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!suggestions.data.length">
                        <td colspan="6" class="empty-row">Предложений нет</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pagination" v-if="suggestions.last_page > 1">
            <a
                v-for="link in suggestions.links"
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
.page-title { font-size: 1.4rem; color: #fff; margin: 0 0 1.25rem; display: flex; align-items: center; gap: 0.6rem; }
.title-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 0.35rem; background: rgba(155,110,232,0.25); border: 1px solid rgba(155,110,232,0.4); color: #ffb2ef; font-size: 0.75rem; font-weight: 700; border-radius: 99px; }

.tabs-nav { display: flex; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 1.25rem; }
.tab-btn { padding: 0.6rem 1.25rem; background: none; border: none; border-bottom: 2px solid transparent; color: rgba(255,255,255,0.4); font-size: 0.88rem; cursor: pointer; font-family: inherit; transition: color 0.15s; margin-bottom: -1px; }
.tab-btn:hover { color: rgba(255,255,255,0.75); }
.tab-btn--active { color: #9B6EE8; border-bottom-color: #9B6EE8; }

.search-bar { margin-bottom: 1.25rem; }
.search-input { width: 100%; max-width: 360px; padding: 0.45rem 0.75rem; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); color: rgba(255,255,255,0.85); font-family: inherit; font-size: 0.88rem; outline: none; }
.search-input::placeholder { color: rgba(255,255,255,0.3); }
.search-input:focus { border-color: rgba(155,110,232,0.5); }

.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; padding: 0.75rem 1.25rem; font-size: 0.82rem; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 0.07em; border-bottom: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.025); }
.data-table td { padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.07); font-size: 0.93rem; color: rgba(255,255,255,0.75); vertical-align: middle; }
.data-table tr:hover td { background: rgba(255,255,255,0.03); }

.user-link { color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.85rem; }
.user-link:hover { color: #9B6EE8; }
.muted { color: rgba(255,255,255,0.35); font-size: 0.78rem; }
.suggestion-cell { font-size: 0.9rem; color: rgba(255,255,255,0.85); max-width: 300px; }
.empty-row { text-align: center; color: rgba(255,255,255,0.25); padding: 2.5rem; }

.status-badge { font-size: 0.68rem; padding: 0.12rem 0.45rem; font-weight: 700; white-space: nowrap; letter-spacing: 0.03em; text-transform: uppercase; }
.status--pending     { background: rgba(251,183,64,0.1);  color: #fbb740; border: 1px solid rgba(251,183,64,0.25); }
.status--approved    { background: rgba(74,222,128,0.1);  color: #4ade80; border: 1px solid rgba(74,222,128,0.25); }
.status--implemented { background: rgba(96,165,250,0.1);  color: #60a5fa; border: 1px solid rgba(96,165,250,0.25); }
.status--rejected    { background: rgba(239,68,68,0.1);   color: #f87171; border: 1px solid rgba(239,68,68,0.25); }

.actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }
.btn-approve { padding: 0.28rem 0.65rem; border: 1px solid rgba(76,222,143,0.4); background: rgba(76,222,143,0.07); color: #4cde8f; font-family: inherit; font-size: 0.77rem; cursor: pointer; }
.btn-approve:hover { background: rgba(76,222,143,0.18); }
.btn-danger { padding: 0.28rem 0.65rem; border: 1px solid rgba(239,68,68,0.4); background: rgba(239,68,68,0.07); color: #f87171; font-family: inherit; font-size: 0.77rem; cursor: pointer; }
.btn-danger:hover { background: rgba(239,68,68,0.18); }
.btn-implement { padding: 0.28rem 0.65rem; border: 1px solid rgba(96,165,250,0.4); background: rgba(96,165,250,0.07); color: #60a5fa; font-family: inherit; font-size: 0.77rem; cursor: pointer; }
.btn-implement:hover { background: rgba(96,165,250,0.18); }
.btn-reopen { padding: 0.28rem 0.65rem; border: 1px solid rgba(251,183,64,0.4); background: rgba(251,183,64,0.07); color: #fbb740; font-family: inherit; font-size: 0.77rem; cursor: pointer; }
.btn-reopen:hover { background: rgba(251,183,64,0.18); }
.btn-delete { padding: 0.28rem 0.65rem; border: 1px solid rgba(255,255,255,0.1); background: transparent; color: rgba(255,255,255,0.3); font-family: inherit; font-size: 0.77rem; cursor: pointer; }
.btn-delete:hover { border-color: rgba(239,68,68,0.3); color: #f87171; background: rgba(239,68,68,0.05); }

.pagination { display: flex; gap: 0.25rem; margin-top: 1.5rem; flex-wrap: wrap; }
.page-link { padding: 0.28rem 0.6rem; border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); font-size: 0.8rem; text-decoration: none; cursor: pointer; }
.page-link--active   { border-color: rgba(155,110,232,0.6); color: #9B6EE8; background: rgba(155,110,232,0.1); }
.page-link--disabled { opacity: 0.3; pointer-events: none; }
</style>
