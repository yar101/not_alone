<script setup>
import { ref, computed, watch, inject, onMounted, onUnmounted, h } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ElNotification, ElIcon } from 'element-plus';
import {
    Promotion, Trophy, CircleClose,
    DocumentAdd, CircleCheckFilled, CircleCloseFilled,
    Coin, SuccessFilled, WarningFilled, Rank, Bell,
    PictureFilled, StarFilled,
} from '@element-plus/icons-vue';

import { useTranslations } from '@/composables/useTranslations';

const page = usePage();
const { __, locale } = useTranslations();
const open = ref(false);

const allItems = ref([]);
const loading = ref(false);
const loadingMore = ref(false);
const hasMore = ref(false);
const beforeCursor = ref(null); // ISO string — oldest created_at seen so far
let autoReadTimer = null;

const openOrder = inject('openOrder', null);

async function loadWindow(before) {
    const params = before ? { before } : {};
    const { data } = await axios.get(route('notifications.combined'), { params });

    hasMore.value = data.has_more;

    if (before) {
        allItems.value = [...allItems.value, ...data.items];
    } else {
        allItems.value = data.items;
    }

    if (allItems.value.length > 0) {
        beforeCursor.value = allItems.value[allItems.value.length - 1].created_at;
    }
}

async function fetchAll() {
    loading.value = true;
    allItems.value = [];
    beforeCursor.value = null;
    hasMore.value = false;
    try {
        await loadWindow(null);
        // Тихо догружаем под скелетоном, не трогая loadingMore
        while (filteredItems.value.length === 0 && hasMore.value && beforeCursor.value !== null) {
            await loadWindow(beforeCursor.value);
        }
    } finally {
        loading.value = false;
    }
}

async function fetchMore() {
    if (loadingMore.value || !hasMore.value) return;
    loadingMore.value = true;
    try {
        await loadWindow(beforeCursor.value);
    } finally {
        loadingMore.value = false;
    }
}

async function markAllRead() {
    const hasUnread = allItems.value.some(n => !n.read_at);
    if (!hasUnread) return;
    await Promise.all([
        axios.patch(route('notifications.read-all')),
        axios.patch(route('notifications.service.read-all')),
        axios.patch(route('notifications.orders.read-all')),
    ]);
    allItems.value.forEach(n => { n.read_at = n.read_at || new Date().toISOString(); });
    router.reload({ only: ['notifications_unread', 'service_unread', 'order_notifications_unread'] });
}

function toggleDropdown() {
    open.value = !open.value;
    if (open.value) {
        fetchAll();
        autoReadTimer = setTimeout(markAllRead, 1500);
    } else {
        clearTimeout(autoReadTimer);
    }
}

function handleItemClick(item) {
    if (item._cat === 'order' && item.order_id) {
        open.value = false;
        openOrder?.(item.order_id);
        return;
    }
    if (item.type === 'new_review') {
        open.value = false;
        router.visit(route('profile.show', { user: page.props.auth.user.id }) + '#reviews');
        return;
    }
    const profileTypes = [
        'idol_approved', 'idol_rejected', 'low_rating_warning',
        'admin_rating', 'review_dispute_approved', 'review_dispute_rejected',
        'content_pack_approved', 'content_pack_remarks', 'content_pack_rejected',
    ];
    if (profileTypes.includes(item.type)) {
        open.value = false;
        router.visit(route('profile.show', { user: page.props.auth.user.id }) + '#content');
    }
}

function isClickable(item) {
    if (item._cat === 'order' && item.order_id) return true;
    const profileTypes = [
        'idol_approved', 'idol_rejected', 'low_rating_warning',
        'admin_rating', 'review_dispute_approved', 'review_dispute_rejected',
        'content_pack_approved', 'content_pack_remarks', 'content_pack_rejected',
        'new_review',
    ];
    return profileTypes.includes(item.type);
}

