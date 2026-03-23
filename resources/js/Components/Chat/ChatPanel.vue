<script setup>
import { ref, watch, onMounted, onUnmounted, computed, nextTick, inject } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import SiteModal from '@/Components/Site/SiteModal.vue';
import axios from 'axios';
import { Check, Lock } from '@element-plus/icons-vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const page = usePage();
const authUser = computed(() => page.props.auth.user);

const isOpen = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const conversations   = ref([]);
const activeConversation = ref(null);
const messages        = ref([]);
const newMessage      = ref('');
const isTyping        = ref(false);
const typingTimer     = ref(null);
const loadingConvs    = ref(false);
const loadingMsgs     = ref(false);
const coverMessages   = ref(false);
const sending         = ref(false);
const messagesEnd     = ref(null);
const messagesContainer = ref(null);

// New state
const otherLastReadAt = ref(null);
const hasMore         = ref(false);
const loadingMore     = ref(false);
const searchQuery     = ref('');

// Online status — from global presence channel in AppLayout
const onlineUserIds = inject('onlineUserIds', ref([]));

// ── Avatar fullscreen ─────────────────────────────────────
const avatarFullscreen = ref(false);

// ── Block state ───────────────────────────────────────────
const activeBlock     = ref(null);
const blockModal          = ref(false);
const blockReason         = ref('');
const blockDuration       = ref(null);
const blockDurationChosen = ref(false);
const blockSubmitting     = ref(false);

const blockReasons = computed(() => page.props.chat_block_reasons ?? []);

const blockDurations = [
    { label: '1 час',    minutes: 60 },
    { label: '24 часа',  minutes: 1440 },
    { label: '7 дней',   minutes: 10080 },
    { label: '30 дней',  minutes: 43200 },
    { label: 'Навсегда', minutes: null },
];

const nowTick = ref(Date.now());
let nowTimer = null;
onMounted(() => { nowTimer = setInterval(() => { nowTick.value = Date.now(); }, 1000); });

const blockedUntilLabel = computed(() => {
    if (!activeBlock.value) return '';
    if (!activeBlock.value.blocked_until) return 'Навсегда';
    const diff = Math.max(0, new Date(activeBlock.value.blocked_until).getTime() - nowTick.value);
    if (diff === 0) return 'Срок вышел';
    const totalSec = Math.floor(diff / 1000);
    const days  = Math.floor(totalSec / 86400);
    const hours = Math.floor((totalSec % 86400) / 3600);
    const mins  = Math.floor((totalSec % 3600) / 60);
    const secs  = totalSec % 60;
    if (days > 0)  return `${days} дн. ${hours} ч.`;
    if (hours > 0) return `${hours} ч. ${mins} мин.`;
    if (mins > 0)  return `${mins} мин. ${secs} сек.`;
    return `${secs} сек.`;
});

let echoChannel = null;

// ── Close panel ──────────────────────────────────────────
function close() {
    isOpen.value = false;
}

// ── Fetch conversation list ──────────────────────────────
async function fetchConversations() {
    loadingConvs.value = true;
    try {
        const res = await axios.get(route('conversations.index'));
        conversations.value = res.data.conversations;
    } finally {
        loadingConvs.value = false;
    }
}

