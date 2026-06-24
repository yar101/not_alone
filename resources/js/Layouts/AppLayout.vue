<script setup>
import { ref, computed, provide, watch, onMounted, onUnmounted } from "vue";
import { Link, usePage, router } from "@inertiajs/vue3";
import { ElNotification } from "element-plus";
import NotificationBell from "@/Components/NotificationBell.vue";
import ChatButton from "@/Components/Chat/ChatButton.vue";
import ChatPanel from "@/Components/Chat/ChatPanel.vue";
import CartIcon from "@/Components/Cart/CartIcon.vue";
import CartDropdown from "@/Components/Cart/CartDropdown.vue";
import AuthModal from "@/Components/Site/AuthModal.vue";
import UserSidebar from "@/Components/UserSidebar.vue";
import LocaleLoader from "@/Components/LocaleLoader.vue";
import PwaUpdateModal from "@/Components/PwaUpdateModal.vue";
import StrikeAlertModal from "@/Components/Site/StrikeAlertModal.vue";
import { useTranslations } from "@/composables/useTranslations";

const { __ } = useTranslations();

const page = usePage();
const user = computed(() => page.props.auth.user);
const initials = computed(
    () => user.value?.name?.charAt(0).toUpperCase() ?? "?",
);
const avatarLoaded = ref(false);
watch(
    () => user.value?.avatar_url,
    () => {
        avatarLoaded.value = false;
    },
);
const profileHref = computed(() =>
    user.value ? route("profile.show", { user: user.value.id }) : "/",
);
const isIdol = computed(() => page.props.is_idol);
const idolStatus = computed(() => page.props.idol_status);

const showAuthModal = ref(false);
const authModalTab = ref("login");
const chatOpen = ref(false);
const chatPanel = ref(null);
const cartOpen = ref(false);
const cartDropdown = ref(null);
const cartInitialTab = ref("services");
const sidebarOpen = ref(false);
const pwaUpdateAvailable = ref(false);

function handleToggleChatEvent(e) {
    cartOpen.value = false;
    chatOpen.value = e.detail;
}
function handleToggleSidebarEvent(e) {
    sidebarOpen.value = e.detail;
}

function onCartClick() {
    if (cartOpen.value) {
        cartOpen.value = false;
        return;
    }
    if (chatOpen.value) chatPanel.value?.silentClose?.();
    cartOpen.value = true;
}

function onChatClick() {
    if (chatOpen.value) {
        chatOpen.value = false;
        return;
    }
    if (cartOpen.value) cartDropdown.value?.silentClose?.();
    chatOpen.value = true;
}

// ── Cart state (localStorage) ─────────────────────────────
const CART_KEY = computed(() => (user.value ? `cart_${user.value.id}` : null));

const emptyCart = () => ({
    services: { idol_id: null, idol_name: "", idol_avatar: null, items: [] },
    content: { items: [] },
});

const cart = ref(emptyCart());

function loadCart() {
    if (!CART_KEY.value) return;
    try {
        const raw = localStorage.getItem(CART_KEY.value);
        if (raw) {
            const parsed = JSON.parse(raw);
            // Migrate old format: if it has .items directly (old services-only format)
            if (Array.isArray(parsed.items)) {
                cart.value = {
                    services: {
                        idol_id: parsed.idol_id ?? null,
                        idol_name: parsed.idol_name ?? "",
                        idol_avatar: parsed.idol_avatar ?? null,
                        items: parsed.items ?? [],
                    },
                    content: { items: [] },
                };
                localStorage.setItem(
                    CART_KEY.value,
                    JSON.stringify(cart.value),
                );
            } else {
                cart.value = parsed;
            }
        }
    } catch {}
}

function saveCart() {
    if (!CART_KEY.value) return;
    localStorage.setItem(CART_KEY.value, JSON.stringify(cart.value));
}

watch(cart, saveCart, { deep: true });

onMounted(loadCart);

function addToContentCart(pack) {
    const already = cart.value.content.items.find((i) => i.pack_id === pack.id);
    if (already) return;
    cart.value.content.items.push({
        pack_id: pack.id,
        title: pack.title,
        price: pack.price,
        cover_url: pack.cover_url ?? null,
        idol_id: pack.idol_id ?? null,
        idol_name: pack.idol_name ?? null,
    });
}

function openAuth(tab) {
    authModalTab.value = tab;
    showAuthModal.value = true;
}

