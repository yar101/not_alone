<script setup>
import { ref, computed, watch, inject, onMounted, onUnmounted, h, nextTick } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ElNotification, ElIcon } from 'element-plus';
import SiteModal from '@/Components/Site/SiteModal.vue';
import {
    Promotion, Trophy, CircleClose,
    DocumentAdd, CircleCheckFilled, CircleCloseFilled,
    Coin, SuccessFilled, WarningFilled, Rank, Bell,
    PictureFilled, StarFilled, ChatDotRound, Service, Van, Setting, Back,
} from '@element-plus/icons-vue';

import { useTranslations } from '@/composables/useTranslations';
import { usePushNotifications } from '@/composables/usePushNotifications';

const page = usePage();
const { __, locale } = useTranslations();
const open = ref(false);
const isMobile = ref(window.innerWidth < 768);
const onResize = () => { isMobile.value = window.innerWidth < 768; };
const { isSupported, subscribe, syncSubscription } = usePushNotifications();
const pushPermission = ref(typeof Notification !== 'undefined' ? Notification.permission : 'unsupported');

const allItems = ref([]);
const loading = ref(false);
const loadingMore = ref(false);
const hasMore = ref(false);
const beforeCursor = ref(null); // ISO string — oldest created_at seen so far
let autoReadTimer = null;

const openOrder = inject('openOrder', null);
const openConversation = inject('openConversation', null);

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
    forceZeroUnread.value = true;

    // Помечаем всё как прочитанное локально сразу для визуального отклика
    allItems.value.forEach(n => { 
        if (!n.read_at) n.read_at = new Date().toISOString(); 
    });

    if (page.props) {
        page.props.notifications_unread = 0;
        page.props.service_unread = 0;
        page.props.order_notifications_unread = 0;
        page.props.messages_notifications_unread = 0;
        page.props.follows_unread = 0;
    }

    try {
        await Promise.all([
            axios.patch(route('notifications.read-all')),
            axios.patch(route('notifications.service.read-all')),
            axios.patch(route('notifications.orders.read-all')),
            axios.patch(route('notifications.messages.read-all')),
            axios.patch(route('notifications.follows.read-all')),
        ]);
    } catch (e) {
        console.error('Failed to mark all read', e);
        // В случае ошибки сбрасываем forceZeroUnread и запрашиваем реальное состояние с сервера
        forceZeroUnread.value = false;
        showSettings.value = false;
        reloadCounts();
    }
}

function toggleDropdown() {
    open.value = !open.value;
    if (open.value) {
        fetchAll();
        if (totalUnread.value > 0) {
            markAllRead();
        }
    }
}

function handleItemClick(item) {
    const authUser = page.props.auth?.user;

    if (item.type === 'new_message' && item.data?.conversation_id) {
        open.value = false;
        openConversation?.(item.data.conversation_id);
        return;
    }
    if (item._cat === 'order' && item.order_id) {
        open.value = false;
        openOrder?.(item.order_id);
        return;
    }
    if (item.type === 'new_review' && authUser?.id) {
        open.value = false;
        router.visit(route('profile.show', { user: authUser.id }) + '#reviews');
        return;
    }
    if (item.type === 'low_rating_warning' && authUser?.id) {
        open.value = false;
        router.visit(route('profile.show', { user: authUser.id }) + '#services');
        return;
    }
    if (item._cat === 'follow' && item.data?.user_id) {
        open.value = false;
        router.visit(route('profile.show', { user: item.data.user_id }));
        return;
    }

    const contentProfileTypes = [
        'idol_approved', 'idol_rejected',
        'admin_rating', 'review_dispute_approved', 'review_dispute_rejected',
        'content_pack_approved', 'content_pack_remarks', 'content_pack_rejected',
        'content_pack_change_approved', 'content_pack_change_remarks', 'content_pack_change_rejected',
    ];
    if (contentProfileTypes.includes(item.type) && authUser?.id) {
        open.value = false;
        router.visit(route('profile.show', { user: authUser.id }) + '#content');
        return;
    }

    const serviceProfileTypes = [
        'service_approved', 'service_remarks', 'service_rejected',
        'service_change_approved', 'service_change_remarks', 'service_change_rejected',
    ];
    if (serviceProfileTypes.includes(item.type) && authUser?.id) {
        open.value = false;
        router.visit(route('profile.show', { user: authUser.id }) + '#services');
        return;
    }
}

