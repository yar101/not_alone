<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import { router } from '@inertiajs/vue3';
import { Edit, Paperclip, Close } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';

const props = defineProps({
    user:    { type: Object, required: true },
    isOwner: { type: Boolean, default: false },
});

const editModal = ref(false);
const photoPreview = ref(props.user.pinned_photo_url || null);
const photoFile = ref(null);
const deletePhotoFlag = ref(false);
const saving = ref(false);

const hasContent = computed(() =>
    !!props.user.pinned_body || !!props.user.pinned_photo_url
);

const editor = useEditor({
    extensions: [StarterKit, Underline],
    content: props.user.pinned_body || '',
    editorProps: {
        attributes: { class: 'tiptap-editor' },
    },
});

function openEdit() {
    editor.value?.commands.setContent(props.user.pinned_body || '');
    photoPreview.value = props.user.pinned_photo_url || null;
    photoFile.value = null;
    deletePhotoFlag.value = false;
    editModal.value = true;
}

function onPhotoChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    photoFile.value = file;
    deletePhotoFlag.value = false;
    const reader = new FileReader();
    reader.onload = (ev) => (photoPreview.value = ev.target.result);
    reader.readAsDataURL(file);
}

function removePhoto() {
    photoFile.value = null;
    photoPreview.value = null;
    deletePhotoFlag.value = true;
}

async function save() {
    saving.value = true;
    const body = editor.value?.getHTML() || '';

    if (deletePhotoFlag.value && !photoFile.value) {
        await new Promise((resolve) => {
            router.delete(route('profile.pinned-card.delete-photo'), {
                preserveState: true,
                preserveScroll: true,
                onFinish: resolve,
            });
        });
    }

    const fd = new FormData();
    fd.append('body', body);
    if (photoFile.value) fd.append('photo', photoFile.value);

    router.post(route('profile.pinned-card.update'), fd, {
        preserveState: true,
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { editModal.value = false; },
        onFinish: () => { saving.value = false; },
    });
}

// ── Lightbox ──────────────────────────────────────────────
const lightboxOpen = ref(false);

function openLightbox() { lightboxOpen.value = true; }
function closeLightbox() { lightboxOpen.value = false; }

function onKeydown(e) { if (e.key === 'Escape') closeLightbox(); }
onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));

function toggleMark(mark) {
    if (!editor.value) return;
    switch (mark) {
        case 'bold':      editor.value.chain().focus().toggleBold().run(); break;
        case 'italic':    editor.value.chain().focus().toggleItalic().run(); break;
        case 'underline': editor.value.chain().focus().toggleUnderline().run(); break;
    }
}
</script>

