<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

const page = usePage();
const open = ref(false);
const activeTab = ref('notifications');

const notifications = ref([]);
const serviceItems = ref([]);
const loadingNotifs = ref(false);
const loadingService = ref(false);

async function fetchNotifications() {
    loadingNotifs.value = true;
    try {
        const res = await axios.get(route('notifications.index'));
        notifications.value = res.data.notifications;
    } finally {
        loadingNotifs.value = false;
    }
}

async function fetchService() {
    loadingService.value = true;
    try {
        const res = await axios.get(route('notifications.service'));
        serviceItems.value = res.data.items;
    } finally {
        loadingService.value = false;
    }
}

function toggleDropdown() {
    open.value = !open.value;
    if (open.value) {
        if (activeTab.value === 'notifications') fetchNotifications();
        else fetchService();
    }
}

function switchTab(tab) {
    activeTab.value = tab;
    if (tab === 'notifications' && notifications.value.length === 0) fetchNotifications();
    else if (tab === 'service' && serviceItems.value.length === 0) fetchService();
}

async function markNotifRead(id) {
    await axios.patch(route('notifications.read', id));
    const n = notifications.value.find(x => x.id === id);
    if (n) n.read_at = new Date().toISOString();
    router.reload({ only: ['notifications_unread'] });
}

async function markAllNotifsRead() {
    await axios.patch(route('notifications.read-all'));
    notifications.value.forEach(n => { n.read_at = n.read_at || new Date().toISOString(); });
    router.reload({ only: ['notifications_unread'] });
}

async function markServiceItemRead(item) {
    if (item.source === 'notification') {
        await axios.patch(route('notifications.read', item.id));
    } else {
        await axios.patch(route('broadcasts.read', item.broadcast_id));
    }
    item.read_at = new Date().toISOString();
    router.reload({ only: ['service_unread'] });
}

async function markAllServiceRead() {
    await axios.patch(route('notifications.service.read-all'));
    serviceItems.value.forEach(i => { i.read_at = i.read_at || new Date().toISOString(); });
    router.reload({ only: ['service_unread'] });
}

function closeOnOutside(e) {
    if (!e.target.closest('.notif-bell')) open.value = false;
}

const totalUnread = computed(() =>
    (page.props.notifications_unread ?? 0) + (page.props.service_unread ?? 0)
);

function reloadCounts() {
    router.reload({ only: ['notifications_unread', 'service_unread'] });
}

