<script setup>
import { ref, reactive, watch, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import { useTranslations } from '@/composables/useTranslations';
import { RefreshLeft, Promotion, ChatLineSquare } from '@element-plus/icons-vue';

const { __ } = useTranslations();
import SiteModal from '@/Components/Site/SiteModal.vue';
import GuestBanner from '@/Components/Profile/GuestBanner.vue';
import AuthModal from '@/Components/Site/AuthModal.vue';
import AppAvatar from '@/Components/Common/AppAvatar.vue';

const props = defineProps({
    post:     { default: null },
    isOwner:  { type: Boolean, default: false },
    authUser: { default: null },
});

const emit = defineEmits(['close', 'liked', 'delete', 'comment-added']);

// ── Comments ───────────────────────────────────────────────
const comments    = ref([]);
const loadingCmt  = ref(false);
const cmtsList    = ref(null);
const scrollZone  = ref(null);

// ── Replies lazy-load ──────────────────────────────────────
const expandedReplies      = reactive(new Set());
const commentReplies       = reactive({});
const commentRepliesPage   = reactive({});
const commentRepliesMore   = reactive({});
const commentRepliesLoading = reactive(new Set());

function replyWord(n) {
    const mod10  = n % 10;
    const mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11)                               return 'ответ';
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return 'ответа';
    return 'ответов';
}

async function loadReplies(commentId, page = 1) {
    if (commentRepliesLoading.has(commentId)) return;
    commentRepliesLoading.add(commentId);
    expandedReplies.add(commentId); // показать блок сразу, чтобы скелетон был виден
    try {
        const { data } = await axios.get(route('comments.replies', commentId), { params: { page } });
        if (page === 1) {
            commentReplies[commentId] = data.data;
        } else {
            commentReplies[commentId] = [...(commentReplies[commentId] ?? []), ...data.data];
        }
        commentRepliesPage[commentId] = page;
        commentRepliesMore[commentId] = data.has_more;
    } finally {
        commentRepliesLoading.delete(commentId);
    }
}

function toggleReplies(commentId) {
    if (expandedReplies.has(commentId)) {
        expandedReplies.delete(commentId);
    } else if (commentReplies[commentId]) {
        expandedReplies.add(commentId);
    } else {
        loadReplies(commentId, 1);
    }
}

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

watch(() => props.post?.id, (val) => {
    if (val) {
        comments.value    = [];
        photoLoaded.value = false;
        photoError.value  = false;
        loadComments();
    }
});

// ── Photo loading state ────────────────────────────────────
const photoLoaded = ref(false);
const photoError  = ref(false);

// ── Like ───────────────────────────────────────────────────
const showAuthModal  = ref(false);
const likeAnimating  = ref(false);