// ── Filtered conversations (search) ─────────────────────
const filteredConversations = computed(() =>
    conversations.value.filter(c =>
        c.other_user?.name?.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
);

// ── Open a conversation ──────────────────────────────────
async function openConversation(conv) {
    if (activeConversation.value?.id === conv.id) return;
    leaveEcho();
    loadingMsgs.value = true;
    activeConversation.value = conv;
    messages.value = [];
    newMessage.value = '';
    isTyping.value = false;
    otherLastReadAt.value = null;
    hasMore.value = false;

    try {
        const res = await axios.get(route('conversations.show', conv.id));
        messages.value = res.data.messages;
        hasMore.value = res.data.has_more;
        otherLastReadAt.value = res.data.other_last_read_at ?? null;
        activeBlock.value = res.data.block ?? null;
        if (res.data.other_user) {
            activeConversation.value = { ...conv, other_user: res.data.other_user };
        }
        const local = conversations.value.find(c => c.id === conv.id);
        if (local) local.unread_count = 0;
        router.reload({ only: ['unread_messages_count'] });
    } finally {
        coverMessages.value = true;
        loadingMsgs.value = false;
    }

    subscribeEcho(conv.id);
    await nextTick();
    messagesEnd.value?.scrollIntoView({ behavior: 'instant' });
    setTimeout(() => { coverMessages.value = false; }, 80);
}

// ── Start conversation with user (called from outside) ───
async function startWith(userId) {
    isOpen.value = true;
    const res = await axios.post(route('conversations.store'), { target_user_id: userId });
    const convId = res.data.conversation_id;

    await fetchConversations();
    const conv = conversations.value.find(c => c.id === convId);
    await openConversation(conv ?? { id: convId, other_user: null, unread_count: 0 });
}

// ── Send message ─────────────────────────────────────────
async function sendMessage() {
    const body = newMessage.value.trim();
    if (!body || sending.value || !activeConversation.value) return;

    sending.value = true;
    newMessage.value = '';
    try {
        const res = await axios.post(
            route('conversations.message', activeConversation.value.id),
            { body }
        );
        messages.value.push(res.data);
        scrollToBottom();
        updateLastMessage(activeConversation.value.id, res.data);
    } finally {
        sending.value = false;
    }
}

function handleEnter(e) {
    if (e.shiftKey) return;
    e.preventDefault();
    sendMessage();
}

// ── Whisper typing ───────────────────────────────────────
function onInput() {
    if (!echoChannel) return;
    echoChannel.whisper('typing', { user_id: authUser.value?.id });
}

// ── Echo subscription ────────────────────────────────────
function subscribeEcho(conversationId) {
    if (!window.Echo || !authUser.value) return;
    echoChannel = window.Echo.private(`conversation.${conversationId}`)
        .listen('.message.sent', (data) => {
            if (data.sender_id !== authUser.value.id) {
                messages.value.push(data);
                scrollToBottom();
                markRead(conversationId);
                updateLastMessage(conversationId, data);
                playNotificationSound();
            }
        })
        .listen('.message.read', (data) => {
            if (data.reader_id !== authUser.value.id) {
                otherLastReadAt.value = data.read_at;
            }
        })
        .listenForWhisper('typing', () => {
            isTyping.value = true;
            clearTimeout(typingTimer.value);
            typingTimer.value = setTimeout(() => { isTyping.value = false; }, 2000);
        });
}

function leaveEcho() {
    const convId = activeConversation.value?.id;
    if (convId && window.Echo) {
        window.Echo.leave(`conversation.${convId}`);
    }
    echoChannel = null;
    clearTimeout(typingTimer.value);
    isTyping.value = false;
}

async function markRead(conversationId) {
    await axios.get(route('conversations.show', conversationId));
    const local = conversations.value.find(c => c.id === conversationId);
    if (local) local.unread_count = 0;
    router.reload({ only: ['unread_messages_count'] });
}

function updateLastMessage(convId, msg) {
    const conv = conversations.value.find(c => c.id === convId);
    if (conv) {
        conv.last_message = { body: msg.body, sender_id: msg.sender_id, created_at: msg.created_at };
        conv.updated_at = msg.created_at;
    }
}

// ── Scroll helpers ────────────────────────────────────────
function scrollToBottom(instant = false) {
    setTimeout(() => {
        messagesEnd.value?.scrollIntoView({ behavior: instant ? 'instant' : 'smooth' });
    }, 50);
}

// ── Pagination on scroll up ──────────────────────────────
function onMessagesScroll(e) {
    if (e.target.scrollTop === 0 && hasMore.value && !loadingMore.value) {
        loadOlderMessages();
    }
}

async function loadOlderMessages() {
    const firstId = messages.value[0]?.id;
    if (!firstId) return;
    loadingMore.value = true;

    const container = messagesContainer.value;
    const prevScrollHeight = container?.scrollHeight ?? 0;

    try {
        const res = await axios.get(route('conversations.show', activeConversation.value.id), {
            params: { before_id: firstId },
        });
        messages.value = [...res.data.messages, ...messages.value];
        hasMore.value = res.data.has_more;

        await nextTick();
        if (container) {
            container.scrollTop = container.scrollHeight - prevScrollHeight;
        }
    } finally {
        loadingMore.value = false;
    }
}

// ── Sound notification ───────────────────────────────────
function playNotificationSound() {
    try {
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = 880;
        osc.type = 'sine';
        gain.gain.setValueAtTime(0.08, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
        osc.start();
        osc.stop(ctx.currentTime + 0.25);
    } catch {}
}

// ── Grouped messages (date dividers + group info) ────────
const groupedMessages = computed(() => {
    const result = [];
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);

    let prevDate = null;
    let prevSenderId = null;

    for (let i = 0; i < messages.value.length; i++) {
        const msg = messages.value[i];
        const msgDate = new Date(msg.created_at);
        const dateKey = msgDate.toDateString();

        if (dateKey !== prevDate) {
            let label;
            if (dateKey === today.toDateString()) {
                label = 'Сегодня';
            } else if (dateKey === yesterday.toDateString()) {
                label = 'Вчера';
            } else {
                label = msgDate.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' });
            }
            result.push({ type: 'divider', label, key: 'divider-' + dateKey });
            prevDate = dateKey;
            prevSenderId = null;
        }

        const nextMsg = messages.value[i + 1];
        const nextSenderId = nextMsg?.sender_id;
        const nextDateKey = nextMsg ? new Date(nextMsg.created_at).toDateString() : null;

        const isFirstInGroup = msg.sender_id !== prevSenderId;
        const isLastInGroup  = msg.sender_id !== nextSenderId || nextDateKey !== dateKey;

        result.push({
            type: 'message',
            msg,
            isFirstInGroup,
            isLastInGroup,
        });

        prevSenderId = msg.sender_id;
    }

    return result;
});

// ── Is other user online ─────────────────────────────────
const isOtherOnline = computed(() => {
    const otherId = activeConversation.value?.other_user?.id;
    return otherId ? onlineUserIds.value.includes(otherId) : false;
});

// ── Block / unblock ───────────────────────────────────────
async function submitBlock() {
    if (!blockReason.value || blockSubmitting.value) return;
    blockSubmitting.value = true;
    try {
        const res = await axios.post(
            route('conversations.block', activeConversation.value.id),
            { reason: blockReason.value, duration: blockDuration.value }
        );
        activeBlock.value = res.data.block;
        blockModal.value  = false;
        blockReason.value = '';
        blockDuration.value = null;
        blockDurationChosen.value = false;
    } finally {
        blockSubmitting.value = false;
    }
}

async function submitUnblock() {
    await axios.delete(route('conversations.unblock', activeConversation.value.id));
    activeBlock.value = null;
}

// ── Message read status ───────────────────────────────────
function isMessageRead(msg) {
    if (!otherLastReadAt.value) return false;
    return otherLastReadAt.value >= msg.created_at;
}

// ── Watch panel open ─────────────────────────────────────
watch(isOpen, (val) => {
    if (val) fetchConversations();
    if (!val) {
        leaveEcho();
        activeConversation.value = null;
        searchQuery.value = '';
    }
});

// ── Close on navigation ──────────────────────────────────
watch(() => page.url, (newUrl, oldUrl) => {
    if (newUrl !== oldUrl) isOpen.value = false;
});

onUnmounted(() => {
    leaveEcho();
    clearInterval(nowTimer);
});

defineExpose({ startWith });

// ── Helpers ──────────────────────────────────────────────
function formatTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    if (d.toDateString() === now.toDateString()) return formatTime(iso);
    return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
}
</script>