function closeOnOutside(e) {
    if (!e.target.closest('.notif-bell')) open.value = false;
}

const activeFilter = ref('all');

const filteredItems = computed(() => {
    if (activeFilter.value === 'all') return allItems.value;
    return allItems.value.filter(n => n._cat === activeFilter.value);
});

// Подгружаем следующие окна, пока в текущем фильтре нет элементов
async function autoFetchIfEmpty() {
    while (filteredItems.value.length === 0 && hasMore.value && !loadingMore.value && beforeCursor.value !== null) {
        await fetchMore();
    }
}

watch(activeFilter, autoFetchIfEmpty);

const totalUnread = computed(() =>
    (page.props.notifications_unread ?? 0) +
    (page.props.service_unread ?? 0) +
    (page.props.order_notifications_unread ?? 0)
);

const serviceUnread = computed(() =>
    (page.props.notifications_unread ?? 0) + (page.props.service_unread ?? 0)
);
const orderUnread = computed(() => page.props.order_notifications_unread ?? 0);

function reloadCounts() {
    router.reload({ only: ['notifications_unread', 'service_unread', 'order_notifications_unread'] });
}

// ── Popup notifications ───────────────────────────────────
const latestKnownAt = ref(null);

function notifPopupTitle(item) {
    if (item._cat === 'order') {
        return {
            order_created: __('notification.type.order_created'),
            order_accepted: __('notification.type.order_accepted'),
            order_cancelled: __('notification.type.order_cancelled'),
            order_paid: __('notification.type.order_paid'),
            order_completed: __('notification.type.order_completed'),
        }[item.type] ?? __('notification.type.order_created');
    }
    if (item.title) return item.title;
    return {
        content_pack_approved: __('notification.type.pack_approved'),
        content_pack_remarks: __('notification.type.pack_remarks'),
        content_pack_rejected: __('notification.type.pack_rejected'),
        content_pack_change_approved: __('notification.type.pack_change_approved'),
        content_pack_change_remarks: __('notification.type.pack_change_remarks'),
        content_pack_change_rejected: __('notification.type.pack_change_rejected'),
        idol_approved: __('notification.type.app_approved'),
        idol_rejected: __('notification.type.app_rejected'),
        admin_broadcast: __('notification.type.broadcast'),
        low_rating_warning: __('notification.type.low_rating'),
        admin_rating: __('notification.type.admin_rating'),
        review_dispute_approved: __('notification.type.dispute_approved'),
        review_dispute_rejected: __('notification.type.dispute_rejected'),
        new_review: __('notification.type.new_review'),
    }[item.type] ?? __('notification.type.default');
}

function showNotifPopup(item) {
    const iconComp = itemIconComponent(item);
    const iconClass = itemIconClass(item);
    const title = notifPopupTitle(item);
    const message = item._cat === 'order' ? orderMessage(item) : (item.message ?? '');

    ElNotification({
        duration: 5000,
        position: 'top-right',
        offset: 70,
        customClass: 'app-notif',
        showClose: true,
        message: h('div', { class: 'app-notif__body' }, [
            h('div', { class: `app-notif__icon ${iconClass}` }, [
                h(ElIcon, null, { default: () => h(iconComp) }),
            ]),
            h('div', { class: 'app-notif__text' }, [
                h('p', { class: 'app-notif__title' }, title),
                ...(message ? [h('p', { class: 'app-notif__msg' }, message)] : []),
            ]),
        ]),
    });
}

async function handleNewNotification() {
    try {
        const { data } = await axios.get(route('notifications.combined'));
        if (data.items.length > 0) {
            if (latestKnownAt.value) {
                const fresh = data.items.filter(n => n.created_at > latestKnownAt.value);
                fresh.slice(0, 3).forEach(showNotifPopup);
            }
            latestKnownAt.value = data.items[0].created_at;
        }
    } catch { /* ignore */ }
    reloadCounts();
    if (open.value) loadWindow(null);
}

