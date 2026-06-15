<script setup>
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import UserPickerModal from '@/Components/Admin/UserPickerModal.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    conversations: Array,
    has_more:      Boolean,
});

// ── Conversations sidebar state ────────────────────────────────────────────
const conversations  = ref(props.conversations ?? []);
const hasMoreConvs   = ref(props.has_more ?? false);
const loadingConvs   = ref(false);
const convPage       = ref(1);
const convSearch     = ref('');
const convStatus     = ref('all'); // 'all' | 'open' | 'closed'
const convListEl     = ref(null);  // sidebar list element
let convObserver     = null;
let searchTimer      = null;

// ── Chat state ────────────────────────────────────────────────────────────
const activeConv     = ref(null);
const messages       = ref([]);
const hasMore        = ref(false);
const newMessage     = ref('');
const sending        = ref(false);
const uploading      = ref(false);
const loadingMsgs    = ref(false);
const messagesEnd    = ref(null);
const messagesEl     = ref(null);
const fileInput      = ref(null);
const showNewChat    = ref(false);

let echoChannel = null;

// ── Load more conversations (lazy) ────────────────────────────────────────
async function loadMoreConvs() {
    if (loadingConvs.value || !hasMoreConvs.value) return;
    loadingConvs.value = true;
    try {
        const res = await axios.get(route('admin.support.more'), {
            params: { page: convPage.value + 1, search: convSearch.value || undefined, status: convStatus.value !== 'all' ? convStatus.value : undefined },
        });
        conversations.value.push(...res.data.conversations);
        hasMoreConvs.value = res.data.has_more;
        convPage.value++;
    } finally {
        loadingConvs.value = false;
    }
}

// ── Reload conversations (search / filter changed) ────────────────────────
async function reloadConvs() {
    loadingConvs.value = true;
    convPage.value = 1;
    try {
        const res = await axios.get(route('admin.support.more'), {
            params: { page: 1, search: convSearch.value || undefined, status: convStatus.value !== 'all' ? convStatus.value : undefined },
        });
        conversations.value = res.data.conversations;
        hasMoreConvs.value  = res.data.has_more;
    } finally {
        loadingConvs.value = false;
    }
}

function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(reloadConvs, 300);
}

watch(convStatus, reloadConvs);

// IntersectionObserver sentinel at bottom of conv list
function setupConvObserver() {
    const sentinel = document.getElementById('conv-sentinel');
    if (!sentinel) return;
    convObserver = new IntersectionObserver(entries => {
        if (entries[0].isIntersecting) loadMoreConvs();
    }, { threshold: 0.1 });
    convObserver.observe(sentinel);
}
function teardownConvObserver() {
    convObserver?.disconnect();
    convObserver = null;
}

onMounted(() => nextTick(setupConvObserver));
onUnmounted(() => {
    teardownConvObserver();
    if (activeConv.value) window.Echo?.leave('admin.support.' + activeConv.value.id);
});

// ── Open conversation ─────────────────────────────────────────────────────
async function openConversation(conv) {
    if (activeConv.value?.id === conv.id) return;

    if (echoChannel) {
        window.Echo?.leave('admin.support.' + activeConv.value?.id);
        echoChannel = null;
    }

    activeConv.value  = { ...conv, unread_count: 0 };
    messages.value    = [];
    hasMore.value     = false;
    loadingMsgs.value = true;
    updateConvInList(conv.id, { unread_count: 0 });

    try {
        const res = await axios.get(route('admin.support.messages', conv.id));
        messages.value = res.data.messages;
        hasMore.value  = res.data.has_more;
        await nextTick();
        messagesEnd.value?.scrollIntoView({ behavior: 'instant' });
    } finally {
        loadingMsgs.value = false;
    }

    if (window.Echo) {
        echoChannel = window.Echo.private('admin.support.' + conv.id)
            .listen('.message.sent', (data) => {
                if (messages.value.some(m => m.id === data.id)) return;
                messages.value.push(data);
                nextTick(scrollToBottom);

                if (data.type === 'system') {
                    if (data.metadata?.event === 'chat_closed') {
                        activeConv.value = { ...activeConv.value, closed_at: new Date().toISOString() };
                        updateConvInList(activeConv.value.id, { closed_at: activeConv.value.closed_at });
                    } else if (data.metadata?.event === 'chat_opened') {
                        activeConv.value = { ...activeConv.value, closed_at: null };
                        updateConvInList(activeConv.value.id, { closed_at: null });
                    }
                }
            });
    }

    // Subscribe to ALL support channels via global Echo to catch messages
    // on non-active conversations (for unread badges)
    subscribeGlobalEcho();
}