async function toggleLike() {
    if (!props.authUser) {
        showAuthModal.value = true;
        return;
    }
    if (!props.post) return;
    likeAnimating.value = true;
    setTimeout(() => { likeAnimating.value = false; }, 400);
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
            if (parent) {
                parent.replies_count++;
                if (commentReplies[parent.id]) {
                    commentReplies[parent.id].push(data);
                    expandedReplies.add(parent.id);
                }
            }
        } else {
            comments.value.push(data);
        }

        emit('comment-added', props.post.id);
        newBody.value     = '';
        replyToId.value   = null;
        replyToName.value = '';

        await nextTick();
        // Desktop: cmtsList scrolls; mobile: scrollZone scrolls
        for (const el of [cmtsList.value, scrollZone.value]) {
            if (el && el.scrollHeight > el.clientHeight) {
                el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
            }
        }
    } catch (e) {
        cmtError.value = e.response?.data?.message ?? __('post.detail.error');
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

// ── Fullscreen photo ───────────────────────────────────────
const fullscreen = ref(false);

function openFullscreen() { fullscreen.value = true; }
function closeFullscreen() { fullscreen.value = false; }

// ── Delete comment ─────────────────────────────────────────
async function deleteComment(commentId, parentId) {
    try {
        await axios.delete(route('posts.comments.destroy', commentId));
        if (parentId) {
            const parent = comments.value.find(c => c.id === parentId);
            if (parent) {
                parent.replies_count = Math.max(0, parent.replies_count - 1);
                parent.replies = parent.replies.filter(r => r.id !== commentId);
                if (commentReplies[parentId]) {
                    commentReplies[parentId] = commentReplies[parentId].filter(r => r.id !== commentId);
                }
            }
        } else {
            comments.value = comments.value.filter(c => c.id !== commentId);
        }
    } catch (e) {
        console.error(e);
    }
}
</script>

<template>
    <SiteModal :show="!!post" variant="pink" :compact="false" no-padding @close="emit('close')">
        <div v-if="post" class="detail">

            <!-- Scrollable zone: left + right columns (transparent to desktop grid) -->
            <div ref="scrollZone" class="detail__scroll">

                <!-- ── Left: photo + body + footer ── -->
                <div class="detail__left">

                    <div v-if="post.photo_url" class="detail__photo-wrap">
                        <div v-if="!photoLoaded && !photoError" class="detail__photo-skel" />
                        <div v-if="photoError" class="detail__photo-error">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                <polyline points="21 15 16 10 5 21"/>
                            </svg>
                        </div>
                        <img
                            :src="post.photo_url"
                            class="detail__photo"
                            :class="{ 'detail__photo--loaded': photoLoaded }"
                            @load="photoLoaded = true"
                            @error="photoError = true"
                            @click="openFullscreen"
                        />
                        <button class="detail__photo-expand" @click="openFullscreen" :title="__('post.detail.fullscreen')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/>
                                <line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/>
                            </svg>
                        </button>
                    </div>

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
                                <svg width="18" height="18" viewBox="0 0 24 24"
                                    :fill="post.liked_by_me ? 'currentColor' : 'none'"
                                    stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    :class="{ 'like-pop': likeAnimating }">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                </svg>
                                {{ post.likes_count }}
                            </button>
                            <button v-if="isOwner" class="detail__del-btn" @click="emit('delete', post.id)">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                                </svg>
                                {{ __('common.delete') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── Right: header + comments list ── -->
                <div class="detail__right">

                    <div class="detail__cmts-header">
                        <span class="detail__cmts-label">
                            <el-icon class="detail__cmts-icon"><ChatLineSquare /></el-icon>
                            {{ __('post.detail.comments') }}
                        </span>
                        <span class="detail__cmts-count">{{ comments.length }}</span>
                    </div>

                    <div ref="cmtsList" class="detail__cmts-list">
                        <div v-if="loadingCmt" class="detail__cmts-skel-list">
                            <div v-for="i in 4" :key="i" class="detail__cmts-skel" :style="{ animationDelay: (i - 1) * 0.07 + 's' }">
                                <div class="detail__cmts-skel__avatar" />
                                <div class="detail__cmts-skel__lines">
                                    <div class="detail__cmts-skel__line detail__cmts-skel__line--name" />
                                    <div class="detail__cmts-skel__line detail__cmts-skel__line--body" />
                                    <div class="detail__cmts-skel__line detail__cmts-skel__line--body2" />
                                </div>
                            </div>
                        </div>
                        <div v-else-if="comments.length === 0" class="detail__cmts-state detail__cmts-state--empty">
                            {{ __('post.detail.empty') }}
                        </div>
                        <template v-else>
                            <div v-for="cmt in comments" :key="cmt.id" class="detail__cmt">
                                <div class="detail__cmt-row">
                                    <a :href="route('profile.show', cmt.user.id) + '#about'" class="detail__cmt-avatar-link">
                                        <AppAvatar :src="cmt.user.avatar_url" :name="cmt.user.name" size="md" />
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
                                                        {{ __('common.delete') }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="detail__cmt-body">{{ cmt.body }}</p>
                                        <div class="detail__cmt-acts">
                                            <span class="detail__cmt-time">{{ cmt.created_at }}</span>
                                            <button v-if="authUser" class="detail__cmt-btn detail__cmt-btn--reply" @click="startReply(cmt)">
                                                <el-icon style="vertical-align: middle;"><RefreshLeft /></el-icon>
                                                {{ __('post.detail.reply') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <!-- Single reply (replies_count === 1): shown inline, no toggle -->
                                <div v-if="cmt.replies_count === 1 && cmt.replies?.length" class="detail__replies">
                                    <div class="detail__cmt detail__cmt--reply">
                                        <div class="detail__cmt-row">
                                            <a :href="route('profile.show', cmt.replies[0].user.id) + '#about'" class="detail__cmt-avatar-link">
                                                <AppAvatar :src="cmt.replies[0].user.avatar_url" :name="cmt.replies[0].user.name" size="sm" />
                                            </a>
                                            <div class="detail__cmt-content">
                                                <div class="detail__cmt-meta">
                                                    <a :href="route('profile.show', cmt.replies[0].user.id) + '#about'" class="detail__cmt-name">{{ cmt.replies[0].user.name }}</a>
                                                    <div v-if="authUser && cmt.replies[0].user.id === authUser.id" class="detail__cmt-menu-wrap">
                                                        <button class="detail__cmt-dots" @click="toggleCmtMenu(cmt.replies[0].id, $event)">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                                <circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>
                                                            </svg>
                                                        </button>
                                                        <div v-if="openCmtMenuId === cmt.replies[0].id" class="detail__cmt-dropdown" @click.stop>
                                                            <button class="detail__cmt-dropdown-item detail__cmt-dropdown-item--danger" @click="deleteComment(cmt.replies[0].id, cmt.id); closeCmtMenus()">
                                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                                                                </svg>
                                                                {{ __('common.delete') }}
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <p class="detail__cmt-body">{{ cmt.replies[0].body }}</p>
                                                <div class="detail__cmt-acts">
                                                    <span class="detail__cmt-time">{{ cmt.replies[0].created_at }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Toggle button: appears when replies > 1 -->
                                <div v-if="cmt.replies_count > 1" class="detail__replies-toggle-wrap">
                                    <button
                                        class="detail__replies-toggle"
                                        :class="{ 'detail__replies-toggle--open': expandedReplies.has(cmt.id) }"
                                        @click="toggleReplies(cmt.id)"
                                    >
                                        <span class="detail__replies-toggle-label">
                                            {{ cmt.replies_count }} {{ replyWord(cmt.replies_count) }}
                                        </span>
                                        <svg class="detail__replies-toggle-chevron" viewBox="0 0 12 12" fill="none">
                                            <path d="M2 4.5L6 8l4-3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                </div>

                                <!-- Expanded replies block -->
                                <Transition name="replies-expand">
                                <div
                                    v-if="cmt.replies_count > 1 && expandedReplies.has(cmt.id)"
                                    class="detail__replies"
                                >
                                    <!-- Skeleton while first page loading -->
                                    <template v-if="commentRepliesLoading.has(cmt.id) && !commentReplies[cmt.id]?.length">
                                        <div v-for="i in 3" :key="i" class="detail__reply-skel">
                                            <div class="detail__reply-skel__avatar" />
                                            <div class="detail__reply-skel__lines">
                                                <div class="detail__reply-skel__line detail__reply-skel__line--name" />
                                                <div class="detail__reply-skel__line detail__reply-skel__line--body" />
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Loaded replies -->
                                    <template v-else>
                                        <div
                                            v-for="reply in (commentReplies[cmt.id] ?? [])"
                                            :key="reply.id"
                                            class="detail__cmt detail__cmt--reply"
                                        >
                                            <div class="detail__cmt-row">
                                                <a :href="route('profile.show', reply.user.id) + '#about'" class="detail__cmt-avatar-link">
                                                    <AppAvatar :src="reply.user.avatar_url" :name="reply.user.name" size="sm" />
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
                                                                    {{ __('common.delete') }}
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

                                        <!-- Load more -->
                                        <button
                                            v-if="commentRepliesMore[cmt.id]"
                                            class="detail__replies-more"
                                            :disabled="commentRepliesLoading.has(cmt.id)"
                                            @click="loadReplies(cmt.id, commentRepliesPage[cmt.id] + 1)"
                                        >
                                            <span v-if="commentRepliesLoading.has(cmt.id)" class="detail__replies-more-spinner" />
                                            <template v-else>Загрузить ещё</template>
                                        </button>
                                    </template>
                                </div>
                                </Transition>
                            </div>
                        </template>
                    </div>

                </div>
            </div>

            <!-- ── Input: fixed footer of the modal ── -->
            <div class="detail__input-area">
                <template v-if="authUser">
                    <div v-if="replyToId" class="detail__reply-hint">
                        <span><el-icon style="vertical-align: middle; margin-right: 0.2em;"><RefreshLeft /></el-icon>{{ replyToName }}</span>
                        <button class="detail__reply-cancel" @click="cancelReply">✕</button>
                    </div>
                    <div class="detail__input-row">
                        <textarea
                            v-model="newBody"
                            class="detail__textarea"
                            :placeholder="replyToId ? __('post.detail.placeholder.reply') : __('post.detail.placeholder.comment')"
                            rows="3"
                            maxlength="177"
                            @keydown.enter.exact.prevent="submitComment"
                        />
                        <span class="detail__char" :class="{ 'detail__char--warn': newBody.length > 150 }">{{ newBody.length }}/177</span>
                        <button class="detail__send-btn" :disabled="!newBody.trim() || submitting" @click="submitComment">
                            <template v-if="submitting">
                                <span class="detail__send-spinner" />
                            </template>
                            <template v-else>
                                <el-icon class="detail__send-icon"><Promotion /></el-icon>
                                <span class="detail__send-label">Enter</span>
                            </template>
                        </button>
                    </div>
                    <div v-if="cmtError" class="detail__err">{{ cmtError }}</div>
                </template>
                <GuestBanner v-else />
            </div>

        </div>
    </SiteModal>

    <AuthModal :show="showAuthModal" initial-tab="register" @close="showAuthModal = false" />

    <!-- Fullscreen photo overlay -->
    <Teleport to="body">
        <div v-if="fullscreen && post?.photo_url" class="photo-fullscreen" @click="closeFullscreen">
            <img :src="post.photo_url" class="photo-fullscreen__img" @click.stop />
            <button class="photo-fullscreen__close" @click="closeFullscreen">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
    </Teleport>
</template>

<style scoped>
/* ── Modal body: no outer scroll, flex column ──────── */
:deep(.site-modal-body) {
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* ── Root grid ───────────────────────────────────────────── */
.detail {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: 1fr auto;
    grid-template-areas:
        "left right"
        "left input";
    height: 100%;
    overflow: hidden;
}
/* Scroll wrapper is transparent to the grid on desktop */
.detail__scroll { display: contents; }
.detail__left        { grid-area: left; }
.detail__right       { grid-area: right; }
.detail__input-area  { grid-area: input; }

/* ═══════════════════════════════════════════════════════════
   LEFT COLUMN — fixed, no scroll
═══════════════════════════════════════════════════════════ */
.detail__left {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-right: 1px solid rgba(255, 255, 255, 0.06);
}

.detail__photo-wrap {
    position: relative;
    flex-shrink: 0;
    aspect-ratio: 4 / 3;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.03);
}
.detail__photo-skel {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0.03) 25%,
        rgba(255, 255, 255, 0.09) 50%,
        rgba(255, 255, 255, 0.03) 75%
    );
    background-size: 200% 100%;
    animation: photo-shimmer 1.4s ease-in-out infinite;
}
@keyframes photo-shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
.detail__photo-error {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.12);
}
.detail__photo {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    opacity: 0;
    transition: opacity 0.35s ease;
    cursor: zoom-in;
}
.detail__photo--loaded { opacity: 1; }
.detail__photo-expand {
    position: absolute;
    bottom: 0.5rem;
    right: 0.5rem;
    background: rgba(0, 0, 0, 0.55);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 5px;
    color: rgba(255, 255, 255, 0.8);
    padding: 0.3rem 0.4rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: background 0.15s, color 0.15s;
}
.detail__photo-expand:hover {
    background: rgba(0, 0, 0, 0.8);
    color: #fff;
}

/* Body text — scrollable zone between photo and footer */
.detail__body-wrap {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 0.9rem 1rem 0.75rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(160, 160, 255, 0.18) transparent;
}
.detail__body-wrap::-webkit-scrollbar { width: 3px; }
.detail__body-wrap::-webkit-scrollbar-thumb { background: rgba(160, 160, 255, 0.18); border-radius: 3px; }

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
    padding: 0.3rem 0.65rem;
    background: transparent;
    border: none;
    border-radius: 5px;
    color: rgba(255, 255, 255, 0.55);
    font-size: 0.9rem;
    font-family: inherit;
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
}
@media (hover: hover) {
    .detail__like-btn:hover:not(:disabled) {
        color: rgba(224, 24, 108, 0.9);
        background: rgba(224, 24, 108, 0.07);
    }
}
.detail__like-btn--active {
    color: rgba(224, 24, 108, 1);
}
.detail__like-btn--active:hover {
    color: rgba(224, 24, 108, 1);
    background: rgba(224, 24, 108, 0.07);
}
.detail__like-btn:disabled { opacity: 0.45; cursor: default; }

@keyframes like-pop {
    0%   { transform: scale(1); }
    30%  { transform: scale(1.45); }
    60%  { transform: scale(0.88); }
    100% { transform: scale(1); }
}
.like-pop {
    animation: like-pop 0.38s cubic-bezier(0.36, 0.07, 0.19, 0.97);
}

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
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.7);
}

.detail__cmts-icon {
    font-size: 1rem;
    opacity: 0.7;
}

.detail__cmts-count {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.55);
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
    scrollbar-color: rgba(160, 160, 255, 0.18) transparent;
}
.detail__cmts-list::-webkit-scrollbar { width: 3px; }
.detail__cmts-list::-webkit-scrollbar-thumb { background: rgba(160, 160, 255, 0.18); border-radius: 3px; }

