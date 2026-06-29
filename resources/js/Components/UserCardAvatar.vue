<script setup>
import { computed } from 'vue';
import { useTranslations } from "@/composables/useTranslations";
import IdolBadge from "@/Components/IdolBadge.vue";

const props = defineProps({
    user: {
        type: Object,
        required: true
    },
    showRating: {
        type: Boolean,
        default: true
    },
    showIdolBadge: {
        type: Boolean,
        default: true
    },
    compact: {
        type: Boolean,
        default: false
    },
    showNewbieBadge: {
        type: Boolean,
        default: true
    }
});

const { __ } = useTranslations();

const avatarUrl = computed(() => {
    return props.user.avatar_url || (props.user.avatar_path ? "/storage/" + props.user.avatar_path : null);
});

const activeFrameUrl = computed(() => {
    return props.user.active_frame_url || (props.user.active_frame_path ? "/storage/" + props.user.active_frame_path : null);
});

const initial = computed(() => {
    return props.user.name?.charAt(0).toUpperCase() ?? "?";
});
</script>

<template>
    <div class="card-avatar-wrap" :class="{ 'is-compact': compact, 'is-male': user.gender === 'male' }">
        <div class="card-avatar">
            <img v-if="avatarUrl" :src="avatarUrl" :alt="__('common.avatar')"
                class="card-avatar__img" />
            <span v-else class="card-avatar__initials">{{ initial }}</span>
        </div>
        <img v-if="activeFrameUrl" :src="activeFrameUrl" class="user-active-frame" alt="" />

        <div v-if="showNewbieBadge && user.is_idol && user.is_newbie" class="newbie-badge" @click.prevent.stop>
            <el-tooltip :trigger="['hover', 'click']" placement="top" effect="dark" popper-class="newbie-dark-tooltip">
                <template #content>
                    Этот айдол — новичок, у него менее 25 выполненных заказов.<br>
                    Не судите строго, у него лапки.
                </template>
                <img src="/not_alone_icon_without_background.png" alt="Newbie" />
            </el-tooltip>
        </div>

        <div class="card-avatar-badges" v-if="user.is_idol && (showIdolBadge || (showRating && user.rating))">
            <IdolBadge
                v-if="showIdolBadge"
                class="card-idol-badge"
                :gender="user.gender"
            />
            <span v-if="showRating && user.rating" class="card-rating">
                ★ {{ user.rating }}
            </span>
        </div>
    </div>
</template>

<style scoped>
.card-avatar-wrap {
    position: relative;
    align-self: center;
    margin-bottom: 0.25rem;
}

.user-active-frame {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(1.15);
    width: 110px;
    height: 110px;
    object-fit: contain;
    z-index: 5;
    pointer-events: none;
}

.is-compact .user-active-frame {
    width: 90px;
    height: 90px;
}


.newbie-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    cursor: help;
    transition: filter 0.3s ease;
}
.newbie-badge:hover {
    filter: drop-shadow(0 0 8px rgba(255, 178, 239, 0.7));
}
.newbie-badge img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.5));
}

.is-compact .newbie-badge {
    width: 42px;
    height: 42px;
    top: -4px;
    right: -4px;
}

.card-avatar {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    overflow: hidden;
    background: rgba(255, 178, 239, 0.12);
    border: 2px solid rgba(255, 178, 239, 0.3);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.4s ease;
}

.is-compact .card-avatar {
    width: 90px;
    height: 90px;
}

.card-avatar-wrap.is-male .card-avatar {
    background: rgba(100, 210, 255, 0.12);
    border-color: rgba(100, 210, 255, 0.3);
}

.card-avatar__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.card-avatar__initials {
    font-size: 2.5rem;
    font-weight: 600;
    color: var(--color-base-1);
    text-shadow: 0 0 20px rgba(255, 178, 239, 0.4);
}

.is-compact .card-avatar__initials {
    font-size: 2rem;
}

.card-avatar-wrap.is-male .card-avatar__initials {
    color: var(--color-base-2);
    text-shadow: 0 0 20px rgba(100, 210, 255, 0.4);
}

.card-avatar-badges {
    position: absolute;
    bottom: -4px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
    z-index: 10;
}

.is-compact .card-avatar-badges {
    bottom: -6px;
    gap: 0.25rem;
}

.card-rating {
    background: rgba(20, 15, 30, 0.85);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 178, 239, 0.4);
    border-radius: 4px;
    padding: 0.2rem 0.6rem;
    font-size: 0.75rem;
    color: var(--color-base-1);
    font-weight: 700;
    white-space: nowrap;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    gap: 0.2rem;
    line-height: 1;
}

.is-compact .card-rating {
    padding: 0.15rem 0.45rem;
    font-size: 0.7rem;
}

.card-avatar-wrap.is-male .card-rating {
    border-color: rgba(100, 210, 255, 0.4);
    color: var(--color-base-2);
}

:deep(.card-idol-badge) {
    background: rgba(20, 15, 30, 0.85);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 178, 239, 0.4);
    border-radius: 4px;
    padding: 0.2rem 0.6rem;
    font-size: 0.75rem;
    color: var(--color-base-1);
    font-weight: 700;
    white-space: nowrap;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    line-height: 1;
}

.is-compact :deep(.card-idol-badge) {
    padding: 0.15rem 0.45rem;
    font-size: 0.7rem;
}

.card-avatar-wrap.is-male :deep(.card-idol-badge) {
    border-color: rgba(100, 210, 255, 0.4);
    color: var(--color-base-2);
}
</style>
