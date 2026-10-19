<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from "vue";
import { usePage, router } from "@inertiajs/vue3";

const page = usePage();
const impersonating = computed(() => Boolean(page.props.impersonating));
const user = computed(() => page.props.auth?.user);

const bannerRef = ref(null);
const isLeaving = ref(false);
let bannerResizeObserver = null;

function syncBannerHeight() {
    if (typeof document === "undefined") return;

    if (impersonating.value && bannerRef.value) {
        const height = bannerRef.value.offsetHeight || 44;
        document.documentElement.style.setProperty("--impersonate-banner-height", `${height}px`);
        document.documentElement.classList.add("has-impersonate-banner");
        document.body.classList.add("has-impersonate-banner");
    } else {
        document.documentElement.style.setProperty("--impersonate-banner-height", "0px");
        document.documentElement.classList.remove("has-impersonate-banner");
        document.body.classList.remove("has-impersonate-banner");
    }
}

watch(
    () => [impersonating.value, bannerRef.value],
    () => {
        nextTick(() => {
            syncBannerHeight();
            if (bannerRef.value && !bannerResizeObserver && typeof ResizeObserver !== "undefined") {
                bannerResizeObserver = new ResizeObserver(() => syncBannerHeight());
                bannerResizeObserver.observe(bannerRef.value);
            }
        });
    },
    { immediate: true }
);

function leaveImpersonation() {
    if (isLeaving.value) return;
    isLeaving.value = true;
    router.post(route("impersonate.leave"), {}, {
        onFinish: () => {
            isLeaving.value = false;
        },
    });
}

onMounted(() => {
    syncBannerHeight();
    window.addEventListener("resize", syncBannerHeight, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener("resize", syncBannerHeight);
    if (bannerResizeObserver) {
        bannerResizeObserver.disconnect();
        bannerResizeObserver = null;
    }
    if (typeof document !== "undefined") {
        document.documentElement.style.setProperty("--impersonate-banner-height", "0px");
        document.documentElement.classList.remove("has-impersonate-banner");
        document.body.classList.remove("has-impersonate-banner");
    }
});
</script>

<template>
    <div
        v-if="impersonating"
        ref="bannerRef"
        class="impersonate-banner"
        role="alert"
    >
        <div class="impersonate-banner__content">
            <div class="impersonate-banner__info">
                <span class="impersonate-banner__icon" aria-hidden="true">⚠️</span>
                <span class="impersonate-banner__text">
                    Вы вошли от лица <strong>{{ user?.name || "пользователя" }}</strong> (Режим администратора)
                </span>
            </div>
            <button
                type="button"
                class="impersonate-banner__btn"
                :disabled="isLeaving"
                @click="leaveImpersonation"
            >
                <span v-if="isLeaving">Возврат...</span>
                <span v-else>Вернуться в панель администратора ➔</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.impersonate-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    width: 100%;
    z-index: 2147483647; /* Поверх абсолютно всех элементов и модалок */
    background: linear-gradient(90deg, #ea580c 0%, #d946ef 55%, #c026d3 100%);
    color: #ffffff;
    padding: 0.5rem 1.25rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.45), 0 1px 0 rgba(255, 255, 255, 0.25) inset;
    border-bottom: 1px solid rgba(255, 255, 255, 0.18);
    box-sizing: border-box;
}

.impersonate-banner__content {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    font-size: 0.88rem;
    flex-wrap: wrap;
}

.impersonate-banner__info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.impersonate-banner__icon {
    font-size: 1rem;
    line-height: 1;
    flex-shrink: 0;
}

.impersonate-banner__text {
    line-height: 1.35;
    word-break: break-word;
}

.impersonate-banner__text strong {
    font-weight: 700;
    color: #ffffff;
}

.impersonate-banner__btn {
    background: rgba(255, 255, 255, 0.22);
    border: 1px solid rgba(255, 255, 255, 0.45);
    color: #ffffff;
    padding: 0.35rem 0.9rem;
    border-radius: 7px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s ease;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    flex-shrink: 0;
}

.impersonate-banner__btn:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.38);
    border-color: rgba(255, 255, 255, 0.7);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.28);
}

.impersonate-banner__btn:active:not(:disabled) {
    transform: translateY(0);
}

.impersonate-banner__btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@media (max-width: 640px) {
    .impersonate-banner {
        padding: 0.45rem 0.75rem;
    }
    .impersonate-banner__content {
        gap: 0.4rem;
    }
    .impersonate-banner__text {
        font-size: 0.78rem;
    }
    .impersonate-banner__btn {
        font-size: 0.75rem;
        padding: 0.25rem 0.65rem;
    }
}
</style>
