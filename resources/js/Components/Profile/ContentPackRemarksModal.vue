<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import SiteModal from '@/Components/Site/SiteModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    pack: { type: Object, required: true },
    // 'review' = initial moderation remarks (default)
    // 'change-request' = published pack change request remarks
    mode: { type: String, default: 'review' },
});
const emit = defineEmits(['close', 'submitted', 'fixed']);

const FIELD_LABELS = {
    title:       'Название',
    description: 'Описание',
    price:       'Цена',
};

const isChangeRequest = computed(() => props.mode === 'change-request');

const review = computed(() => props.pack?.latest_review);
const flaggedFields   = computed(() => isChangeRequest.value
    ? (props.pack?.pending_change?.flagged_fields ?? [])
    : (review.value?.flagged_fields ?? []));
const fieldComments   = computed(() => isChangeRequest.value
    ? (props.pack?.pending_change?.field_comments ?? {})
    : (review.value?.field_comments ?? {}));
const flaggedPhotoIds = computed(() => isChangeRequest.value ? [] : (review.value?.flagged_photo_ids ?? []));
const photoComments   = computed(() => isChangeRequest.value ? {} : (review.value?.photo_comments ?? {}));

const editFields = reactive({
    title:       props.pack?.title ?? '',
    description: props.pack?.description ?? '',
    price:       props.pack?.price ?? '',
});

watch([() => props.pack, () => props.mode], ([p]) => {
    if (p) {
        if (props.mode === 'change-request') {
            editFields.title       = p.pending_change?.pending_title       ?? p.title       ?? '';
            editFields.description = p.pending_change?.pending_description ?? p.description ?? '';
            editFields.price       = p.pending_change?.pending_price       ?? p.price       ?? '';
        } else {
            editFields.title       = p.title       ?? '';
            editFields.description = p.description ?? '';
            editFields.price       = p.price       ?? '';
        }
    }
}, { immediate: true });

const photoFiles    = ref({});
const deletedPhotos = ref(new Set());
const errors        = ref({});
const submitting    = ref(false);

const MAX_SIZE_MB  = 10;
const ALLOWED_MIME = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

function toggleDeletePhoto(photoId) {
    if (deletedPhotos.value.has(photoId)) {
        deletedPhotos.value.delete(photoId);
    } else {
        deletedPhotos.value.add(photoId);
        delete photoFiles.value[photoId];
    }
}

function handlePhotoReplace(photoId, e) {
    const file = e.target.files?.[0];
    if (!file) return;
    if (!ALLOWED_MIME.includes(file.type)) {
        errors.value['photo_' + photoId] = 'Только JPEG, PNG, WebP.';
        return;
    }
    if (file.size > MAX_SIZE_MB * 1024 * 1024) {
        errors.value['photo_' + photoId] = `Файл превышает ${MAX_SIZE_MB} МБ.`;
        return;
    }
    errors.value['photo_' + photoId] = null;
    photoFiles.value[photoId] = file;
}