// ── Global Echo: badge updates for non-active convs ───────────────────────
const globalEchoChannels = new Map();

function subscribeGlobalEcho() {
    conversations.value.forEach(conv => {
        if (conv.id === activeConv.value?.id) return;
        if (globalEchoChannels.has(conv.id)) return;
        if (!window.Echo) return;
        const ch = window.Echo.private('admin.support.' + conv.id)
            .listen('.message.sent', (data) => {
                if (data.sender_id !== null) { // user message
                    updateConvInList(conv.id, {
                        unread_count: (conversations.value.find(c => c.id === conv.id)?.unread_count ?? 0) + 1,
                        last_message: { body: data.type === 'image' ? '[фото]' : data.body, created_at: data.created_at },
                    });
                }
            });
        globalEchoChannels.set(conv.id, ch);
    });
}

function updateConvInList(id, patch) {
    const idx = conversations.value.findIndex(c => c.id === id);
    if (idx !== -1) conversations.value[idx] = { ...conversations.value[idx], ...patch };
}

// ── Send text ─────────────────────────────────────────────────────────────
async function sendMessage() {
    if (!newMessage.value.trim() || !activeConv.value || sending.value) return;
    const body = newMessage.value;
    newMessage.value = '';
    sending.value = true;
    try {
        const res = await axios.post(route('admin.support.send', activeConv.value.id), { body });
        if (!messages.value.some(m => m.id === res.data.id)) messages.value.push(res.data);
        nextTick(scrollToBottom);
        updateLastMessage(activeConv.value.id, body);
    } finally {
        sending.value = false;
    }
}

function handleEnter(e) {
    if (!e.shiftKey) { e.preventDefault(); sendMessage(); }
}

// ── Upload photo ──────────────────────────────────────────────────────────
function triggerFileInput() { fileInput.value?.click(); }

async function onFileSelected(e) {
    const file = e.target.files?.[0];
    if (!file || !activeConv.value) return;
    e.target.value = '';
    if (file.size > 5 * 1024 * 1024) { alert('Файл слишком большой. Максимум 5 МБ.'); return; }
    uploading.value = true;
    try {
        const fd = new FormData();
        fd.append('file', file);
        const upRes = await axios.post(route('admin.support.upload', activeConv.value.id), fd);
        const msgRes = await axios.post(route('admin.support.image', activeConv.value.id), { image_url: upRes.data.url });
        if (!messages.value.some(m => m.id === msgRes.data.id)) messages.value.push(msgRes.data);
        nextTick(scrollToBottom);
        updateLastMessage(activeConv.value.id, '[фото]');
    } finally {
        uploading.value = false;
    }
}

function updateLastMessage(convId, body) {
    const idx = conversations.value.findIndex(c => c.id === convId);
    if (idx !== -1) {
        conversations.value[idx] = {
            ...conversations.value[idx],
            last_message: { body, created_at: new Date().toISOString() },
            updated_at: new Date().toISOString(),
        };
    }
}

// ── Load older messages (scroll up) ──────────────────────────────────────
async function onMessagesScroll(e) {
    if (e.target.scrollTop === 0 && hasMore.value && !loadingMsgs.value) loadOlder();
}

async function loadOlder() {
    if (!hasMore.value || !activeConv.value || loadingMsgs.value) return;
    const firstId = messages.value[0]?.id;
    const container = messagesEl.value;
    const prevHeight = container?.scrollHeight ?? 0;
    loadingMsgs.value = true;
    try {
        const res = await axios.get(route('admin.support.messages', activeConv.value.id), { params: { before_id: firstId } });
        messages.value = [...res.data.messages, ...messages.value];
        hasMore.value  = res.data.has_more;
        await nextTick();
        if (container) container.scrollTop = container.scrollHeight - prevHeight;
    } finally {
        loadingMsgs.value = false;
    }
}

// ── Close / Open ──────────────────────────────────────────────────────────
async function closeChat() {
    if (!activeConv.value) return;
    const res = await axios.post(route('admin.support.close', activeConv.value.id));
    activeConv.value = { ...activeConv.value, closed_at: res.data.closed_at };
    updateConvInList(activeConv.value.id, { closed_at: res.data.closed_at });
}

