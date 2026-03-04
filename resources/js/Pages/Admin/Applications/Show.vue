<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ application: Object });

const lightboxOpen = ref(false);
const rejectForm = useForm({ rejection_reason: '', reset_quiz: false });
const showRejectForm = ref(false);

function approve() {
    if (!confirm('Одобрить заявку?')) return;
    useForm({}).patch(route('admin.applications.approve', props.application.id));
}

function submitReject() {
    rejectForm.patch(route('admin.applications.reject', props.application.id), {
        onSuccess: () => { showRejectForm.value = false; },
    });
}

const statusLabel = { pending: 'На рассмотрении', approved: 'Одобрена', rejected: 'Отклонена' };
const statusClass = { pending: 'badge--pending', approved: 'badge--approved', rejected: 'badge--rejected' };
</script>

<template>
    <div class="show-wrap">
        <Link :href="route('admin.applications.index')" class="back-link">← Назад к заявкам</Link>

        <div class="show-grid">
            <!-- Photo -->
            <div class="photo-section">
                <img
                    :src="application.face_photo_url"
                    class="face-photo"
                    alt="Фото лица"
                    @click="lightboxOpen = true"
                />
                <p class="photo-hint">Нажмите для увеличения</p>
            </div>

            <!-- Info -->
            <div class="info-section">
                <div class="info-header">
                    <img v-if="application.user.avatar_url" :src="application.user.avatar_url" class="user-avatar" alt="" />
                    <div v-else class="user-avatar user-avatar--init">{{ application.user.name[0] }}</div>
                    <div>
                        <div class="user-name">{{ application.user.name }}</div>
                        <div class="user-email">{{ application.user.email }}</div>
                    </div>
                    <span :class="['badge', statusClass[application.status]]">{{ statusLabel[application.status] }}</span>
                </div>

                <div class="meta-row">
                    <span class="meta-label">Заявка подана:</span>
                    <span class="meta-value">{{ new Date(application.created_at).toLocaleString('ru') }}</span>
                </div>
                <div class="meta-row" v-if="application.reviewed_at">
                    <span class="meta-label">Проверена:</span>
                    <span class="meta-value">{{ new Date(application.reviewed_at).toLocaleString('ru') }} ({{ application.reviewer?.name }})</span>
                </div>
                <div class="meta-row" v-if="application.rejection_reason">
                    <span class="meta-label">Причина отказа:</span>
                    <span class="meta-value meta-value--reason">{{ application.rejection_reason }}</span>
                </div>

                <div v-if="application.status === 'pending'" class="action-section">
                    <button @click="approve" class="btn-approve">Одобрить заявку</button>
                    <button @click="showRejectForm = !showRejectForm" class="btn-reject-toggle">
                        {{ showRejectForm ? 'Отмена' : 'Отклонить' }}
                    </button>
                    <form v-if="showRejectForm" @submit.prevent="submitReject" class="reject-form">
                        <div class="reason-chips">
                            <button
                                type="button"
                                v-for="chip in ['Не видно лица', 'Плохое качество фото', 'Фото не вашего лица', 'Нарушение правил']"
                                :key="chip"
                                class="reason-chip"
                                :class="{ 'reason-chip--active': rejectForm.rejection_reason === chip }"
                                @click="rejectForm.rejection_reason = chip"
                            >{{ chip }}</button>
                        </div>
                        <textarea
                            v-model="rejectForm.rejection_reason"
                            class="reject-textarea"
                            rows="3"
                            placeholder="Или введите свою причину..."
                            required
                        />
                        <label class="reset-quiz-label">
                            <input type="checkbox" v-model="rejectForm.reset_quiz" />
                            Сбросить результаты теста (пользователь пересдаёт с нуля)
                        </label>
                        <button type="submit" class="btn-reject-confirm" :disabled="rejectForm.processing">
                            Отклонить заявку
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Lightbox -->
        <div v-if="lightboxOpen" class="lightbox" @click="lightboxOpen = false">
            <img :src="application.face_photo_url" class="lightbox-img" alt="Фото" />
        </div>
    </div>
