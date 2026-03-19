<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import SiteModal from '@/Components/Site/SiteModal.vue';
import GuestBanner from '@/Components/Profile/GuestBanner.vue';
import AuthModal from '@/Components/Site/AuthModal.vue';

const props = defineProps({
    post:     { default: null },
    isOwner:  { type: Boolean, default: false },
    authUser: { default: null },
});

const emit = defineEmits(['close', 'liked', 'delete', 'comment-added']);

// ── Comments ───────────────────────────────────────────────
const comments   = ref([]);
const loadingCmt = ref(false);

async function loadComments() {
    if (!props.post) return;
    loadingCmt.value = true;
    try {
        const { data } = await axios.get(route('posts.comments.index', props.post.id));
        comments.value = data;
    } catch (e) {
        console.error(e);
    } finally {
        loadingCmt.value = false;
    }
}

watch(() => props.post, (val) => {
    if (val) {
        comments.value = [];
        loadComments();
    }
});

// ── Like ───────────────────────────────────────────────────
const showAuthModal = ref(false);

async function toggleLike() {
    if (!props.authUser) {
        showAuthModal.value = true;
        return;
    }
    if (!props.post) return;
    try {
        const { data } = await axios.post(route('posts.like', props.post.id));
        emit('liked', { postId: props.post.id, liked: data.liked, likesCount: data.likes_count });
    } catch (e) {
        console.error(e);
    }
}

// ── New comment ────────────────────────────────────────────
const newBody     = ref('');
const replyToId   = ref(null);
const replyToName = ref('');
const submitting  = ref(false);
const cmtError    = ref('');

function startReply(comment) {
    replyToId.value   = comment.id;
    replyToName.value = comment.user.name;
    newBody.value     = '';
    cmtError.value    = '';
}

function cancelReply() {
    replyToId.value   = null;
    replyToName.value = '';
}

async function submitComment() {
    if (!newBody.value.trim() || submitting.value) return;
    submitting.value = true;
    cmtError.value   = '';
    try {
        const { data } = await axios.post(route('posts.comments.store', props.post.id), {
            body:      newBody.value.trim(),
            parent_id: replyToId.value ?? undefined,
        });

        if (replyToId.value) {
            const parent = comments.value.find(c => c.id === replyToId.value);
            if (parent) parent.replies.push(data);
        } else {
            comments.value.push(data);
        }

        emit('comment-added', props.post.id);
        newBody.value     = '';
        replyToId.value   = null;
        replyToName.value = '';
    } catch (e) {
        cmtError.value = e.response?.data?.message ?? 'Ошибка';
    } finally {
        submitting.value = false;
    }
}

// ── Comment 3-dot menu ─────────────────────────────────────
const openCmtMenuId = ref(null);

function toggleCmtMenu(id, e) {
    e.stopPropagation();
    openCmtMenuId.value = openCmtMenuId.value === id ? null : id;
}

function closeCmtMenus() {
    openCmtMenuId.value = null;
}

onMounted(() => document.addEventListener('click', closeCmtMenus));
onUnmounted(() => document.removeEventListener('click', closeCmtMenus));

// ── Delete comment ─────────────────────────────────────────
async function deleteComment(commentId, parentId) {
    try {
        await axios.delete(route('posts.comments.destroy', commentId));
        if (parentId) {
            const parent = comments.value.find(c => c.id === parentId);
            if (parent) parent.replies = parent.replies.filter(r => r.id !== commentId);
        } else {
            comments.value = comments.value.filter(c => c.id !== commentId);
        }
    } catch (e) {
        console.error(e);
    }
}
</script>

