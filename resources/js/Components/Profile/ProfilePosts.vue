<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Close, Delete, Plus } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import CreateButton from '@/Components/CreateButton.vue';

const props = defineProps({
    posts:   { default: null },
    isOwner: { type: Boolean, default: false },
});

const createModal = ref(false);
const photoPreview = ref(null);

const form = useForm({ body: '', photo: null });
const photoError = ref('');

function onPhotoChange(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    if (file.size > 1024 * 1024) {
        photoError.value = 'Файл слишком большой. Максимум 1 МБ.';
        form.photo = null;
        photoPreview.value = null;
        return;
    }
    photoError.value = '';
    form.photo = file;
    const reader = new FileReader();
    reader.onload = (ev) => (photoPreview.value = ev.target.result);
    reader.readAsDataURL(file);
}

function removePhoto() {
    form.photo = null;
    photoPreview.value = null;
    photoError.value = '';
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

// ── Delete confirmation ────────────────────────────────────
const confirmDeleteId = ref(null);

function confirmDelete(postId) {
    confirmDeleteId.value = postId;
}

function deletePost() {
    router.delete(route('profile.posts.destroy', confirmDeleteId.value), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            confirmDeleteId.value = null;
            selectedPost.value = null;
        },
    });
}

// ── Post view ─────────────────────────────────────────────
const selectedPost = ref(null);

