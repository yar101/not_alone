<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

function logout() {
    router.post(route('admin.logout'));
}

const page = usePage();
const adminUser = computed(() => page.props.auth_admin);
const pendingCount = computed(() => page.props.pending_applications_count ?? 0);
const pendingServicesCount = computed(() => page.props.pending_services_count ?? 0);
const pendingReportsCount = computed(() => page.props.pending_reports_count ?? 0);
const pendingTraitSuggestionsCount = computed(() => page.props.pending_trait_suggestions_count ?? 0);
const pendingInterestSuggestionsCount = computed(() => page.props.pending_interest_suggestions_count ?? 0);
const pendingReviewDisputesCount = computed(() => page.props.pending_review_disputes_count ?? 0);

const servicesOpen      = ref(false);
const traitsOpen        = ref(false);
const interestsOpen     = ref(false);
const contentPacksOpen  = ref(false);
const sidebarOpen       = ref(false);

const component = computed(() => page.component);

function isOnServices() {
    return component.value?.startsWith('Admin/Services/');
}

function isOnTraits() {
    return component.value?.startsWith('Admin/Traits/');
}

function isOnInterests() {
    return component.value?.startsWith('Admin/Interests/');
}

function isOnContentPacks() {
    return component.value?.startsWith('Admin/ContentPacks/');
}

watch(component, (val) => {
    if (val?.startsWith('Admin/Services/')) {
        servicesOpen.value = true;
    }
    if (val?.startsWith('Admin/Traits/')) {
        traitsOpen.value = true;
    }
    if (val?.startsWith('Admin/Interests/')) {
        interestsOpen.value = true;
    }
    if (val?.startsWith('Admin/ContentPacks/')) {
        contentPacksOpen.value = true;
    }
    sidebarOpen.value = false;
}, { immediate: true });

function isActive(routeName) {
    return route().current(routeName);
}
</script>

