<script setup>
import { ref, reactive, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    changeRequest: Object,
    service:       Object,
    fields:        Object, // field -> { current, pending, flagged, admin_comment }
});

const FIELD_LABELS = {
    name:         'Название',
    name_ru:      'Название (RU)',
    name_en:      'Название (EN)',
    price:        'Цена',
    category_id:  'Категория',
    time_unit_id: 'Единица времени',
};

// Initialize flags from existing remarks
const flaggedFields = reactive(Object.fromEntries(
    Object.keys(props.fields).map(f => [f, (props.changeRequest.flagged_fields || []).includes(f)])
));
const fieldComments = reactive(Object.fromEntries(
    Object.keys(props.fields).map(f => [f, (props.changeRequest.field_comments || {})[f] || ''])
));

const isApproved   = ref(false);
const submitting   = ref(false);
const errors       = ref({});
const adminComment = ref(props.changeRequest.admin_comment || '');
const showReject   = ref(false);

const hasAnyFlag = computed(() => {
    const flagged = Object.values(flaggedFields).some(v => v);
    if (flagged) isApproved.value = false;
    return flagged;
});

const canSubmit = computed(() => isApproved.value || hasAnyFlag.value);

const STATUS_LABELS = {
    pending:     'На рассмотрении',
    has_remarks: 'Есть замечания',
    approved:    'Одобрен',
    rejected:    'Отклонён',
};

function formatValue(field, data, side) {
    const val = data[side];
    if (val === null || val === undefined) return '—';
    if (field === 'name') {
        if (typeof val === 'object') {
            const parts = [];
            if (val.ru) parts.push(`RU: ${val.ru}`);
            if (val.en) parts.push(`EN: ${val.en}`);
            return parts.join('\n') || '—';
        }
        return val;
    }
    if (field === 'category_id' || field === 'time_unit_id') {
        return val?.name ?? '—';
    }
    if (field === 'price') {
        return Number(val).toLocaleString('ru') + ' ₽';
    }
    return String(val);
}

function isChanged(field, data) {
    if (field === 'name') {
        return JSON.stringify(data.current) !== JSON.stringify(data.pending);
    }
    if (field === 'category_id' || field === 'time_unit_id') {
        return data.current?.id !== data.pending?.id;
    }
    return data.current !== data.pending;
}

function submit() {
    if (!canSubmit.value) return;
    errors.value = {};

    const flagged = Object.keys(flaggedFields).filter(f => flaggedFields[f]);
    const comments = {};
    flagged.forEach(f => { if (fieldComments[f]) comments[f] = fieldComments[f]; });

    submitting.value = true;
    router.post(route('admin.services.change-requests.decide', props.changeRequest.id), {
        decision:       isApproved.value ? 'approved' : 'has_remarks',
        flagged_fields: flagged,
        field_comments: comments,
    }, {
        preserveScroll: true,
        onError: (errs) => { errors.value = errs; submitting.value = false; },
        onFinish: () => { submitting.value = false; },
    });
}

function reject() {
    if (!confirm('Отклонить эти изменения? Айдол получит уведомление.')) return;
    submitting.value = true;
    router.post(route('admin.services.change-requests.decide', props.changeRequest.id), {
        decision:      'rejected',
        admin_comment: adminComment.value,
    }, {
        preserveScroll: true,
        onError: (errs) => { errors.value = errs; submitting.value = false; },
        onFinish: () => { submitting.value = false; },
    });
}
</script>