function relativeTime(dateStr) {
    const diff = (Date.now() - new Date(dateStr)) / 1000;
    if (diff < 60) return __('notification.time.just_now');
    if (diff < 3600) return __('notification.time.minutes', { n: Math.floor(diff / 60) });
    if (diff < 86400) return __('notification.time.hours', { n: Math.floor(diff / 3600) });
    if (diff < 2592000) return __('notification.time.days', { n: Math.floor(diff / 86400) });
    const loc = locale.value?.current === 'ru' ? 'ru' : 'en';
    return new Date(dateStr).toLocaleDateString(loc, { day: 'numeric', month: 'short' });
}

function itemIconComponent(item) {
    if (item._cat === 'order') {
        return {
            order_created: DocumentAdd,
            order_accepted: CircleCheckFilled,
            order_cancelled: CircleCloseFilled,
            order_paid: Coin,
            order_completed: SuccessFilled,
        }[item.type] ?? DocumentAdd;
    }
    if (item._cat === 'service') {
        return {
            admin_broadcast: Promotion,
            idol_approved: Trophy,
            idol_rejected: CircleClose,
            low_rating_warning: WarningFilled,
            admin_rating: Rank,
            review_dispute_approved: CircleCheckFilled,
            review_dispute_rejected: CircleCloseFilled,
            content_pack_approved: PictureFilled,
            content_pack_remarks: PictureFilled,
            content_pack_rejected: PictureFilled,
            content_pack_change_approved: PictureFilled,
            content_pack_change_remarks: PictureFilled,
            content_pack_change_rejected: PictureFilled,
            new_review: StarFilled,
        }[item.type] ?? Bell;
    }
    return Bell;
}

function itemIconClass(item) {
    if (item._cat === 'order') {
        if (item.type === 'order_accepted' || item.type === 'order_completed') return 'icon--success';
        if (item.type === 'order_cancelled') return 'icon--danger';
        if (item.type === 'order_paid') return 'icon--paid';
        if (item.type === 'order_created') return 'icon--paid';
        return 'icon--default';
    }
    if (item._cat === 'service') {
        if (item.type === 'admin_broadcast') return 'icon--broadcast';
        if (item.type === 'idol_approved' || item.type === 'review_dispute_approved' || item.type === 'content_pack_approved' || item.type === 'content_pack_change_approved') return 'icon--success';
        if (item.type === 'idol_rejected' || item.type === 'review_dispute_rejected' || item.type === 'content_pack_remarks' || item.type === 'content_pack_change_remarks') return 'icon--warning';
        if (item.type === 'content_pack_rejected' || item.type === 'content_pack_change_rejected') return 'icon--danger';
        if (item.type === 'new_review') return 'icon--success';
        if (item.type === 'low_rating_warning') return 'icon--warning';
        if (item.type === 'admin_rating') return 'icon--paid';
        return 'icon--default';
    }
    return 'icon--personal';
}

function orderMessage(item) {
    if (item.type === 'order_created') return __('notification.msg.new_order', { name: item.data?.customer_name });
    if (item.type === 'order_accepted') {
        const key = item.data?.idol_gender === 'female'
            ? 'notification.msg.order_accepted.female'
            : 'notification.msg.order_accepted.male';
        return __(key, { name: item.data?.idol_name });
    }
    if (item.type === 'order_cancelled') return __('notification.msg.order_cancelled');
    if (item.type === 'order_paid') {
        const key = item.data?.customer_gender === 'female'
            ? 'notification.msg.order_paid.female'
            : 'notification.msg.order_paid.male';
        return __(key, { name: item.data?.customer_name });
    }
    if (item.type === 'order_completed') return __('notification.msg.order_completed');
    return '';
}

