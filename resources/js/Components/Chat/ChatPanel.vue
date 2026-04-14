<script setup>
import { ref, watch, onMounted, onUnmounted, computed, nextTick, inject } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import SiteModal from '@/Components/Site/SiteModal.vue';
import axios from 'axios';
import { Check, Lock } from '@element-plus/icons-vue';
import IdolBadge from '@/Components/IdolBadge.vue';
import ServiceOfferModal from '@/Components/Chat/ServiceOfferModal.vue';
import ReviewForm from '@/Components/Chat/ReviewForm.vue';
import RepeatOrderModal from '@/Components/Chat/RepeatOrderModal.vue';

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
const uploading       = ref(false);
const fileInput       = ref(null);
const messagesEnd     = ref(null);
const messagesContainer = ref(null);

// New state
const otherLastReadAt = ref(null);
const hasMore         = ref(false);
const loadingMore     = ref(false);
const searchQuery     = ref('');

// Online status — from global presence channel in AppLayout
const onlineUserIds  = inject('onlineUserIds', ref([]));
const injectAddToCart = inject('addToCart', null);
const showOfferModal       = ref(false);
const repeatOrderOpen      = ref(false);
const confirmAddModal      = ref(false);
const hasReview            = ref(false);
const confirmAddService    = ref(null); // { id, name, price, time_unit }
const confirmAddLoading    = ref(false);

// ── Orders tab ────────────────────────────────────────────
const activeTab      = ref('messages'); // 'messages' | 'orders'
const orders         = ref([]);
const loadingOrders  = ref(false);
const activeOrderData = ref(null); // order data for the current open conversation
const ordersSubTab   = ref('mine'); // 'mine' | 'incoming' — only used when authUser is idol

const orderStatusFilter = ref('all'); // 'all' | 'pending' | 'accepted' | 'cancelled'
const orderSearch       = ref('');
const orderFiltersOpen  = ref(false);


const subtabOrders = computed(() => {
    if (!authUser.value?.is_idol) return orders.value;
    return ordersSubTab.value === 'mine'
        ? orders.value.filter(o => o.is_customer)
        : orders.value.filter(o => !o.is_customer);
});

const pendingOrderUnread = ref(false);
const ordersHaveUnread = computed(() => pendingOrderUnread.value || orders.value.some(o => (o.unread_count ?? 0) > 0));
const mineHaveUnread = computed(() => orders.value.filter(o => o.is_customer).some(o => (o.unread_count ?? 0) > 0));
const incomingHaveUnread = computed(() => orders.value.filter(o => !o.is_customer).some(o => (o.unread_count ?? 0) > 0));

const orderStatusCounts = computed(() => ({
    all:       subtabOrders.value.length,
    pending:   subtabOrders.value.filter(o => o.status === 'pending').length,
    accepted:  subtabOrders.value.filter(o => o.status === 'accepted').length,
    cancelled: subtabOrders.value.filter(o => o.status === 'cancelled').length,
}));

const visibleOrders = computed(() => {
    let list = subtabOrders.value;
    if (orderStatusFilter.value !== 'all') {
        list = list.filter(o => o.status === orderStatusFilter.value);
    }
    if (orderSearch.value.trim()) {
        const q = orderSearch.value.trim().toLowerCase();
        list = list.filter(o => {
            const partner = o.is_customer ? o.idol : o.customer;
            return partner.name.toLowerCase().includes(q);
        });
    }
    return list;
});

// ── Pay / complete / timer ────────────────────────────────
const orderTimerLabel = computed(() => {
    if (activeOrderData.value?.status !== 'paid') return '';
    const paidAt = activeOrderData.value?.paid_at;
    if (!paidAt) return '— : — : —';
    const deadline = new Date(paidAt).getTime() + 72 * 3600 * 1000;
    const diff = Math.max(0, deadline - nowTick.value);
    if (diff === 0) return 'Завершается…';
    const totalSec = Math.floor(diff / 1000);
    const days  = Math.floor(totalSec / 86400);
    const hours = Math.floor((totalSec % 86400) / 3600);
    const mins  = Math.floor((totalSec % 3600) / 60);
    const secs  = totalSec % 60;
    if (days > 0) return `${days} д. ${String(hours).padStart(2,'0')}:${String(mins).padStart(2,'0')}:${String(secs).padStart(2,'0')}`;
    return `${String(hours).padStart(2,'0')}:${String(mins).padStart(2,'0')}:${String(secs).padStart(2,'0')}`;
});

const myConfirmation = computed(() => {
    if (!activeOrderData.value) return false;
    return activeOrderData.value.is_customer
        ? activeOrderData.value.completion_confirmed_by_customer
        : activeOrderData.value.completion_confirmed_by_idol;
});

async function payOrder() {
    if (!activeOrderData.value || activeOrderData.value.status !== 'accepted') return;
    await axios.patch(route('orders.pay', activeOrderData.value.id));
    // activeOrderData will be updated via broadcast
}

async function confirmCompletion() {
    if (!activeOrderData.value || activeOrderData.value.status !== 'paid') return;
    await axios.patch(route('orders.confirm-completion', activeOrderData.value.id));
}

// ── Cancel order modal ────────────────────────────────────
const cancelModal       = ref(false);
const cancelReason      = ref('');
const cancelSubmitting  = ref(false);
const CANCEL_TEMPLATES_CUSTOMER = [
    'Изменились планы',
    'Нашёл другого исполнителя',
    'Сделал заказ по ошибке',
    'Не устраивают условия',
    'Не получил ответа от исполнителя',
    'По личным причинам',
];

const CANCEL_TEMPLATES_IDOL = [
    'Изменились планы',
    'Не смогу выполнить этот заказ',
    'Не хватает времени',
    'Слишком большой объём работы',
    'Это не моя специализация',
    'По личным причинам',
];

