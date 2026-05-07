<script setup>
import { ref, onMounted, nextTick } from "vue";
import axios from "axios";
import AuthModal from "@/Components/Site/AuthModal.vue";
import AppAvatar from "@/Components/Common/AppAvatar.vue";
import { useTranslations } from "@/composables/useTranslations";

const { __ } = useTranslations();

const props = defineProps({
    post: { type: Object, required: true },
    isOwner: { type: Boolean, default: false },
    authUser: { default: null },
});

const emit = defineEmits(["open-detail", "liked"]);

const showAuthModal = ref(false);
const likeAnimating = ref(false);
const photoLoaded = ref(false);
const photoError = ref(false);

async function toggleLike() {
    if (!props.authUser) {
        showAuthModal.value = true;
        return;
    }
    likeAnimating.value = true;
    setTimeout(() => {
        likeAnimating.value = false;
    }, 400);
    try {
        const { data } = await axios.post(route("posts.like", props.post.id));
        emit("liked", {
            postId: props.post.id,
            liked: data.liked,
            likesCount: data.likes_count,
        });
    } catch (e) {
        console.error(e);
    }
}

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
        <!-- Header: avatar + name + date -->
        <div class="feed-card__header">
            <a
                class="feed-card__header-left"
                :href="route('profile.show', post.author?.id) + '#about'"
                @click.stop
            >
                <AppAvatar
                    :src="post.author?.avatar_url"
                    :name="post.author?.name ?? ''"
                    size="lg"
                />
                <span class="feed-card__author-name">{{
                    post.author?.name
                }}</span>
            </a>
            <span class="feed-card__date">{{ post.created_at }}</span>
        </div>

        <!-- Photo -->
        <div v-if="post.photo_url" class="feed-card__photo-wrap">
            <div
                v-if="!photoLoaded && !photoError"
                class="feed-card__photo-shimmer"
            />
            <div v-if="photoError" class="feed-card__photo-error">
                <svg
                    width="28"
                    height="28"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <rect x="3" y="3" width="18" height="18" rx="2" />
                    <circle cx="8.5" cy="8.5" r="1.5" />
                    <polyline points="21 15 16 10 5 21" />
                </svg>
            </div>
            <img
                :src="post.photo_url"
                class="feed-card__photo"
                :class="{ 'feed-card__photo--loaded': photoLoaded }"
                @load="photoLoaded = true"
                @error="photoError = true"
            />
        </div>

        <!-- Body -->
        <div class="feed-card__body">
            <p ref="textEl" class="feed-card__text">{{ post.body }}</p>
        </div>

        <!-- Read more -->
        <button v-if="isClamped" class="feed-card__read-more">
            {{ __("post.read_more") }}
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
                    <svg
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        :fill="post.liked_by_me ? 'currentColor' : 'none'"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        :class="{ 'like-pop': likeAnimating }"
                    >
                        <path
                            d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
                        />
                    </svg>
                    <span>{{ post.likes_count }}</span>
                </button>

                <!-- Comments -->
                <button
                    class="feed-card__action"
                    @click.stop="emit('open-detail', post)"
                >
                    <svg
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path
                            d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                        />
                    </svg>
                    <span>{{ post.comments_count }}</span>
                </button>
            </div>
        </div>
    </div>

    <AuthModal
        :show="showAuthModal"
        initial-tab="register"
        @close="showAuthModal = false"
    />
</template>

<style scoped>
.feed-card {
    break-inside: avoid;
    margin-bottom: 1.6rem;
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.02);
    overflow: hidden;
    cursor: pointer;
    transition:
        border-color 0.2s ease,
        background 0.2s ease,
        box-shadow 0.2s ease;
    display: flex;
    flex-direction: column;
}

.feed-card:hover {
    border-color: rgba(255, 178, 239, 0.22);
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
.feed-card__photo-wrap {
    position: relative;
    width: 100%;
    aspect-ratio: 4 / 3;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.03);
    flex-shrink: 0;
}
.feed-card__photo-shimmer {
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
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
.feed-card__photo-error {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.12);
}
.feed-card__photo {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    opacity: 0;
    transition: opacity 0.35s ease;
}
.feed-card__photo--loaded {
    opacity: 1;
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
    color: var(--color-base-1);
    opacity: 0.6;
    background: transparent;
    border: none;
    padding: 0.2rem 0.85rem 0.5rem;
    font-family: inherit;
    cursor: pointer;
    text-align: left;
    transition:
        color 0.15s,
        opacity 0.15s;
}
.feed-card__read-more:hover {
    color: var(--color-base-1);
    opacity: 0.9;
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
    transition:
        color 0.15s,
        background 0.15s;
}
.feed-card__action:hover {
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.85);
}
.feed-card__action--liked {
    color: rgba(224, 24, 108, 1);
}

@keyframes like-pop {
    0% {
        transform: scale(1);
    }
    30% {
        transform: scale(1.45);
    }
    60% {
        transform: scale(0.88);
    }
    100% {
        transform: scale(1);
    }
}
.like-pop {
    animation: like-pop 0.38s cubic-bezier(0.36, 0.07, 0.19, 0.97);
}
.feed-card__action--liked:hover {
    color: rgba(224, 24, 108, 1);
    background: rgba(224, 24, 108, 0.07);
}
</style>