onMounted(() => {
    document.addEventListener('click', closeOnOutside);

    // Инициализируем курсор, чтобы не показывать попапы для уже существующих уведомлений
    axios.get(route('notifications.combined'))
        .then(({ data }) => {
            latestKnownAt.value = data.items.length > 0
                ? data.items[0].created_at
                : new Date().toISOString();
        })
        .catch(() => { latestKnownAt.value = new Date().toISOString(); });

    window.Echo.channel('notifications.global')
        .listen('.new-notification', handleNewNotification);
    const userId = page.props.auth?.user?.id;
    if (userId) {
        window.Echo.private(`App.Models.User.${userId}`)
            .listen('.new-notification', handleNewNotification);
    }
});

onUnmounted(() => {
    clearTimeout(autoReadTimer);
    document.removeEventListener('click', closeOnOutside);
    window.Echo.leaveChannel('notifications.global');
    const userId = page.props.auth?.user?.id;
    if (userId) {
        window.Echo.leaveChannel(`private-App.Models.User.${userId}`);
    }
});
</script>

<template>
    <div class="notif-bell">
        <!-- Bell button -->
        <button class="bell-btn" @click.stop="toggleDropdown" :class="{ 'bell-btn--active': open }">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            <Transition name="badge-pop">
                <span v-if="totalUnread > 0" class="badge" :key="totalUnread">
                    {{ totalUnread > 9 ? '9+' : totalUnread }}
                </span>
            </Transition>
        </button>

        <Transition name="dropdown">
            <div v-if="open" class="notif-dropdown">

                <!-- Header -->
                <div class="notif-panel-header">
                    <span class="notif-panel-title">{{ __('notification.title') }}</span>
                    <div class="notif-filters">
                        <button class="notif-filter-btn" :class="{ 'notif-filter-btn--active': activeFilter === 'all' }"
                            @click="activeFilter = 'all'">
                            {{ __('notification.tab.all') }}
                            <span v-if="totalUnread > 0" class="notif-filter-dot"></span>
                        </button>
                        <button class="notif-filter-btn"
                            :class="{ 'notif-filter-btn--active': activeFilter === 'service' }"
                            @click="activeFilter = 'service'">
                            {{ __('notification.tab.service') }}
                            <span v-if="serviceUnread > 0" class="notif-filter-dot"></span>
                        </button>
                        <button class="notif-filter-btn"
                            :class="{ 'notif-filter-btn--active': activeFilter === 'order' }"
                            @click="activeFilter = 'order'">
                            {{ __('notification.tab.orders') }}
                            <span v-if="orderUnread > 0" class="notif-filter-dot"></span>
                        </button>
                    </div>
                </div>

                <!-- Skeleton loader -->
                <div v-if="loading" class="notif-skeleton-list">
                    <div v-for="i in 4" :key="i" class="notif-skeleton-item">
                        <div class="sk-icon"></div>
                        <div class="sk-lines">
                            <div class="sk-line sk-line--title"></div>
                            <div class="sk-line sk-line--body"></div>
                            <div class="sk-line sk-line--time"></div>
                        </div>
                    </div>
                </div>

                <template v-else>
                    <!-- Empty state -->
                    <div v-if="allItems.length === 0" class="notif-empty">
                        <div class="empty-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" opacity="0.3">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                            </svg>
                        </div>
                        <p class="empty-text">{{ __('notification.empty') }}</p>
                    </div>

                    <!-- Unified list -->
                    <div v-else class="notif-list">
                        <div v-for="item in filteredItems" :key="item.id" class="notif-item" :class="{
                            'notif-item--unread': !item.read_at,
                            'notif-item--clickable': isClickable(item),
                        }" @click="handleItemClick(item)">
                            <!-- Content -->
                            <div class="notif-content">
                                <div class="notif-header">
                                    <div class="notif-icon-wrap" :class="itemIconClass(item)">
                                        <el-icon>
                                            <component :is="itemIconComponent(item)" />
                                        </el-icon>
                                    </div>
                                    <p v-if="item.title" class="notif-service-title">{{ item.title }}</p>
                                    <p v-else class="notif-msg notif-msg--headline">
                                        <template v-if="item._cat === 'order'">{{ orderMessage(item) }}</template>
                                        <template v-else>{{ item.message }}</template>
                                    </p>
                                    <span class="notif-cat-tag" :class="`cat--${item._cat}`">
                                        {{ item._cat === 'personal' ? __('notification.tag.personal') : item._cat === 'service' ? __('notification.tag.service') : __('notification.tag.order') }}
                                    </span>
                                </div>
                                <p v-if="item.title" class="notif-msg notif-msg--sub">
                                    <template v-if="item._cat === 'order'">{{ orderMessage(item) }}</template>
                                    <template v-else>{{ item.message }}</template>
                                </p>
                                <p v-if="item.reason" class="notif-reason">
                                    <span class="notif-reason--sub">{{ __('notification.reason') }}</span> {{ item.reason }}
                                </p>
                                <div class="notif-footer-row">
                                    <span class="notif-time">{{ relativeTime(item.created_at) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Load more button -->
                        <div v-if="hasMore || loadingMore" class="notif-load-more-wrap">
                            <button class="notif-load-more-btn" :disabled="loadingMore" @click="fetchMore">
                                <span class="notif-load-more-text"
                                    :style="{ visibility: loadingMore ? 'hidden' : 'visible' }">{{ __('notification.load_more') }}</span>
                                <span v-if="loadingMore" class="notif-load-more-dots">
                                    <span class="notif-load-dot"></span>
                                    <span class="notif-load-dot"></span>
                                    <span class="notif-load-dot"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                </template>

            </div>
        </Transition>
    </div>
</template>

<style scoped>
/* ── Bell button ── */
.notif-bell {
    position: relative;
}

.bell-btn {
    position: relative;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: transparent;
    border: 1px solid transparent;
    color: rgba(255, 255, 255, 0.45);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
}

.bell-btn:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
}

.bell-btn--active {
    background: rgba(160, 160, 255, 0.1);
    border-color: rgba(160, 160, 255, 0.3);
    color: var(--color-base-1);
}

.badge {
    position: absolute;
    top: 4px;
    right: 4px;
    min-width: 15px;
    height: 15px;
    background: var(--color-base-1);
    border-radius: 999px;
    font-size: 0.6rem;
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 3px;
    line-height: 1;
    border: 1.5px solid #0a0a14;
}

/* ── Dropdown panel ── */
.notif-dropdown {
    position: absolute;
    top: calc(100% + 24px);
    right: 0;
    width: 500px;
    background: #0f0f1d;
    border: 1px solid rgba(160, 160, 255, 0.18);
    border-radius: 6px;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.04) inset;
    overflow: hidden;
    z-index: 1101;
    overflow: hidden;
}