const cancelTemplates = computed(() =>
    activeOrderData.value?.is_customer ? CANCEL_TEMPLATES_CUSTOMER : CANCEL_TEMPLATES_IDOL
);

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
onMounted(() => {
    nowTimer = setInterval(() => { nowTick.value = Date.now(); }, 1000);
    subscribeUserEcho();
});

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
let ordersEchoChannel = null;
let userEchoChannel = null;

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
    conversations.value.filter(c => {
        if (c.is_support) return 'поддержка no alone'.includes(searchQuery.value.toLowerCase());
        return c.other_user?.name?.toLowerCase().includes(searchQuery.value.toLowerCase());
    })
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
        activeOrderData.value = res.data.order ?? null;
        hasReview.value = res.data.has_review ?? false;
        activeConversation.value = {
            ...conv,
            ...(res.data.other_user ? { other_user: res.data.other_user } : {}),
            is_support: res.data.is_support ?? conv.is_support ?? false,
            closed_at:  res.data.closed_at  ?? null,
        };
        const local = conversations.value.find(c => c.id === conv.id);
        if (local) local.unread_count = 0;
        const order = orders.value.find(o => o.conversation_id === conv.id);
        if (order) order.unread_count = 0;
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
            if (data.sender_id !== authUser.value.id || data.type === 'system') {
                messages.value.push(data);
                scrollToBottom();
                markRead(conversationId);
                updateLastMessage(conversationId, data);
                if (data.type !== 'system') playNotificationSound();
            }
            if (data.type === 'system' && activeConversation.value?.id === conversationId) {
                if (data.metadata?.event === 'chat_closed') {
                    activeConversation.value = { ...activeConversation.value, closed_at: data.created_at };
                } else if (data.metadata?.event === 'chat_opened') {
                    activeConversation.value = { ...activeConversation.value, closed_at: null };
                }
            }
        })
        .listen('.message.read', (data) => {
            if (data.reader_id !== authUser.value.id) {
                otherLastReadAt.value = data.read_at;
            }
        })
        .listen('.order.status-changed', (data) => {
            if (activeOrderData.value && activeOrderData.value.id === data.order_id) {
                activeOrderData.value = {
                    ...activeOrderData.value,
                    status:                           data.status,
                    cancelled_by:                     data.cancelled_by                     ?? activeOrderData.value.cancelled_by,
                    cancelled_by_name:                data.cancelled_by_name                ?? activeOrderData.value.cancelled_by_name,
                    cancel_reason:                    data.cancel_reason                    ?? activeOrderData.value.cancel_reason,
                    paid_at:                          data.paid_at                          ?? activeOrderData.value.paid_at,
                    completed_at:                     data.completed_at                     ?? activeOrderData.value.completed_at,
                    completion_confirmed_by_idol:     data.completion_confirmed_by_idol     ?? activeOrderData.value.completion_confirmed_by_idol,
                    completion_confirmed_by_customer: data.completion_confirmed_by_customer ?? activeOrderData.value.completion_confirmed_by_customer,
                };
            }
            const idx = orders.value.findIndex(o => o.id === data.order_id);
            if (idx !== -1) {
                orders.value[idx] = {
                    ...orders.value[idx],
                    status:            data.status,
                    cancelled_by:      data.cancelled_by      ?? orders.value[idx].cancelled_by,
                    cancelled_by_name: data.cancelled_by_name ?? orders.value[idx].cancelled_by_name,
                    cancel_reason:     data.cancel_reason     ?? orders.value[idx].cancel_reason,
                };
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

function subscribeOrdersEcho() {
    if (!window.Echo || !authUser.value) return;
    ordersEchoChannel = window.Echo.private(`orders.${authUser.value.id}`)
        .listen('.order.changed', ({ change_type, order }) => {
            const idx = orders.value.findIndex(o => o.id === order.id);
            if (idx !== -1) {
                orders.value.splice(idx, 1, {
                    ...order,
                    unread_count: orders.value[idx].unread_count ?? 0,
                });
            } else {
                orders.value.unshift({ ...order, unread_count: 0 });
            }
            if (activeOrderData.value?.id === order.id) {
                activeOrderData.value = { ...activeOrderData.value, ...order };
            }
        });
}

function leaveOrdersEcho() {
    if (ordersEchoChannel && window.Echo && authUser.value) {
        window.Echo.leave(`orders.${authUser.value.id}`);
    }
    ordersEchoChannel = null;
}

function subscribeUserEcho() {
    if (!window.Echo || !authUser.value) return;
    userEchoChannel = window.Echo.private(`App.Models.User.${authUser.value.id}`)
        .listen('.message.received', handleIncomingMessageForList);
}

function leaveUserEcho() {
    if (userEchoChannel) {
        userEchoChannel.stopListening('.message.received', handleIncomingMessageForList);
    }
    userEchoChannel = null;
}

function handleIncomingMessageForList(data) {
    const { conversation_id, order_id, last_message } = data;

    // Не трогать активный диалог — он уже обрабатывается через markRead
    if (activeConversation.value?.id === conversation_id) return;

    if (order_id) {
        pendingOrderUnread.value = true;
        const order = orders.value.find(o => o.conversation_id === conversation_id);
        if (order) {
            order.unread_count = (order.unread_count ?? 0) + 1;
        }
    } else {
        const conv = conversations.value.find(c => c.id === conversation_id);
        if (conv) {
            const body = last_message?.type === 'image' ? '[фото]' : (last_message?.body ?? '');
            conv.last_message = { body, sender_id: last_message?.sender_id, created_at: last_message?.created_at };
            conv.updated_at = last_message?.created_at;
            conv.unread_count = (conv.unread_count ?? 0) + 1;
        }
    }
}

async function markRead(conversationId) {
    await axios.get(route('conversations.show', conversationId));
    const local = conversations.value.find(c => c.id === conversationId);
    if (local) local.unread_count = 0;
    const order = orders.value.find(o => o.conversation_id === conversationId);
    if (order) order.unread_count = 0;
    router.reload({ only: ['unread_messages_count'] });
}

function updateLastMessage(convId, msg) {
    const conv = conversations.value.find(c => c.id === convId);
    if (conv) {
        const body = msg.type === 'image' ? '[фото]' : msg.body;
        conv.last_message = { body, sender_id: msg.sender_id, created_at: msg.created_at };
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

// ── Support chat helpers ──────────────────────────────────
const isSupport    = computed(() => !!activeConversation.value?.is_support);
const isChatClosed = computed(() => isSupport.value && !!activeConversation.value?.closed_at);
const showReviewForm = computed(() =>
    activeOrderData.value?.status === 'completed' &&
    activeOrderData.value?.is_customer === true &&
    !hasReview.value
);

function onReviewSubmitted() {
    hasReview.value = true;
    messages.value.push({
        id: Date.now(),
        sender_id: null,
        created_at: new Date().toISOString(),
        type: 'system',
        body: null,
        metadata: { event: 'review_submitted' },
        read_at: null,
    });
}

async function onRepeatOrderCreated({ conversation_id }) {
    repeatOrderOpen.value = false;
    await fetchConversations();
    const conv = conversations.value.find(c => c.id === conversation_id);
    await openConversation(conv ?? { id: conversation_id, other_user: null, unread_count: 0 });
}

function onOfferSent(msg) {
    // Push the message immediately on the idol's side (Echo skips own messages)
    messages.value.push(msg);
    nextTick(scrollToBottom);
    if (activeConversation.value) {
        updateLastMessage(activeConversation.value.id, msg);
    }
}

function addServiceToCart(svc) {
    if (activeOrderData.value && activeOrderData.value.status === 'pending') {
        confirmAddService.value = svc;
        confirmAddModal.value   = true;
    } else if (injectAddToCart) {
        const idol = activeConversation.value?.other_user;
        if (idol) injectAddToCart(svc, idol);
    }
}

async function confirmAddToOrder() {
    if (!confirmAddService.value || confirmAddLoading.value) return;
    confirmAddLoading.value = true;
    try {
        await axios.post(route('orders.items.add', activeOrderData.value.id), { service_id: confirmAddService.value.id });
        confirmAddModal.value = false;
    } catch (e) {
        alert(e.response?.data?.message ?? 'Не удалось добавить услугу к заказу');
    } finally {
        confirmAddLoading.value = false;
    }
}

async function uploadAndSendImage(file) {
    if (!file || uploading.value || !activeConversation.value) return;
    uploading.value = true;
    try {
        const form = new FormData();
        form.append('file', file);
        const up = await axios.post(
            route('conversations.upload', activeConversation.value.id),
            form,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        );
        const res = await axios.post(
            route('conversations.message', activeConversation.value.id),
            { body: '', type: 'image', metadata: { image_url: up.data.url } }
        );
        messages.value.push(res.data);
        scrollToBottom();
        updateLastMessage(activeConversation.value.id, res.data);
    } finally {
        uploading.value = false;
    }
}

function onFileChange(e) {
    const file = e.target.files?.[0];
    if (file) uploadAndSendImage(file);
    e.target.value = '';
}

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
    if (val) {
        fetchConversations();
        if (activeTab.value === 'orders') fetchOrders();
        subscribeOrdersEcho();
    }
    if (!val) {
        leaveEcho();
        leaveOrdersEcho();
        activeConversation.value = null;
        activeOrderData.value = null;
        searchQuery.value = '';
        orderSearch.value = '';
        orderStatusFilter.value = 'all';
        orderFiltersOpen.value = false;
    }
});

watch(activeTab, (tab) => {
    if (tab === 'orders') fetchOrders();
});

watch(ordersSubTab, () => {
    orderStatusFilter.value = 'all';
    orderSearch.value = '';
    orderFiltersOpen.value = false;
});

// ── Close on navigation (ignore reloads on same URL) ─────
watch(() => page.url, (newUrl, oldUrl) => {
    const stripQuery = (url) => url.split('?')[0].split('#')[0];
    if (stripQuery(newUrl) !== stripQuery(oldUrl)) isOpen.value = false;
});

onUnmounted(() => {
    leaveEcho();
    leaveOrdersEcho();
    leaveUserEcho();
    clearInterval(nowTimer);
});

// ── Orders helpers ────────────────────────────────────────
async function fetchOrders() {
    loadingOrders.value = true;
    try {
        const res = await axios.get(route('orders.index'));
        orders.value = res.data.orders;
        pendingOrderUnread.value = false;
    } finally {
        loadingOrders.value = false;
    }
}

async function openOrderConversation(order) {
    if (!order.conversation_id) return;
    const other = order.is_customer ? order.idol : order.customer;
    await openConversation({ id: order.conversation_id, other_user: other, unread_count: 0 });
}

const acceptBtnText = computed(() => {
    const gender = activeOrderData.value?.idol?.gender;
    if (gender === 'male')   return 'Готов принять заказ';
    if (gender === 'female') return 'Готова принять заказ';
    return 'Готов(а) принять заказ';
});

function orderTotal(order) {
    return order.items.reduce((s, i) => s + (i.service?.price ?? 0) * (i.quantity ?? 1), 0);
}

function cancelledByLabel(orderData) {
    if (!orderData?.cancelled_by) return null;
    if (orderData.cancelled_by === authUser.value?.id) return 'вами';
    return orderData.cancelled_by_name ?? null;
}

async function acceptOrder() {
    if (!activeOrderData.value || activeOrderData.value.status !== 'pending') return;
    await axios.patch(route('orders.accept', activeOrderData.value.id));
    activeOrderData.value = { ...activeOrderData.value, status: 'accepted' };
    orders.value = orders.value.map(o => o.id === activeOrderData.value.id ? { ...o, status: 'accepted' } : o);
    router.reload({ only: ['order_notifications_unread'] });
}

async function submitCancelOrder() {
    if (!cancelReason.value.trim() || cancelSubmitting.value) return;
    cancelSubmitting.value = true;
    try {
        await axios.patch(route('orders.cancel', activeOrderData.value.id), { cancel_reason: cancelReason.value });
        const updated = {
            ...activeOrderData.value,
            status: 'cancelled',
            cancel_reason: cancelReason.value,
            cancelled_by: authUser.value.id,
            cancelled_by_name: authUser.value.name,
        };
        activeOrderData.value = updated;
        orders.value = orders.value.map(o => o.id === updated.id
            ? { ...o, status: 'cancelled', cancel_reason: updated.cancel_reason, cancelled_by: updated.cancelled_by, cancelled_by_name: updated.cancelled_by_name }
            : o
        );
        cancelModal.value = false;
        cancelReason.value = '';
        router.reload({ only: ['order_notifications_unread'] });
    } finally {
        cancelSubmitting.value = false;
    }
}

async function openOrder(orderId) {
    isOpen.value = true;
    activeTab.value = 'orders';
    await fetchOrders();
    const order = orders.value.find(o => o.id === orderId);
    ordersSubTab.value = order?.is_customer === false ? 'incoming' : 'mine';
    if (order) await openOrderConversation(order);
}

defineExpose({ startWith, openOrder });

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
                        <span class="chat-sidebar__title">Чат</span>
                        <button class="chat-icon-btn" @click="close">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Табы -->
                    <div class="chat-tabs">
                        <button class="chat-tab" :class="{ 'chat-tab--active': activeTab === 'messages' }" @click="activeTab = 'messages'">Сообщения</button>
                        <button class="chat-tab" :class="{ 'chat-tab--active': activeTab === 'orders' }" @click="activeTab = 'orders'">
                            Заказы
                            <span v-if="ordersHaveUnread" class="chat-tab__dot"></span>
                        </button>
                    </div>

                    <!-- Поиск по диалогам (только для сообщений) -->
                    <div v-if="activeTab === 'messages'" class="chat-sidebar__search">
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="chat-search-input"
                            placeholder="Поиск…"
                        />
                    </div>

                    <div class="chat-sidebar__list">
                        <!-- ── Сообщения ── -->
                        <template v-if="activeTab === 'messages'">
                            <div v-if="loadingConvs" class="chat-empty">Загрузка…</div>
                            <template v-else-if="conversations.length === 0">
                                <div class="chat-no-convs">
                                    <p>{{ $page.props.is_idol ? 'Нет диалогов' : 'У вас пока нет сообщений' }}</p>
                                    <a v-if="$page.props.is_idol" :href="route('users.search')">Найти пользователей →</a>
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
                                        <template v-if="conv.is_support">
                                            <span class="chat-support-icon">✦</span>
                                        </template>
                                        <template v-else>
                                            <img v-if="conv.other_user?.avatar_url" :src="conv.other_user.avatar_url" alt="" />
                                            <span v-else>{{ conv.other_user?.name?.charAt(0) ?? '?' }}</span>
                                        </template>
                                    </div>
                                    <div class="chat-conv-info">
                                        <div class="chat-conv-name">{{ conv.is_support ? 'Поддержка no alone' : (conv.other_user?.name ?? '—') }}</div>
                                        <div class="chat-conv-preview">{{ conv.last_message?.body ?? '' }}</div>
                                    </div>
                                    <div class="chat-conv-meta">
                                        <span class="chat-conv-time">{{ formatDate(conv.last_message?.created_at) }}</span>
                                        <span v-if="conv.unread_count > 0" class="chat-conv-badge">{{ conv.unread_count }}</span>
                                    </div>
                                </button>
                            </template>
                        </template>

                        <!-- ── Заказы ── -->
                        <template v-else-if="activeTab === 'orders'">
                            <div v-if="loadingOrders" class="chat-empty">Загрузка…</div>
                            <template v-else>
                                <!-- Саб-табы только для айдолов -->
                                <div v-if="authUser?.is_idol" class="chat-order-subtabs">
                                    <button
                                        class="chat-order-subtab"
                                        :class="{ 'chat-order-subtab--active': ordersSubTab === 'mine' }"
                                        @click="ordersSubTab = 'mine'"
                                    >Мои <span v-if="mineHaveUnread" class="chat-tab__dot"></span></button>
                                    <button
                                        class="chat-order-subtab"
                                        :class="{ 'chat-order-subtab--active': ordersSubTab === 'incoming' }"
                                        @click="ordersSubTab = 'incoming'"
                                    >Входящие <span v-if="incomingHaveUnread" class="chat-tab__dot"></span></button>
                                </div>

                                <!-- ── Фильтры заказов ──────────────────── -->
                                <div class="order-filters">
                                    <input
                                        v-model="orderSearch"
                                        type="text"
                                        class="chat-search-input order-filters__search-input"
                                        placeholder="Поиск по имени…"
                                    />
                                    <button
                                        class="order-filters__toggle"
                                        :class="{ 'order-filters__toggle--open': orderFiltersOpen }"
                                        @click="orderFiltersOpen = !orderFiltersOpen"
                                    >
                                        Фильтры
                                        <svg class="order-filters__arrow" width="10" height="10" viewBox="0 0 10 10" fill="none">
                                            <path d="M2 3.5L5 6.5L8 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                    <Transition name="of-expand">
                                        <div v-if="orderFiltersOpen" class="order-filters__pills">
                                            <button
                                                v-for="pill in [
                                                    { key: 'all',       label: 'Все' },
                                                    { key: 'pending',   label: 'Ожидает' },
                                                    { key: 'accepted',  label: 'Принят' },
                                                    { key: 'cancelled', label: 'Отменён' },
                                                ]"
                                                :key="pill.key"
                                                class="order-filter-pill"
                                                :class="{
                                                    'order-filter-pill--active': orderStatusFilter === pill.key,
                                                    [`order-filter-pill--${pill.key}`]: pill.key !== 'all',
                                                }"
                                                @click="orderStatusFilter = pill.key"
                                            >
                                                {{ pill.label }}
                                                <span class="order-filter-pill__count">{{ orderStatusCounts[pill.key] }}</span>
                                            </button>
                                        </div>
                                    </Transition>
                                </div>

                                <template v-if="visibleOrders.length === 0">
                                    <div class="chat-no-convs"><p>Нет заказов</p></div>
                                </template>
                                <template v-else>
                                <button
                                    v-for="order in visibleOrders"
                                    :key="order.id"
                                    class="order-stub"
                                    :class="[`order-stub--${order.status}`, { 'order-stub--active': activeOrderData?.id === order.id }]"
                                    @click="openOrderConversation(order)"
                                >
                                    <div class="order-stub__head">
                                        <div class="chat-conv-avatar chat-conv-avatar--sm">
                                            <img v-if="(order.is_customer ? order.idol : order.customer).avatar_url"
                                                :src="(order.is_customer ? order.idol : order.customer).avatar_url" alt="" />
                                            <span v-else>{{ (order.is_customer ? order.idol : order.customer).name?.charAt(0) ?? '?' }}</span>
                                        </div>
                                        <div class="order-stub__who">
                                            <span class="order-stub__name">{{ (order.is_customer ? order.idol : order.customer).name }}</span>
                                            <span class="order-stub__date">{{ formatDate(order.created_at) }}</span>
                                        </div>
                                        <span class="order-stub__badge" :class="`order-stub__badge--${order.status}`">
                                            {{ { pending: 'Создан', accepted: 'Принят', paid: 'Оплачен', completed: 'Выполнен', cancelled: 'Отменён', refunded: 'Аннулирован', disputed: 'Оспаривается' }[order.status] }}
                                        </span>
                                        <span v-if="(order.unread_count ?? 0) > 0" class="chat-conv-badge">{{ order.unread_count }}</span>
                                    </div>
                                    <div class="order-stub__perf">
                                        <span class="order-stub__perf-dot" v-for="n in 14" :key="n"></span>
                                    </div>
                                    <div class="order-stub__foot">
                                        <span class="order-stub__count">
                                            {{ order.items.length }}&thinsp;{{ order.items.length === 1 ? 'услуга' : order.items.length < 5 ? 'услуги' : 'услуг' }}
                                        </span>
                                        <span class="order-stub__total">{{ orderTotal(order).toLocaleString('ru-RU') }}&thinsp;₽</span>
                                    </div>
                                </button>
                                </template><!-- /visibleOrders -->
                            </template><!-- /v-else (not loading) -->
                        </template><!-- /orders tab -->
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
                                class="chat-conv-avatar chat-conv-avatar--sm"
                                :class="{ 'chat-conv-avatar--clickable': !isSupport }"
                                @click="!isSupport && (avatarFullscreen = true)"
                                :title="!isSupport ? 'Посмотреть фото' : undefined"
                            >
                                <template v-if="isSupport">
                                    <span class="chat-support-icon">✦</span>
                                </template>
                                <template v-else>
                                    <img v-if="activeConversation.other_user?.avatar_url" :src="activeConversation.other_user.avatar_url" alt="" />
                                    <span v-else>{{ activeConversation.other_user?.name?.charAt(0) ?? '?' }}</span>
                                </template>
                            </div>
                            <div class="chat-main__header-info">
                                <div class="chat-main__name-row">
                                    <template v-if="isSupport">
                                        <span class="chat-main__name">Поддержка no alone</span>
                                    </template>
                                    <template v-else>
                                        <a
                                            :href="route('profile.show', { user: activeConversation.other_user?.id })"
                                            target="_blank"
                                            rel="noopener"
                                            class="chat-main__name"
                                        >{{ activeConversation.other_user?.name ?? '…' }}</a>
                                        <IdolBadge v-if="activeConversation.other_user?.is_idol" />
                                    </template>
                                </div>
                                <span v-if="!isSupport" class="chat-online-badge" :class="{ 'chat-online-badge--visible': isOtherOnline }">
                                    <span class="chat-online-dot"></span>онлайн
                                </span>
                            </div>
                            <button
                                v-if="!isSupport && (!activeBlock?.active || activeBlock?.i_am_blocker)"
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

                        <!-- Таймер авто-завершения — встроенный блок под шапкой -->
                        <Transition name="timer-pop">
                            <div v-if="activeOrderData?.status === 'paid'" class="chat-order-timer-bar">
                                <div class="chat-order-timer-bar__inner">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0">
                                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                                    </svg>
                                    <span class="chat-order-timer-bar__label">Авто-завершение через</span>
                                    <span class="chat-order-timer-bar__value">{{ orderTimerLabel }}</span>
                                </div>
                            </div>
                        </Transition>

                        <!-- Сообщения -->
                        <div class="chat-messages-wrap">
                        <div class="chat-messages" ref="messagesContainer" @scroll="onMessagesScroll">
                            <div v-if="loadingMsgs" class="chat-skeleton">
                                <div class="chat-skeleton__row chat-skeleton__row--left">
                                    <div class="chat-skeleton__avatar"></div>
                                    <div class="chat-skeleton__bubbles">
                                        <div class="chat-skeleton__bubble" style="width:54%"></div>
                                    </div>
                                </div>
                                <div class="chat-skeleton__row chat-skeleton__row--right">
                                    <div class="chat-skeleton__bubbles">
                                        <div class="chat-skeleton__bubble" style="width:38%"></div>
                                        <div class="chat-skeleton__bubble" style="width:62%"></div>
                                    </div>
                                    <div class="chat-skeleton__avatar"></div>
                                </div>
                                <div class="chat-skeleton__row chat-skeleton__row--left">
                                    <div class="chat-skeleton__avatar"></div>
                                    <div class="chat-skeleton__bubbles">
                                        <div class="chat-skeleton__bubble" style="width:72%"></div>
                                        <div class="chat-skeleton__bubble" style="width:45%"></div>
                                    </div>
                                </div>
                                <div class="chat-skeleton__row chat-skeleton__row--right">
                                    <div class="chat-skeleton__bubbles">
                                        <div class="chat-skeleton__bubble" style="width:48%"></div>
                                    </div>
                                    <div class="chat-skeleton__avatar"></div>
                                </div>
                                <div class="chat-skeleton__row chat-skeleton__row--left">
                                    <div class="chat-skeleton__avatar"></div>
                                    <div class="chat-skeleton__bubbles">
                                        <div class="chat-skeleton__bubble" style="width:60%"></div>
                                    </div>
                                </div>
                                <div class="chat-skeleton__row chat-skeleton__row--right">
                                    <div class="chat-skeleton__bubbles">
                                        <div class="chat-skeleton__bubble" style="width:55%"></div>
                                        <div class="chat-skeleton__bubble" style="width:30%"></div>
                                    </div>
                                    <div class="chat-skeleton__avatar"></div>
                                </div>
                            </div>
                            <template v-else>
                                <!-- Индикатор подгрузки -->
                                <div v-if="loadingMore" class="chat-loading-more">Загрузка…</div>

                                <TransitionGroup name="msg" tag="div" class="chat-messages-inner">
                                    <template v-for="item in groupedMessages" :key="item.key ?? item.msg?.id">
                                        <!-- Date divider -->
                                        <div v-if="item.type === 'divider'" class="chat-date-divider">
                                            <span>{{ item.label }}</span>
                                        </div>

                                        <!-- System message -->
                                        <div v-else-if="item.type === 'message' && item.msg.type === 'system'" class="chat-system-msg">
                                            <template v-if="item.msg.metadata?.event === 'order_created'">
                                                <div class="sc-card">
                                                    <p class="sc-title">ЗАКАЗ ОФОРМЛЕН</p>
                                                    <div class="sc-rule sc-rule--double"></div>
                                                    <div class="sc-lines">
                                                        <div v-for="s in item.msg.metadata.services" :key="s.id" class="sc-line">
                                                            <span class="sc-line__name">{{ s.name }}</span>
                                                            <span class="sc-line__dots"></span>
                                                            <span class="sc-line__qty" v-if="(s.quantity ?? 1) > 1">×{{ s.quantity }}</span>
                                                            <span class="sc-line__price">{{ (s.price * (s.quantity ?? 1))?.toLocaleString('ru-RU') }}&thinsp;₽<template v-if="s.time_unit">&thinsp;/&thinsp;{{ s.time_unit }}</template></span>
                                                        </div>
                                                    </div>
                                                    <div class="sc-perf"><span class="sc-perf__line"></span></div>
                                                    <div class="sc-total">
                                                        <span class="sc-total__label">ИТОГО</span>
                                                        <span class="sc-total__value">{{ item.msg.metadata.services.reduce((sum, s) => sum + (s.price ?? 0) * (s.quantity ?? 1), 0).toLocaleString('ru-RU') }}&thinsp;₽</span>
                                                    </div>
                                                    <p class="sc-date">{{ formatTime(item.msg.created_at) }}</p>
                                                </div>
                                            </template>
                                            <template v-else-if="item.msg.metadata?.event === 'order_accepted'">
                                                <div class="sc-card sc-card--accept">
                                                    <div class="sc-rule sc-rule--double sc-rule--green"></div>
                                                    <p class="sc-title sc-title--accept">
                                                        {{
                                                            item.msg.metadata.idol_gender === 'male'   ? 'ГОТОВ ПРИНЯТЬ ЗАКАЗ' :
                                                            item.msg.metadata.idol_gender === 'female' ? 'ГОТОВА ПРИНЯТЬ ЗАКАЗ' :
                                                            'ГОТОВ(А) ПРИНЯТЬ ЗАКАЗ'
                                                        }}
                                                    </p>
                                                    <p class="sc-date">{{ formatTime(item.msg.created_at) }}</p>
                                                    <div class="sc-rule sc-rule--double sc-rule--green"></div>
                                                </div>
                                            </template>
                                            <template v-else-if="item.msg.metadata?.event === 'order_paid'">
                                                <div class="sc-card sc-card--paid">
                                                    <div class="sc-rule sc-rule--double sc-rule--cyan"></div>
                                                    <p class="sc-title sc-title--paid">ЗАКАЗ ОПЛАЧЕН</p>
                                                    <p class="sc-date sc-date--paid">{{ formatTime(item.msg.created_at) }}</p>
                                                    <div class="sc-rule sc-rule--double sc-rule--cyan"></div>
                                                </div>
                                            </template>
                                            <template v-else-if="item.msg.metadata?.event === 'order_cancelled'">
                                                <div class="sc-card sc-card--cancel">
                                                    <div class="sc-rule sc-rule--double sc-rule--red"></div>
                                                    <p class="sc-title sc-title--cancel">ЗАКАЗ ОТМЕНЁН</p>
                                                    <p class="sc-who sc-who--cancel">{{ item.msg.metadata.cancelled_by === authUser?.id ? 'Вами' : (item.msg.metadata.cancelled_by_name ?? 'Другой стороной') }}</p>
                                                    <p v-if="item.msg.metadata.cancel_reason" class="sc-reason">{{ item.msg.metadata.cancel_reason }}</p>
                                                    <p class="sc-date sc-date--cancel">{{ formatTime(item.msg.created_at) }}</p>
                                                    <div class="sc-rule sc-rule--double sc-rule--red"></div>
                                                </div>
                                            </template>
                                            <template v-else-if="item.msg.metadata?.event === 'item_added'">
                                                <div class="sc-card sc-card--update">
                                                    <p class="sc-title sc-title--update">ЗАКАЗ ОБНОВЛЁН</p>
                                                    <div class="sc-rule sc-rule--double sc-rule--cyan"></div>
                                                    <div class="sc-lines">
                                                        <div v-for="s in item.msg.metadata.services" :key="s.id" class="sc-line">
                                                            <span class="sc-line__name">{{ s.name }}</span>
                                                            <span class="sc-line__dots"></span>
                                                            <span class="sc-line__qty" v-if="(s.quantity ?? 1) > 1">×{{ s.quantity }}</span>
                                                            <span class="sc-line__price">{{ ((s.price ?? 0) * (s.quantity ?? 1)).toLocaleString('ru-RU') }}&thinsp;₽<template v-if="s.time_unit">&thinsp;/&thinsp;{{ s.time_unit }}</template></span>
                                                        </div>
                                                    </div>
                                                    <div class="sc-perf"><span class="sc-perf__line"></span></div>
                                                    <div class="sc-total">
                                                        <span class="sc-total__label">ИТОГО</span>
                                                        <span class="sc-total__value">{{ (item.msg.metadata.services ?? []).reduce((sum, s) => sum + (s.price ?? 0) * (s.quantity ?? 1), 0).toLocaleString('ru-RU') }}&thinsp;₽</span>
                                                    </div>
                                                    <p class="sc-date">{{ formatTime(item.msg.created_at) }}</p>
                                                </div>
                                            </template>
                                            <template v-else-if="item.msg.metadata?.event === 'completion_confirmed_by_idol'">
                                                <div class="sc-card sc-card--confirm">
                                                    <div class="sc-rule sc-rule--green"></div>
                                                    <p class="sc-title sc-title--confirm">ВЫПОЛНЕНИЕ ПОДТВЕРЖДЕНО</p>
                                                    <p class="sc-who">Айдол подтвердил завершение заказа</p>
                                                    <p class="sc-date">{{ formatTime(item.msg.created_at) }}</p>
                                                    <div class="sc-rule sc-rule--green"></div>
                                                </div>
                                            </template>
                                            <template v-else-if="item.msg.metadata?.event === 'completion_confirmed_by_customer'">
                                                <div class="sc-card sc-card--confirm">
                                                    <div class="sc-rule sc-rule--green"></div>
                                                    <p class="sc-title sc-title--confirm">ВЫПОЛНЕНИЕ ПОДТВЕРЖДЕНО</p>
                                                    <p class="sc-who">Заказчик подтвердил завершение заказа</p>
                                                    <p class="sc-date">{{ formatTime(item.msg.created_at) }}</p>
                                                    <div class="sc-rule sc-rule--green"></div>
                                                </div>
                                            </template>
                                            <template v-else-if="item.msg.metadata?.event === 'order_completed' || item.msg.metadata?.event === 'order_auto_completed'">
                                                <div v-if="activeOrderData?.is_customer" class="chat-repeat-wrap">
                                                    <button class="chat-repeat-btn" @click="repeatOrderOpen = true">↺ ПОВТОРИТЬ ЗАКАЗ</button>
                                                </div>
                                            </template>
                                            <template v-else-if="item.msg.metadata?.event === 'chat_closed'">
                                                <div class="chat-event-label">// Чат закрыт // <span class="chat-event-label__time">{{ formatTime(item.msg.created_at) }}</span></div>
                                            </template>
                                            <template v-else-if="item.msg.metadata?.event === 'chat_opened'">
                                                <div class="chat-event-label">// Чат открыт // <span class="chat-event-label__time">{{ formatTime(item.msg.created_at) }}</span></div>
                                            </template>
                                            <template v-else-if="item.msg.metadata?.event === 'review_submitted'">
                                                <div class="chat-event-label chat-event-label--review">// Отзыв отправлен //</div>
                                            </template>
                                            <template v-else>
                                                <div class="chat-event-label">{{ item.msg.body || '—' }} <span class="chat-event-label__time">{{ formatTime(item.msg.created_at) }}</span></div>
                                            </template>
                                        </div>

                                        <!-- Service offer message — one bubble per service -->
                                        <template v-else-if="item.type === 'message' && item.msg.type === 'service_offer'">
                                            <div
                                                v-for="(svc, svcIdx) in (item.msg.metadata?.services ?? [])"
                                                :key="svc.id"
                                                class="chat-msg"
                                                :class="{
                                                    'chat-msg--mine': item.msg.sender_id === authUser?.id,
                                                    'chat-msg--first-in-group': item.isFirstInGroup && svcIdx === 0,
                                                    'chat-msg--last-in-group': item.isLastInGroup && svcIdx === (item.msg.metadata?.services?.length ?? 1) - 1,
                                                }"
                                            >
                                                <div class="svc-offer-bubble">
                                                    <div v-if="svcIdx === 0" class="svc-offer__header">✦ Предложение</div>
                                                    <div class="svc-offer__card">
                                                        <span class="svc-offer__svc">{{ svc.name }}<template v-if="svc.time_unit">&thinsp;/&thinsp;{{ svc.time_unit }}</template></span>
                                                        <button
                                                            v-if="item.msg.sender_id !== authUser?.id && (!activeOrderData || activeOrderData.status === 'pending' || !activeOrderData.id)"
                                                            class="svc-offer__cart-btn"
                                                            @click="addServiceToCart(svc)"
                                                        ><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
                                                            <line x1="3" y1="6" x2="21" y2="6"/>
                                                            <path d="M16 10a4 4 0 01-8 0"/>
                                                        </svg></button>
                                                    </div>
                                                    <span
                                                        v-if="svcIdx === (item.msg.metadata?.services?.length ?? 1) - 1"
                                                        class="chat-msg__meta"
                                                    >
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

                                        <!-- Regular message -->
                                        <div
                                            v-else-if="item.type === 'message'"
                                            class="chat-msg"
                                            :class="{
                                                'chat-msg--mine': item.msg.sender_id === authUser?.id,
                                                'chat-msg--first-in-group': item.isFirstInGroup,
                                                'chat-msg--last-in-group': item.isLastInGroup,
                                            }"
                                        >
                                            <div class="chat-msg__bubble" :class="{ 'chat-msg__bubble--image': item.msg.type === 'image' && item.msg.metadata?.image_url }">
                                                <template v-if="item.msg.type === 'image' && item.msg.metadata?.image_url">
                                                    <a :href="item.msg.metadata.image_url" target="_blank" rel="noopener">
                                                        <img :src="item.msg.metadata.image_url" class="chat-msg__image" />
                                                    </a>
                                                </template>
                                                <span v-else class="chat-msg__text">{{ item.msg.body }}</span>
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
                                <ReviewForm
                                    v-if="showReviewForm"
                                    :order-id="activeOrderData.id"
                                    :idol-id="activeOrderData.idol.id"
                                    @submitted="onReviewSubmitted"
                                />
                                <RepeatOrderModal
                                    v-if="activeOrderData"
                                    :show="repeatOrderOpen"
                                    :order="activeOrderData"
                                    @created="onRepeatOrderCreated"
                                    @close="repeatOrderOpen = false"
                                />
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
                                <p class="chat-blocked-header">================================</p>
                                <p class="chat-blocked-title">// ДОСТУП ЗАПРЕЩЁН //</p>
                                <p class="chat-blocked-header">================================</p>
                                <p class="chat-blocked-reason">{{ activeBlock.reason }}</p>
                                <p class="chat-blocked-header">--------------------------------</p>
                                <div class="chat-blocked-timer">
                                    <span class="chat-blocked-timer__label" v-if="activeBlock.blocked_until">ОСТАЛОСЬ</span>
                                    <span class="chat-blocked-timer__label" v-else>СРОК БЛОКИРОВКИ</span>
                                    <span class="chat-blocked-timer__value">{{ blockedUntilLabel }}</span>
                                </div>
                                <p class="chat-blocked-header">================================</p>
                            </div>
                        </div>
                            <div class="chat-input-fade"></div>
                        </div><!-- end chat-messages-wrap -->

                        <!-- Поле ввода + панель заказа -->
                        <div class="chat-input-wrap">

                            <!-- Баннер: чат закрыт -->
                            <div v-if="isChatClosed" class="chat-closed-banner">
                                // Чат закрыт — ожидайте ответа поддержки //
                            </div>

                            <!-- Баннер блокировщика -->
                            <div v-if="activeBlock?.active && activeBlock?.i_am_blocker" class="chat-block-banner">
                                <div class="chat-block-banner__info">
                                    <span class="chat-block-banner__label">// ПОЛЬЗОВАТЕЛЬ ЗАБЛОКИРОВАН //</span>
                                    <span class="chat-block-banner__timer">
                                        {{ activeBlock.blocked_until ? blockedUntilLabel + ' осталось' : 'Навсегда' }}
                                    </span>
                                </div>
                                <button @click="submitUnblock" class="chat-block-unblock-btn">РАЗБЛОКИРОВАТЬ</button>
                            </div>

                            <!-- Панель действий заказа -->
                            <div v-if="activeOrderData && ['pending','accepted','paid'].includes(activeOrderData.status)" class="chat-order-actions">
                                <button
                                    v-if="!activeOrderData.is_customer && activeOrderData.status === 'pending'"
                                    class="chat-order-btn chat-order-btn--accept"
                                    @click="acceptOrder"
                                >{{ acceptBtnText.toUpperCase() }}</button>
                                <button
                                    v-if="activeOrderData.is_customer && activeOrderData.status === 'accepted'"
                                    class="chat-order-btn chat-order-btn--pay"
                                    @click="payOrder"
                                >ОПЛАТИТЬ ЗАКАЗ</button>
                                <button
                                    v-if="activeOrderData.status === 'paid'"
                                    class="chat-order-btn chat-order-btn--complete"
                                    :disabled="myConfirmation"
                                    @click="confirmCompletion"
                                >{{ myConfirmation ? 'ВЫ ПОДТВЕРДИЛИ' : 'ЗАКАЗ ВЫПОЛНЕН' }}</button>
                                <button
                                    v-if="['pending','accepted'].includes(activeOrderData.status)"
                                    class="chat-order-btn chat-order-btn--cancel"
                                    @click="cancelModal = true"
                                >ОТМЕНИТЬ ЗАКАЗ</button>
                                <!-- Предложить услугу — рядом с кнопками заказа -->
                                <button
                                    v-if="authUser?.is_idol && activeOrderData.status === 'pending'"
                                    class="chat-order-btn chat-order-btn--offer"
                                    @click="showOfferModal = true"
                                >✦ ПРЕДЛОЖИТЬ</button>
                            </div>

                            <!-- Кнопка предложения услуги — для обычного чата (без заказа) -->
                            <div v-if="authUser?.is_idol && !isSupport && !activeOrderData && !isChatClosed" class="chat-order-actions">
                                <button class="chat-order-btn chat-order-btn--offer" @click="showOfferModal = true">✦ ПРЕДЛОЖИТЬ</button>
                            </div>

                            <!-- Плашка: заказ отменён -->
                            <div v-if="activeOrderData?.status === 'cancelled'" class="chat-order-cancelled-bar">
                                <span class="chat-order-cancelled-bar__label">// ЗАКАЗ ОТМЕНЁН //</span>
                                <template v-if="cancelledByLabel(activeOrderData)">
                                    <span class="chat-order-cancelled-bar__who">{{ cancelledByLabel(activeOrderData) }}</span>
                                </template>
                                <span v-if="activeOrderData.cancel_reason" class="chat-order-cancelled-bar__reason">{{ activeOrderData.cancel_reason }}</span>
                            </div>

                            <!-- Плашка: заказ выполнен -->
                            <div v-if="activeOrderData?.status === 'completed'" class="chat-order-completed-bar">
                                <span class="chat-order-completed-bar__label">// ЗАКАЗ ВЫПОЛНЕН //</span>
                                <span v-if="activeOrderData.completed_at" class="chat-order-completed-bar__time">{{ formatDate(activeOrderData.completed_at) }}</span>
                            </div>

                            <!-- Плашка: спор -->
                            <div v-if="activeOrderData?.status === 'disputed'" class="chat-order-disputed-bar">
                                <span class="chat-order-disputed-bar__label">// ОТКРЫТ СПОР — ЧАТ ЗАМОРОЖЕН //</span>
                            </div>

                            <!-- Плашка: заказ аннулирован -->
                            <div v-if="activeOrderData?.status === 'refunded'" class="chat-order-cancelled-bar">
                                <span class="chat-order-cancelled-bar__label">// ЗАКАЗ АННУЛИРОВАН //</span>
                            </div>

                            <!-- Textarea (скрыт если заказ завершён в финальном статусе) -->
                            <div v-if="!activeOrderData || !['cancelled', 'completed', 'disputed', 'refunded'].includes(activeOrderData.status)" class="chat-input-inner">
                                <input
                                    v-if="isSupport"
                                    type="file"
                                    ref="fileInput"
                                    accept="image/*"
                                    style="display:none"
                                    @change="onFileChange"
                                />
                                <button
                                    v-if="isSupport"
                                    class="chat-attach-btn"
                                    :disabled="isChatClosed || uploading || (!!activeBlock?.active && !activeBlock?.i_am_blocker)"
                                    @click="fileInput.click()"
                                    title="Прикрепить фото"
                                >
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                                    </svg>
                                </button>
                                <textarea
                                    v-model="newMessage"
                                    class="chat-input"
                                    placeholder="Сообщение…"
                                    rows="3"
                                    maxlength="500"
                                    :disabled="isChatClosed || (!!activeBlock?.active && !activeBlock?.i_am_blocker)"
                                    @keydown.enter="handleEnter"
                                    @input="onInput"
                                />
                                <span class="chat-char-count" :class="{ 'chat-char-count--warn': newMessage.length > 450 }">
                                    {{ newMessage.length }}/500
                                </span>
                                <button class="chat-send" :disabled="!newMessage.trim() || sending || isChatClosed || (!!activeBlock?.active && !activeBlock?.i_am_blocker)" @click="sendMessage">
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

    <!-- ── Модалка отмены заказа ─────────────────────── -->
    <SiteModal :show="cancelModal" variant="pink" compact @close="cancelModal = false">
        <div class="cm-wrap">
            <div class="cm-rule cm-rule--double cm-rule--red"></div>
            <h2 class="cm-title">ОТМЕНА ЗАКАЗА</h2>
            <div class="cm-rule cm-rule--double cm-rule--red"></div>

            <div class="cm-section-label">// ВЫБЕРИТЕ ПРИЧИНУ</div>
            <div class="cm-tags">
                <button
                    v-for="t in cancelTemplates"
                    :key="t"
                    class="cm-tag"
                    :class="{ 'cm-tag--selected': cancelReason === t }"
                    @click="cancelReason = t"
                >{{ t }}</button>
            </div>

            <div class="cm-section-label">// ИЛИ НАПИШИТЕ СВОЮ</div>
            <textarea
                v-model="cancelReason"
                class="cm-textarea"
                placeholder="причина отмены…"
                rows="3"
                maxlength="1000"
            ></textarea>

            <div class="cm-perf"><span class="cm-perf__line"></span></div>

            <div class="cm-footer">
                <button class="cm-btn cm-btn--back" @click="cancelModal = false">НАЗАД</button>
                <button
                    class="cm-btn cm-btn--confirm"
                    :disabled="!cancelReason.trim() || cancelSubmitting"
                    @click="submitCancelOrder"
                >{{ cancelSubmitting ? 'ОТМЕНЯЕМ…' : 'ПОДТВЕРДИТЬ' }}</button>
            </div>
        </div>
    </SiteModal>

    <!-- ── Подтверждение добавления услуги к заказу ────────── -->
    <SiteModal :show="confirmAddModal" variant="cyan" compact max-width="420px" @close="confirmAddModal = false">
        <div class="cm-title cm-title--cyan">ДОБАВИТЬ К ЗАКАЗУ</div>
        <div class="confirm-add__svc">{{ confirmAddService?.name }}</div>
        <div v-if="confirmAddService?.price" class="confirm-add__price">
            {{ confirmAddService.price.toLocaleString('ru-RU') }}&thinsp;₽<template v-if="confirmAddService.time_unit">&thinsp;/&thinsp;{{ confirmAddService.time_unit }}</template>
        </div>
        <div class="cm-perf"><span class="cm-perf__line cm-perf__line--cyan"></span></div>
        <div class="cm-footer">
            <button class="cm-btn cm-btn--back" @click="confirmAddModal = false">НАЗАД</button>
            <button class="cm-btn cm-btn--confirm-cyan" :disabled="confirmAddLoading" @click="confirmAddToOrder">
                {{ confirmAddLoading ? 'ДОБАВЛЕНИЕ…' : 'ДОБАВИТЬ' }}
            </button>
        </div>
    </SiteModal>

    <!-- ── Модалка предложения услуги ───────────────────── -->
    <ServiceOfferModal
        v-if="activeConversation"
        v-model="showOfferModal"
        :conversation-id="activeConversation.id"
        @sent="onOfferSent"
    />

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
    top: 60px;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 999;
    background: rgba(0, 0, 0, 0.4);
}
.backdrop-enter-active, .backdrop-leave-active { transition: opacity 0.25s; }
.backdrop-enter-from, .backdrop-leave-to { opacity: 0; }

