<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import AuthModal from '@/Components/Site/AuthModal.vue';

const props = defineProps({
    post:     { type: Object, required: true },
    isOwner:  { type: Boolean, default: false },
    authUser: { default: null },
});

const emit = defineEmits(['open-detail', 'liked', 'delete']);

const showAuthModal = ref(false);
const likeAnimating = ref(false);

async function toggleLike() {
    if (!props.authUser) {
        showAuthModal.value = true;
        return;
    }
    likeAnimating.value = true;
    setTimeout(() => { likeAnimating.value = false; }, 400);
    try {
        const { data } = await axios.post(route('posts.like', props.post.id));
        emit('liked', { postId: props.post.id, liked: data.liked, likesCount: data.likes_count });
    } catch (e) {
        console.error(e);
    }
}

// ── Dropdown menu (Teleport + fixed, не обрезается скроллом) ──
const menuOpen = ref(false);
const menuPos  = ref({ top: 0, left: 0 });

function openMenu(e) {
    const rect = e.currentTarget.getBoundingClientRect();
    const menuWidth = 140;
    menuPos.value = {
        top:  rect.bottom + 6,
        left: rect.right - menuWidth,
    };
    menuOpen.value = true;
}

function closeMenu() {
    menuOpen.value = false;
}

function onDeleteClick() {
    closeMenu();
    emit('delete', props.post.id);
}

function onDocClick(e) {
    if (menuOpen.value) closeMenu();
}

onMounted(() => document.addEventListener('click', onDocClick, true));
onUnmounted(() => document.removeEventListener('click', onDocClick, true));

// ── Text clamp detection ──
const textEl = ref(null);
const isClamped = ref(false);

onMounted(async () => {
    await nextTick();
    if (textEl.value) {
        isClamped.value = textEl.value.scrollHeight > textEl.value.clientHeight;
    }
});
</script>

<template>
    <div class="feed-card" @click="emit('open-detail', post)">
        <!-- Header: avatar + name + date + menu -->
        <div class="feed-card__header">
            <a
                class="feed-card__header-left"
                :href="route('profile.show', post.author?.id) + '#about'"
                @click.stop
            >
                <div class="feed-card__avatar">
                    <img v-if="post.author?.avatar_url" :src="post.author.avatar_url" class="feed-card__avatar-img" />
                    <span v-else class="feed-card__avatar-fb">{{ post.author?.name?.[0] }}</span>
                </div>
                <span class="feed-card__author-name">{{ post.author?.name }}</span>
            </a>
            <div class="feed-card__header-right">
                <span class="feed-card__date">{{ post.created_at }}</span>
                <button v-if="isOwner" class="feed-card__menu-btn" @click.stop="openMenu">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Photo -->
        <img v-if="post.photo_url" :src="post.photo_url" class="feed-card__photo" />

        <!-- Body -->
        <div class="feed-card__body">
            <p ref="textEl" class="feed-card__text">{{ post.body }}</p>
        </div>

        <!-- Read more -->
        <button v-if="isClamped" class="feed-card__read-more">
            ··· читать далее
        </button>

        <!-- Footer: [like] [comment] -->
        <div class="feed-card__footer">
            <div class="feed-card__actions">
                <!-- Like -->
                <button
                    class="feed-card__action"
                    :class="{ 'feed-card__action--liked': post.liked_by_me }"
                    @click.stop="toggleLike"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" :fill="post.liked_by_me ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="{ 'like-pop': likeAnimating }">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                    <span>{{ post.likes_count }}</span>
                </button>

                <!-- Comments -->
                <button class="feed-card__action" @click.stop="emit('open-detail', post)">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span>{{ post.comments_count }}</span>
                </button>
            </div>
        </div>
    </div>

    <AuthModal :show="showAuthModal" initial-tab="register" @close="showAuthModal = false" />

    <!-- Dropdown — teleported to body, position: fixed, never clipped -->
    <Teleport to="body">
        <div
            v-if="menuOpen"
            class="feed-card-dropdown"
            :style="{ top: menuPos.top + 'px', left: menuPos.left + 'px' }"
            @click.stop
        >
            <button class="feed-card-dropdown__item feed-card-dropdown__item--danger" @click="onDeleteClick">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                </svg>
                Удалить
            </button>
        </div>
    </Teleport>
