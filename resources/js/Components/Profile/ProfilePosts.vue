<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Close } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';

const props = defineProps({
    posts:   { type: Array, default: () => [] },
    isOwner: { type: Boolean, default: false },
});

const createModal = ref(false);
const photoPreview = ref(null);

const form = useForm({ body: '', photo: null });

function onPhotoChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    form.photo = file;
    const reader = new FileReader();
    reader.onload = (ev) => (photoPreview.value = ev.target.result);
    reader.readAsDataURL(file);
}

function removePhoto() {
    form.photo = null;
    photoPreview.value = null;
}

function submitPost() {
    form.post(route('profile.posts.store'), {
        preserveState: true,
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            photoPreview.value = null;
            createModal.value = false;
        },
    });
}

function deletePost(postId) {
    router.delete(route('profile.posts.destroy', postId), {
        preserveState: true,
        preserveScroll: true,
    });
}

// ── Lightbox ──────────────────────────────────────────────
const lightboxSrc = ref(null);

function openLightbox(src) {
    lightboxSrc.value = src;
}

function closeLightbox() {
    lightboxSrc.value = null;
}

function onKeydown(e) {
    if (e.key === 'Escape') closeLightbox();
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="posts-section">
        <!-- Inline add-post button for owner -->
        <button v-if="isOwner" class="add-post-btn" @click="createModal = true">
            + Новая запись
        </button>

        <!-- Posts list -->
        <div v-if="posts.length > 0" class="posts-list">
            <div v-for="post in posts" :key="post.id" class="post-card block-card">
                <div v-if="post.photo_url" class="post-photo-wrap" @click="openLightbox(post.photo_url)">
                    <img :src="post.photo_url" alt="" class="post-photo" />
                </div>
                <div class="post-body">
                    <p class="post-text">{{ post.body }}</p>
                    <div class="post-footer">
                        <span class="post-date">{{ post.created_at }}</span>
                        <button
                            v-if="isOwner"
                            class="post-delete-btn"
                            @click="deletePost(post.id)"
                            title="Удалить"
                        ><el-icon><Close /></el-icon></button>
                    </div>
                </div>
            </div>
        </div>

        <p v-else-if="!isOwner" class="posts-empty">Записей пока нет.</p>

        <!-- Lightbox -->
        <Teleport to="body">
            <div v-if="lightboxSrc" class="lightbox-overlay" @click="closeLightbox">
                <img :src="lightboxSrc" alt="" class="lightbox-img" @click.stop />
            </div>
        </Teleport>

        <!-- Create post modal -->
        <SiteModal :show="createModal" variant="pink" :compact="false" @close="createModal = false">
            <div class="create-form">
                <h3 class="create-title">Новая запись</h3>

                <div class="create-layout">
                    <!-- Left: form -->
                    <div class="create-left">
                        <textarea
                            v-model="form.body"
                            class="post-textarea"
                            placeholder="Напиши что-нибудь..."
                            rows="5"
                            maxlength="2000"
                        />
                        <div class="char-count">{{ form.body.length }}/2000</div>

                        <div v-if="photoPreview" class="photo-preview-wrap">
                            <img :src="photoPreview" alt="Preview" class="photo-preview" />
                            <button class="remove-photo-btn" type="button" @click="removePhoto"><el-icon><Close /></el-icon></button>
                        </div>

                        <label class="photo-label">
                            <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden-input" @change="onPhotoChange" />
                            <span class="photo-btn">{{ photoPreview ? 'Сменить фото' : '+ Добавить фото' }}</span>
                        </label>

                        <button
                            class="save-btn"
                            :disabled="form.processing || !form.body.trim()"
                            @click="submitPost"
                        >Опубликовать</button>
                    </div>

                    <!-- Right: live preview -->
                    <div class="create-right">
                        <div class="preview-label">Предпросмотр</div>
                        <div class="preview-card">
                            <div v-if="photoPreview" class="preview-photo-wrap">
                                <img :src="photoPreview" alt="" class="preview-photo" />
                            </div>
                            <div class="preview-body">
                                <p class="preview-text">{{ form.body || 'Текст записи...' }}</p>
                                <span class="preview-date">сегодня</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SiteModal>
    </div>
</template>

<style scoped>
.posts-section { display: flex; flex-direction: column; gap: 1rem; }

/* Inline add button */
.add-post-btn {
    width: 100%;
    padding: 0.7rem;
    border-radius: 3px;
    border: 1px dashed rgba(200,70,126,0.35);
    background: transparent;
    color: rgba(200,70,126,0.7);
    font-size: 0.9rem;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.2s;
}
.add-post-btn:hover {
    background: rgba(200,70,126,0.06);
    border-color: rgba(200,70,126,0.6);
    color: rgba(200,70,126,1);
}

.posts-list { display: flex; flex-direction: column; gap: 1rem; }

.post-card {
    background: rgba(255,255,255,0.045);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 3px;
    overflow: hidden;
    position: relative;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    transition: transform 0.2s ease, box-shadow 0.25s ease, border-color 0.2s ease;
}
.post-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 1px; z-index: 1;
    background: linear-gradient(90deg, transparent 0%, rgba(200,70,126,0.4) 40%, rgba(120,70,200,0.3) 70%, transparent 100%);
}
.post-card:hover {
    transform: translateY(-2px);
    border-color: rgba(200,70,126,0.2);
    box-shadow: 0 12px 36px rgba(0,0,0,0.4), 0 0 0 1px rgba(200,70,126,0.08), 0 0 40px rgba(200,70,126,0.06);
}

