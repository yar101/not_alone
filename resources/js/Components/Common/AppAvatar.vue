<script setup>
import { ref, watch } from "vue";

const props = defineProps({
    src: { default: null },
    name: { type: String, default: "" },
    size: { type: String, default: "md" }, // 'sm' | 'md' | 'lg'
});

const loaded = ref(false);
const error = ref(false);

watch(
    () => props.src,
    () => {
        loaded.value = false;
        error.value = false;
    },
);
</script>

<template>
    <div class="app-avatar" :class="`app-avatar--${size}`">
        <div v-if="src && !loaded && !error" class="app-avatar__shimmer" />
        <div v-if="!src || error" class="app-avatar__fb">
            {{ name?.[0]?.toUpperCase() }}
        </div>
        <img
            v-if="src && !error"
            :src="src"
            class="app-avatar__img"
            :class="{ 'app-avatar__img--loaded': loaded }"
            @load="loaded = true"
            @error="error = true"
        />
    </div>
</template>

<style scoped>
.app-avatar {
    position: relative;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}
.app-avatar--sm {
    width: 20px;
    height: 20px;
}
.app-avatar--md {
    width: 30px;
    height: 30px;
}
.app-avatar--lg {
    width: 32px;
    height: 32px;
}

.app-avatar__shimmer {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0.05) 25%,
        rgba(255, 255, 255, 0.13) 50%,
        rgba(255, 255, 255, 0.05) 75%
    );
    background-size: 200% 100%;
    animation: avatar-shimmer 1.3s ease-in-out infinite;
}
@keyframes avatar-shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

.app-avatar__fb {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 178, 239, 0.14);
    color: var(--color-base-1);
    opacity: 0.75;
    font-weight: 700;
    text-transform: uppercase;
}
.app-avatar--sm .app-avatar__fb {
    font-size: 0.55rem;
}
.app-avatar--md .app-avatar__fb {
    font-size: 0.68rem;
}
.app-avatar--lg .app-avatar__fb {
    font-size: 0.72rem;
}

.app-avatar__img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.app-avatar__img--loaded {
    opacity: 1;
}
</style>
