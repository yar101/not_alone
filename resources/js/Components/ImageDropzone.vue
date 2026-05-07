<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

const props = defineProps({
    preview:   { type: String, default: null },
    accept:    { type: String, default: 'image/jpeg,image/png,image/webp' },
    maxSizeMb: { type: Number, default: 4 },
});

const emit = defineEmits(['change', 'remove']);

const isDragging = ref(false);
const error      = ref('');
const fileInput  = ref(null);
let dragCounter  = 0;

const ACCEPT = props.accept.split(',').map(s => s.trim());

function validate(file) {
    if (!file || !file.type.startsWith('image/')) {
        error.value = __('upload.error.not_image');
        return false;
    }
    if (!ACCEPT.includes(file.type)) {
        error.value = __('upload.error.format', { formats: ACCEPT.map(t => t.split('/')[1].toUpperCase()).join(', ') });
        return false;
    }
    if (file.size > props.maxSizeMb * 1024 * 1024) {
        error.value = __('upload.error.size', { size: props.maxSizeMb });
        return false;
    }
    error.value = '';
    return true;
}

function handleFile(file) {
    if (!validate(file)) return;
    const url = URL.createObjectURL(file);
    emit('change', file, url);
}

// ── Click to browse ──────────────────────────────────────────
function openPicker() {
    fileInput.value?.click();
}

function onInputChange(e) {
    const file = e.target.files[0];
    if (file) handleFile(file);
    e.target.value = '';
}

// ── Drag & Drop ──────────────────────────────────────────────
function onDragEnter(e) {
    e.preventDefault();
    dragCounter++;
    isDragging.value = true;
}

function onDragLeave(e) {
    e.preventDefault();
    dragCounter--;
    if (dragCounter === 0) isDragging.value = false;
}

function onDragOver(e) {
    e.preventDefault();
}

function onDrop(e) {
    e.preventDefault();
    dragCounter = 0;
    isDragging.value = false;
    const file = e.dataTransfer?.files[0];
    if (file) handleFile(file);
}

// ── Clipboard paste ──────────────────────────────────────────
function onPaste(e) {
    const item = [...(e.clipboardData?.items ?? [])].find(i => i.type.startsWith('image/'));
    if (item) handleFile(item.getAsFile());
}

onMounted(() => document.addEventListener('paste', onPaste));
onUnmounted(() => document.removeEventListener('paste', onPaste));
</script>

<template>
    <div class="dz-wrap">
        <!-- Preview state -->
        <div v-if="preview" class="dz-preview">
            <img :src="preview" class="dz-preview__img" alt="preview" />
            <div class="dz-preview__overlay">
                <button type="button" class="dz-preview__replace" @click="openPicker">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    {{ __('upload.replace') }}
                </button>
                <button type="button" class="dz-preview__remove" @click="emit('remove')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6l-1 14H6L5 6"/>
                        <path d="M10 11v6M14 11v6"/>
                        <path d="M9 6V4h6v2"/>
                    </svg>
                    {{ __('upload.delete') }}
                </button>
            </div>
        </div>

        <!-- Drop zone -->
        <div
            v-else
            class="dz-zone"
            :class="{ 'dz-zone--over': isDragging }"
            @click="openPicker"
            @dragenter="onDragEnter"
            @dragleave="onDragLeave"
            @dragover="onDragOver"
            @drop="onDrop"
        >
            <div class="dz-zone__icon">
                <svg v-if="!isDragging" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>
                <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="8 17 12 21 16 17"/>
                    <line x1="12" y1="21" x2="12" y2="7"/>
                    <path d="M3 15a4 4 0 0 1 0-8h1a5 5 0 0 1 9.9-1"/>
                </svg>
            </div>
            <p class="dz-zone__text">
                <span v-if="isDragging">{{ __('upload.drop') }}</span>
                <span v-else>{{ __('upload.hint') }} <kbd>Ctrl+V</kbd><br><span class="dz-zone__link">{{ __('upload.hint.select') }}</span></span>
            </p>
            <p class="dz-zone__hint">{{ ACCEPT.map(t => t.split('/')[1].toUpperCase()).join(', ') }} · {{ __('upload.hint.up_to') }} {{ maxSizeMb }} {{ __('upload.size.suffix') }}</p>
        </div>

        <p v-if="error" class="dz-error">{{ error }}</p>

        <input
            ref="fileInput"
            type="file"
            :accept="accept"
            class="dz-input"
            @change="onInputChange"
        />
    </div>
</template>

<style scoped>
.dz-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

/* ── Drop zone ─────────────────────────────────────────────── */
.dz-zone {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1.5rem 1rem;
    border: 1px dashed rgba(255, 255, 255, 0.14);
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.025);
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    text-align: center;
    user-select: none;
}

.dz-zone:hover {
    border-color: rgba(255, 178, 239, 0.4);
    background: rgba(255, 178, 239, 0.04);
}

.dz-zone--over {
    border-color: rgba(255, 178, 239, 0.7);
    background: rgba(255, 178, 239, 0.08);
}

.dz-zone__icon {
    color: rgba(255, 255, 255, 0.25);
    transition: color 0.15s;
}

.dz-zone:hover .dz-zone__icon,
.dz-zone--over .dz-zone__icon {
    color: rgba(255, 178, 239, 0.6);
}

.dz-zone__text {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.4);
    margin: 0;
    line-height: 1.6;
}

.dz-zone__link {
    color: rgba(255, 178, 239, 0.75);
    text-decoration: underline;
    text-decoration-style: dotted;
    text-underline-offset: 2px;
}

.dz-zone__hint {
    font-size: 0.68rem;
    color: rgba(255, 255, 255, 0.2);
    margin: 0;
    letter-spacing: 0.04em;
}

kbd {
    display: inline-block;
    padding: 0.05em 0.35em;
    font-size: 0.72rem;
    font-family: inherit;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.45);
    line-height: 1.4;
}

/* ── Preview ───────────────────────────────────────────────── */
.dz-preview {
    position: relative;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.dz-preview__img {
    display: block;
    width: 100%;
    max-height: 160px;
    object-fit: cover;
}

.dz-preview__overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    opacity: 0;
    transition: opacity 0.18s ease;
}

.dz-preview:hover .dz-preview__overlay {
    opacity: 1;
}

.dz-preview__replace,
.dz-preview__remove {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.85rem;
    border-radius: 4px;
    font-family: inherit;
    font-size: 0.78rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}

.dz-preview__replace {
    border: 1px solid rgba(255, 255, 255, 0.25);
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.85);
}

.dz-preview__replace:hover {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}

.dz-preview__remove {
    border: 1px solid rgba(239, 68, 68, 0.4);
    background: rgba(239, 68, 68, 0.15);
    color: rgba(239, 68, 68, 0.9);
}

.dz-preview__remove:hover {
    background: rgba(239, 68, 68, 0.3);
    color: #fff;
}

/* ── Error & hidden input ──────────────────────────────────── */
.dz-error {
    font-size: 0.75rem;
    color: rgba(239, 68, 68, 0.85);
    margin: 0;
}

.dz-input {
    display: none;
}
</style>
