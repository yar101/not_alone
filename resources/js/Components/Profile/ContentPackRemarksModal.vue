<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SiteModal from '@/Components/Site/SiteModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    pack: { type: Object, required: true },
});
const emit = defineEmits(['close']);

const FIELD_LABELS = {
    title:       'Название',
    description: 'Описание',
    price:       'Цена',
};

const review = computed(() => props.pack?.latest_review);
const flaggedFields   = computed(() => review.value?.flagged_fields ?? []);
const fieldComments   = computed(() => review.value?.field_comments ?? {});
const flaggedPhotoIds = computed(() => review.value?.flagged_photo_ids ?? []);
const photoComments   = computed(() => review.value?.photo_comments ?? {});

const editFields = reactive({
    title:       props.pack?.title ?? '',
    description: props.pack?.description ?? '',
    price:       props.pack?.price ?? '',
});

watch(() => props.pack, (p) => {
    if (p) {
        editFields.title       = p.title ?? '';
        editFields.description = p.description ?? '';
        editFields.price       = p.price ?? '';
    }
}, { immediate: true });

const photoFiles   = ref({}); // { [photoId]: File }
const deletedPhotos = ref(new Set()); // photo IDs marked for deletion
const errors       = ref({});
const submitting   = ref(false);

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
        onSuccess: () => { emit('close'); },
        onError: (errs) => {
            errors.value  = errs;
            submitting.value = false;
        },
        onFinish: () => { submitting.value = false; },
    });
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
    <SiteModal :show="show" variant="pink" :max-width="'600px'" @close="emit('close')">
        <div class="rm-wrap">
            <h2 class="rm-title">Замечания администрации</h2>

            <p class="rm-subtitle">Исправьте отмеченные поля и фотографии, затем отправьте на повторную проверку.</p>

            <!-- Flagged fields -->
            <template v-if="flaggedFields.length">
                <div v-for="field in flaggedFields" :key="field" class="rm-field rm-field--flagged">
                    <div class="rm-field__label">{{ FIELD_LABELS[field] || field }}</div>
                    <div v-if="fieldComments[field]" class="rm-admin-comment">
                        <span class="rm-admin-comment__icon">📝</span>
                        {{ fieldComments[field] }}
                    </div>
                    <input
                        v-if="field === 'title'"
                        v-model="editFields.title"
                        class="rm-input"
                        type="text"
                        maxlength="120"
                    />
                    <textarea
                        v-else-if="field === 'description'"
                        v-model="editFields.description"
                        class="rm-input rm-textarea"
                        rows="3"
                        maxlength="2000"
                    />
                    <input
                        v-else-if="field === 'price'"
                        v-model="editFields.price"
                        class="rm-input"
                        type="number"
                        min="1"
                        max="999999"
                    />
                    <span v-if="errors[field]" class="rm-err">{{ errors[field] }}</span>
                </div>
            </template>

            <!-- Flagged photos -->
            <template v-if="flaggedPhotoIds.length">
                <div class="rm-section-label">Фотографии с замечаниями</div>
                <div class="rm-photos">
                    <div v-for="photoId in flaggedPhotoIds" :key="photoId" class="rm-photo">
                        <div class="rm-photo__preview">
                            <img v-if="previewForPhoto(photoId)" :src="previewForPhoto(photoId)" alt="фото" />
                            <div v-else class="rm-photo__empty">нет фото</div>
                        </div>
                        <div v-if="photoComments[photoId]" class="rm-admin-comment rm-admin-comment--photo">
                            <span class="rm-admin-comment__icon">📝</span>
                            {{ photoComments[photoId] }}
                        </div>
                        <template v-if="!deletedPhotos.has(photoId)">
                            <label class="rm-photo__replace">
                                <input
                                    type="file"
                                    accept="image/jpeg,image/jpg,image/png,image/webp"
                                    style="display:none"
                                    @change="handlePhotoReplace(photoId, $event)"
                                />
                                {{ photoFiles[photoId] ? '✓ Загружено' : 'Заменить фото' }}
                            </label>
                            <button class="rm-photo__delete" @click="toggleDeletePhoto(photoId)">Удалить фото</button>
                        </template>
                        <template v-else>
                            <div class="rm-photo__deleted-label">Будет удалено</div>
                            <button class="rm-photo__restore" @click="toggleDeletePhoto(photoId)">Отменить</button>
                        </template>
                        <span v-if="errors['photo_' + photoId]" class="rm-err">{{ errors['photo_' + photoId] }}</span>
                    </div>
                </div>
            </template>

            <div v-if="!flaggedFields.length && !flaggedPhotoIds.length" class="rm-empty">
                Нет конкретных замечаний.
            </div>

            <div class="rm-actions">
                <button class="rm-cancel" @click="emit('close')" :disabled="submitting">Закрыть</button>
                <button class="rm-submit" @click="submit" :disabled="submitting">
                    {{ submitting ? 'Отправка…' : 'Отправить на проверку' }}
                </button>
            </div>
        </div>
    </SiteModal>