/* Empty state */
.detail__cmts-state--empty {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.18);
    text-align: center;
    padding: 1.5rem 0;
}

/* Comment skeleton */
.detail__cmts-skel-list {
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
}
.detail__cmts-skel {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    animation: reply-shimmer 1.4s ease-in-out infinite;
}
.detail__cmts-skel__avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    flex-shrink: 0;
    background: rgba(255, 255, 255, 0.06);
}
.detail__cmts-skel__lines {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding-top: 3px;
}
.detail__cmts-skel__line {
    height: 10px;
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.06);
}
.detail__cmts-skel__line--name  { width: 90px; }
.detail__cmts-skel__line--body  { width: 80%; animation-delay: 0.05s; }
.detail__cmts-skel__line--body2 { width: 55%; animation-delay: 0.1s; }

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
    color: rgba(160, 160, 255, 0.75);
    border: 1px solid rgba(160, 160, 255, 0.3);
    border-radius: 4px;
    padding: 0.18rem 0.55rem;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
}
.detail__cmt-btn--reply:hover {
    color: rgba(160, 160, 255, 1);
    border-color: rgba(160, 160, 255, 0.6);
    background: rgba(160, 160, 255, 0.08);
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

/* ── Replies toggle ──────────────────────────────────────── */
.detail__replies-toggle-wrap {
    margin-left: 1.6rem;
    margin-top: 0.3rem;
    display: flex;
    justify-content: center;
}
.detail__replies-toggle {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: none;
    border: none;
    padding: 4px 2px;
    cursor: pointer;
    color: rgba(160, 160, 255, 0.65);
    font-size: 0.82rem;
    font-weight: 500;
    font-family: inherit;
    letter-spacing: 0.02em;
    transition: color 0.15s;
}
@media (hover: hover) {
    .detail__replies-toggle:hover {
        color: rgba(180, 180, 255, 0.95);
    }
}
.detail__replies-toggle--open {
    color: rgba(180, 180, 255, 0.95);
}
.detail__replies-toggle-chevron {
    width: 13px;
    height: 13px;
    flex-shrink: 0;
    transition: transform 0.2s cubic-bezier(0.33, 1, 0.68, 1);
}
.detail__replies-toggle--open .detail__replies-toggle-chevron {
    transform: rotate(180deg);
}

/* ── Reply skeleton ──────────────────────────────────────── */
.detail__reply-skel {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
}
.detail__reply-skel__avatar {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    flex-shrink: 0;
    background: rgba(255, 255, 255, 0.06);
    animation: reply-shimmer 1.4s ease-in-out infinite;
}
.detail__reply-skel__lines {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 5px;
    padding-top: 2px;
}
.detail__reply-skel__line {
    height: 9px;
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.06);
    animation: reply-shimmer 1.4s ease-in-out infinite;
}
.detail__reply-skel__line--name { width: 72px; }
.detail__reply-skel__line--body { width: 140px; animation-delay: 0.1s; }
@keyframes reply-shimmer {
    0%, 100% { opacity: 0.55; }
    50%       { opacity: 1; }
}

