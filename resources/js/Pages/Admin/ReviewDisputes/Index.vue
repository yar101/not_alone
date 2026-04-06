<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    disputes:      Object,
    status_filter: String,
    search:        String,
    counts:        Object,
});

const selected    = ref(null);
const decision    = ref('');
const adminNote   = ref('');
const submitting  = ref(false);
const searchInput = ref(props.search ?? '');

function openModal(dispute) {
    selected.value  = dispute;
    decision.value  = '';
    adminNote.value = '';
}

function closeModal() {
    selected.value = null;
}

function setFilter(val) {
    router.get(route('admin.review-disputes.index'), { status: val, search: searchInput.value }, { preserveState: false });
}

function doSearch() {
    router.get(route('admin.review-disputes.index'), { status: props.status_filter, search: searchInput.value }, { preserveState: false });
}

function submitResolve() {
    if (!decision.value || submitting.value) return;
    submitting.value = true;
    router.patch(
        route('admin.review-disputes.resolve', selected.value.id),
        { decision: decision.value, admin_note: adminNote.value },
        {
            onFinish:  () => { submitting.value = false; },
            onSuccess: () => { closeModal(); },
        }
    );
}

function truncate(str, len = 80) {
    if (!str) return '—';
    return str.length > len ? str.slice(0, len) + '…' : str;
}

const STATUS_LABELS = { pending: 'Ожидает', approved: 'Одобрено', rejected: 'Отклонено' };
const STATUS_CLASS  = { pending: 'badge--pending', approved: 'badge--approved', rejected: 'badge--rejected' };
</script>

