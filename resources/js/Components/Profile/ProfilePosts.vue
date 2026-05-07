<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { useForm, router } from "@inertiajs/vue3";
import axios from "axios";
import { useTranslations } from "@/composables/useTranslations";

const { __ } = useTranslations();
import SiteModal from "@/Components/Site/SiteModal.vue";
import CreateButton from "@/Components/CreateButton.vue";
import PostFeedCard from "@/Components/Profile/PostFeedCard.vue";
import PostDetailModal from "@/Components/Profile/PostDetailModal.vue";

const props = defineProps({
    profileUserId: { type: Number, required: true },
    isOwner: { type: Boolean, default: false },
    authUser: { default: null },
});

// ── Feed ───────────────────────────────────────────────────
const posts = ref([]);
const page = ref(1);
const hasMore = ref(true);
const loading = ref(false);

async function fetchPosts() {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    try {
        const { data } = await axios.get(
            route("profile.posts.feed", props.profileUserId),
            {
                params: { page: page.value },
            },
        );
        posts.value.push(...data.data);
        hasMore.value = data.current_page < data.last_page;
        page.value++;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

function resetFeed() {
    posts.value = [];
    page.value = 1;
    hasMore.value = true;
    fetchPosts();
}

// ── IntersectionObserver sentinel ─────────────────────────
const sentinel = ref(null);
let observer = null;

onMounted(() => {
    fetchPosts();

    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0].isIntersecting) fetchPosts();
        },
        { rootMargin: "120px" },
    );

    if (sentinel.value) observer.observe(sentinel.value);
});

onUnmounted(() => {
    if (observer) observer.disconnect();
});

// ── Like update in-place ───────────────────────────────────
function onLiked({ postId, liked, likesCount }) {
    const p = posts.value.find((x) => x.id === postId);
    if (p) {
        p.liked_by_me = liked;
        p.likes_count = likesCount;
    }
    if (detailPost.value?.id === postId) {
        detailPost.value = {
            ...detailPost.value,
            liked_by_me: liked,
            likes_count: likesCount,
        };
    }
}

function onCommentAdded(postId) {
    const p = posts.value.find((x) => x.id === postId);
    if (p) p.comments_count++;
}

// ── Detail modal ───────────────────────────────────────────
const detailPost = ref(null);

function openDetail(post) {
    detailPost.value = post;
}

function closeDetail() {
    detailPost.value = null;
}

// ── Create post ────────────────────────────────────────────
const createModal = ref(false);
const photoPreview = ref(null);
const photoError = ref("");
const form = useForm({ body: "", photo: null });

function onPhotoChange(e) {
    const file = e.target.files?.[0];
    e.target.value = "";
    if (!file) return;
    if (file.size > 1024 * 1024) {
        photoError.value = __("post.create.photo_error");
        form.photo = null;
        photoPreview.value = null;
        return;
    }
    photoError.value = "";
    form.photo = file;
    const reader = new FileReader();
    reader.onload = (ev) => (photoPreview.value = ev.target.result);
    reader.readAsDataURL(file);
}

function removePhoto() {
    form.photo = null;
    photoPreview.value = null;
    photoError.value = "";
}

function submitPost() {
    form.post(route("profile.posts.store"), {
        preserveState: true,
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            photoPreview.value = null;
            createModal.value = false;
            resetFeed();
        },
    });
}

// ── Delete post ────────────────────────────────────────────
const confirmDeleteId = ref(null);

function confirmDelete(postId) {
    confirmDeleteId.value = postId;
    if (detailPost.value?.id === postId) closeDetail();
}

function deletePost() {
    router.delete(route("profile.posts.destroy", confirmDeleteId.value), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            posts.value = posts.value.filter(
                (p) => p.id !== confirmDeleteId.value,
            );
            confirmDeleteId.value = null;
        },
    });
}
</script>