<template>
    <SiteModal :show="!!post" variant="pink" :compact="false" @close="emit('close')">
        <div v-if="post" class="detail">

            <!-- ── Left: photo + body + footer — no scroll ── -->
            <div class="detail__left">

                <img v-if="post.photo_url" :src="post.photo_url" class="detail__photo" />

                <div class="detail__body-wrap">
                    <p class="detail__body">{{ post.body }}</p>
                </div>

                <div class="detail__left-footer">
                    <span class="detail__date">{{ post.created_at }}</span>
                    <div class="detail__actions-row">
                        <button
                            class="detail__like-btn"
                            :class="{ 'detail__like-btn--active': post.liked_by_me }"
                            :disabled="!authUser"
                            @click="toggleLike"
                        >
                            <svg width="13" height="13" viewBox="0 0 24 24"
                                :fill="post.liked_by_me ? 'currentColor' : 'none'"
                                stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                            {{ post.likes_count }}
                        </button>
                        <button v-if="isOwner" class="detail__del-btn" @click="emit('delete', post.id)">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                            </svg>
                            Удалить
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Right: header (fixed) + comments (scroll) + input (fixed) ── -->
            <div class="detail__right">

                <!-- Fixed header -->
                <div class="detail__cmts-header">
                    <span class="detail__cmts-label">Комментарии</span>
                    <span class="detail__cmts-count">{{ comments.length }}</span>
                </div>

                <!-- Scrollable comments -->
                <div class="detail__cmts-list">
                    <div v-if="loadingCmt" class="detail__cmts-state">
                        <span class="detail__cmts-dot" /><span class="detail__cmts-dot" /><span class="detail__cmts-dot" />
                    </div>
                    <div v-else-if="comments.length === 0" class="detail__cmts-state detail__cmts-state--empty">
                        Комментариев пока нет
                    </div>
                    <template v-else>
                        <div v-for="cmt in comments" :key="cmt.id" class="detail__cmt">
                            <div class="detail__cmt-row">
                                <a :href="route('profile.show', cmt.user.id) + '#about'" class="detail__cmt-avatar-link">
                                    <img v-if="cmt.user.avatar_url" :src="cmt.user.avatar_url" class="detail__cmt-avatar" />
                                    <div v-else class="detail__cmt-avatar detail__cmt-avatar--fb">{{ cmt.user.name[0] }}</div>
                                </a>
                                <div class="detail__cmt-content">
                                    <div class="detail__cmt-meta">
                                        <a :href="route('profile.show', cmt.user.id) + '#about'" class="detail__cmt-name">{{ cmt.user.name }}</a>
                                        <div v-if="authUser && cmt.user.id === authUser.id" class="detail__cmt-menu-wrap">
                                            <button class="detail__cmt-dots" @click="toggleCmtMenu(cmt.id, $event)">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                    <circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>
                                                </svg>
                                            </button>
                                            <div v-if="openCmtMenuId === cmt.id" class="detail__cmt-dropdown" @click.stop>
                                                <button class="detail__cmt-dropdown-item detail__cmt-dropdown-item--danger" @click="deleteComment(cmt.id, null); closeCmtMenus()">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                                                    </svg>
                                                    Удалить
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="detail__cmt-body">{{ cmt.body }}</p>
                                    <div class="detail__cmt-acts">
                                        <span class="detail__cmt-time">{{ cmt.created_at }}</span>
                                        <button v-if="authUser" class="detail__cmt-btn detail__cmt-btn--reply" @click="startReply(cmt)">↩ ответить</button>
                                    </div>
                                </div>
                            </div>
                            <!-- Replies -->
                            <div v-if="cmt.replies?.length" class="detail__replies">
                                <div v-for="reply in cmt.replies" :key="reply.id" class="detail__cmt detail__cmt--reply">
                                    <div class="detail__cmt-row">
                                        <a :href="route('profile.show', reply.user.id) + '#about'" class="detail__cmt-avatar-link">
                                            <img v-if="reply.user.avatar_url" :src="reply.user.avatar_url" class="detail__cmt-avatar detail__cmt-avatar--sm" />
                                            <div v-else class="detail__cmt-avatar detail__cmt-avatar--fb detail__cmt-avatar--sm">{{ reply.user.name[0] }}</div>
                                        </a>
                                        <div class="detail__cmt-content">
                                            <div class="detail__cmt-meta">
                                                <a :href="route('profile.show', reply.user.id) + '#about'" class="detail__cmt-name">{{ reply.user.name }}</a>
                                                <div v-if="authUser && reply.user.id === authUser.id" class="detail__cmt-menu-wrap">
                                                    <button class="detail__cmt-dots" @click="toggleCmtMenu(reply.id, $event)">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                            <circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>
                                                        </svg>
                                                    </button>
                                                    <div v-if="openCmtMenuId === reply.id" class="detail__cmt-dropdown" @click.stop>
                                                        <button class="detail__cmt-dropdown-item detail__cmt-dropdown-item--danger" @click="deleteComment(reply.id, cmt.id); closeCmtMenus()">
                                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                                                            </svg>
                                                            Удалить
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="detail__cmt-body">{{ reply.body }}</p>
                                            <div class="detail__cmt-acts">
                                                <span class="detail__cmt-time">{{ reply.created_at }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Fixed input area -->
                <div class="detail__input-area">
                    <template v-if="authUser">
                        <div v-if="replyToId" class="detail__reply-hint">
                            <span>↩ {{ replyToName }}</span>
                            <button class="detail__reply-cancel" @click="cancelReply">✕</button>
                        </div>
                        <div class="detail__input-row">
                            <textarea
                                v-model="newBody"
                                class="detail__textarea"
                                :placeholder="replyToId ? 'Ваш ответ...' : 'Написать комментарий...'"
                                rows="3"
                                maxlength="177"
                                @keydown.enter.exact.prevent="submitComment"
                            />
                            <span class="detail__char" :class="{ 'detail__char--warn': newBody.length > 150 }">{{ newBody.length }}/177</span>
                            <button class="detail__send-btn" :disabled="!newBody.trim() || submitting" @click="submitComment">
                                Enter
                            </button>
                        </div>
                        <div v-if="cmtError" class="detail__err">{{ cmtError }}</div>
                    </template>
                    <GuestBanner v-else />
                </div>

            </div>
        </div>
    </SiteModal>

    <AuthModal :show="showAuthModal" initial-tab="register" @close="showAuthModal = false" />
</template>

<style scoped>
/* ── Hijack modal body: no padding, no outer scroll ──────── */
:deep(.site-modal-body) {
    padding: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* ── Root grid ───────────────────────────────────────────── */
.detail {
    display: grid;
    grid-template-columns: 1fr 1fr;
    height: 100%;
    overflow: hidden;
}

/* ═══════════════════════════════════════════════════════════
   LEFT COLUMN — fixed, no scroll
═══════════════════════════════════════════════════════════ */
.detail__left {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-right: 1px solid rgba(255, 255, 255, 0.06);
}

.detail__photo {
    width: 100%;
    flex-shrink: 0;
    display: block;
    object-fit: cover;
    max-height: 55%;
}

/* Body text — scrollable zone between photo and footer */
.detail__body-wrap {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 0.9rem 1rem 0.75rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(190, 145, 255, 0.18) transparent;
}
.detail__body-wrap::-webkit-scrollbar { width: 3px; }
.detail__body-wrap::-webkit-scrollbar-thumb { background: rgba(190, 145, 255, 0.18); border-radius: 3px; }

.detail__body {
    font-size: 1.05rem;
    line-height: 1.72;
    color: rgba(255, 255, 255, 0.78);
    margin: 0;
    white-space: pre-wrap;
    word-break: break-word;
}

/* Footer — always pinned at the bottom of the left column */
.detail__left-footer {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.6rem 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.055);
    background: rgba(255, 255, 255, 0.012);
}

.detail__date {
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.6);
    letter-spacing: 0.03em;
}

