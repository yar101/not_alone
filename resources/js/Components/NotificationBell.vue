<script setup>
import { ref, computed, inject, onMounted, onUnmounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Promotion, Trophy, CircleClose } from '@element-plus/icons-vue';

const page = usePage();
const open = ref(false);

const allItems  = ref([]);
const loading   = ref(false);
let autoReadTimer = null;

const openOrder = inject('openOrder', null);

async function fetchAll() {
    loading.value = true;
    try {
        const [r1, r2, r3] = await Promise.all([
            axios.get(route('notifications.index')),
            axios.get(route('notifications.service')),
            axios.get(route('notifications.orders')),
        ]);
        const personal = r1.data.notifications.map(n => ({ ...n, _cat: 'personal' }));
        const service  = r2.data.items.map(n => ({ ...n, _cat: 'service' }));
        const orders   = r3.data.items.map(n => ({ ...n, _cat: 'order' }));
        allItems.value = [...personal, ...service, ...orders]
            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    } finally {
        loading.value = false;
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
    const profileTypes = [
        'idol_approved', 'idol_rejected', 'low_rating_warning',
        'admin_rating', 'review_dispute_approved', 'review_dispute_rejected',
    ];
    if (profileTypes.includes(item.type)) {
        open.value = false;
        router.visit(route('profile.show', { user: page.props.auth.user.id }));
    }
}

function isClickable(item) {
    if (item._cat === 'order' && item.order_id) return true;
    const profileTypes = [
        'idol_approved', 'idol_rejected', 'low_rating_warning',
        'admin_rating', 'review_dispute_approved', 'review_dispute_rejected',
    ];
    return profileTypes.includes(item.type);
}

function closeOnOutside(e) {
    if (!e.target.closest('.notif-bell')) open.value = false;
}

const totalUnread = computed(() =>
    (page.props.notifications_unread ?? 0) +
    (page.props.service_unread ?? 0) +
    (page.props.order_notifications_unread ?? 0)
);

function reloadCounts() {
    router.reload({ only: ['notifications_unread', 'service_unread', 'order_notifications_unread'] });
}

function relativeTime(dateStr) {
    const diff = (Date.now() - new Date(dateStr)) / 1000;
    if (diff < 60) return 'только что';
    if (diff < 3600) return `${Math.floor(diff / 60)} мин. назад`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} ч. назад`;
    if (diff < 2592000) return `${Math.floor(diff / 86400)} дн. назад`;
    return new Date(dateStr).toLocaleDateString('ru', { day: 'numeric', month: 'short' });
}

function itemIcon(item) {
    if (item._cat === 'order') {
        if (item.type === 'order_accepted' || item.type === 'order_completed') return '✓';
        if (item.type === 'order_cancelled') return '✕';
        if (item.type === 'order_paid') return '₽';
        return '◈';
    }
    if (item._cat === 'service') return null; // использует el-icon
    const msg = (item.message || item.title || '').toLowerCase();
    if (msg.includes('одобр') || msg.includes('утвержд')) return '✦';
    if (msg.includes('отклон') || msg.includes('отказ')) return '✕';
    return '●';
}

function itemIconClass(item) {
    if (item._cat === 'order') {
        if (item.type === 'order_accepted' || item.type === 'order_completed') return 'icon--success';
        if (item.type === 'order_cancelled') return 'icon--danger';
        if (item.type === 'order_paid') return 'icon--paid';
        return 'icon--default';
    }
    if (item._cat === 'service') {
        if (item.type === 'admin_broadcast') return 'icon--broadcast';
        if (item.type === 'idol_approved') return 'icon--success';
        if (item.type === 'idol_rejected') return 'icon--danger';
        return 'icon--default';
    }
    const msg = (item.message || item.title || '').toLowerCase();
    if (msg.includes('одобр') || msg.includes('утвержд')) return 'icon--success';
    if (msg.includes('отклон') || msg.includes('отказ')) return 'icon--danger';
    return 'icon--default';
}

function orderMessage(item) {
    if (item.type === 'order_created')   return `Новый заказ от ${item.data?.customer_name}`;
    if (item.type === 'order_accepted')  return `${item.data?.idol_name} принял(а) заказ`;
    if (item.type === 'order_cancelled') return 'Заказ отменён';
    if (item.type === 'order_paid')      return `${item.data?.customer_name} оплатил(а) заказ`;
    if (item.type === 'order_completed') return 'Заказ успешно завершён';
    return '';
}

onMounted(() => {
    document.addEventListener('click', closeOnOutside);
    window.Echo.channel('notifications.global')
        .listen('.new-notification', reloadCounts);
    const userId = page.props.auth?.user?.id;
    if (userId) {
        window.Echo.private(`App.Models.User.${userId}`)
            .listen('.new-notification', reloadCounts);
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
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
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
                    <span class="notif-panel-title">Уведомления</span>
                    <span v-if="totalUnread > 0" class="notif-panel-count">{{ totalUnread }} новых</span>
                </div>

                <!-- Loader -->
                <div v-if="loading" class="notif-loader">
                    <span class="notif-spinner"></span>
                </div>

                <template v-else>
                    <!-- Empty state -->
                    <div v-if="allItems.length === 0" class="notif-empty">
                        <div class="empty-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" opacity="0.3">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                            </svg>
                        </div>
                        <p class="empty-text">Нет уведомлений</p>
                    </div>

                    <!-- Unified list -->
                    <div v-else class="notif-list">
                        <div
                            v-for="item in allItems"
                            :key="item.id"
                            class="notif-item"
                            :class="{
                                'notif-item--unread': !item.read_at,
                                'notif-item--clickable': isClickable(item),
                            }"
                            @click="handleItemClick(item)"
                        >
                            <!-- Icon -->
                            <div class="notif-icon-wrap" :class="itemIconClass(item)">
                                <template v-if="item._cat === 'service'">
                                    <el-icon>
                                        <Promotion v-if="item.type === 'admin_broadcast'" />
                                        <Trophy v-else-if="item.type === 'idol_approved'" />
                                        <CircleClose v-else-if="item.type === 'idol_rejected'" />
                                        <span v-else class="notif-icon-char">●</span>
                                    </el-icon>
                                </template>
                                <span v-else class="notif-icon-char">{{ itemIcon(item) }}</span>
                            </div>

                            <!-- Content -->
                            <div class="notif-content">
                                <p v-if="item.title" class="notif-service-title">{{ item.title }}</p>
                                <p class="notif-msg" :class="{ 'notif-msg--sub': item.title }">
                                    <template v-if="item._cat === 'order'">{{ orderMessage(item) }}</template>
                                    <template v-else>{{ item.message }}</template>
                                </p>
                                <p v-if="item.reason" class="notif-reason">{{ item.reason }}</p>
                                <div class="notif-meta">
                                    <span class="notif-time">{{ relativeTime(item.created_at) }}</span>
                                    <span class="notif-cat-tag" :class="`cat--${item._cat}`">
                                        {{ item._cat === 'personal' ? 'Личное' : item._cat === 'service' ? 'Сервис' : 'Заказ' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

            </div>
        </Transition>
    </div>
</template>

<style scoped>
/* ── Bell button ── */
.notif-bell { position: relative; }

.bell-btn {
    position: relative;
    width: 36px; height: 36px;
    border-radius: 8px;
    background: transparent;
    border: 1px solid transparent;
    color: rgba(255,255,255,0.45);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s;
}
.bell-btn:hover {
    background: rgba(255,255,255,0.06);
    border-color: rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.8);
}
.bell-btn--active {
    background: rgba(155,110,232,0.1);
    border-color: rgba(155,110,232,0.3);
    color: #7070d8;
}

.badge {
    position: absolute;
    top: 4px; right: 4px;
    min-width: 15px; height: 15px;
    background: #7070d8;
    border-radius: 999px;
    font-size: 0.6rem; font-weight: 700; color: #fff;
    display: flex; align-items: center; justify-content: center;
    padding: 0 3px; line-height: 1;
    border: 1.5px solid #0a0a14;
}

/* ── Dropdown panel ── */
.notif-dropdown {
    position: absolute;
    top: calc(100% + 10px); right: 0;
    width: 420px;
    background: #0f0f1d;
    border: 1px solid rgba(155,110,232,0.18);
    border-radius: 16px;
    box-shadow: 0 16px 48px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.04) inset;
    z-index: 1101; overflow: hidden;
}

/* ── Panel header ── */
.notif-panel-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1.25rem 0.75rem;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.notif-panel-title {
    font-size: 0.8rem; font-weight: 600;
    color: rgba(255,255,255,0.35);
    text-transform: uppercase; letter-spacing: 0.06em;
}
.notif-panel-count {
    font-size: 0.75rem; font-weight: 600;
    color: rgba(155,110,232,0.7);
    background: rgba(155,110,232,0.1);
    padding: 2px 8px; border-radius: 99px;
}

/* ── Notification list ── */
.notif-list {
    max-height: 400px; overflow-y: auto;
    scrollbar-width: thin; scrollbar-color: rgba(155,110,232,0.3) transparent;
}
.notif-list:deep(::-webkit-scrollbar) { width: 3px; }
.notif-list:deep(::-webkit-scrollbar-track) { background: transparent; }
.notif-list:deep(::-webkit-scrollbar-thumb) { background: rgba(155,110,232,0.3); border-radius: 99px; }
.notif-list:deep(::-webkit-scrollbar-thumb:hover) { background: rgba(155,110,232,0.6); }

.notif-item {
    display: flex; align-items: flex-start; gap: 0.85rem;
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: background 0.12s;
    position: relative;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: rgba(255,255,255,0.025); }
.notif-item--unread { background: rgba(155,110,232,0.04); }
.notif-item--unread:hover { background: rgba(155,110,232,0.07); }
.notif-item--clickable { cursor: pointer; }

/* unread left accent */
.notif-item--unread::before {
    content: '';
    position: absolute;
    left: 0; top: 20%; bottom: 20%;
    width: 2px;
    background: #7070d8;
    border-radius: 0 2px 2px 0;
}

/* ── Icon ── */
.notif-icon-wrap {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 0.9rem;
}
.icon--success   { background: rgba(76,222,143,0.1);   color: #4cde8f; }
.icon--danger    { background: rgba(239,68,68,0.1);    color: #f87171; }
.icon--paid      { background: rgba(96,165,250,0.1);   color: #60a5fa; }
.icon--broadcast { background: rgba(139,92,246,0.1);   color: #a78bfa; }
.icon--default   { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.4); font-size: 0.55rem; }
.notif-icon-char { line-height: 1; }
.notif-icon-wrap .el-icon { font-size: 1rem; }

/* ── Content ── */
.notif-content { flex: 1; min-width: 0; }
.notif-service-title {
    font-size: 0.9rem; font-weight: 600;
    color: rgba(255,255,255,0.85); margin: 0 0 0.15rem;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.notif-msg {
    font-size: 0.88rem; color: rgba(255,255,255,0.72);
    margin: 0 0 0.35rem; line-height: 1.45;
}
.notif-msg--sub { font-size: 0.83rem; color: rgba(255,255,255,0.5); }
.notif-reason {
    font-size: 0.8rem; color: rgba(239,68,68,0.7);
    margin: 0 0 0.3rem;
    padding: 0.2rem 0.5rem;
    background: rgba(239,68,68,0.06);
    border-radius: 5px; border-left: 2px solid rgba(239,68,68,0.3);
}
.notif-meta {
    display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
}
.notif-time {
    font-size: 0.76rem; color: rgba(255,255,255,0.22);
}

/* ── Category tag ── */
.notif-cat-tag {
    font-size: 0.66rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.05em; padding: 2px 7px; border-radius: 4px;
    flex-shrink: 0;
}
.cat--personal { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.3); }
.cat--service  { background: rgba(155,110,232,0.12); color: rgba(155,110,232,0.7); }
.cat--order    { background: rgba(96,165,250,0.1);   color: rgba(96,165,250,0.6); }

/* ── Empty state ── */
.notif-empty {
    padding: 2.5rem 1.5rem; text-align: center;
    display: flex; flex-direction: column; align-items: center; gap: 0.6rem;
}
.empty-icon { opacity: 0.4; }
.empty-text { font-size: 0.86rem; color: rgba(255,255,255,0.25); margin: 0; }

/* ── Spinner loader ── */
.notif-loader {
    display: flex; align-items: center; justify-content: center;
    height: 100px;
}
.notif-spinner {
    width: 22px; height: 22px;
    border: 2px solid rgba(255,255,255,0.08);
    border-top-color: rgba(160,160,255,0.6);
    border-radius: 50%;
    animation: notif-spin 0.7s linear infinite;
}
@keyframes notif-spin {
    to { transform: rotate(360deg); }
}

/* ── Animations ── */
.dropdown-enter-active, .dropdown-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}
.dropdown-enter-from, .dropdown-leave-to {
    opacity: 0; transform: translateY(-8px) scale(0.98);
}

.badge-pop-enter-active { transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.15s; }
.badge-pop-enter-from { transform: scale(0); opacity: 0; }
</style>
