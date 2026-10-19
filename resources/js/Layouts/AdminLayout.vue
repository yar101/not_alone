<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ElNotification } from 'element-plus';
import axios from 'axios';
import {
    Odometer, Warning, List, ShoppingBag, Aim, Message,
    Picture, User, ChatDotRound, Tickets, ScaleToOriginal,
    Microphone, Headset, Star, Discount, Lock, ChatSquare,
    Document, QuestionFilled, WarningFilled, Monitor,
    TrendCharts, Setting, RefreshRight, Help, Coin, Search
} from '@element-plus/icons-vue';

function logout() {
    router.post(route('admin.logout'));
}

const page = usePage();
const adminUser = computed(() => page.props.auth_admin);
const pendingCount = computed(() => page.props.pending_applications_count ?? 0);
const pendingServicesCount = computed(() => page.props.pending_services_count ?? 0);
const pendingServiceChangesCount = computed(() => page.props.pending_service_changes_count ?? 0);
const pendingContentPacksCount = computed(() => page.props.pending_content_packs_count ?? 0);
const pendingContentPackChangesCount = computed(() => page.props.pending_content_pack_changes_count ?? 0);
const pendingReportsCount = computed(() => page.props.pending_reports_count ?? 0);
const pendingDisputesCount = computed(() => page.props.pending_disputes_count ?? 0);
const pendingTraitSuggestionsCount = computed(() => page.props.pending_trait_suggestions_count ?? 0);
const pendingInterestSuggestionsCount = computed(() => page.props.pending_interest_suggestions_count ?? 0);
const pendingReviewDisputesCount = computed(() => page.props.pending_review_disputes_count ?? 0);

// Global Toast Notifications
let lastShownFlashKey = null;

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) {
            lastShownFlashKey = null;
            return;
        }

        const hasMessage = Boolean(flash.success || flash.error || flash.warning || flash.info);
        if (!hasMessage) {
            lastShownFlashKey = null;
            return;
        }

        const flashKey = flash.token || `${flash.success || ''}|${flash.error || ''}|${flash.warning || ''}|${flash.info || ''}`;
        if (lastShownFlashKey === flashKey) {
            return;
        }
        lastShownFlashKey = flashKey;

        if (flash.success) {
            ElNotification.success({
                title: 'Успешно',
                message: flash.success,
                duration: 4000,
            });
        }
        if (flash.error) {
            ElNotification.error({
                title: 'Ошибка',
                message: flash.error,
                duration: 6000,
            });
        }
        if (flash.warning) {
            ElNotification.warning({
                title: 'Внимание',
                message: flash.warning,
                duration: 5000,
            });
        }
        if (flash.info) {
            ElNotification.info({
                title: 'Информация',
                message: flash.info,
                duration: 4000,
            });
        }
    },
    { deep: true, immediate: true }
);

let lastShownErrorsKey = null;

watch(
    () => page.props.errors,
    (errors) => {
        if (!errors || Object.keys(errors).length === 0) {
            lastShownErrorsKey = null;
            return;
        }
        const errorsKey = JSON.stringify(errors);
        if (lastShownErrorsKey === errorsKey) return;
        lastShownErrorsKey = errorsKey;

        const firstError = Object.values(errors)[0];
        if (firstError) {
            ElNotification.error({
                title: 'Ошибка валидации',
                message: typeof firstError === 'string' ? firstError : JSON.stringify(firstError),
                duration: 6000,
            });
        }
    },
    { deep: true }
);

const servicesOpen      = ref(false);
const traitsOpen        = ref(false);
const interestsOpen     = ref(false);
const contentPacksOpen  = ref(false);
const quizOpen          = ref(false);
const sidebarOpen       = ref(false);

const component = computed(() => page.component);