<template>
    <Teleport to="body">
        <Transition name="backdrop">
            <div v-if="isOpen" class="chat-backdrop" @click="close" />
        </Transition>

        <Transition name="slide">
            <div v-if="isOpen" class="chat-panel">

                <!-- ── Sidebar: список диалогов ───────────── -->
                <div class="chat-sidebar">
                    <div class="chat-sidebar__header">
                        <span class="chat-sidebar__title">Сообщения</span>
                        <button class="chat-icon-btn" @click="close">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Поиск по диалогам -->
                    <div class="chat-sidebar__search">
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="chat-search-input"
                            placeholder="Поиск…"
                        />
                    </div>

                    <div class="chat-sidebar__list">
                        <div v-if="loadingConvs" class="chat-empty">Загрузка…</div>
                        <template v-else-if="conversations.length === 0">
                            <div class="chat-no-convs">
                                <p>Нет диалогов</p>
                                <a :href="route('users.search')">Найти пользователей →</a>
                            </div>
                        </template>
                        <template v-else>
                            <button
                                v-for="conv in filteredConversations"
                                :key="conv.id"
                                class="chat-conv-item"
                                :class="{
                                    'chat-conv-item--active': activeConversation?.id === conv.id,
                                    'chat-conv-item--unread': conv.unread_count > 0,
                                }"
                                @click="openConversation(conv)"
                            >
                                <div class="chat-conv-avatar">
                                    <img v-if="conv.other_user?.avatar_url" :src="conv.other_user.avatar_url" alt="" />
                                    <span v-else>{{ conv.other_user?.name?.charAt(0) ?? '?' }}</span>
                                </div>
                                <div class="chat-conv-info">
                                    <div class="chat-conv-name">
                                        {{ conv.other_user?.name ?? '—' }}
                                    </div>
                                    <div class="chat-conv-preview">{{ conv.last_message?.body ?? '' }}</div>
                                </div>
                                <div class="chat-conv-meta">
                                    <span class="chat-conv-time">{{ formatDate(conv.last_message?.created_at) }}</span>
                                    <span v-if="conv.unread_count > 0" class="chat-conv-badge">{{ conv.unread_count }}</span>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- ── Main: переписка ────────────────────── -->
                <div class="chat-main">
                    <!-- Пусто — нет выбранного диалога -->
                    <div v-if="!activeConversation" class="chat-main__empty">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span>Выберите диалог</span>
                    </div>

                    <template v-else>
                        <!-- Шапка диалога -->
                        <div class="chat-main__header">
                            <div
                                class="chat-conv-avatar chat-conv-avatar--sm chat-conv-avatar--clickable"
                                @click="avatarFullscreen = true"
                                title="Посмотреть фото"
                            >
                                <img v-if="activeConversation.other_user?.avatar_url" :src="activeConversation.other_user.avatar_url" alt="" />
                                <span v-else>{{ activeConversation.other_user?.name?.charAt(0) ?? '?' }}</span>
                            </div>
                            <div class="chat-main__header-info">
                                <div class="chat-main__name-row">
                                    <a
                                        :href="route('profile.show', { user: activeConversation.other_user?.id })"
                                        target="_blank"
                                        rel="noopener"
                                        class="chat-main__name"
                                    >{{ activeConversation.other_user?.name ?? '…' }}</a>
                                    <span v-if="activeConversation.other_user?.is_idol" class="chat-idol-badge">Айдол</span>
                                </div>
                                <span class="chat-online-badge" :class="{ 'chat-online-badge--visible': isOtherOnline }">
                                    <span class="chat-online-dot"></span>онлайн
                                </span>
                            </div>
                            <button
                                v-if="!activeBlock?.active || activeBlock?.i_am_blocker"
                                class="chat-lock-btn"
                                :class="{ 'chat-lock-btn--active': activeBlock?.active && activeBlock?.i_am_blocker }"
                                :title="activeBlock?.active ? 'Заблокирован' : 'Заблокировать'"
                                @click="blockModal = true"
                                style="margin-left: auto;"
                            >
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Сообщения -->
                        <div class="chat-messages-wrap">
                        <div class="chat-messages" ref="messagesContainer" @scroll="onMessagesScroll">
                            <div v-if="loadingMsgs" class="chat-empty">Загрузка…</div>
                            <template v-else>
                                <!-- Индикатор подгрузки -->
                                <div v-if="loadingMore" class="chat-loading-more">Загрузка…</div>

                                <TransitionGroup name="msg" tag="div" class="chat-messages-inner">
                                    <template v-for="item in groupedMessages" :key="item.key ?? item.msg?.id">
                                        <!-- Date divider -->
                                        <div v-if="item.type === 'divider'" class="chat-date-divider">
                                            <span>{{ item.label }}</span>
                                        </div>

                                        <!-- Message -->
                                        <div
                                            v-else-if="item.type === 'message'"
                                            class="chat-msg"
                                            :class="{
                                                'chat-msg--mine': item.msg.sender_id === authUser?.id,
                                                'chat-msg--first-in-group': item.isFirstInGroup,
                                                'chat-msg--last-in-group': item.isLastInGroup,
                                            }"
                                        >
                                            <div class="chat-msg__bubble">
                                                <span class="chat-msg__text">{{ item.msg.body }}</span>
                                                <span class="chat-msg__meta">
                                                    <span class="chat-msg__time">{{ formatTime(item.msg.created_at) }}</span>
                                                    <span
                                                        v-if="item.msg.sender_id === authUser?.id"
                                                        class="chat-msg__status"
                                                        :class="{ 'chat-msg__status--read': isMessageRead(item.msg) }"
                                                    >
                                                        <span class="chat-ticks">
                                                            <el-icon class="chat-tick chat-tick--1"><Check /></el-icon>
                                                            <el-icon class="chat-tick chat-tick--2"><Check /></el-icon>
                                                        </span>
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                    </template>
                                </TransitionGroup>

                                <div v-if="isTyping" class="chat-typing">
                                    {{ activeConversation.other_user?.name }} печатает…
                                </div>
                                <div ref="messagesEnd" />
                            </template>
                        </div>

                        <!-- Оверлей: скрывает скролл при открытии диалога -->
                        <Transition name="cover-fade">
                            <div v-if="coverMessages" class="chat-messages-cover" />
                        </Transition>

                        <!-- Оверлей: заблокированный -->
                        <div v-if="activeBlock?.active && !activeBlock?.i_am_blocker" class="chat-blocked-overlay">
                            <div class="chat-blocked-card">
                                <svg class="chat-blocked-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                                <p class="chat-blocked-title">Вы заблокированы</p>
                                <p class="chat-blocked-reason">{{ activeBlock.reason }}</p>
                                <div class="chat-blocked-timer">
                                    <span class="chat-blocked-timer__value">{{ blockedUntilLabel }}</span>
                                    <span class="chat-blocked-timer__label" v-if="activeBlock.blocked_until">осталось</span>
                                </div>
                            </div>
                        </div>
                        </div><!-- end chat-messages-wrap -->

                        <!-- Баннер блокировщика -->
                        <div v-if="activeBlock?.active && activeBlock?.i_am_blocker" class="chat-block-banner">
                            Вы заблокировали этого пользователя
                            <button @click="submitUnblock" class="chat-block-unblock-btn">Разблокировать</button>
                        </div>

                        <!-- Поле ввода -->
                        <div class="chat-input-wrap">
                            <div class="chat-input-fade"></div>
                            <div class="chat-input-inner">
                                <textarea
                                    v-model="newMessage"
                                    class="chat-input"
                                    placeholder="Сообщение…"
                                    rows="3"
                                    maxlength="500"
                                    :disabled="!!activeBlock?.active && !activeBlock?.i_am_blocker"
                                    @keydown.enter="handleEnter"
                                    @input="onInput"
                                />
                                <span class="chat-char-count" :class="{ 'chat-char-count--warn': newMessage.length > 450 }">
                                    {{ newMessage.length }}/500
                                </span>
                                <button class="chat-send" :disabled="!newMessage.trim() || sending || (!!activeBlock?.active && !activeBlock?.i_am_blocker)" @click="sendMessage">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="22" y1="2" x2="11" y2="13"/>
                                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                    </svg>
                                    Enter
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

            </div>
        </Transition>

    </Teleport>

        <!-- ── Модалка блокировки ─────────────────────── -->
        <SiteModal :show="blockModal" variant="pink" compact @close="blockModal = false">
            <h2 class="bm-title">Заблокировать пользователя: <span class="bm-title-name">{{ activeConversation?.other_user?.name }}</span></h2>

            <div class="bm-section-label">Причина</div>
            <div class="bm-reasons">
                <button
                    v-for="r in blockReasons"
                    :key="r"
                    class="bm-reason-tag"
                    :class="{ 'bm-reason-tag--selected': blockReason === r }"
                    @click="blockReason = r"
                >{{ r }}</button>
            </div>

            <div class="bm-section-label">Срок</div>
            <div class="bm-durations">
                <button
                    v-for="d in blockDurations"
                    :key="d.label"
                    class="bm-duration-btn"
                    :class="{ 'bm-duration-btn--selected': blockDurationChosen && blockDuration === d.minutes }"
                    @click="() => { blockDuration = d.minutes; blockDurationChosen = true; }"
                >{{ d.label }}</button>
            </div>

            <div class="bm-footer">
                <button class="bm-cancel" @click="blockModal = false">Отмена</button>
                <button
                    class="bm-submit"
                    :disabled="!blockReason || !blockDurationChosen || blockSubmitting"
                    @click="submitBlock"
                >Заблокировать</button>
            </div>
        </SiteModal>

    <!-- ── Полноэкранный просмотр аватарки ──────────────── -->
    <Teleport to="body">
        <Transition name="avatar-fade">
            <div v-if="avatarFullscreen" class="avatar-fs-backdrop" @click="avatarFullscreen = false">
                <div class="avatar-fs-inner" @click.stop>
                    <button class="avatar-fs-close" @click="avatarFullscreen = false">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                    <img
                        v-if="activeConversation?.other_user?.avatar_url"
                        :src="activeConversation.other_user.avatar_url"
                        class="avatar-fs-img"
                        :alt="activeConversation.other_user?.name"
                    />
                    <div v-else class="avatar-fs-placeholder">
                        <span class="avatar-fs-initial">{{ activeConversation?.other_user?.name?.charAt(0)?.toUpperCase() ?? '?' }}</span>
                        <span class="avatar-fs-noavatar">Нет фото</span>
                    </div>
                    <div class="avatar-fs-name">{{ activeConversation?.other_user?.name }}</div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* ── Backdrop ─────────────────────────────────────────── */
