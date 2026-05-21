<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import SiteModal from '@/Components/Site/SiteModal.vue';
import { useTranslations } from '@/composables/useTranslations';
import AppSelect from '@/Components/AppSelect.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    service: { type: Object, required: true },
    serviceCategories: { type: Array, default: () => [] },
    serviceTimeUnits: { type: Array, default: () => [] },
    // 'review' = initial moderation remarks
    // 'change-request' = published service change request remarks
    mode: { type: String, default: 'review' },
});
const emit = defineEmits(['close', 'submitted', 'fixed']);

const { __, locale } = useTranslations();

const categoryOptions  = computed(() =>
    (props.serviceCategories ?? []).map(c => ({ value: c.id, label: catName(c) }))
);
const timeUnitOptions  = computed(() =>
    (props.serviceTimeUnits ?? []).map(u => ({ value: u.id, label: localUnitName(u) }))
);

const FIELD_LABELS = computed(() => ({
    name:         __('profile.services.form.name'),
    name_ru:      __('profile.services.form.name_ru'),
    name_en:      __('profile.services.form.name_en'),
    category_id:  __('profile.services.form.category'),
    time_unit_id: __('profile.services.form.unit'),
    price:        __('profile.services.form.price'),
}));

const isChangeRequest = computed(() => props.mode === 'change-request');

const review = computed(() => props.service?.latest_review);
const flaggedFields   = computed(() => isChangeRequest.value
    ? (props.service?.pending_change?.flagged_fields ?? [])
    : (review.value?.flagged_fields ?? []));
const fieldComments   = computed(() => isChangeRequest.value
    ? (props.service?.pending_change?.field_comments ?? {})
    : (review.value?.field_comments ?? {}));

const editFields = reactive({
    name_ru:      '',
    name_en:      '',
    category_id:  null,
    time_unit_id: null,
    price:        '',
});

watch([() => props.service, () => props.mode], ([s]) => {
    if (s) {
        if (props.mode === 'change-request') {
            editFields.name_ru      = s.pending_change?.pending_name?.ru ?? s.name_ru ?? '';
            editFields.name_en      = s.pending_change?.pending_name?.en ?? s.name_en ?? '';
            editFields.category_id  = s.pending_change?.pending_category?.id ?? s.category_id ?? null;
            editFields.time_unit_id = s.pending_change?.pending_time_unit?.id ?? s.time_unit?.id ?? null;
            editFields.price        = s.pending_change?.pending_price ?? s.price ?? '';
        } else {
            editFields.name_ru      = s.name_ru ?? '';
            editFields.name_en      = s.name_en ?? '';
            editFields.category_id  = s.category_id ?? null;
            editFields.time_unit_id = s.time_unit?.id ?? null;
            editFields.price        = s.price ?? '';
        }
    }
}, { immediate: true });

const errors     = ref({});
const submitting = ref(false);

function submit() {
    if (isChangeRequest.value) {
        submitChangeRequest();
        return;
    }

    errors.value = {};
    const fd = new FormData();

    const payload = {};
    flaggedFields.value.forEach((field) => {
        if (field === 'name' || field === 'name_ru' || field === 'name_en') {
            payload.name_ru = editFields.name_ru;
            payload.name_en = editFields.name_en;
        }
        if (field === 'category_id')  payload.category_id = editFields.category_id;
        if (field === 'time_unit_id') payload.time_unit_id = editFields.time_unit_id;
        if (field === 'price')        payload.price = editFields.price;
    });

    Object.keys(payload).forEach(key => fd.append(key, payload[key]));

    fd.append('_method', 'PATCH');
    submitting.value = true;
    router.post(route('profile.services.update', props.service.id), fd, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { emit('submitted', props.service.id); emit('close'); },
        onError: (errs) => {
            errors.value     = errs;
            submitting.value = false;
        },
        onFinish: () => { submitting.value = false; },
    });
}

