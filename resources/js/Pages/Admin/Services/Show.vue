<script setup>
import { ref, reactive, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    service: Object,
});

// ── Flag state ──────────────────────────────────────────
const FIELDS = ['name_ru', 'name_en', 'price', 'category_id', 'time_unit_id'];
const FIELD_LABELS = {
    name_ru:      'Название (RU)',
    name_en:      'Название (EN)',
    price:        'Цена',
    category_id:  'Категория',
    time_unit_id: 'Единица времени',
};

const flaggedFields  = reactive(Object.fromEntries(FIELDS.map(f => [f, false])));
const fieldComments  = reactive(Object.fromEntries(FIELDS.map(f => [f, ''])));

const PRESETS = {
    name_ru: [
        'Содержит ссылки / контактную информацию',
        'Нецензурная лексика или оскорбления',
        'Опечатки или некорректный регистр',
    ],
    name_en: [
        'Contains links or contact info',
        'Inappropriate language',
        'Typos or incorrect casing',
    ],
    price: [
        'Некорректная стоимость услуги',
        'Цена не соответствует правилам платформы',
    ],
    category_id: [
        'Выбрана неподходящая категория',
    ],
    time_unit_id: [
        'Некорректная единица времени',
    ],
    rejection: [
        'Услуга нарушает правила платформы',
        'Спам / Дубликат существующей услуги',
    ],
};

const isApproved  = ref(false);
const submitting  = ref(false);
const errors      = ref({});
const rejectionReason = ref('');
const showReject  = ref(false);

const hasAnyFlag = computed(() => {
    const flagged = Object.values(flaggedFields).some(v => v);
    if (flagged) isApproved.value = false;
    return flagged;
});

const canSubmit = computed(() => isApproved.value || hasAnyFlag.value);

const STATUS_LABELS = {
    pending:     'На рассмотрении',
    approved:    'Одобрена',
    has_remarks: 'Есть замечания',
    rejected:    'Отклонена',
};

function submit() {
    if (!canSubmit.value) return;
    errors.value = {};

    const flagged = Object.keys(flaggedFields).filter(f => flaggedFields[f]);
    const comments = {};
    flagged.forEach(f => { if (fieldComments[f]) comments[f] = fieldComments[f]; });

    submitting.value = true;
    router.post(route('admin.services.moderation.decide', props.service.id), {
        decision:      isApproved.value ? 'approved' : 'has_remarks',
        flagged_fields: flagged,
        field_comments: comments,
    }, {
        preserveScroll: true,
        onError: (errs) => { errors.value = errs; submitting.value = false; },
        onFinish: () => { submitting.value = false; },
    });
}

function reject() {
    if (!rejectionReason.value.trim()) { showReject.value = true; return; }
    if (!confirm('Полностью отклонить услугу? Айдол получит уведомление.')) return;
    submitting.value = true;
    router.post(route('admin.services.moderation.decide', props.service.id), {
        decision: 'rejected',
        rejection_reason: rejectionReason.value,
    }, {
        preserveScroll: true,
        onError: (errs) => { errors.value = errs; submitting.value = false; },
        onFinish: () => { submitting.value = false; },
    });
}
</script>

