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
    TrendCharts, Setting, Coin, Search, Money, Expand, Fold,
    ArrowRight, Check, Close, TopRight
} from '@element-plus/icons-vue';

const page = usePage();
const adminUser = computed(() => page.props.auth_admin);

// ─── Attention counters ──────────────────────────────────────────────────────
const pendingCount                  = computed(() => page.props.pending_applications_count ?? 0);
const pendingServicesCount          = computed(() => page.props.pending_services_count ?? 0);
const pendingServiceChangesCount    = computed(() => page.props.pending_service_changes_count ?? 0);
const pendingContentPacksCount      = computed(() => page.props.pending_content_packs_count ?? 0);
const pendingContentPackChangesCount= computed(() => page.props.pending_content_pack_changes_count ?? 0);
const pendingReportsCount           = computed(() => page.props.pending_reports_count ?? 0);
const pendingDisputesCount          = computed(() => page.props.pending_disputes_count ?? 0);
const pendingTraitSuggestionsCount  = computed(() => page.props.pending_trait_suggestions_count ?? 0);
const pendingInterestSuggestionsCount = computed(() => page.props.pending_interest_suggestions_count ?? 0);
const pendingReviewDisputesCount    = computed(() => page.props.pending_review_disputes_count ?? 0);

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

// ─── Global Flash & Error Toasts ─────────────────────────────────────────────
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
        if (lastShownFlashKey === flashKey) return;
        lastShownFlashKey = flashKey;

        if (flash.success) ElNotification.success({ title: 'Успешно', message: flash.success, duration: 4000 });
        if (flash.error)   ElNotification.error({ title: 'Ошибка', message: flash.error, duration: 6000 });
        if (flash.warning) ElNotification.warning({ title: 'Внимание', message: flash.warning, duration: 5000 });
        if (flash.info)    ElNotification.info({ title: 'Информация', message: flash.info, duration: 4000 });
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

// ─── Sidebar state & Icon Rail ───────────────────────────────────────────────
const isRail = ref(false);
const mobileOpen = ref(false);

const servicesOpen      = ref(false);
const contentPacksOpen  = ref(false);
const quizOpen          = ref(false);
const traitsOpen        = ref(false);
const interestsOpen     = ref(false);

const component = computed(() => page.component || '');

const sectionTitle = computed(() => {
    const c = component.value;
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
    if (c.startsWith('Admin/Payouts')) return 'Выплаты';
    if (c.startsWith('Admin/Users')) return 'Пользователи';
    if (c.startsWith('Admin/Conversations')) return 'Переписки';
    if (c.startsWith('Admin/Strikes')) return 'Страйки';
    if (c.startsWith('Admin/Disputes')) return 'Споры по заказам';
    if (c.startsWith('Admin/ReviewDisputes') || c.startsWith('Admin/Reviews')) return 'Отзывы и споры';
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
    if (c.startsWith('Admin/Logs')) return 'Журнал аудита действий';
    if (c.startsWith('Admin/RatingLogs')) return 'История рейтинга';
    if (c.startsWith('Admin/Settings')) return 'Настройки платформы';
    return 'Панель управления';
});

watch(component, (val) => {
    if (val?.startsWith('Admin/Services/')) servicesOpen.value = true;
    if (val?.startsWith('Admin/ContentPacks/')) contentPacksOpen.value = true;
    if (val?.startsWith('Admin/Quiz/')) quizOpen.value = true;
    if (val?.startsWith('Admin/Traits/')) traitsOpen.value = true;
    if (val?.startsWith('Admin/Interests/')) interestsOpen.value = true;
    mobileOpen.value = false;
}, { immediate: true });

function toggleRail() {
    isRail.value = !isRail.value;
    try {
        localStorage.setItem('admin_sidebar_rail', isRail.value ? 'true' : 'false');
    } catch {}
}

function logout() {
    router.post(route('admin.logout'));
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

const hasResults = computed(() => allResultsFlat.value.length > 0);

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
        if (quickSearchOpen.value) closeQuickSearch();
        else openQuickSearch();
    }
    if ((e.metaKey || e.ctrlKey) && (e.key?.toLowerCase() === 'b' || e.code === 'KeyB')) {
        e.preventDefault();
        toggleRail();
    }
}

// ─── Theme Management ────────────────────────────────────────────────────────
const themes = [
    { id: 'violet',  name: 'Midnight Violet', color: '#8b5cf6', bg: '#110d22' },
    { id: 'ocean',   name: 'Deep Ocean',      color: '#3b82f6', bg: '#0a1426' },
    { id: 'emerald', name: 'Cyber Emerald',   color: '#10b981', bg: '#081a12' },
    { id: 'rose',    name: 'Crimson Velvet',  color: '#f43f5e', bg: '#1c0a12' },
    { id: 'amber',   name: 'Warm Amber',      color: '#f59e0b', bg: '#1a1309' },
];

const currentTheme = ref(typeof window !== 'undefined' ? (localStorage.getItem('admin_theme') || 'violet') : 'violet');
const themeMenuOpen = ref(false);

const activeTheme = computed(() => {
    return themes.find(t => t.id === currentTheme.value) || themes[0];
});