<template>
    <div class="posts-section">
        <!-- Toolbar: create button for owner -->
        <div v-if="isOwner" class="posts-toolbar">
            <CreateButton @click="createModal = true">{{
                __("post.new")
            }}</CreateButton>
        </div>

        <!-- Skeleton loader -->
        <div v-if="loading && !posts.length" class="posts-skeleton-grid">
            <div v-for="i in 4" :key="i" class="sk-card">
                <div class="sk-card__header">
                    <div class="sk-avatar sk-bone" />
                    <div class="sk-header-text">
                        <div class="sk-name sk-bone" />
                        <div class="sk-date sk-bone" />
                    </div>
                </div>
                <div class="sk-photo sk-bone" />
                <div class="sk-body">
                    <div class="sk-line sk-bone" style="width: 92%" />
                    <div class="sk-line sk-bone" style="width: 78%" />
                    <div class="sk-line sk-bone" style="width: 55%" />
                </div>
                <div class="sk-footer">
                    <div class="sk-action sk-bone" />
                    <div class="sk-action sk-bone" />
                </div>
            </div>
        </div>

        <template v-else>
            <!-- Feed -->
            <div v-if="posts.length" class="posts-feed">
                <PostFeedCard
                    v-for="post in posts"
                    :key="post.id"
                    :post="post"
                    :is-owner="isOwner"
                    :auth-user="authUser"
                    @open-detail="openDetail"
                    @liked="onLiked"
                />
            </div>

            <!-- Empty state -->
            <p v-else class="posts-empty">
                {{ isOwner ? __("post.empty.owner") : __("post.empty.guest") }}
            </p>
        </template>

        <!-- Pagination loader -->
        <div v-if="loading && posts.length" class="posts-loading">
            <span class="posts-loading__dot" />
            <span class="posts-loading__dot" />
            <span class="posts-loading__dot" />
        </div>

        <!-- Sentinel for infinite scroll -->
        <div ref="sentinel" class="posts-sentinel" />

        <!-- Detail modal -->
        <PostDetailModal
            :post="detailPost"
            :is-owner="isOwner"
            :auth-user="authUser"
            @close="closeDetail"
            @liked="onLiked"
            @delete="confirmDelete"
            @comment-added="onCommentAdded"
        />

        <!-- Delete confirmation modal -->
        <SiteModal
            :show="confirmDeleteId !== null"
            variant="pink"
            :compact="true"
            @close="confirmDeleteId = null"
        >
            <div class="confirm-delete">
                <div
                    class="confirm-delete__rule confirm-delete__rule--red"
                ></div>
                <h2 class="confirm-delete__title">
                    {{ __("post.delete.title") }}
                </h2>
                <div
                    class="confirm-delete__rule confirm-delete__rule--red"
                ></div>
                <div class="confirm-delete__perf">
                    <span class="confirm-delete__perf-line"></span>
                </div>
                <div class="confirm-delete__footer">
                    <button
                        class="confirm-delete__cancel"
                        @click="confirmDeleteId = null"
                    >
                        {{ __("common.cancel") }}
                    </button>
                    <button class="confirm-delete__confirm" @click="deletePost">
                        {{ __("common.delete") }}
                    </button>
                </div>
            </div>
        </SiteModal>

        <!-- Create post modal -->
        <SiteModal
            :show="createModal"
            variant="pink"
            :compact="false"
            @close="createModal = false"
        >
            <div class="create-form">
                <h3 class="create-title">{{ __("post.new") }}</h3>

                <div v-if="photoPreview" class="photo-preview-wrap">
                    <img
                        :src="photoPreview"
                        alt="Preview"
                        class="photo-preview"
                    />
                    <button
                        class="remove-photo-btn"
                        type="button"
                        @click="removePhoto"
                    >
                        ✕
                    </button>
                </div>

                <textarea
                    v-model="form.body"
                    class="post-textarea"
                    :placeholder="__('post.create.placeholder')"
                    rows="5"
                    maxlength="377"
                />
                <div
                    class="char-count"
                    :class="{ 'char-count--warn': form.body.length > 340 }"
                >
                    {{ form.body.length }}/377
                </div>

                <label class="photo-label">
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="hidden-input"
                        @change="onPhotoChange"
                    />
                    <span class="photo-btn">{{
                        photoPreview
                            ? __("post.create.change_photo")
                            : __("post.create.add_photo")
                    }}</span>
                </label>
                <div v-if="photoError" class="photo-error">
                    {{ photoError }}
                </div>
                <div v-if="form.errors.photo" class="photo-error">
                    {{ form.errors.photo }}
                </div>

                <button
                    class="save-btn"
                    :disabled="form.processing || !form.body.trim()"
                    @click="submitPost"
                >
                    <span v-if="form.processing" class="save-btn__spinner" />
                    <template v-else>{{ __("common.publish") }}</template>
                </button>
            </div>
        </SiteModal>
    </div>