.notif-dropdown::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(160, 160, 255, 0.3) 50%, transparent 100%);
    pointer-events: none;
}

/* ── Panel header ── */
.notif-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem 0.75rem;
    border-bottom: 1px solid rgba(160, 160, 255, 0.15);
}

.notif-panel-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: rgba(160, 160, 255, 0.75);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

/* ── Filter buttons ── */
.notif-filters {
    display: flex;
    gap: 7px;
}

.notif-filter-btn {
    position: relative;
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.07);
    font-size: 0.88rem;
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    transition: all 0.15s;
    overflow: hidden;
}

.notif-filter-btn::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    border-radius: 6px 6px 0 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.22) 50%, transparent 100%);
}

.notif-filter-btn:hover {
    color: rgba(255, 255, 255, 0.7);
    background: rgba(255, 255, 255, 0.07);
}

.notif-filter-btn--active {
    background: rgba(160, 160, 255, 0.15);
    border-color: rgba(160, 160, 255, 0.3);
    color: rgba(160, 160, 255, 0.95);
}

.notif-filter-btn--active::after {
    background: linear-gradient(90deg, transparent 0%, rgba(160, 160, 255, 0.55) 50%, transparent 100%);
}

.notif-filter-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--color-base-1);
    flex-shrink: 0;
}