function openChatWith(userId) {
    chatPanel.value?.startWith(userId);
}

function handleOpenOrderEvent(e) {
    openOrder(e.detail);
}

function openOrder(orderId) {
    cartOpen.value = false;
    chatOpen.value = true;
    chatPanel.value?.openOrder(orderId);
}

function openConversation(conversationId) {
    cartOpen.value = false;
    chatOpen.value = true;
    chatPanel.value?.startConversation(conversationId);
}

function addToCart(service, idol) {
    const sc = cart.value.services;
    // If cart has items from a different idol — clear and start fresh
    if (sc.idol_id && sc.idol_id !== idol.id) {
        cart.value.services = {
            idol_id: idol.id,
            idol_name: idol.name,
            idol_avatar: idol.avatar_url ?? null,
            items: [],
        };
    } else if (!sc.idol_id) {
        sc.idol_id = idol.id;
        sc.idol_name = idol.name;
        sc.idol_avatar = idol.avatar_url ?? null;
    }

    const existing = cart.value.services.items.find(
        (i) => i.service_id === service.id,
    );
    if (existing) {
        existing.quantity = (existing.quantity || 1) + 1;
    } else {
        cart.value.services.items.push({
            service_id: service.id,
            name: service.name,
            price: service.price,
            quantity: 1,
            time_unit: service.time_unit ?? null,
        });
    }
}

provide("openAuth", openAuth);
provide("openChatWith", openChatWith);
provide("openConversation", openConversation);
provide("openOrder", openOrder);
provide("addToCart", addToCart);
provide("addToContentCart", addToContentCart);
provide("cart", cart);
provide("openCart", (tab = "services") => {
    cartInitialTab.value = tab;
    cartOpen.value = true;
});

// ── Global online presence ────────────────────────────────
const onlineUserIds = ref([]);
provide("onlineUserIds", onlineUserIds);

// ── Global listeners ──────────────────────────────────────
let msgChannel = null;
let onlineChannel = null;
onMounted(() => {
    if (user.value && window.Echo) {
        msgChannel = window.Echo.private(
            `App.Models.User.${user.value.id}`,
        ).listen(".message.received", () => {
            router.reload({ only: ["unread_messages_count"] });
        });

        onlineChannel = window.Echo.join("presence-online")
            .here((members) => {
                onlineUserIds.value = members.map((m) => m.id);
            })
            .joining((member) => {
                if (!onlineUserIds.value.includes(member.id)) {
                    onlineUserIds.value.push(member.id);
                }
            })
            .leaving((member) => {
                onlineUserIds.value = onlineUserIds.value.filter(
                    (id) => id !== member.id,
                );
            });
    }
});
function handleUserBannedEvent() {
    ElNotification({
        duration: 5000,
        position: "top-right",
        offset: 70,
        customClass: "app-notif app-notif--warn",
        showClose: true,
        message: __("layout.banned_error"),
    });
}

onMounted(() => {
    window.addEventListener("noalone:open-order", handleOpenOrderEvent);
    window.addEventListener("noalone:user-banned", handleUserBannedEvent);
    window.addEventListener("noalone:toggle-chat", handleToggleChatEvent);
    window.addEventListener("noalone:toggle-sidebar", handleToggleSidebarEvent);

    if ("serviceWorker" in navigator) {
        let refreshing = false;
        navigator.serviceWorker.addEventListener("controllerchange", () => {
            if (refreshing) return;
            refreshing = true;
            pwaUpdateAvailable.value = true;
        });
    }
});

onUnmounted(() => {
    window.removeEventListener("noalone:open-order", handleOpenOrderEvent);
    window.removeEventListener("noalone:user-banned", handleUserBannedEvent);
    window.removeEventListener("noalone:toggle-chat", handleToggleChatEvent);
    window.removeEventListener("noalone:toggle-sidebar", handleToggleSidebarEvent);
    if (msgChannel) msgChannel.stopListening(".message.received");
    if (window.Echo) window.Echo.leave("presence-online");
});
</script>