const sectionTitle = computed(() => {
    const c = component.value || '';
    if (c.startsWith('Admin/Dashboard')) return 'Дашборд';
    if (c.startsWith('Admin/Applications')) return 'Заявки на айдола';
    if (c.startsWith('Admin/Services/Categories')) return 'Категории услуг';
    if (c.startsWith('Admin/Services/TimeUnits')) return 'Единицы времени';
    if (c.startsWith('Admin/Services/PriceLimits')) return 'Лимиты цен';
    if (c.startsWith('Admin/Services/ChangeRequests')) return 'Запросы на изменение услуг';
    if (c.startsWith('Admin/Services')) return 'Модерация услуг';
    if (c.startsWith('Admin/ContentPacks/ChangeRequest')) return 'Запросы на изменение паков';
    if (c.startsWith('Admin/ContentPacks')) return 'Контент-паки';
    if (c.startsWith('Admin/Orders')) return 'Заказы';
    if (c.startsWith('Admin/Transactions')) return 'Транзакции';
    if (c.startsWith('Admin/Users')) return 'Пользователи';
    if (c.startsWith('Admin/Conversations')) return 'Переписки';
    if (c.startsWith('Admin/Strikes')) return 'Страйки';
    if (c.startsWith('Admin/Disputes')) return 'Споры по заказам';
    if (c.startsWith('Admin/ReviewDisputes')) return 'Споры по отзывам';
    if (c.startsWith('Admin/Reports')) return 'Жалобы пользователей';
    if (c.startsWith('Admin/Support')) return 'Техническая поддержка';
    if (c.startsWith('Admin/ChatBlocks')) return 'Блокировки в чате';
    if (c.startsWith('Admin/AvatarFrames')) return 'Рамки аватарок';
    if (c.startsWith('Admin/Quiz')) return 'Квиз для айдолов';
    if (c.startsWith('Admin/Traits')) return 'Черты характера';
    if (c.startsWith('Admin/Interests')) return 'Интересы';
    if (c.startsWith('Admin/BanReasons')) return 'Причины блокировок';
    if (c.startsWith('Admin/ReviewEpithets')) return 'Эпитеты отзывов';
    if (c.startsWith('Admin/Help')) return 'Справочный центр';
    if (c.startsWith('Admin/Messages')) return 'Массовые рассылки';
    if (c.startsWith('Admin/SystemLogs')) return 'Системные логи';
    if (c.startsWith('Admin/Logs')) return 'Журнал действий администраторов';
    if (c.startsWith('Admin/RatingLogs')) return 'История рейтинга';
    if (c.startsWith('Admin/Settings')) return 'Настройки платформы';
    return 'Панель управления';
});

const totalAttentionCount = computed(() => {
    return (
        pendingCount.value +
        pendingServicesCount.value +
        pendingServiceChangesCount.value +
        pendingContentPacksCount.value +
        pendingContentPackChangesCount.value +
        pendingReportsCount.value +
        pendingDisputesCount.value +
        pendingReviewDisputesCount.value +
        pendingTraitSuggestionsCount.value +
        pendingInterestSuggestionsCount.value
    );
});

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

function isOnQuiz() {
    return component.value?.startsWith('Admin/Quiz/');
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
    if (val?.startsWith('Admin/Quiz/')) {
        quizOpen.value = true;
    }
    sidebarOpen.value = false;
}, { immediate: true });

function isActive(routeName) {
    return route().current(routeName);
}

// ─── Quick Search (Command Palette) ──────────────────────────────────────────
const quickSearchOpen = ref(false);
const quickSearchQuery = ref('');
const quickSearchLoading = ref(false);
const quickSearchResults = ref({
    users: [],
    orders: [],
    packs: [],
    transactions: [],
});
const searchInputRef = ref(null);
let searchDebounceTimer = null;

const allResultsFlat = computed(() => {
    const list = [];
    (quickSearchResults.value.users || []).forEach(item => list.push({ ...item, group: 'users' }));
    (quickSearchResults.value.orders || []).forEach(item => list.push({ ...item, group: 'orders' }));
    (quickSearchResults.value.packs || []).forEach(item => list.push({ ...item, group: 'packs' }));
    (quickSearchResults.value.transactions || []).forEach(item => list.push({ ...item, group: 'transactions' }));
    return list;
});
const selectedIndex = ref(0);

const hasResults = computed(() => {
    return allResultsFlat.value.length > 0;
});

function openQuickSearch() {
    quickSearchOpen.value = true;
    quickSearchQuery.value = '';
    quickSearchResults.value = { users: [], orders: [], packs: [], transactions: [] };
    selectedIndex.value = 0;
    nextTick(() => {
        searchInputRef.value?.focus();
    });
}

function closeQuickSearch() {
    quickSearchOpen.value = false;
}

async function performQuickSearch() {
    const q = quickSearchQuery.value.trim();
    if (!q || (q.length < 2 && !/^\d+$/.test(q))) {
        quickSearchResults.value = { users: [], orders: [], packs: [], transactions: [] };
        quickSearchLoading.value = false;
        return;
    }

    quickSearchLoading.value = true;
    try {
        const { data } = await axios.get('/admin/quick-search', { params: { q } });
        quickSearchResults.value = data;
        selectedIndex.value = 0;
    } catch (err) {
        console.error('Quick search error:', err);
    } finally {
        quickSearchLoading.value = false;
    }
}