</template>

<style scoped>
.rm-wrap {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.rm-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: rgba(255,255,255,0.9);
    margin: 0;
}

.rm-subtitle {
    font-size: 0.88rem;
    color: rgba(255,255,255,0.45);
    margin: 0;
    line-height: 1.5;
}

.rm-field {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.rm-field--flagged {
    background: rgba(255,80,80,0.06);
    border: 1px solid rgba(255,80,80,0.2);
    border-radius: 8px;
    padding: 0.75rem;
}

.rm-field__label {
    font-size: 0.82rem;
    font-weight: 600;
    color: rgba(255,120,120,0.9);
}

.rm-admin-comment {
    background: rgba(255,200,100,0.08);
    border: 1px solid rgba(255,200,100,0.2);
    border-radius: 6px;
    padding: 0.45rem 0.7rem;
    font-size: 0.82rem;
    color: rgba(255,220,140,0.85);
    display: flex;
    gap: 0.4rem;
    align-items: flex-start;
}

.rm-admin-comment--photo { margin-bottom: 0.3rem; }

.rm-admin-comment__icon { flex-shrink: 0; }

.rm-input {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 7px;
    color: rgba(255,255,255,0.88);
    font-size: 0.9rem;
    font-family: inherit;
    padding: 0.5rem 0.7rem;
    outline: none;
    width: 100%;
    box-sizing: border-box;
    transition: border-color 0.15s;
}
.rm-input:focus { border-color: rgba(200,100,100,0.5); }

.rm-textarea { resize: vertical; min-height: 64px; }

.rm-section-label {
    font-size: 0.82rem;
    font-weight: 600;
    color: rgba(255,255,255,0.45);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.rm-photos {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.rm-photo {
    width: 130px;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    background: rgba(255,80,80,0.06);
    border: 1px solid rgba(255,80,80,0.2);
    border-radius: 8px;
    padding: 0.6rem;
}

.rm-photo__preview {
    width: 100%;
    aspect-ratio: 1;
    border-radius: 5px;
    overflow: hidden;
    background: rgba(255,255,255,0.04);
}
.rm-photo__preview img {
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
    font-size: 0.75rem;
    color: rgba(255,255,255,0.25);
}

.rm-photo__replace {
    font-size: 0.8rem;
    color: rgba(160,160,255,0.8);
    cursor: pointer;
    text-align: center;
    padding: 0.3rem;
    border: 1px solid rgba(160,160,255,0.2);
    border-radius: 5px;
    text-align: center;
    transition: background 0.15s;
}
.rm-photo__replace:hover { background: rgba(160,160,255,0.08); }

.rm-photo__delete {
    font-size: 0.78rem;
    color: rgba(255,100,100,0.75);
    cursor: pointer;
    text-align: center;
    padding: 0.3rem;
    border: 1px solid rgba(255,80,80,0.2);
    border-radius: 5px;
    background: transparent;
    font-family: inherit;
    transition: background 0.15s;
    width: 100%;
}
.rm-photo__delete:hover { background: rgba(255,80,80,0.08); }

.rm-photo__deleted-label {
    font-size: 0.75rem;
    color: rgba(255,100,100,0.6);
    text-align: center;
    padding: 0.2rem 0;
    font-style: italic;
}

.rm-photo__restore {
    font-size: 0.78rem;
    color: rgba(160,160,255,0.75);
    cursor: pointer;
    text-align: center;
    padding: 0.3rem;
    border: 1px solid rgba(160,160,255,0.2);
    border-radius: 5px;
    background: transparent;
    font-family: inherit;
    width: 100%;
    transition: background 0.15s;
}
.rm-photo__restore:hover { background: rgba(160,160,255,0.08); }

.rm-err {
    font-size: 0.78rem;
    color: #ff7b7b;
}

.rm-empty {
    font-size: 0.88rem;
    color: rgba(255,255,255,0.3);
    text-align: center;
    padding: 1rem;
}

.rm-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding-top: 0.5rem;
    border-top: 1px solid rgba(255,255,255,0.06);
}

.rm-cancel {
    padding: 0.5rem 1.1rem;
    border-radius: 7px;
    background: transparent;
    border: 1px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.5);
    font-size: 0.9rem;
    font-family: inherit;
    cursor: pointer;
}
.rm-cancel:disabled { opacity: 0.5; cursor: not-allowed; }

.rm-submit {
    padding: 0.5rem 1.25rem;
    border-radius: 7px;
    background: rgba(200,80,80,0.12);
    border: 1px solid rgba(200,80,80,0.35);
    color: #ff9a9a;
    font-size: 0.9rem;
    font-family: inherit;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s;
}
.rm-submit:hover:not(:disabled) { background: rgba(200,80,80,0.2); }
.rm-submit:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
