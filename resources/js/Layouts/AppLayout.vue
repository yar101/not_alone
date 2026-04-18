<script setup>
import { ref, computed, provide, watch, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { ElNotification } from 'element-plus';
import NotificationBell from '@/Components/NotificationBell.vue';
import ChatButton from '@/Components/Chat/ChatButton.vue';
import ChatPanel from '@/Components/Chat/ChatPanel.vue';
import CartIcon from '@/Components/Cart/CartIcon.vue';
import CartDropdown from '@/Components/Cart/CartDropdown.vue';
import AuthModal from '@/Components/Site/AuthModal.vue';
import UserSidebar from '@/Components/UserSidebar.vue';
import LocaleLoader from '@/Components/LocaleLoader.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const initials = computed(() => user.value?.name?.charAt(0).toUpperCase() ?? '?');
const profileHref = computed(() =>
    user.value ? route('profile.show', { user: user.value.id }) : '/'
);
const isIdol = computed(() => page.props.is_idol);
const idolStatus = computed(() => page.props.idol_status);
const showIdolBtn = computed(() => user.value && !isIdol.value && idolStatus.value !== 'pending');

const showAuthModal = ref(false);
const authModalTab  = ref('login');
const chatOpen   = ref(false);
const chatPanel  = ref(null);
const cartOpen      = ref(false);
const cartInitialTab = ref('services');
const sidebarOpen = ref(false);

// ── Cart state (localStorage) ─────────────────────────────
const CART_KEY = computed(() => user.value ? `cart_${user.value.id}` : null);

const emptyCart = () => ({
    services: { idol_id: null, idol_name: '', idol_avatar: null, items: [] },
    content:  { items: [] },
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
                    services: { idol_id: parsed.idol_id ?? null, idol_name: parsed.idol_name ?? '', idol_avatar: parsed.idol_avatar ?? null, items: parsed.items ?? [] },
                    content:  { items: [] },
                };
                localStorage.setItem(CART_KEY.value, JSON.stringify(cart.value));
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
    const already = cart.value.content.items.find(i => i.pack_id === pack.id);
    if (already) return;
    cart.value.content.items.push({
        pack_id:    pack.id,
        title:      pack.title,
        price:      pack.price,
        cover_url:  pack.cover_url ?? null,
        idol_id:    pack.idol_id ?? null,
        idol_name:  pack.idol_name ?? null,
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

function addToCart(service, idol) {
    const sc = cart.value.services;
    // If cart has items from a different idol — clear and start fresh
    if (sc.idol_id && sc.idol_id !== idol.id) {
        cart.value.services = { idol_id: idol.id, idol_name: idol.name, idol_avatar: idol.avatar_url ?? null, items: [] };
    } else if (!sc.idol_id) {
        sc.idol_id    = idol.id;
        sc.idol_name  = idol.name;
        sc.idol_avatar = idol.avatar_url ?? null;
    }

    const existing = cart.value.services.items.find(i => i.service_id === service.id);
    if (existing) {
        existing.quantity = (existing.quantity || 1) + 1;
    } else {
        cart.value.services.items.push({
            service_id: service.id,
            name:       service.name,
            price:      service.price,
            quantity:   1,
            time_unit:  service.time_unit ?? null,
        });
    }
}

provide('openAuth', openAuth);
provide('openChatWith', openChatWith);
provide('openOrder', openOrder);
provide('addToCart', addToCart);
provide('addToContentCart', addToContentCart);
provide('cart', cart);
provide('openCart', (tab = 'services') => { cartInitialTab.value = tab; cartOpen.value = true; });

// ── Global online presence ────────────────────────────────
const onlineUserIds = ref([]);
provide('onlineUserIds', onlineUserIds);

// ── Global listeners ──────────────────────────────────────
let msgChannel = null;
let onlineChannel = null;
onMounted(() => {
    if (user.value && window.Echo) {
        msgChannel = window.Echo.private(`App.Models.User.${user.value.id}`)
            .listen('.message.received', () => {
                router.reload({ only: ['unread_messages_count'] });
            });

        onlineChannel = window.Echo.join('presence-online')
            .here(members => {
                onlineUserIds.value = members.map(m => m.id);
            })
            .joining(member => {
                if (!onlineUserIds.value.includes(member.id)) {
                    onlineUserIds.value.push(member.id);
                }
            })
            .leaving(member => {
                onlineUserIds.value = onlineUserIds.value.filter(id => id !== member.id);
            });
    }
});
function handleUserBannedEvent() {
    ElNotification({
        duration: 5000,
        position: 'top-right',
        offset: 70,
        customClass: 'app-notif app-notif--warn',
        showClose: true,
        message: 'Невозможно взаимодействовать с заблокированным пользователем',
    });
}

onMounted(() => {
    window.addEventListener('noalone:open-order', handleOpenOrderEvent);
    window.addEventListener('noalone:user-banned', handleUserBannedEvent);
});

onUnmounted(() => {
    window.removeEventListener('noalone:open-order', handleOpenOrderEvent);
    window.removeEventListener('noalone:user-banned', handleUserBannedEvent);
    if (msgChannel) msgChannel.stopListening('.message.received');
    if (window.Echo) window.Echo.leave('presence-online');
});
</script>

<template>
    <LocaleLoader />
    <div class="app-wrap">
        <header class="app-header">
            <Link href="/" class="app-logo">NoAlone</Link>

            <nav v-if="user" class="header-nav">
                <Link :href="route('users.search')" class="header-nav__item" :class="{ 'header-nav__item--active': $page.url.startsWith('/search') }">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    Поиск
                </Link>
            </nav>

            <div class="header-right">
                <Link
                    v-if="showIdolBtn"
                    href="/idol/apply"
                    class="become-idol-btn"
                >Стать Айдолом</Link>

                <!-- Иконка поиска — только на мобиле вместо nav -->
                <Link
                    v-if="user"
                    :href="route('users.search')"
                    class="mobile-search-btn"
                    :class="{ 'mobile-search-btn--active': $page.url.startsWith('/search') }"
                    aria-label="Поиск"
                >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </Link>

                <CartIcon v-if="user" :cart="cart" @click="cartOpen = !cartOpen" />
                <ChatButton v-if="user" @click="chatOpen = !chatOpen" />
                <NotificationBell v-if="user" />

                <template v-if="user">
                    <button @click="sidebarOpen = true" class="user-chip">
                        <div class="user-avatar">
                            <img
                                v-if="user.avatar_url"
                                :src="user.avatar_url"
                                class="user-avatar__img"
                                alt="Аватар"
                            />
                            <span v-else class="user-avatar__initials">{{ initials }}</span>
                        </div>
                        <span class="user-name-clip">
                            <span class="user-name">{{ user.name }}</span>
                        </span>
                    </button>
                </template>
                <template v-else>
                    <button @click="openAuth('login')" class="guest-btn guest-btn--outline">Войти</button>
                    <button @click="openAuth('register')" class="guest-btn guest-btn--fill">Регистрация</button>
                </template>
            </div>
        </header>

        <main class="app-main">
            <slot />
        </main>

        <AuthModal :show="showAuthModal" :initial-tab="authModalTab" @close="showAuthModal = false" />
        <CartDropdown
            v-if="user"
            v-model="cartOpen"
            :cart="cart"
            :initial-tab="cartInitialTab"
            @clear-services="cart.services = { idol_id: null, idol_name: '', idol_avatar: null, items: [] }"
            @clear-content="cart.content.items = []"
            @remove-service="(idx) => cart.services.items.splice(idx, 1)"
            @remove-content="(idx) => cart.content.items.splice(idx, 1)"
            @change-quantity="(idx, delta) => { const q = (cart.services.items[idx].quantity || 1) + delta; cart.services.items[idx].quantity = Math.max(1, q); }"
        />
        <ChatPanel v-if="user" ref="chatPanel" v-model="chatOpen" />
        <UserSidebar v-if="user" v-model="sidebarOpen" :user="user" :is-idol="isIdol" :rating="user?.rating" />
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
    z-index: 1101;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 2rem;
    background: rgba(10, 10, 20, 0.96);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(110, 110, 210, 0.18);
    box-shadow:
        0 1px 0 rgba(110, 110, 210, 0.06),
        0 4px 24px rgba(0, 0, 0, 0.4);
    flex-shrink: 0;
}

/* ── Logo ────────────────────────────────────────────────── */
.app-logo {
    font-family: 'Imbue', serif;
    font-size: 1.35rem;
    font-weight: 400;
    color: #7070d8;
    text-decoration: none;
    letter-spacing: 0.04em;
    text-shadow: 0 0 24px rgba(110, 110, 210, 0.45);
    transition: text-shadow 0.2s, color 0.2s;
}
.app-logo:hover {
    color: #e0558f;
    text-shadow: 0 0 32px rgba(110, 110, 210, 0.7);
}

/* ── User chip ───────────────────────────────────────────── */
.user-chip {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    text-decoration: none;
    border-radius: 8px;
    padding: 0.22rem 1.1rem 0.22rem 0.5rem;
    border: 1px solid transparent;
    transition: background 0.18s, border-color 0.18s;
}
.user-chip:hover {
    background: rgba(110, 110, 210, 0.08);
    border-color: rgba(110, 110, 210, 0.22);
}

/* ── Avatar ──────────────────────────────────────────────── */
.user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    background: rgba(110, 110, 210, 0.15);
    border: 1.5px solid rgba(110, 110, 210, 0.5);
    box-shadow: 0 0 10px rgba(110, 110, 210, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
}

.user-avatar__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-avatar__initials {
    font-size: 0.9rem;
    font-weight: 600;
    color: #7070d8;
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
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.65);
    font-family: 'Figtree', sans-serif;
    white-space: nowrap;
    display: inline-block;
    transition: color 0.18s;
}
.user-chip:hover .user-name { color: rgba(255, 255, 255, 0.9); }


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

/* ── Become Idol button ───────────────────────────────────── */
.become-idol-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.85rem;
    border-radius: 3px;
    border: 1px solid rgba(160, 160, 255, 0.45);
    position: relative;
    background-image: linear-gradient(135deg, #be91ff 0%, #7070d8 50%, #6B3FD9 100%);
    background-clip: text;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    text-decoration: none;
    transition: box-shadow 0.2s, transform 0.15s, border-color 0.2s;
    white-space: nowrap;
}
.become-idol-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 3px;
    background: linear-gradient(135deg, rgba(155,110,232,0.18) 0%, rgba(124,45,126,0.12) 100%);
    z-index: -1;
}
.become-idol-btn:hover {
    border-color: rgba(160, 160, 255, 0.75);
    box-shadow: 0 0 16px rgba(110, 110, 210, 0.4), 0 2px 8px rgba(0,0,0,0.25);
    transform: translateY(-1px);
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
    transition: background 0.18s, border-color 0.18s, color 0.18s, box-shadow 0.18s;
    white-space: nowrap;
}
.guest-btn--outline {
    border: 1px solid rgba(110, 110, 210, 0.4);
    color: rgba(160, 160, 255, 0.85);
    background: transparent;
}
.guest-btn--outline:hover {
    border-color: rgba(160, 160, 255, 0.7);
    color: #be91ff;
    background: rgba(110, 110, 210, 0.08);
}
.guest-btn--fill {
    border: 1px solid transparent;
    background: linear-gradient(135deg, rgba(155,110,232,0.22) 0%, rgba(107,63,217,0.18) 100%);
    color: #be91ff;
    box-shadow: 0 0 12px rgba(110, 110, 210, 0.2);
}
.guest-btn--fill:hover {
    background: linear-gradient(135deg, rgba(155,110,232,0.35) 0%, rgba(107,63,217,0.28) 100%);
    box-shadow: 0 0 18px rgba(110, 110, 210, 0.4);
    color: #d4aaff;
}

/* ── Central nav ─────────────────────────────────────────── */
.header-nav {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.header-nav__item {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.75rem;
    border-radius: 3px;
    font-size: 0.8rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.5);
    text-decoration: none;
    transition: color 0.18s, background 0.18s;
    letter-spacing: 0.02em;
    white-space: nowrap;
}
.header-nav__item:hover {
    color: rgba(255, 255, 255, 0.85);
    background: rgba(110, 110, 210, 0.08);
}
.header-nav__item--active {
    color: #be91ff;
}

/* ── Mobile search button (hidden on desktop) ────────────── */
.mobile-search-btn {
    display: none;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.5);
    text-decoration: none;
    transition: color 0.18s, background 0.18s;
    flex-shrink: 0;
}
.mobile-search-btn:hover,
.mobile-search-btn--active {
    color: #be91ff;
    background: rgba(110, 110, 210, 0.08);
}

/* ── Tablet (640–899px) ──────────────────────────────────── */
@media (max-width: 899px) {
    .header-nav { display: none; }
    .mobile-search-btn { display: inline-flex; }
    .app-header { padding: 0 1.25rem; }
}

/* ── Mobile (< 640px) ────────────────────────────────────── */
@media (max-width: 639px) {
    .app-header { padding: 0 0.75rem; }
    .header-right { gap: 0.25rem; }
    .user-name-clip { display: none; }
    .user-chip { padding: 0.25rem; }
    .guest-btn--fill { display: none; }
    .guest-btn--outline { font-size: 0.75rem; padding: 0.28rem 0.7rem; }
}
</style>