function openPost(post) {
    selectedPost.value = post;
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
    if (e.key === 'Escape') {
        if (lightboxSrc.value) { closeLightbox(); return; }
        selectedPost.value = null;
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="posts-section">
        <!-- Toolbar: create button for owner -->
        <div v-if="isOwner" class="posts-toolbar">
            <CreateButton @click="createModal = true">
                <template #icon><el-icon><Plus /></el-icon></template>
                Новая запись
            </CreateButton>
        </div>

        <!-- 3-col grid -->
        <div v-if="posts?.length" class="posts-grid">
            <div
                v-for="post in posts" :key="post.id"
                class="post-tile"
                :class="{ 'post-tile--text': !post.photo_url }"
                @click="openPost(post)"
            >
                <img v-if="post.photo_url" :src="post.photo_url" class="post-tile__img"
                     @click.stop="openLightbox(post.photo_url)" />
                <template v-else>
                    <p class="post-tile__text">{{ post.body }}</p>
                    <span class="post-tile__meta">{{ post.created_at }}</span>
                </template>

                <!-- Hover overlay: date + delete (owner only) -->
                <div class="post-tile__overlay">
                    <span class="post-tile__date">{{ post.created_at }}</span>
                    <button v-if="isOwner" class="post-tile__del"
                            @click.stop="confirmDelete(post.id)" title="Удалить">
                        <el-icon class="post-tile__del-icon"><Delete /></el-icon>
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <p v-else class="posts-empty">
            {{ isOwner ? 'Нет публикаций — поделись чем-нибудь' : 'Публикаций пока нет' }}
        </p>

        <!-- Lightbox -->
        <Teleport to="body">
            <div v-if="lightboxSrc" class="lightbox-overlay" @click="closeLightbox">
                <img :src="lightboxSrc" alt="" class="lightbox-img" @click.stop />
            </div>
        </Teleport>

        <!-- Post view modal -->
        <SiteModal :show="!!selectedPost" variant="pink" :compact="true" @close="selectedPost = null">
            <div v-if="selectedPost" class="view-post">
                <div class="view-post__scroll">
                    <img v-if="selectedPost.photo_url"
                         :src="selectedPost.photo_url"
                         class="view-post__photo"
                         @click="openLightbox(selectedPost.photo_url)" />
                    <p class="view-post__body">{{ selectedPost.body }}</p>
                </div>
                <div class="view-post__footer">
                    <span class="view-post__date">{{ selectedPost.created_at }}</span>
                    <button v-if="isOwner" class="view-post__del"
                            @click="confirmDelete(selectedPost.id)"
                            title="Удалить запись">
                        <el-icon><Delete /></el-icon>
                        <span>Удалить запись</span>
                    </button>
                </div>
            </div>
        </SiteModal>

        <!-- Delete confirmation modal -->
        <SiteModal :show="confirmDeleteId !== null" variant="pink" :compact="true" @close="confirmDeleteId = null">
            <div class="confirm-delete">
                <p class="confirm-delete__text">Удалить запись? Это действие нельзя отменить.</p>
                <div class="confirm-delete__actions">
                    <button class="confirm-delete__cancel" @click="confirmDeleteId = null">Отмена</button>
                    <button class="confirm-delete__confirm" @click="deletePost">Удалить</button>
                </div>
            </div>
        </SiteModal>

        <!-- Create post modal -->
        <SiteModal :show="createModal" variant="pink" :compact="false" @close="createModal = false">
            <div class="create-form">
                <h3 class="create-title">Новая запись</h3>

                <div v-if="photoPreview" class="photo-preview-wrap">
                    <img :src="photoPreview" alt="Preview" class="photo-preview" />
                    <button class="remove-photo-btn" type="button" @click="removePhoto"><el-icon><Close /></el-icon></button>
                </div>

                <textarea
                    v-model="form.body"
                    class="post-textarea"
                    placeholder="Напиши что-нибудь..."
                    rows="5"
                    maxlength="277"
                />
                <div class="char-count" :class="{ 'char-count--warn': form.body.length > 250 }">{{ form.body.length }}/277</div>

                <label class="photo-label photo-label--required">
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden-input" @change="onPhotoChange" />
                    <span class="photo-btn">{{ photoPreview ? 'Сменить фото' : '+ Добавить фото (обязательно)' }}</span>
                </label>
                <div v-if="photoError" class="photo-error">{{ photoError }}</div>
                <div v-if="form.errors.photo" class="photo-error">{{ form.errors.photo }}</div>

                <button
                    class="save-btn"
                    :disabled="form.processing || !form.photo || !form.body.trim()"
                    @click="submitPost"
                >Опубликовать</button>
            </div>
        </SiteModal>
    </div>
</template>

<style scoped>
.posts-section { display: flex; flex-direction: column; gap: 0.5rem; }

/* Toolbar */
.posts-toolbar { display: flex; justify-content: flex-start; margin-bottom: 0.5rem; }


/* Grid */
.posts-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2px;
    padding-right: 4px;
}

/* Tile base */
.post-tile {
    position: relative;
    aspect-ratio: 1 / 1;
    overflow: hidden;
    border-radius: 2px;
    background: rgba(255,255,255,0.03);
    cursor: pointer;
}

/* Photo tile */
.post-tile__img {
    width: 100%; height: 100%;
    object-fit: cover; display: block;
    transition: transform 0.3s ease;
    cursor: zoom-in;
}
.post-tile:hover .post-tile__img { transform: scale(1.04); }

/* Text tile */
.post-tile--text {
    border: 1px solid rgba(255,255,255,0.07);
    box-sizing: border-box;
    padding: 0.45rem;
    display: flex; flex-direction: column; justify-content: space-between;
}
.post-tile--text::after {
    content: '';
    position: absolute; top: 0; left: 0; bottom: 0; width: 2px;
    background: rgba(155,110,232,0.45);
}
.post-tile__text {
    font-size: 0.65rem; line-height: 1.4;
    color: rgba(255,255,255,0.7);
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
}
.post-tile__meta {
    font-size: 0.58rem;
    color: rgba(255,255,255,0.28);
    margin-top: 0.3rem;
    flex-shrink: 0;
}

/* Hover overlay */
.post-tile__overlay {
    position: absolute; inset: 0;
    background: linear-gradient(
        to top,
        rgba(0,0,0,0.88) 0%,
        rgba(0,0,0,0.35) 45%,
        transparent 100%
    );
    display: flex; align-items: flex-end; justify-content: space-between;
    padding: 0.38rem 0.35rem 0.35rem;
    opacity: 0; transition: opacity 0.22s ease;
}
.post-tile:hover .post-tile__overlay { opacity: 1; }
.post-tile__date {
    font-size: 0.58rem;
    color: rgba(255,255,255,0.92);
    line-height: 1;
    letter-spacing: 0.02em;
    text-shadow: 0 1px 6px rgba(0,0,0,0.9);
}
.post-tile__del {
    display: flex; align-items: center; justify-content: center;
    width: 20px; height: 20px;
    background: rgba(160,30,55,0.82);
    border: 1px solid rgba(220,60,90,0.55);
    border-radius: 4px;
    cursor: pointer;
    color: rgba(255,200,210,0.95);
    padding: 0;
    transition: background 0.15s, box-shadow 0.15s, transform 0.12s;
    box-shadow: 0 2px 6px rgba(0,0,0,0.5);
}
.post-tile__del-icon { font-size: 0.7rem; }
.post-tile__del:hover {
    background: rgba(210,40,75,0.95);
    box-shadow: 0 0 10px rgba(210,40,75,0.5), 0 2px 6px rgba(0,0,0,0.4);
    transform: scale(1.1);
}

/* Empty state */
.posts-empty {
    padding: 2rem 0; text-align: center;
    font-size: 0.82rem; color: rgba(255,255,255,0.2); margin: 0;
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

/* Post view modal */
.view-post {
    display: flex; flex-direction: column;
    /* fills modal body so sticky footer works */
    margin: -0.25rem 0;
}
.view-post__scroll {
    display: flex; flex-direction: column; gap: 0.85rem;
    overflow-y: auto;
    padding: 0.25rem 0 0.75rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.08) transparent;
}
.view-post__scroll::-webkit-scrollbar { width: 3px; }
.view-post__scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 3px; }
.view-post__photo {
    width: 100%; border-radius: 4px; display: block;
    object-fit: cover; max-height: 280px;
    cursor: zoom-in;
    box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    flex-shrink: 0;
}
.view-post__body {
    font-size: 0.9rem; line-height: 1.65;
    color: rgba(255,255,255,0.82);
    margin: 0; white-space: pre-wrap; word-break: break-word;
}
.view-post__footer {
    display: flex; align-items: center; justify-content: space-between;
    padding-top: 0.5rem;
    border-top: 1px solid rgba(255,255,255,0.06);
    margin-top: auto;
}
.view-post__date {
    font-size: 0.68rem;
    color: rgba(255,255,255,0.55);
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.view-post__del {
    display: flex; align-items: center; gap: 0.28rem;
    padding: 0.22rem 0.5rem;
    background: rgba(140,25,50,0.22);
    border: 1px solid rgba(200,50,80,0.3);
    border-radius: 4px;
    cursor: pointer;
    color: rgba(220,100,120,0.85);
    font-size: 0.7rem;
    font-family: inherit;
    transition: background 0.18s, border-color 0.18s, color 0.18s, box-shadow 0.18s;
}
.view-post__del:hover {
    background: rgba(180,35,65,0.4);
    border-color: rgba(220,70,100,0.55);
    color: rgba(255,145,160,1);
    box-shadow: 0 0 10px rgba(200,40,75,0.22);
}

/* Create form */
.create-form { padding: 0.5rem 0.25rem; display: flex; flex-direction: column; }
.create-title { font-size: 1.1rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0 0 1rem; }

.photo-preview-wrap {
    position: relative;
    margin-bottom: 0.75rem;
}
.photo-preview {
    max-width: 100%; max-height: 160px;
    width: auto; height: auto;
    object-fit: contain; border-radius: 3px;
    display: block; margin: 0 auto;
}
.remove-photo-btn {
    position: absolute; top: 0.4rem; right: 0.4rem;
    background: rgba(0,0,0,0.6); border: none;
    color: #fff; font-size: 0.8rem;
    width: 24px; height: 24px; border-radius: 50%;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
}

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
.post-textarea:focus { border-color: rgba(155,110,232,0.4); }

.char-count {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.25);
    text-align: right;
    margin: 0.25rem 0 1rem;
}
.char-count--warn { color: rgba(190,145,255,0.85); }