async function openChat() {
    if (!activeConv.value) return;
    await axios.post(route('admin.support.open', activeConv.value.id));
    activeConv.value = { ...activeConv.value, closed_at: null };
    updateConvInList(activeConv.value.id, { closed_at: null });
}

// ── New chat ──────────────────────────────────────────────────────────────
async function startChatWith(user) {
    const res = await axios.post(route('admin.support.store'), { user_id: user.id });
    const existing = conversations.value.find(c => c.id === res.data.id);
    if (!existing) conversations.value.unshift(res.data);
    openConversation(existing ?? res.data);
}

// ── Helpers ───────────────────────────────────────────────────────────────
function scrollToBottom() {
    messagesEnd.value?.scrollIntoView({ behavior: 'smooth' });
}

function fmtTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function isAdminMsg(msg) {
    return msg.type === 'support' || (msg.type === 'image' && !msg.sender_id);
}

const SYSTEM_LABELS = {
    chat_closed:    'Чат закрыт',
    chat_opened:    'Чат открыт',
    order_paid:     'Заказ оплачен',
    order_completed:'Заказ завершён',
    order_disputed: 'Открыт спор',
    order_cancelled:'Заказ отменён',
    order_accepted: 'Заказ принят',
};
</script>

<template>
    <div class="support-layout">

        <!-- ── Sidebar: conversation list ── -->
        <aside class="support-sidebar">
            <div class="support-sidebar__header">
                <span class="support-sidebar__title">Поддержка</span>
                <button class="new-chat-btn" @click="showNewChat = true" title="Новый чат">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                </button>
            </div>

            <div class="sidebar-filters">
                <input
                    v-model="convSearch"
                    @input="onSearchInput"
                    placeholder="Поиск…"
                    class="conv-search"
                />
                <div class="status-tabs">
                    <button :class="['status-tab', convStatus === 'all' && 'status-tab--active']" @click="convStatus = 'all'">Все</button>
                    <button :class="['status-tab', convStatus === 'open' && 'status-tab--active']" @click="convStatus = 'open'">Открытые</button>
                    <button :class="['status-tab', convStatus === 'closed' && 'status-tab--active']" @click="convStatus = 'closed'">Закрытые</button>
                </div>
            </div>

            <div class="support-sidebar__list" ref="convListEl">
                <div
                    v-for="conv in conversations"
                    :key="conv.id"
                    class="conv-item"
                    :class="{ 'conv-item--active': activeConv?.id === conv.id, 'conv-item--closed': conv.closed_at }"
                    @click="openConversation(conv)"
                >
                    <div class="conv-item__avatar">
                        <img v-if="conv.user?.avatar" :src="conv.user.avatar" alt="" />
                        <span v-else class="conv-item__avatar-placeholder">{{ conv.user?.name?.charAt(0) ?? '?' }}</span>
                    </div>
                    <div class="conv-item__info">
                        <div class="conv-item__name">{{ conv.user?.name ?? 'Неизвестный' }}</div>
                        <div class="conv-item__last">{{ conv.last_message?.body ?? 'Нет сообщений' }}</div>
                    </div>
                    <div class="conv-item__meta">
                        <span class="conv-item__time">{{ fmtTime(conv.last_message?.created_at ?? conv.updated_at) }}</span>
                        <span v-if="conv.unread_count > 0" class="conv-item__unread">{{ conv.unread_count }}</span>
                        <span v-else-if="conv.closed_at" class="conv-item__closed-badge">🔒</span>
                    </div>
                </div>

                <div v-if="conversations.length === 0 && !loadingConvs" class="conv-empty">Нет чатов</div>
                <div v-if="loadingConvs" class="conv-loading">Загрузка…</div>
                <div id="conv-sentinel" style="height:1px" />
            </div>
        </aside>

        <!-- ── Chat area ── -->
        <main class="support-chat">

            <template v-if="activeConv">
                <!-- Header -->
                <div class="chat-header">
                    <div class="chat-header__user">
                        <div class="chat-header__avatar">
                            <img v-if="activeConv.user?.avatar" :src="activeConv.user.avatar" alt="" />
                            <span v-else>{{ activeConv.user?.name?.charAt(0) ?? '?' }}</span>
                        </div>
                        <span class="chat-header__name">{{ activeConv.user?.name ?? 'Неизвестный' }}</span>
                        <span v-if="activeConv.closed_at" class="chat-header__closed-tag">Закрыт</span>
                    </div>
                    <div class="chat-header__actions">
                        <button v-if="!activeConv.closed_at" class="chat-action-btn chat-action-btn--close" @click="closeChat">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6L6 18M6 6l12 12"/>
                            </svg>
                            Закрыть чат
                        </button>
                        <button v-else class="chat-action-btn chat-action-btn--open" @click="openChat">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 5v14M5 12l7 7 7-7"/>
                            </svg>
                            Открыть чат
                        </button>
                    </div>
                </div>

                <!-- Messages -->
                <div class="chat-messages" ref="messagesEl" @scroll="onMessagesScroll">
                    <div v-if="hasMore" class="load-more">
                        <button @click="loadOlder" :disabled="loadingMsgs" class="load-more-btn">
                            {{ loadingMsgs ? 'Загрузка…' : 'Загрузить ещё' }}
                        </button>
                    </div>

                    <template v-for="msg in messages" :key="msg.id">
                        <!-- System event -->
                        <div v-if="msg.type === 'system'" class="sys-event">
                            <span class="sys-event__label">{{ SYSTEM_LABELS[msg.metadata?.event] ?? msg.metadata?.event }}</span>
                        </div>

                        <!-- Image message -->
                        <div v-else-if="msg.type === 'image'" class="bubble-wrap" :class="isAdminMsg(msg) ? 'bubble-wrap--right' : 'bubble-wrap--left'">
                            <div class="bubble bubble--image" :class="isAdminMsg(msg) ? 'bubble--admin' : 'bubble--user'">
                                <a :href="msg.metadata?.image_url" target="_blank">
                                    <img :src="msg.metadata?.image_url" class="chat-image" alt="фото" />
                                </a>
                                <span class="bubble-meta">{{ fmtTime(msg.created_at) }}</span>
                            </div>
                        </div>

                        <!-- Text message -->
                        <div v-else class="bubble-wrap" :class="isAdminMsg(msg) ? 'bubble-wrap--right' : 'bubble-wrap--left'">
                            <div class="bubble" :class="isAdminMsg(msg) ? 'bubble--admin' : 'bubble--user'">
                                <span v-if="!isAdminMsg(msg)" class="bubble-sender">{{ msg.sender_name }}</span>
                                <p class="bubble-body">{{ msg.body }}</p>
                                <span class="bubble-meta">{{ fmtTime(msg.created_at) }}</span>
                            </div>
                        </div>
                    </template>

                    <div ref="messagesEnd" />
                </div>

                <!-- Input -->
                <div class="chat-input-area">
                    <input ref="fileInput" type="file" accept="image/*" class="file-input-hidden" @change="onFileSelected" />

                    <button class="attach-btn" @click="triggerFileInput" :disabled="uploading" title="Прикрепить фото">
                        <svg v-if="!uploading" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/>
                        </svg>
                        <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="spin">
                            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                        </svg>
                    </button>

                    <textarea
                        v-model="newMessage"
                        class="chat-textarea"
                        placeholder="Написать сообщение…"
                        rows="1"
                        @keydown.enter="handleEnter"
                    />

                    <button class="send-btn" @click="sendMessage" :disabled="!newMessage.trim() || sending">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                    </button>
                </div>
            </template>

            <div v-else class="chat-empty">
                <p>Выберите чат или создайте новый</p>
            </div>
        </main>

        <!-- ── New chat modal ── -->
        <UserPickerModal
            v-model="showNewChat"
            title="Новый чат — выбор пользователя"
            @select="startChatWith"
        />

    </div>