/* ── Notification list ── */
.notif-list {
    max-height: 560px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(160, 160, 255, 0.3) transparent;
}

.notif-list:deep(::-webkit-scrollbar) {
    width: 3px;
}

.notif-list:deep(::-webkit-scrollbar-track) {
    background: transparent;
}

.notif-list:deep(::-webkit-scrollbar-thumb) {
    background: rgba(160, 160, 255, 0.3);
    border-radius: 99px;
}

.notif-list:deep(::-webkit-scrollbar-thumb:hover) {
    background: rgba(160, 160, 255, 0.6);
}

.notif-item {
    display: flex;
    padding: 0.7rem 1rem 0.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.09);
    transition: background 0.12s;
    position: relative;
}

.notif-item:last-child {
    border-bottom: none;
}

.notif-item:hover {
    background: rgba(255, 255, 255, 0.025);
}

.notif-item--unread {
    background: rgba(160, 160, 255, 0.04);
}

.notif-item--unread:hover {
    background: rgba(160, 160, 255, 0.07);
}

.notif-item--clickable {
    cursor: pointer;
}

/* unread left accent */
.notif-item--unread::before {
    content: '';
    position: absolute;
    left: 0;
    top: 20%;
    bottom: 20%;
    width: 2px;
    background: var(--color-base-1);
    border-radius: 0 2px 2px 0;
}

/* ── Icon ── */
.notif-icon-wrap {
    width: 40px;
    height: 40px;
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
}

.notif-icon-wrap .el-icon {
    font-size: 1.15rem;
}

.icon--success {
    background: rgba(76, 222, 143, 0.12);
    color: #4cde8f;
}

.icon--danger {
    background: rgba(239, 68, 68, 0.12);
    color: #f87171;
}

.icon--paid {
    background: rgba(96, 165, 250, 0.12);
    color: #60a5fa;
}

.icon--broadcast {
    background: rgba(160, 160, 255, 0.12);
    color: var(--color-base-1);
}

.icon--warning {
    background: rgba(251, 191, 36, 0.12);
    color: #fbbf24;
}

.icon--personal {
    background: rgba(160, 160, 255, 0.12);
    color: var(--color-base-1);
}

.icon--default {
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.4);
}