<template>
    <LocaleLoader />
    <PwaUpdateModal :show="pwaUpdateAvailable" />
    <StrikeAlertModal />
    <div class="app-wrap">
        <header class="app-header">
            <Link href="/" class="app-logo">
                <img src="/app-logo-v3.png" alt="Not Alone" class="app-logo__img" />
            </Link>

            <div class="header-right">
                <CartIcon
                    v-if="user"
                    id="tour-cart"
                    :cart="cart"
                    :active="cartOpen"
                    @click="onCartClick"
                />
                <ChatButton
                    v-if="user"
                    id="tour-chat"
                    :active="chatOpen"
                    @click="onChatClick"
                />
                <NotificationBell v-if="user" id="tour-notifications" />

                <template v-if="user">
                    <button @click="sidebarOpen = true" id="tour-user-chip" class="user-chip">
                        <div
                            class="user-avatar"
                            :class="{ 'is-male': user.gender === 'male' }"
                        >
                            <template v-if="user.avatar_url">
                                <div
                                    v-if="!avatarLoaded"
                                    class="user-avatar__shimmer"
                                />
                                <img
                                    :src="user.avatar_url"
                                    class="user-avatar__img"
                                    :class="{
                                        'user-avatar__img--loaded':
                                            avatarLoaded,
                                    }"
                                    :alt="__('common.avatar')"
                                    @load="avatarLoaded = true"
                                />
                            </template>
                            <span v-else class="user-avatar__initials">{{
                                initials
                            }}</span>
                        </div>
                        <img v-if="user.active_frame_path" :src="'/storage/' + user.active_frame_path" class="applayout-active-frame" alt="" />
                        <span class="user-name-clip">
                            <span class="user-name">{{ user.name }}</span>
                        </span>
                    </button>
                </template>
                <template v-else>
                    <button
                        @click="openAuth('login')"
                        class="guest-btn guest-btn--outline"
                    >
                        {{ __("common.login") }}
                    </button>
                    <button
                        @click="openAuth('register')"
                        class="guest-btn guest-btn--fill"
                    >
                        {{ __("common.register") }}
                    </button>
                </template>
            </div>
        </header>

        <main class="app-main">
            <slot />
        </main>

        <AuthModal
            :show="showAuthModal"
            :initial-tab="authModalTab"
            @close="showAuthModal = false"
        />
        <CartDropdown
            v-if="user"
            ref="cartDropdown"
            v-model="cartOpen"
            :cart="cart"
            :initial-tab="cartInitialTab"
            @clear-services="
                cart.services = {
                    idol_id: null,
                    idol_name: '',
                    idol_avatar: null,
                    items: [],
                }
            "
            @clear-content="cart.content.items = []"
            @remove-service="(idx) => cart.services.items.splice(idx, 1)"
            @remove-content="(idx) => cart.content.items.splice(idx, 1)"
            @change-quantity="
                (idx, delta) => {
                    const q = (cart.services.items[idx].quantity || 1) + delta;
                    cart.services.items[idx].quantity = Math.max(1, q);
                }
            "
        />
        <ChatPanel v-if="user" ref="chatPanel" v-model="chatOpen" />
        <UserSidebar
            v-if="user"
            v-model="sidebarOpen"
            :user="user"
            :is-idol="isIdol"
            :rating="user?.rating"
        />
    </div>
</template>

<style scoped>
/* ── Layout wrap ─────────────────────────────────────────── */
.app-wrap {
    min-height: 100vh;
    background: #0a0a14;
    display: flex;
    flex-direction: column;
}

/* ── Sticky header ───────────────────────────────────────── */
.app-header {
    position: sticky;
    top: 0;
    z-index: 900;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 2rem;
    background: rgba(10, 10, 20, 0.96);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    flex-shrink: 0;
}

/* ── Logo ────────────────────────────────────────────────── */
.app-logo {
    display: flex;
    align-items: center;
    text-decoration: none;
    transition: opacity 0.2s;
    overflow: visible;
}
.app-logo:hover {
    opacity: 0.8;
}
.app-logo__img {
    height: 44px;
    width: auto;
    display: block;
    position: relative;
    top: 0;
}

/* ── User chip ───────────────────────────────────────────── */
.user-chip {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    text-decoration: none;
    border-radius: 9px;
    padding: 0.35rem 1.1rem 0.35rem 0.55rem;
    border: 1px solid transparent;
    transition:
        background 0.18s,
        border-color 0.18s;
    position: relative;
}

.applayout-active-frame {
    position: absolute;
    top: 50%;
    left: 0.55rem; /* user-chip padding-left */
    transform: translateY(-50%) scale(1.15);
    width: 32px;
    height: 32px;
    object-fit: contain;
    z-index: 5;
    pointer-events: none;
}