</template>

<style scoped>
/* ── Layout ───────────────────────────────────────────────── */
.support-layout {
    display: flex;
    /* вырваться из padding: 2rem в admin-main и занять всю высоту */
    margin: -2rem;
    height: calc(100% + 4rem);
    overflow: hidden;
    background: #07070f;
    color: #fff;
}

/* ── Sidebar ─────────────────────────────────────────────── */
.support-sidebar {
    width: 280px;
    flex-shrink: 0;
    border-right: 1px solid rgba(255, 178, 239,0.14);
    display: flex;
    flex-direction: column;
    background: #09090f;
}

.support-sidebar__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1rem 0.75rem;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.support-sidebar__title {
    font-size: 0.82rem;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.4);
}

.new-chat-btn {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 1px solid rgba(255, 178, 239,0.3);
    background: rgba(255, 178, 239,0.08);
    color: rgba(255, 178, 239,0.9);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
}
.new-chat-btn:hover { background: rgba(255, 178, 239,0.18); }

.support-sidebar__list {
    flex: 1;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.06) transparent;
}

.conv-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.7rem 0.85rem;
    cursor: pointer;
    border-bottom: 1px solid rgba(255,255,255,0.03);
    transition: background 0.15s;
}
.conv-item:hover { background: rgba(255, 178, 239,0.06); }
.conv-item--active { background: rgba(255, 178, 239,0.12); }
.conv-item--closed { opacity: 0.6; }