<template>
    <div class="admin-wrap">
        <div v-if="sidebarOpen" class="sidebar-backdrop" @click="sidebarOpen = false"></div>
        <aside class="sidebar" :class="{ 'sidebar--open': sidebarOpen }">
            <div class="sidebar__logo">
                <span class="logo-brand">NoAlone</span>
                <span class="logo-sub">Admin</span>
            </div>

            <nav class="sidebar__nav">
                <Link
                    :href="route('admin.dashboard')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.dashboard') }"
                >
                    Дашборд
                </Link>

                <Link
                    :href="route('admin.applications.index')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.applications.*') }"
                >
                    Заявки
                    <span v-if="pendingCount > 0" class="nav-badge">{{ pendingCount }}</span>
                </Link>

                <div class="nav-group">
                    <button
                        class="nav-item nav-item--group"
                        :class="{ 'nav-item--active': isOnServices() }"
                        @click="servicesOpen = !servicesOpen"
                    >
                        <span>Услуги</span>
                        <span class="nav-badge" v-if="pendingServicesCount > 0" style="margin-left: auto; margin-right: 0.25rem;">{{ pendingServicesCount }}</span>
                        <span class="nav-arrow" :class="{ 'nav-arrow--open': servicesOpen }">▾</span>
                    </button>
                    <div v-if="servicesOpen" class="nav-sub">
                        <Link
                            :href="route('admin.services.categories.index')"
                            class="nav-item nav-item--sub"
                            :class="{ 'nav-item--active': isActive('admin.services.categories.index') }"
                        >Категории</Link>
                        <Link
                            :href="route('admin.services.time-units.index')"
                            class="nav-item nav-item--sub"
                            :class="{ 'nav-item--active': isActive('admin.services.time-units.index') }"
                        >Ед. времени</Link>
                        <Link
                            :href="route('admin.services.price-limits.index')"
                            class="nav-item nav-item--sub"
                            :class="{ 'nav-item--active': isActive('admin.services.price-limits.index') }"
                        >Лимиты цен</Link>
                        <Link
                            :href="route('admin.services.moderation.index')"
                            class="nav-item nav-item--sub"
                            :class="{ 'nav-item--active': isActive('admin.services.moderation.index') }"
                        >
                            Модерация
                            <span v-if="pendingServicesCount > 0" class="nav-badge">{{ pendingServicesCount }}</span>
                        </Link>
                    </div>
                </div>

                <Link
                    :href="route('admin.quiz.questions.index')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.quiz.*') }"
                >
                    Квиз
                </Link>

                <Link
                    :href="route('admin.messages.index')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.messages.index') }"
                >
                    Рассылки
                </Link>

                <div class="nav-group">
                    <button
                        class="nav-item nav-item--group"
                        :class="{ 'nav-item--active': isOnContentPacks() }"
                        @click="contentPacksOpen = !contentPacksOpen"
                    >
                        <span>Контент-паки</span>
                        <span class="nav-arrow" :class="{ 'nav-arrow--open': contentPacksOpen }">▾</span>
                    </button>
                    <div v-if="contentPacksOpen" class="nav-sub">
                        <Link
                            :href="route('admin.content-packs.index')"
                            class="nav-item nav-item--sub"
                            :class="{ 'nav-item--active': isActive('admin.content-packs.index') || isActive('admin.content-packs.show') }"
                        >Модерация</Link>
                        <Link
                            :href="route('admin.content-packs.change-requests.index')"
                            class="nav-item nav-item--sub"
                            :class="{ 'nav-item--active': isActive('admin.content-packs.change-requests.*') }"
                        >Изменения</Link>
                    </div>
                </div>

                <Link
                    :href="route('admin.users.index')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.users.*') }"
                >
                    Пользователи
                </Link>

                <Link
                    :href="route('admin.orders.index')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.orders.*') }"
                >
                    Заказы
                </Link>

                <Link
                    :href="route('admin.disputes.index')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.disputes.*') }"
                >
                    Споры
                </Link>

                <Link
                    :href="route('admin.review-disputes.index')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.review-disputes.*') }"
                >
                    Отзывы (жалобы)
                    <span v-if="pendingReviewDisputesCount > 0" class="nav-badge" style="margin-left: auto; margin-right: 0.25rem;">{{ pendingReviewDisputesCount }}</span>
                </Link>

                <Link
                    :href="route('admin.support.index')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.support.*') }"
                >
                    Поддержка
                </Link>

                <div class="nav-group">
                    <button
                        class="nav-item nav-item--group"
                        :class="{ 'nav-item--active': isOnTraits() }"
                        @click="traitsOpen = !traitsOpen"
                    >
                        <span>Черты характера</span>
                        <span class="nav-badge" v-if="pendingTraitSuggestionsCount > 0" style="margin-left: auto; margin-right: 0.25rem;">{{ pendingTraitSuggestionsCount }}</span>
                        <span class="nav-arrow" :class="{ 'nav-arrow--open': traitsOpen }">▾</span>
                    </button>
                    <div v-if="traitsOpen" class="nav-sub">
                        <Link
                            :href="route('admin.traits.index')"
                            class="nav-item nav-item--sub"
                            :class="{ 'nav-item--active': isActive('admin.traits.index') }"
                        >Список</Link>
                        <Link
                            :href="route('admin.traits.suggestions.index')"
                            class="nav-item nav-item--sub"
                            :class="{ 'nav-item--active': isActive('admin.traits.suggestions.*') }"
                        >
                            Предложения
                            <span v-if="pendingTraitSuggestionsCount > 0" class="nav-badge">{{ pendingTraitSuggestionsCount }}</span>
                        </Link>
                    </div>
                </div>

                <div class="nav-group">
                    <button
                        class="nav-item nav-item--group"
                        :class="{ 'nav-item--active': isOnInterests() }"
                        @click="interestsOpen = !interestsOpen"
                    >
                        <span>Интересы</span>
                        <span class="nav-badge" v-if="pendingInterestSuggestionsCount > 0" style="margin-left: auto; margin-right: 0.25rem;">{{ pendingInterestSuggestionsCount }}</span>
                        <span class="nav-arrow" :class="{ 'nav-arrow--open': interestsOpen }">▾</span>
                    </button>
                    <div v-if="interestsOpen" class="nav-sub">
                        <Link
                            :href="route('admin.interests.index')"
                            class="nav-item nav-item--sub"
                            :class="{ 'nav-item--active': isActive('admin.interests.index') }"
                        >Список</Link>
                        <Link
                            :href="route('admin.interests.suggestions.index')"
                            class="nav-item nav-item--sub"
                            :class="{ 'nav-item--active': isActive('admin.interests.suggestions.*') }"
                        >
                            Предложения
                            <span v-if="pendingInterestSuggestionsCount > 0" class="nav-badge">{{ pendingInterestSuggestionsCount }}</span>
                        </Link>
                    </div>
                </div>

                <Link
                    :href="route('admin.chat-blocks.index')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.chat-blocks.index') }"
                >
                    Блокировки в чате
                </Link>

                <Link
                    :href="route('admin.ban-reasons.index')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.ban-reasons.index') }"
                >
                    Причины блокировок
                </Link>

                <Link
                    :href="route('admin.review-epithets.index')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.review-epithets.index') }"
                >
                    Отзывы — Эпитеты
                </Link>

                <Link
                    :href="route('admin.news.index')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.news.index') }"
                >
                    Новости
                </Link>

                <Link
                    :href="route('admin.reports.index')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.reports.*') }"
                >
                    Жалобы
                    <span v-if="pendingReportsCount > 0" class="nav-badge">{{ pendingReportsCount }}</span>
                </Link>

                <Link
                    :href="route('admin.logs.index')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.logs.index') }"
                >
                    Логи
                </Link>

                <Link
                    :href="route('admin.rating-logs.index')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.rating-logs.index') }"
                >
                    Рейтинг айдолов
                </Link>

                <Link
                    :href="route('admin.settings.index')"
                    class="nav-item"
                    :class="{ 'nav-item--active': isActive('admin.settings.index') }"
                >
                    Настройки
                </Link>
            </nav>

            <div class="sidebar__footer">
                <div class="admin-info" v-if="adminUser">
                    <div class="admin-avatar">{{ adminUser.name?.[0]?.toUpperCase() ?? 'A' }}</div>
                    <div class="admin-details">
                        <div class="admin-name">{{ adminUser.name }}</div>
                        <div class="admin-email">{{ adminUser.email }}</div>
                    </div>
                </div>
                <button class="logout-btn" @click="logout">Выйти</button>
            </div>
        </aside>

        <div class="content-wrap">
            <header class="admin-mobile-header">
                <button class="hamburger-btn" @click="sidebarOpen = !sidebarOpen">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <span class="admin-mobile-title">Admin</span>
            </header>
            <main class="admin-main">
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
.admin-wrap {
    height: 100vh;
    background: #07070f;
    display: flex;
    overflow: hidden;
    font-family: 'Rubik', sans-serif;
}

