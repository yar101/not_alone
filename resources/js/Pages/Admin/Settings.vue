<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    settings:      Object,
    rating_deltas: Object,
});

const form = useForm({
    rating_low_threshold:      props.settings.rating_low_threshold,
    order_auto_complete_delay: props.settings.order_auto_complete_delay,
    content_pack_price_min:    props.settings.content_pack_price_min,
    content_pack_price_max:  props.settings.content_pack_price_max,
    moderate_new_packs:      props.settings.moderate_new_packs,
    moderate_existing_packs: props.settings.moderate_existing_packs,
    rating_deltas: { ...props.rating_deltas },
});

function save() {
    form.patch(route('admin.settings.update'), { preserveScroll: true });
}

// ── Event groups ──────────────────────────────────────────────────────────────

const EVENT_GROUPS = [
    {
        label: 'Отзывы',
        events: [
            { key: 'review_5star', label: 'Отзыв 5★', positive: true },
            { key: 'review_4star', label: 'Отзыв 4★', positive: true },
            { key: 'review_2star', label: 'Отзыв 2★', positive: false },
            { key: 'review_1star', label: 'Отзыв 1★', positive: false },
        ],
    },
    {
        label: 'Активность',
        events: [
            { key: 'order_completed',         label: 'Заказ выполнен',       positive: true },
            { key: 'review_dispute_approved',  label: 'Спор по отзыву выигран', positive: true },
        ],
    },
    {
        label: 'Штрафы',
        events: [
            { key: 'report_accepted', label: 'Репорт принят', positive: false },
        ],
    },
];

// ── Stepper ───────────────────────────────────────────────────────────────────

function step(key, dir) {
    const val = Math.round((Number(form.rating_deltas[key] || 0) + dir * 0.1) * 100) / 100;
    form.rating_deltas[key] = val;
}

// ── Preview modal ─────────────────────────────────────────────────────────────

const PREVIEW_RATINGS = [20, 50, 75, 90, 95];

const previewEvent = ref(null);
const previewLabel = ref('');

function openPreview(key, label) {
    previewEvent.value = key;
    previewLabel.value = label;
}

function closePreview() {
    previewEvent.value = null;
}

const previewRows = computed(() => {
    if (!previewEvent.value) return [];
    const base = Number(form.rating_deltas[previewEvent.value]) || 0;
    return PREVIEW_RATINGS.map(rating => {
        const effective = base > 0
            ? Math.max(0.01, Math.round(base * (100 - rating) / 80 * 100) / 100)
            : base;
        return { rating, effective };
    });
});

const previewBase = computed(() =>
    previewEvent.value ? Number(form.rating_deltas[previewEvent.value]) || 0 : 0
);
</script>