async function submitChangeRequest() {
    errors.value = {};
    const fd = new FormData();

    const payload = {};
    flaggedFields.value.forEach((field) => {
        if (field === 'name' || field === 'name_ru' || field === 'name_en') {
            payload.name_ru = editFields.name_ru;
            payload.name_en = editFields.name_en;
        }
        if (field === 'category_id')  payload.category_id = editFields.category_id;
        if (field === 'time_unit_id') payload.time_unit_id = editFields.time_unit_id;
        if (field === 'price')        payload.price = editFields.price;
    });

    Object.keys(payload).forEach(key => fd.append(key, payload[key]));

    submitting.value = true;
    router.post(route('profile.services.fix-change-request', props.service.id), fd, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { emit('fixed', props.service.id); emit('close'); },
        onError: (errs) => {
            errors.value     = errs;
            submitting.value = false;
        },
        onFinish: () => { submitting.value = false; },
    });
}

function catName(cat) {
    return locale.value?.current === "en" && cat?.name_en ? cat.name_en : (cat?.name_ru ?? cat?.name ?? "");
}
function localUnitName(unit) {
    return locale.value?.current === "en" && unit?.name_en ? unit.name_en : (unit?.name_ru ?? "");
}
</script>

<template>
    <SiteModal :show="show" variant="pink" max-width="600px" compact @close="emit('close')">
        <div class="rm">

            <!-- Header -->
            <div class="rm-header">
                <div class="rm-header__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div>
                    <h2 class="rm-header__title">{{ __('profile.services.remarks.title') }}</h2>
                    <p class="rm-header__sub">{{ __('profile.services.remarks.sub') }}</p>
                </div>
            </div>

            <!-- Flagged fields -->
            <template v-if="flaggedFields.length">
                <div class="rm-section-label">{{ __('profile.services.remarks.fields_label') }}</div>
                <div class="rm-fields">
                    <div v-for="field in flaggedFields" :key="field" class="rm-field">
                        <div class="rm-field__head">
                            <span class="rm-field__name">{{ FIELD_LABELS[field] || field }}</span>
                            <span class="rm-field__dot" />
                        </div>

                        <div v-if="fieldComments[field]" class="rm-comment">
                            <div class="rm-comment__bar" />
                            <p class="rm-comment__text">{{ fieldComments[field] }}</p>
                        </div>

                        <template v-if="field === 'name' || field === 'name_ru'">
                            <input
                                v-model="editFields.name_ru"
                                class="rm-input"
                                type="text"
                                maxlength="120"
                                :placeholder="__('profile.services.form.name_ru')"
                            />
                        </template>
                        <template v-else-if="field === 'name_en'">
                            <input
                                v-model="editFields.name_en"
                                class="rm-input"
                                type="text"
                                maxlength="120"
                                :placeholder="__('profile.services.form.name_en')"
                            />
                        </template>
                        <input
                            v-else-if="field === 'price'"
                            v-model="editFields.price"
                            class="rm-input"
                            type="number"
                            min="1"
                            max="999999"
                            :placeholder="__('profile.services.form.price')"
                        />
                        <AppSelect
                            v-else-if="field === 'category_id'"
                            v-model="editFields.category_id"
                            :options="categoryOptions"
                            :placeholder="__('profile.services.form.category_ph')"
                        />
                        <AppSelect
                            v-else-if="field === 'time_unit_id'"
                            v-model="editFields.time_unit_id"
                            :options="timeUnitOptions"
                            :placeholder="__('profile.services.form.unit_ph')"
                        />

                        <span v-if="errors[field]" class="rm-err">{{ errors[field] }}</span>
                    </div>
                </div>
            </template>

            <!-- Empty state -->
            <div v-if="!flaggedFields.length" class="rm-empty">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span>{{ __('profile.services.remarks.empty') }}</span>
            </div>

            <!-- Footer actions -->
            <div class="rm-footer">
                <button class="rm-btn rm-btn--cancel" @click="emit('close')">{{ __('common.cancel') }}</button>
                <button class="rm-btn rm-btn--submit" :disabled="submitting" @click="submit">
                    <svg v-if="!submitting" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="rm-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                    {{ submitting ? __('pack.submit.loading') : __('pack.submit') }}
                </button>
            </div>

        </div>
    </SiteModal>