function onQuickSearchInput() {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(performQuickSearch, 200);
}

function navigateToItem(item) {
    if (item?.url) {
        closeQuickSearch();
        router.visit(item.url);
    }
}

function navigateToSelected() {
    const item = allResultsFlat.value[selectedIndex.value];
    navigateToItem(item);
}

function onSearchKeydown(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (allResultsFlat.value.length > 0) {
            selectedIndex.value = (selectedIndex.value + 1) % allResultsFlat.value.length;
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (allResultsFlat.value.length > 0) {
            selectedIndex.value = (selectedIndex.value - 1 + allResultsFlat.value.length) % allResultsFlat.value.length;
        }
    } else if (e.key === 'Enter') {
        e.preventDefault();
        navigateToSelected();
    } else if (e.key === 'Escape') {
        e.preventDefault();
        closeQuickSearch();
    }
}

function onGlobalKeyDown(e) {
    if (e.key === 'Escape' && quickSearchOpen.value) {
        e.preventDefault();
        closeQuickSearch();
        return;
    }

    if ((e.metaKey || e.ctrlKey) && (e.key?.toLowerCase() === 'k' || e.code === 'KeyK')) {
        e.preventDefault();
        if (quickSearchOpen.value) {
            closeQuickSearch();
        } else {
            openQuickSearch();
        }
    }
}

onMounted(() => {
    window.addEventListener('keydown', onGlobalKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onGlobalKeyDown);
});
</script>

