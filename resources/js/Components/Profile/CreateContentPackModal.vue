<script setup>
import { ref, reactive, computed } from 'vue';
import axios from 'axios';
import SiteModal from '@/Components/Site/SiteModal.vue';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

const props = defineProps({
    show: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'created']);

const MAX_FILES         = 50;
const MAX_SIZE_MB       = 10;
const MAX_TOTAL_SIZE_MB = 50;
const ALLOWED_MIME      = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

const form = reactive({
    title:       '',
    description: '',
    price:       '',
});
const photos      = ref([]); // [{file, preview, name, error}]
const coverIndex  = ref(null); // index of chosen cover photo
const errors      = ref({});
const submitting  = ref(false);

const totalSizeMB = computed(() => {
    const bytes = photos.value.reduce((sum, p) => sum + (p.file?.size || 0), 0);
    return bytes / (1024 * 1024);
});

function close() {
    if (submitting.value) return;
    resetForm();
    emit('close');
}

function resetForm() {
    form.title       = '';
    form.description = '';
    form.price       = '';
    photos.value.forEach(p => URL.revokeObjectURL(p.preview));
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
    const toAdd = files;
    
    for (const file of toAdd) {
        if (photos.value.length >= MAX_FILES) {
            errors.value.photos = __('pack.error.max_photos', { count: MAX_FILES });
            break;
        }

        let fileError = null;
        if (!ALLOWED_MIME.includes(file.type)) {
            fileError = __('pack.error.format');
        } else if (file.size > MAX_SIZE_MB * 1024 * 1024) {
            fileError = __('pack.error.file_size', { name: file.name, size: MAX_SIZE_MB });
        }

        const preview = URL.createObjectURL(file);
        photos.value.push({ 
            file, 
            preview, 
            name: file.name, 
            error: fileError 
        });
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
    
    // Client-side validation
    if (!form.title.trim()) { errors.value.title = __('pack.error.title'); }
    if (!form.price || Number(form.price) < 1) { errors.value.price = __('pack.error.price'); }
    
    if (!photos.value.length) { 
        errors.value.photos = __('pack.error.no_photos'); 
    } else {
        const hasFileErrors = photos.value.some(p => p.error);
        if (hasFileErrors) {
            errors.value.photos = __('pack.error.server'); 
        } else if (totalSizeMB.value > MAX_TOTAL_SIZE_MB) {
            errors.value.photos = __('pack.error.server') + ' (' + totalSizeMB.value.toFixed(1) + 'MB / ' + MAX_TOTAL_SIZE_MB + 'MB)';
        }
    }

    if (Object.keys(errors.value).length > 0) return;
    if (coverIndex.value === null) { errors.value.photos = __('pack.error.no_cover'); return; }

    const fd = new FormData();
    fd.append('title',       form.title.trim());
    fd.append('description', form.description.trim());
    fd.append('price',       form.price);
    fd.append('cover_index', coverIndex.value);
    
    // Clear any previous individual file errors before submitting
    photos.value.forEach(p => p.error = null);

    photos.value.forEach((p) => fd.append('photos[]', p.file));

    submitting.value = true;
    try {
        await axios.post(route('content-packs.store'), fd);
        resetForm();
        emit('created');
        emit('close');
    } catch (e) {
        if (e.response?.status === 422) {
            const errs = e.response.data.errors ?? {};
            const normalized = { ...errs };
            
            // Map individual photo errors back to the photos array
            Object.entries(errs).forEach(([key, messages]) => {
                if (key.startsWith('photos.')) {
                    const index = parseInt(key.split('.')[1]);
                    if (photos.value[index]) {
                        photos.value[index].error = Array.isArray(messages) ? messages[0] : messages;
                    }
                }
            });

            if (!normalized.photos) {
                const photoEntry = Object.entries(errs).find(([k]) => k.startsWith('photos.'));
                if (photoEntry) normalized.photos = Array.isArray(photoEntry[1]) ? photoEntry[1][0] : photoEntry[1];
            }
            errors.value = normalized;
        } else if (e.response?.status === 413) {
            errors.value = { photos: __('pack.error.server') + ' (Request too large)' };
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
        <div class="cpm-outer">
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
                    <span class="cpm-hint">
                        {{ __('pack.field.photos_hint', { max: MAX_FILES, total: MAX_TOTAL_SIZE_MB }) }}
                        <span v-if="photos.length" class="cpm-total-size" :class="{ 'cpm-total-size--error': totalSizeMB > MAX_TOTAL_SIZE_MB }">
                            — {{ totalSizeMB.toFixed(1) }} МБ
                        </span>
                    </span>
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
                        :class="{ 
                            'cpm-thumb--cover': coverIndex === i,
                            'cpm-thumb--error': p.error
                        }"
                        @click="coverIndex = coverIndex === i ? null : i"
                        :title="p.error || __('profile.content.select_cover_title')"
                    >
                        <img :src="p.preview" :alt="p.name" />
                        <button class="cpm-thumb__del" @click.stop="removePhoto(i)" :title="__('common.delete')">×</button>
                        <div v-if="coverIndex === i" class="cpm-thumb__cover-badge">{{ __('pack.cover_badge') }}</div>
                        <div v-if="p.error" class="cpm-thumb__error-icon" :title="p.error">!</div>
                    </div>
                </div>
                <p v-if="photos.length" class="cpm-cover-hint">{{ __('pack.cover_hint') }} <span class="req">*</span></p>
            </div>

        </div>

        <!-- Fixed footer -->
        <div class="cpm-footer">
            <button class="cpm-cancel" @click="close" :disabled="submitting">{{ __('common.cancel') }}</button>
            <button class="cpm-submit" @click="submit" :disabled="submitting">
                <svg v-if="submitting" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="cpm-spin">
                    <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                </svg>
                {{ submitting ? __('pack.submit.loading') : __('pack.submit') }}
            </button>
        </div>
        </div><!-- /cpm-outer -->
    </SiteModal>
</template>

<style scoped>
.cpm-outer {
    display: flex;
    flex-direction: column;
    min-height: 100%;
}

.cpm-wrap {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    flex: 1;
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

.cpm-total-size {
    margin-left: 0.5rem;
    color: rgba(255,255,255,0.4);
}
.cpm-total-size--error {
    color: #ff7b7b;
    font-weight: 600;
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
.cpm-thumb--error { border-color: #ff7b7b !important; }

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
.cpm-thumb__error-icon {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 18px;
    height: 18px;
    background: #ff7b7b;
    color: #fff;
    border-radius: 50%;
    font-size: 12px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 4px rgba(0,0,0,0.5);
}
.cpm-cover-hint {
    font-size: 0.78rem;
    color: rgba(255,255,255,0.25);
    margin: 0.25rem 0 0;
}

.cpm-footer {
    position: sticky;
    bottom: -2rem;
    margin: 0.5rem -2rem -2rem;
    padding: 0.85rem 2rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background: rgba(6, 7, 13, 0.97);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-top: 1px solid rgba(100, 210, 255, 0.07);
    box-shadow: 0 -12px 28px rgba(0, 0, 0, 0.35);
}

@media (max-width: 768px) {
    .cpm-footer {
        bottom: -1.25rem;
        margin: 0.5rem -1.25rem -1.25rem;
        padding: 0.85rem 1.25rem;
    }
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