function isClickable(item) {
    if (item.type === 'new_message' && item.data?.conversation_id) return true;
    if (item._cat === 'order' && item.order_id) return true;
    if (item._cat === 'follow' && item.data?.user_id) return true;
    const profileTypes = [
        'idol_approved', 'idol_rejected', 'low_rating_warning',
        'admin_rating', 'review_dispute_approved', 'review_dispute_rejected',
        'content_pack_approved', 'content_pack_remarks', 'content_pack_rejected',
        'content_pack_change_approved', 'content_pack_change_remarks', 'content_pack_change_rejected',
        'new_review',
        'service_approved', 'service_remarks', 'service_rejected',
        'service_change_approved', 'service_change_remarks', 'service_change_rejected',
    ];
    return profileTypes.includes(item.type);
}

function closeOnOutside(e) {
    // .site-modal-root — teleported modal content, not inside .notif-bell in DOM
    if (!e.target.closest('.notif-bell') && !e.target.closest('.site-modal-root')) {
        open.value = false;
    }
}

const STORAGE_KEY = 'notif_active_filters';
const savedFilters = localStorage.getItem(STORAGE_KEY);
const activeFilters = ref(savedFilters ? JSON.parse(savedFilters) : ['service', 'order']);

const POPUP_STORAGE_KEY = 'notif_popups_enabled';
const showSettings = ref(false);
const popupsEnabled = ref(localStorage.getItem(POPUP_STORAGE_KEY) !== 'false');

watch(popupsEnabled, (val) => {
    localStorage.setItem(POPUP_STORAGE_KEY, val ? 'true' : 'false');
});

watch(activeFilters, (val) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(val));
}, { deep: true });

function toggleFilter(filter) {
    if (filter === 'all') {
        activeFilters.value = ['all'];
        return;
    }

    const index = activeFilters.value.indexOf('all');
    if (index !== -1) {
        activeFilters.value.splice(index, 1);
    }

    const filterIndex = activeFilters.value.indexOf(filter);
    if (filterIndex === -1) {
        activeFilters.value.push(filter);
    } else {
        activeFilters.value.splice(filterIndex, 1);
    }

    if (activeFilters.value.length === 0) {
        activeFilters.value = ['all'];
    }
}

const filteredItems = computed(() => {
    if (activeFilters.value.includes('all')) return allItems.value;
    return allItems.value.filter(n => activeFilters.value.includes(n._cat));
});

// Подгружаем следующие окна, пока в текущем фильтре нет элементов
async function autoFetchIfEmpty() {
    while (filteredItems.value.length === 0 && hasMore.value && !loadingMore.value && beforeCursor.value !== null) {
        await fetchMore();
    }
}

watch(activeFilters, autoFetchIfEmpty, { deep: true });

const forceZeroUnread = ref(false);

const totalUnread = computed(() => {
    if (forceZeroUnread.value) return 0;
    return (page.props.notifications_unread ?? 0) +
        (page.props.service_unread ?? 0) +
        (page.props.order_notifications_unread ?? 0) +
        (page.props.messages_notifications_unread ?? 0) +
        (page.props.follows_unread ?? 0);
});

const serviceUnread = computed(() => forceZeroUnread.value ? 0 : (page.props.service_unread ?? 0));
const orderUnread = computed(() => forceZeroUnread.value ? 0 : (page.props.order_notifications_unread ?? 0));
const messagesUnread = computed(() => forceZeroUnread.value ? 0 : (page.props.messages_notifications_unread ?? 0));
const followsUnread = computed(() => forceZeroUnread.value ? 0 : (page.props.follows_unread ?? 0));
const personalUnread = computed(() => forceZeroUnread.value ? 0 : (page.props.notifications_unread ?? 0));

watch(() => page.url, () => {
    forceZeroUnread.value = false;
        showSettings.value = false;
});

watch(open, (newVal) => {
    if (!newVal) {
        forceZeroUnread.value = false;
        showSettings.value = false;
        
        // На мобильных устройствах закрытие модалки SiteModal выполняет history.back(),
        // восстанавливая Inertia-стейт страницы до открытия модалки (со старыми счетчиками).
        // С небольшой задержкой сбрасываем счетчики локально и запрашиваем актуальные данные с бэкенда.
        setTimeout(() => {
            if (page.props) {
                page.props.notifications_unread = 0;
                page.props.service_unread = 0;
                page.props.order_notifications_unread = 0;
                page.props.messages_notifications_unread = 0;
                page.props.follows_unread = 0;
            }
            reloadCounts();
        }, 200);
    }
});

function reloadCounts() {
    router.reload({ only: ['notifications_unread', 'service_unread', 'order_notifications_unread', 'messages_notifications_unread', 'follows_unread'] });
}

// ── Popup notifications ───────────────────────────────────
const latestKnownAt = ref(null);