.chat-backdrop {
    position: fixed;
    inset: 0;
    z-index: 999;
    background: rgba(0, 0, 0, 0.4);
}
.backdrop-enter-active, .backdrop-leave-active { transition: opacity 0.25s; }
.backdrop-enter-from, .backdrop-leave-to { opacity: 0; }

/* ── Panel ────────────────────────────────────────────── */
.chat-panel {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: 900px;
    max-width: 100vw;
    z-index: 1000;
    display: flex;
    background: #0e0e1c;
    border-left: 1px solid rgba(110, 110, 210, 0.18);
    box-shadow: -8px 0 48px rgba(0, 0, 0, 0.55);
}
.slide-enter-active, .slide-leave-active { transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1); }
.slide-enter-from, .slide-leave-to { transform: translateX(100%); }

/* ── Sidebar ──────────────────────────────────────────── */
.chat-sidebar {
    width: 260px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(110, 110, 210, 0.14);
    background: #0b0b18;
}

.chat-sidebar__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1rem 0.85rem;
    border-bottom: 1px solid rgba(110, 110, 210, 0.12);
    flex-shrink: 0;
}
.chat-sidebar__title {
    font-size: 1.05rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.88);
    letter-spacing: 0.01em;
}

.chat-icon-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: none;
    background: transparent;
    border-radius: 50%;
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
}
.chat-icon-btn:hover {
    color: rgba(255, 255, 255, 0.85);
    background: rgba(110, 110, 210, 0.1);
}