/* ── Panel ────────────────────────────────────────────── */
.chat-panel {
    position: fixed;
    top: 60px;
    right: 0;
    bottom: 0;
    width: 1100px;
    max-width: 100vw;
    z-index: 1000;
    display: flex;
    background: linear-gradient(160deg, #0f0f22 0%, #0a0a16 100%);
    border-left: 1px solid rgba(110, 110, 210, 0.22);
    box-shadow: -8px 0 64px rgba(0, 0, 0, 0.7), -1px 0 0 rgba(160, 100, 255, 0.06);
}

@media (min-width: 1440px) {
    .chat-panel { width: 1320px; }
}

.slide-enter-active, .slide-leave-active { transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1); }
.slide-enter-from, .slide-leave-to { transform: translateX(100%); }

/* ── Sidebar ──────────────────────────────────────────── */
.chat-sidebar {
    width: 340px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(110, 110, 210, 0.14);
    background: #0b0b18;
    background-image: radial-gradient(ellipse 260px 140px at 50% 0%, rgba(110, 110, 210, 0.1) 0%, transparent 100%);
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

/* ── Messages skeleton loader ─────────────────────────── */
.chat-skeleton {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
    padding: 1.5rem 1rem 2rem;
    overflow: hidden;
}
.chat-skeleton__row {
    display: flex;
    align-items: flex-end;
    gap: 0.6rem;
}
.chat-skeleton__row--right { flex-direction: row-reverse; }

.chat-skeleton__avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    flex-shrink: 0;
    background: rgba(100,200,255,0.07);
    overflow: hidden;
    position: relative;
}
.chat-skeleton__bubbles {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    flex: 1;
    min-width: 0;
}
.chat-skeleton__row--right .chat-skeleton__bubbles {
    align-items: flex-end;
}
.chat-skeleton__bubble {
    height: 36px;
    border-radius: 12px;
    background: rgba(100,200,255,0.07);
    position: relative;
    overflow: hidden;
}
.chat-skeleton__row--right .chat-skeleton__bubble {
    background: rgba(160,130,255,0.07);
}