function selectTheme(themeId) {
    currentTheme.value = themeId;
    themeMenuOpen.value = false;
    try {
        localStorage.setItem('admin_theme', themeId);
        document.documentElement.setAttribute('data-admin-theme', themeId);
    } catch {}
}

function linkClass(isActive) {
    if (isRail.value) {
        return [
            'w-10 h-10 mx-auto rounded-xl flex items-center justify-center transition-all group relative shrink-0',
            isActive
                ? 'bg-admin-primary text-white shadow-sm font-semibold'
                : 'text-zinc-400 hover:text-zinc-100 hover:bg-admin-card'
        ];
    }
    return [
        'flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-all group relative',
        isActive
            ? 'bg-admin-primary text-white shadow-sm'
            : 'text-zinc-400 hover:text-zinc-100 hover:bg-admin-card'
    ];
}

function sublinkClass(isActive) {
    return [
        'flex items-center justify-between py-1.5 px-2.5 rounded-lg text-xs font-medium transition-colors',
        isActive
            ? 'text-admin-accent bg-admin-primary-light font-semibold'
            : 'text-zinc-400 hover:text-zinc-200 hover:bg-admin-card/60'
    ];
}

function groupBtnClass(isActive) {
    if (isRail.value) {
        return [
            'w-10 h-10 mx-auto rounded-xl flex items-center justify-center transition-all group relative shrink-0',
            isActive
                ? 'text-admin-accent bg-admin-primary-light'
                : 'text-zinc-400 hover:text-zinc-100 hover:bg-admin-card'
        ];
    }
    return [
        'w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-all group',
        isActive
            ? 'text-admin-accent bg-admin-primary-light'
            : 'text-zinc-400 hover:text-zinc-100 hover:bg-admin-card'
    ];
}

function onWindowClick(e) {
    if (themeMenuOpen.value && !e.target.closest('.admin-theme-picker')) {
        themeMenuOpen.value = false;
    }
}

onMounted(() => {
    try {
        isRail.value = localStorage.getItem('admin_sidebar_rail') === 'true';
        const savedTheme = localStorage.getItem('admin_theme');
        if (savedTheme && themes.some(t => t.id === savedTheme)) {
            currentTheme.value = savedTheme;
        }
        document.documentElement.setAttribute('data-admin-theme', currentTheme.value);
    } catch {}
    window.addEventListener('keydown', onGlobalKeyDown);
    window.addEventListener('click', onWindowClick);
});

onUnmounted(() => {
    try {
        document.documentElement.removeAttribute('data-admin-theme');
    } catch {}
    window.removeEventListener('keydown', onGlobalKeyDown);
    window.removeEventListener('click', onWindowClick);
});
</script>

