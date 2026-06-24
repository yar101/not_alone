<script setup>
const props = defineProps({
    user: { type: Object, required: true },
    size: { type: Number, default: 36 },
});

const initials = (props.user?.name ?? '?').charAt(0).toUpperCase();
</script>

<template>
    <div class="ua-container" :style="{ width: size + 'px', height: size + 'px' }">
        <div
            class="ua-wrap"
            :style="{ fontSize: (size * 0.38) + 'px' }"
            :class="{ 'is-male': user?.gender === 'male' }"
        >
            <img v-if="user.avatar_url" :src="user.avatar_url" class="ua-img" alt="" />
            <span v-else class="ua-initials">{{ initials }}</span>
        </div>
        <img v-if="user?.active_frame_path" :src="'/storage/' + user.active_frame_path" class="ua-frame" alt="" />
    </div>
</template>

<style scoped>
.ua-container {
    position: relative;
    display: inline-flex;
    flex-shrink: 0;
}
.ua-wrap {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    overflow: hidden;
    background: rgba(255, 178, 239, 0.15);
    border: 2px solid rgba(120, 100, 230, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
}
.ua-wrap.is-male {
    background: rgba(100, 210, 255, 0.15);
    border-color: rgba(100, 210, 255, 0.45);
}
.ua-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.ua-initials {
    font-weight: 600;
    color: var(--color-base-1);
    line-height: 1;
    user-select: none;
}
.ua-wrap.is-male .ua-initials {
    color: var(--color-base-2);
}
.ua-frame {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(1.15);
    width: 100%;
    height: 100%;
    object-fit: contain;
    z-index: 5;
    pointer-events: none;
}
</style>
