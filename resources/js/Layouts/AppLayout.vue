<script setup>
import { ref, computed, provide, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import NotificationBell from '@/Components/NotificationBell.vue';
import ChatButton from '@/Components/Chat/ChatButton.vue';
import ChatPanel from '@/Components/Chat/ChatPanel.vue';
import AuthModal from '@/Components/Site/AuthModal.vue';

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
const chatOpen = ref(false);
const chatPanel = ref(null);

function openAuth(tab) {
    authModalTab.value = tab;
    showAuthModal.value = true;
}

function openChatWith(userId) {
    chatPanel.value?.startWith(userId);
}

provide('openChatWith', openChatWith);

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
onUnmounted(() => {
    if (msgChannel) msgChannel.stopListening('.message.received');
    if (window.Echo) window.Echo.leave('presence-online');
});
</script>

<template>
    <div class="app-wrap">
        <header class="app-header">
            <Link href="/" class="app-logo">NoAlone</Link>

            <nav v-if="user" class="header-nav">
                <Link :href="route('users.search')" class="header-nav__item" :class="{ 'header-nav__item--active': $page.url.startsWith('/search') }">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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

                <ChatButton v-if="user" @click="chatOpen = !chatOpen" />
                <NotificationBell v-if="user" />

                <template v-if="user">
                    <Link :href="profileHref" class="user-chip">
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
                    </Link>
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
        <ChatPanel v-if="user" ref="chatPanel" v-model="chatOpen" />
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
    z-index: 100;
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
    border-radius: 999px;
    padding: 0.22rem 0.75rem 0.22rem 0.22rem;
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

.user-chip:hover .user-name {
    animation: user-name-scroll 2.5s ease-in-out infinite alternate;
    animation-delay: 0.5s;
}

@keyframes user-name-scroll {
    0%,  20% { transform: translateX(0); }
    80%, 100% { transform: translateX(min(0px, calc(160px - 100%))); }
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

/* ── Mobile ──────────────────────────────────────────────── */
@media (max-width: 639px) {
    .user-name-clip { display: none; }
    .user-chip { padding: 0.25rem; }
    .guest-btn--fill { display: none; }
    .guest-btn--outline { font-size: 0.75rem; padding: 0.28rem 0.7rem; }
}
</style>
