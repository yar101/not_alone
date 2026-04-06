<script setup>
const props = defineProps({
    user: { type: Object, required: true },
    size: { type: Number, default: 36 },
});

const initials = (props.user?.name ?? '?').charAt(0).toUpperCase();
</script>

<template>
    <div
        class="ua-wrap"
        :style="{ width: size + 'px', height: size + 'px', fontSize: (size * 0.38) + 'px' }"
    >
        <img v-if="user.avatar_url" :src="user.avatar_url" class="ua-img" alt="" />
        <span v-else class="ua-initials">{{ initials }}</span>
    </div>
</template>

<style scoped>
.ua-wrap {
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    background: rgba(110, 110, 210, 0.15);
    border: 2px solid rgba(120, 100, 230, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
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
</style>