/* GPU-accelerated shimmer via translateX on ::after */
.chat-skeleton__bubble::after,
.chat-skeleton__avatar::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255,255,255,0.09) 50%,
        transparent 100%
    );
    transform: translateX(-100%);
    animation: skel-slide 1.2s ease-in-out infinite;
    will-change: transform;
}

@keyframes skel-slide {
    0%   { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
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
    position: relative;
}
.chat-conv-item:hover {
    background: rgba(110, 110, 210, 0.06);
}
.chat-conv-item--active {
    background: rgba(120, 90, 255, 0.1);
}
.chat-conv-item--active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 20%;
    bottom: 20%;
    width: 3px;
    border-radius: 0 3px 3px 0;
    background: linear-gradient(to bottom, #be91ff, #7060e0);
}
.chat-conv-item--active:hover { background: rgba(120, 90, 255, 0.14); transform: none; }

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
    background: linear-gradient(135deg, #e0558f, #b03070);
    box-shadow: 0 2px 8px rgba(224, 85, 143, 0.4);
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
    border-bottom: none;
    box-shadow: 0 1px 0 rgba(110, 110, 210, 0.12), 0 4px 20px rgba(0, 0, 0, 0.25);
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
    padding-bottom: 1.5rem;
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
    color: rgba(255, 255, 255, 0.22);
    font-size: 0.74rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.chat-date-divider::before {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(to right, transparent, rgba(110, 110, 210, 0.22));
}
.chat-date-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(to left, transparent, rgba(110, 110, 210, 0.22));
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
    background: linear-gradient(135deg, rgba(80, 80, 160, 0.22) 0%, rgba(60, 60, 130, 0.16) 100%);
    border: 1px solid rgba(130, 130, 210, 0.22);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(180, 180, 255, 0.07);
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.chat-msg--mine .chat-msg__bubble {
    background: linear-gradient(135deg, rgba(130, 80, 255, 0.26) 0%, rgba(100, 55, 215, 0.2) 100%);
    border-color: rgba(160, 100, 255, 0.22);
    box-shadow: 0 2px 12px rgba(100, 55, 215, 0.18), inset 0 1px 0 rgba(200, 160, 255, 0.08);
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
    font-size: 1.05rem;
    color: rgba(255, 255, 255, 0.92);
    line-height: 1.45;
    white-space: pre-wrap;
    word-break: break-word;
}

.chat-msg__meta {
    display: flex;
    align-items: center;
    gap: 4px;
    align-self: flex-end;
    justify-content: flex-end;
    margin-top: 1px;
}
.chat-msg__time {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.45);
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
    flex-shrink: 0;
    padding: 0 1.1rem 0.85rem;
    background: transparent;
}
.chat-input-fade {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: linear-gradient(to bottom, transparent, #0e0e1c);
    pointer-events: none;
    z-index: 1;
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
    background: linear-gradient(90deg, transparent, rgba(155, 110, 232, 0.4), rgba(190, 145, 255, 0.7), rgba(155, 110, 232, 0.4), transparent);
    box-shadow: 0 0 12px rgba(155, 110, 232, 0.2);
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
    padding: 0.65rem 3.5rem 2.25rem 0.85rem;
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
    border-color: rgba(160, 100, 255, 0.4);
    background: rgba(255, 255, 255, 0.05);
    box-shadow: 0 0 0 3px rgba(130, 80, 255, 0.08);
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
    background: linear-gradient(135deg, rgba(140, 90, 255, 0.3), rgba(100, 55, 210, 0.25));
    border: 1px solid rgba(160, 100, 255, 0.4);
    border-radius: 4px;
    color: rgba(210, 170, 255, 0.95);
    font-family: inherit;
    font-size: 0.78rem;
    font-weight: 500;
    letter-spacing: 0.03em;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(100, 55, 210, 0.25);
    transition: background 0.15s, border-color 0.15s, color 0.15s, box-shadow 0.15s;
}
.chat-send:hover:not(:disabled) {
    background: linear-gradient(135deg, rgba(155, 100, 255, 0.42), rgba(110, 65, 220, 0.36));
    border-color: rgba(180, 120, 255, 0.6);
    color: #d4aaff;
    box-shadow: 0 2px 16px rgba(120, 60, 230, 0.4);
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
    border: 1px solid rgba(220, 60, 60, 0.25);
    background: rgba(220, 60, 60, 0.08);
    border-radius: 8px;
    color: rgba(220, 60, 60, 0.65);
    cursor: pointer;
    transition: color 0.15s, background 0.15s, border-color 0.15s;
}
.chat-lock-btn--active {
    color: rgba(255, 80, 80, 0.95);
    background: rgba(200, 30, 30, 0.18);
    border-color: rgba(220, 60, 60, 0.5);
}
.chat-lock-btn:hover {
    color: rgba(255, 80, 80, 0.95);
    background: rgba(200, 30, 30, 0.18);
    border-color: rgba(220, 60, 60, 0.45);
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
    gap: 0.45rem;
    background: rgba(180, 20, 20, 0.1);
    border: 2px dashed rgba(220, 60, 60, 0.5);
    border-radius: 3px;
    padding: 1.5rem 2rem;
    max-width: 320px;
    font-family: 'Courier New', Courier, monospace;
    text-align: center;
}
.chat-blocked-header {
    font-size: 0.75rem;
    color: rgba(255, 80, 80, 0.35);
    margin: 0;
    letter-spacing: 0.02em;
    user-select: none;
}
.chat-blocked-title {
    font-size: 1rem;
    font-weight: 700;
    color: rgba(255, 90, 90, 0.95);
    margin: 0;
    letter-spacing: 0.1em;
}
.chat-blocked-reason {
    font-size: 0.9rem;
    color: rgba(255, 200, 200, 0.75);
    margin: 0.2rem 0;
    letter-spacing: 0.03em;
}
.chat-blocked-timer {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.15rem;
    margin: 0.2rem 0;
}
.chat-blocked-timer__value {
    font-size: 1.4rem;
    font-weight: 700;
    color: rgba(255, 90, 90, 0.95);
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.06em;
    line-height: 1.2;
}
.chat-blocked-timer__label {
    font-size: 0.7rem;
    color: rgba(255, 90, 90, 0.5);
    letter-spacing: 0.15em;
    text-transform: uppercase;
}

/* ── Block banner (for blocker) ───────────────────────── */
.chat-block-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.45rem 1.1rem;
    background: rgba(200, 30, 30, 0.12);
    border-top: 2px dashed rgba(220, 60, 60, 0.4);
    border-bottom: 2px dashed rgba(220, 60, 60, 0.4);
    font-family: 'Courier New', Courier, monospace;
    flex-shrink: 0;
}
.chat-block-banner__info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.chat-block-banner__label {
    font-size: 0.95rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: rgba(255, 100, 100, 0.9);
}
.chat-block-banner__timer {
    font-size: 0.78rem;
    letter-spacing: 0.05em;
    color: rgba(255, 100, 100, 0.55);
}
.chat-block-unblock-btn {
    background: transparent;
    border: 1px solid rgba(220, 60, 60, 0.45);
    color: rgba(255, 100, 100, 0.85);
    font-size: 0.88rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    padding: 0.3rem 0.9rem;
    border-radius: 3px;
    cursor: pointer;
    font-family: 'Courier New', Courier, monospace;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
    white-space: nowrap;
}
.chat-block-unblock-btn:hover {
    border-color: rgba(220, 60, 60, 0.8);
    color: rgba(255, 120, 120, 1);
    background: rgba(200, 30, 30, 0.12);
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
.bm-submit--danger {
    background: linear-gradient(135deg, rgba(220, 60, 60, 0.45), rgba(180, 30, 30, 0.4));
    border: 1px solid rgba(220, 80, 80, 0.5);
    color: #ffaaaa;
}
.bm-submit--danger:hover:not(:disabled) {
    background: linear-gradient(135deg, rgba(220, 60, 60, 0.65), rgba(180, 30, 30, 0.6));
    border-color: rgba(220, 80, 80, 0.75);
}
.bm-textarea {
    width: 100%;
    background: rgba(110,110,210,0.07);
    border: 1px solid rgba(110,110,210,0.2);
    border-radius: 8px;
    color: rgba(255,255,255,0.85);
    padding: 0.55rem 0.75rem;
    font-size: 0.875rem;
    font-family: inherit;
    resize: vertical;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.15s;
}
.bm-textarea:focus { border-color: rgba(160,160,255,0.4); }

/* ── Cancel order modal ─────────────────────────────────── */
.cm-wrap {
    font-family: 'Courier New', Courier, monospace;
    color: rgba(210, 240, 255, 0.78);
    display: flex;
    flex-direction: column;
    gap: 0;
}
.cm-rule {
    width: 100%;
    height: 0;
    border: none;
    border-top: 2px double rgba(100,210,255,0.3);
    margin: 0.5rem 0;
}
.cm-rule--red { border-color: rgba(220,80,80,0.45); }

.cm-title {
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.22em;
    color: rgba(255,120,120,0.9);
    text-align: center;
    margin: 0.3rem 0;
}
.cm-title--cyan { color: rgba(80,230,200,0.9); }
.cm-perf__line--cyan { border-top-color: rgba(60,200,180,0.3); border-top-style: dashed; }

.confirm-add__svc {
    font-size: 1.05rem;
    color: rgba(255,255,255,0.88);
    text-align: center;
    margin: 0.75rem 0 0.25rem;
    font-weight: 500;
}
.confirm-add__price {
    font-size: 0.95rem;
    color: rgba(100,200,255,0.75);
    text-align: center;
    font-family: 'Courier New', monospace;
    margin-bottom: 0.75rem;
}
.cm-section-label {
    font-size: 0.68rem;
    letter-spacing: 0.12em;
    color: rgba(210,240,255,0.3);
    margin: 0.9rem 0 0.45rem;
}
.cm-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 0.25rem;
}
.cm-tag {
    padding: 0.32rem 0.7rem;
    border-radius: 3px;
    border: 1px dashed rgba(210,240,255,0.2);
    background: transparent;
    color: rgba(210,240,255,0.5);
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.75rem;
    letter-spacing: 0.04em;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.cm-tag:hover {
    border-color: rgba(210,240,255,0.45);
    color: rgba(210,240,255,0.85);
    background: rgba(100,210,255,0.05);
}
.cm-tag--selected {
    border-color: rgba(100,210,255,0.55);
    color: rgba(100,210,255,0.95);
    background: rgba(100,210,255,0.08);
    border-style: solid;
}
.cm-textarea {
    width: 100%;
    box-sizing: border-box;
    background: rgba(100,210,255,0.04);
    border: 1px dashed rgba(100,210,255,0.22);
    border-radius: 3px;
    color: rgba(210,240,255,0.82);
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.82rem;
    padding: 0.65rem 0.85rem;
    resize: none;
    outline: none;
    transition: border-color 0.15s;
    margin-top: 0.1rem;
}
.cm-textarea::placeholder { color: rgba(210,240,255,0.2); }
.cm-textarea:focus { border-color: rgba(100,210,255,0.45); border-style: solid; }

.cm-perf {
    display: flex;
    align-items: center;
    margin: 1rem -0.1rem 0.85rem;
    position: relative;
}
.cm-perf::before,
.cm-perf::after {
    content: '';
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #12122a;
    border: 1px solid rgba(220,80,80,0.15);
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
}
.cm-perf::before { left: -5px; }
.cm-perf::after  { right: -5px; }
.cm-perf__line {
    flex: 1;
    display: block;
    border-top: 1px dashed rgba(220,80,80,0.3);
    margin: 0 7px;
}

.cm-footer {
    display: flex;
    gap: 0.55rem;
}
.cm-btn {
    flex: 1;
    padding: 0.65rem 1rem;
    border-radius: 3px;
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.15em;
    cursor: pointer;
    line-height: 1;
    transition: background 0.15s, border-color 0.15s;
}
.cm-btn--back {
    background: transparent;
    border: 1px solid rgba(210,240,255,0.25);
    color: rgba(210,240,255,0.65);
}
.cm-btn--back:hover {
    border-color: rgba(210,240,255,0.5);
    color: rgba(210,240,255,0.9);
}
.cm-btn--confirm {
    background: rgba(220,60,60,0.08);
    border: 1px solid rgba(220,60,60,0.4);
    color: rgba(255,120,120,0.9);
}
.cm-btn--confirm:hover:not(:disabled) {
    background: rgba(220,60,60,0.18);
    border-color: rgba(220,60,60,0.65);
}
.cm-btn--confirm:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}
.cm-btn--confirm-cyan {
    background: rgba(60,200,180,0.08);
    border: 1px solid rgba(60,200,180,0.4);
    color: rgba(80,230,200,0.9);
}
.cm-btn--confirm-cyan:hover:not(:disabled) {
    background: rgba(60,200,180,0.18);
    border-color: rgba(60,200,180,0.65);
}
.cm-btn--confirm-cyan:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

/* ── Chat tabs ──────────────────────────────────────────── */
.chat-tabs {
    display: flex;
    border-bottom: 1px solid rgba(160,160,255,0.12);
    flex-shrink: 0;
}
.chat-tab {
    flex: 1;
    padding: 0.55rem 0;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: rgba(255,255,255,0.35);
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s;
    font-family: inherit;
}
.chat-tab:hover { color: rgba(255,255,255,0.65); }
.chat-tab--active {
    color: var(--color-base-1);
    border-bottom-color: var(--color-base-1);
}
.chat-tab {
    position: relative;
}
.chat-tab__dot {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #e0558f;
    vertical-align: middle;
    margin-left: 4px;
    flex-shrink: 0;
}

/* ── Order stub cards ───────────────────────────────────── */
.order-stub {
    display: flex;
    flex-direction: column;
    width: calc(100% - 1.25rem);
    margin: 0.75rem auto;
    padding: 0;
    border: none;
    border-radius: 7px;
    background: rgba(255,255,255,0.035);
    cursor: pointer;
    text-align: left;
    position: relative;
    overflow: visible;
    transition: background 0.15s, box-shadow 0.15s;
    box-shadow: 0 1px 8px rgba(0,0,0,0.35), inset 0 0 0 1px rgba(110,110,210,0.13);
}

.order-stub:hover {
    background: rgba(255,255,255,0.055);

    box-shadow: 0 4px 16px rgba(0,0,0,0.45), inset 0 0 0 1px rgba(140,110,255,0.2);
}
.order-stub--active {
    background: rgba(160,160,255,0.1);
    box-shadow: 0 2px 14px rgba(160,160,255,0.18), inset 0 0 0 1px rgba(160,160,255,0.28);
}
.order-stub--active:hover { transform: none; }

/* head */
.order-stub__head {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.6rem 0.75rem 0.55rem;
}
.order-stub__who {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 1px;
}
.order-stub__name {
    font-size: 0.87rem;
    font-weight: 600;
    color: rgba(255,255,255,0.88);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.order-stub__date {
    font-size: 0.68rem;
    color: rgba(255,255,255,0.28);
}
.order-stub__badge {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    padding: 0.14rem 0.42rem;
    border-radius: 3px;
    flex-shrink: 0;
    margin-top: 0.1rem;
}
.order-stub__badge--pending,
.order-stub__badge--accepted,
.order-stub__badge--paid      { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.75);  border: 1px solid rgba(255,255,255,0.12); }
.order-stub__badge--completed { background: rgba(80,240,160,0.1);   color: rgba(80,240,160,0.9);    border: 1px solid rgba(80,240,160,0.25); }
.order-stub__badge--cancelled,
.order-stub__badge--refunded,
.order-stub__badge--disputed  { background: rgba(255,110,110,0.1);  color: rgba(255,110,110,0.85);  border: 1px solid rgba(255,110,110,0.25); }

/* perforated tear line */
.order-stub__perf {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 0;
    position: relative;
    height: 10px;
    margin: 0 -1px;
}
.order-stub__perf::before,
.order-stub__perf::after {
    content: '';
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #0b0b18;
    border: 1px solid rgba(110,110,210,0.13);
    z-index: 2;
}
.order-stub__perf::before { left: -4px; }
.order-stub__perf::after  { right: -4px; }
.order-stub__perf-dot {
    flex: 1;
    height: 1px;
    background: rgba(255,255,255,0.07);
    margin: 0 1px;
    border-radius: 1px;
}

/* foot */
.order-stub__foot {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding: 0.45rem 0.75rem 0.55rem;
}
.order-stub__count {
    font-size: 0.82rem;
    color: rgba(255,255,255,0.45);
    letter-spacing: 0.02em;
}
.order-stub__total {
    font-size: 0.92rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: rgba(255,255,255,0.9);
    font-variant-numeric: tabular-nums;
}

/* ── Order sub-tabs (Мои / Входящие) ───────────────────── */
.chat-order-subtabs {
    display: flex;
    gap: 0.4rem;
    margin: 0.6rem 0.75rem 0.35rem;
    padding: 0;
}
.chat-order-subtab {
    flex: 1;
    padding: 0.42rem 0;
    font-size: 0.76rem;
    font-weight: 600;
    border: 1px solid rgba(255,255,255,0.07);
    border-top: none;
    border-radius: 6px;
    background: rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.38);
    cursor: pointer;
    transition: background 0.15s, color 0.15s, border-color 0.15s, box-shadow 0.15s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.18);
    position: relative;
}
.chat-order-subtab:hover {
    color: rgba(255,255,255,0.65);
    border-color: rgba(255,255,255,0.13);
    background: rgba(255,255,255,0.07);
}
.chat-order-subtab--active {
    background: rgba(160,160,255,0.13);
    border-color: rgba(160,160,255,0.3);
    border-top: none;
    color: rgba(200,200,255,0.95);
    box-shadow: 0 2px 8px rgba(160,160,255,0.18), inset 0 1px 0 rgba(200,200,255,0.3);
}
.chat-order-subtab--active:hover {
    background: rgba(160,160,255,0.18);
}