</template>

<style scoped>
.posts-section {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0 1rem;
}

.posts-toolbar {
    display: flex;
    justify-content: flex-start;
    margin-bottom: 0.75rem;
}

.posts-feed {
    column-count: 2;
    column-gap: 1.6rem;
}

.posts-empty {
    padding: 2rem 0;
    text-align: center;
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.2);
    margin: 0;
}

/* ── Skeleton ─────────────────────────────────────────────── */
@keyframes shimmer {
    0% {
        background-position: -400px 0;
    }
    100% {
        background-position: 400px 0;
    }
}

.sk-bone {
    border-radius: 4px;
    background: linear-gradient(
        90deg,
        rgba(255, 178, 239, 0.05) 0%,
        rgba(255, 178, 239, 0.13) 40%,
        rgba(255, 178, 239, 0.05) 80%
    );
    background-size: 800px 100%;
    animation: shimmer 1.6s infinite linear;
}

.posts-skeleton-grid {
    column-count: 2;
    column-gap: 1.6rem;
}

.sk-card {
    break-inside: avoid;
    margin-bottom: 1.6rem;
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.02);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.sk-card__header {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.6rem 0.85rem;
}

.sk-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    flex-shrink: 0;
}

.sk-header-text {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    flex: 1;
}

.sk-name {
    height: 11px;
    width: 45%;
    border-radius: 4px;
}

.sk-date {
    height: 9px;
    width: 28%;
    border-radius: 4px;
}

.sk-photo {
    width: 100%;
    height: 140px;
}

.sk-body {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding: 0.65rem 0.85rem 0.5rem;
}

.sk-line {
    height: 11px;
    border-radius: 4px;
}

.sk-footer {
    display: flex;
    gap: 0.5rem;
    padding: 0.55rem 0.75rem 0.65rem;
    margin-top: auto;
}

.sk-action {
    height: 28px;
    width: 52px;
    border-radius: 4px;
}

/* ── Pagination loader ────────────────────────────────────── */
.posts-loading {
    display: flex;
    justify-content: center;
    gap: 0.3rem;
    padding: 1rem 0;
}

.posts-loading__dot {
    width: 6px;
    height: 6px;
    background: var(--color-base-1);
    border-radius: 50%;
    animation: bounce 1.1s infinite ease-in-out both;
}

.posts-loading__dot:nth-child(2) {
    animation-delay: 0.16s;
}
.posts-loading__dot:nth-child(3) {
    animation-delay: 0.32s;
}

@keyframes bounce {
    0%,
    80%,
    100% {
        transform: scale(0);
    }
    40% {
        transform: scale(1);
    }
}

.posts-sentinel {
    height: 1px;
}

/* Delete confirmation */
.confirm-delete {
    font-family: "Courier New", Courier, monospace;
    display: flex;
    flex-direction: column;
    gap: 0;
    padding: 1.5rem 1.5rem 1.25rem;
}

.confirm-delete__rule {
    width: 100%;
    height: 0;
    border: none;
    border-top: 2px double rgba(100, 210, 255, 0.3);
    margin: 0.5rem 0;
}
.confirm-delete__rule--red {
    border-color: rgba(220, 80, 80, 0.45);
}

.confirm-delete__title {
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    color: rgba(255, 120, 120, 0.9);
    text-align: center;
    margin: 0.3rem 0;
}

.confirm-delete__perf {
    position: relative;
    display: flex;
    align-items: center;
    margin: 0.85rem 0 0.25rem;
}
.confirm-delete__perf::before,
.confirm-delete__perf::after {
    content: "◆";
    font-size: 0.45rem;
    color: rgba(220, 80, 80, 0.4);
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
}
.confirm-delete__perf::before {
    left: -5px;
}
.confirm-delete__perf::after {
    right: -5px;
}
.confirm-delete__perf-line {
    display: block;
    width: 100%;
    border-top: 1px dashed rgba(220, 80, 80, 0.25);
}

