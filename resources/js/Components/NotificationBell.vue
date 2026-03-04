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

let pollInterval;
onMounted(() => {
    document.addEventListener('click', closeOnOutside);
    pollInterval = setInterval(() => {
        router.reload({ only: ['notifications_unread', 'service_unread'] });
    }, 60000);
});
onUnmounted(() => {
    document.removeEventListener('click', closeOnOutside);
    clearInterval(pollInterval);
});
</script>

<template>
    <div class="notif-bell">
        <button class="bell-btn" @click.stop="toggleDropdown" :class="{ 'bell-btn--active': open }">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <span v-if="totalUnread > 0" class="badge">
                {{ totalUnread > 9 ? '9+' : totalUnread }}
            </span>
        </button>

        <Transition name="dropdown">
            <div v-if="open" class="notif-dropdown">
                <!-- Tabs -->
                <div class="notif-tabs">
                    <button
                        class="notif-tab"
                        :class="{ 'notif-tab--active': activeTab === 'notifications' }"
                        @click="switchTab('notifications')"
                    >
                        Уведомления
                        <span v-if="page.props.notifications_unread > 0" class="tab-badge">
                            {{ page.props.notifications_unread }}
                        </span>
                    </button>
                    <button
                        class="notif-tab"
                        :class="{ 'notif-tab--active': activeTab === 'service' }"
                        @click="switchTab('service')"
                    >
                        Сервис
                        <span v-if="page.props.service_unread > 0" class="tab-badge">
                            {{ page.props.service_unread }}
                        </span>
                    </button>
                </div>

                <!-- Уведомления tab -->
                <template v-if="activeTab === 'notifications'">
                    <div class="notif-header">
                        <span class="notif-title">Уведомления</span>
                        <button v-if="page.props.notifications_unread > 0" @click="markAllNotifsRead" class="mark-all-btn">
                            Все прочитаны
                        </button>
                    </div>
                    <div v-if="loadingNotifs" class="notif-loading">Загрузка...</div>
                    <div v-else-if="notifications.length === 0" class="notif-empty">Уведомлений нет</div>
                    <div v-else class="notif-list">
                        <div
                            v-for="n in notifications"
                            :key="n.id"
                            class="notif-item"
                            :class="{ 'notif-item--unread': !n.read_at }"
                            @click="!n.read_at && markNotifRead(n.id)"
                        >
                            <div class="notif-dot" v-if="!n.read_at"></div>
                            <div class="notif-content">
                                <p class="notif-msg">{{ n.message }}</p>
                                <p v-if="n.reason" class="notif-reason">Причина: {{ n.reason }}</p>
                                <span class="notif-time">{{ new Date(n.created_at).toLocaleDateString('ru') }}</span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Сервис tab -->
                <template v-else>
                    <div class="notif-header">
                        <span class="notif-title">Сервис</span>
                        <button v-if="page.props.service_unread > 0" @click="markAllServiceRead" class="mark-all-btn">
                            Все прочитаны
                        </button>
                    </div>
                    <div v-if="loadingService" class="notif-loading">Загрузка...</div>
                    <div v-else-if="serviceItems.length === 0" class="notif-empty">Нет сервисных уведомлений</div>
                    <div v-else class="notif-list">
                        <div
                            v-for="item in serviceItems"
                            :key="item.id"
                            class="notif-item"
                            :class="{ 'notif-item--unread': !item.read_at }"
                            @click="!item.read_at && markServiceItemRead(item)"
                        >
                            <div class="notif-dot" v-if="!item.read_at"></div>
                            <div class="notif-content">
                                <p v-if="item.title" class="notif-msg notif-msg--bold">{{ item.title }}</p>
                                <p class="notif-msg" :class="{ 'notif-msg--sub': item.title }">{{ item.message }}</p>
                                <p v-if="item.reason" class="notif-reason">Причина: {{ item.reason }}</p>
                                <span class="notif-time">{{ new Date(item.created_at).toLocaleDateString('ru') }}</span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.notif-bell { position: relative; }

.bell-btn {
    position: relative;
    width: 38px; height: 38px;
    border-radius: 50%;
    background: transparent;
    border: 1px solid transparent;
    color: rgba(255,255,255,0.5);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s;
}
.bell-btn:hover, .bell-btn--active {
    background: rgba(200,70,126,0.08);
    border-color: rgba(200,70,126,0.22);
    color: rgba(255,255,255,0.85);
}

.badge {
    position: absolute;
    top: 3px; right: 3px;
    min-width: 16px; height: 16px;
    background: #C8467E;
    border-radius: 999px;
    font-size: 0.65rem; font-weight: 700; color: #fff;
    display: flex; align-items: center; justify-content: center;
    padding: 0 3px; line-height: 1;
}

.notif-dropdown {
    position: absolute;
    top: calc(100% + 8px); right: 0;
    width: 340px;
    background: #111120;
    border: 1px solid rgba(200,70,126,0.2);
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
    z-index: 500; overflow: hidden;
}

.notif-tabs {
    display: flex;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.notif-tab {
    flex: 1; padding: 0.65rem 0.5rem;
    background: none; border: none;
    font-size: 0.8rem; color: rgba(255,255,255,0.45);
    cursor: pointer; transition: color 0.12s;
    display: flex; align-items: center; justify-content: center; gap: 0.35rem;
}
.notif-tab:hover { color: rgba(255,255,255,0.7); }
.notif-tab--active { color: #C8467E; box-shadow: inset 0 -2px 0 #C8467E; }

.tab-badge {
    min-width: 14px; height: 14px;
    background: #C8467E; border-radius: 999px;
    font-size: 0.6rem; font-weight: 700; color: #fff;
    display: flex; align-items: center; justify-content: center; padding: 0 3px;
}

.notif-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.7rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.notif-title { font-size: 0.82rem; font-weight: 600; color: rgba(255,255,255,0.6); }
.mark-all-btn { font-size: 0.72rem; color: #C8467E; background: none; border: none; cursor: pointer; padding: 0.2rem 0.5rem; }

.notif-loading, .notif-empty {
    padding: 1.5rem; text-align: center;
    font-size: 0.82rem; color: rgba(255,255,255,0.3);
}

.notif-list { max-height: 320px; overflow-y: auto; }

.notif-item {
    display: flex; align-items: flex-start; gap: 0.6rem;
    padding: 0.8rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    cursor: pointer; transition: background 0.12s;
}
.notif-item:hover { background: rgba(255,255,255,0.02); }
.notif-item--unread { background: rgba(200,70,126,0.04); }

.notif-dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: #C8467E; flex-shrink: 0; margin-top: 5px;
}

.notif-content { flex: 1; min-width: 0; }
.notif-msg { font-size: 0.84rem; color: rgba(255,255,255,0.8); margin: 0 0 0.2rem; line-height: 1.4; }
.notif-msg--bold { font-weight: 600; }
.notif-msg--sub { font-size: 0.8rem; color: rgba(255,255,255,0.55); }
.notif-reason { font-size: 0.78rem; color: rgba(255,107,107,0.8); margin: 0.15rem 0 0; }
.notif-time { font-size: 0.74rem; color: rgba(255,255,255,0.3); }

.dropdown-enter-active, .dropdown-leave-active { transition: opacity 0.15s, transform 0.15s; }
.dropdown-enter-from, .dropdown-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