<template>
    <div class="admin-wrap">
        <div v-if="sidebarOpen" class="sidebar-backdrop" @click="sidebarOpen = false"></div>
        <aside class="sidebar" :class="{ 'sidebar--open': sidebarOpen }">
            <div class="sidebar__logo">
                <img src="/app-logo-v3.webp" alt="Not Alone" class="sidebar__logo-img" />
                <span class="logo-sub">Admin</span>
            </div>

            <nav class="sidebar__nav">
                <!-- Основное -->
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Основное</div>
                    
                    <Link
                        :href="route('admin.dashboard')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.dashboard') }"
                    >
                        <el-icon class="nav-icon"><Odometer /></el-icon>
                        <span class="nav-text">Дашборд</span>
                    </Link>

                    <Link
                        :href="route('admin.applications.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.applications.*') }"
                    >
                        <el-icon class="nav-icon"><List /></el-icon>
                        <span class="nav-text">Заявки</span>
                        <span v-if="pendingCount > 0" class="nav-badge">{{ pendingCount }}</span>
                    </Link>
                </div>

                <!-- Коммерция и Контент -->
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Коммерция и Контент</div>

                    <div class="nav-group">
                        <button
                            class="nav-item nav-item--group"
                            :class="{ 'nav-item--active': isOnServices() }"
                            @click="servicesOpen = !servicesOpen"
                        >
                            <el-icon class="nav-icon"><ShoppingBag /></el-icon>
                            <span class="nav-text">Услуги</span>
                            <span class="nav-badge" v-if="(pendingServicesCount + pendingServiceChangesCount) > 0" style="margin-left: auto; margin-right: 0.25rem;">{{ pendingServicesCount + pendingServiceChangesCount }}</span>
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
                            <Link
                                :href="route('admin.services.change-requests.index')"
                                class="nav-item nav-item--sub"
                                :class="{ 'nav-item--active': isActive('admin.services.change-requests.*') }"
                            >
                                Изменения
                                <span v-if="pendingServiceChangesCount > 0" class="nav-badge">{{ pendingServiceChangesCount }}</span>
                            </Link>
                        </div>
                    </div>

                    <div class="nav-group">
                        <button
                            class="nav-item nav-item--group"
                            :class="{ 'nav-item--active': isOnContentPacks() }"
                            @click="contentPacksOpen = !contentPacksOpen"
                        >
                            <el-icon class="nav-icon"><Picture /></el-icon>
                            <span class="nav-text">Контент-паки</span>
                            <span class="nav-badge" v-if="(pendingContentPacksCount + pendingContentPackChangesCount) > 0" style="margin-left: auto; margin-right: 0.25rem;">{{ pendingContentPacksCount + pendingContentPackChangesCount }}</span>
                            <span class="nav-arrow" :class="{ 'nav-arrow--open': contentPacksOpen }">▾</span>
                        </button>
                        <div v-if="contentPacksOpen" class="nav-sub">
                            <Link
                                :href="route('admin.content-packs.index')"
                                class="nav-item nav-item--sub"
                                :class="{ 'nav-item--active': isActive('admin.content-packs.index') || isActive('admin.content-packs.show') }"
                            >
                                Модерация
                                <span v-if="pendingContentPacksCount > 0" class="nav-badge">{{ pendingContentPacksCount }}</span>
                            </Link>
                            <Link
                                :href="route('admin.content-packs.change-requests.index')"
                                class="nav-item nav-item--sub"
                                :class="{ 'nav-item--active': isActive('admin.content-packs.change-requests.*') }"
                            >
                                Изменения
                                <span v-if="pendingContentPackChangesCount > 0" class="nav-badge">{{ pendingContentPackChangesCount }}</span>
                            </Link>
                        </div>
                    </div>

                    <Link
                        :href="route('admin.orders.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.orders.*') }"
                    >
                        <el-icon class="nav-icon"><Tickets /></el-icon>
                        <span class="nav-text">Заказы</span>
                    </Link>

                    <Link
                        :href="route('admin.transactions.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.transactions.*') }"
                    >
                        <el-icon class="nav-icon"><Coin /></el-icon>
                        <span class="nav-text">Транзакции</span>
                    </Link>
                </div>

                <!-- Пользователи и Модерация -->
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Пользователи и Модерация</div>

                    <Link
                        :href="route('admin.users.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.users.*') }"
                    >
                        <el-icon class="nav-icon"><User /></el-icon>
                        <span class="nav-text">Пользователи</span>
                    </Link>

                    <Link
                        :href="route('admin.conversations.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.conversations.*') }"
                    >
                        <el-icon class="nav-icon"><ChatDotRound /></el-icon>
                        <span class="nav-text">Переписки</span>
                    </Link>

                    <Link :href="route('admin.strikes.index')" class="nav-item" :class="{ active: $page.component.startsWith('Admin/Strikes') }">
                        <el-icon class="nav-icon"><Warning /></el-icon>
                        <span class="nav-text">Страйки</span>
                    </Link>

                    <Link
                        :href="route('admin.disputes.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.disputes.*') }"
                    >
                        <el-icon class="nav-icon"><ScaleToOriginal /></el-icon>
                        <span class="nav-text">Споры</span>
                        <span v-if="pendingDisputesCount > 0" class="nav-badge">{{ pendingDisputesCount }}</span>
                    </Link>

                    <Link
                        :href="route('admin.review-disputes.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.review-disputes.*') }"
                    >
                        <el-icon class="nav-icon"><Microphone /></el-icon>
                        <span class="nav-text">Отзывы</span>
                        <span v-if="pendingReviewDisputesCount > 0" class="nav-badge" style="margin-left: auto; margin-right: 0.25rem;">{{ pendingReviewDisputesCount }}</span>
                    </Link>

                    <Link
                        :href="route('admin.reports.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.reports.*') }"
                    >
                        <el-icon class="nav-icon"><WarningFilled /></el-icon>
                        <span class="nav-text">Жалобы</span>
                        <span v-if="pendingReportsCount > 0" class="nav-badge">{{ pendingReportsCount }}</span>
                    </Link>

                    <Link
                        :href="route('admin.support.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.support.*') }"
                    >
                        <el-icon class="nav-icon"><Headset /></el-icon>
                        <span class="nav-text">Поддержка</span>
                    </Link>

                    <Link
                        :href="route('admin.chat-blocks.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.chat-blocks.index') }"
                    >
                        <el-icon class="nav-icon"><Lock /></el-icon>
                        <span class="nav-text">Блокировки в чате</span>
                    </Link>
                </div>

                <!-- Наполнение -->
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Наполнение</div>

                    <Link
                        :href="route('admin.avatar-frames.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.avatar-frames.*') }"
                    >
                        <el-icon class="nav-icon"><Picture /></el-icon>
                        <span class="nav-text">Рамки аватарок</span>
                    </Link>

                    <div class="nav-group">
                        <button
                            class="nav-item nav-item--group"
                            :class="{ 'nav-item--active': isOnQuiz() }"
                            @click="quizOpen = !quizOpen"
                        >
                            <el-icon class="nav-icon"><Aim /></el-icon>
                            <span class="nav-text">Квиз</span>
                            <span class="nav-arrow" :class="{ 'nav-arrow--open': quizOpen }">▾</span>
                        </button>
                        <div v-if="quizOpen" class="nav-sub">
                            <Link
                                :href="route('admin.quiz.questions.index')"
                                class="nav-item nav-item--sub"
                                :class="{ 'nav-item--active': isActive('admin.quiz.questions.index') }"
                            >Вопросы</Link>
                            <Link
                                :href="route('admin.quiz.article.index')"
                                class="nav-item nav-item--sub"
                                :class="{ 'nav-item--active': isActive('admin.quiz.article.index') }"
                            >Статья о вступлении</Link>
                        </div>
                    </div>

                    <div class="nav-group">
                        <button
                            class="nav-item nav-item--group"
                            :class="{ 'nav-item--active': isOnTraits() }"
                            @click="traitsOpen = !traitsOpen"
                        >
                            <el-icon class="nav-icon"><Star /></el-icon>
                            <span class="nav-text">Черты характера</span>
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
                            <el-icon class="nav-icon"><Discount /></el-icon>
                            <span class="nav-text">Интересы</span>
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
                        :href="route('admin.ban-reasons.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.ban-reasons.index') }"
                    >
                        <el-icon class="nav-icon"><Lock /></el-icon>
                        <span class="nav-text">Причины блокировок</span>
                    </Link>

                    <Link
                        :href="route('admin.review-epithets.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.review-epithets.index') }"
                    >
                        <el-icon class="nav-icon"><ChatSquare /></el-icon>
                        <span class="nav-text">Отзывы — Эпитеты</span>
                    </Link>

                    <Link
                        :href="route('admin.help-categories.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.help-categories.*') }"
                    >
                        <el-icon class="nav-icon"><QuestionFilled /></el-icon>
                        <span class="nav-text">Справка</span>
                    </Link>
                </div>

                <!-- Система -->
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Система</div>

                    <Link
                        :href="route('admin.messages.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.messages.index') }"
                    >
                        <el-icon class="nav-icon"><Message /></el-icon>
                        <span class="nav-text">Рассылки</span>
                    </Link>

                    <Link
                        :href="route('admin.logs.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.logs.index') }"
                    >
                        <el-icon class="nav-icon"><Monitor /></el-icon>
                        <span class="nav-text">Действия админов</span>
                    </Link>

                    <Link
                        :href="route('admin.system-logs.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.system-logs.index') }"
                    >
                        <el-icon class="nav-icon"><Document /></el-icon>
                        <span class="nav-text">Логи системы</span>
                    </Link>

                    <Link
                        :href="route('admin.rating-logs.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.rating-logs.index') }"
                    >
                        <el-icon class="nav-icon"><TrendCharts /></el-icon>
                        <span class="nav-text">Рейтинг айдолов</span>
                    </Link>

                    <Link
                        :href="route('admin.settings.index')"
                        class="nav-item"
                        :class="{ 'nav-item--active': isActive('admin.settings.index') }"
                    >
                        <el-icon class="nav-icon"><Setting /></el-icon>
                        <span class="nav-text">Настройки</span>
                    </Link>
                </div>
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
            <header class="admin-topbar">
                <div class="topbar-left">
                    <button class="hamburger-btn" @click="sidebarOpen = !sidebarOpen" title="Меню">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                        </svg>
                    </button>
                    <div class="topbar-breadcrumbs">
                        <span class="crumb-root">Админ</span>
                        <span class="crumb-separator">/</span>
                        <span class="crumb-current">{{ sectionTitle }}</span>
                    </div>
                </div>

                <div class="topbar-center">
                    <button class="topbar-search-trigger" @click="openQuickSearch" title="Быстрый поиск (Ctrl+K)">
                        <el-icon class="search-trigger-icon"><Search /></el-icon>
                        <span class="search-trigger-text">Быстрый поиск...</span>
                        <kbd class="search-trigger-kbd">Ctrl K</kbd>
                    </button>
                </div>

                <div class="topbar-right">
                    <div v-if="totalAttentionCount > 0" class="attention-pill" title="Ожидают модерации или решения">
                        <span class="attention-dot"></span>
                        <span class="attention-text">В очереди: {{ totalAttentionCount }}</span>
                    </div>

                    <a href="/" target="_blank" rel="noopener" class="topbar-site-link">
                        <span>На сайт</span>
                        <span class="external-icon">↗</span>
                    </a>

                    <el-dropdown trigger="click" v-if="adminUser" placement="bottom-end">
                        <div class="topbar-admin-badge topbar-admin-badge--interactive">
                            <div class="admin-avatar-mini">{{ adminUser.name?.[0]?.toUpperCase() ?? 'A' }}</div>
                            <span class="admin-name-mini">{{ adminUser.name }}</span>
                            <span class="admin-chevron">▾</span>
                        </div>
                        <template #dropdown>
                            <el-dropdown-menu class="admin-topbar-menu">
                                <div class="admin-menu-header">
                                    <div class="admin-menu-name">{{ adminUser.name }}</div>
                                    <div class="admin-menu-email">{{ adminUser.email }}</div>
                                </div>
                                <el-dropdown-item @click="router.visit(route('admin.settings.index'))">
                                    <el-icon><Setting /></el-icon> Настройки платформы
                                </el-dropdown-item>
                                <el-dropdown-item @click="router.visit(route('admin.logs.index'))">
                                    <el-icon><Document /></el-icon> Журнал действий
                                </el-dropdown-item>
                                <el-dropdown-item @click="router.visit(route('admin.system-logs.index'))">
                                    <el-icon><Monitor /></el-icon> Системные логи
                                </el-dropdown-item>
                                <el-dropdown-item divided @click="logout" class="menu-logout-item">
                                    <el-icon><Lock /></el-icon> Выйти из панели
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </div>
            </header>

            <main class="admin-main">
                <slot />
            </main>
        </div>

        <!-- Command Palette / Quick Search Modal -->
        <Teleport to="body">
            <div v-if="quickSearchOpen" class="cmd-palette-backdrop" @click.self="closeQuickSearch">
                <div class="cmd-palette">
                    <div class="cmd-palette__input-wrap">
                        <el-icon class="cmd-palette__icon"><Search /></el-icon>
                        <input
                            ref="searchInputRef"
                            v-model="quickSearchQuery"
                            type="text"
                            class="cmd-palette__input"
                            placeholder="Поиск пользователей, заказов, паков, транзакций..."
                            @input="onQuickSearchInput"
                            @keydown="onSearchKeydown"
                        />
                        <button class="cmd-palette__close" @click="closeQuickSearch">Esc</button>
                    </div>

                    <div v-if="quickSearchLoading" class="cmd-palette__loading">
                        Поиск по платформе...
                    </div>

                    <div v-else-if="quickSearchQuery.length >= 2 && !hasResults" class="cmd-palette__empty">
                        Ничего не найдено по запросу «{{ quickSearchQuery }}»
                    </div>

                    <div v-else-if="hasResults" class="cmd-palette__results">
                        <!-- Users -->
                        <div v-if="quickSearchResults.users?.length" class="cmd-palette__group">
                            <div class="cmd-palette__group-title">Пользователи</div>
                            <div
                                v-for="item in quickSearchResults.users"
                                :key="'user-' + item.id"
                                class="cmd-palette__item"
                                :class="{ 'cmd-palette__item--selected': allResultsFlat[selectedIndex]?.group === 'users' && allResultsFlat[selectedIndex]?.id === item.id }"
                                @click="navigateToItem(item)"
                            >
                                <div class="item-avatar-mini" v-if="item.avatar_url">
                                    <img :src="item.avatar_url" alt="" />
                                </div>
                                <div class="item-avatar-mini item-avatar-mini--empty" v-else>
                                    {{ item.title.charAt(0) }}
                                </div>
                                <div class="item-info">
                                    <div class="item-title">{{ item.title }}</div>
                                    <div class="item-sub">{{ item.subtitle }}</div>
                                </div>
                                <span class="item-badge" v-if="item.badge">{{ item.badge }}</span>
                            </div>
                        </div>

                        <!-- Orders -->
                        <div v-if="quickSearchResults.orders?.length" class="cmd-palette__group">
                            <div class="cmd-palette__group-title">Заказы</div>
                            <div
                                v-for="item in quickSearchResults.orders"
                                :key="'order-' + item.id"
                                class="cmd-palette__item"
                                :class="{ 'cmd-palette__item--selected': allResultsFlat[selectedIndex]?.group === 'orders' && allResultsFlat[selectedIndex]?.id === item.id }"
                                @click="navigateToItem(item)"
                            >
                                <div class="item-info">
                                    <div class="item-title">{{ item.title }}</div>
                                    <div class="item-sub">{{ item.subtitle }}</div>
                                </div>
                                <span class="item-badge item-badge--order" v-if="item.badge">{{ item.badge }}</span>
                            </div>
                        </div>

                        <!-- Packs -->
                        <div v-if="quickSearchResults.packs?.length" class="cmd-palette__group">
                            <div class="cmd-palette__group-title">Контент-паки</div>
                            <div
                                v-for="item in quickSearchResults.packs"
                                :key="'pack-' + item.id"
                                class="cmd-palette__item"
                                :class="{ 'cmd-palette__item--selected': allResultsFlat[selectedIndex]?.group === 'packs' && allResultsFlat[selectedIndex]?.id === item.id }"
                                @click="navigateToItem(item)"
                            >
                                <div class="item-info">
                                    <div class="item-title">{{ item.title }}</div>
                                    <div class="item-sub">{{ item.subtitle }}</div>
                                </div>
                                <span class="item-badge" v-if="item.badge">{{ item.badge }}</span>
                            </div>
                        </div>

                        <!-- Transactions -->
                        <div v-if="quickSearchResults.transactions?.length" class="cmd-palette__group">
                            <div class="cmd-palette__group-title">Транзакции</div>
                            <div
                                v-for="item in quickSearchResults.transactions"
                                :key="'tx-' + item.id"
                                class="cmd-palette__item"
                                :class="{ 'cmd-palette__item--selected': allResultsFlat[selectedIndex]?.group === 'transactions' && allResultsFlat[selectedIndex]?.id === item.id }"
                                @click="navigateToItem(item)"
                            >
                                <div class="item-info">
                                    <div class="item-title">{{ item.title }}</div>
                                    <div class="item-sub">{{ item.subtitle }}</div>
                                </div>
                                <span class="item-badge" v-if="item.badge">{{ item.badge }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="cmd-palette__footer">
                        <span>↑↓ Навигация</span>
                        <span>↵ Выбрать</span>
                        <span>Esc Закрыть</span>
                    </div>
                </div>
            </div>
        </Teleport>
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
    gap: 0.25rem;
}