function getNotificationTitle(item) {
    if (item._cat === 'order') {
        return {
            order_created: __('notification.type.order_created'),
            order_accepted: __('notification.type.order_accepted'),
            order_cancelled: __('notification.type.order_cancelled'),
            order_paid: __('notification.type.order_paid'),
            order_completed: __('notification.type.order_completed'),
        }[item.type] ?? __('notification.type.order_created');
    }

    const type = item.type;
    const data = item.data || {};

    if (type === 'admin_broadcast') {
        const currentLocale = locale.value?.current ?? 'ru';
        const fallbackLocale = 'ru';
        return data.title_locales?.[currentLocale] || data.title_locales?.[fallbackLocale] || data.title_raw || item.title || __('notification.type.broadcast');
    }

    return {
        service_approved: __('notification.type.service_approved'),
        service_remarks: __('notification.type.service_remarks'),
        service_rejected: __('notification.type.service_rejected'),
        service_change_approved: __('notification.type.service_change_approved'),
        service_change_remarks: __('notification.type.service_change_remarks'),
        service_change_rejected: __('notification.type.service_change_rejected'),
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
        review_dispute_rejected: __('notification.type.review_dispute_rejected'),
        new_review: __('notification.type.new_review'),
        new_post: __('notification.type.new_post'),
        new_service: __('notification.type.new_service'),
        new_content_pack: __('notification.type.new_content_pack'),
        new_message: data.sender_id ? __('notification.type.new_message') : __('notification.type.support_message'),
        chat_status: __('notification.type.chat_status'),
    }[type] ?? __('notification.type.default');
}

function getNotificationMessage(item) {
    if (item._cat === 'order') return orderMessage(item);

    const type = item.type;
    const data = item.data || {};
    const params = { ...data };

    if (type === 'admin_broadcast') {
        const currentLocale = locale.value?.current ?? 'ru';
        const fallbackLocale = 'ru';
        return data.body_locales?.[currentLocale] || data.body_locales?.[fallbackLocale] || data.message_raw || item.message || '';
    }

    if (type === 'new_message') {
        if (data.message_type === 'system' && data.event) {
            return __(`notification.msg.${data.event}`);
        }
        return __('notification.msg.new_message', { name: data.sender_name || 'NOT ALONE' });
    }

    if (type === 'new_review') {
        const rating = data.rating || 5;
        params.stars = '★'.repeat(rating) + '☆'.repeat(5 - rating);
    }

    if (type === 'new_post' || type === 'new_service' || type === 'new_content_pack') {
        params.name = data.user_name;
    }

    if (type === 'admin_rating') {
        params.delta = (data.delta > 0 ? '+' : '') + data.delta;
        params.rating = data.new_rating;
        params.note = data.note ? `${__('common.reason')}: ${data.note}` : '';
    }

    if (type === 'service_rejected') {
        params.name = data.service_name;
        params.reason = data.rejection_reason;
    }

    if (type === 'service_approved') {
        params.name = data.service_name;
    }

    if (type === 'service_remarks') {
        params.name = data.service_name;
    }

    if (type === 'service_change_approved') {
        params.name = data.service_name;
        const fieldNames = (data.fields || []).map(f => __('service.field.' + f));
        params.fields = fieldNames.join(', ');
    }

    if (type === 'service_change_remarks') {
        params.name = data.service_name;
    }

    if (type === 'service_change_rejected') {
        params.name = data.service_name;
        params.note = data.reason ? `${__('common.reason')}: ${data.reason}` : '';
    }

    if (type === 'content_pack_approved' || type === 'content_pack_rejected' || type === 'content_pack_remarks' || type === 'content_pack_change_remarks') {
        params.title = data.pack_title;
    }

    if (type === 'content_pack_change_approved') {
        params.title = data.pack_title;
        const fieldNames = (data.approved_fields || []).map(f => __('pack.field.' + f));
        params.fields = fieldNames.join(', ');
    }

    if (type === 'content_pack_change_rejected') {
        params.title = data.pack_title;
        params.note = data.admin_comment ? `${__('common.reason')}: ${data.admin_comment}` : '';
    }

    if (type === 'idol_rejected') {
        params.reason = data.reason;
    }

    if (type === 'review_dispute_approved' || type === 'review_dispute_rejected') {
        params.note = data.admin_note ? `${__('common.reason')}: ${data.admin_note}` : '';
    }

    if (type === 'test') {
        params.message = data.message_raw;
    }

    const msg = __(`notification.msg.${type}`, params);

    // Fallback for old notifications or missing keys
    if (msg === `notification.msg.${type}` && item.message) {
        return item.message;
    }

    return msg;
}

function notifPopupTitle(item) {
    return getNotificationTitle(item);
}