.detail__actions-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.detail__like-btn {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 5px;
    color: rgba(255, 255, 255, 0.65);
    font-size: 0.9rem;
    font-family: inherit;
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s;
}
.detail__like-btn:hover:not(:disabled) {
    color: rgba(224, 24, 108, 0.9);
    border-color: rgba(224, 24, 108, 0.4);
}
.detail__like-btn--active {
    color: rgba(224, 24, 108, 1);
    border-color: rgba(224, 24, 108, 0.45);
}
.detail__like-btn:disabled { opacity: 0.45; cursor: default; }

.detail__del-btn {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.35rem 0.75rem;
    background: transparent;
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 5px;
    color: rgba(239, 68, 68, 0.7);
    font-size: 0.9rem;
    font-family: inherit;
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
}
.detail__del-btn:hover {
    color: rgba(239, 68, 68, 1);
    border-color: rgba(239, 68, 68, 0.55);
    background: rgba(239, 68, 68, 0.07);
}

/* ═══════════════════════════════════════════════════════════
   RIGHT COLUMN — header + [scroll] + input, all fixed
═══════════════════════════════════════════════════════════ */
.detail__right {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Fixed header */
.detail__cmts-header {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.65rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.055);
}

.detail__cmts-label {
    font-size: 0.8rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.7);
}