.sidebar__logo-img {
    height: 20px;
    width: auto;
    object-fit: contain;
    align-self: flex-start;
}

.logo-sub {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.25);
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

/* Nav */

.sidebar-section {
    display: flex;
    flex-direction: column;
    margin-bottom: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}
.sidebar-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}
.sidebar-section-title {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255, 255, 255, 0.25);
    padding: 0.5rem 1rem 0.25rem;
    font-weight: 600;
}
.nav-icon {
    font-size: 1.05rem;
    width: 20px;
    display: flex;
    justify-content: center;
    color: rgba(255, 255, 255, 0.4);
    transition: color 0.15s;
}
.nav-text {
    flex: 1;
}
.nav-item--active .nav-icon {
    color: #9B6EE8;
}
.nav-item:hover .nav-icon {
    color: rgba(255, 255, 255, 0.7);
}

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
    color: #ffb2ef;
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

/* ── Topbar ─────────────────────────────────────────────── */
.admin-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1.75rem;
    background: #090912;
    border-bottom: 1px solid rgba(155, 110, 232, 0.15);
    flex-shrink: 0;
    gap: 1rem;
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.topbar-breadcrumbs {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.85rem;
}

.crumb-root {
    color: rgba(255, 255, 255, 0.35);
    font-weight: 500;
}