function renderNotif(item) {
    const iconComp = itemIconComponent(item);
    const iconClass = itemIconClass(item);
    const title = getNotificationTitle(item);
    const message = getNotificationMessage(item);

    return { iconComp, iconClass, title, message };
}

function showNotifPopup(item) {
    if (isMobile.value) return;
    if (!popupsEnabled.value) return;
    const { iconComp, iconClass, title, message } = renderNotif(item);
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
    forceZeroUnread.value = false;
        showSettings.value = false;
    try {
        const { data } = await axios.get(route('notifications.combined'));
        if (data.items.length > 0) {
            // Ищем новые уведомления, которых мы еще не видели
            const fresh = data.items.filter(n => !latestKnownAt.value || n.created_at > latestKnownAt.value);
            
            for (const item of fresh.slice(0, 3)) {
                showNotifPopup(item);
                await new Promise(r => setTimeout(r, 50));
            }
            
            latestKnownAt.value = data.items[0].created_at;
            
            if (open.value) {
                // Если колокольчик открыт, обновляем список и сразу помечаем как прочитанное
                allItems.value = data.items;
                await markAllRead();
                return;
            }
        }
    } catch (e) { 
        console.error('Error handling new notification', e);
    }
    reloadCounts();
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
    if (item._cat === 'follow') {
        return Van;
    }
    if (item._cat === 'service' || item._cat === 'message') {
        if (item.type === 'new_message' && !item.data?.sender_id) {
            return Service;
        }
        return {
            service_approved: Service,
            service_remarks: Service,
            service_rejected: Service,
            service_change_approved: Service,
            service_change_remarks: Service,
            service_change_rejected: Service,
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
            new_message: ChatDotRound,
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
    if (item._cat === 'service' || item._cat === 'message') {
        if (item.type === 'admin_broadcast') return 'icon--broadcast';
        if (
            item.type === 'idol_approved' ||
            item.type === 'review_dispute_approved' ||
            item.type === 'content_pack_approved' ||
            item.type === 'content_pack_change_approved' ||
            item.type === 'service_approved' ||
            item.type === 'service_change_approved'
        ) return 'icon--success';
        if (
            item.type === 'idol_rejected' ||
            item.type === 'review_dispute_rejected' ||
            item.type === 'content_pack_remarks' ||
            item.type === 'content_pack_change_remarks' ||
            item.type === 'service_remarks' ||
            item.type === 'service_change_remarks'
        ) return 'icon--warning';
        if (
            item.type === 'content_pack_rejected' ||
            item.type === 'content_pack_change_rejected' ||
            item.type === 'service_rejected' ||
            item.type === 'service_change_rejected'
        ) return 'icon--danger';
        if (item.type === 'new_review') return 'icon--success';
        if (item.type === 'new_message') return 'icon--personal';
        if (item.type === 'new_post' || item.type === 'new_service' || item.type === 'new_content_pack') return 'icon--success';
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

async function requestPush() {
    try {
        await subscribe();
    } catch (e) {
        console.error('Push subscription failed', e);
    } finally {
        if (typeof Notification !== 'undefined') {
            pushPermission.value = Notification.permission;
        }
    }
}

onMounted(() => {
    window.addEventListener('resize', onResize);
    document.addEventListener('click', closeOnOutside);

    if (isSupported() && page.props.auth?.user) {
        if (Notification.permission === 'granted') {
            syncSubscription();
        }
    }

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
    window.removeEventListener('resize', onResize);
    document.removeEventListener('click', closeOnOutside);
    window.Echo.leaveChannel('notifications.global');
    const userId = page.props.auth?.user?.id;
    if (userId) {
        window.Echo.leaveChannel(`private-App.Models.User.${userId}`);
    }
});

defineExpose({ toggleDropdown });
</script>

<template>
    <div class="notif-bell">
        <!-- Bell button -->
        <button class="bell-btn" @click.stop="toggleDropdown" :class="{ 'bell-btn--active': open }"
            :aria-label="__('notification.title')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            <Transition name="badge-pop">
                <span v-if="totalUnread > 0" class="badge" :key="totalUnread">
                    {{ totalUnread > 99 ? '99+' : totalUnread }}
                </span>
            </Transition>
        </button>

        <!-- Desktop: dropdown -->
        <Transition name="dropdown">
            <div v-if="open && !isMobile" class="notif-dropdown">

                <!-- Header -->
                <div class="notif-panel-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <button type="button" v-if="showSettings" @click.stop="showSettings = false" class="notif-back-btn">
                                <el-icon><Back /></el-icon>
                            </button>
                            <span class="notif-panel-title">{{ showSettings ? 'Настройки' : __('notification.title') }}</span>
                        </div>
                        <button type="button" v-if="!showSettings" @click.stop="showSettings = true" class="notif-settings-btn">
                            <el-icon><Setting /></el-icon>
                        </button>
                    </div>
                </div>

                <div v-if="showSettings" class="notif-settings-panel">
                    <div class="notif-setting-item">
                        <span class="notif-setting-label">Всплывающие окна на экране</span>
                        <label class="toggle-switch">
                            <input type="checkbox" v-model="popupsEnabled" style="display: none;">
                            <div class="slider" :class="{ 'slider--on': popupsEnabled }"></div>
                        </label>
                    </div>

                    <div class="notif-setting-group">
                        <div class="notif-setting-group-title">Отображаемые категории</div>
                        <div class="notif-filters-compact">
                            <label class="compact-filter" :class="{ 'compact-filter--active': activeFilters.includes('all') }">
                                <input type="checkbox" :checked="activeFilters.includes('all')" @change="toggleFilter('all')" style="display:none;">
                                {{ __('notification.tab.all') }}
                            </label>
                            <label class="compact-filter" :class="{ 'compact-filter--active': activeFilters.includes('service') }">
                                <input type="checkbox" :checked="activeFilters.includes('service')" @change="toggleFilter('service')" style="display:none;">
                                {{ __('notification.tab.service') }}
                            </label>
                            <label class="compact-filter" :class="{ 'compact-filter--active': activeFilters.includes('order') }">
                                <input type="checkbox" :checked="activeFilters.includes('order')" @change="toggleFilter('order')" style="display:none;">
                                {{ __('notification.tab.orders') }}
                            </label>
                            <label class="compact-filter" :class="{ 'compact-filter--active': activeFilters.includes('message') }">
                                <input type="checkbox" :checked="activeFilters.includes('message')" @change="toggleFilter('message')" style="display:none;">
                                {{ __('notification.tab.messages') }}
                            </label>
                            <label class="compact-filter" :class="{ 'compact-filter--active': activeFilters.includes('follow') }">
                                <input type="checkbox" :checked="activeFilters.includes('follow')" @change="toggleFilter('follow')" style="display:none;">
                                {{ __('notification.tab.follows') }}
                            </label>
                        </div>
                    </div>
                </div>

                <template v-else>
                    <!-- Push permission banner -->
                    <div v-if="isSupported() && pushPermission !== 'granted' && page.props.auth?.user" class="push-banner">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;opacity:.7">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        <template v-if="pushPermission === 'denied'">
                            <span class="push-banner__text">{{ __('notification.push_denied') }}</span>
                        </template>
                        <template v-else>
                            <span class="push-banner__text">{{ __('notification.push_prompt') }}</span>
                            <button class="push-banner__btn" @click.stop="requestPush">{{ __('notification.push_allow') }}</button>
                        </template>
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
                                        <p v-if="getNotificationTitle(item) && !['new_message', 'order_created', 'order_accepted', 'order_cancelled', 'order_paid', 'order_completed'].includes(item.type)" class="notif-service-title">{{ getNotificationTitle(item) }}</p>
                                        <p v-else class="notif-msg notif-msg--headline">
                                            {{ getNotificationMessage(item) }}
                                        </p>
                                        <span class="notif-cat-tag" :class="`cat--${item._cat}`">
                                            {{ item._cat === 'personal' ? __('notification.tag.personal') : item._cat === 'service' ? __('notification.tag.service') : item._cat === 'order' ? __('notification.tag.order') : item._cat === 'follow' ? __('notification.tag.follow') : __('notification.tag.message') }}
                                        </span>
                                    </div>
                                    <p v-if="getNotificationTitle(item) && !['new_message', 'order_created', 'order_accepted', 'order_cancelled', 'order_paid', 'order_completed'].includes(item.type)" class="notif-msg notif-msg--sub">
                                        {{ getNotificationMessage(item) }}
                                    </p>
                                    <div v-if="item.data?.field_comments && Object.keys(item.data.field_comments).length" class="notif-field-comments">
                                        <div class="notif-field-comments-title">Замечания</div>
                                        <div v-for="(comment, field) in item.data.field_comments" :key="field" class="notif-field-comment">
                                            <span class="notif-field-name">{{ item.type.includes('pack') ? __('pack.field.' + field) : __('service.field.' + field) }}:</span> {{ comment }}
                                        </div>
                                    </div>
                                    <p v-else-if="item.reason" class="notif-reason">
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
                </template>
            </div>
        </Transition>

        <!-- Mobile: modal -->
        <SiteModal v-if="isMobile" :show="open" variant="pink" compact fill no-padding @close="open = false">
            <div class="notif-fill-wrap">
                <div class="notif-panel-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <button type="button" v-if="showSettings" @click.stop="showSettings = false" class="notif-back-btn">
                                <el-icon><Back /></el-icon>
                            </button>
                            <span class="notif-panel-title">{{ showSettings ? 'Настройки' : __('notification.title') }}</span>
                        </div>
                        <button type="button" v-if="!showSettings" @click.stop="showSettings = true" class="notif-settings-btn">
                            <el-icon><Setting /></el-icon>
                        </button>
                    </div>
                </div>

                <div v-if="showSettings" class="notif-settings-panel" style="flex:1; overflow-y:auto;">
                    <div class="notif-setting-item">
                        <span class="notif-setting-label">Всплывающие окна на экране</span>
                        <label class="toggle-switch">
                            <input type="checkbox" v-model="popupsEnabled" style="display: none;">
                            <div class="slider" :class="{ 'slider--on': popupsEnabled }"></div>
                        </label>
                    </div>

                    <div class="notif-setting-group">
                        <div class="notif-setting-group-title">Отображаемые категории</div>
                        <div class="notif-filters-compact">
                            <label class="compact-filter" :class="{ 'compact-filter--active': activeFilters.includes('all') }">
                                <input type="checkbox" :checked="activeFilters.includes('all')" @change="toggleFilter('all')" style="display:none;">
                                {{ __('notification.tab.all') }}
                            </label>
                            <label class="compact-filter" :class="{ 'compact-filter--active': activeFilters.includes('service') }">
                                <input type="checkbox" :checked="activeFilters.includes('service')" @change="toggleFilter('service')" style="display:none;">
                                {{ __('notification.tab.service') }}
                            </label>
                            <label class="compact-filter" :class="{ 'compact-filter--active': activeFilters.includes('order') }">
                                <input type="checkbox" :checked="activeFilters.includes('order')" @change="toggleFilter('order')" style="display:none;">
                                {{ __('notification.tab.orders') }}
                            </label>
                            <label class="compact-filter" :class="{ 'compact-filter--active': activeFilters.includes('message') }">
                                <input type="checkbox" :checked="activeFilters.includes('message')" @change="toggleFilter('message')" style="display:none;">
                                {{ __('notification.tab.messages') }}
                            </label>
                            <label class="compact-filter" :class="{ 'compact-filter--active': activeFilters.includes('follow') }">
                                <input type="checkbox" :checked="activeFilters.includes('follow')" @change="toggleFilter('follow')" style="display:none;">
                                {{ __('notification.tab.follows') }}
                            </label>
                        </div>
                    </div>
                </div>

                <template v-else>
                    <div v-if="isSupported() && pushPermission !== 'granted' && page.props.auth?.user" class="push-banner">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;opacity:.7">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        <template v-if="pushPermission === 'denied'">
                            <span class="push-banner__text">{{ __('notification.push_denied') }}</span>
                        </template>
                        <template v-else>
                            <span class="push-banner__text">{{ __('notification.push_prompt') }}</span>
                            <button class="push-banner__btn" @click.stop="requestPush">{{ __('notification.push_allow') }}</button>
                        </template>
                    </div>

                    <div class="notif-fill-scroll">
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

                            <template v-else>
                                <div v-for="item in filteredItems" :key="item.id" class="notif-item" :class="{
                                    'notif-item--unread': !item.read_at,
                                    'notif-item--clickable': isClickable(item),
                                }" @click="handleItemClick(item)">
                                    <div class="notif-content">
                                        <div class="notif-header">
                                            <div class="notif-icon-wrap" :class="itemIconClass(item)">
                                                <el-icon><component :is="itemIconComponent(item)" /></el-icon>
                                            </div>
                                            <p v-if="getNotificationTitle(item) && !['new_message', 'order_created', 'order_accepted', 'order_cancelled', 'order_paid', 'order_completed'].includes(item.type)" class="notif-service-title">{{ getNotificationTitle(item) }}</p>
                                            <p v-else class="notif-msg notif-msg--headline">
                                                {{ getNotificationMessage(item) }}
                                            </p>
                                            <span class="notif-cat-tag" :class="`cat--${item._cat}`">
                                                {{ item._cat === 'personal' ? __('notification.tag.personal') : item._cat === 'service' ? __('notification.tag.service') : item._cat === 'order' ? __('notification.tag.order') : item._cat === 'follow' ? __('notification.tag.follow') : __('notification.tag.message') }}
                                            </span>
                                        </div>
                                        <p v-if="getNotificationTitle(item) && !['new_message', 'order_created', 'order_accepted', 'order_cancelled', 'order_paid', 'order_completed'].includes(item.type)" class="notif-msg notif-msg--sub">
                                            {{ getNotificationMessage(item) }}
                                        </p>
                                        <div v-if="item.data?.field_comments && Object.keys(item.data.field_comments).length" class="notif-field-comments">
                                            <div class="notif-field-comments-title">Замечания</div>
                                            <div v-for="(comment, field) in item.data.field_comments" :key="field" class="notif-field-comment">
                                                <span class="notif-field-name">{{ item.type.includes('pack') ? __('pack.field.' + field) : __('service.field.' + field) }}:</span> {{ comment }}
                                            </div>
                                        </div>
                                        <p v-else-if="item.reason" class="notif-reason">
                                            <span class="notif-reason--sub">{{ __('notification.reason') }}</span> {{ item.reason }}
                                        </p>
                                        <div class="notif-footer-row">
                                            <span class="notif-time">{{ relativeTime(item.created_at) }}</span>
                                        </div>
                                    </div>
                                </div>

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
                            </template>
                        </template>
                    </div>
                </template>
            </div>
        </SiteModal>
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

@media (hover: hover) {
    .bell-btn:hover {
        background: rgba(255, 255, 255, 0.06);
        border-color: rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.8);
    }
}

.bell-btn--active {
    background: rgba(255, 178, 239, 0.1);
    border-color: rgba(255, 178, 239, 0.3);
    color: var(--color-base-1);
}
@media (max-width: 768px) {
    .bell-btn {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.08);
        color: rgba(255, 255, 255, 0.55);
    }
    .bell-btn--active {
        background: rgba(255, 178, 239, 0.12);
        border-color: rgba(255, 178, 239, 0.28);
        color: var(--color-base-1);
    }
}

.badge {
    position: absolute;
    top: -2px;
    right: -2px;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    background: rgba(255, 178, 239, 0.6); /* Semi-transparent color-base-1 */
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 178, 239, 0.4);
    border-radius: 999px;
    
    font-size: 0.65rem;
    font-weight: 700;
    color: #ffffff;
    
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    pointer-events: none;
    z-index: 10;
}