/* ── Order filters ───────────────────────────────────────── */
.order-filters {
    padding: 0.75rem 0.75rem 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    flex-shrink: 0;
    border-bottom: 1px solid rgba(110,110,210,0.1);
}
.order-filters__search-input {
    padding-top: 0.28rem;
    padding-bottom: 0.28rem;
}
.order-filters__toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.32rem 0.65rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 4px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.4);
    cursor: pointer;
    font-family: inherit;
    white-space: nowrap;
    flex-shrink: 0;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.order-filters__toggle:hover {
    color: rgba(255,255,255,0.65);
    background: rgba(255,255,255,0.07);
}
.order-filters__toggle--open {
    color: var(--color-base-1);
    border-color: rgba(160,160,255,0.35);
    background: rgba(160,160,255,0.08);
}
.order-filters__arrow {
    transition: transform 0.2s ease;
}
.order-filters__toggle--open .order-filters__arrow {
    transform: rotate(180deg);
}

/* pills expand transition */
.of-expand-enter-active, .of-expand-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}
.of-expand-enter-from, .of-expand-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.order-filters__pills {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
    padding-bottom: 0.1rem;
}
.order-filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.38rem 0.8rem;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 4px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.4);
    cursor: pointer;
    font-family: inherit;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.order-filter-pill:hover {
    color: rgba(255,255,255,0.65);
    background: rgba(255,255,255,0.07);
}
.order-filter-pill--active {
    color: rgba(200,200,255,0.95);
    border-color: rgba(160,160,255,0.4);
    background: rgba(160,160,255,0.12);
}
.order-filter-pill--pending.order-filter-pill--active {
    color: rgba(255,210,80,0.9);
    border-color: rgba(180,130,0,0.45);
    background: rgba(180,130,0,0.14);
}
.order-filter-pill--accepted.order-filter-pill--active {
    color: rgba(80,240,160,0.9);
    border-color: rgba(0,180,100,0.4);
    background: rgba(0,180,100,0.12);
}
.order-filter-pill--cancelled.order-filter-pill--active {
    color: rgba(255,130,130,0.85);
    border-color: rgba(180,50,50,0.4);
    background: rgba(180,50,50,0.12);
}
.order-filter-pill__count {
    font-size: 0.7rem;
    font-weight: 700;
    opacity: 0.6;
    min-width: 16px;
    text-align: center;
}
.order-filter-pill--active .order-filter-pill__count { opacity: 0.85; }