<template>
    <div>
        <h1 class="page-title">Настройки платформы</h1>

        <form @submit.prevent="save" class="settings-form">

            <!-- ── Раздел: Заказы ── -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Заказы</h2>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Автозавершение заказа, ч</label>
                            <p class="field-hint">Через сколько часов после оплаты заказ будет завершен автоматически</p>
                        </div>
                        <div class="field-control">
                            <input
                                v-model.number="form.order_auto_complete_delay"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="input input--sm"
                                :class="{ 'input--err': form.errors.order_auto_complete_delay }"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.order_auto_complete_delay" class="err">{{ form.errors.order_auto_complete_delay }}</p>
                </div>
            </div>

            <!-- ── Раздел: Контент-паки ── -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Контент-паки</h2>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Минимальная цена, ₽</label>
                            <p class="field-hint">Айдол не сможет выставить цену ниже этого значения</p>
                        </div>
                        <div class="field-control">
                            <input
                                v-model.number="form.content_pack_price_min"
                                type="number"
                                min="1"
                                class="input input--sm"
                                :class="{ 'input--err': form.errors.content_pack_price_min }"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.content_pack_price_min" class="err">{{ form.errors.content_pack_price_min }}</p>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Максимальная цена, ₽</label>
                            <p class="field-hint">Айдол не сможет выставить цену выше этого значения</p>
                        </div>
                        <div class="field-control">
                            <input
                                v-model.number="form.content_pack_price_max"
                                type="number"
                                min="1"
                                class="input input--sm"
                                :class="{ 'input--err': form.errors.content_pack_price_max }"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.content_pack_price_max" class="err">{{ form.errors.content_pack_price_max }}</p>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Модерировать новые паки</label>
                            <p class="field-hint">Новые паки проходят проверку модератором перед публикацией</p>
                        </div>
                        <div class="field-control">
                            <label class="toggle">
                                <input type="checkbox" v-model="form.moderate_new_packs" />
                                <span class="toggle__track"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Модерировать изменения в паках</label>
                            <p class="field-hint">Изменения названия, описания и цены в опубликованных паках проходят проверку модератором</p>
                        </div>
                        <div class="field-control">
                            <label class="toggle">
                                <input type="checkbox" v-model="form.moderate_existing_packs" />
                                <span class="toggle__track"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Раздел: Рейтинг ── -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Рейтинг</h2>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Порог низкого рейтинга</label>
                            <p class="field-hint">Айдолы ниже этого значения ограничены в ценах на услуги</p>
                        </div>
                        <div class="field-control">
                            <input
                                v-model.number="form.rating_low_threshold"
                                type="number"
                                min="0"
                                max="100"
                                class="input input--sm"
                                :class="{ 'input--err': form.errors.rating_low_threshold }"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.rating_low_threshold" class="err">{{ form.errors.rating_low_threshold }}</p>
                </div>
            </div>

            <!-- ── Раздел: Дельты ── -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Базовые дельты событий</h2>
                    <p class="section-hint">Позитивные значения затухают с ростом рейтинга. Штрафы — полные.</p>
                </div>

                <div class="delta-groups">
                    <div
                        v-for="group in EVENT_GROUPS"
                        :key="group.label"
                        class="delta-group"
                    >
                        <div class="delta-group-label">{{ group.label }}</div>

                        <div
                            v-for="event in group.events"
                            :key="event.key"
                            class="delta-row"
                            :class="event.positive ? 'delta-row--pos' : 'delta-row--neg'"
                        >
                            <span class="delta-row__dot" />
                            <span class="delta-row__label">{{ event.label }}</span>
                            <div class="stepper" :class="{ 'stepper--err': form.errors['rating_deltas.' + event.key] }">
                                <button type="button" class="stepper__btn" @click="step(event.key, -1)">−</button>
                                <input
                                    v-model.number="form.rating_deltas[event.key]"
                                    type="number"
                                    step="0.1"
                                    class="stepper__input"
                                />
                                <button type="button" class="stepper__btn" @click="step(event.key, +1)">+</button>
                            </div>
                            <button
                                type="button"
                                class="btn-preview"
                                @click="openPreview(event.key, event.label)"
                            >
                                Пример
                            </button>
                            <p v-if="form.errors['rating_deltas.' + event.key]" class="delta-row__err">
                                {{ form.errors['rating_deltas.' + event.key] }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Сохранить ── -->
            <div class="form-actions">
                <Transition name="fade">
                    <span v-if="form.wasSuccessful" class="success-msg">Сохранено</span>
                </Transition>
                <button type="submit" class="btn-submit" :disabled="form.processing">
                    {{ form.processing ? 'Сохранение…' : 'Сохранить' }}
                </button>
            </div>
        </form>

        <!-- ── Превью ── -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="previewEvent" class="overlay" @click.self="closePreview">
                    <div class="modal">
                        <div class="modal-header">
                            <div>
                                <div class="modal-title">{{ previewLabel }}</div>
                                <div class="modal-sub">
                                    Базовая дельта:
                                    <span :class="previewBase >= 0 ? 'pos' : 'neg'">
                                        {{ previewBase >= 0 ? '+' : '' }}{{ previewBase }}
                                    </span>
                                </div>
                            </div>
                            <button class="modal-close" @click="closePreview">✕</button>
                        </div>
                        <div class="modal-body">
                            <p class="modal-hint">
                                Фактическое изменение при разных уровнях рейтинга.
                                <template v-if="previewBase > 0">
                                    Формула: <code>max(0.01, base × (100 − R) / 80)</code>
                                </template>
                                <template v-else>
                                    Штраф применяется полностью при любом рейтинге.
                                </template>
                            </p>
                            <table class="preview-table">
                                <thead>
                                    <tr>
                                        <th>Рейтинг айдола</th>
                                        <th>Эффективная дельта</th>
                                        <th>Станет</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in previewRows" :key="row.rating">
                                        <td class="td-rating">{{ row.rating }}</td>
                                        <td :class="['td-delta', row.effective >= 0 ? 'pos' : 'neg']">
                                            {{ row.effective >= 0 ? '+' : '' }}{{ row.effective }}
                                        </td>
                                        <td class="td-result">
                                            {{ Math.min(100, Math.max(0, Math.round((row.rating + row.effective) * 100) / 100)) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.page-title { font-size: 1.4rem; color: #fff; margin: 0 0 1.5rem; font-weight: 600; }

/* ── Form ── */
.settings-form { max-width: 580px; display: flex; flex-direction: column; gap: 1.25rem; }

/* ── Section ── */
.section {
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.08);
    overflow: hidden;
}

.section-header {
    padding: 0.9rem 1.1rem 0.75rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

.section-title {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(190,145,255,0.65);
    margin: 0;
}

.section-hint {
    font-size: 0.95rem;
    color: rgba(255,255,255,0.28);
    margin: 0.3rem 0 0;
}

/* ── Field (threshold) ── */
.field { padding: 0.9rem 1.1rem; }

.field-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    justify-content: space-between;
}

.field-meta { flex: 1; }
.field-label { font-size: 1.1rem; font-weight: 500; color: rgba(255,255,255,0.8); display: block; margin-bottom: 0.2rem; }
.field-hint  { font-size: 0.92rem; color: rgba(255,255,255,0.28); margin: 0; }
.field-control { flex-shrink: 0; }

/* ── Inputs ── */
.input {
    padding: 0.5rem 0.75rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.85);
    font-family: inherit;
    font-size: 1rem;
    outline: none;
    transition: border-color 0.15s;
    box-sizing: border-box;
}
.input:focus    { border-color: rgba(190,145,255,0.5); }
.input--err     { border-color: rgba(239,68,68,0.55); }
.input--sm      { width: 88px; text-align: center; }

/* ── Toggle ── */
.toggle {
    position: relative;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}
.toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
.toggle__track {
    width: 44px;
    height: 24px;
    border-radius: 12px;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.15);
    transition: background 0.2s, border-color 0.2s;
    position: relative;
}
.toggle__track::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: rgba(255,255,255,0.5);
    transition: transform 0.2s, background 0.2s;
}
.toggle input:checked + .toggle__track {
    background: rgba(255, 178, 239,0.7);
    border-color: rgba(255, 178, 239,0.5);
}
.toggle input:checked + .toggle__track::after {
    transform: translateX(20px);
    background: #fff;
}