<template>
    <div class="scr-wrap">
        <Link :href="route('admin.services.change-requests.index')" class="scr-back">← К списку</Link>

        <div class="scr-layout">
            <!-- LEFT: Comparison table + idol info -->
            <div class="scr-left">

                <!-- Idol + service info -->
                <div class="scr-card">
                    <h2 class="scr-card__title">Айдол</h2>
                    <div class="scr-idol">
                        <img v-if="service.user.avatar_url" :src="service.user.avatar_url" class="scr-idol__avatar" alt="" />
                        <div v-else class="scr-idol__avatar scr-idol__avatar--empty">{{ service.user.name?.charAt(0) }}</div>
                        <div>
                            <div class="scr-idol__name">{{ service.user.name }}</div>
                            <div class="scr-idol__meta">
                                Услуга #{{ service.id }} ·
                                <span class="scr-status" :class="'scr-status--' + service.status">
                                    {{ STATUS_LABELS[service.status] || service.status }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Review history -->
                <div v-if="service.history?.length" class="scr-card">
                    <h2 class="scr-card__title">История проверок</h2>
                    <div v-for="item in service.history" :key="item.id" class="scr-review-item">
                        <div class="scr-review-item__header">
                            <span :class="item.decision === 'approved' ? 'scr-dec--ok' : item.decision === 'rejected' ? 'scr-dec--reject' : 'scr-dec--bad'">
                                {{ item.decision === 'approved' ? '✓ Одобрено' : item.decision === 'rejected' ? '✗ Отклонено' : '⚑ Замечания' }}
                            </span>
                            <span class="scr-review-item__type">
                                {{ item.type === 'initial' ? '(Модерация)' : '(Изменения)' }}
                            </span>
                            <span class="scr-review-item__date">{{ new Date(item.created_at).toLocaleDateString('ru-RU') }}</span>
                            <span v-if="item.admin" class="scr-review-item__admin">{{ item.admin.name }}</span>
                        </div>
                        <div v-if="item.flagged_fields?.length" class="scr-review-item__detail">
                            Помечено: {{ item.flagged_fields.map(f => FIELD_LABELS[f] || f).join(', ') }}
                        </div>
                        <div v-if="item.field_comments && Object.keys(item.field_comments).length" class="scr-review-item__comments">
                            <div v-for="(comment, field) in item.field_comments" :key="field" class="scr-review-item__comment">
                                <span class="scr-review-item__comment-field">{{ FIELD_LABELS[field] || field }}:</span>
                                {{ comment }}
                            </div>
                        </div>
                        <div v-if="item.admin_comment" class="scr-review-item__comment">
                            <span class="scr-review-item__comment-field">Причина:</span>
                            {{ item.admin_comment }}
                        </div>
                    </div>
                </div>

                <!-- Comparison table -->
                <div class="scr-card">
                    <h2 class="scr-card__title">Запрошенные изменения</h2>
                    <div v-for="(data, field) in fields" :key="field" class="scr-comp-row">
                        <div class="scr-comp-label">{{ FIELD_LABELS[field] || field }}</div>
                        <div class="scr-comp-grid">
                            <div class="scr-side scr-side--current">
                                <div class="scr-side__label">Сейчас</div>
                                <div class="scr-side__val">{{ formatValue(field, data, 'current') }}</div>
                            </div>
                            <div class="scr-arrow">→</div>
                            <div class="scr-side scr-side--pending">
                                <div class="scr-side__label">Станет</div>
                                <div class="scr-side__val" :class="{ 'scr-val--diff': isChanged(field, data) }">
                                    {{ formatValue(field, data, 'pending') }}
                                </div>
                            </div>
                        </div>
                        <div v-if="data.admin_comment" class="scr-prev-comment">
                            Предыдущее замечание: {{ data.admin_comment }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Decision form -->
            <div class="scr-right">

                <!-- Field flags -->
                <div class="scr-card">
                    <h2 class="scr-card__title">Замечания к полям</h2>
                    <div v-for="(data, field) in fields" :key="field" class="scr-flag-row">
                        <div class="scr-flag-header">
                            <label class="scr-flag-label">
                                <input type="checkbox" v-model="flaggedFields[field]" class="scr-checkbox" />
                                <span class="scr-flag-name" :class="{ 'scr-flag-name--flagged': flaggedFields[field] }">
                                    {{ FIELD_LABELS[field] || field }}
                                </span>
                            </label>
                            <span v-if="isChanged(field, data)" class="scr-changed-badge">изменено</span>
                        </div>
                        <textarea
                            v-if="flaggedFields[field]"
                            v-model="fieldComments[field]"
                            class="scr-comment"
                            rows="2"
                            placeholder="Комментарий к замечанию..."
                        />
                    </div>
                </div>

                <!-- Decision -->
                <div class="scr-card">
                    <h2 class="scr-card__title">Решение</h2>

                    <label class="scr-approved-label" :class="{ 'scr-approved-label--disabled': hasAnyFlag }">
                        <input type="checkbox" v-model="isApproved" :disabled="hasAnyFlag" class="scr-checkbox" />
                        <span>Одобрить изменения</span>
                    </label>

                    <p v-if="hasAnyFlag" class="scr-decision-hint">
                        Есть пометки — будет отправлено «Есть замечания».
                    </p>
                    <p v-else-if="isApproved" class="scr-decision-hint scr-decision-hint--ok">
                        Изменения будут применены к услуге.
                    </p>

                    <div v-if="errors._" class="scr-err">{{ errors._ }}</div>

                    <div class="scr-actions">
                        <button
                            class="scr-submit"
                            :disabled="!canSubmit || submitting"
                            @click="submit"
                        >
                            {{ submitting ? 'Отправка…' : 'Отправить решение' }}
                        </button>

                        <!-- Rejection section -->
                        <div v-if="showReject" class="scr-reject-form">
                            <textarea
                                v-model="adminComment"
                                class="scr-comment"
                                rows="3"
                                placeholder="Причина отклонения (увидит пользователь)..."
                            />
                            <div class="scr-reject-btns">
                                <button class="scr-reject-confirm" :disabled="submitting" @click="reject">
                                    {{ submitting ? 'Отправка…' : 'Подтвердить отклонение' }}
                                </button>
                                <button class="scr-cancel" @click="showReject = false; adminComment = ''">
                                    Отмена
                                </button>
                            </div>
                        </div>

                        <button v-else class="scr-reject" :disabled="submitting" @click="showReject = true">
                            Отклонить изменения
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.scr-wrap { padding: 1.5rem; }

.scr-back { color: rgba(255,178,239,0.7); text-decoration: none; font-size: 0.88rem; display: inline-block; margin-bottom: 1.25rem; transition: color 0.15s; }
.scr-back:hover { color: #ffb2ef; }

.scr-layout { display: grid; grid-template-columns: 1fr 340px; gap: 1.25rem; }
@media (max-width: 900px) { .scr-layout { grid-template-columns: 1fr; } }

.scr-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 10px;
    padding: 1.1rem;
    margin-bottom: 1rem;
}
.scr-card__title { font-size: 0.9rem; font-weight: 600; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 0.85rem; }

.scr-idol { display: flex; align-items: center; gap: 0.75rem; }
.scr-idol__avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.scr-idol__avatar--empty { background: rgba(255,178,239,0.15); display: flex; align-items: center; justify-content: center; color: rgba(255,178,239,0.7); font-weight: 600; }
.scr-idol__name { font-size: 0.95rem; font-weight: 600; color: rgba(255,255,255,0.85); }
.scr-idol__meta { font-size: 0.8rem; color: rgba(255,255,255,0.4); margin-top: 2px; display: flex; align-items: center; gap: 0.3rem; flex-wrap: wrap; }

.scr-status { padding: 1px 7px; border-radius: 10px; font-size: 0.75rem; font-weight: 500; }
.scr-status--pending     { background: rgba(255,178,239,0.15); color: #ffb2ef; }
.scr-status--approved    { background: rgba(100,210,160,0.15); color: #64d2a0; }
.scr-status--has_remarks { background: rgba(255,123,123,0.15); color: #ff7b7b; }
.scr-status--rejected    { background: rgba(239,68,68,0.15); color: #ef4444; }

/* Comparison */
.scr-comp-row { padding: 0.9rem 0; border-top: 1px solid rgba(255,255,255,0.05); }
.scr-comp-row:first-of-type { border-top: none; padding-top: 0; }
.scr-comp-label { font-size: 0.78rem; color: rgba(255,178,239,0.7); font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.55rem; }

.scr-comp-grid { display: grid; grid-template-columns: 1fr 32px 1fr; align-items: stretch; gap: 0.6rem; }
.scr-side { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.04); border-radius: 7px; padding: 0.7rem 0.85rem; display: flex; flex-direction: column; }
.scr-side__label { font-size: 0.68rem; color: rgba(255,255,255,0.25); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem; font-weight: 600; }
.scr-side__val { font-size: 0.9rem; color: rgba(255,255,255,0.75); white-space: pre-line; word-break: break-word; }
.scr-arrow { display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: rgba(255,255,255,0.15); }
.scr-val--diff { color: #64d2a0 !important; font-weight: 600; }

.scr-prev-comment { margin-top: 0.45rem; font-size: 0.8rem; color: rgba(255,178,100,0.65); font-style: italic; }

/* Flags */
.scr-flag-row { margin-bottom: 0.75rem; }
.scr-flag-header { display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; }
.scr-flag-label { display: flex; align-items: center; gap: 0.4rem; cursor: pointer; }
.scr-flag-name { font-size: 0.88rem; color: rgba(255,255,255,0.65); font-weight: 500; transition: color 0.15s; }
.scr-flag-name--flagged { color: #ff7b7b; }
.scr-changed-badge { font-size: 0.72rem; font-weight: 600; color: rgba(100,210,160,0.7); background: rgba(100,210,160,0.08); border: 1px solid rgba(100,210,160,0.2); border-radius: 10px; padding: 0 6px; }

.scr-checkbox { accent-color: #ffb2ef; width: 15px; height: 15px; cursor: pointer; }

.scr-comment {
    width: 100%;
    box-sizing: border-box;
    margin-top: 0.5rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,100,100,0.25);
    border-radius: 6px;
    color: rgba(255,255,255,0.8);
    font-size: 0.85rem;
    font-family: inherit;
    padding: 0.45rem 0.65rem;
    resize: vertical;
    outline: none;
}
.scr-comment:focus { border-color: rgba(255,100,100,0.5); }
.scr-comment::placeholder { color: rgba(255,255,255,0.25); }

.scr-approved-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.92rem; color: rgba(255,255,255,0.7); }
.scr-approved-label--disabled { opacity: 0.35; cursor: not-allowed; }
.scr-decision-hint { font-size: 0.83rem; color: rgba(255,123,123,0.8); margin: 0.5rem 0 0; }
.scr-decision-hint--ok { color: rgba(100,210,160,0.8); }

.scr-err { font-size: 0.83rem; color: #ff7b7b; margin-top: 0.4rem; }

.scr-actions { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem; }

.scr-submit {
    width: 100%;
    padding: 0.65rem;
    border-radius: 8px;
    background: rgba(255,178,239,0.12);
    border: 1px solid rgba(255,178,239,0.35);
    color: #ffb2ef;
    font-size: 0.95rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s;
}
.scr-submit:hover:not(:disabled) { background: rgba(255,178,239,0.22); }
.scr-submit:disabled { opacity: 0.4; cursor: not-allowed; }

.scr-reject-form { display: flex; flex-direction: column; gap: 0.4rem; }
.scr-reject-btns { display: flex; gap: 0.4rem; }

.scr-reject-confirm {
    flex: 1;
    padding: 0.55rem;
    border-radius: 8px;
    background: rgba(180,50,50,0.15);
    border: 1px solid rgba(200,60,60,0.35);
    color: rgba(255,110,110,0.9);
    font-size: 0.88rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s;
}
.scr-reject-confirm:hover:not(:disabled) { background: rgba(180,50,50,0.25); }
.scr-reject-confirm:disabled { opacity: 0.4; cursor: not-allowed; }

.scr-reject {
    width: 100%;
    padding: 0.55rem;
    border-radius: 8px;
    background: rgba(180,50,50,0.1);
    border: 1px solid rgba(200,60,60,0.3);
    color: rgba(255,110,110,0.8);
    font-size: 0.88rem;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s;
}
.scr-reject:hover:not(:disabled) { background: rgba(180,50,50,0.2); }
.scr-reject:disabled { opacity: 0.4; cursor: not-allowed; }

.scr-cancel {
    flex: 0 0 auto;
    padding: 0.45rem 0.85rem;
    border-radius: 8px;
    background: transparent;
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.45);
    font-size: 0.85rem;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s;
}
.scr-cancel:hover { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.65); }

/* Review history styles */
.scr-review-item { padding: 0.6rem 0; border-top: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; }
.scr-review-item:first-child { border-top: none; }
.scr-review-item__header { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
.scr-review-item__date { color: rgba(255,255,255,0.3); font-size: 0.8rem; }
.scr-review-item__admin { color: rgba(255,255,255,0.35); font-size: 0.8rem; margin-left: auto; }
.scr-review-item__detail { margin-top: 0.3rem; color: rgba(255,255,255,0.4); font-size: 0.8rem; }
.scr-review-item__comments { margin-top: 0.35rem; display: flex; flex-direction: column; gap: 0.2rem; }
.scr-review-item__comment { font-size: 0.8rem; color: rgba(255,255,255,0.45); }
.scr-review-item__comment-field { color: rgba(255,178,239,0.6); font-weight: 600; margin-right: 0.3rem; }
.scr-review-item__type { color: rgba(255,255,255,0.25); font-size: 0.78rem; font-weight: 500; }
.scr-dec--ok     { color: #64d2a0; }
.scr-dec--bad    { color: #ff7b7b; }
.scr-dec--reject { color: #ef4444; }
</style>