/* ── Search ───────────────────────────────────────────── */
.chat-sidebar__search {
    padding: 0.55rem 0.75rem;
    border-bottom: 1px solid rgba(110, 110, 210, 0.1);
    flex-shrink: 0;
}
.chat-search-input {
    width: 100%;
    background: rgba(110, 110, 210, 0.07);
    border: 1px solid rgba(110, 110, 210, 0.18);
    border-radius: 8px;
    padding: 0.45rem 0.75rem;
    color: rgba(255, 255, 255, 0.85);
    font-size: 0.875rem;
    outline: none;
    transition: border-color 0.15s;
    box-sizing: border-box;
}
.chat-search-input:focus { border-color: rgba(160, 160, 255, 0.45); }
.chat-search-input::placeholder { color: rgba(255, 255, 255, 0.22); }

.chat-sidebar__list {
    flex: 1;
    overflow-y: auto;
    padding: 0.35rem 0;
}

.chat-empty {
    padding: 2.5rem 1rem;
    text-align: center;
    color: rgba(255, 255, 255, 0.28);
    font-size: 0.95rem;
}

/* ── Empty state ──────────────────────────────────────── */
.chat-no-convs {
    padding: 2.5rem 1rem;
    text-align: center;
    color: rgba(255, 255, 255, 0.28);
    font-size: 0.92rem;
}
.chat-no-convs p { margin: 0 0 0.6rem; }
.chat-no-convs a {
    color: rgba(160, 160, 255, 0.65);
    text-decoration: none;
    font-size: 0.88rem;
    transition: color 0.15s;
}
.chat-no-convs a:hover { color: rgba(160, 160, 255, 0.9); }

/* ── Conversation items ───────────────────────────────── */
.chat-conv-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    padding: 0.7rem 0.85rem;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    transition: background 0.15s;
    border-radius: 0;
}
.chat-conv-item:hover { background: rgba(110, 110, 210, 0.07); }
.chat-conv-item--active { background: rgba(110, 110, 210, 0.13); }
.chat-conv-item--active:hover { background: rgba(110, 110, 210, 0.17); }

.chat-conv-item--unread .chat-conv-name {
    font-weight: 700;
    color: rgba(255, 255, 255, 0.95);
}
.chat-conv-item--unread .chat-conv-preview {
    color: rgba(255, 255, 255, 0.55);
}

.chat-conv-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(110, 110, 210, 0.15);
    border: 1.5px solid rgba(110, 110, 210, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
    color: #7070d8;
    font-weight: 700;
    font-size: 1.05rem;
}
.chat-conv-avatar--sm {
    width: 36px;
    height: 36px;
    font-size: 0.95rem;
}
.chat-conv-avatar--xs {
    width: 28px;
    height: 28px;
    font-size: 0.8rem;
}
.chat-conv-avatar--spacer {
    background: transparent;
    border-color: transparent;
    visibility: hidden;
}
.chat-conv-avatar img { width: 100%; height: 100%; object-fit: cover; }

.chat-conv-info {
    flex: 1;
    min-width: 0;
}
.chat-conv-name {
    font-size: 0.95rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.88);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.chat-conv-preview {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.35);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
}

.chat-conv-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
    flex-shrink: 0;
}
.chat-conv-time {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.28);
}
.chat-conv-badge {
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    border-radius: 999px;
    background: #e0558f;
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 20px;
    text-align: center;
}

/* ── Main area ────────────────────────────────────────── */
.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    background: transparent;
    position: relative;
}

.chat-main__empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    color: rgba(255, 255, 255, 0.22);
    font-size: 1rem;
}