<template>
    <div class="sps-wrap">
        <Link :href="route('admin.services.moderation.index')" class="sps-back">← К списку</Link>

        <div class="sps-layout">
            <!-- LEFT: Service info + history -->
            <div class="sps-left">

                <!-- Idol info -->
                <div class="sps-card">
                    <h2 class="sps-card__title">Айдол</h2>
                    <a :href="route('profile.show', service.user.id)" target="_blank" class="sps-idol-link">
                        <div class="sps-idol">
                            <img v-if="service.user.avatar_url" :src="service.user.avatar_url" class="sps-idol__avatar" alt="" />
                            <div v-else class="sps-idol__avatar sps-idol__avatar--empty">{{ service.user.name?.charAt(0) }}</div>
                            <div>
                                <div class="sps-idol__name">{{ service.user.name }}</div>
                                <div class="sps-idol__email">{{ service.user.email }}</div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Service metadata -->
                <div class="sps-card">
                    <h2 class="sps-card__title">Услуга #{{ service.id }}</h2>
                    <div class="sps-meta">
                        <div class="sps-meta__row">
                            <span class="sps-meta__label">Статус</span>
                            <span class="sps-status" :class="'sps-status--' + service.status">
                                {{ STATUS_LABELS[service.status] || service.status }}
                            </span>
                        </div>
                        <div class="sps-meta__row">
                            <span class="sps-meta__label">Название (RU)</span>
                            <span>{{ service.name_ru }}</span>
                        </div>
                        <div class="sps-meta__row" v-if="service.name_en">
                            <span class="sps-meta__label">Название (EN)</span>
                            <span>{{ service.name_en }}</span>
                        </div>
                        <div class="sps-meta__row">
                            <span class="sps-meta__label">Цена</span>
                            <span>{{ service.price?.toLocaleString('ru') }} ₽</span>
                        </div>
                        <div class="sps-meta__row" v-if="service.category">
                            <span class="sps-meta__label">Категория</span>
                            <span>{{ service.category }}</span>
                        </div>
                        <div class="sps-meta__row" v-if="service.time_unit">
                            <span class="sps-meta__label">Ед. времени</span>
                            <span>{{ service.time_unit }}</span>
                        </div>
                        <div class="sps-meta__row">
                            <span class="sps-meta__label">Создана</span>
                            <span>{{ new Date(service.created_at).toLocaleDateString('ru-RU') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Review history -->
                <div v-if="service.history?.length" class="sps-card">
                    <h2 class="sps-card__title">История проверок</h2>
                    <div v-for="item in service.history" :key="item.id" class="sps-review-item">
                        <div class="sps-review-item__header">
                            <span :class="item.decision === 'approved' ? 'sps-dec--ok' : item.decision === 'rejected' ? 'sps-dec--reject' : 'sps-dec--bad'">
                                {{ item.decision === 'approved' ? '✓ Одобрено' : item.decision === 'rejected' ? '✗ Отклонено' : '⚑ Замечания' }}
                            </span>
                            <span class="sps-review-item__type">
                                {{ item.type === 'initial' ? '(Модерация)' : '(Изменения)' }}
                            </span>
                            <span class="sps-review-item__date">{{ new Date(item.created_at).toLocaleDateString('ru-RU') }}</span>
                            <span v-if="item.admin" class="sps-review-item__admin">{{ item.admin.name }}</span>
                        </div>
                        <div v-if="item.flagged_fields?.length" class="sps-review-item__detail">
                            Помечено: {{ item.flagged_fields.map(f => FIELD_LABELS[f] || f).join(', ') }}
                        </div>
                        <div v-if="item.field_comments && Object.keys(item.field_comments).length" class="sps-review-item__comments">
                            <div v-for="(comment, field) in item.field_comments" :key="field" class="sps-review-item__comment">
                                <span class="sps-review-item__comment-field">{{ FIELD_LABELS[field] || field }}:</span>
                                {{ comment }}
                            </div>
                        </div>
                        <div v-if="item.admin_comment" class="sps-review-item__comment">
                            <span class="sps-review-item__comment-field">Причина:</span>
                            {{ item.admin_comment }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Decision form -->
            <div class="sps-right">

                <!-- Field flags -->
                <div class="sps-card">
                    <h2 class="sps-card__title">Поля</h2>
                    <div v-for="field in FIELDS" :key="field" class="sps-flag-row" :class="{ 'sps-flag-row--flagged': flaggedFields[field] }">
                        <div class="sps-flag-header">
                            <label class="sps-flag-label">
                                <input type="checkbox" v-model="flaggedFields[field]" class="sps-checkbox" />
                                <span class="sps-flag-name" :class="{ 'sps-flag-name--flagged': flaggedFields[field] }">
                                    {{ FIELD_LABELS[field] }}
                                </span>
                                <svg v-if="flaggedFields[field]" class="sps-warn-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ff7b7b" stroke-width="2.5">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                            </label>
                            <span class="sps-flag-value">
                                <template v-if="field === 'name_ru'">{{ service.name_ru || '—' }}</template>
                                <template v-else-if="field === 'name_en'">{{ service.name_en || '—' }}</template>
                                <template v-else-if="field === 'price'">{{ service.price?.toLocaleString('ru') }} ₽</template>
                                <template v-else-if="field === 'category_id'">{{ service.category || '—' }}</template>
                                <template v-else-if="field === 'time_unit_id'">{{ service.time_unit || '—' }}</template>
                            </span>
                        </div>
                        <div v-if="flaggedFields[field]" class="sps-comment-wrapper">
                            <textarea
                                v-model="fieldComments[field]"
                                class="sps-comment"
                                rows="2"
                                placeholder="Комментарий к замечанию..."
                            />
                            <div class="sps-presets">
                                <button v-for="preset in PRESETS[field]" :key="preset" type="button" class="sps-preset-btn" @click="fieldComments[field] = preset">
                                    + {{ preset }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Decision -->
                <div class="sps-card sps-card--sticky">
                    <h2 class="sps-card__title">Решение</h2>

                    <label class="sps-approved-label" :class="{ 'sps-approved-label--disabled': hasAnyFlag }">
                        <input type="checkbox" v-model="isApproved" :disabled="hasAnyFlag" class="sps-checkbox" />
                        <span>Одобрить услугу</span>
                    </label>

                    <p v-if="hasAnyFlag" class="sps-decision-hint">
                        Есть пометки — будет отправлено решение «Есть замечания».
                    </p>
                    <p v-else-if="isApproved" class="sps-decision-hint sps-decision-hint--ok">
                        Услуга будет одобрена и появится в профиле айдола.
                    </p>

                    <div v-if="errors._" class="sps-err">{{ errors._ }}</div>

                    <div class="sps-actions">
                        <button
                            class="sps-submit"
                            :disabled="!canSubmit || submitting"
                            @click="submit"
                        >
                            {{ submitting ? 'Отправка…' : 'Отправить решение' }}
                        </button>

                        <!-- Rejection section -->
                        <div v-if="showReject" class="sps-reject-form">
                            <textarea
                                v-model="rejectionReason"
                                class="sps-comment"
                                rows="3"
                                placeholder="Причина отклонения (увидит пользователь)..."
                            />
                            <div class="sps-presets sps-presets--block">
                                <button v-for="preset in PRESETS.rejection" :key="preset" type="button" class="sps-preset-btn" @click="rejectionReason = preset">
                                    + {{ preset }}
                                </button>
                            </div>
                        </div>

                        <button
                            class="sps-reject"
                            :disabled="submitting"
                            @click="showReject ? reject() : (showReject = true)"
                        >
                            {{ showReject ? 'Подтвердить отклонение' : 'Отклонить полностью' }}
                        </button>
                        <button v-if="showReject" type="button" class="sps-cancel" @click="showReject = false; rejectionReason = ''">
                            Отмена
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.sps-wrap { padding: 1.5rem; max-width: 1200px; margin: 0 auto; }

.sps-back { color: rgba(255, 178, 239, 0.7); text-decoration: none; font-size: 0.88rem; display: inline-block; margin-bottom: 1.25rem; transition: color 0.15s; }
.sps-back:hover { color: #ffb2ef; }

.sps-warn-icon {
    display: inline-block;
    vertical-align: middle;
    margin-left: 0.4rem;
}

.sps-layout { display: grid; grid-template-columns: 320px 1fr; gap: 1.25rem; }
@media (max-width: 900px) { .sps-layout { grid-template-columns: 1fr; } }

.sps-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 10px;
    padding: 1.1rem;
    margin-bottom: 1rem;
}

.sps-card__title { font-size: 0.9rem; font-weight: 600; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 0.85rem; }

.sps-idol-link {
    text-decoration: none;
    display: block;
}
.sps-idol-link:hover .sps-idol__name {
    color: #ffb2ef;
}

.sps-idol { display: flex; align-items: center; gap: 0.75rem; }
.sps-idol__avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.sps-idol__avatar--empty { background: rgba(255,178,239,0.15); display: flex; align-items: center; justify-content: center; color: rgba(255,178,239,0.7); font-weight: 600; }
.sps-idol__name  { font-size: 0.95rem; font-weight: 600; color: rgba(255,255,255,0.85); }
.sps-idol__email { font-size: 0.8rem; color: rgba(255,255,255,0.4); margin-top: 2px; }

.sps-meta { display: flex; flex-direction: column; gap: 0.55rem; }
.sps-meta__row { display: flex; gap: 0.75rem; font-size: 0.88rem; align-items: baseline; }
.sps-meta__label { color: rgba(255,255,255,0.35); flex-shrink: 0; width: 110px; }

.sps-status { padding: 2px 8px; border-radius: 10px; font-size: 0.78rem; font-weight: 500; }
.sps-status--pending     { background: rgba(255,178,239,0.15); color: #ffb2ef; }
.sps-status--approved    { background: rgba(100,210,160,0.15); color: #64d2a0; }
.sps-status--has_remarks { background: rgba(255,123,123,0.15); color: #ff7b7b; }
.sps-status--rejected    { background: rgba(239,68,68,0.15); color: #ef4444; }

.sps-review-item { padding: 0.6rem 0; border-top: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; }
.sps-review-item:first-child { border-top: none; }
.sps-review-item__header { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
.sps-review-item__date { color: rgba(255,255,255,0.3); font-size: 0.8rem; }
.sps-review-item__admin { color: rgba(255,255,255,0.35); font-size: 0.8rem; margin-left: auto; }
.sps-review-item__detail { margin-top: 0.3rem; color: rgba(255,255,255,0.4); font-size: 0.8rem; }
.sps-review-item__comments { margin-top: 0.35rem; display: flex; flex-direction: column; gap: 0.2rem; }
.sps-review-item__comment { font-size: 0.8rem; color: rgba(255,255,255,0.45); }
.sps-review-item__comment-field { color: rgba(255,178,239,0.6); font-weight: 600; margin-right: 0.3rem; }
.sps-review-item__type { color: rgba(255,255,255,0.25); font-size: 0.78rem; font-weight: 500; }
.sps-dec--ok     { color: #64d2a0; }
.sps-dec--bad    { color: #ff7b7b; }
.sps-dec--reject { color: #ef4444; }

.sps-flag-row { margin-bottom: 0.75rem; }
.sps-flag-header { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.sps-flag-label { display: flex; align-items: center; gap: 0.4rem; cursor: pointer; }
.sps-flag-name { font-size: 0.88rem; color: rgba(255,255,255,0.65); font-weight: 500; transition: color 0.15s; }
.sps-flag-name--flagged { color: #ff7b7b; }
.sps-flag-value { font-size: 0.83rem; color: rgba(255,255,255,0.35); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.sps-checkbox { accent-color: #ffb2ef; width: 15px; height: 15px; cursor: pointer; }

.sps-comment {
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
.sps-comment:focus { border-color: rgba(255,100,100,0.5); }
.sps-comment::placeholder { color: rgba(255,255,255,0.25); }

.sps-approved-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.92rem; color: rgba(255,255,255,0.7); }
.sps-approved-label--disabled { opacity: 0.35; cursor: not-allowed; }
.sps-decision-hint { font-size: 0.83rem; color: rgba(255,123,123,0.8); margin: 0.5rem 0 0; }
.sps-decision-hint--ok { color: rgba(100,210,160,0.8); }

.sps-err { font-size: 0.83rem; color: #ff7b7b; margin-top: 0.4rem; }

.sps-actions { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem; }

.sps-submit {
    width: 100%;
    padding: 0.65rem;
    border-radius: 8px;
    background: rgba(255, 178, 239, 0.12);
    border: 1px solid rgba(255, 178, 239, 0.35);
    color: #ffb2ef;
    font-size: 0.95rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s;
}
.sps-submit:hover:not(:disabled) { background: rgba(255, 178, 239, 0.22); }
.sps-submit:disabled { opacity: 0.4; cursor: not-allowed; }

.sps-reject-form { margin-top: 0.25rem; }

.sps-reject {
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
.sps-reject:hover:not(:disabled) { background: rgba(180,50,50,0.2); }
.sps-reject:disabled { opacity: 0.4; cursor: not-allowed; }

.sps-cancel {
    width: 100%;
    padding: 0.45rem;
    border-radius: 8px;
    background: transparent;
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.45);
    font-size: 0.85rem;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s;
}
.sps-cancel:hover { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.65); }

/* ── Enhanced UI/UX Styles ── */
.sps-flag-row {
    border: 1px solid transparent;
    border-radius: 8px;
    padding: 0.5rem;
    transition: all 0.2s ease;
}
.sps-flag-row--flagged {
    border-color: rgba(255,100,100,0.15);
    background: rgba(255,100,100,0.02);
}
.sps-indicator {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
}
.sps-indicator--ok {
    color: #64d2a0;
    background: rgba(100,210,160,0.1);
}
.sps-indicator--flagged {
    color: #ff7b7b;
    background: rgba(255,123,123,0.1);
}
.sps-presets {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.35rem;
}
.sps-presets--block {
    margin-bottom: 0.75rem;
    margin-top: 0.5rem;
}
.sps-preset-btn {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    color: rgba(255,255,255,0.5);
    font-size: 0.72rem;
    padding: 3px 8px;
    cursor: pointer;
    transition: all 0.15s;
}
.sps-preset-btn:hover {
    background: rgba(255, 178, 239, 0.1);
    color: #ffb2ef;
    border-color: rgba(255, 178, 239, 0.25);
}
.sps-card--sticky {
    position: sticky;
    bottom: 1rem;
    z-index: 100;
    background: rgba(30, 30, 42, 0.96) !important;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 178, 239, 0.25) !important;
    box-shadow: 0 10px 40px rgba(0,0,0,0.6);
}
</style>
