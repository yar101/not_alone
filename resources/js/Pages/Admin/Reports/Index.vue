<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    reports:       Object,
    filter_status: String,
});

const REASON_LABELS = {
    spam:          'Спам',
    inappropriate: 'Неприемлемый контент',
    fraud:         'Мошенничество',
    harassment:    'Домогательства',
    other:         'Другое',
};

const STATUS_TABS = [
    { key: '', label: 'Все' },
    { key: 'pending', label: 'Ожидают' },
    { key: 'reviewed', label: 'Рассмотрены' },
    { key: 'dismissed', label: 'Отклонены' },
];

function switchStatus(status) {
    router.get(route('admin.reports.index'), { status: status || undefined }, { preserveState: false });
}

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('ru', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}

// Review / Dismiss modals
const showModal    = ref(false);
const modalAction  = ref('review');
const modalTarget  = ref(null);
const adminNote    = ref('');

function openModal(report, action) {
    modalTarget.value  = report;
    modalAction.value  = action;
    adminNote.value    = '';
    showModal.value    = true;
}

function closeModal() {
    showModal.value = false;
}

function submitModal() {
    const routeName = modalAction.value === 'review' ? 'admin.reports.review' : 'admin.reports.dismiss';
    router.patch(route(routeName, modalTarget.value.id), {
        admin_note: adminNote.value || null,
    }, {
        preserveScroll: false,
        onSuccess: closeModal,
    });
}
</script>

<template>
    <div>
        <h1 class="page-title">Жалобы</h1>

        <!-- Status tabs -->
        <div class="tabs-nav">
            <button
                v-for="t in STATUS_TABS"
                :key="t.key"
                class="tab-btn"
                :class="{ 'tab-btn--active': (filter_status ?? '') === t.key }"
                @click="switchStatus(t.key)"
            >{{ t.label }}</button>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Отправитель</th>
                        <th>На пользователя</th>
                        <th>Причина</th>
                        <th>Описание</th>
                        <th>Статус</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in reports.data" :key="r.id" class="row-link" @click="router.visit(route('admin.reports.show', r.id))">
                        <td>
                            <div class="mini-user">
                                <div>{{ r.reporter?.name ?? '—' }}</div>
                                <div class="muted">{{ r.reporter?.email ?? '' }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="mini-user" v-if="r.reported">
                                <Link :href="route('admin.users.show', r.reported.id)" class="user-link" @click.stop>{{ r.reported.name }}</Link>
                                <div class="muted">{{ r.reported.email }}</div>
                            </div>
                            <span v-else class="muted">—</span>
                        </td>
                        <td>{{ REASON_LABELS[r.reason] ?? r.reason }}</td>
                        <td class="details-cell">{{ r.details ? r.details.slice(0, 80) + (r.details.length > 80 ? '…' : '') : '—' }}</td>
                        <td>
                            <span :class="['status-badge', r.status === 'pending' ? 'status--pending' : r.status === 'reviewed' ? 'status--approved' : 'status--rejected']">
                                {{ r.status === 'pending' ? 'Ожидает' : r.status === 'reviewed' ? 'Рассмотрена' : 'Отклонена' }}
                            </span>
                        </td>
                        <td class="muted">{{ formatDate(r.created_at) }}</td>
                        <td>
                            <div class="actions" v-if="r.status === 'pending'" @click.stop>
                                <button class="btn-approve" @click="openModal(r, 'review')">Рассмотреть</button>
                                <button class="btn-danger"  @click="openModal(r, 'dismiss')">Отклонить</button>
                            </div>
                            <Link
                                v-else
                                :href="route('admin.reports.show', r.id)"
                                class="btn-view"
                                @click.stop
                            >Подробнее →</Link>
                        </td>
                    </tr>
                    <tr v-if="!reports.data.length">
                        <td colspan="7" class="empty-row">Жалоб нет</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pagination" v-if="reports.last_page > 1">
            <a
                v-for="link in reports.links"
                :key="link.label"
                :href="link.url || '#'"
                class="page-link"
                :class="{ 'page-link--active': link.active, 'page-link--disabled': !link.url }"
                v-html="link.label"
                @click.prevent="link.url && router.get(link.url, {}, { preserveState: true })"
            />
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="overlay" @click.self="closeModal">
                <div class="modal">
                    <div class="modal__header">
                        <span>{{ modalAction === 'review' ? 'Рассмотреть жалобу' : 'Отклонить жалобу' }}</span>
                        <button class="modal__close" @click="closeModal">✕</button>
                    </div>
                    <form @submit.prevent="submitModal" class="modal__body">
                        <div class="field">
                            <label>Заметка администратора (необязательно)</label>
                            <textarea v-model="adminNote" class="input input--textarea" rows="3" maxlength="1000" />
                        </div>
                        <div class="modal__actions">
                            <button type="button" class="btn-cancel" @click="closeModal">Отмена</button>
                            <button type="submit" class="btn-submit">Подтвердить</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.page-title { font-size: 1.4rem; color: #fff; margin: 0 0 1.25rem; }

.tabs-nav { display: flex; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 1.5rem; }
.tab-btn { padding: 0.6rem 1.25rem; background: none; border: none; border-bottom: 2px solid transparent; color: rgba(255,255,255,0.4); font-size: 0.88rem; cursor: pointer; font-family: inherit; transition: color 0.15s; margin-bottom: -1px; }
.tab-btn:hover { color: rgba(255,255,255,0.75); }
.tab-btn--active { color: #9B6EE8; border-bottom-color: #9B6EE8; }

.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; padding: 0.75rem 1.25rem; font-size: 0.82rem; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 0.07em; border-bottom: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.025); }
.data-table td { padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.07); font-size: 0.93rem; color: rgba(255,255,255,0.75); vertical-align: middle; }
.data-table tr:hover td { background: rgba(255,255,255,0.03); }
.row-link { cursor: pointer; }