/* ── Order badge in sidebar ─────────────────────────────── */
.chat-order-status-row { margin-top: 0.15rem; }
.chat-order-badge {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 0.12rem 0.45rem;
    border-radius: 3px;
}
.chat-order-badge--pending  { background: rgba(180,130,0,0.18);  color: rgba(255,210,80,0.85);  border: 1px solid rgba(180,130,0,0.3); }
.chat-order-badge--accepted { background: rgba(0,180,100,0.15);  color: rgba(100,255,180,0.85); border: 1px solid rgba(0,180,100,0.3); }
.chat-order-badge--cancelled{ background: rgba(180,50,50,0.15);  color: rgba(255,140,140,0.8);  border: 1px solid rgba(180,50,50,0.25); }

/* ── Order detail link ──────────────────────────────────── */
.chat-order-detail-link {
    display: block;
    text-align: center;
    font-size: 0.78rem;
    color: rgba(160,160,255,0.45);
    padding: 0.5rem 1rem;
    text-decoration: none;
    letter-spacing: 0.05em;
    transition: color 0.15s;
    border-bottom: 1px solid rgba(160,160,255,0.08);
}
.chat-order-detail-link:hover {
    color: rgba(160,160,255,0.85);
}

/* ── Order actions panel ────────────────────────────────── */
.chat-order-actions {
    display: flex;
    gap: 0.6rem;
    padding: 0.65rem 1.1rem 0.55rem;
    flex-shrink: 0;
    flex-wrap: wrap;
    border-top: 1px dashed rgba(100,210,255,0.1);
}
.chat-order-btn {
    flex: 1;
    min-width: 130px;
    padding: 1rem 1rem;
    border-radius: 3px;
    font-size: 0.88rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    line-height: 1;
    padding-left: 1rem;
    position: relative;
    overflow: hidden;
}
.chat-order-btn::before {
    content: '';
    position: absolute;
    inset: 2px;
    border-radius: 2px;
    opacity: 0;
    transition: opacity 0.15s;
}
.chat-order-btn::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    border-radius: 3px 3px 0 0;
}

