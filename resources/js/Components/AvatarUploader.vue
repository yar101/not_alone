<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';
import { Camera } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import ImageDropzone from '@/Components/ImageDropzone.vue';
import { Cropper, CircleStencil } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';

const props = defineProps({
    user:     { type: Object, required: true },
    size:     { type: Number, default: 88 },
    editable: { type: Boolean, default: false },
});

const { __ } = useTranslations();

const initials = props.user?.name?.charAt(0).toUpperCase() ?? '?';
const avatarLoaded = ref(false);
watch(() => props.user?.avatar_url, () => { avatarLoaded.value = false; });

// ── Upload modal ───────────────────────────────────────────
const avatarModal = ref(false);

// ── Crop modal ─────────────────────────────────────────────
const cropModal      = ref(false);
const cropSrc        = ref('');
const cropperRef     = ref(null);
const cropError      = ref('');
const cropUploading  = ref(false);
const cropWrapHeight = ref(380);

function openUpload() {
    if (props.editable) avatarModal.value = true;
}

function processAvatarFile(file) {
    avatarModal.value = false;
    cropError.value = '';
    const url = URL.createObjectURL(file);
    cropSrc.value = url;
    const img = new Image();
    img.onload = () => {
        const ratio = img.naturalHeight / img.naturalWidth;
        cropWrapHeight.value = Math.min(380, Math.max(200, Math.round(420 * ratio)));
    };
    img.src = url;
    cropModal.value = true;
}

function cancelCrop() {
    cropModal.value = false;
    cropSrc.value = '';
    cropError.value = '';
    cropWrapHeight.value = 380;
}

function applyCrop() {
    if (cropError.value || !cropperRef.value) return;
    const { canvas } = cropperRef.value.getResult();
    if (!canvas) return;
    cropUploading.value = true;
    canvas.toBlob(blob => {
        const fd = new FormData();
        fd.append('avatar', blob, 'avatar.jpg');
        router.post(route('profile.update.avatar'), fd, {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => {
                cropUploading.value = false;
                cancelCrop();
            },
        });
    }, 'image/jpeg', 0.92);
}
</script>

<template>
    <div
        class="au-wrap"
        :style="{ width: size + 'px', height: size + 'px' }"
        :class="{ 'au-wrap--editable': editable }"
        @click="openUpload"
    >
        <template v-if="user.avatar_url">
            <div v-if="!avatarLoaded" class="au-shimmer" />
            <img
                :src="user.avatar_url"
                class="au-img"
                :class="{ 'au-img--loaded': avatarLoaded }"
                alt=""
                @load="avatarLoaded = true"
            />
        </template>
        <span v-else class="au-initials" :style="{ fontSize: size * 0.28 + 'px' }">{{ initials }}</span>

        <div v-if="editable" class="au-overlay">
            <el-icon class="au-overlay-icon"><Camera /></el-icon>
        </div>
    </div>

    <!-- Upload modal -->
    <SiteModal :show="avatarModal" variant="pink" :compact="true" @close="avatarModal = false">
        <div class="au-upload-form">
            <h3 class="au-title">{{ __('upload.avatar.title') }}</h3>
            <ImageDropzone :max-size-mb="10" @change="processAvatarFile" />
        </div>
    </SiteModal>

    <!-- Crop modal -->
    <SiteModal :show="cropModal" variant="pink" :compact="true" @close="cancelCrop">
        <div class="au-crop-form">
            <h3 class="au-title">{{ __('upload.avatar.crop') }}</h3>
            <div v-if="cropError" class="au-crop-error">{{ cropError }}</div>
            <template v-else>
                <div class="au-crop-wrap" :style="{ height: cropWrapHeight + 'px' }">
                    <Cropper
                        ref="cropperRef"
                        :src="cropSrc"
                        :stencil-component="CircleStencil"
                        :stencil-props="{ movable: true, resizable: true }"
                        :default-size="{ width: 300, height: 300 }"
                        background-class="cropper-bg"
                        class="au-cropper"
                    />
                </div>
                <div class="au-rotate-row">
                    <button class="au-rotate-btn" type="button" @click="cropperRef.rotate(-90)" title="Повернуть влево">↺</button>
                    <button class="au-rotate-btn" type="button" @click="cropperRef.rotate(90)" title="Повернуть вправо">↻</button>
                </div>
            </template>
            <div class="au-crop-actions">
                <button class="au-save-btn" type="button" :disabled="!!cropError || cropUploading" @click="applyCrop">
                    {{ cropUploading ? __('upload.avatar.saving') : __('common.save') }}
                </button>
            </div>
        </div>
    </SiteModal>
</template>

<style scoped>
.au-wrap {
    position: relative;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    background: color-mix(in srgb, var(--color-base-1), transparent 85%);
    border: 2px solid color-mix(in srgb, var(--color-base-1), transparent 55%);
    display: flex;
    align-items: center;
    justify-content: center;
}

.au-wrap--editable {
    cursor: pointer;
}

.au-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
    opacity: 0;
    transition: opacity 0.35s;
}
.au-img--loaded { opacity: 1; }
.au-shimmer {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: linear-gradient(90deg,
        rgba(255,255,255,0.04) 25%,
        rgba(255,255,255,0.1)  50%,
        rgba(255,255,255,0.04) 75%);
    background-size: 200% 100%;
    animation: au-shimmer 1.5s ease-in-out infinite;
}
@keyframes au-shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.au-initials {
    font-weight: 600;
    color: color-mix(in srgb, var(--color-base-1), white 30%);
    line-height: 1;
    user-select: none;
}

/* Hover overlay */
.au-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.au-wrap--editable:hover .au-overlay {
    opacity: 1;
}

.au-overlay-icon {
    font-size: 1.4rem;
    color: #fff;
}

/* Modals */
.au-upload-form,
.au-crop-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.au-title {
    font-size: 1rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.85);
    margin: 0;
}

.au-crop-error {
    color: #f87171;
    font-size: 0.85rem;
}

.au-crop-wrap {
    width: 100%;
    position: relative;
}

.au-cropper {
    width: 100%;
    height: 100%;
}

.au-rotate-row {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
}

.au-rotate-btn {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.75);
    border-radius: 6px;
    padding: 0.3rem 0.9rem;
    font-size: 1.1rem;
    cursor: pointer;
    transition: background 0.15s;
}
.au-rotate-btn:hover { background: rgba(255, 255, 255, 0.12); }

.au-crop-actions {
    display: flex;
    justify-content: flex-end;
}

.au-save-btn {
    padding: 0.45rem 1.2rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    background: linear-gradient(135deg, color-mix(in srgb, var(--color-base-1), transparent 20%), color-mix(in srgb, var(--color-base-1), black 20%));
    color: #fff;
    transition: opacity 0.15s;
}
.au-save-btn:disabled { opacity: 0.5; cursor: default; }
.au-save-btn:not(:disabled):hover { opacity: 0.85; }

.au-upload-form :deep(.dz-zone) { min-height: 140px; }
</style>