@media (max-width: 768px) {
    .badge {
        top: -3px;
        right: -3px;
        min-width: 20px;
        height: 20px;
        font-size: 0.7rem;
    }
}

/* ── Dropdown panel ── */
.notif-dropdown {
    position: absolute;
    top: calc(100% + 24px);
    right: 0;
    width: min(500px, calc(100vw - 1rem));
    background: #0f0f1d;
    border: 1px solid rgba(255, 178, 239, 0.18);
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
    background: linear-gradient(90deg, transparent 0%, rgba(255, 178, 239, 0.3) 50%, transparent 100%);
    pointer-events: none;
}

/* ── Panel header ── */
.notif-panel-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(255, 178, 239, 0.15);
}

.notif-panel-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 178, 239, 0.75);
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

/* ── Filter buttons ── */
.notif-filters {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.notif-filter-row {
    display: flex;
    justify-content: center;
    gap: 7px;
    flex-wrap: wrap;
}

.notif-filter-btn {
    position: relative;
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    font-size: 0.88rem;
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    transition: all 0.15s;
    overflow: hidden;
}

@media (hover: hover) {
    .notif-filter-btn:hover {
        color: rgba(255, 255, 255, 0.7);
        background: rgba(255, 255, 255, 0.07);
    }
}

.notif-filter-btn--active {
    background: rgba(255, 178, 239, 0.22);
    border-color: rgba(255, 178, 239, 0.55);
    color: var(--color-base-1);
    box-shadow: inset 0 1px 0 rgba(255, 178, 239, 0.25);
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
    scrollbar-color: rgba(255, 178, 239, 0.3) transparent;
}

.notif-list:deep(::-webkit-scrollbar) {
    width: 3px;
}

.notif-list:deep(::-webkit-scrollbar-track) {
    background: transparent;
}

.notif-list:deep(::-webkit-scrollbar-thumb) {
    background: rgba(255, 178, 239, 0.3);
    border-radius: 99px;
}

.notif-list:deep(::-webkit-scrollbar-thumb:hover) {
    background: rgba(255, 178, 239, 0.6);
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
    background: rgba(255, 178, 239, 0.04);
}

.notif-item--unread:hover {
    background: rgba(255, 178, 239, 0.07);
}

.notif-item--clickable {
    cursor: pointer;
}

.notif-icon-wrap {
    width: 32px;
    height: 32px;
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
}

.notif-icon-wrap .el-icon {
    font-size: 0.95rem;
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
    background: rgba(255, 178, 239, 0.12);
    color: var(--color-base-1);
}

.icon--warning {
    background: rgba(251, 191, 36, 0.12);
    color: #fbbf24;
}

.icon--personal {
    background: rgba(255, 178, 239, 0.12);
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
    font-size: 0.95rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.92);
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
    background: rgba(255, 178, 239, 0.12);
    color: rgba(255, 178, 239, 0.7);
}