/* ── Load more ───────────────────────────────────────────── */
.detail__replies-more {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    width: 100%;
    padding: 0.32rem;
    background: none;
    border: 1px dashed rgba(160, 160, 255, 0.18);
    border-radius: 3px;
    color: rgba(160, 160, 255, 0.55);
    font-size: 0.73rem;
    font-family: inherit;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s;
    margin-top: 0.2rem;
}
.detail__replies-more:hover:not(:disabled) {
    border-color: rgba(160, 160, 255, 0.4);
    color: rgba(180, 180, 255, 0.9);
}
.detail__replies-more:disabled { opacity: 0.5; cursor: default; }
.detail__replies-more-spinner {
    width: 11px;
    height: 11px;
    border: 1.5px solid rgba(160, 160, 255, 0.3);
    border-top-color: rgba(160, 160, 255, 0.9);
    border-radius: 50%;
    animation: reply-spin 0.7s linear infinite;
}
@keyframes reply-spin { to { transform: rotate(360deg); } }

/* ── Replies expand transition ───────────────────────────── */
.replies-expand-enter-active {
    transition: opacity 0.18s ease, transform 0.18s cubic-bezier(0.33, 1, 0.68, 1);
}
.replies-expand-leave-active {
    transition: opacity 0.12s ease, transform 0.12s ease;
}
.replies-expand-enter-from,
.replies-expand-leave-to {
    opacity: 0;
    transform: translateY(-5px);
}