.confirm-delete__footer {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.confirm-delete__cancel {
    flex: 1;
    padding: 0.5rem 1rem;
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    color: rgba(210, 240, 255, 0.45);
    font-size: 0.78rem;
    font-family: "Courier New", Courier, monospace;
    letter-spacing: 0.08em;
    cursor: pointer;
    transition:
        border-color 0.15s,
        color 0.15s;
}
.confirm-delete__cancel:hover {
    border-color: rgba(255, 255, 255, 0.22);
    color: rgba(210, 240, 255, 0.8);
}

.confirm-delete__confirm {
    flex: 1;
    padding: 0.5rem 1rem;
    background: rgba(180, 30, 60, 0.2);
    border: 1px solid rgba(210, 50, 80, 0.45);
    border-radius: 3px;
    color: rgba(255, 120, 130, 0.9);
    font-size: 0.78rem;
    font-family: "Courier New", Courier, monospace;
    letter-spacing: 0.08em;
    cursor: pointer;
    transition:
        background 0.15s,
        border-color 0.15s;
}
.confirm-delete__confirm:hover {
    background: rgba(210, 40, 75, 0.35);
    border-color: rgba(230, 70, 100, 0.65);
}

/* Create form */
.create-form {
    padding: 0.5rem 0.25rem;
    display: flex;
    flex-direction: column;
}

.create-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
    margin: 0 0 1rem;
}

.photo-preview-wrap {
    position: relative;
    margin-bottom: 0.75rem;
}

.photo-preview {
    max-width: 100%;
    max-height: 160px;
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: 3px;
    display: block;
    margin: 0 auto;
}

.remove-photo-btn {
    position: absolute;
    top: 0.4rem;
    right: 0.4rem;
    background: rgba(0, 0, 0, 0.6);
    border: none;
    color: #fff;
    font-size: 0.8rem;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.post-textarea {
    width: 100%;
    box-sizing: border-box;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    padding: 0.75rem;
    color: rgba(255, 255, 255, 0.85);
    font-size: 0.92rem;
    font-family: inherit;
    resize: vertical;
    outline: none;
    transition: border-color 0.2s;
}

.post-textarea:focus {
    border-color: var(--color-base-1);
}

.char-count {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.25);
    text-align: right;
    margin: 0.25rem 0 1rem;
}

.char-count--warn {
    color: var(--color-base-1);
    opacity: 0.85;
}

.photo-error {
    font-size: 0.78rem;
    color: var(--color-base-1);
    opacity: 0.85;
    margin: -0.5rem 0 0.75rem;
}

.hidden-input {
    display: none;
}

.photo-label {
    display: block;
    margin-bottom: 1rem;
    cursor: pointer;
}

.photo-btn {
    font-size: 0.85rem;
    color: var(--color-base-1);
    border: 1px dashed var(--color-base-1);
    opacity: 0.7;
    border-radius: 3px;
    padding: 0.4rem 0.85rem;
    transition: all 0.2s;
    display: inline-block;
}

.photo-label:hover .photo-btn {
    color: var(--color-base-1);
    border-color: var(--color-base-1);
    opacity: 1;
}

.save-btn {
    width: 100%;
    padding: 0.8rem;
    border-radius: 3px;
    border: 1px solid rgba(255, 178, 239, 0.35);
    background: linear-gradient(
        135deg,
        rgba(255, 178, 239, 0.25),
        rgba(255, 178, 239, 0.1)
    );
    color: #fff;
    font-size: 0.95rem;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
}

.save-btn:hover:not(:disabled) {
    background: linear-gradient(
        135deg,
        rgba(255, 178, 239, 0.38),
        rgba(255, 178, 239, 0.18)
    );
}

.save-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
@keyframes save-spin {
    to {
        transform: rotate(360deg);
    }
}
.save-btn__spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid currentColor;
    border-top-color: transparent;
    border-radius: 50%;
    animation: save-spin 0.6s linear infinite;
    opacity: 0.75;
    vertical-align: middle;
}

@media (max-width: 600px) {
    .posts-feed,
    .posts-skeleton-grid {
        column-count: 1;
        column-gap: 0;
    }
}
</style>