<template>
    <!-- Show nothing if guest and no content -->
    <div
        v-if="hasContent || isOwner"
        class="pinned-card block-card"
    >
        <!-- Premium pinned badge -->
        <div v-if="hasContent" class="pinned-badge" aria-hidden="true">
            <el-icon><Paperclip /></el-icon>
        </div>

        <!-- Placeholder for owner when empty -->
        <div
            v-if="!hasContent && isOwner"
            class="pinned-placeholder"
            @click="openEdit"
        >
            <el-icon class="pinned-placeholder-icon"><Paperclip /></el-icon>
            <span class="pinned-placeholder-text">Добавь приветственную карточку</span>
        </div>

        <!-- Content view -->
        <template v-else>
            <div v-if="user.pinned_photo_url" class="pinned-photo-wrap" @click="openLightbox">
                <img :src="user.pinned_photo_url" alt="Pinned photo" class="pinned-photo" />
            </div>

            <Teleport to="body">
                <div v-if="lightboxOpen" class="lightbox-overlay" @click="closeLightbox">
                    <img :src="user.pinned_photo_url" alt="" class="lightbox-img" @click.stop />
                </div>
            </Teleport>
            <div class="pinned-body">
                <!-- eslint-disable-next-line vue/no-v-html -->
                <div class="pinned-html" v-html="user.pinned_body" />
                <button
                    v-if="isOwner"
                    class="pinned-edit-btn"
                    @click="openEdit"
                    title="Редактировать карточку"
                ><el-icon><Edit /></el-icon></button>
            </div>
        </template>
    </div>

    <!-- Edit modal -->
    <SiteModal :show="editModal" variant="pink" :compact="false" @close="editModal = false">
        <div class="edit-form">
            <h3 class="edit-title">Приветственная карточка</h3>

            <!-- Photo -->
            <div v-if="photoPreview" class="photo-preview-wrap">
                <img :src="photoPreview" alt="Preview" class="photo-preview" />
                <button class="remove-photo-btn" type="button" @click="removePhoto"><el-icon><Close /></el-icon></button>
            </div>
            <label class="photo-label">
                <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden-input" @change="onPhotoChange" />
                <span class="photo-btn">{{ photoPreview ? 'Сменить фото' : '+ Добавить фото' }}</span>
            </label>

            <!-- Toolbar -->
            <div class="tiptap-toolbar">
                <button
                    type="button"
                    class="toolbar-btn"
                    :class="{ active: editor?.isActive('bold') }"
                    @click="toggleMark('bold')"
                ><strong>B</strong></button>
                <button
                    type="button"
                    class="toolbar-btn"
                    :class="{ active: editor?.isActive('italic') }"
                    @click="toggleMark('italic')"
                ><em>I</em></button>
                <button
                    type="button"
                    class="toolbar-btn"
                    :class="{ active: editor?.isActive('underline') }"
                    @click="toggleMark('underline')"
                ><u>U</u></button>
            </div>

            <!-- Editor -->
            <EditorContent :editor="editor" class="tiptap-wrap" />

            <button class="save-btn" :disabled="saving" @click="save">Сохранить</button>
        </div>
    </SiteModal>
</template>

<style scoped>
.pinned-card {
    background: linear-gradient(135deg, rgba(200,70,126,0.08) 0%, rgba(140,60,180,0.04) 100%);
    border: 1px solid rgba(200,70,126,0.22);
    border-radius: 3px;
    overflow: hidden;
    position: relative;
    transition: transform 0.2s ease, box-shadow 0.25s ease, border-color 0.2s ease;
}
.pinned-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(200,70,126,0.65) 35%, rgba(200,70,126,0.45) 65%, transparent 100%);
}
.pinned-card:hover {
    transform: translateY(-2px);
    border-color: rgba(200,70,126,0.38);
    box-shadow: 0 12px 36px rgba(0,0,0,0.4), 0 0 50px rgba(200,70,126,0.09), 0 0 0 1px rgba(200,70,126,0.12);
}
.pinned-badge {
    position: absolute; top: 0.65rem; right: 0.75rem; z-index: 3;
    color: rgba(200,70,126,0.5); font-size: 0.85rem;
    pointer-events: none;
}

.pinned-placeholder {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 1.25rem 1.5rem;
    cursor: pointer;
    opacity: 0.5;
    transition: opacity 0.2s;
}
.pinned-placeholder:hover { opacity: 0.85; }
.pinned-placeholder-icon { font-size: 1.2rem; color: rgba(200,70,126,0.6); }
.pinned-placeholder-text { font-size: 0.9rem; color: rgba(255,255,255,0.7); }

.pinned-photo-wrap {
    cursor: zoom-in;
    overflow: hidden;
}
.pinned-photo {
    width: 100%;
    max-height: 400px;
    object-fit: cover;
    display: block;
    transition: transform 0.3s ease;
}
.pinned-photo-wrap:hover .pinned-photo { transform: scale(1.02); }

.pinned-body {
    padding: 1rem 1.25rem;
    position: relative;
}

.pinned-html {
    font-size: 0.95rem;
    color: rgba(255,255,255,0.82);
    line-height: 1.6;
}

