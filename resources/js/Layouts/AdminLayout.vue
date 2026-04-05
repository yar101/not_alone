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

const servicesOpen  = ref(false);
const traitsOpen    = ref(false);
const interestsOpen = ref(false);

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
}, { immediate: true });

function isActive(routeName) {
    return route().current(routeName);
}
</script>

<template>
    <div class="admin-wrap">
        <aside class="sidebar">
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
    font-family: 'Figtree', sans-serif;
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
</style>