.detail__cmts-count {
    font-size: 0.8rem;
    color: rgba(190, 145, 255, 0.75);
}

/* Scrollable comments list */
.detail__cmts-list {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 0.6rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(190, 145, 255, 0.18) transparent;
}
.detail__cmts-list::-webkit-scrollbar { width: 3px; }
.detail__cmts-list::-webkit-scrollbar-thumb { background: rgba(190, 145, 255, 0.18); border-radius: 3px; }

/* Loading dots */
.detail__cmts-state {
    display: flex;
    justify-content: center;
    gap: 0.3rem;
    padding: 1.5rem 0;
}
.detail__cmts-state--empty {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.18);
    text-align: center;
}
.detail__cmts-dot {
    width: 5px; height: 5px;
    background: rgba(190, 145, 255, 0.35);
    border-radius: 50%;
    animation: dot-pulse 1.2s infinite ease-in-out both;
}
.detail__cmts-dot:nth-child(2) { animation-delay: 0.16s; }
.detail__cmts-dot:nth-child(3) { animation-delay: 0.32s; }
@keyframes dot-pulse {
    0%, 80%, 100% { transform: scale(0.5); opacity: 0.4; }
    40%            { transform: scale(1);   opacity: 1; }
}

/* Comment items */
.detail__cmts-list > div + div {
    border-top: 1px solid rgba(255, 255, 255, 0.045);
    padding-top: 1.4rem;
}

.detail__cmt-row {
    display: flex;
    gap: 0.5rem;
    align-items: flex-start;
}
.detail__cmt-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
}
.detail__cmt-avatar--fb {
    background: rgba(190, 145, 255, 0.14);
    color: rgba(190, 145, 255, 0.75);
    font-size: 0.68rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    text-transform: uppercase;
}
.detail__cmt-avatar--sm {
    width: 20px;
    height: 20px;
}
.detail__cmt-content { flex: 1; min-width: 0; }
.detail__cmt-meta {
    display: flex;
    align-items: center;
    gap: 0.38rem;
    margin-bottom: 0.18rem;
}
.detail__cmt-avatar-link {
    flex-shrink: 0;
    display: block;
    transition: opacity 0.15s;
}
.detail__cmt-avatar-link:hover {
    opacity: 0.75;
}
.detail__cmt-name {
    font-size: 0.82rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.88);
    text-decoration: none;
    transition: color 0.15s;
}
.detail__cmt-name:hover {
    color: rgba(255, 255, 255, 1);
    text-decoration: underline;
}
.detail__cmt-time {
    font-size: 0.68rem;
    color: rgba(255, 255, 255, 0.35);
}
.detail__cmt-body {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.75);
    margin: 0;
    line-height: 1.55;
    word-break: break-word;
}
.detail__cmt-acts {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 0.35rem;
}
.detail__cmt-btn {
    background: transparent;
    border: none;
    padding: 0;
    font-size: 0.65rem;
    font-family: inherit;
    cursor: pointer;
    transition: color 0.12s;
}
.detail__cmt-btn--reply {
    font-size: 0.8rem;
    font-weight: 600;
    color: rgba(190, 145, 255, 0.75);
    border: 1px solid rgba(190, 145, 255, 0.3);
    border-radius: 4px;
    padding: 0.18rem 0.55rem;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
}
.detail__cmt-btn--reply:hover {
    color: rgba(190, 145, 255, 1);
    border-color: rgba(190, 145, 255, 0.6);
    background: rgba(190, 145, 255, 0.08);
}