<template>
    <div class="page-wrap">
        <div class="page-header">
            <h1 class="page-title">Жалобы на отзывы</h1>
            <span class="page-count">{{ disputes.total }} всего</span>
        </div>

        <!-- Поиск -->
        <div class="search-wrap">
            <input
                v-model="searchInput"
                class="search-input"
                type="text"
                placeholder="Поиск по имени айдола…"
                @keyup.enter="doSearch"
            />
            <button class="search-btn" @click="doSearch">Найти</button>
        </div>

        <!-- Фильтр по статусу -->
        <div class="filter-tabs">
            <button
                v-for="tab in [
                    { key: 'pending',  label: 'Ожидают',    count: counts.pending },
                    { key: 'approved', label: 'Одобрены',   count: counts.approved },
                    { key: 'rejected', label: 'Отклонены',  count: counts.rejected },
                    { key: 'all',      label: 'Все',        count: disputes.total },
                ]"
                :key="tab.key"
                class="filter-tab"
                :class="{ 'filter-tab--active': status_filter === tab.key }"
                @click="setFilter(tab.key)"
            >
                {{ tab.label }}
                <span class="filter-tab__count">{{ tab.count }}</span>
            </button>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Айдол</th>
                        <th>Отзыв от</th>
                        <th>Текст отзыва</th>
                        <th>Причина жалобы</th>
                        <th>Статус</th>
                        <th>Подана</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="d in disputes.data"
                        :key="d.id"
                        class="data-row"
                    >
                        <td class="cell-id">{{ d.id }}</td>
                        <td class="cell-user">
                            <div class="cell-user-inner">
                                <img v-if="d.idol.avatar" :src="d.idol.avatar" class="user-avatar" />
                                <span>{{ d.idol.name }}</span>
                            </div>
                        </td>
                        <td class="cell-user">
                            <div class="cell-user-inner">
                                <img v-if="d.review.reviewer.avatar" :src="d.review.reviewer.avatar" class="user-avatar" />
                                <span>{{ d.review.reviewer.name }}</span>
                            </div>
                        </td>
                        <td class="cell-reason">
                            <div class="cell-stars">
                                <span v-for="i in 5" :key="i" class="star" :class="{ 'star--filled': i <= d.review.rating }">♥</span>
                            </div>
                            {{ truncate(d.review.text) }}
                        </td>
                        <td class="cell-reason">{{ truncate(d.reason) }}</td>
                        <td>
                            <span class="badge" :class="STATUS_CLASS[d.status]">
                                {{ STATUS_LABELS[d.status] }}
                            </span>
                        </td>
                        <td class="cell-date">{{ d.created_at }}</td>
                        <td>
                            <button
                                v-if="d.status === 'pending'"
                                class="action-btn"
                                @click="openModal(d)"
                            >Рассмотреть</button>
                            <button
                                v-else
                                class="action-btn action-btn--secondary"
                                @click="openModal(d)"
                            >Детали</button>
                        </td>
                    </tr>
                    <tr v-if="disputes.data.length === 0">
                        <td colspan="8" class="cell-empty">Жалоб нет</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="disputes.last_page > 1" class="pagination">
            <a
                v-for="pg in disputes.last_page"
                :key="pg"
                :href="`?page=${pg}&status=${status_filter}&search=${search ?? ''}`"
                class="page-link"
                :class="{ 'page-link--active': pg === disputes.current_page }"
            >{{ pg }}</a>
        </div>
    </div>

    <!-- Оверлей -->
    <div v-if="selected" class="modal-overlay" @click.self="closeModal">
        <div class="modal-box">
            <button class="modal-close" @click="closeModal">×</button>
            <h2 class="modal-title">
                Жалоба #{{ selected.id }}
                <span class="badge" :class="STATUS_CLASS[selected.status]" style="margin-left:0.5rem;font-size:0.8rem;">
                    {{ STATUS_LABELS[selected.status] }}
                </span>
            </h2>

            <div class="modal-grid">
                <div class="modal-section">
                    <div class="section-label">Айдол</div>
                    <div class="user-row">
                        <img v-if="selected.idol.avatar" :src="selected.idol.avatar" class="user-avatar" />
                        <span>{{ selected.idol.name }}</span>
                    </div>
                </div>
                <div class="modal-section">
                    <div class="section-label">Автор отзыва</div>
                    <div class="user-row">
                        <img v-if="selected.review.reviewer.avatar" :src="selected.review.reviewer.avatar" class="user-avatar" />
                        <span>{{ selected.review.reviewer.name }}</span>
                    </div>
                </div>

                <div class="modal-section modal-section--full">
                    <div class="section-label">Отзыв</div>
                    <div class="review-box">
                        <div class="review-box__stars">
                            <span v-for="i in 5" :key="i" class="star" :class="{ 'star--filled': i <= selected.review.rating }">♥</span>
                        </div>
                        <p class="review-box__text">{{ selected.review.text || '(без текста)' }}</p>
                    </div>
                </div>

                <div class="modal-section modal-section--full">
                    <div class="section-label">Причина жалобы</div>
                    <p class="details-text">{{ selected.reason }}</p>
                </div>

                <div v-if="selected.status !== 'pending'" class="modal-section modal-section--full">
                    <div class="section-label">Комментарий администратора</div>
                    <p class="details-text">{{ selected.admin_note || '—' }}</p>
                </div>
                <div v-if="selected.resolved_at" class="modal-section">
                    <div class="section-label">Рассмотрена</div>
                    <span>{{ selected.resolved_at }}</span>
                </div>

                <template v-if="selected.status === 'pending'">
                    <div class="modal-section modal-section--full resolve-section">
                        <div class="section-label">Решение</div>
                        <div class="decision-btns">
                            <button
                                class="decision-btn decision-btn--approve"
                                :class="{ 'decision-btn--active': decision === 'approved' }"
                                @click="decision = 'approved'"
                            >Одобрить — скрыть отзыв</button>
                            <button
                                class="decision-btn decision-btn--reject"
                                :class="{ 'decision-btn--active': decision === 'rejected' }"
                                @click="decision = 'rejected'"
                            >Отклонить жалобу</button>
                        </div>

                        <div class="section-label" style="margin-top:0.75rem;">Сообщение айдолу (необязательно)</div>
                        <textarea
                            v-model="adminNote"
                            class="resolve-textarea"
                            placeholder="Причина решения или пояснение…"
                            rows="3"
                            maxlength="2000"
                        ></textarea>

                        <div class="resolve-footer">
                            <button class="resolve-cancel" @click="closeModal">Отмена</button>
                            <button
                                class="resolve-submit"
                                :disabled="!decision || submitting"
                                @click="submitResolve"
                            >{{ submitting ? 'Сохраняем…' : 'Сохранить решение' }}</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<style scoped>