.conv-item__avatar {
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    border-radius: 50%;
    overflow: hidden;
    background: rgba(255, 178, 239,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
}
.conv-item__avatar img { width: 100%; height: 100%; object-fit: cover; }
.conv-item__avatar-placeholder { font-size: 0.85rem; color: rgba(255,255,255,0.7); font-weight: 600; }

.conv-item__info { flex: 1; min-width: 0; }
.conv-item__name { font-size: 0.82rem; color: rgba(255,255,255,0.85); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.conv-item__last { font-size: 0.72rem; color: rgba(255,255,255,0.35); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 0.1rem; }

.conv-item__meta { display: flex; flex-direction: column; align-items: flex-end; gap: 0.2rem; flex-shrink: 0; }
.conv-item__time { font-size: 0.68rem; color: rgba(255,255,255,0.25); }
.conv-item__closed-badge { font-size: 0.72rem; }

.conv-empty  { text-align: center; color: rgba(255,255,255,0.2); padding: 2rem 1rem; font-size: 0.82rem; }
.conv-loading { text-align: center; color: rgba(255,255,255,0.18); padding: 0.75rem 1rem; font-size: 0.75rem; }

/* ── Sidebar filters ─────────────────────────────────────── */
.sidebar-filters {
    padding: 0.6rem 0.85rem 0.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.conv-search {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255, 178, 239,0.2);
    border-radius: 6px;
    color: rgba(255,255,255,0.8);
    font-size: 0.78rem;
    padding: 0.38rem 0.65rem;
    outline: none;
    font-family: inherit;
    box-sizing: border-box;
}
.conv-search::placeholder { color: rgba(255,255,255,0.25); }
.conv-search:focus { border-color: rgba(255, 178, 239,0.45); }

.status-tabs { display: flex; gap: 0.3rem; }
.status-tab {
    flex: 1;
    padding: 0.3rem 0;
    background: transparent;
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 5px;
    color: rgba(255,255,255,0.35);
    font-size: 0.72rem;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.status-tab:hover { background: rgba(255, 178, 239,0.07); color: rgba(255,255,255,0.6); }
.status-tab--active {
    background: rgba(255, 178, 239,0.15);
    border-color: rgba(255, 178, 239,0.4);
    color: rgba(160,150,255,0.9);
}

/* ── Unread badge ────────────────────────────────────────── */
.conv-item__unread {
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    background: #9B6EE8;
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
}

/* ── Chat area ───────────────────────────────────────────── */
.support-chat {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.chat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    background: rgba(0,0,0,0.2);
    flex-shrink: 0;
}

.chat-header__user { display: flex; align-items: center; gap: 0.65rem; }
.chat-header__avatar {
    width: 32px; height: 32px; border-radius: 50%; overflow: hidden;
    background: rgba(255, 178, 239,0.2); display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; color: rgba(255,255,255,0.7); font-weight: 600;
}
.chat-header__avatar img { width: 100%; height: 100%; object-fit: cover; }
.chat-header__name { font-size: 0.9rem; color: rgba(255,255,255,0.85); font-weight: 500; }
.chat-header__closed-tag {
    font-size: 0.68rem; padding: 0.15rem 0.5rem; border-radius: 3px;
    background: rgba(255,110,110,0.12); border: 1px solid rgba(255,110,110,0.3);
    color: rgba(255,130,130,0.85);
}

.chat-header__actions { display: flex; gap: 0.5rem; }
.chat-action-btn {
    display: flex; align-items: center; gap: 0.4rem;
    padding: 0.4rem 0.75rem; border-radius: 4px; font-size: 0.78rem;
    cursor: pointer; transition: background 0.15s, border-color 0.15s; font-family: inherit;
}
.chat-action-btn--close {
    background: rgba(255,110,110,0.07); border: 1px solid rgba(255,110,110,0.25); color: rgba(255,130,130,0.85);
}
.chat-action-btn--close:hover { background: rgba(255,110,110,0.14); border-color: rgba(255,110,110,0.45); }
.chat-action-btn--open {
    background: rgba(80,240,160,0.07); border: 1px solid rgba(80,240,160,0.25); color: rgba(80,240,160,0.85);
}
.chat-action-btn--open:hover { background: rgba(80,240,160,0.14); border-color: rgba(80,240,160,0.45); }

/* ── Messages ────────────────────────────────────────────── */
.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.06) transparent;
}

.load-more { text-align: center; margin-bottom: 0.5rem; }
.load-more-btn {
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.4); font-size: 0.75rem; padding: 0.3rem 0.9rem;
    border-radius: 4px; cursor: pointer; font-family: inherit;
}
.load-more-btn:hover { background: rgba(255,255,255,0.08); }