</template>

<style scoped>
.feed-card {
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.02);
    overflow: hidden;
    cursor: pointer;
    transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
    display: flex;
    flex-direction: column;
}

.feed-card__footer {
    margin-top: auto;
}
.feed-card:hover {
    border-color: rgba(160, 160, 255, 0.22);
    background: rgba(255, 255, 255, 0.04);
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.25);
}

/* Header */
.feed-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.6rem 0.85rem;
}

.feed-card__header-left {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    text-decoration: none;
    color: inherit;
    border-radius: 4px;
    transition: opacity 0.15s;
}
.feed-card__header-left:hover {
    opacity: 0.8;
}

.feed-card__header-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.feed-card__avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}

.feed-card__avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.feed-card__avatar-fb {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(160, 160, 255, 0.14);
    color: rgba(160, 160, 255, 0.75);
    font-size: 0.72rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    text-transform: uppercase;
}

.feed-card__author-name {
    font-size: 0.83rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.85);
}

.feed-card__date {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.4);
    letter-spacing: 0.03em;
}

/* Photo */
.feed-card__photo {
    width: 100%;
    display: block;
    object-fit: cover;
    max-height: 320px;
}

/* Body */
.feed-card__body {
    cursor: pointer;
    padding: 0.65rem 0.85rem 0;
}
.feed-card__text {
    font-size: 1rem;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.75);
    margin: 0;
    white-space: pre-wrap;
    word-break: break-word;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.feed-card__read-more {
    display: block;
    font-size: 0.92rem;
    color: rgba(160, 160, 255, 0.6);
    background: transparent;
    border: none;
    padding: 0.2rem 0.85rem 0.5rem;
    font-family: inherit;
    cursor: pointer;
    text-align: left;
    transition: color 0.15s;
}
.feed-card__read-more:hover {
    color: rgba(160, 160, 255, 0.9);
}

/* Footer */
.feed-card__footer {
    display: flex;
    align-items: center;
    padding: 0.55rem 0.75rem 0.65rem;
}
.feed-card__actions {
    display: flex;
    align-items: center;
    gap: 0.1rem;
}

.feed-card__action {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.55rem;
    background: transparent;
    border: none;
    border-radius: 4px;
    color: rgba(255, 255, 255, 0.55);
    font-size: 0.9rem;
    font-family: inherit;
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
}
.feed-card__action:hover {
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.85);
}
.feed-card__action--liked { color: rgba(224, 24, 108, 1); }

@keyframes like-pop {
    0%   { transform: scale(1); }
    30%  { transform: scale(1.45); }
    60%  { transform: scale(0.88); }
    100% { transform: scale(1); }
}
.like-pop {
    animation: like-pop 0.38s cubic-bezier(0.36, 0.07, 0.19, 0.97);
}
.feed-card__action--liked:hover {
    color: rgba(224, 24, 108, 1);
    background: rgba(224, 24, 108, 0.07);
}

.feed-card__menu-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.2rem 0.3rem;
    background: transparent;
    border: none;
    border-radius: 4px;
    color: rgba(255, 255, 255, 0.2);
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
}
.feed-card__menu-btn:hover {
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.55);
}
</style>

<!-- Dropdown живёт в body, не scoped -->
<style>
.feed-card-dropdown {
    position: fixed;
    z-index: 9999;
    width: 140px;
    background: rgb(18, 14, 26);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    overflow: hidden;
    animation: dropdown-in 0.1s ease;
}
@keyframes dropdown-in {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}
.feed-card-dropdown__item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.55rem 0.85rem;
    background: transparent;
    border: none;
    font-family: inherit;
    font-size: 0.82rem;
    cursor: pointer;
    transition: background 0.12s;
    text-align: left;
}
.feed-card-dropdown__item--danger {
    color: rgba(239, 68, 68, 0.75);
}
.feed-card-dropdown__item--danger:hover {
    background: rgba(239, 68, 68, 0.08);
    color: rgba(239, 68, 68, 1);
}
</style>