.page-wrap { padding: 2rem; }

.page-header { display: flex; align-items: baseline; gap: 1rem; margin-bottom: 1.25rem; }
.page-title { font-size: 1.35rem; font-weight: 700; color: rgba(255,255,255,0.9); margin: 0; }
.page-count { color: rgba(255,255,255,0.3); font-size: 0.88rem; }

/* Search */
.search-wrap { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
.search-input {
    flex: 1; max-width: 320px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: rgba(255,255,255,0.85);
    font-size: 0.85rem;
    padding: 0.45rem 0.75rem;
    outline: none;
    transition: border-color 0.15s;
    font-family: inherit;
}
.search-input:focus { border-color: rgba(155,110,232,0.5); }
.search-btn {
    padding: 0.45rem 0.9rem;
    background: rgba(155,110,232,0.15);
    border: 1px solid rgba(155,110,232,0.3);
    border-radius: 6px;
    color: rgba(180,150,255,0.9);
    font-size: 0.84rem;
    cursor: pointer;
    transition: background 0.15s;
    font-family: inherit;
}
.search-btn:hover { background: rgba(155,110,232,0.25); }

/* Filter tabs */
.filter-tabs { display: flex; gap: 0; margin-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.08); }
.filter-tab {
    display: flex; align-items: center; gap: 0.4rem;
    padding: 0.45rem 1rem;
    background: none; border: none; border-bottom: 2px solid transparent;
    color: rgba(255,255,255,0.4); font-size: 0.84rem; cursor: pointer;
    font-family: inherit; margin-bottom: -1px; transition: color 0.15s;
}
.filter-tab:hover { color: rgba(255,255,255,0.7); }
.filter-tab--active { color: #9B6EE8; border-bottom-color: #9B6EE8; }
.filter-tab__count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 18px; height: 18px; padding: 0 0.3rem;
    background: rgba(255,255,255,0.07); border-radius: 99px;
    font-size: 0.7rem; color: rgba(255,255,255,0.45);
}

/* Badge */
.badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 99px; font-size: 0.72rem; font-weight: 600; }
.badge--pending  { background: rgba(251,191,36,0.12);  color: rgba(251,191,36,0.9); }
.badge--approved { background: rgba(74,222,128,0.1);   color: rgba(74,222,128,0.85); }
.badge--rejected { background: rgba(239,68,68,0.1);    color: rgba(239,68,68,0.8); }

/* Table */
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
.data-table th {
    padding: 0.55rem 0.75rem; text-align: left;
    font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.07em;
    color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.07);
    white-space: nowrap;
}
.data-row td {
    padding: 0.65rem 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: middle; color: rgba(255,255,255,0.75);
}
.cell-id     { color: rgba(255,255,255,0.3); font-size: 0.78rem; white-space: nowrap; width: 40px; }
.cell-user        { min-width: 130px; }
.cell-user-inner  { display: flex; align-items: center; gap: 0.5rem; }
.user-avatar      { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.cell-reason { max-width: 180px; color: rgba(255,255,255,0.6); font-size: 0.82rem; }
.cell-date   { white-space: nowrap; color: rgba(255,255,255,0.3); font-size: 0.78rem; }
.cell-empty  { text-align: center; color: rgba(255,255,255,0.2); padding: 2rem; font-size: 0.88rem; }

/* Stars */
.cell-stars  { display: flex; gap: 2px; margin-bottom: 0.2rem; }
.star        { font-size: 0.75rem; color: rgba(255,160,180,0.25); }
.star--filled { color: rgba(210,50,100,0.9); }

/* Action button */
.action-btn {
    padding: 0.3rem 0.75rem;
    background: rgba(155,110,232,0.15);
    border: 1px solid rgba(155,110,232,0.3);
    border-radius: 5px;
    color: rgba(180,150,255,0.9);
    font-size: 0.78rem;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s;
    font-family: inherit;
}
.action-btn:hover { background: rgba(155,110,232,0.25); }
.action-btn--secondary {
    background: rgba(255,255,255,0.04);
    border-color: rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.4);
}