/* hide native spinners */
.input[type=number]::-webkit-inner-spin-button,
.input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
.input[type=number] { -moz-appearance: textfield; }

/* ── Stepper ── */
.stepper {
    display: flex;
    align-items: stretch;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.05);
    flex-shrink: 0;
    transition: border-color 0.15s;
}
.stepper:focus-within { border-color: rgba(190,145,255,0.5); }
.stepper--err         { border-color: rgba(239,68,68,0.55); }

.stepper__btn {
    width: 34px;
    background: transparent;
    border: none;
    color: rgba(255,255,255,0.45);
    font-size: 1.15rem;
    line-height: 1;
    cursor: pointer;
    flex-shrink: 0;
    transition: color 0.12s, background 0.12s;
    padding: 0;
    font-family: inherit;
}
.stepper__btn:hover { color: rgba(255,255,255,0.9); background: rgba(255,255,255,0.06); }
.stepper__btn:active { background: rgba(255,255,255,0.1); }

.stepper__input {
    width: 70px;
    padding: 0.5rem 0.25rem;
    background: transparent;
    border: none;
    border-left: 1px solid rgba(255,255,255,0.08);
    border-right: 1px solid rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.9);
    font-family: inherit;
    font-size: 1.05rem;
    font-variant-numeric: tabular-nums;
    outline: none;
    text-align: center;
    -moz-appearance: textfield;
}
.stepper__input::-webkit-inner-spin-button,
.stepper__input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }

/* ── Delta groups ── */
.delta-groups { display: flex; flex-direction: column; }

.delta-group { border-bottom: 1px solid rgba(255,255,255,0.04); }
.delta-group:last-child { border-bottom: none; }