function relativeTime(dateStr) {
    const diff = (Date.now() - new Date(dateStr)) / 1000;
    if (diff < 60) return 'только что';
    if (diff < 3600) return `${Math.floor(diff / 60)} мин. назад`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} ч. назад`;
    if (diff < 2592000) return `${Math.floor(diff / 86400)} дн. назад`;
    return new Date(dateStr).toLocaleDateString('ru', { day: 'numeric', month: 'short' });
}

// Иконка по типу уведомления
function notifIcon(n) {
    const msg = (n.message || n.title || '').toLowerCase();
    if (msg.includes('одобр') || msg.includes('утвержд')) return '✦';
    if (msg.includes('отклон') || msg.includes('отказ')) return '✕';
    if (msg.includes('рассылк') || msg.includes('broadcast')) return '📣';
    return '●';
}

function notifIconClass(n) {
    const msg = (n.message || n.title || '').toLowerCase();
    if (msg.includes('одобр') || msg.includes('утвержд')) return 'icon--success';
    if (msg.includes('отклон') || msg.includes('отказ')) return 'icon--danger';
    if (msg.includes('рассылк') || msg.includes('broadcast')) return 'icon--broadcast';
    return 'icon--default';
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
                    <div class="notif-panel-tabs">
                        <button
                            class="panel-tab"
                            :class="{ 'panel-tab--active': activeTab === 'notifications' }"
                            @click="switchTab('notifications')"
                        >
                            Личные
                            <span v-if="page.props.notifications_unread > 0" class="panel-tab-dot"></span>
                        </button>
                        <button
                            class="panel-tab"
                            :class="{ 'panel-tab--active': activeTab === 'service' }"
                            @click="switchTab('service')"
                        >
                            Сервис
                            <span v-if="page.props.service_unread > 0" class="panel-tab-dot"></span>
                        </button>
                    </div>
                </div>

                <!-- Уведомления -->
                <template v-if="activeTab === 'notifications'">
                    <div v-if="loadingNotifs" class="notif-skeleton-wrap">
                        <div class="notif-skeleton" v-for="i in 3" :key="i"></div>
                    </div>
                    <template v-else>
                        <div v-if="notifications.length === 0" class="notif-empty">
                            <div class="empty-icon">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" opacity="0.3">
                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                                </svg>
                            </div>
                            <p class="empty-text">Нет уведомлений</p>
                        </div>
                        <div v-else class="notif-list">
                            <div
                                v-for="n in notifications"
                                :key="n.id"
                                class="notif-item"
                                :class="{ 'notif-item--unread': !n.read_at }"
                                @click="!n.read_at && markNotifRead(n.id)"
                            >
                                <div class="notif-icon-wrap" :class="notifIconClass(n)">
                                    <span class="notif-icon-char">{{ notifIcon(n) }}</span>
                                </div>
                                <div class="notif-content">
                                    <p class="notif-msg">{{ n.message }}</p>
                                    <p v-if="n.reason" class="notif-reason">{{ n.reason }}</p>
                                    <span class="notif-time">{{ relativeTime(n.created_at) }}</span>
                                </div>
                                <div v-if="!n.read_at" class="notif-unread-dot"></div>
                            </div>
                        </div>
                        <div v-if="page.props.notifications_unread > 0" class="notif-footer">
                            <button @click="markAllNotifsRead" class="footer-btn">
                                Отметить все прочитанными
                            </button>
                        </div>
                    </template>
                </template>

                <!-- Сервис -->
                <template v-else>
                    <div v-if="loadingService" class="notif-skeleton-wrap">
                        <div class="notif-skeleton" v-for="i in 3" :key="i"></div>
                    </div>
                    <template v-else>
                        <div v-if="serviceItems.length === 0" class="notif-empty">
                            <div class="empty-icon">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" opacity="0.3">
                                    <path d="M3 11l19-9-9 19-2-8-8-2z"/>
                                </svg>
                            </div>
                            <p class="empty-text">Нет сервисных уведомлений</p>
                        </div>
                        <div v-else class="notif-list">
                            <div
                                v-for="item in serviceItems"
                                :key="item.id"
                                class="notif-item"
                                :class="{ 'notif-item--unread': !item.read_at }"
                                @click="!item.read_at && markServiceItemRead(item)"
                            >
                                <div class="notif-icon-wrap icon--broadcast">
                                    <span class="notif-icon-char">📣</span>
                                </div>
                                <div class="notif-content">
                                    <p v-if="item.title" class="notif-service-title">{{ item.title }}</p>
                                    <p class="notif-msg" :class="{ 'notif-msg--sub': item.title }">{{ item.message }}</p>
                                    <p v-if="item.reason" class="notif-reason">{{ item.reason }}</p>
                                    <span class="notif-time">{{ relativeTime(item.created_at) }}</span>
                                </div>
                                <div v-if="!item.read_at" class="notif-unread-dot"></div>
                            </div>
                        </div>
                        <div v-if="page.props.service_unread > 0" class="notif-footer">
                            <button @click="markAllServiceRead" class="footer-btn">
                                Отметить все прочитанными
                            </button>
                        </div>
                    </template>
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
    width: 38px; height: 38px;
    border-radius: 10px;
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
    background: rgba(200,70,126,0.1);
    border-color: rgba(200,70,126,0.3);
    color: #C8467E;
}

.badge {
    position: absolute;
    top: 4px; right: 4px;
    min-width: 15px; height: 15px;
    background: #C8467E;
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
    width: 360px;
    background: #0f0f1d;
    border: 1px solid rgba(200,70,126,0.18);
    border-radius: 16px;
    box-shadow: 0 16px 48px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.04) inset;
    z-index: 500; overflow: hidden;
}

/* ── Panel header ── */
.notif-panel-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1.1rem 0;
}
.notif-panel-title {
    font-size: 0.78rem; font-weight: 600;
    color: rgba(255,255,255,0.35);
    text-transform: uppercase; letter-spacing: 0.06em;
}
.notif-panel-tabs {
    display: flex; gap: 2px;
    background: rgba(255,255,255,0.04);
    border-radius: 8px; padding: 3px;
}
.panel-tab {
    position: relative;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    background: none; border: none;
    font-size: 0.78rem; color: rgba(255,255,255,0.4);
    cursor: pointer; transition: all 0.15s;
    display: flex; align-items: center; gap: 5px;
}
.panel-tab:hover { color: rgba(255,255,255,0.7); }
.panel-tab--active {
    background: rgba(200,70,126,0.18);
    color: #C8467E;
}
.panel-tab-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: #C8467E;
    flex-shrink: 0;
}

/* ── Notification list ── */
.notif-list { max-height: 340px; overflow-y: auto; margin-top: 0.75rem; }
.notif-list::-webkit-scrollbar { width: 3px; }
.notif-list::-webkit-scrollbar-track { background: transparent; }
.notif-list::-webkit-scrollbar-thumb { background: rgba(200,70,126,0.3); border-radius: 99px; }

.notif-item {
    display: flex; align-items: flex-start; gap: 0.75rem;
    padding: 0.85rem 1.1rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    cursor: pointer; transition: background 0.12s;
    position: relative;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: rgba(255,255,255,0.025); }
.notif-item--unread { background: rgba(200,70,126,0.04); }
.notif-item--unread:hover { background: rgba(200,70,126,0.07); }

/* unread left accent */
.notif-item--unread::before {
    content: '';
    position: absolute;
    left: 0; top: 20%; bottom: 20%;
    width: 2px;
    background: #C8467E;
    border-radius: 0 2px 2px 0;
}

.notif-unread-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: #C8467E; flex-shrink: 0; margin-top: 7px;
}

/* ── Icon ── */
.notif-icon-wrap {
    width: 34px; height: 34px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 0.85rem;
}
.icon--success { background: rgba(76,222,143,0.1); color: #4cde8f; }
.icon--danger  { background: rgba(239,68,68,0.1);  color: #f87171; }
.icon--broadcast { background: rgba(139,92,246,0.1); }
.icon--default { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.4); font-size: 0.5rem; }
.notif-icon-char { line-height: 1; }

/* ── Content ── */
.notif-content { flex: 1; min-width: 0; }
.notif-service-title {
    font-size: 0.84rem; font-weight: 600;
    color: rgba(255,255,255,0.85); margin: 0 0 0.15rem;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.notif-msg {
    font-size: 0.83rem; color: rgba(255,255,255,0.72);
    margin: 0 0 0.3rem; line-height: 1.45;
}
.notif-msg--sub { font-size: 0.8rem; color: rgba(255,255,255,0.5); }
.notif-reason {
    font-size: 0.77rem; color: rgba(239,68,68,0.7);
    margin: 0 0 0.25rem;
    padding: 0.2rem 0.5rem;
    background: rgba(239,68,68,0.06);
    border-radius: 5px; border-left: 2px solid rgba(239,68,68,0.3);
}
.notif-time {
    font-size: 0.72rem; color: rgba(255,255,255,0.22);
    display: block;
}

/* ── Empty state ── */
.notif-empty {
    padding: 2.5rem 1.5rem; text-align: center;
    display: flex; flex-direction: column; align-items: center; gap: 0.6rem;
    margin-top: 0.5rem;
}
.empty-icon { opacity: 0.4; }
.empty-text { font-size: 0.83rem; color: rgba(255,255,255,0.25); margin: 0; }

/* ── Skeleton loader ── */
.notif-skeleton-wrap { padding: 0.75rem 1.1rem; display: flex; flex-direction: column; gap: 0.75rem; margin-top: 0.5rem; }
.notif-skeleton {
    height: 52px; border-radius: 10px;
    background: linear-gradient(90deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.07) 50%, rgba(255,255,255,0.03) 100%);
    background-size: 200% 100%;
    animation: shimmer 1.5s ease-in-out infinite;
}
@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

/* ── Footer ── */
.notif-footer {
    padding: 0.6rem 1.1rem;
    border-top: 1px solid rgba(255,255,255,0.05);
    background: rgba(255,255,255,0.015);
}
.footer-btn {
    width: 100%; padding: 0.5rem;
    background: none; border: 1px solid rgba(200,70,126,0.2);
    border-radius: 8px; color: rgba(200,70,126,0.7);
    font-size: 0.78rem; cursor: pointer; transition: all 0.15s;
}
.footer-btn:hover { background: rgba(200,70,126,0.08); border-color: rgba(200,70,126,0.35); color: #C8467E; }

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