.crumb-separator {
    color: rgba(255, 255, 255, 0.2);
}

.crumb-current {
    color: #fff;
    font-weight: 600;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.attention-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.3rem 0.7rem;
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #fbbf24;
}

.attention-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #fbbf24;
    box-shadow: 0 0 8px #fbbf24;
    animation: pulse-dot 2s infinite ease-in-out;
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.85); }
}

.topbar-site-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.32rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 500;
    color: rgba(190, 145, 255, 0.8);
    background: rgba(155, 110, 232, 0.08);
    border: 1px solid rgba(155, 110, 232, 0.25);
    text-decoration: none;
    transition: all 0.15s ease;
}

.topbar-site-link:hover {
    color: #fff;
    background: rgba(155, 110, 232, 0.2);
    border-color: rgba(155, 110, 232, 0.5);
}

.external-icon {
    font-size: 0.85rem;
}

.topbar-admin-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-left: 0.5rem;
    border-left: 1px solid rgba(255, 255, 255, 0.08);
}

.admin-avatar-mini {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: rgba(155, 110, 232, 0.25);
    border: 1px solid rgba(155, 110, 232, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    color: #c084fc;
}

.admin-name-mini {
    font-size: 0.8rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.75);
}

.topbar-admin-badge--interactive {
    cursor: pointer;
    user-select: none;
    transition: background 0.15s, border-color 0.15s;
    padding: 0.25rem 0.6rem 0.25rem 0.4rem;
    border-radius: 8px;
    border: 1px solid transparent;
}
.topbar-admin-badge--interactive:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.1);
}