.chat-main__header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1.1rem;
    border-bottom: 1px solid rgba(110, 110, 210, 0.12);
    flex-shrink: 0;
}
.chat-main__header-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.chat-main__name-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.chat-main__name {
    font-size: 1rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    transition: color 0.15s;
}
.chat-main__name:hover {
    color: #be91ff;
}
.chat-idol-badge {
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 0.12rem 0.45rem;
    background: rgba(56, 189, 248, 0.12);
    color: #38bdf8;
    border: 1px solid rgba(56, 189, 248, 0.3);
    border-radius: 4px;
}

/* ── Online indicator ─────────────────────────────────── */
.chat-online-badge {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.78rem;
    color: rgba(80, 220, 140, 0.85);
    visibility: hidden;   /* место зарезервировано всегда */
}
.chat-online-badge--visible {
    visibility: visible;
}
.chat-online-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #3ddc84;
    box-shadow: 0 0 4px rgba(61, 220, 132, 0.6);
}

/* ── Messages ─────────────────────────────────────────── */
.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 1.1rem 1.1rem 0.5rem;
    display: flex;
    flex-direction: column;
    scrollbar-width: thin;
    scrollbar-color: rgba(100, 220, 180, 0.35) transparent;
}
.chat-messages::-webkit-scrollbar {
    width: 4px;
}
.chat-messages::-webkit-scrollbar-track {
    background: transparent;
}
.chat-messages::-webkit-scrollbar-thumb {
    background: rgba(100, 220, 180, 0.35);
    border-radius: 99px;
}
.chat-messages::-webkit-scrollbar-thumb:hover {
    background: rgba(100, 220, 180, 0.6);
}

.chat-messages-inner {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

/* ── Load more indicator ──────────────────────────────── */
.chat-loading-more {
    text-align: center;
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.28);
    padding: 0.5rem 0;
    flex-shrink: 0;
}

/* ── Date divider ─────────────────────────────────────── */
.chat-date-divider {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0.75rem 0 0.5rem;
    color: rgba(255, 255, 255, 0.28);
    font-size: 0.78rem;
}
.chat-date-divider::before,
.chat-date-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(110, 110, 210, 0.15);
}

/* ── Message row ──────────────────────────────────────── */
.chat-msg {
    display: flex;
    align-items: flex-end;
    gap: 6px;
    justify-content: flex-start;
}
.chat-msg--mine {
    justify-content: flex-end;
}

/* Tight spacing inside a chain */
.chat-msg + .chat-msg:not(.chat-msg--first-in-group) {
    margin-top: 0.15rem;
}
.chat-msg--first-in-group {
    margin-top: 0.55rem;
}

/* ── Avatar slot ──────────────────────────────────────── */
.chat-msg__avatar-slot {
    flex-shrink: 0;
    width: 28px;
    align-self: flex-end;
}

/* ── Bubble ───────────────────────────────────────────── */
.chat-msg__bubble {
    max-width: 72%;
    padding: 0.55rem 0.9rem;
    border-radius: 14px;
    background: rgba(110, 110, 210, 0.12);
    border: 1px solid rgba(110, 110, 210, 0.18);
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.chat-msg--mine .chat-msg__bubble {
    background: rgba(160, 100, 255, 0.2);
    border-color: rgba(160, 100, 255, 0.3);
}

/* Smart corners — theirs (left side) */
.chat-msg:not(.chat-msg--mine).chat-msg--first-in-group .chat-msg__bubble {
    border-top-left-radius: 14px;
}
.chat-msg:not(.chat-msg--mine):not(.chat-msg--first-in-group) .chat-msg__bubble {
    border-top-left-radius: 4px;
}
.chat-msg:not(.chat-msg--mine).chat-msg--last-in-group .chat-msg__bubble {
    border-bottom-left-radius: 14px;
}
.chat-msg:not(.chat-msg--mine):not(.chat-msg--last-in-group) .chat-msg__bubble {
    border-bottom-left-radius: 4px;
}

/* Smart corners — mine (right side) */
.chat-msg--mine.chat-msg--first-in-group .chat-msg__bubble {
    border-top-right-radius: 14px;
}
.chat-msg--mine:not(.chat-msg--first-in-group) .chat-msg__bubble {
    border-top-right-radius: 4px;
}
.chat-msg--mine.chat-msg--last-in-group .chat-msg__bubble {
    border-bottom-right-radius: 14px;
}
.chat-msg--mine:not(.chat-msg--last-in-group) .chat-msg__bubble {
    border-bottom-right-radius: 4px;
}

.chat-msg__text {
    font-size: 0.975rem;
    color: rgba(255, 255, 255, 0.92);
    line-height: 1.45;
    white-space: pre-wrap;
    word-break: break-word;
}

.chat-msg__meta {
    display: flex;
    align-items: center;
    gap: 4px;
    align-self: stretch;
}
.chat-msg__time {
    flex: 1;
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.3);
}
.chat-msg__status {
    display: inline-flex;
    align-items: center;
}

/* Контейнер двух галочек */
.chat-ticks {
    position: relative;
    display: inline-block;
    width: 18px;   /* ширина = иконка + сдвиг */
    height: 12px;
}

.chat-tick {
    position: absolute;
    top: 0;
    font-size: 11px;
    color: rgba(255, 255, 255, 0.35);
    transition: color 0.2s;
}

/* Первая галочка — позади, немного левее */
.chat-tick--1 {
    left: 0;
    z-index: 1;
}