.cat--order {
    background: rgba(96, 165, 250, 0.1);
    color: rgba(96, 165, 250, 0.6);
}

.cat--message {
    background: rgba(255, 178, 239, 0.12);
    color: rgba(255, 178, 239, 0.7);
}

.cat--follow {
    background: rgba(255, 178, 239, 0.12);
    color: rgba(255, 178, 239, 0.7);
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

/* ── Push banner ── */
.push-banner {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.65rem 1.25rem;
    background: rgba(255, 178, 239, 0.06);
    border-bottom: 1px solid rgba(255, 178, 239, 0.12);
    font-size: 0.84rem;
    color: rgba(255, 255, 255, 0.55);
}

.push-banner__text {
    flex: 1;
    line-height: 1.35;
}

.push-banner__btn {
    flex-shrink: 0;
    padding: 0.25rem 0.7rem;
    border-radius: 5px;
    background: rgba(255, 178, 239, 0.18);
    border: 1px solid rgba(255, 178, 239, 0.3);
    color: rgba(255, 178, 239, 0.95);
    font-size: 0.82rem;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.12s;
}

.push-banner__btn:hover {
    background: rgba(255, 178, 239, 0.28);
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
    background: rgba(255, 178, 239, 0.6);
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

@media (max-width: 540px) {
    .notif-dropdown {
        position: fixed;
        top: 60px;
        left: 0.5rem;
        right: 0.5rem;
        width: auto;
        max-height: calc(100vh - 80px);
        overflow-y: auto;
    }
    .notif-list {
        max-height: calc(100vh - 180px);
    }
}

/* ── Mobile fill layout ── */
.notif-fill-wrap {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
}

.notif-fill-wrap > .notif-panel-header {
    flex-shrink: 0;
    padding: 1rem 1.25rem;
}

.notif-fill-wrap > .push-banner {
    flex-shrink: 0;
}

.notif-fill-scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 178, 239, 0.3) transparent;
}
</style>