.photo-error {
    font-size: 0.78rem;
    color: rgba(190,145,255,0.85);
    margin: -0.5rem 0 0.75rem;
}

.hidden-input { display: none; }
.photo-label { display: block; margin-bottom: 1rem; cursor: pointer; }
.photo-btn {
    font-size: 0.85rem;
    color: rgba(155,110,232,0.7);
    border: 1px dashed rgba(155,110,232,0.3);
    border-radius: 3px;
    padding: 0.4rem 0.85rem;
    transition: all 0.2s;
    display: inline-block;
}
.photo-label:hover .photo-btn { color: rgba(155,110,232,1); border-color: rgba(155,110,232,0.6); }

/* Delete confirmation */
.confirm-delete {
    display: flex; flex-direction: column; gap: 1.25rem;
    padding: 0.25rem 0;
}
.confirm-delete__text {
    font-size: 0.9rem;
    color: rgba(255,255,255,0.75);
    margin: 0;
    line-height: 1.5;
}
.confirm-delete__actions {
    display: flex; gap: 0.6rem; justify-content: flex-end;
}
.confirm-delete__cancel {
    padding: 0.45rem 1rem;
    background: transparent;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 4px;
    color: rgba(255,255,255,0.5);
    font-size: 0.85rem; font-family: inherit; cursor: pointer;
    transition: border-color 0.15s, color 0.15s;
}
.confirm-delete__cancel:hover {
    border-color: rgba(255,255,255,0.25);
    color: rgba(255,255,255,0.8);
}
.confirm-delete__confirm {
    padding: 0.45rem 1rem;
    background: rgba(180,30,60,0.25);
    border: 1px solid rgba(210,50,80,0.4);
    border-radius: 4px;
    color: rgba(255,140,155,0.95);
    font-size: 0.85rem; font-family: inherit; cursor: pointer;
    transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
}
.confirm-delete__confirm:hover {
    background: rgba(210,40,75,0.4);
    border-color: rgba(230,70,100,0.65);
    box-shadow: 0 0 10px rgba(210,40,75,0.25);
}

.save-btn {
    width: 100%; padding: 0.8rem; border-radius: 3px;
    border: 1px solid rgba(155,110,232,0.35);
    background: linear-gradient(135deg, rgba(155,110,232,0.25), rgba(155,110,232,0.1));
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: all 0.2s;
}
.save-btn:hover:not(:disabled) { background: linear-gradient(135deg, rgba(155,110,232,0.38), rgba(155,110,232,0.18)); }
.save-btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
