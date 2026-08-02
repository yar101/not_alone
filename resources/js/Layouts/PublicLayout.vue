<script setup>
import SiteHeader from "@/Components/Site/SiteHeader.vue";

defineProps({
    activePage: { type: String, default: "home" },
});
</script>

<template>
    <div class="public-layout">
        <!-- Единые декоративные фоновые элементы -->
        <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
            <div class="circle1" />
            <div class="circle2" />
            <div class="circle3" />
        </div>

        <!-- Основная обертка с градиентным фоном -->
        <div class="min-h-screen text-white overflow-x-hidden main-gradient relative z-10 flex flex-col">
            <!-- Зафиксированная шапка (без перезагрузок) -->
            <SiteHeader :activePage="activePage" />

            <!-- Мгновенная смена контента без анимационных лагов -->
            <div class="flex-1 flex flex-col relative w-full">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.public-layout {
    min-height: 100vh;
    width: 100%;
}

.main-gradient {
    background: linear-gradient(
            180deg,
            rgba(255, 42, 191, 0.09) 0%,
            rgba(0, 0, 0, 0.56) 100%
        )
        fixed;
}

.circle1,
.circle2,
.circle3 {
    border-radius: 50%;
    background: rgba(60, 60, 190, 0.03);
    box-shadow: inset 0 0 30px rgba(255, 255, 255, 0.015);
    position: absolute;
    right: -10vw;
    top: -10vw;
}

.circle1 {
    width: clamp(350px, 60vw, 900px);
    height: clamp(350px, 60vw, 900px);
}
.circle2 {
    width: clamp(250px, 45vw, 700px);
    height: clamp(250px, 45vw, 700px);
}
.circle3 {
    width: clamp(150px, 30vw, 500px);
    height: clamp(150px, 30vw, 500px);
}

@media (max-width: 768px) {
    .circle1,
    .circle2,
    .circle3 {
        right: 0;
        top: 0;
        transform: translate(40%, -40%);
    }
}
</style>