@media (hover: hover) {
    .user-chip:hover {
        background: rgba(255, 178, 239, 0.08);
        border-color: rgba(255, 178, 239, 0.22);
    }
}

/* ── Avatar ──────────────────────────────────────────────── */
.user-avatar {
    position: relative;
    width: 40px;
    height: 40px;
    border-radius: 999px;
    overflow: hidden;
    background: color-mix(in srgb, var(--color-base-1), transparent 85%);
    border: 1.5px solid color-mix(in srgb, var(--color-base-1), transparent 50%);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.user-avatar.is-male {
    --color-base-1: var(--color-base-2);
}

.user-avatar__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0;
    transition: opacity 0.3s;
}
.user-avatar__img--loaded {
    opacity: 1;
}
.user-avatar__shimmer {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0.04) 25%,
        rgba(255, 255, 255, 0.1) 50%,
        rgba(255, 255, 255, 0.04) 75%
    );
    background-size: 200% 100%;
    animation: avatar-shimmer 1.5s ease-in-out infinite;
}
@keyframes avatar-shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

.user-avatar__initials {
    font-size: 0.9rem;
    font-weight: 600;
    color: color-mix(in srgb, var(--color-base-1), white 15%);
    line-height: 1;
}

/* ── User name ───────────────────────────────────────────── */
.user-name-clip {
    max-width: 160px;
    overflow: hidden;
    display: inline-block;
    vertical-align: middle;
}

.user-name {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.65);
    font-family: "Rubik", sans-serif;
    white-space: nowrap;
    display: inline-block;
    transition: color 0.18s;
}
@media (hover: hover) {
    .user-chip:hover .user-name {
        color: rgba(255, 255, 255, 0.9);
    }
}

/* ── Main ────────────────────────────────────────────────── */
.app-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

/* ── Header right group ───────────────────────────────────── */
.header-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* ── Guest auth buttons ──────────────────────────────────── */
.guest-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.9rem;
    border-radius: 3px;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-decoration: none;
    transition:
        background 0.18s,
        border-color 0.18s,
        color 0.18s,
        box-shadow 0.18s;
    white-space: nowrap;
}
.guest-btn--outline {
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 60%);
    color: color-mix(in srgb, var(--color-base-1), white 15%);
    background: transparent;
}
.guest-btn--outline:hover {
    border-color: color-mix(in srgb, var(--color-base-1), transparent 30%);
    color: color-mix(in srgb, var(--color-base-1), white 40%);
    background: color-mix(in srgb, var(--color-base-1), transparent 92%);
}
.guest-btn--fill {
    border: 1px solid transparent;
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--color-base-1), transparent 78%) 0%,
        color-mix(in srgb, var(--color-base-1), black 20%) 100%
    );
    color: color-mix(in srgb, var(--color-base-1), white 40%);
    box-shadow: 0 0 12px
        color-mix(in srgb, var(--color-base-1), transparent 80%);
}
.guest-btn--fill:hover {
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--color-base-1), transparent 65%) 0%,
        color-mix(in srgb, var(--color-base-1), black 10%) 100%
    );
    box-shadow: 0 0 18px
        color-mix(in srgb, var(--color-base-1), transparent 60%);
    color: color-mix(in srgb, var(--color-base-1), white 50%);
}

/* ── Tablet (640–899px) ──────────────────────────────────── */
@media (max-width: 899px) {
    .app-header {
        padding: 0 1.25rem;
    }
}

/* ── Mobile header upgrade (≤768px) ─────────────────────── */
@media (max-width: 768px) {
    .app-header {
        height: 60px;
        padding: 0 1rem;
        border-bottom-color: transparent;
    }
    .app-logo__img {
        height: 36px;
    }
}

/* ── Mobile (< 640px) ────────────────────────────────────── */
@media (max-width: 639px) {
    .app-header {
        padding: 0 0.875rem;
    }
    .header-right {
        gap: 0.5rem;
    }
    .user-name-clip {
        display: none;
    }
    .user-chip {
        padding: 3px;
    }
    .user-avatar {
        width: 40px;
        height: 40px;
        border-width: 2px;
    }
    .guest-btn--fill {
        display: none;
    }
    .guest-btn--outline {
        font-size: 0.75rem;
        padding: 0.28rem 0.7rem;
    }
}

/* ── Very small screens (≤480px) ────────────────────────── */
@media (max-width: 480px) {
}
</style>
