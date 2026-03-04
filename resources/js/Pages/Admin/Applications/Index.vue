<script setup>
import { ref, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    applications: Object,
    filter_status: String,
});

const showRejectModal = ref(false);
const rejectingId = ref(null);
const rejectForm = useForm({ rejection_reason: '', reset_quiz: false });
const lightboxUrl = ref(null);

function openReject(id) {
    rejectingId.value = id;
    rejectForm.rejection_reason = '';
    rejectForm.reset_quiz = false;
    showRejectModal.value = true;
}

function approve(id) {
    if (!confirm('Одобрить заявку?')) return;
    router.patch(route('admin.applications.approve', id));
}

function submitReject() {
    rejectForm.patch(route('admin.applications.reject', rejectingId.value), {
        onSuccess: () => { showRejectModal.value = false; },
    });
}

function filterBy(status) {
    router.get(route('admin.applications.index'), { status: status || undefined }, { preserveState: true });
}

const statusLabel = { pending: 'На рассмотрении', approved: 'Одобрена', rejected: 'Отклонена' };
const statusClass = { pending: 'badge--pending', approved: 'badge--approved', rejected: 'badge--rejected' };
</script>

<template>
    <div>
        <div class="page-header">
            <h1 class="page-title">Заявки на Айдола</h1>
            <div class="filters">
                <button @click="filterBy('')" :class="['filter-btn', !filter_status && 'filter-btn--active']">Все</button>
                <button @click="filterBy('pending')" :class="['filter-btn', filter_status === 'pending' && 'filter-btn--active']">На рассмотрении</button>
                <button @click="filterBy('approved')" :class="['filter-btn', filter_status === 'approved' && 'filter-btn--active']">Одобренные</button>
                <button @click="filterBy('rejected')" :class="['filter-btn', filter_status === 'rejected' && 'filter-btn--active']">Отклонённые</button>
            </div>
        </div>

        <div class="table-wrap">
            <table class="apps-table">
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>Фото</th>
                        <th>Статус</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="app in applications.data" :key="app.id">
                        <td>
                            <div class="user-cell">
                                <img v-if="app.user.avatar_url" :src="app.user.avatar_url" class="user-avatar" alt="" />
                                <div v-else class="user-avatar user-avatar--initials">{{ app.user.name[0] }}</div>
                                <div>
                                    <div class="user-name">{{ app.user.name }}</div>
                                    <div class="user-email">{{ app.user.email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <img :src="app.face_photo_url" class="photo-thumb" alt="Фото" @click="lightboxUrl = app.face_photo_url" />
                        </td>
                        <td>
                            <span :class="['badge', statusClass[app.status]]">{{ statusLabel[app.status] }}</span>
                        </td>
                        <td class="date-cell">{{ new Date(app.created_at).toLocaleDateString('ru') }}</td>
                        <td>
                            <div class="actions" v-if="app.status === 'pending'">
                                <button @click="approve(app.id)" class="btn-approve">Одобрить</button>
                                <button @click="openReject(app.id)" class="btn-reject">Отклонить</button>
                            </div>
                            <Link v-else :href="route('admin.applications.show', app.id)" class="btn-view">Просмотр</Link>
                        </td>
                    </tr>
                    <tr v-if="!applications.data.length">
                        <td colspan="5" class="empty-row">Заявок нет</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination" v-if="applications.last_page > 1">
            <Link
                v-for="link in applications.links"
                :key="link.label"
                :href="link.url || '#'"
                class="page-link"
                :class="{ 'page-link--active': link.active, 'page-link--disabled': !link.url }"
                v-html="link.label"
            />
        </div>

        <!-- Reject Modal -->
        <div v-if="showRejectModal" class="modal-overlay" @click.self="showRejectModal = false">
            <div class="modal">
                <h2 class="modal-title">Причина отклонения</h2>
                <div class="reason-chips">
                    <button
                        v-for="chip in ['Не видно лица', 'Плохое качество фото', 'Фото не вашего лица', 'Нарушение правил']"
                        :key="chip"
                        class="reason-chip"
                        :class="{ 'reason-chip--active': rejectForm.rejection_reason === chip }"
                        @click="rejectForm.rejection_reason = chip"
                    >{{ chip }}</button>
                </div>
                <textarea
                    v-model="rejectForm.rejection_reason"
                    class="modal-textarea"
                    rows="3"
                    placeholder="Или введите свою причину..."
                />
                <label class="reset-quiz-label">
                    <input type="checkbox" v-model="rejectForm.reset_quiz" />
                    Сбросить результаты теста (пользователь пересдаёт с нуля)
                </label>
                <div class="modal-actions">
                    <button @click="showRejectModal = false" class="btn-cancel">Отмена</button>
                    <button @click="submitReject" class="btn-reject-confirm" :disabled="rejectForm.processing">
                        Отклонить
                    </button>
                </div>
            </div>
        </div>

        <!-- Lightbox -->
        <div v-if="lightboxUrl" class="lightbox" @click="lightboxUrl = null">
            <img :src="lightboxUrl" class="lightbox-img" alt="Фото" />
        </div>
    </div>
</template>

<style scoped>
.page-header { display: flex; align-items: center; gap: 2rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.page-title { font-size: 1.4rem; color: #fff; margin: 0; }

.filters { display: flex; gap: 0.5rem; }
.filter-btn {
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.1);
    background: transparent;
    color: rgba(255,255,255,0.5);
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.15s;
}
.filter-btn--active, .filter-btn:hover {
    border-color: rgba(200,70,126,0.5);
    color: #C8467E;
    background: rgba(200,70,126,0.08);
}

.table-wrap { overflow-x: auto; }
.apps-table { width: 100%; border-collapse: collapse; }
.apps-table th {
    text-align: left;
    padding: 0.75rem 1rem;
    font-size: 0.75rem;
    color: rgba(255,255,255,0.4);
    text-transform: uppercase;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.apps-table td {
    padding: 0.9rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: middle;
}
.apps-table tr:hover td { background: rgba(255,255,255,0.02); }

.user-cell { display: flex; align-items: center; gap: 0.75rem; }
.user-avatar {
    width: 36px; height: 36px; border-radius: 50%; object-fit: cover;
    background: rgba(200,70,126,0.15); flex-shrink: 0;
}
.user-avatar--initials {
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem; font-weight: 600; color: #C8467E;
    border: 1px solid rgba(200,70,126,0.3);
}
.user-name { font-size: 0.9rem; color: rgba(255,255,255,0.85); font-weight: 500; }
.user-email { font-size: 0.78rem; color: rgba(255,255,255,0.35); }

.photo-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; cursor: zoom-in; }

.badge {
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}
.badge--pending { background: rgba(255,180,0,0.12); color: #fbb740; }
.badge--approved { background: rgba(0,200,100,0.12); color: #4cde8f; }
.badge--rejected { background: rgba(255,80,80,0.12); color: #ff6b6b; }

.date-cell { font-size: 0.82rem; color: rgba(255,255,255,0.4); }

.actions { display: flex; gap: 0.5rem; }
.btn-approve, .btn-reject, .btn-view {
    padding: 0.3rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8rem;
    cursor: pointer;
    border: 1px solid;
    transition: all 0.15s;
    text-decoration: none;
    display: inline-block;
}
.btn-approve { border-color: rgba(76,222,143,0.4); color: #4cde8f; background: rgba(76,222,143,0.08); }
.btn-approve:hover { background: rgba(76,222,143,0.15); }
.btn-reject { border-color: rgba(255,107,107,0.4); color: #ff6b6b; background: rgba(255,107,107,0.08); }
.btn-reject:hover { background: rgba(255,107,107,0.15); }
.btn-view { border-color: rgba(200,70,126,0.3); color: #C8467E; background: rgba(200,70,126,0.06); }
.btn-view:hover { background: rgba(200,70,126,0.12); }

.empty-row { text-align: center; color: rgba(255,255,255,0.3); padding: 3rem; }

.pagination { display: flex; gap: 0.35rem; margin-top: 1.5rem; flex-wrap: wrap; }
.page-link {
    padding: 0.3rem 0.65rem;
    border-radius: 6px;
    border: 1px solid rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.5);
    font-size: 0.82rem;
    text-decoration: none;
    transition: all 0.15s;
}
.page-link--active { border-color: rgba(200,70,126,0.5); color: #C8467E; background: rgba(200,70,126,0.08); }
.page-link--disabled { opacity: 0.3; pointer-events: none; }

.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.7);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.modal {
    background: #111120;
    border: 1px solid rgba(200,70,126,0.25);
    border-radius: 12px;
    padding: 1.75rem;
    width: 420px;
    max-width: 90vw;
}
.modal-title { margin: 0 0 1rem; color: #fff; font-size: 1.1rem; }
.modal-textarea {
    width: 100%; box-sizing: border-box;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    color: #fff;
    padding: 0.7rem;
    font-size: 0.9rem;
    resize: vertical;
    outline: none;
}
.modal-textarea:focus { border-color: rgba(200,70,126,0.5); }
.modal-actions { display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1rem; }
.btn-cancel {
    padding: 0.5rem 1rem; border-radius: 7px;
    border: 1px solid rgba(255,255,255,0.1);
    background: transparent; color: rgba(255,255,255,0.5);
    cursor: pointer;
}
.btn-reject-confirm {
    padding: 0.5rem 1rem; border-radius: 7px;
    background: rgba(255,80,80,0.15);
    border: 1px solid rgba(255,80,80,0.4);
    color: #ff6b6b; cursor: pointer; transition: all 0.15s;
}
.btn-reject-confirm:hover { background: rgba(255,80,80,0.25); }
.btn-reject-confirm:disabled { opacity: 0.5; }

.reason-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.75rem; }
.reason-chip {
    padding: 0.25rem 0.65rem; border-radius: 20px; font-size: 0.78rem; cursor: pointer;
    border: 1px solid rgba(255,107,107,0.3); background: rgba(255,107,107,0.06); color: rgba(255,107,107,0.75);
    transition: all 0.12s;
}
.reason-chip:hover, .reason-chip--active {
    border-color: rgba(255,107,107,0.6); background: rgba(255,107,107,0.15); color: #ff6b6b;
}

.reset-quiz-label {
    display: flex; align-items: center; gap: 0.5rem; margin: 0.75rem 0 0;
    font-size: 0.82rem; color: rgba(255,255,255,0.55); cursor: pointer;
}
.reset-quiz-label input { accent-color: #C8467E; }

.lightbox {
    position: fixed; inset: 0; background: rgba(0,0,0,0.9);
    display: flex; align-items: center; justify-content: center; z-index: 9999; cursor: zoom-out;
}
.lightbox-img { max-width: 90vw; max-height: 90vh; border-radius: 8px; }
</style>