<template>
    <div
        id="admin-root"
        :data-admin-theme="currentTheme"
        class="h-screen w-screen flex overflow-hidden bg-admin-base font-sans text-zinc-100 antialiased selection:bg-admin-primary selection:text-white"
    >
        <!-- Mobile Sidebar Backdrop -->
        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-40 bg-black/80 backdrop-blur-sm lg:hidden transition-opacity"
            @click="mobileOpen = false"
        />

        <!-- ─── Sidebar ───────────────────────────────────────────────────────────── -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex flex-col bg-admin-surface border-r border-admin-theme transition-all duration-200 select-none lg:static"
            :class="[
                isRail ? 'w-16' : 'w-64',
                mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
            ]"
        >
            <!-- Logo Header -->
            <div
                class="h-16 flex items-center border-b border-admin-theme shrink-0"
                :class="isRail ? 'justify-center px-2' : 'justify-between px-4'"
            >
                <Link v-if="!isRail" :href="route('admin.dashboard')" class="flex items-center gap-3 overflow-hidden">
                    <img src="/app-logo-v3.webp" alt="Not Alone" class="h-6 w-auto shrink-0 object-contain" />
                    <span class="text-xs font-bold uppercase tracking-widest text-admin-accent bg-admin-primary-light border border-admin-primary px-2 py-0.5 rounded-full">
                        Admin
                    </span>
                </Link>

                <button
                    type="button"
                    class="hidden lg:flex items-center justify-center rounded-xl text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
                    :class="isRail ? 'w-10 h-10' : 'w-7 h-7'"
                    :title="isRail ? 'Развернуть меню (Ctrl+B)' : 'Свернуть в иконки (Ctrl+B)'"
                    @click="toggleRail"
                >
                    <el-icon :size="isRail ? 18 : 16">
                        <component :is="isRail ? Expand : Fold" />
                    </el-icon>
                </button>
            </div>

            <!-- Nav Links Container -->
            <nav
                class="flex-1 overflow-y-auto overflow-x-hidden space-y-4"
                :class="isRail ? 'p-2 admin-rail-scroll' : 'p-3'"
            >
                <!-- Группа 1: Операции и Задачи -->
                <div>
                    <div v-if="!isRail" class="px-2.5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-zinc-500">
                        Операционный центр
                    </div>
                    <div class="space-y-1">
                        <!-- Дашборд -->
                        <Link
                            :href="route('admin.dashboard')"
                            :class="linkClass(route().current('admin.dashboard'))"
                            :title="isRail ? 'Дашборд' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><Odometer /></el-icon>
                            <span v-if="!isRail" class="truncate">Дашборд</span>
                        </Link>

                        <!-- Заявки -->
                        <Link
                            :href="route('admin.applications.index')"
                            :class="linkClass(route().current('admin.applications.*'))"
                            :title="isRail ? `Заявки (${pendingCount})` : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><List /></el-icon>
                            <span v-if="!isRail" class="truncate flex-1">Заявки</span>
                            <span
                                v-if="pendingCount > 0"
                                :class="[
                                    isRail
                                        ? 'absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-amber-400 ring-2 ring-zinc-900 animate-pulse'
                                        : (route().current('admin.applications.*') ? 'bg-white/20 text-white border-white/30' : 'bg-admin-primary-light text-admin-accent border border-admin-primary') + ' inline-flex items-center justify-center px-1.5 py-0.5 text-[11px] font-bold rounded-full shrink-0'
                                ]"
                            >
                                <template v-if="!isRail">{{ pendingCount }}</template>
                            </span>
                        </Link>

                        <!-- Споры по заказам -->
                        <Link
                            :href="route('admin.disputes.index')"
                            :class="linkClass(route().current('admin.disputes.*'))"
                            :title="isRail ? `Споры (${pendingDisputesCount})` : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><ScaleToOriginal /></el-icon>
                            <span v-if="!isRail" class="truncate flex-1">Споры</span>
                            <span
                                v-if="pendingDisputesCount > 0"
                                :class="[
                                    isRail
                                        ? 'absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-rose-400 ring-2 ring-zinc-900 animate-pulse'
                                        : 'inline-flex items-center justify-center px-1.5 py-0.5 text-[11px] font-bold rounded-full bg-rose-950/80 text-rose-300 border border-rose-800/80 shrink-0'
                                ]"
                            >
                                <template v-if="!isRail">{{ pendingDisputesCount }}</template>
                            </span>
                        </Link>

                        <!-- Жалобы -->
                        <Link
                            :href="route('admin.reports.index')"
                            :class="linkClass(route().current('admin.reports.*'))"
                            :title="isRail ? `Жалобы (${pendingReportsCount})` : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><WarningFilled /></el-icon>
                            <span v-if="!isRail" class="truncate flex-1">Жалобы</span>
                            <span
                                v-if="pendingReportsCount > 0"
                                :class="[
                                    isRail
                                        ? 'absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-amber-400 ring-2 ring-zinc-900 animate-pulse'
                                        : 'inline-flex items-center justify-center px-1.5 py-0.5 text-[11px] font-bold rounded-full bg-amber-950/80 text-amber-300 border border-amber-800/80 shrink-0'
                                ]"
                            >
                                <template v-if="!isRail">{{ pendingReportsCount }}</template>
                            </span>
                        </Link>

                        <!-- Поддержка -->
                        <Link
                            :href="route('admin.support.index')"
                            :class="linkClass(route().current('admin.support.*'))"
                            :title="isRail ? 'Поддержка' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><Headset /></el-icon>
                            <span v-if="!isRail" class="truncate">Поддержка</span>
                        </Link>
                    </div>
                </div>

                <!-- Группа 2: Коммерция и Финансы -->
                <div>
                    <div v-if="!isRail" class="px-2.5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-zinc-500">
                        Коммерция & Финансы
                    </div>
                    <div class="space-y-1">
                        <!-- Заказы -->
                        <Link
                            :href="route('admin.orders.index')"
                            :class="linkClass(route().current('admin.orders.*'))"
                            :title="isRail ? 'Заказы' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><Tickets /></el-icon>
                            <span v-if="!isRail" class="truncate">Заказы</span>
                        </Link>

                        <!-- Транзакции -->
                        <Link
                            :href="route('admin.transactions.index')"
                            :class="linkClass(route().current('admin.transactions.*'))"
                            :title="isRail ? 'Транзакции' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><Coin /></el-icon>
                            <span v-if="!isRail" class="truncate">Транзакции</span>
                        </Link>

                        <!-- Выплаты -->
                        <Link
                            :href="route('admin.payouts.index')"
                            :class="linkClass(route().current('admin.payouts.*'))"
                            :title="isRail ? 'Выплаты' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><Money /></el-icon>
                            <span v-if="!isRail" class="truncate">Выплаты</span>
                        </Link>
                    </div>
                </div>

                <!-- Группа 3: Каталог и Контент -->
                <div>
                    <div v-if="!isRail" class="px-2.5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-zinc-500">
                        Каталог & Контент
                    </div>
                    <div class="space-y-1">
                        <!-- Услуги (группа) -->
                        <div>
                            <button
                                type="button"
                                :class="groupBtnClass(component.startsWith('Admin/Services/'))"
                                :title="isRail ? 'Услуги' : undefined"
                                @click="isRail ? (isRail = false, servicesOpen = true) : (servicesOpen = !servicesOpen)"
                            >
                                <el-icon :size="18" class="shrink-0"><ShoppingBag /></el-icon>
                                <span v-if="!isRail" class="truncate flex-1 text-left">Услуги</span>
                                <span
                                    v-if="(pendingServicesCount + pendingServiceChangesCount) > 0"
                                    :class="[
                                        isRail
                                            ? 'absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-amber-400 ring-2 ring-zinc-900 animate-pulse'
                                            : 'inline-flex items-center justify-center px-1.5 py-0.5 text-[11px] font-bold rounded-full bg-admin-primary-light text-admin-accent border border-admin-primary shrink-0 mr-1'
                                    ]"
                                >
                                    <template v-if="!isRail">{{ pendingServicesCount + pendingServiceChangesCount }}</template>
                                </span>
                                <span v-if="!isRail" class="text-xs text-zinc-500 transition-transform duration-200" :class="{ 'rotate-90': servicesOpen }">
                                    ▸
                                </span>
                            </button>

                            <div v-if="servicesOpen && !isRail" class="mt-1 pl-7 pr-2 space-y-1">
                                <Link
                                    :href="route('admin.services.moderation.index')"
                                    :class="sublinkClass(route().current('admin.services.moderation.index'))"
                                >
                                    <span>Модерация</span>
                                    <span v-if="pendingServicesCount > 0" class="text-[10px] bg-admin-primary text-white px-1.5 rounded-full">{{ pendingServicesCount }}</span>
                                </Link>
                                <Link
                                    :href="route('admin.services.change-requests.index')"
                                    :class="sublinkClass(route().current('admin.services.change-requests.*'))"
                                >
                                    <span>Запросы изменений</span>
                                    <span v-if="pendingServiceChangesCount > 0" class="text-[10px] bg-admin-primary text-white px-1.5 rounded-full">{{ pendingServiceChangesCount }}</span>
                                </Link>
                                <Link
                                    :href="route('admin.services.categories.index')"
                                    :class="sublinkClass(route().current('admin.services.categories.index'))"
                                >
                                    Категории
                                </Link>
                                <Link
                                    :href="route('admin.services.price-limits.index')"
                                    :class="sublinkClass(route().current('admin.services.price-limits.index'))"
                                >
                                    Лимиты цен
                                </Link>
                                <Link
                                    :href="route('admin.services.time-units.index')"
                                    :class="sublinkClass(route().current('admin.services.time-units.index'))"
                                >
                                    Единицы времени
                                </Link>
                            </div>
                        </div>

                        <!-- Контент-паки (группа) -->
                        <div>
                            <button
                                type="button"
                                :class="groupBtnClass(component.startsWith('Admin/ContentPacks'))"
                                :title="isRail ? 'Контент-паки' : undefined"
                                @click="isRail ? (isRail = false, contentPacksOpen = true) : (contentPacksOpen = !contentPacksOpen)"
                            >
                                <el-icon :size="18" class="shrink-0"><Picture /></el-icon>
                                <span v-if="!isRail" class="truncate flex-1 text-left">Контент-паки</span>
                                <span
                                    v-if="(pendingContentPacksCount + pendingContentPackChangesCount) > 0"
                                    :class="[
                                        isRail
                                            ? 'absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-amber-400 ring-2 ring-zinc-900 animate-pulse'
                                            : 'inline-flex items-center justify-center px-1.5 py-0.5 text-[11px] font-bold rounded-full bg-admin-primary-light text-admin-accent border border-admin-primary shrink-0 mr-1'
                                    ]"
                                >
                                    <template v-if="!isRail">{{ pendingContentPacksCount + pendingContentPackChangesCount }}</template>
                                </span>
                                <span v-if="!isRail" class="text-xs text-zinc-500 transition-transform duration-200" :class="{ 'rotate-90': contentPacksOpen }">
                                    ▸
                                </span>
                            </button>

                            <div v-if="contentPacksOpen && !isRail" class="mt-1 pl-7 pr-2 space-y-1">
                                <Link
                                    :href="route('admin.content-packs.index')"
                                    :class="sublinkClass(route().current('admin.content-packs.index') || route().current('admin.content-packs.show'))"
                                >
                                    <span>Модерация</span>
                                    <span v-if="pendingContentPacksCount > 0" class="text-[10px] bg-admin-primary text-white px-1.5 rounded-full">{{ pendingContentPacksCount }}</span>
                                </Link>
                                <Link
                                    :href="route('admin.content-packs.change-requests.index')"
                                    :class="sublinkClass(route().current('admin.content-packs.change-requests.*'))"
                                >
                                    <span>Запросы изменений</span>
                                    <span v-if="pendingContentPackChangesCount > 0" class="text-[10px] bg-admin-primary text-white px-1.5 rounded-full">{{ pendingContentPackChangesCount }}</span>
                                </Link>
                            </div>
                        </div>

                        <!-- Отзывы и споры по отзывам -->
                        <Link
                            :href="route('admin.review-disputes.index')"
                            :class="linkClass(route().current('admin.review-disputes.*'))"
                            :title="isRail ? `Отзывы (${pendingReviewDisputesCount})` : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><Microphone /></el-icon>
                            <span v-if="!isRail" class="truncate flex-1">Отзывы</span>
                            <span
                                v-if="pendingReviewDisputesCount > 0"
                                :class="[
                                    isRail
                                        ? 'absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-admin-accent ring-2 ring-zinc-900 animate-pulse'
                                        : 'inline-flex items-center justify-center px-1.5 py-0.5 text-[11px] font-bold rounded-full bg-admin-primary-light text-admin-accent border border-admin-primary shrink-0'
                                ]"
                            >
                                <template v-if="!isRail">{{ pendingReviewDisputesCount }}</template>
                            </span>
                        </Link>

                        <!-- Рамки аватарок -->
                        <Link
                            :href="route('admin.avatar-frames.index')"
                            :class="linkClass(route().current('admin.avatar-frames.*'))"
                            :title="isRail ? 'Рамки аватарок' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><Aim /></el-icon>
                            <span v-if="!isRail" class="truncate">Рамки аватарок</span>
                        </Link>
                    </div>
                </div>

                <!-- Группа 4: Пользователи и Модерация -->
                <div>
                    <div v-if="!isRail" class="px-2.5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-zinc-500">
                        Пользователи & Модерация
                    </div>
                    <div class="space-y-1">
                        <!-- Пользователи -->
                        <Link
                            :href="route('admin.users.index')"
                            :class="linkClass(route().current('admin.users.*'))"
                            :title="isRail ? 'Пользователи' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><User /></el-icon>
                            <span v-if="!isRail" class="truncate">Пользователи</span>
                        </Link>

                        <!-- Переписки -->
                        <Link
                            :href="route('admin.conversations.index')"
                            :class="linkClass(route().current('admin.conversations.*'))"
                            :title="isRail ? 'Переписки' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><ChatDotRound /></el-icon>
                            <span v-if="!isRail" class="truncate">Переписки</span>
                        </Link>

                        <!-- Страйки -->
                        <Link
                            :href="route('admin.strikes.index')"
                            :class="linkClass(route().current('admin.strikes.*'))"
                            :title="isRail ? 'Страйки' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><Warning /></el-icon>
                            <span v-if="!isRail" class="truncate">Страйки</span>
                        </Link>

                        <!-- Блокировки в чате -->
                        <Link
                            :href="route('admin.chat-blocks.index')"
                            :class="linkClass(route().current('admin.chat-blocks.*'))"
                            :title="isRail ? 'Блокировки чата' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><Lock /></el-icon>
                            <span v-if="!isRail" class="truncate">Блокировки чата</span>
                        </Link>
                    </div>
                </div>

                <!-- Группа 5: Справочники и Наполнение -->
                <div>
                    <div v-if="!isRail" class="px-2.5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-zinc-500">
                        Справочники
                    </div>
                    <div class="space-y-1">
                        <!-- Квиз -->
                        <div>
                            <button
                                type="button"
                                :class="groupBtnClass(component.startsWith('Admin/Quiz'))"
                                :title="isRail ? 'Квиз' : undefined"
                                @click="isRail ? (isRail = false, quizOpen = true) : (quizOpen = !quizOpen)"
                            >
                                <el-icon :size="18" class="shrink-0"><Aim /></el-icon>
                                <span v-if="!isRail" class="truncate flex-1 text-left">Квиз</span>
                                <span v-if="!isRail" class="text-xs text-zinc-500 transition-transform duration-200" :class="{ 'rotate-90': quizOpen }">
                                    ▸
                                </span>
                            </button>

                            <div v-if="quizOpen && !isRail" class="mt-1 pl-7 pr-2 space-y-1">
                                <Link
                                    :href="route('admin.quiz.questions.index')"
                                    :class="sublinkClass(route().current('admin.quiz.questions.*'))"
                                >
                                    Вопросы
                                </Link>
                                <Link
                                    :href="route('admin.quiz.article.index')"
                                    :class="sublinkClass(route().current('admin.quiz.article.*'))"
                                >
                                    Статья вступления
                                </Link>
                            </div>
                        </div>

                        <!-- Черты характера -->
                        <Link
                            :href="route('admin.traits.index')"
                            :class="linkClass(route().current('admin.traits.*'))"
                            :title="isRail ? 'Черты характера' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><Star /></el-icon>
                            <span v-if="!isRail" class="truncate flex-1">Черты характера</span>
                            <span
                                v-if="pendingTraitSuggestionsCount > 0"
                                :class="[
                                    isRail
                                        ? 'absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-admin-accent ring-2 ring-zinc-900 animate-pulse'
                                        : 'inline-flex items-center justify-center px-1.5 py-0.5 text-[11px] font-bold rounded-full bg-admin-primary-light text-admin-accent border border-admin-primary shrink-0'
                                ]"
                            >
                                <template v-if="!isRail">{{ pendingTraitSuggestionsCount }}</template>
                            </span>
                        </Link>

                        <!-- Интересы -->
                        <Link
                            :href="route('admin.interests.index')"
                            :class="linkClass(route().current('admin.interests.*'))"
                            :title="isRail ? 'Интересы' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><Discount /></el-icon>
                            <span v-if="!isRail" class="truncate flex-1">Интересы</span>
                            <span
                                v-if="pendingInterestSuggestionsCount > 0"
                                :class="[
                                    isRail
                                        ? 'absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-admin-accent ring-2 ring-zinc-900 animate-pulse'
                                        : 'inline-flex items-center justify-center px-1.5 py-0.5 text-[11px] font-bold rounded-full bg-admin-primary-light text-admin-accent border border-admin-primary shrink-0'
                                ]"
                            >
                                <template v-if="!isRail">{{ pendingInterestSuggestionsCount }}</template>
                            </span>
                        </Link>

                        <!-- База знаний / Справка -->
                        <Link
                            :href="route('admin.help-categories.index')"
                            :class="linkClass(route().current('admin.help-categories.*'))"
                            :title="isRail ? 'База знаний' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><QuestionFilled /></el-icon>
                            <span v-if="!isRail" class="truncate">Справка / FAQ</span>
                        </Link>
                    </div>
                </div>

                <!-- Группа 6: Система -->
                <div>
                    <div v-if="!isRail" class="px-2.5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-zinc-500">
                        Система & Логи
                    </div>
                    <div class="space-y-1">
                        <!-- Настройки -->
                        <Link
                            :href="route('admin.settings.index')"
                            :class="linkClass(route().current('admin.settings.*'))"
                            :title="isRail ? 'Настройки' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><Setting /></el-icon>
                            <span v-if="!isRail" class="truncate">Настройки</span>
                        </Link>

                        <!-- Рассылки -->
                        <Link
                            :href="route('admin.messages.index')"
                            :class="linkClass(route().current('admin.messages.*'))"
                            :title="isRail ? 'Рассылки' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><Message /></el-icon>
                            <span v-if="!isRail" class="truncate">Рассылки</span>
                        </Link>

                        <!-- Аудит админов -->
                        <Link
                            :href="route('admin.logs.index')"
                            :class="linkClass(route().current('admin.logs.*'))"
                            :title="isRail ? 'Журнал аудита' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><Monitor /></el-icon>
                            <span v-if="!isRail" class="truncate">Журнал аудита</span>
                        </Link>

                        <!-- Системные логи -->
                        <Link
                            :href="route('admin.system-logs.index')"
                            :class="linkClass(route().current('admin.system-logs.*'))"
                            :title="isRail ? 'Системные логи' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><Document /></el-icon>
                            <span v-if="!isRail" class="truncate">Системные логи</span>
                        </Link>

                        <!-- Рейтинг айдолов -->
                        <Link
                            :href="route('admin.rating-logs.index')"
                            :class="linkClass(route().current('admin.rating-logs.*'))"
                            :title="isRail ? 'История рейтинга' : undefined"
                        >
                            <el-icon :size="18" class="shrink-0"><TrendCharts /></el-icon>
                            <span v-if="!isRail" class="truncate">Рейтинг айдолов</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- Sidebar Footer (Admin Profile & Collapse) -->
            <div class="p-3 border-t border-admin-theme bg-admin-surface shrink-0" :class="{ 'px-2': isRail }">
                <div v-if="adminUser" class="flex items-center" :class="isRail ? 'justify-center' : 'gap-3'">
                    <div
                        class="w-8 h-8 rounded-full bg-admin-primary-light border border-admin-primary text-admin-accent flex items-center justify-center font-bold text-xs shrink-0 cursor-pointer"
                        :title="isRail ? `${adminUser.name} (${adminUser.email})` : undefined"
                    >
                        {{ adminUser.name?.[0]?.toUpperCase() ?? 'A' }}
                    </div>
                    <div v-if="!isRail" class="min-w-0 flex-1">
                        <div class="text-xs font-semibold text-zinc-200 truncate">{{ adminUser.name }}</div>
                        <div class="text-[11px] text-zinc-500 truncate">{{ adminUser.email }}</div>
                    </div>
                    <button
                        v-if="!isRail"
                        type="button"
                        class="p-1.5 rounded-lg text-zinc-400 hover:text-rose-400 hover:bg-zinc-800 transition-colors"
                        title="Выйти из админки"
                        @click="logout"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- ─── Main Content Wrap ────────────────────────────────────────────────── -->
        <div class="flex-1 min-w-0 flex flex-col overflow-hidden bg-admin-base">
            <!-- Topbar -->
            <header class="h-16 flex items-center justify-between px-4 sm:px-6 bg-admin-surface/80 border-b border-admin-theme backdrop-blur-sm shrink-0 gap-4">
                <!-- Left: Hamburger (Mobile) & Breadcrumbs -->
                <div class="flex items-center gap-3 min-w-0">
                    <button
                        type="button"
                        class="lg:hidden p-2 rounded-lg text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <!-- Breadcrumbs -->
                    <div class="flex items-center gap-2 text-sm truncate">
                        <span class="text-zinc-500 font-medium hidden sm:inline">Админ</span>
                        <span class="text-zinc-600 hidden sm:inline">/</span>
                        <span class="text-zinc-100 font-semibold truncate">{{ sectionTitle }}</span>
                    </div>
                </div>

                <!-- Center: Global Quick Search Button -->
                <div class="flex-1 max-w-md hidden md:block">
                    <button
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-1.5 text-xs text-zinc-400 bg-admin-card border border-admin-subtle hover:border-admin-theme rounded-lg transition-colors group shadow-inner"
                        @click="openQuickSearch"
                    >
                        <span class="flex items-center gap-2">
                            <el-icon class="text-zinc-500 group-hover:text-admin-accent transition-colors"><Search /></el-icon>
                            <span>Поиск пользователей, заказов, транзакций...</span>
                        </span>
                        <kbd class="px-1.5 py-0.5 text-[10px] font-mono bg-zinc-800 border border-zinc-700 text-zinc-400 rounded">Ctrl K</kbd>
                    </button>
                </div>

                <!-- Right: Attention Pill, Site Link, Profile -->
                <div class="flex items-center gap-3 shrink-0">
                    <!-- Attention Pill -->
                    <div
                        v-if="totalAttentionCount > 0"
                        class="flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-950/60 text-amber-300 border border-amber-800/60 shadow-sm"
                        title="Требуют модерации или решения"
                    >
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping" />
                        <span>В очереди: {{ totalAttentionCount }}</span>
                    </div>

                    <!-- External Site Link -->
                    <a
                        href="/"
                        target="_blank"
                        rel="noopener"
                        class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-admin-accent hover:text-white bg-admin-primary-light hover:bg-admin-primary/30 border border-admin-primary rounded-lg transition-colors"
                    >
                        <span>На сайт</span>
                        <el-icon :size="12"><TopRight /></el-icon>
                    </a>

                    <!-- Theme Selector Dropdown -->
                    <div class="relative admin-theme-picker">
                        <button
                            type="button"
                            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-admin-subtle bg-admin-card text-xs text-zinc-300 hover:text-white hover:border-admin-theme transition"
                            title="Тема оформления админки"
                            @click="themeMenuOpen = !themeMenuOpen"
                        >
                            <span
                                class="w-2.5 h-2.5 rounded-full shrink-0"
                                :style="{ backgroundColor: activeTheme?.color || '#8b5cf6' }"
                            />
                            <span class="hidden md:inline">{{ activeTheme?.name || 'Тема' }}</span>
                            <svg class="w-3.5 h-3.5 text-zinc-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div
                            v-if="themeMenuOpen"
                            class="absolute right-0 mt-2 w-52 rounded-xl border border-admin-theme bg-admin-card/95 backdrop-blur-md p-1.5 shadow-2xl z-50 text-xs space-y-1"
                        >
                            <div class="px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-zinc-500">
                                Тема оформления
                            </div>
                            <button
                                v-for="t in themes"
                                :key="t.id"
                                type="button"
                                class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-left transition-colors"
                                :class="currentTheme === t.id ? 'bg-admin-primary-light text-admin-accent font-semibold border border-admin-primary' : 'text-zinc-400 hover:text-zinc-200 hover:bg-admin-surface'"
                                @click="selectTheme(t.id)"
                            >
                                <span class="flex items-center gap-2">
                                    <span
                                        class="w-3.5 h-3.5 rounded-full shrink-0 border-2 flex items-center justify-center"
                                        :style="{ backgroundColor: t.bg, borderColor: t.color }"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: t.color }" />
                                    </span>
                                    <span>{{ t.name }}</span>
                                </span>
                                <el-icon v-if="currentTheme === t.id" class="text-admin-accent"><Check /></el-icon>
                            </button>
                        </div>
                    </div>

                    <!-- Profile Dropdown -->
                    <div v-if="adminUser" class="flex items-center gap-2 pl-2 border-l border-admin-theme">
                        <Link
                            :href="route('admin.settings.index')"
                            class="w-8 h-8 rounded-full bg-admin-primary-light border border-admin-primary text-admin-accent flex items-center justify-center font-bold text-xs hover:border-admin-primary transition-colors"
                            :title="adminUser.name"
                        >
                            {{ adminUser.name?.[0]?.toUpperCase() ?? 'A' }}
                        </Link>
                    </div>
                </div>
            </header>

            <!-- Main Page Viewport -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-admin-base">
                <slot />
            </main>
        </div>

        <!-- ─── Command Palette (Ctrl+K Modal) ─────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="quickSearchOpen" class="fixed inset-0 z-50 p-4 sm:p-6 md:p-20 flex items-start justify-center">
                <div class="fixed inset-0 bg-black/75 backdrop-blur-[3px] transition-opacity" @click="closeQuickSearch" />

                <div class="relative w-full max-w-xl bg-admin-card border border-admin-theme rounded-2xl shadow-2xl overflow-hidden text-zinc-100 flex flex-col max-h-[80vh] z-10">
                    <div class="flex items-center px-4 border-b border-admin-theme bg-admin-surface/80">
                        <el-icon :size="18" class="text-zinc-500 mr-3"><Search /></el-icon>
                        <input
                            ref="searchInputRef"
                            v-model="quickSearchQuery"
                            type="text"
                            class="w-full bg-transparent border-0 text-zinc-100 placeholder-zinc-500 py-3.5 text-sm focus:outline-none focus:ring-0"
                            placeholder="Поиск по ID, имени, email, телефону..."
                            @input="onQuickSearchInput"
                            @keydown="onSearchKeydown"
                        />
                        <button class="text-xs text-zinc-400 bg-zinc-800 border border-zinc-700 px-2 py-0.5 rounded" @click="closeQuickSearch">Esc</button>
                    </div>

                    <div v-if="quickSearchLoading" class="p-8 text-center text-xs text-zinc-500">
                        Поиск по платформе...
                    </div>

                    <div v-else-if="quickSearchQuery.length >= 2 && !hasResults" class="p-8 text-center text-xs text-zinc-500">
                        Ничего не найдено по запросу «{{ quickSearchQuery }}»
                    </div>

                    <div v-else-if="hasResults" class="overflow-y-auto p-2 space-y-3 flex-1">
                        <!-- Users -->
                        <div v-if="quickSearchResults.users?.length">
                            <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-zinc-500">Пользователи</div>
                            <div
                                v-for="item in quickSearchResults.users"
                                :key="'user-' + item.id"
                                class="flex items-center justify-between p-2 rounded-lg cursor-pointer transition-colors"
                                :class="allResultsFlat[selectedIndex]?.group === 'users' && allResultsFlat[selectedIndex]?.id === item.id ? 'bg-violet-600 text-white' : 'hover:bg-zinc-800 text-zinc-300'"
                                @click="navigateToItem(item)"
                            >
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <img v-if="item.avatar_url" :src="item.avatar_url" class="w-6 h-6 rounded-full object-cover shrink-0" alt="" />
                                    <div v-else class="w-6 h-6 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center text-[10px] shrink-0 font-bold">
                                        {{ item.title?.[0] }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs font-medium truncate">{{ item.title }}</div>
                                        <div class="text-[10px] opacity-70 truncate">{{ item.subtitle }}</div>
                                    </div>
                                </div>
                                <span class="text-[10px] px-1.5 py-0.5 rounded border border-current opacity-80 shrink-0">{{ item.badge }}</span>
                            </div>
                        </div>

                        <!-- Orders -->
                        <div v-if="quickSearchResults.orders?.length">
                            <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-zinc-500">Заказы</div>
                            <div
                                v-for="item in quickSearchResults.orders"
                                :key="'order-' + item.id"
                                class="flex items-center justify-between p-2 rounded-lg cursor-pointer transition-colors"
                                :class="allResultsFlat[selectedIndex]?.group === 'orders' && allResultsFlat[selectedIndex]?.id === item.id ? 'bg-violet-600 text-white' : 'hover:bg-zinc-800 text-zinc-300'"
                                @click="navigateToItem(item)"
                            >
                                <div class="min-w-0">
                                    <div class="text-xs font-medium truncate">{{ item.title }}</div>
                                    <div class="text-[10px] opacity-70 truncate">{{ item.subtitle }}</div>
                                </div>
                                <span class="text-[10px] px-1.5 py-0.5 rounded border border-current opacity-80 shrink-0">{{ item.badge }}</span>
                            </div>
                        </div>

                        <!-- Packs -->
                        <div v-if="quickSearchResults.packs?.length">
                            <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-zinc-500">Контент-паки</div>
                            <div
                                v-for="item in quickSearchResults.packs"
                                :key="'pack-' + item.id"
                                class="flex items-center justify-between p-2 rounded-lg cursor-pointer transition-colors"
                                :class="allResultsFlat[selectedIndex]?.group === 'packs' && allResultsFlat[selectedIndex]?.id === item.id ? 'bg-violet-600 text-white' : 'hover:bg-zinc-800 text-zinc-300'"
                                @click="navigateToItem(item)"
                            >
                                <div class="min-w-0">
                                    <div class="text-xs font-medium truncate">{{ item.title }}</div>
                                    <div class="text-[10px] opacity-70 truncate">{{ item.subtitle }}</div>
                                </div>
                                <span class="text-[10px] px-1.5 py-0.5 rounded border border-current opacity-80 shrink-0">{{ item.badge }}</span>
                            </div>
                        </div>

                        <!-- Transactions -->
                        <div v-if="quickSearchResults.transactions?.length">
                            <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-zinc-500">Транзакции</div>
                            <div
                                v-for="item in quickSearchResults.transactions"
                                :key="'tx-' + item.id"
                                class="flex items-center justify-between p-2 rounded-lg cursor-pointer transition-colors"
                                :class="allResultsFlat[selectedIndex]?.group === 'transactions' && allResultsFlat[selectedIndex]?.id === item.id ? 'bg-violet-600 text-white' : 'hover:bg-zinc-800 text-zinc-300'"
                                @click="navigateToItem(item)"
                            >
                                <div class="min-w-0">
                                    <div class="text-xs font-medium truncate">{{ item.title }}</div>
                                    <div class="text-[10px] opacity-70 truncate">{{ item.subtitle }}</div>
                                </div>
                                <span class="text-[10px] px-1.5 py-0.5 rounded border border-current opacity-80 shrink-0">{{ item.badge }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="px-4 py-2 bg-zinc-950 border-t border-zinc-800 text-[10px] text-zinc-500 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span>↑↓ Выбрать</span>
                            <span>↵ Перейти</span>
                            <span>Esc Закрыть</span>
                        </div>
                        <div>Not Alone Admin</div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