.mini-user { display: flex; flex-direction: column; gap: 0.15rem; }
.user-link { color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.85rem; }
.user-link:hover { color: #9B6EE8; }
.muted { color: rgba(255,255,255,0.35); font-size: 0.78rem; }
.details-cell { max-width: 260px; font-size: 0.82rem; color: rgba(255,255,255,0.4); }
.empty-row { text-align: center; color: rgba(255,255,255,0.25); padding: 2.5rem; }

.status-badge { font-size: 0.68rem; padding: 0.12rem 0.45rem; font-weight: 700; white-space: nowrap; letter-spacing: 0.03em; text-transform: uppercase; }
.status--pending  { background: rgba(251,183,64,0.1); color: #fbb740; border: 1px solid rgba(251,183,64,0.25); }
.status--approved { background: rgba(74,222,128,0.1); color: #4ade80; border: 1px solid rgba(74,222,128,0.25); }
.status--rejected { background: rgba(239,68,68,0.1);  color: #f87171; border: 1px solid rgba(239,68,68,0.25); }

.actions { display: flex; gap: 0.4rem; }
.btn-view { padding: 0.28rem 0.65rem; border: 1px solid rgba(155,110,232,0.35); background: rgba(155,110,232,0.07); color: rgba(190,145,255,0.8); font-family: inherit; font-size: 0.77rem; text-decoration: none; white-space: nowrap; }
.btn-view:hover { background: rgba(155,110,232,0.18); }
.btn-approve { padding: 0.28rem 0.65rem; border: 1px solid rgba(76,222,143,0.4); background: rgba(76,222,143,0.07); color: #4cde8f; font-family: inherit; font-size: 0.77rem; cursor: pointer; }
.btn-approve:hover { background: rgba(76,222,143,0.18); }
.btn-danger { padding: 0.28rem 0.65rem; border: 1px solid rgba(239,68,68,0.4); background: rgba(239,68,68,0.07); color: #f87171; font-family: inherit; font-size: 0.77rem; cursor: pointer; }
.btn-danger:hover { background: rgba(239,68,68,0.18); }

.pagination { display: flex; gap: 0.25rem; margin-top: 1.5rem; flex-wrap: wrap; }
.page-link { padding: 0.28rem 0.6rem; border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); font-size: 0.8rem; text-decoration: none; cursor: pointer; }
.page-link--active   { border-color: rgba(155,110,232,0.6); color: #9B6EE8; background: rgba(155,110,232,0.1); }
.page-link--disabled { opacity: 0.3; pointer-events: none; }

/* Modal */
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #0a0a0f; border: 1px solid rgba(255,255,255,0.12); width: 100%; max-width: 420px; margin: 1rem; max-height: 90vh; overflow-y: auto; }
.modal__header { display: flex; align-items: center; justify-content: space-between; padding: 0.8rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.07); font-size: 0.88rem; color: rgba(255,255,255,0.85); font-weight: 600; }
.modal__close { background: none; border: none; color: rgba(255,255,255,0.4); cursor: pointer; font-size: 1rem; }
.modal__body { padding: 1rem; display: flex; flex-direction: column; gap: 0.8rem; }
.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.07em; color: rgba(255,255,255,0.35); }
.input { padding: 0.45rem 0.7rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.85); font-family: inherit; font-size: 0.88rem; outline: none; transition: border-color 0.15s; box-sizing: border-box; width: 100%; }
.input:focus { border-color: rgba(190,145,255,0.45); }
.input--textarea { resize: vertical; min-height: 72px; }
.modal__actions { display: flex; justify-content: flex-end; gap: 0.5rem; }
.btn-cancel { padding: 0.45rem 0.9rem; border: 1px solid rgba(255,255,255,0.12); background: transparent; color: rgba(255,255,255,0.4); font-family: inherit; font-size: 0.82rem; cursor: pointer; }
.btn-submit { padding: 0.45rem 1rem; border: 1px solid rgba(190,145,255,0.45); background: rgba(190,145,255,0.1); color: rgba(255,255,255,0.9); font-family: inherit; font-size: 0.82rem; cursor: pointer; }
</style>
