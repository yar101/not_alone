<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import NotificationBell from '@/Components/NotificationBell.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const initials = computed(() => user.value?.name?.charAt(0).toUpperCase() ?? '?');
const profileHref = computed(() =>
    user.value ? route('profile.show', { user: user.value.id }) : '/'
);
const isIdol = computed(() => page.props.is_idol);
const idolStatus = computed(() => page.props.idol_status);
const showIdolBtn = computed(() => user.value && !isIdol.value && idolStatus.value !== 'pending');
</script>

<template>
    <div class="app-wrap">
        <header class="app-header">
            <Link href="/" class="app-logo">NoAlone</Link>

            <div class="header-right">
                <Link
                    v-if="showIdolBtn"
                    href="/idol/apply"
                    class="become-idol-btn"
                >Стать Айдолом</Link>

                <NotificationBell v-if="user" />

                <Link :href="profileHref" class="user-chip">
                    <div class="user-avatar">
                        <img
                            v-if="user?.avatar_url"
                            :src="user.avatar_url"
                            class="user-avatar__img"
                            alt="Аватар"
                        />
                        <span v-else class="user-avatar__initials">{{ initials }}</span>
                    </div>
                    <span class="user-name">{{ user?.name }}</span>
                </Link>
            </div>
        </header>

        <main class="app-main">
            <slot />
        </main>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Imbue:opsz,wght@10..100,300;10..100,400&display=swap');

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
    border-bottom: 1px solid rgba(200, 70, 126, 0.18);
    box-shadow:
        0 1px 0 rgba(200, 70, 126, 0.06),
        0 4px 24px rgba(0, 0, 0, 0.4);
    flex-shrink: 0;
}

/* ── Logo ────────────────────────────────────────────────── */
.app-logo {
    font-family: 'Imbue', serif;
    font-size: 1.35rem;
    font-weight: 400;
    color: #C8467E;
    text-decoration: none;
    letter-spacing: 0.04em;
    text-shadow: 0 0 24px rgba(200, 70, 126, 0.45);
    transition: text-shadow 0.2s, color 0.2s;
}
.app-logo:hover {
    color: #e0558f;
    text-shadow: 0 0 32px rgba(200, 70, 126, 0.7);
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
    background: rgba(200, 70, 126, 0.08);
    border-color: rgba(200, 70, 126, 0.22);
}

/* ── Avatar ──────────────────────────────────────────────── */
.user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    background: rgba(200, 70, 126, 0.15);
    border: 1.5px solid rgba(200, 70, 126, 0.5);
    box-shadow: 0 0 10px rgba(200, 70, 126, 0.25);
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
    color: #C8467E;
    line-height: 1;
}

/* ── User name ───────────────────────────────────────────── */
.user-name {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.65);
    font-family: 'Figtree', sans-serif;
    max-width: 160px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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
    padding: 0.3rem 0.85rem;
    border-radius: 20px;
    border: 1px solid rgba(200, 70, 126, 0.45);
    color: #C8467E;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.18s;
    white-space: nowrap;
    background: rgba(200, 70, 126, 0.06);
}
.become-idol-btn:hover {
    background: rgba(200, 70, 126, 0.15);
    border-color: rgba(200, 70, 126, 0.7);
    box-shadow: 0 0 12px rgba(200, 70, 126, 0.25);
}

/* ── Mobile ──────────────────────────────────────────────── */
@media (max-width: 639px) {
    .user-name { display: none; }
    .user-chip { padding: 0.25rem; }
}
</style>