/* Fixed input area */
.detail__input-area {
    flex-shrink: 0;
    border-top: 1px solid rgba(255, 255, 255, 0.055);
    padding: 0.65rem 1rem 1.1rem;
    background: rgba(255, 255, 255, 0.012);
}
.detail__reply-hint {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.68rem;
    color: rgba(160, 160, 255, 0.6);
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
    display: block;
    width: 100%;
    box-sizing: border-box;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 6px;
    padding: 0.65rem 5.5rem 0.65rem 0.75rem;
    color: rgba(255, 255, 255, 0.95);
    font-family: inherit;
    font-size: 0.95rem;
    resize: none;
    outline: none;
    line-height: 1.55;
    transition: border-color 0.15s, background 0.15s;
}
.detail__textarea:focus {
    border-color: rgba(160, 160, 255, 0.4);
    background: rgba(255, 255, 255, 0.06);
}
.detail__textarea::placeholder { color: rgba(255, 255, 255, 0.4); }
.detail__send-btn {
    position: absolute;
    right: 0.5rem;
    bottom: 0.5rem;
    padding: 0.28rem 0.6rem;
    background: rgba(160, 160, 255, 0.18);
    border: 1px solid rgba(160, 160, 255, 0.45);
    border-bottom: 2px solid rgba(160, 160, 255, 0.6);
    border-radius: 5px;
    color: rgba(160, 160, 255, 0.9);
    font-family: inherit;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    cursor: pointer;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.detail__send-icon {
    font-size: 1rem;
}
.detail__send-btn:hover:not(:disabled) {
    background: rgba(160, 160, 255, 0.3);
    border-color: rgba(160, 160, 255, 0.75);
    border-bottom-color: rgba(160, 160, 255, 0.9);
    color: rgba(160, 160, 255, 1);
}
.detail__send-btn:disabled { opacity: 0.3; cursor: default; }
@keyframes detail-spin {
    to { transform: rotate(360deg); }
}
.detail__send-spinner {
    display: inline-block;
    width: 13px;
    height: 13px;
    border: 2px solid currentColor;
    border-top-color: transparent;
    border-radius: 50%;
    animation: detail-spin 0.6s linear infinite;
    opacity: 0.75;
}
.detail__char {
    position: absolute;
    top: 0.45rem;
    right: 0.6rem;
    font-size: 0.68rem;
    color: rgba(255, 255, 255, 0.3);
    pointer-events: none;
}
.detail__char--warn { color: rgba(160, 160, 255, 0.8); }
.detail__err {
    font-size: 0.65rem;
    color: rgba(239, 68, 68, 0.7);
    margin-top: 0.2rem;
}

/* ── Mobile ───────────────────────────────────────────────── */
@media (max-width: 640px) {
    /* Modal body: flex column, scroll disabled */
    :deep(.site-modal-body) {
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    /* Detail: flex column filling modal height */
    .detail {
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow: hidden;
        grid-template-columns: unset;
        grid-template-rows: unset;
        grid-template-areas: unset;
    }

    /* Scroll zone: left + right scroll together */
    .detail__scroll {
        display: block;
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        scrollbar-width: none;
    }
    .detail__scroll::-webkit-scrollbar { display: none; }

    /* Left: natural flow */
    .detail__left {
        grid-area: unset;
        display: block;
        border-right: none;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        overflow: visible;
        max-height: unset;
    }
    .detail__body-wrap {
        flex: unset;
        min-height: unset;
        overflow: visible;
    }
    .detail__photo { max-height: 65vw; }

    /* Right: natural flow, no internal scroll */
    .detail__right {
        grid-area: unset;
        display: block;
        overflow: visible;
        min-height: unset;
    }
    .detail__cmts-list {
        overflow: visible;
        max-height: unset;
        height: auto;
        padding-bottom: 0.5rem;
    }

    /* Fixed footer: true flex child, edge-to-edge */
    .detail__input-area {
        grid-area: unset;
        flex-shrink: 0;
        background: rgb(10, 9, 20);
        border-top: 1px solid rgba(160, 160, 255, 0.12);
        padding: 0.6rem 0.75rem 0.75rem;
    }

    /* Reply-to username: bigger */
    .detail__reply-hint {
        font-size: 0.9rem;
        font-weight: 500;
        margin-bottom: 0.3rem;
    }

    /* Textarea: 2 rows, compact padding */
    .detail__textarea {
        font-size: 0.88rem;
        padding: 0.5rem 3.4rem 0.5rem 0.75rem;
        line-height: 1.45;
    }

    /* Send button */
    .detail__send-label { display: none; }
    .detail__send-icon  { font-size: 1.3rem; }
    .detail__send-btn   { bottom: 0.5rem; padding: 0.35rem 0.5rem; }
}
</style>

<style>
.photo-fullscreen {
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: rgba(0, 0, 0, 0.92);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: zoom-out;
    animation: fs-in 0.18s ease;
}
@keyframes fs-in {
    from { opacity: 0; }
    to   { opacity: 1; }
}
.photo-fullscreen__img {
    max-width: 92vw;
    max-height: 92vh;
    object-fit: contain;
    border-radius: 4px;
    cursor: default;
    box-shadow: 0 16px 64px rgba(0, 0, 0, 0.7);
}
.photo-fullscreen__close {
    position: fixed;
    top: 1.25rem;
    right: 1.25rem;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.75);
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.photo-fullscreen__close:hover {
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
}
</style>