.pagination { display: flex; gap: 0.25rem; margin-top: 1.5rem; flex-wrap: wrap; }
.page-link { padding: 0.28rem 0.6rem; border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); font-size: 0.8rem; text-decoration: none; }
.page-link--active { border-color: rgba(155,110,232,0.6); color: #9B6EE8; background: rgba(155,110,232,0.1); }

/* Modal */
.modal-overlay {
    position: fixed; inset: 0; z-index: 1000;
    background: rgba(0,0,0,0.6);
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
}
.modal-box {
    position: relative;
    background: rgb(14,11,22);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 1.75rem 1.75rem 1.5rem;
    width: 100%; max-width: 600px;
    max-height: 85vh; overflow-y: auto;
}
.modal-close {
    position: absolute; top: 0.9rem; right: 1rem;
    background: none; border: none; color: rgba(255,255,255,0.4);
    font-size: 1.4rem; cursor: pointer; line-height: 1;
    transition: color 0.15s;
}
.modal-close:hover { color: rgba(255,255,255,0.75); }
.modal-title {
    font-size: 1.05rem; font-weight: 700; color: rgba(255,255,255,0.88);
    margin: 0 0 1.2rem; display: flex; align-items: center;
}
.modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; }
.modal-section { }
.modal-section--full { grid-column: 1 / -1; }
.section-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.07em; color: rgba(255,255,255,0.3); margin-bottom: 0.3rem; }
.user-row { display: flex; align-items: center; gap: 0.5rem; color: rgba(255,255,255,0.8); font-size: 0.88rem; }
.details-text { margin: 0; color: rgba(255,255,255,0.72); font-size: 0.88rem; line-height: 1.55; }

/* Review box */
.review-box {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 6px;
    padding: 0.75rem 0.9rem;
}
.review-box__stars { display: flex; gap: 3px; margin-bottom: 0.4rem; }
.review-box__text { margin: 0; color: rgba(255,255,255,0.7); font-size: 0.88rem; line-height: 1.55; }

/* Resolve */
.resolve-section { display: flex; flex-direction: column; gap: 0.5rem; }
.decision-btns { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.decision-btn {
    padding: 0.45rem 1rem;
    border-radius: 6px;
    font-size: 0.84rem;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.15s, border-color 0.15s;
}
.decision-btn--approve {
    background: rgba(74,222,128,0.08);
    border: 1px solid rgba(74,222,128,0.25);
    color: rgba(74,222,128,0.85);
}
.decision-btn--approve.decision-btn--active {
    background: rgba(74,222,128,0.18);
    border-color: rgba(74,222,128,0.5);
}
.decision-btn--reject {
    background: rgba(239,68,68,0.07);
    border: 1px solid rgba(239,68,68,0.2);
    color: rgba(239,68,68,0.8);
}
.decision-btn--reject.decision-btn--active {
    background: rgba(239,68,68,0.15);
    border-color: rgba(239,68,68,0.45);
}
.resolve-textarea {
    width: 100%; box-sizing: border-box;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: rgba(255,255,255,0.85);
    font-size: 0.88rem;
    padding: 0.55rem 0.75rem;
    resize: vertical;
    outline: none;
    transition: border-color 0.15s;
    font-family: inherit;
}
.resolve-textarea:focus { border-color: rgba(155,110,232,0.5); }
.resolve-footer { display: flex; gap: 0.6rem; justify-content: flex-end; margin-top: 0.25rem; }
.resolve-cancel {
    padding: 0.45rem 1rem;
    background: none;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 6px;
    color: rgba(255,255,255,0.45);
    font-size: 0.84rem;
    cursor: pointer;
    font-family: inherit;
}
.resolve-submit {
    padding: 0.45rem 1.2rem;
    background: rgba(155,110,232,0.2);
    border: 1px solid rgba(155,110,232,0.4);
    border-radius: 6px;
    color: rgba(180,150,255,0.95);
    font-size: 0.84rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
    font-family: inherit;
}
.resolve-submit:not(:disabled):hover { background: rgba(155,110,232,0.32); }
.resolve-submit:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