/* ── Content ── */
.notif-content {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.notif-service-title {
    font-size: 1rem;
    font-weight: 400;
    color: rgba(255, 255, 255, 0.75);
    margin: 0 0 0.15rem;
}

.notif-msg {
    font-size: 1rem;
    color: #fff;
    margin: 0 0 0.3rem;
    line-height: 1.45;
}

.notif-msg--sub {
    font-size: 0.92rem;
    color: rgba(255, 255, 255, 0.75);
}

.notif-reason {
    font-size: 0.8rem;
    color: rgba(239, 68, 68, 0.8);
    margin: 0 0 0.3rem;
    padding: 0.2rem 0.5rem;
    /* background: rgba(239, 68, 68, 0.06); */
    border-radius: 5px;

    .notif-reason--sub {
        color: rgba(255, 255, 255, 0.75);
    }
}

.notif-header {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.notif-header .notif-service-title,
.notif-header .notif-msg--headline {
    flex: 1;
    min-width: 0;
    margin: 0;
    padding: 0;
    font-size: 0.8rem;
    font-weight: 400;
    color: rgba(255, 255, 255, 0.75);
}

.notif-footer-row {
    display: flex;
    justify-content: flex-end;
    margin-top: auto;
    padding-top: 0.5rem;
}

.notif-time {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.45);
}

/* ── Category tag ── */
.notif-cat-tag {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 2px 8px;
    border-radius: 6px;
    flex-shrink: 0;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
}

.cat--personal {
    background: rgba(255, 255, 255, 0.07);
    color: rgba(255, 255, 255, 0.3);
}

.cat--service {
    background: rgba(160, 160, 255, 0.12);
    color: rgba(160, 160, 255, 0.7);
}

.cat--order {
    background: rgba(96, 165, 250, 0.1);
    color: rgba(96, 165, 250, 0.6);
}

/* ── Empty state ── */
.notif-empty {
    padding: 2.5rem 1.5rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.6rem;
}

.empty-icon {
    opacity: 0.4;
}

.empty-text {
    font-size: 0.86rem;
    color: rgba(255, 255, 255, 0.25);
    margin: 0;
}

/* ── Skeleton loader ── */
.notif-skeleton-list {
    padding: 0.25rem 0;
}

.notif-skeleton-item {
    display: flex;
    gap: 0.75rem;
    padding: 1.1rem 1.25rem 0.7rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.sk-icon {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    border-radius: 5px;
    background: rgba(255, 255, 255, 0.06);
    animation: sk-pulse 1.4s ease-in-out infinite;
    animation-fill-mode: backwards;
}

.sk-lines {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    justify-content: center;
}

.sk-line {
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.06);
    animation: sk-pulse 1.4s ease-in-out infinite;
    animation-fill-mode: backwards;
}

.sk-line--title {
    height: 13px;
    width: 55%;
}

.sk-line--body {
    height: 11px;
    width: 85%;
    animation-delay: 0.1s;
}

.sk-line--time {
    height: 9px;
    width: 30%;
    margin-top: 4px;
    animation-delay: 0.2s;
}

.notif-skeleton-item:nth-child(2) .sk-icon,
.notif-skeleton-item:nth-child(2) .sk-line {
    animation-delay: 0.15s;
}

.notif-skeleton-item:nth-child(3) .sk-icon,
.notif-skeleton-item:nth-child(3) .sk-line {
    animation-delay: 0.3s;
}

.notif-skeleton-item:nth-child(4) .sk-icon,
.notif-skeleton-item:nth-child(4) .sk-line {
    animation-delay: 0.45s;
}

@keyframes sk-pulse {

    0%,
    100% {
        opacity: 0.35;
    }

    50% {
        opacity: 0.8;
    }
}

/* ── Load more button ── */
.notif-load-more-wrap {
    display: flex;
    justify-content: center;
    padding: 0.75rem 1.25rem;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.notif-load-more-btn {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    width: 100%;
    padding: 0.55rem 1rem;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-top: none;
    font-size: 0.92rem;
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    transition: all 0.15s;
    overflow: hidden;
    font-family: inherit;
}

.notif-load-more-btn::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    border-radius: 6px 6px 0 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.22) 50%, transparent 100%);
}

.notif-load-more-btn:hover:not(:disabled) {
    color: rgba(255, 255, 255, 0.7);
    background: rgba(255, 255, 255, 0.07);
}

.notif-load-more-btn:disabled {
    cursor: default;
}

.notif-load-more-dots {
    position: absolute;
    display: flex;
    align-items: center;
    gap: 5px;
}

/* три точки внутри кнопки */
.notif-load-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: rgba(160, 160, 255, 0.6);
    animation: notif-bounce 1s ease-in-out infinite;
}

.notif-load-dot:nth-child(2) {
    animation-delay: 0.15s;
}

.notif-load-dot:nth-child(3) {
    animation-delay: 0.30s;
}

@keyframes notif-bounce {

    0%,
    80%,
    100% {
        transform: translateY(0);
        opacity: 0.5;
    }

    40% {
        transform: translateY(-5px);
        opacity: 1;
    }
}

/* ── Animations ── */
.dropdown-enter-active,
.dropdown-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.98);
}

.badge-pop-enter-active {
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.15s;
}

.badge-pop-enter-from {
    transform: scale(0);
    opacity: 0;
}
</style>