.admin-chevron {
    font-size: 0.65rem;
    color: rgba(255, 255, 255, 0.4);
    margin-left: 2px;
}

:deep(.admin-topbar-menu) {
    background: #14131d !important;
    border: 1px solid rgba(255, 255, 255, 0.14) !important;
    border-radius: 10px !important;
    padding: 6px 0 !important;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.6) !important;
}

.admin-menu-header {
    padding: 8px 14px 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    margin-bottom: 4px;
}
.admin-menu-name {
    font-size: 0.86rem;
    font-weight: 600;
    color: #fff;
}
.admin-menu-email {
    font-size: 0.74rem;
    color: rgba(255, 255, 255, 0.45);
    margin-top: 2px;
}
:deep(.menu-logout-item) {
    color: #f87171 !important;
}
:deep(.menu-logout-item:hover) {
    background: rgba(239, 68, 68, 0.15) !important;
    color: #ef4444 !important;
}

.hamburger-btn {
    display: none;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border: none;
    background: transparent;
    border-radius: 6px;
    color: rgba(255, 255, 255, 0.65);
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
}

.hamburger-btn:hover {
    color: rgba(255, 255, 255, 0.95);
    background: rgba(155, 110, 232, 0.15);
}

/* ── Mobile responsive ──────────────────────────────────── */
.sidebar-backdrop {
    display: none;
}