</template>

<style scoped>
.show-wrap { max-width: 900px; }
.back-link { color: rgba(255,255,255,0.4); text-decoration: none; font-size: 0.85rem; display: inline-block; margin-bottom: 1.5rem; }
.back-link:hover { color: rgba(255,255,255,0.7); }

.show-grid { display: grid; grid-template-columns: 280px 1fr; gap: 2rem; }
@media (max-width: 640px) { .show-grid { grid-template-columns: 1fr; } }

.photo-section { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
.face-photo { width: 100%; border-radius: 12px; cursor: zoom-in; border: 1px solid rgba(200,70,126,0.2); }
.photo-hint { font-size: 0.75rem; color: rgba(255,255,255,0.3); }

.info-section { display: flex; flex-direction: column; gap: 1rem; }
.info-header { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.user-avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; background: rgba(200,70,126,0.15); }
.user-avatar--init { display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 600; color: #C8467E; border: 1px solid rgba(200,70,126,0.3); }
.user-name { font-size: 1rem; color: #fff; font-weight: 600; }
.user-email { font-size: 0.82rem; color: rgba(255,255,255,0.4); }

.badge { padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
.badge--pending { background: rgba(255,180,0,0.12); color: #fbb740; }
.badge--approved { background: rgba(0,200,100,0.12); color: #4cde8f; }
.badge--rejected { background: rgba(255,80,80,0.12); color: #ff6b6b; }

.meta-row { display: flex; gap: 0.75rem; align-items: flex-start; }
.meta-label { font-size: 0.8rem; color: rgba(255,255,255,0.35); white-space: nowrap; min-width: 120px; }
.meta-value { font-size: 0.85rem; color: rgba(255,255,255,0.7); }
.meta-value--reason { color: #ff6b6b; font-style: italic; }

.action-section { display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1rem; }
.btn-approve {
    padding: 0.6rem 1.25rem; border-radius: 8px;
    background: rgba(76,222,143,0.12); border: 1px solid rgba(76,222,143,0.4);
    color: #4cde8f; font-size: 0.9rem; cursor: pointer; transition: all 0.15s; align-self: flex-start;
}
.btn-approve:hover { background: rgba(76,222,143,0.2); }
.btn-reject-toggle {
    padding: 0.5rem 1rem; border-radius: 8px;
    background: transparent; border: 1px solid rgba(255,80,80,0.3);
    color: #ff6b6b; font-size: 0.85rem; cursor: pointer; align-self: flex-start;
}
.reject-form { display: flex; flex-direction: column; gap: 0.75rem; }
.reject-textarea {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px; color: #fff; padding: 0.7rem; font-size: 0.9rem; resize: vertical; outline: none;
}
.reject-textarea:focus { border-color: rgba(200,70,126,0.5); }
.btn-reject-confirm {
    padding: 0.55rem 1.1rem; border-radius: 7px; align-self: flex-start;
    background: rgba(255,80,80,0.15); border: 1px solid rgba(255,80,80,0.4);
    color: #ff6b6b; cursor: pointer;
}
.btn-reject-confirm:disabled { opacity: 0.5; }

.reason-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.reason-chip {
    padding: 0.25rem 0.65rem; border-radius: 20px; font-size: 0.78rem; cursor: pointer;
    border: 1px solid rgba(255,107,107,0.3); background: rgba(255,107,107,0.06); color: rgba(255,107,107,0.75);
    transition: all 0.12s;
}
.reason-chip:hover, .reason-chip--active {
    border-color: rgba(255,107,107,0.6); background: rgba(255,107,107,0.15); color: #ff6b6b;
}
.reset-quiz-label {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.82rem; color: rgba(255,255,255,0.55); cursor: pointer;
}
.reset-quiz-label input { accent-color: #C8467E; }

.lightbox {
    position: fixed; inset: 0; background: rgba(0,0,0,0.9);
    display: flex; align-items: center; justify-content: center; z-index: 9999; cursor: zoom-out;
}
.lightbox-img { max-width: 90vw; max-height: 90vh; border-radius: 8px; }
</style>
