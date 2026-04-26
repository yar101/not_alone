<script setup>
import { ref, reactive } from 'vue';
import axios from 'axios';
import SiteModal from '@/Components/Site/SiteModal.vue';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

const props = defineProps({
    show: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'created']);

const MAX_FILES    = 50;
const MAX_SIZE_MB  = 10;
const ALLOWED_MIME = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

const form = reactive({
    title:       '',
    description: '',
    price:       '',
});
const photos      = ref([]); // [{file, preview, name}]
const coverIndex  = ref(null); // index of chosen cover photo
const errors      = ref({});
const submitting  = ref(false);

function close() {
    if (submitting.value) return;
    resetForm();
    emit('close');
}

function resetForm() {
    form.title       = '';
    form.description = '';
    form.price       = '';
    photos.value     = [];
    coverIndex.value = null;
    errors.value     = {};
    submitting.value = false;
}

function handleFileInput(e) {
    addFiles(Array.from(e.target.files || []));
    e.target.value = '';
}

function handleDrop(e) {
    e.preventDefault();
    addFiles(Array.from(e.dataTransfer.files || []));
}

function addFiles(files) {
    errors.value.photos = null;
    const remaining = MAX_FILES - photos.value.length;
    if (remaining <= 0) {
        errors.value.photos = __('pack.error.max_photos', { count: MAX_FILES });
        return;
    }
    const toAdd = files.slice(0, remaining);
    for (const file of toAdd) {
        if (!ALLOWED_MIME.includes(file.type)) {
            errors.value.photos = __('pack.error.format');
            continue;
        }
        if (file.size > MAX_SIZE_MB * 1024 * 1024) {
            errors.value.photos = __('pack.error.file_size', { name: file.name, size: MAX_SIZE_MB });
            continue;
        }
        const preview = URL.createObjectURL(file);
        photos.value.push({ file, preview, name: file.name });
    }
}

function removePhoto(index) {
    URL.revokeObjectURL(photos.value[index].preview);
    photos.value.splice(index, 1);
    if (coverIndex.value === index) coverIndex.value = null;
    else if (coverIndex.value > index) coverIndex.value--;
}

