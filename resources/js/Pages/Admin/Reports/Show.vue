<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ report: Object });

const REASON_LABELS = {
    spam:          'Спам',
    inappropriate: 'Неприемлемый контент',
    fraud:         'Мошенничество',
    harassment:    'Домогательства',
    other:         'Другое',
};

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('ru', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}

const adminNote = ref('');
const showAction = ref(null); // 'review' | 'dismiss'

function submit() {
    const routeName = showAction.value === 'review' ? 'admin.reports.review' : 'admin.reports.dismiss';
    router.patch(route(routeName, props.report.id), { admin_note: adminNote.value || null }, {
        onSuccess: () => { showAction.value = null; },
    });
}
</script>

<template>
    <div class="wrap">
        <Link :href="route('admin.reports.index')" class="back">← Жалобы</Link>

        <div class="card">
            <div class="card-row">
                <span class="label">Жалобщик</span>
                <Link :href="route('admin.users.show', report.reporter.id)" class="user-link" v-if="report.reporter">
                    {{ report.reporter.name }} <span class="muted">{{ report.reporter.email }}</span>
                </Link>
                <span v-else class="muted">—</span>
            </div>
            <div class="card-row">
                <span class="label">На кого</span>
                <Link :href="route('admin.users.show', report.reported.id)" class="user-link" v-if="report.reported">
                    {{ report.reported.name }} <span class="muted">{{ report.reported.email }}</span>
                </Link>
                <span v-else class="muted">—</span>
            </div>
            <div class="card-row">
                <span class="label">Причина</span>
                <span>{{ REASON_LABELS[report.reason] ?? report.reason }}</span>
            </div>
            <div class="card-row">
                <span class="label">Статус</span>
                <span :class="['badge', report.status === 'pending' ? 'badge--pending' : report.status === 'reviewed' ? 'badge--reviewed' : 'badge--dismissed']">
                    {{ report.status === 'pending' ? 'Ожидает' : report.status === 'reviewed' ? 'Рассмотрена' : 'Отклонена' }}
                </span>
            </div>
            <div class="card-row">
                <span class="label">Дата</span>
                <span class="muted">{{ formatDate(report.created_at) }}</span>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Описание</div>
            <div class="text-block">{{ report.details ?? '—' }}</div>
        </div>

        <div v-if="report.admin_note" class="section">
            <div class="section-title">Заметка администратора</div>
            <div class="text-block text-block--note">{{ report.admin_note }}</div>
            <div class="reviewer-meta" v-if="report.reviewer">
                {{ report.reviewer.name }}, {{ formatDate(report.reviewed_at) }}
            </div>
        </div>

        <div v-if="report.status === 'pending'" class="actions-section">
            <template v-if="!showAction">
                <button class="btn-approve" @click="showAction = 'review'">Рассмотреть</button>
                <button class="btn-danger"  @click="showAction = 'dismiss'">Отклонить</button>
            </template>
            <template v-else>
                <div class="note-form">
                    <label class="note-label">Заметка <span class="muted">(необязательно)</span></label>
                    <textarea v-model="adminNote" class="note-textarea" rows="3" maxlength="1000" />
                    <div class="note-actions">
                        <button class="btn-cancel" @click="showAction = null">Отмена</button>
                        <button :class="showAction === 'review' ? 'btn-approve' : 'btn-danger'" @click="submit">
                            {{ showAction === 'review' ? 'Подтвердить рассмотрение' : 'Подтвердить отклонение' }}
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<style scoped>
.wrap { max-width: 640px; display: flex; flex-direction: column; gap: 1.5rem; }

.back { font-size: 0.83rem; color: rgba(155,110,232,0.7); text-decoration: none; }
.back:hover { color: #9B6EE8; }

.card {
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.02);
}
.card-row {
    display: flex;
    align-items: baseline;
    gap: 1rem;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    font-size: 0.93rem;
}
.card-row:last-child { border-bottom: none; }
.label { min-width: 110px; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.07em; color: rgba(255,255,255,0.3); flex-shrink: 0; }

.user-link { color: rgba(255,255,255,0.85); text-decoration: none; display: flex; gap: 0.5rem; align-items: baseline; }
.user-link:hover { color: #9B6EE8; }
.muted { color: rgba(255,255,255,0.35); font-size: 0.82rem; }

.badge { font-size: 0.7rem; padding: 0.15rem 0.5rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
.badge--pending  { background: rgba(251,183,64,0.1);  color: #fbb740; border: 1px solid rgba(251,183,64,0.25); }
.badge--reviewed { background: rgba(74,222,128,0.1);  color: #4ade80; border: 1px solid rgba(74,222,128,0.25); }
.badge--dismissed{ background: rgba(239,68,68,0.1);   color: #f87171; border: 1px solid rgba(239,68,68,0.25); }

.section-title { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.07em; color: rgba(255,255,255,0.3); margin-bottom: 0.5rem; }
.text-block {
    white-space: pre-wrap;
    font-size: 0.93rem;
    color: rgba(255,255,255,0.75);
    line-height: 1.65;
    padding: 1rem 1.25rem;
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.02);
}
.text-block--note { border-color: rgba(190,145,255,0.15); background: rgba(190,145,255,0.04); color: rgba(255,255,255,0.6); font-style: italic; }
.reviewer-meta { font-size: 0.75rem; color: rgba(255,255,255,0.25); margin-top: 0.4rem; }

.actions-section { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.note-form { display: flex; flex-direction: column; gap: 0.5rem; width: 100%; }
.note-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.07em; color: rgba(255,255,255,0.3); }
.note-textarea { padding: 0.6rem 0.75rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.85); font-family: inherit; font-size: 0.9rem; outline: none; resize: vertical; min-height: 72px; box-sizing: border-box; width: 100%; }
.note-textarea:focus { border-color: rgba(190,145,255,0.4); }
.note-actions { display: flex; gap: 0.5rem; }

.btn-approve { padding: 0.4rem 0.9rem; border: 1px solid rgba(76,222,143,0.4); background: rgba(76,222,143,0.07); color: #4cde8f; font-family: inherit; font-size: 0.85rem; cursor: pointer; }
.btn-approve:hover { background: rgba(76,222,143,0.18); }
.btn-danger  { padding: 0.4rem 0.9rem; border: 1px solid rgba(239,68,68,0.4); background: rgba(239,68,68,0.07); color: #f87171; font-family: inherit; font-size: 0.85rem; cursor: pointer; }
.btn-danger:hover  { background: rgba(239,68,68,0.18); }
.btn-cancel  { padding: 0.4rem 0.9rem; border: 1px solid rgba(255,255,255,0.12); background: transparent; color: rgba(255,255,255,0.4); font-family: inherit; font-size: 0.85rem; cursor: pointer; }
</style>