/* Square photo — Instagram style */
.post-photo-wrap {
    width: 100%;
    aspect-ratio: 1 / 1;
    overflow: hidden;
    cursor: zoom-in;
}
.post-photo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.3s ease;
}
.post-photo-wrap:hover .post-photo { transform: scale(1.03); }

.post-body { padding: 1rem 1.25rem; }

.post-text {
    font-size: 0.95rem;
    color: rgba(255,255,255,0.8);
    margin: 0 0 0.75rem;
    white-space: pre-wrap;
    word-break: break-word;
    line-height: 1.55;
}

.post-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.post-date {
    font-size: 0.72rem;
    color: rgba(255,255,255,0.4);
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.07);
    padding: 0.15rem 0.55rem;
    border-radius: 3px;
    letter-spacing: 0.02em;
}

.post-delete-btn {
    background: none; border: none; cursor: pointer;
    color: rgba(255,255,255,0.2); font-size: 0.85rem;
    padding: 0.2rem 0.4rem; border-radius: 3px;
    transition: color 0.2s, background 0.2s;
}
.post-delete-btn:hover { color: rgba(200,70,126,0.8); background: rgba(200,70,126,0.08); }

.posts-empty {
    font-size: 0.88rem;
    color: rgba(255,255,255,0.3);
    text-align: center;
    padding: 1.5rem 0;
    margin: 0;
}

/* Lightbox */
.lightbox-overlay {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.88);
    display: flex; align-items: center; justify-content: center;
    cursor: zoom-out;
    animation: fadeIn 0.15s ease;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.lightbox-img {
    max-width: 90vw;
    max-height: 90vh;
    object-fit: contain;
    border-radius: 3px;
    cursor: default;
    box-shadow: 0 16px 64px rgba(0,0,0,0.7);
}

/* Create form */
.create-form { padding: 0.5rem 0.25rem; }
.create-title { font-size: 1.1rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0 0 1rem; }

.create-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    align-items: start;
}

.create-left { display: flex; flex-direction: column; }

.post-textarea {
    width: 100%; box-sizing: border-box;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px;
    padding: 0.75rem;
    color: rgba(255,255,255,0.85);
    font-size: 0.92rem;
    font-family: inherit;
    resize: vertical;
    outline: none;
    transition: border-color 0.2s;
}
.post-textarea:focus { border-color: rgba(200,70,126,0.4); }

.char-count {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.25);
    text-align: right;
    margin: 0.25rem 0 1rem;
}

.photo-preview-wrap {
    position: relative;
    margin-bottom: 0.75rem;
}
.photo-preview {
    width: 100%; max-height: 200px;
    object-fit: cover; border-radius: 3px;
    display: block;
}
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

.save-btn {
    width: 100%; padding: 0.8rem; border-radius: 3px;
    border: 1px solid rgba(200,70,126,0.35);
    background: linear-gradient(135deg, rgba(200,70,126,0.25), rgba(200,70,126,0.1));
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: all 0.2s;
}
.save-btn:hover:not(:disabled) { background: linear-gradient(135deg, rgba(200,70,126,0.38), rgba(200,70,126,0.18)); }
.save-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Live preview panel */
.create-right { display: flex; flex-direction: column; gap: 0.5rem; }

.preview-label {
    font-size: 0.68rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(200,70,126,0.5);
}

.preview-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 3px;
    overflow: hidden;
}

.preview-photo-wrap {
    width: 100%;
    aspect-ratio: 1 / 1;
    overflow: hidden;
}
.preview-photo {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
}

.preview-body { padding: 0.75rem 1rem; }

.preview-text {
    font-size: 0.88rem;
    color: rgba(255,255,255,0.6);
    margin: 0 0 0.5rem;
    white-space: pre-wrap;
    word-break: break-word;
    line-height: 1.5;
    min-height: 2.5em;
    font-style: italic;
}

.preview-date {
    font-size: 0.72rem;
    color: rgba(255,255,255,0.25);
}

@media (max-width: 640px) {
    .create-layout { grid-template-columns: 1fr; }
    .create-right { display: none; }
}
</style>