async function submit() {
    errors.value = {};
    if (!form.title.trim()) { errors.value.title = __('pack.error.title'); return; }
    if (!form.price || Number(form.price) < 1) { errors.value.price = __('pack.error.price'); return; }
    if (!photos.value.length) { errors.value.photos = __('pack.error.no_photos'); return; }
    if (coverIndex.value === null) { errors.value.photos = __('pack.error.no_cover'); return; }

    const fd = new FormData();
    fd.append('title',       form.title.trim());
    fd.append('description', form.description.trim());
    fd.append('price',       form.price);
    fd.append('cover_index', coverIndex.value);
    photos.value.forEach((p) => fd.append('photos[]', p.file));

    submitting.value = true;
    try {
        await axios.post(route('content-packs.store'), fd);
        resetForm();
        emit('created');
        emit('close');
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        } else {
            errors.value = { photos: __('pack.error.server') };
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <SiteModal :show="show" variant="cyan" :max-width="'640px'" @close="close">
        <div class="cpm-wrap">
            <h2 class="cpm-title">{{ __('pack.create_title') }}</h2>

            <!-- Title -->
            <div class="cpm-field">
                <label class="cpm-label">{{ __('pack.field.title') }} <span class="req">*</span></label>
                <input
                    v-model="form.title"
                    class="cpm-input"
                    :class="{ 'cpm-input--error': errors.title }"
                    type="text"
                    maxlength="120"
                    :placeholder="__('pack.field.title_placeholder')"
                />
                <span v-if="errors.title" class="cpm-err">{{ errors.title }}</span>
            </div>

            <!-- Description -->
            <div class="cpm-field">
                <label class="cpm-label">{{ __('pack.field.desc') }}</label>
                <textarea
                    v-model="form.description"
                    class="cpm-input cpm-textarea"
                    maxlength="2000"
                    rows="3"
                    :placeholder="__('pack.field.desc_placeholder')"
                />
            </div>

            <!-- Price -->
            <div class="cpm-field">
                <label class="cpm-label">{{ __('pack.field.price') }} <span class="req">*</span></label>
                <input
                    v-model="form.price"
                    class="cpm-input"
                    :class="{ 'cpm-input--error': errors.price }"
                    type="number"
                    min="1"
                    max="999999"
                    placeholder="500"
                />
                <span v-if="errors.price" class="cpm-err">{{ errors.price }}</span>
            </div>

            <!-- Photos -->
            <div class="cpm-field">
                <label class="cpm-label">
                    {{ __('pack.field.photos') }} <span class="req">*</span>
                    <span class="cpm-hint">{{ __('pack.field.photos_hint', { max: MAX_FILES, size: MAX_SIZE_MB }) }}</span>
                </label>

                <!-- Drop zone -->
                <div
                    class="cpm-dropzone"
                    :class="{ 'cpm-dropzone--active': photos.length < MAX_FILES }"
                    @dragover.prevent
                    @drop="handleDrop"
                    @click="$refs.fileInput.click()"
                >
                    <input
                        ref="fileInput"
                        type="file"
                        multiple
                        accept="image/jpeg,image/jpg,image/png,image/webp"
                        style="display:none"
                        @change="handleFileInput"
                    />
                    <svg class="cpm-dropzone__icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                    </svg>
                    <span class="cpm-dropzone__text">
                        {{ __('pack.dropzone_text') }}
                        <br/><small>({{ photos.length }}/{{ MAX_FILES }})</small>
                    </span>
                </div>

                <span v-if="errors.photos" class="cpm-err">{{ errors.photos }}</span>

                <!-- Thumbnails -->
                <div v-if="photos.length" class="cpm-thumbs">
                    <div
                        v-for="(p, i) in photos"
                        :key="i"
                        class="cpm-thumb"
                        :class="{ 'cpm-thumb--cover': coverIndex === i }"
                        @click="coverIndex = coverIndex === i ? null : i"
                        :title="__('profile.content.select_cover_title')"
                    >
                        <img :src="p.preview" :alt="p.name" />
                        <button class="cpm-thumb__del" @click.stop="removePhoto(i)" :title="__('common.delete')">×</button>
                        <div v-if="coverIndex === i" class="cpm-thumb__cover-badge">{{ __('pack.cover_badge') }}</div>
                    </div>
                </div>
                <p v-if="photos.length" class="cpm-cover-hint">{{ __('pack.cover_hint') }} <span class="req">*</span></p>
            </div>

            <!-- Actions -->
            <div class="cpm-actions">
                <button class="cpm-cancel" @click="close" :disabled="submitting">{{ __('common.cancel') }}</button>
                <button class="cpm-submit" @click="submit" :disabled="submitting">
                    <svg v-if="submitting" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="cpm-spin">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                    </svg>
                    {{ submitting ? __('pack.submit.loading') : __('pack.submit') }}
                </button>
            </div>
        </div>
    </SiteModal>
</template>

<style scoped>
.cpm-wrap {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

.cpm-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: rgba(255,255,255,0.9);
    margin: 0;
}

.cpm-field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.cpm-label {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.5);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.req { color: #ff7b7b; }

.cpm-hint {
    font-size: 0.78rem;
    color: rgba(255,255,255,0.3);
    font-weight: 400;
}

.cpm-input {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    color: rgba(255,255,255,0.88);
    font-size: 0.92rem;
    font-family: inherit;
    padding: 0.55rem 0.75rem;
    outline: none;
    transition: border-color 0.15s;
    width: 100%;
    box-sizing: border-box;
}
.cpm-input:focus { border-color: rgba(100,210,255,0.4); }
.cpm-input--error { border-color: rgba(255,100,100,0.5); }

.cpm-textarea { resize: vertical; min-height: 72px; }

.cpm-err {
    font-size: 0.8rem;
    color: #ff7b7b;
}

.cpm-dropzone {
    border: 2px dashed rgba(100,210,255,0.2);
    border-radius: 10px;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.6rem;
    cursor: pointer;
    color: rgba(255,255,255,0.35);
    transition: border-color 0.15s, background 0.15s;
}
.cpm-dropzone:hover {
    border-color: rgba(100,210,255,0.45);
    background: rgba(100,210,255,0.04);
}

.cpm-dropzone__icon { opacity: 0.5; }

.cpm-dropzone__text {
    font-size: 0.88rem;
    text-align: center;
    line-height: 1.5;
}
.cpm-dropzone__text small {
    font-size: 0.78rem;
    opacity: 0.6;
}

.cpm-thumbs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.cpm-thumb {
    position: relative;
    width: 72px;
    height: 72px;
    border-radius: 6px;
    overflow: hidden;
    border: 2px solid rgba(255,255,255,0.1);
    cursor: pointer;
    transition: border-color 0.15s;
}
.cpm-thumb:hover { border-color: rgba(100,210,255,0.4); }
.cpm-thumb--cover { border-color: #64d2ff; }
.cpm-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.cpm-thumb__del {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 18px;
    height: 18px;
    background: rgba(0,0,0,0.7);
    color: #fff;
    border: none;
    border-radius: 50%;
    font-size: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}
.cpm-thumb__cover-badge {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(100,210,255,0.75);
    color: #fff;
    font-size: 0.62rem;
    font-weight: 600;
    text-align: center;
    padding: 2px 0;
    letter-spacing: 0.02em;
}
.cpm-cover-hint {
    font-size: 0.78rem;
    color: rgba(255,255,255,0.25);
    margin: 0.25rem 0 0;
}

.cpm-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding-top: 0.5rem;
    border-top: 1px solid rgba(255,255,255,0.06);
}

.cpm-cancel {
    padding: 0.5rem 1.1rem;
    border-radius: 7px;
    background: transparent;
    border: 1px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.5);
    font-size: 0.9rem;
    font-family: inherit;
    cursor: pointer;
    transition: border-color 0.15s;
}
.cpm-cancel:hover { border-color: rgba(255,255,255,0.25); }
.cpm-cancel:disabled { opacity: 0.5; cursor: not-allowed; }

.cpm-submit {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1.25rem;
    border-radius: 7px;
    background: rgba(100,210,255,0.12);
    border: 1px solid rgba(100,210,255,0.35);
    color: #64d2ff;
    font-size: 0.9rem;
    font-family: inherit;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s;
}
.cpm-submit:hover:not(:disabled) { background: rgba(100,210,255,0.2); }
.cpm-submit:disabled { opacity: 0.5; cursor: not-allowed; }
@keyframes cpm-spin {
    to { transform: rotate(360deg); }
}
.cpm-spin { animation: cpm-spin 0.8s linear infinite; }
</style>