<style scoped>
.toggle-switch {
    width: 28px;
    height: 16px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    position: relative;
    transition: background 0.2s;
    border: 1px solid rgba(255, 255, 255, 0.05);
}
.slider {
    position: absolute;
    top: 1px;
    left: 2px;
    width: 12px;
    height: 12px;
    background: rgba(255, 255, 255, 0.5);
    border-radius: 50%;
    transition: transform 0.2s, background 0.2s;
}
.slider--on {
    transform: translateX(12px);
    background: var(--color-base-1);
}
.popup-toggle-label:hover .toggle-switch {
    background: rgba(255, 255, 255, 0.15);
}
.popup-toggle-label:hover {
    color: rgba(255, 255, 255, 0.6) !important;
}
</style>

<style scoped>
.notif-settings-btn {
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.45);
    font-size: 1.1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.15s, transform 0.15s;
    padding: 0.2rem;
}
.notif-settings-btn:hover {
    color: var(--color-base-1);
    transform: rotate(45deg);
}
.notif-back-btn {
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.45);
    font-size: 1.1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.15s;
    padding: 0.2rem;
}
.notif-back-btn:hover {
    color: #fff;
}

.notif-settings-panel {
    padding: 1rem;
    background: rgba(0, 0, 0, 0.15);
    min-height: 200px;
}

.notif-field-comments {
    margin-top: 0.6rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.notif-field-comments-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.4);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.1rem;
}

.notif-field-comment {
    font-size: 1rem;
    line-height: 1.45;
    color: rgba(255, 255, 255, 0.85);
    background: rgba(255, 178, 239, 0.06);
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    border-left: 3px solid rgba(255, 178, 239, 0.5);
    word-break: break-word;
}

.notif-field-name {
    font-weight: 600;
    color: #ffb2ef;
    margin-right: 0.4rem;
}

.notif-setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}
.notif-setting-label {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.85);
}
.notif-setting-group {
    margin-top: 1.25rem;
}
.notif-setting-group-title {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.notif-filters-compact {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.compact-filter {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    padding: 0.35rem 0.6rem;
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    transition: all 0.15s;
}
.compact-filter:hover {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.8);
}
.compact-filter--active {
    background: rgba(255, 178, 239, 0.15);
    border-color: rgba(255, 178, 239, 0.4);
    color: var(--color-base-1);
}
</style>