function submit() {
    if (isChangeRequest.value) {
        submitChangeRequest();
        return;
    }

    errors.value = {};
    const fd = new FormData();

    flaggedFields.value.forEach((field) => {
        if (field === 'title')       fd.append('title',       editFields.title);
        if (field === 'description') fd.append('description', editFields.description);
        if (field === 'price')       fd.append('price',       editFields.price);
    });

    deletedPhotos.value.forEach(id => fd.append('delete_photo_ids[]', id));

    flaggedPhotoIds.value.forEach((photoId) => {
        if (deletedPhotos.value.has(photoId)) return;
        const file = photoFiles.value[photoId];
        if (file) {
            fd.append('photos[]',            file);
            fd.append('replace_photo_ids[]', photoId);
        }
    });

    fd.append('_method', 'PATCH');
    submitting.value = true;
    router.post(route('content-packs.update', props.pack.id), fd, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { emit('submitted', props.pack.id); emit('close'); },
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

    flaggedFields.value.forEach((field) => {
        if (field === 'title')       fd.append('title',       editFields.title);
        if (field === 'description') fd.append('description', editFields.description);
        if (field === 'price')       fd.append('price',       editFields.price);
    });

    submitting.value = true;
    try {
        const { data } = await axios.post(route('content-packs.fix-change-request', props.pack.id), fd);
        emit('fixed', props.pack.id, data.pending_change);
        emit('close');
    } catch (e) {
        if (e.response?.data?.errors) {
            errors.value = e.response.data.errors;
        }
    } finally {
        submitting.value = false;
    }
}

function photoById(id) {
    return props.pack?.photos?.find(p => p.id == id);
}

function previewForPhoto(id) {
    const file = photoFiles.value[id];
    if (file) return URL.createObjectURL(file);
    return photoById(id)?.url ?? null;
}
</script>

<template>
    <SiteModal :show="show" variant="pink" max-width="600px" @close="emit('close')">
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
                    <h2 class="rm-header__title">Замечания по паку</h2>
                    <p class="rm-header__sub">Исправьте отмеченные поля и отправьте на повторную проверку</p>
                </div>
            </div>

            <!-- Flagged fields -->
            <template v-if="flaggedFields.length">
                <div class="rm-section-label">Поля</div>
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

                        <input
                            v-if="field === 'title'"
                            v-model="editFields.title"
                            class="rm-input"
                            type="text"
                            maxlength="120"
                            placeholder="Название"
                        />
                        <textarea
                            v-else-if="field === 'description'"
                            v-model="editFields.description"
                            class="rm-input rm-textarea"
                            rows="3"
                            maxlength="2000"
                            placeholder="Описание"
                        />
                        <input
                            v-else-if="field === 'price'"
                            v-model="editFields.price"
                            class="rm-input"
                            type="number"
                            min="1"
                            max="999999"
                            placeholder="Цена в рублях"
                        />
                        <span v-if="errors[field]" class="rm-err">{{ errors[field] }}</span>
                    </div>
                </div>
            </template>

            <!-- Flagged photos -->
            <template v-if="flaggedPhotoIds.length">
                <div class="rm-section-label">Фотографии</div>
                <div class="rm-photos">
                    <div
                        v-for="photoId in flaggedPhotoIds"
                        :key="photoId"
                        class="rm-photo"
                        :class="{ 'rm-photo--deleted': deletedPhotos.has(photoId) }"
                    >
                        <!-- Preview -->
                        <div class="rm-photo__img-wrap">
                            <img v-if="previewForPhoto(photoId)" :src="previewForPhoto(photoId)" alt="фото" />
                            <div v-else class="rm-photo__empty">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            </div>
                            <div v-if="deletedPhotos.has(photoId)" class="rm-photo__deleted-overlay">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                            </div>
                            <div v-else-if="photoFiles[photoId]" class="rm-photo__replaced-badge">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                        </div>

                        <!-- Admin comment -->
                        <div v-if="photoComments[photoId]" class="rm-comment rm-comment--photo">
                            <div class="rm-comment__bar" />
                            <p class="rm-comment__text">{{ photoComments[photoId] }}</p>
                        </div>

                        <!-- Actions -->
                        <template v-if="!deletedPhotos.has(photoId)">
                            <label class="rm-photo__action rm-photo__action--replace">
                                <input
                                    type="file"
                                    accept="image/jpeg,image/jpg,image/png,image/webp"
                                    style="display:none"
                                    @change="handlePhotoReplace(photoId, $event)"
                                />
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                {{ photoFiles[photoId] ? 'Заменено' : 'Заменить' }}
                            </label>
                            <button class="rm-photo__action rm-photo__action--delete" @click="toggleDeletePhoto(photoId)">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                Удалить
                            </button>
                        </template>
                        <template v-else>
                            <button class="rm-photo__action rm-photo__action--restore" @click="toggleDeletePhoto(photoId)">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.01"/></svg>
                                Восстановить
                            </button>
                        </template>

                        <span v-if="errors['photo_' + photoId]" class="rm-err">{{ errors['photo_' + photoId] }}</span>
                    </div>
                </div>
            </template>

            <!-- Empty state -->
            <div v-if="!flaggedFields.length && !flaggedPhotoIds.length" class="rm-empty">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span>Конкретных замечаний нет</span>
            </div>

            <!-- Footer actions -->
            <div class="rm-footer">
                <button class="rm-btn rm-btn--submit" :disabled="submitting" @click="submit">
                    <svg v-if="!submitting" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="rm-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                    {{ submitting ? 'Отправка…' : 'Отправить на проверку' }}
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
    background: rgba(160, 160, 255, 0.05);
    border: 1px solid rgba(160, 160, 255, 0.15);
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
.rm-comment--photo { margin-top: 0.25rem; }
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
.rm-textarea {
    resize: vertical;
    min-height: 70px;
    line-height: 1.5;
}
.rm-input[type="number"] {
    -moz-appearance: textfield;
}
.rm-input[type="number"]::-webkit-outer-spin-button,
.rm-input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; }

/* ── Photos grid ─────────────────────────────────────────── */
.rm-photos {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.75rem;
}
.rm-photo {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    background: rgba(160, 160, 255, 0.05);
    border: 1px solid rgba(160, 160, 255, 0.15);
    border-radius: 8px;
    padding: 0.6rem;
    transition: opacity 0.2s;
}
.rm-photo--deleted {
    opacity: 0.5;
}
.rm-photo__img-wrap {
    position: relative;
    aspect-ratio: 1;
    border-radius: 5px;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.04);
}
.rm-photo__img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.rm-photo__empty {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.18);
}
.rm-photo__deleted-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255, 120, 100, 0.85);
}
.rm-photo__replaced-badge {
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: rgba(80, 210, 140, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}

/* Photo action buttons */
.rm-photo__action {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    width: 100%;
    padding: 0.32rem 0.5rem;
    border-radius: 5px;
    font-size: 0.82rem;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    border: 1px solid;
}
.rm-photo__action--replace {
    background: rgba(110, 110, 210, 0.08);
    border-color: rgba(110, 110, 210, 0.22);
    color: rgba(160, 160, 255, 0.75);
}
.rm-photo__action--replace:hover {
    background: rgba(110, 110, 210, 0.16);
    color: rgba(180, 180, 255, 0.95);
}
.rm-photo__action--delete {
    background: transparent;
    border-color: rgba(255, 80, 60, 0.18);
    color: rgba(255, 110, 90, 0.6);
}
.rm-photo__action--delete:hover {
    background: rgba(255, 80, 60, 0.08);
    color: rgba(255, 120, 100, 0.9);
}
.rm-photo__action--restore {
    background: rgba(110, 110, 210, 0.08);
    border-color: rgba(110, 110, 210, 0.22);
    color: rgba(160, 160, 255, 0.75);
}
.rm-photo__action--restore:hover {
    background: rgba(110, 110, 210, 0.16);
    color: rgba(180, 180, 255, 0.95);
}

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
    background: rgba(110, 110, 210, 0.15);
    border-color: rgba(110, 110, 210, 0.38);
    color: rgba(170, 170, 255, 0.95);
    box-shadow: inset 0 1px 0 rgba(180, 180, 255, 0.18);
}
.rm-btn--submit:hover:not(:disabled) {
    background: rgba(110, 110, 210, 0.26);
    color: rgba(200, 200, 255, 1);
}

@keyframes rm-spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
.rm-spin { animation: rm-spin 0.8s linear infinite; }
</style>