.sys-event {
    text-align: center; margin: 0.5rem 0;
    display: flex; align-items: center; gap: 0.75rem;
}
.sys-event::before, .sys-event::after {
    content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.07);
}
.sys-event__label { font-size: 0.7rem; color: rgba(255,255,255,0.25); white-space: nowrap; }

.bubble-wrap { display: flex; }
.bubble-wrap--right { justify-content: flex-end; }
.bubble-wrap--left  { justify-content: flex-start; }

.bubble {
    max-width: 65%;
    padding: 0.55rem 0.75rem;
    border-radius: 10px;
    position: relative;
}
.bubble--admin {
    background: linear-gradient(135deg, rgba(255, 178, 239,0.22), rgba(180, 80, 150,0.16));
    border: 1px solid rgba(255, 178, 239,0.2);
    border-bottom-right-radius: 2px;
}
.bubble--user {
    background: linear-gradient(135deg, rgba(255,255,255,0.07), rgba(255,255,255,0.04));
    border: 1px solid rgba(255,255,255,0.08);
    border-bottom-left-radius: 2px;
}
.bubble--image { padding: 0.35rem; }

.bubble-sender { display: block; font-size: 0.75rem; color: rgba(255,255,255,0.35); margin-bottom: 0.2rem; }
.bubble-body { font-size: 1rem; color: rgba(255,255,255,0.9); line-height: 1.55; margin: 0; white-space: pre-wrap; }
.bubble-meta { display: block; font-size: 0.72rem; color: rgba(255,255,255,0.3); text-align: right; margin-top: 0.3rem; }

.chat-image { max-width: 240px; max-height: 320px; border-radius: 6px; display: block; cursor: pointer; }

.chat-empty { flex: 1; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.2); font-size: 0.88rem; }

/* ── Input ───────────────────────────────────────────────── */
.chat-input-area {
    display: flex;
    align-items: flex-end;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-top: 1px solid rgba(255,255,255,0.06);
    background: rgba(0,0,0,0.15);
    flex-shrink: 0;
}

.file-input-hidden { display: none; }

.attach-btn {
    width: 36px; height: 36px; flex-shrink: 0;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 8px; color: rgba(255,255,255,0.4); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s, color 0.15s;
}
.attach-btn:hover:not(:disabled) { background: rgba(255,255,255,0.09); color: rgba(255,255,255,0.75); }
.attach-btn:disabled { opacity: 0.5; cursor: default; }

.chat-textarea {
    flex: 1;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255, 178, 239,0.2);
    border-radius: 8px;
    color: rgba(255,255,255,0.85);
    font-size: 0.84rem;
    padding: 0.55rem 0.75rem;
    outline: none;
    font-family: inherit;
    resize: none;
    max-height: 120px;
    overflow-y: auto;
    line-height: 1.5;
}
.chat-textarea:focus { border-color: rgba(255, 178, 239,0.45); }

.send-btn {
    width: 36px; height: 36px; flex-shrink: 0;
    background: rgba(255, 178, 239,0.15); border: 1px solid rgba(255, 178, 239,0.35);
    border-radius: 8px; color: rgba(160,150,255,0.9); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s;
}
.send-btn:hover:not(:disabled) { background: rgba(255, 178, 239,0.25); }
.send-btn:disabled { opacity: 0.35; cursor: default; }

.spin { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

</style>