/* Sidebar */
.sidebar {
    width: 220px;
    flex-shrink: 0;
    background: #09090f;
    border-right: 1px solid rgba(155, 110, 232, 0.2);
    display: flex;
    flex-direction: column;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
}

.sidebar__logo {
    padding: 1.25rem 1rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.logo-brand {
    font-family: 'Imbue', serif;
    font-size: 1rem;
    color: #9B6EE8;
    letter-spacing: 0.02em;
}

.logo-sub {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.25);
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

/* Nav */
.sidebar__nav {
    flex: 1;
    padding: 0.5rem 0;
    display: flex;
    flex-direction: column;
}

.nav-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.55rem 1rem;
    color: rgba(255, 255, 255, 0.45);
    text-decoration: none;
    font-size: 0.85rem;
    transition: color 0.15s, background 0.15s;
    cursor: pointer;
    background: none;
    border: none;
    width: 100%;
    text-align: left;
    font-family: inherit;
    gap: 0.5rem;
}

.nav-item:hover {
    color: rgba(255, 255, 255, 0.85);
    background: rgba(255, 255, 255, 0.04);
}

.nav-item--active {
    color: #9B6EE8;
    background: rgba(155, 110, 232, 0.1);
}

.nav-item--group {
    justify-content: space-between;
}

.nav-arrow {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.25);
    transition: transform 0.2s;
    display: inline-block;
}

.nav-arrow--open {
    transform: rotate(180deg);
}

.nav-sub {
    display: flex;
    flex-direction: column;
}

.nav-item--sub {
    padding-left: 1.75rem;
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.35);
}

.nav-item--sub:hover {
    color: rgba(255, 255, 255, 0.75);
}

.nav-item--sub.nav-item--active {
    color: rgba(190, 145, 255, 0.85);
    background: rgba(155, 110, 232, 0.08);
}

.nav-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 0.3rem;
    background: rgba(155, 110, 232, 0.25);
    border: 1px solid rgba(155, 110, 232, 0.4);
    color: #be91ff;
    font-size: 0.68rem;
    font-weight: 700;
    border-radius: 99px;
    margin-left: auto;
}

/* Footer */
.sidebar__footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.admin-info {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    min-width: 0;
}

.admin-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgba(155, 110, 232, 0.2);
    border: 1px solid rgba(155, 110, 232, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
    color: #9B6EE8;
    flex-shrink: 0;
}

.admin-details {
    min-width: 0;
    flex: 1;
}

.admin-name {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.7);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-email {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.25);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.logout-btn {
    padding: 0.35rem 0.7rem;
    border: 1px solid rgba(155, 110, 232, 0.25);
    background: transparent;
    color: rgba(255, 255, 255, 0.35);
    font-size: 0.78rem;
    cursor: pointer;
    font-family: inherit;
    text-align: center;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
}

.logout-btn:hover {
    border-color: rgba(155, 110, 232, 0.5);
    color: rgba(255, 255, 255, 0.7);
    background: rgba(155, 110, 232, 0.07);
}

/* Content */
.content-wrap {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.admin-main {
    flex: 1;
    padding: 2rem;
    width: 100%;
    min-width: 0;
    overflow-y: auto;
}

/* ── Mobile header ──────────────────────────────────────── */
.admin-mobile-header {
    display: none;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(155, 110, 232, 0.15);
    background: #09090f;
    flex-shrink: 0;
}
.admin-mobile-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.7);
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.hamburger-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: none;
    background: transparent;
    border-radius: 6px;
    color: rgba(255, 255, 255, 0.55);
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
}
.hamburger-btn:hover {
    color: rgba(255, 255, 255, 0.9);
    background: rgba(155, 110, 232, 0.12);
}

/* ── Mobile responsive ──────────────────────────────────── */
.sidebar-backdrop {
    display: none;
}

@media (max-width: 768px) {
    .admin-wrap { overflow: visible; height: auto; min-height: 100vh; }

    .admin-mobile-header { display: flex; }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100%;
        z-index: 1200;
        transform: translateX(-100%);
        transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .sidebar--open { transform: translateX(0); }

    .sidebar-backdrop {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1199;
    }

    .content-wrap { flex-direction: column; }
    .admin-main { padding: 1rem; overflow-y: visible; }
}
</style>