/* Вторая галочка — поверх, немного правее */
.chat-tick--2 {
    left: 5px;
    z-index: 2;
    /* До прочтения — скрыта */
    opacity: 0;
    transition: opacity 0.2s, color 0.2s;
}

/* Прочитано: обе видны и синие */
.chat-msg__status--read .chat-tick {
    color: rgba(100, 200, 255, 0.85);
}
.chat-msg__status--read .chat-tick--2 {
    opacity: 1;
}

.chat-typing {
    font-size: 0.88rem;
    color: rgba(160, 160, 255, 0.55);
    padding: 0.25rem 0;
    font-style: italic;
}

/* ── Message enter animation ──────────────────────────── */
.msg-enter-active { transition: all 0.2s ease; }
.msg-enter-from   { opacity: 0; transform: translateY(8px); }

/* ── Input ────────────────────────────────────────────── */
.chat-input-wrap {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 0 1.1rem 0.85rem;
    background: transparent;
}
.chat-input-fade {
    position: absolute;
    bottom: 100%;
    left: 0;
    right: 0;
    height: 60px;
    background: linear-gradient(to bottom, transparent, #0e0e1c);
    pointer-events: none;
}
.chat-input-inner {
    flex: 1;
    position: relative;
}
.chat-input-inner::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    border-radius: 6px 6px 0 0;
    background: linear-gradient(90deg, transparent, rgba(155, 110, 232, 0.3), rgba(190, 145, 255, 0.5), rgba(155, 110, 232, 0.3), transparent);
    box-shadow: 0 0 6px rgba(155, 110, 232, 0.1);
    z-index: 1;
}
.chat-input {
    width: 100%;
    box-sizing: border-box;
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    padding: 0.65rem 5rem 2.25rem 0.85rem;
    color: rgba(255, 255, 255, 0.95);
    font-size: 0.95rem;
    resize: none;
    line-height: 1.55;
    outline: none;
    font-family: inherit;
    transition: border-color 0.15s, background 0.15s;
    display: block;
}
.chat-input:focus {
    border-color: rgba(160, 160, 255, 0.35);
    background: rgba(255, 255, 255, 0.05);
}
.chat-input::placeholder { color: rgba(255, 255, 255, 0.25); }

.chat-char-count {
    position: absolute;
    left: 0.75rem;
    bottom: 0.55rem;
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.2);
    font-variant-numeric: tabular-nums;
    pointer-events: none;
    transition: color 0.15s;
}
.chat-char-count--warn {
    color: rgba(239, 68, 68, 0.7);
}
.chat-send {
    position: absolute;
    right: 0.5rem;
    bottom: 0.5rem;
    padding: 0.28rem 0.65rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    background: rgba(155, 110, 232, 0.15);
    border: 1px solid rgba(155, 110, 232, 0.35);
    border-radius: 4px;
    color: rgba(190, 145, 255, 0.85);
    font-family: inherit;
    font-size: 0.78rem;
    font-weight: 500;
    letter-spacing: 0.03em;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.chat-send:hover:not(:disabled) {
    background: rgba(155, 110, 232, 0.28);
    border-color: rgba(155, 110, 232, 0.55);
    color: #be91ff;
}
.chat-send:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

/* ── Lock button in header ────────────────────────────── */
.chat-lock-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: 1px solid rgba(255, 100, 180, 0.25);
    background: rgba(255, 100, 180, 0.08);
    border-radius: 8px;
    color: rgba(255, 100, 180, 0.65);
    cursor: pointer;
    transition: color 0.15s, background 0.15s, border-color 0.15s;
}
.chat-lock-btn--active {
    color: rgba(255, 80, 160, 0.95);
    background: rgba(255, 80, 160, 0.18);
    border-color: rgba(255, 80, 160, 0.5);
}
.chat-lock-btn:hover {
    color: rgba(255, 80, 160, 0.95);
    background: rgba(255, 80, 160, 0.18);
    border-color: rgba(255, 80, 160, 0.45);
}

/* ── Clickable avatar ─────────────────────────────────── */
.chat-conv-avatar--clickable {
    cursor: pointer;
    transition: opacity 0.15s;
}
.chat-conv-avatar--clickable:hover {
    opacity: 0.8;
}

/* ── Avatar fullscreen ────────────────────────────────── */
.avatar-fs-backdrop {
    position: fixed;
    inset: 0;
    z-index: 2000;
    background: rgba(0, 0, 0, 0.88);
    display: flex;
    align-items: center;
    justify-content: center;
}
.avatar-fade-enter-active, .avatar-fade-leave-active { transition: opacity 0.2s; }
.avatar-fade-enter-from, .avatar-fade-leave-to { opacity: 0; }

.avatar-fs-inner {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}
.avatar-fs-close {
    position: absolute;
    top: -2.5rem;
    right: -0.5rem;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.55);
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.avatar-fs-close:hover {
    background: rgba(255,255,255,0.15);
    color: #fff;
}
.avatar-fs-img {
    width: min(72vw, 400px);
    height: min(72vw, 400px);
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255,255,255,0.12);
}
.avatar-fs-placeholder {
    width: min(72vw, 400px);
    height: min(72vw, 400px);
    border-radius: 50%;
    background: rgba(155,110,232,0.15);
    border: 2px solid rgba(155,110,232,0.3);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}
.avatar-fs-initial {
    font-size: clamp(4rem, 15vw, 8rem);
    font-weight: 700;
    color: rgba(155,110,232,0.85);
    line-height: 1;
}
.avatar-fs-noavatar {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.3);
}
.avatar-fs-name {
    font-size: 1.1rem;
    color: rgba(255,255,255,0.75);
    font-weight: 500;
    text-align: center;
}