</template>

<style scoped>
.rm {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* ── Header ──────────────────────────────────────────────── */
.rm-header {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
}
.rm-header__icon {
    flex-shrink: 0;
    width: 38px;
    height: 38px;
    border-radius: 8px;
    background: rgba(220, 50, 50, 0.12);
    border: 1px solid rgba(220, 50, 50, 0.28);
    color: rgba(255, 100, 100, 0.95);
    display: flex;
    align-items: center;
    justify-content: center;
}
.rm-header__title {
    margin: 0 0 0.2rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.92);
    line-height: 1.2;
}
.rm-header__sub {
    margin: 0;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.38);
    line-height: 1.45;
}

/* ── Section label ───────────────────────────────────────── */
.rm-section-label {
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.28);
    margin-bottom: -0.5rem;
}

/* ── Flagged field card ──────────────────────────────────── */
.rm-fields {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.rm-field {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    background: rgba(255, 178, 239, 0.05);
    border: 1px solid rgba(255, 178, 239, 0.15);
    border-radius: 8px;
    padding: 0.85rem;
}
.rm-field__head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.rm-field__name {
    font-size: 0.9rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
}
.rm-field__dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
    background: rgba(220, 60, 60, 0.85);
    box-shadow: inset 0 1px 0 rgba(255, 130, 130, 0.6);
}

/* ── Admin comment (quoted style) ───────────────────────── */
.rm-comment {
    display: flex;
    gap: 0.65rem;
    align-items: stretch;
}
.rm-comment__bar {
    flex-shrink: 0;
    width: 3px;
    border-radius: 3px;
    background: rgba(220, 60, 60, 0.55);
}
.rm-comment__text {
    margin: 0;
    font-size: 0.9rem;
    color: rgba(255, 110, 110, 0.85);
    line-height: 1.55;
}

/* ── Inputs ──────────────────────────────────────────────── */
.rm-input {
    width: 100%;
    box-sizing: border-box;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 7px;
    color: rgba(255, 255, 255, 0.88);
    font-size: 0.95rem;
    font-family: inherit;
    padding: 0.5rem 0.75rem;
    outline: none;
    transition: border-color 0.15s;
}
.rm-input:focus { border-color: rgba(255, 140, 100, 0.45); }
.rm-input[type="number"] {
    -moz-appearance: textfield;
}
.rm-input[type="number"]::-webkit-outer-spin-button,
.rm-input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; }

/* ── Error ───────────────────────────────────────────────── */
.rm-err {
    font-size: 0.82rem;
    color: rgba(255, 110, 90, 0.9);
}

/* ── Empty state ─────────────────────────────────────────── */
.rm-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 1.5rem;
    font-size: 0.92rem;
    color: rgba(255, 255, 255, 0.28);
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 8px;
}

/* ── Footer ──────────────────────────────────────────────── */
.rm-footer {
    display: flex;
    gap: 0.6rem;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
}
.rm-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 0.6rem 1.1rem;
    border-radius: 8px;
    font-size: 0.92rem;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    border: 1px solid;
    transition: background 0.15s, color 0.15s;
}
.rm-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.rm-btn--cancel {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.45);
}
.rm-btn--cancel:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.7);
}
.rm-btn--submit {
    flex: 1;
    background: rgba(255, 178, 239, 0.15);
    border-color: rgba(255, 178, 239, 0.38);
    color: rgba(170, 170, 255, 0.95);
    box-shadow: inset 0 1px 0 rgba(180, 180, 255, 0.18);
}
.rm-btn--submit:hover:not(:disabled) {
    background: rgba(255, 178, 239, 0.26);
    color: rgba(200, 200, 255, 1);
}

@keyframes rm-spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
.rm-spin { animation: rm-spin 0.8s linear infinite; }

:deep(.app-select__trigger) {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.1);
}
</style>