/* 3-dot menu for comments */
.detail__cmt-menu-wrap {
    position: relative;
    margin-left: auto;
}
.detail__cmt-dots {
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    padding: 0.1rem 0.25rem;
    border-radius: 4px;
    color: rgba(255, 255, 255, 0.25);
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
}
.detail__cmt-dots:hover {
    color: rgba(255, 255, 255, 0.65);
    background: rgba(255, 255, 255, 0.06);
}
.detail__cmt-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    right: 0;
    z-index: 100;
    width: 120px;
    background: rgb(18, 14, 26);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.5);
    overflow: hidden;
    animation: cmt-menu-in 0.1s ease;
}
@keyframes cmt-menu-in {
    from { opacity: 0; transform: translateY(-3px); }
    to   { opacity: 1; transform: translateY(0); }
}
.detail__cmt-dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    width: 100%;
    padding: 0.5rem 0.75rem;
    background: transparent;
    border: none;
    font-family: inherit;
    font-size: 0.82rem;
    cursor: pointer;
    text-align: left;
    transition: background 0.12s;
}
.detail__cmt-dropdown-item--danger {
    color: rgba(239, 68, 68, 0.75);
}
.detail__cmt-dropdown-item--danger:hover {
    background: rgba(239, 68, 68, 0.08);
    color: rgba(239, 68, 68, 1);
}

.detail__replies {
    margin-left: 1.6rem;
    margin-top: 0.45rem;
    padding-left: 0.65rem;
    border-left: 1px solid rgba(255, 255, 255, 0.055);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* Fixed input area */
.detail__input-area {
    flex-shrink: 0;
    border-top: 1px solid rgba(255, 255, 255, 0.055);
    padding: 0.65rem 1rem;
    background: rgba(255, 255, 255, 0.012);
}
.detail__reply-hint {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.68rem;
    color: rgba(190, 145, 255, 0.6);
    margin-bottom: 0.35rem;
}
.detail__reply-cancel {
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.28);
    cursor: pointer;
    font-size: 0.65rem;
    padding: 0;
    line-height: 1;
    transition: color 0.12s;
}
.detail__reply-cancel:hover { color: rgba(255, 255, 255, 0.6); }
.detail__input-row {
    position: relative;
}
.detail__textarea {
    width: 100%;
    box-sizing: border-box;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 6px;
    padding: 0.65rem 5.5rem 1.8rem 0.75rem;
    color: rgba(255, 255, 255, 0.95);
    font-family: inherit;
    font-size: 0.95rem;
    resize: none;
    outline: none;
    line-height: 1.55;
    transition: border-color 0.15s, background 0.15s;
}
.detail__textarea:focus {
    border-color: rgba(190, 145, 255, 0.4);
    background: rgba(255, 255, 255, 0.06);
}
.detail__textarea::placeholder { color: rgba(255, 255, 255, 0.4); }
.detail__send-btn {
    position: absolute;
    right: 0.5rem;
    bottom: 0.5rem;
    padding: 0.28rem 0.6rem;
    background: rgba(190, 145, 255, 0.18);
    border: 1px solid rgba(190, 145, 255, 0.45);
    border-bottom: 2px solid rgba(190, 145, 255, 0.6);
    border-radius: 5px;
    color: rgba(190, 145, 255, 0.9);
    font-family: inherit;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    cursor: pointer;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
    white-space: nowrap;
}
.detail__send-btn:hover:not(:disabled) {
    background: rgba(190, 145, 255, 0.3);
    border-color: rgba(190, 145, 255, 0.75);
    border-bottom-color: rgba(190, 145, 255, 0.9);
    color: rgba(190, 145, 255, 1);
}
.detail__send-btn:disabled { opacity: 0.3; cursor: default; }
.detail__char {
    position: absolute;
    bottom: 0.5rem;
    left: 0.75rem;
    font-size: 0.68rem;
    color: rgba(255, 255, 255, 0.3);
    pointer-events: none;
}
.detail__char--warn { color: rgba(190, 145, 255, 0.8); }
.detail__err {
    font-size: 0.65rem;
    color: rgba(239, 68, 68, 0.7);
    margin-top: 0.2rem;
}

/* ── Mobile ───────────────────────────────────────────────── */
@media (max-width: 640px) {
    .detail {
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr;
    }
    .detail__left {
        border-right: none;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        max-height: 55vh;
        overflow-y: auto;
    }
    .detail__right {
        min-height: 0;
    }
}
</style>