.chat-order-btn--accept {
    background: rgba(60,200,110,0.07);
    border: 1px solid rgba(60,200,110,0.4);
    color: rgba(90,240,145,0.92);
    box-shadow: 0 0 12px rgba(60,200,110,0.06);
}
.chat-order-btn--accept::after {
    background: linear-gradient(90deg, transparent 0%, rgba(140,255,180,0.65) 50%, transparent 100%);
}
.chat-order-btn--accept::before { border: 1px dashed rgba(60,200,110,0.2); }
.chat-order-btn--accept:hover {
    background: rgba(60,200,110,0.14);
    border-color: rgba(60,200,110,0.65);
    box-shadow: 0 0 18px rgba(60,200,110,0.14);
}
.chat-order-btn--accept:hover::before { opacity: 1; }

.chat-order-btn--pay {
    background: rgba(100,210,255,0.07);
    border: 1px solid rgba(100,210,255,0.38);
    color: rgba(100,210,255,0.9);
    box-shadow: 0 0 12px rgba(100,210,255,0.06);
}
.chat-order-btn--pay::after {
    background: linear-gradient(90deg, transparent 0%, rgba(180,235,255,0.65) 50%, transparent 100%);
}
.chat-order-btn--pay::before { border: 1px dashed rgba(100,210,255,0.2); }
.chat-order-btn--pay:hover {
    background: rgba(100,210,255,0.14);
    border-color: rgba(100,210,255,0.65);
    box-shadow: 0 0 18px rgba(100,210,255,0.14);
}
.chat-order-btn--pay:hover::before { opacity: 1; }

.chat-order-btn--cancel {
    background: rgba(220,60,60,0.07);
    border: 1px solid rgba(220,60,60,0.35);
    color: rgba(255,120,120,0.88);
    box-shadow: 0 0 12px rgba(220,60,60,0.05);
}
.chat-order-btn--cancel::after {
    background: linear-gradient(90deg, transparent 0%, rgba(255,150,150,0.6) 50%, transparent 100%);
}
.chat-order-btn--cancel::before { border: 1px dashed rgba(220,60,60,0.2); }
.chat-order-btn--cancel:hover {
    background: rgba(220,60,60,0.15);
    border-color: rgba(220,60,60,0.6);
    box-shadow: 0 0 18px rgba(220,60,60,0.13);
}
.chat-order-btn--cancel:hover::before { opacity: 1; }

.chat-order-btn--complete {
    background: rgba(60,200,120,0.07);
    border: 1px solid rgba(60,200,120,0.35);
    color: rgba(80,240,160,0.88);
    box-shadow: 0 0 12px rgba(60,200,120,0.05);
}
.chat-order-btn--complete::after {
    background: linear-gradient(90deg, transparent 0%, rgba(80,240,160,0.6) 50%, transparent 100%);
}
.chat-order-btn--complete::before { border: 1px dashed rgba(60,200,120,0.2); }
.chat-order-btn--complete:hover:not(:disabled) {
    background: rgba(60,200,120,0.14);
    border-color: rgba(60,200,120,0.65);
    box-shadow: 0 0 18px rgba(60,200,120,0.14);
}
.chat-order-btn--complete:hover:not(:disabled)::before { opacity: 1; }
.chat-order-btn--complete:disabled {
    opacity: 0.5;
    cursor: default;
}

.chat-order-btn--offer {
    background: rgba(110,80,210,0.07);
    border: 1px solid rgba(110,80,210,0.3);
    color: rgba(155,110,232,0.85);
}
.chat-order-btn--offer::after {
    background: linear-gradient(90deg, transparent 0%, rgba(155,110,232,0.5) 50%, transparent 100%);
}
.chat-order-btn--offer::before { border: 1px dashed rgba(110,80,210,0.18); }
.chat-order-btn--offer:hover {
    background: rgba(110,80,210,0.15);
    border-color: rgba(110,80,210,0.5);
}
.chat-order-btn--offer:hover::before { opacity: 1; }

.chat-repeat-wrap {
    display: flex;
    justify-content: center;
}
.chat-repeat-btn {
    padding: 0.75rem 2rem;
    border-radius: 3px;
    font-size: 0.88rem;
    font-weight: 700;
    font-family: 'Courier New', Courier, monospace;
    letter-spacing: 0.1em;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    background: rgba(100, 210, 255, 0.06);
    border: 1px solid rgba(100, 210, 255, 0.28);
    color: var(--color-base-2);
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.chat-repeat-btn::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(100, 210, 255, 0.45) 50%, transparent 100%);
}
.chat-repeat-btn::before {
    content: '';
    position: absolute;
    inset: 2px;
    border-radius: 2px;
    border: 1px dashed rgba(100, 210, 255, 0.18);
    opacity: 0;
    transition: opacity 0.15s;
}
.chat-repeat-btn:hover {
    background: rgba(100, 210, 255, 0.12);
    border-color: rgba(100, 210, 255, 0.5);
}
.chat-repeat-btn:hover::before { opacity: 1; }


.chat-order-timer-bar {
    flex-shrink: 0;
    padding: 0.55rem 1.25rem;
    background: linear-gradient(160deg, rgb(16,11,20) 0%, rgb(7,6,11) 100%);
    border-bottom: 1px solid rgba(110,110,210,0.18);
}
.chat-order-timer-bar__inner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    color: rgba(255,255,255,0.55);
}
.chat-order-timer-bar__label {
    font-size: 0.8rem;
    letter-spacing: 0.02em;
}
.chat-order-timer-bar__value {
    font-size: 1.05rem;
    font-weight: 700;
    font-family: 'Courier New', Courier, monospace;
    color: rgba(255,255,255,0.82);
    letter-spacing: 0.08em;
    min-width: 7ch;
    text-align: left;
}
.timer-pop-enter-active, .timer-pop-leave-active { transition: opacity 0.2s, max-height 0.2s; }
.timer-pop-enter-from, .timer-pop-leave-to { opacity: 0; }