.delta-group-label {
    padding: 0.5rem 1.1rem;
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.015);
    border-bottom: 1px solid rgba(255,255,255,0.04);
}

/* ── Delta row ── */
.delta-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.7rem 1.1rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    flex-wrap: wrap;
}
.delta-row:last-child { border-bottom: none; }
.delta-row:hover { background: rgba(255,255,255,0.015); }

.delta-row__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}
.delta-row--pos .delta-row__dot { background: rgba(74,222,128,0.55); }
.delta-row--neg .delta-row__dot { background: rgba(248,113,113,0.55); }

.delta-row__label {
    flex: 1;
    font-size: 1.1rem;
    color: rgba(255,255,255,0.72);
    min-width: 0;
}

.delta-row__err {
    width: 100%;
    font-size: 0.82rem;
    color: rgba(239,68,68,0.75);
    margin: 0;
    padding-left: 1.4rem;
}

/* ── Preview button ── */
.btn-preview {
    padding: 0.4rem 0.85rem;
    border: 1px solid rgba(255,255,255,0.08);
    background: transparent;
    color: rgba(255,255,255,0.35);
    font-family: inherit;
    font-size: 0.95rem;
    cursor: pointer;
    white-space: nowrap;
    transition: color 0.12s, border-color 0.12s;
    flex-shrink: 0;
}
.btn-preview:hover { color: rgba(190,145,255,0.8); border-color: rgba(190,145,255,0.3); }

/* ── Actions ── */
.form-actions { display: flex; align-items: center; gap: 1rem; }

.success-msg { font-size: 0.92rem; color: rgba(74,222,128,0.8); }

.btn-submit {
    padding: 0.6rem 1.6rem;
    border: 1px solid rgba(190,145,255,0.4);
    background: rgba(190,145,255,0.1);
    color: rgba(255,255,255,0.9);
    font-family: inherit;
    font-size: 1.05rem;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-submit:hover:not(:disabled) { background: rgba(190,145,255,0.2); }
.btn-submit:disabled { opacity: 0.45; cursor: default; }

/* ── Error ── */
.err { font-size: 0.82rem; color: rgba(239,68,68,0.8); margin: 0.35rem 0 0; }

/* ── Modal ── */
.overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.65);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal {
    background: #0d0d14;
    border: 1px solid rgba(255,255,255,0.1);
    width: 100%;
    max-width: 440px;
    margin: 1rem;
}

.modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 1rem 1.1rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
}

.modal-title { font-size: 1rem; font-weight: 600; color: rgba(255,255,255,0.9); }
.modal-sub   { font-size: 0.86rem; color: rgba(255,255,255,0.35); margin-top: 0.2rem; }

.modal-close {
    background: none;
    border: none;
    color: rgba(255,255,255,0.35);
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    padding: 0.1rem;
    flex-shrink: 0;
}
.modal-close:hover { color: rgba(255,255,255,0.7); }

.modal-body { padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: 0.9rem; }

.modal-hint {
    font-size: 0.86rem;
    color: rgba(255,255,255,0.28);
    margin: 0;
    line-height: 1.5;
}

.modal-hint code {
    font-family: 'Courier New', monospace;
    font-size: 0.82rem;
    color: rgba(190,145,255,0.6);
    background: rgba(190,145,255,0.06);
    padding: 0.1rem 0.3rem;
}

/* ── Preview table ── */
.preview-table { width: 100%; border-collapse: collapse; }

.preview-table th {
    text-align: left;
    padding: 0.5rem 0.85rem;
    font-size: 0.76rem;
    color: rgba(255,255,255,0.28);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    border-bottom: 1px solid rgba(255,255,255,0.07);
}

.preview-table td {
    padding: 0.65rem 0.85rem;
    font-size: 0.97rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    font-variant-numeric: tabular-nums;
}

.preview-table tr:last-child td { border-bottom: none; }

.td-rating { color: rgba(255,255,255,0.5); }
.td-delta  { font-weight: 600; }
.td-result { color: rgba(255,255,255,0.65); }

.pos { color: #4ade80; }
.neg { color: #f87171; }

/* ── Transitions ── */
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.modal-enter-active, .modal-leave-active { transition: opacity 0.2s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