/* ── Messages wrap (for overlay positioning) ──────────── */
.chat-messages-wrap {
    flex: 1;
    min-height: 0;
    position: relative;
    display: flex;
    flex-direction: column;
    padding-bottom: 160px;
}
.chat-messages-wrap .chat-messages {
    flex: 1;
    min-height: 0;
}

.chat-messages-cover {
    position: absolute;
    inset: 0;
    background: #0e0e1c;
    z-index: 10;
    pointer-events: none;
}
.cover-fade-leave-active { transition: opacity 0.15s ease; }
.cover-fade-leave-to { opacity: 0; }

/* ── Blocked overlay ──────────────────────────────────── */
.chat-blocked-overlay {
    position: absolute;
    inset: 0;
    background: rgba(10, 10, 20, 0.85);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    padding: 1.5rem;
    text-align: center;
}
.chat-blocked-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.65rem;
    background: rgba(255, 50, 130, 0.06);
    border: 1px solid rgba(255, 80, 160, 0.22);
    border-radius: 16px;
    padding: 2rem 2.5rem 1.75rem;
    max-width: 300px;
    box-shadow: 0 0 40px rgba(255, 80, 160, 0.1);
}
.chat-blocked-icon {
    color: rgba(255, 80, 160, 0.55);
    margin-bottom: 0.25rem;
    flex-shrink: 0;
}
.chat-blocked-title {
    font-size: 0.72rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.4);
    margin: 0;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}
.chat-blocked-reason {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}
.chat-blocked-timer {
    margin-top: 0.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
}
.chat-blocked-timer__value {
    font-size: 1.25rem;
    font-weight: 400;
    font-family: 'Courier New', Courier, monospace;
    color: #ff5aaa;
    text-shadow: 0 0 18px rgba(255, 80, 160, 0.45);
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.05em;
    line-height: 1.2;
}
.chat-blocked-timer__label {
    font-size: 0.7rem;
    color: rgba(255, 80, 160, 0.5);
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

/* ── Block banner (for blocker) ───────────────────────── */
.chat-block-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.5rem 1.1rem;
    background: rgba(255, 140, 50, 0.1);
    border-top: 1px solid rgba(255, 140, 50, 0.25);
    border-bottom: 1px solid rgba(255, 140, 50, 0.15);
    font-size: 0.85rem;
    color: rgba(255, 190, 100, 0.85);
    flex-shrink: 0;
}
.chat-block-unblock-btn {
    background: transparent;
    border: 1px solid rgba(255, 140, 50, 0.4);
    color: rgba(255, 190, 100, 0.85);
    font-size: 0.82rem;
    padding: 0.2rem 0.65rem;
    border-radius: 6px;
    cursor: pointer;
    font-family: inherit;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
    white-space: nowrap;
}
.chat-block-unblock-btn:hover {
    border-color: rgba(255, 140, 50, 0.75);
    color: #ffc875;
    background: rgba(255, 140, 50, 0.1);
}

/* ── Block modal ──────────────────────────────────────── */
/* ── Block modal content (inside SiteModal) ───────── */
.bm-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.88);
    margin: 0 0 1.25rem;
}
.bm-title-name {
    color: #ff5aaa;
}
.bm-section-label {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.3);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 0.5rem;
}
.bm-reasons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
    margin-bottom: 1.25rem;
}
.bm-reason-tag {
    padding: 0.3rem 0.8rem;
    border-radius: 999px;
    border: 1px solid rgba(110, 110, 210, 0.3);
    background: transparent;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    cursor: pointer;
    font-family: inherit;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.bm-reason-tag:hover {
    border-color: rgba(160, 100, 255, 0.5);
    color: rgba(255, 255, 255, 0.85);
}
.bm-reason-tag--selected {
    border-color: rgba(160, 100, 255, 0.7);
    background: rgba(155, 110, 232, 0.2);
    color: #be91ff;
}
.bm-durations {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
    margin-bottom: 2rem;
}
.bm-duration-btn {
    padding: 0.3rem 0.85rem;
    border-radius: 8px;
    border: 1px solid rgba(110, 110, 210, 0.3);
    background: transparent;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    cursor: pointer;
    font-family: inherit;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.bm-duration-btn:hover {
    border-color: rgba(160, 100, 255, 0.5);
    color: rgba(255, 255, 255, 0.85);
}
.bm-duration-btn--selected {
    border-color: rgba(160, 100, 255, 0.7);
    background: rgba(155, 110, 232, 0.2);
    color: #be91ff;
}
.bm-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}
.bm-cancel {
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.88rem;
    padding: 0.45rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
    transition: border-color 0.15s, color 0.15s;
}
.bm-cancel:hover {
    border-color: rgba(255, 255, 255, 0.25);
    color: rgba(255, 255, 255, 0.75);
}
.bm-submit {
    background: linear-gradient(135deg, rgba(220, 60, 100, 0.45), rgba(180, 30, 80, 0.4));
    border: 1px solid rgba(220, 80, 110, 0.5);
    color: #ff9ab5;
    font-size: 0.88rem;
    padding: 0.45rem 1.25rem;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
    font-weight: 600;
    transition: background 0.15s, border-color 0.15s;
}
.bm-submit:hover:not(:disabled) {
    background: linear-gradient(135deg, rgba(220, 60, 100, 0.65), rgba(180, 30, 80, 0.6));
    border-color: rgba(220, 80, 110, 0.75);
}
.bm-submit:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}
</style>