/* ── Cancelled bar ──────────────────────────────────────── */
.chat-order-cancelled-bar {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.55rem 1.1rem;
    background: rgba(180,30,30,0.07);
    border-top: 1px dashed rgba(255,100,100,0.25);
    border-bottom: 1px dashed rgba(255,100,100,0.25);
    margin: 0 0 0.4rem;
    flex-shrink: 0;
    font-family: 'Courier New', Courier, monospace;
}
.chat-order-cancelled-bar__label {
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255,120,120,0.8);
    letter-spacing: 0.12em;
}
.chat-order-cancelled-bar__who {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.45);
    letter-spacing: 0.04em;
}
.chat-order-cancelled-bar__reason {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.3);
    font-style: italic;
}

/* ── Completed bar ──────────────────────────────────────── */
.chat-order-completed-bar {
    display: flex;
    align-items: center;
    padding: 0.55rem 1.1rem;
    background: rgba(80,240,160,0.05);
    border-top: 1px dashed rgba(80,240,160,0.25);
    border-bottom: 1px dashed rgba(80,240,160,0.25);
    margin: 0 0 0.4rem;
    flex-shrink: 0;
    font-family: 'Courier New', Courier, monospace;
}
.chat-order-completed-bar__label {
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(80,240,160,0.75);
    letter-spacing: 0.12em;
}
.chat-order-completed-bar__time {
    margin-left: auto;
    font-size: 0.78rem;
    color: rgba(80,240,160,0.7);
    letter-spacing: 0.06em;
}

/* ── Disputed bar ───────────────────────────────────────── */
.chat-order-disputed-bar {
    display: flex;
    align-items: center;
    padding: 0.55rem 1.1rem;
    background: rgba(180,30,30,0.07);
    border-top: 1px dashed rgba(255,100,100,0.25);
    border-bottom: 1px dashed rgba(255,100,100,0.25);
    margin: 0 0 0.4rem;
    flex-shrink: 0;
    font-family: 'Courier New', Courier, monospace;
}
.chat-order-disputed-bar__label {
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255,120,120,0.8);
    letter-spacing: 0.12em;
}

/* ── Service offer bubble ───────────────────────────────── */
.svc-offer-bubble {
    min-width: 180px;
    width: fit-content;
    max-width: 100%;
    background: linear-gradient(135deg, rgba(80,60,160,0.22), rgba(60,40,130,0.16));
    border: 1px solid rgba(110,110,210,0.3);
    border-radius: 10px;
    border-bottom-left-radius: 2px;
    overflow: hidden;
    font-family: inherit;
    display: flex;
    flex-direction: column;
}
.svc-offer-bubble .chat-msg__meta {
    padding: 0.1rem 0.7rem 0.3rem;
}
.chat-msg--mine .svc-offer-bubble {
    border-bottom-left-radius: 10px;
    border-bottom-right-radius: 2px;
}
.svc-offer__header {
    padding: 0.35rem 0.7rem 0.25rem;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(155,110,232,0.75);
    border-bottom: 1px solid rgba(110,110,210,0.13);
}
.svc-offer__card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.35rem 0.7rem;
    border-bottom: 1px solid rgba(110,110,210,0.08);
}
.svc-offer__card:last-of-type { border-bottom: none; }
.svc-offer__svc {
    font-size: 1.05rem;
    color: rgba(255,255,255,0.88);
    line-height: 1.3;
    flex: 1;
    min-width: 0;
    white-space: normal;
    overflow: visible;
    text-overflow: unset;
    word-break: break-word;
}
.svc-offer__cart-btn {
    flex-shrink: 0;
    width: 2.2rem;
    height: 2.2rem;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(110,110,210,0.18);
    border: 1px solid rgba(110,110,210,0.4);
    border-radius: 6px;
    color: rgba(160,150,255,0.9);
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    line-height: 0;
}
.svc-offer__cart-btn svg {
    display: block;
}
.svc-offer__cart-btn:hover { background: rgba(110,110,210,0.32); }

/* ── Cancelled by in sidebar ────────────────────────────── */
.chat-order-cancelled-by {
    font-size: 0.68rem;
    color: rgba(255,255,255,0.35);
    margin-left: 0.35rem;
}

/* ── System messages — receipt cards ────────────────────── */
.chat-system-msg {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0.6rem 0;
}
.chat-system-msg::before,
.chat-system-msg::after {
    content: '';
    flex: 1;
    height: 0;
    border-top: 1px dashed rgba(100,210,255,0.28);
}
.chat-system-msg:has(.sc-card--accept)::before,
.chat-system-msg:has(.sc-card--accept)::after {
    border-color: rgba(60,200,110,0.32);
}
.chat-system-msg:has(.sc-card--cancel)::before,
.chat-system-msg:has(.sc-card--cancel)::after {
    border-color: rgba(200,50,50,0.32);
}
.chat-system-msg:has(.sc-card--update)::before,
.chat-system-msg:has(.sc-card--update)::after {
    border-color: rgba(60,180,255,0.32);
}
.chat-system-msg:has(.sc-card--paid)::before,
.chat-system-msg:has(.sc-card--paid)::after {
    border-color: rgba(100,210,255,0.32);
}
.chat-system-msg:has(.sc-card--confirm)::before,
.chat-system-msg:has(.sc-card--confirm)::after {
    border-color: rgba(60,200,110,0.32);
}

/* base card */
.sc-card {
    font-family: 'Courier New', Courier, monospace;
    background: rgba(100,210,255,0.04);
    border: 1px dashed rgba(100,210,255,0.2);
    border-radius: 4px;
    padding: 0.65rem 1.1rem;
    width: min(620px, 90vw);
    max-width: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}
.sc-card--accept {
    background: rgba(60,200,110,0.04);
    border-color: rgba(60,200,110,0.25);
    text-align: center;
}
.sc-card--cancel {
    background: rgba(200,50,50,0.05);
    border-color: rgba(200,50,50,0.25);
}
.sc-card--update {
    background: rgba(60,180,255,0.04);
    border-color: rgba(60,180,255,0.22);
}
.sc-card--paid {
    background: rgba(100,210,255,0.04);
    border-color: rgba(100,210,255,0.22);
    text-align: center;
}
.sc-card--confirm {
    background: rgba(60,200,110,0.04);
    border-color: rgba(60,200,110,0.22);
    text-align: center;
}

/* double rule */
.sc-rule {
    width: 100%;
    height: 0;
    border: none;
    border-top: 1px solid rgba(100,210,255,0.12);
    margin: 0.35rem 0;
}
.sc-rule--green { border-color: rgba(60,200,110,0.15); }
.sc-rule--red   { border-color: rgba(200,50,50,0.15); }
.sc-rule--cyan  { border-color: rgba(60,180,255,0.15); }
.sc-rule--gold  { border-color: rgba(255,210,80,0.15); }

/* title */
.sc-title {
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    color: rgba(100,210,255,0.85);
    margin: 0.2rem 0;
    text-align: center;
}
.sc-title--accept  { color: rgba(80,230,130,0.9); }
.sc-title--cancel  { color: rgba(255,110,110,0.85); }
.sc-title--update  { color: rgba(60,180,255,0.9); }
.sc-title--paid    { color: rgba(100,210,255,0.9); }
.sc-title--confirm { color: rgba(80,230,130,0.9); }

/* who (idol name / canceller) */
.sc-who {
    font-size: 0.78rem;
    color: rgba(210,240,255,0.45);
    letter-spacing: 0.06em;
    text-align: center;
    margin: 0.1rem 0;
}
.sc-who--cancel { color: rgba(255,200,200,0.45); }

/* cancel reason */
.sc-reason {
    font-size: 0.78rem;
    color: rgba(255,255,255,0.3);
    font-style: italic;
    margin: 0.15rem 0 0;
    letter-spacing: 0.02em;
}
.sc-date {
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.35);
    letter-spacing: 0.1em;
    text-align: center;
    margin: 0.1rem 0 0;
}
.sc-date--cancel { color: rgba(255, 255, 255, 0.35); }
.sc-date--paid   { color: rgba(255, 255, 255, 0.35); }

.chat-event-label__time {
    opacity: 0.5;
    font-size: 0.75em;
    margin-left: 0.4em;
}

/* line items with dot leaders */
.sc-line {
    display: flex;
    align-items: baseline;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(100,210,255,0.06);
}
.sc-lines .sc-line:last-child { border-bottom: none; }
.sc-line__name {
    font-size: 0.8rem;
    color: rgba(210,240,255,0.72);
    white-space: normal;
    overflow: visible;
    max-width: 55%;
    flex-shrink: 0;
}
.sc-line__dots {
    flex: 1;
    border-bottom: 1px dotted rgba(100,210,255,0.28);
    margin: 0 0.4rem;
    position: relative;
    top: -3px;
    min-width: 0.5rem;
}
.sc-line__price {
    font-size: 0.8rem;
    font-weight: 700;
    color: rgba(100,210,255,0.88);
    white-space: nowrap;
    flex-shrink: 0;
}

/* perforation */
.sc-perf {
    display: flex;
    align-items: center;
    margin: 0.4rem -1.1rem;
    position: relative;
}
.sc-perf::before,
.sc-perf::after {
    content: '';
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #0b0b18;
    border: 1px solid rgba(100,210,255,0.12);
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
}
.sc-perf::before { left: -5px; }
.sc-perf::after  { right: -5px; }
.sc-perf__line {
    flex: 1;
    display: block;
    border-top: 1px dashed rgba(100,210,255,0.3);
    margin: 0 8px;
}

/* total */
.sc-total {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding: 0.15rem 0;
}
.sc-total__label {
    font-size: 0.72rem;
    letter-spacing: 0.2em;
    color: rgba(210,240,255,0.38);
}
.sc-total__value {
    font-size: 1rem;
    font-weight: 700;
    color: rgba(100,210,255,0.97);
    font-variant-numeric: tabular-nums;
}

/* quantity badge */
.sc-line__qty {
    font-size: 0.72rem;
    color: rgba(100, 210, 255, 0.5);
    margin-right: 0.4rem;
    flex-shrink: 0;
    white-space: nowrap;
}

.chat-system-card__who { font-size: 0.8rem; color: rgba(255,255,255,0.45); margin: 0.2rem 0 0; font-weight: 600; }
.chat-system-card__reason { font-size: 0.85rem; color: rgba(255,255,255,0.5); margin: 0.25rem 0 0; font-style: italic; }

/* ── Support chat ─────────────────────────────────────── */
.chat-support-icon {
    font-size: 1rem;
    color: rgba(155, 110, 232, 0.85);
    line-height: 1;
}

.chat-event-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.25);
    letter-spacing: 0.06em;
    font-family: 'Courier New', monospace;
    text-align: center;
    padding: 0.2rem 0;
}

.chat-event-label--review {
    color: rgba(255, 140, 175, 0.6);
}

.chat-closed-banner {
    padding: 0.5rem 1rem;
    background: rgba(100, 60, 180, 0.12);
    border-top: 1px solid rgba(120, 80, 200, 0.2);
    font-size: 0.78rem;
    color: rgba(190, 150, 255, 0.65);
    letter-spacing: 0.04em;
    text-align: center;
    font-family: 'Courier New', monospace;
}

/* ── Attach button ────────────────────────────────────── */
.chat-attach-btn {
    position: absolute;
    right: 0.5rem;
    bottom: 2.35rem;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    background: transparent;
    border: none;
    border-radius: 4px;
    color: rgba(255, 255, 255, 0.28);
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
    z-index: 1;
}
.chat-attach-btn:hover:not(:disabled) {
    color: rgba(190, 145, 255, 0.8);
    background: rgba(110, 110, 210, 0.12);
}
.chat-attach-btn:disabled {
    opacity: 0.25;
    cursor: not-allowed;
}

/* ── Image messages ───────────────────────────────────── */
.chat-msg__bubble--image {
    padding: 0.3rem;
    background: transparent !important;
    border-color: rgba(110, 110, 210, 0.2) !important;
}
.chat-msg__image {
    display: block;
    max-width: 240px;
    max-height: 300px;
    border-radius: 6px;
    object-fit: cover;
    cursor: pointer;
}
</style>