/* Rich text styles */
.pinned-html :deep(strong) { color: rgba(255,255,255,0.95); font-weight: 600; }
.pinned-html :deep(em) { font-style: italic; }
.pinned-html :deep(u) { text-decoration: underline; }
.pinned-html :deep(p) { margin: 0 0 0.5rem; }
.pinned-html :deep(p:last-child) { margin: 0; }
.pinned-html :deep(ul), .pinned-html :deep(ol) { padding-left: 1.25rem; margin: 0.5rem 0; }

.pinned-edit-btn {
    position: absolute; top: 0.75rem; right: 0.75rem;
    background: none; border: none; cursor: pointer;
    font-size: 1rem; opacity: 0; transition: opacity 0.2s;
    padding: 0.2rem;
}
.pinned-body:hover .pinned-edit-btn { opacity: 0.7; }
.pinned-edit-btn:hover { opacity: 1 !important; }

/* Edit form */
.edit-form { padding: 0.5rem 0.25rem; }
.edit-title { font-size: 1.1rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0 0 1rem; }

.photo-preview-wrap { position: relative; margin-bottom: 0.75rem; }
.photo-preview { width: 100%; max-height: 260px; object-fit: cover; border-radius: 3px; display: block; }
.remove-photo-btn {
    position: absolute; top: 0.4rem; right: 0.4rem;
    background: rgba(0,0,0,0.6); border: none;
    color: #fff; font-size: 0.8rem;
    width: 24px; height: 24px; border-radius: 50%;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
}

.hidden-input { display: none; }
.photo-label { display: block; margin-bottom: 1rem; cursor: pointer; }
.photo-btn {
    font-size: 0.85rem;
    color: rgba(200,70,126,0.7);
    border: 1px dashed rgba(200,70,126,0.3);
    border-radius: 3px;
    padding: 0.4rem 0.85rem;
    transition: all 0.2s;
    display: inline-block;
}
.photo-label:hover .photo-btn { color: rgba(200,70,126,1); border-color: rgba(200,70,126,0.6); }

.tiptap-toolbar {
    display: flex; gap: 0.35rem; margin-bottom: 0.5rem;
}
.toolbar-btn {
    padding: 0.3rem 0.6rem; border-radius: 3px;
    border: 1px solid rgba(255,255,255,0.1);
    background: transparent; color: rgba(255,255,255,0.6);
    font-size: 0.88rem; cursor: pointer; font-family: inherit;
    transition: all 0.15s;
}
.toolbar-btn:hover { border-color: rgba(200,70,126,0.4); color: #fff; }
.toolbar-btn.active { background: rgba(200,70,126,0.18); border-color: rgba(200,70,126,0.5); color: #fff; }

.tiptap-wrap {
    margin-bottom: 1rem;
}
</style>

<style>
/* Lightbox — global because rendered via Teleport outside component */
.lightbox-overlay {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.88);
    display: flex; align-items: center; justify-content: center;
    cursor: zoom-out;
    animation: pinnedLbFadeIn 0.15s ease;
}
@keyframes pinnedLbFadeIn { from { opacity: 0; } to { opacity: 1; } }
.lightbox-img {
    max-width: 90vw;
    max-height: 90vh;
    object-fit: contain;
    border-radius: 3px;
    cursor: default;
    box-shadow: 0 16px 64px rgba(0,0,0,0.7);
}

/* Tiptap editor — global (not scoped) */
.tiptap-editor {
    min-height: 120px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px;
    padding: 0.75rem;
    color: rgba(255,255,255,0.85);
    font-size: 0.92rem;
    font-family: inherit;
    line-height: 1.55;
    outline: none;
    transition: border-color 0.2s;
}
.tiptap-editor:focus { border-color: rgba(200,70,126,0.4); }
.tiptap-editor p { margin: 0 0 0.4rem; }
.tiptap-editor p:last-child { margin: 0; }
.tiptap-editor ul, .tiptap-editor ol { padding-left: 1.25rem; }
</style>