@media (max-width: 768px) {
    .admin-wrap { overflow: visible; height: auto; min-height: 100vh; }

    .hamburger-btn { display: flex; }
    .crumb-root, .crumb-separator { display: none; }
    .topbar-admin-badge { display: none; }
    .admin-topbar { padding: 0.75rem 1rem; }

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
    .topbar-center { display: none; }
}

/* ── Command Palette (Quick Search) ─────────────────────── */
.topbar-center {
    display: flex;
    align-items: center;
    flex: 1;
    max-width: 380px;
    margin: 0 1.5rem;
}

.topbar-search-trigger {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.4rem 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.82rem;
    cursor: pointer;
    transition: all 0.2s;
}
.topbar-search-trigger:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(155, 110, 232, 0.4);
    color: rgba(255, 255, 255, 0.7);
}
.search-trigger-icon {
    font-size: 0.95rem;
}
.search-trigger-text {
    flex: 1;
    text-align: left;
}
.search-trigger-kbd {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 4px;
    padding: 0.1rem 0.4rem;
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.6);
    font-family: inherit;
}

.cmd-palette-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: flex;
    justify-content: center;
    padding-top: 10vh;
}

.cmd-palette {
    width: 100%;
    max-width: 620px;
    max-height: 70vh;
    background: #111116;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.cmd-palette__input-wrap {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.cmd-palette__icon {
    font-size: 1.2rem;
    color: rgba(255, 255, 255, 0.4);
}

.cmd-palette__input {
    flex: 1;
    background: transparent;
    border: none;
    color: #fff;
    font-size: 1.05rem;
    outline: none;
    font-family: inherit;
}

.cmd-palette__close {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 4px;
    color: rgba(255, 255, 255, 0.5);
    padding: 0.15rem 0.5rem;
    font-size: 0.72rem;
    cursor: pointer;
}

.cmd-palette__loading, .cmd-palette__empty {
    padding: 2.5rem 1.5rem;
    text-align: center;
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.9rem;
}

.cmd-palette__results {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem;
}

.cmd-palette__group {
    margin-bottom: 0.75rem;
}

.cmd-palette__group-title {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgba(255, 255, 255, 0.35);
    padding: 0.4rem 0.75rem 0.2rem;
    font-weight: 600;
}

.cmd-palette__item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.55rem 0.75rem;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.15s;
}
.cmd-palette__item:hover, .cmd-palette__item--selected {
    background: rgba(155, 110, 232, 0.18);
}

.item-avatar-mini {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}
.item-avatar-mini img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.item-avatar-mini--empty {
    background: #8b5cf6;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
}

.item-info {
    flex: 1;
    min-width: 0;
}
.item-title {
    color: #f4f4f5;
    font-size: 0.88rem;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.item-sub {
    color: rgba(255, 255, 255, 0.45);
    font-size: 0.75rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 0.1rem;
}

.item-badge {
    font-size: 0.72rem;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.7);
    flex-shrink: 0;
}
.item-badge--order {
    background: rgba(56, 189, 248, 0.15);
    color: #38bdf8;
}

.cmd-palette__footer {
    display: flex;
    gap: 1rem;
    padding: 0.6rem 1.25rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(0, 0, 0, 0.2);
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.4);
}
</style>
