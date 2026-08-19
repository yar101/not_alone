<script setup>
import {
    ref,
    watch,
    onMounted,
    onUnmounted,
    computed,
    nextTick,
    inject,
} from "vue";
import { usePage, router, Link } from "@inertiajs/vue3";
import UserAvatar from "@/Components/UserAvatar.vue";
import SiteModal from "@/Components/Site/SiteModal.vue";
import axios from "axios";
import { Check, Lock } from "@element-plus/icons-vue";
import ServiceOfferModal from "@/Components/Chat/ServiceOfferModal.vue";
import ReviewForm from "@/Components/Chat/ReviewForm.vue";
import RepeatOrderModal from "@/Components/Chat/RepeatOrderModal.vue";
import { useTranslations } from "@/composables/useTranslations";
import { useModalHistory } from "@/composables/useModalHistory";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue"]);

const page = usePage();
const { __, transChoice, locale } = useTranslations();
const authUser = computed(() => page.props.auth.user);

const isOpen = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

const conversations = ref([]);
const activeConversation = ref(null);
const messages = ref([]);
const newMessage = ref("");
const isTyping = ref(false);
const typingTimer = ref(null);
const loadingConvs = ref(false);
const loadingMoreConvs = ref(false);
const convsHasMore = ref(false);
const convsCursor = ref(null);
const loadingMsgs = ref(false);
const coverMessages = ref(false);
const sending = ref(false);
const uploading = ref(false);
const fileInput = ref(null);
const messagesEnd = ref(null);
const messagesContainer = ref(null);

// New state
const otherLastReadAt = ref(null);
const hasMore = ref(false);
const loadingMore = ref(false);
const searchQuery = ref("");

// Online status — from global presence channel in AppLayout
const onlineUserIds = inject("onlineUserIds", ref([]));
const injectAddToCart = inject("addToCart", null);
const showOfferModal = ref(false);
const repeatOrderOpen = ref(false);
const confirmOfferModal = ref(false);
const confirmOfferMode = ref("create"); // 'create' | 'add'
const confirmOfferServices = ref([]); // array of { id, name, price, time_unit }
const confirmOfferLoading = ref(false);

// ── Orders tab ────────────────────────────────────────────
const activeTab = ref("messages"); // 'messages' | 'orders'
const orders = ref([]);
const loadingOrders = ref(false);
const loadingMoreOrders = ref(false);
const ordersHasMore = ref(false);
const ordersCursor = ref(null); // id последнего загруженного заказа
const activeOrderData = ref(null); // order data for the current open conversation
const hasReview = ref(false); // whether the current user has left a review for the order
const ordersSubTab = ref("mine"); // 'mine' | 'incoming' — only used when authUser is idol

const orderStatusFilter = ref("all"); // 'all' | 'pending' | 'accepted' | 'cancelled'
const orderSearch = ref("");
const orderFiltersOpen = ref(false);
const orderCounts = ref({
    all: 0,
    pending: 0,
    accepted: 0,
    paid: 0,
    completed: 0,
    cancelled: 0,
    refunded: 0,
    disputed: 0,
});
const convUnreadOnly = ref(false);
const orderUnreadOnly = ref(false);

const isMobile = ref(false);
// Separate mobile nav state from data state to prevent flash
// 'list' = show sidebar, 'detail' = show conversation
const mobileView = ref('list');
let mobileViewTimer = null;
const mobileOpening = ref(false);

function setMobileView(view) {
    if (mobileViewTimer) clearTimeout(mobileViewTimer);
    mobileView.value = view;
}
function checkMobile() {
    isMobile.value = window.innerWidth < 768;
}

const subtabOrders = computed(() => orders.value);

const pendingOrderUnread = ref(false);

// Instant dots from server-shared props — no fetch required
const messagesHaveUnread = computed(
    () => page.props.has_unread_direct,
);
const ordersHaveUnread = computed(
    () =>
        pendingOrderUnread.value ||
        page.props.has_unread_orders ||
        orders.value.some((o) => o.unread),
);
const mineHaveUnread = computed(
    () =>
        page.props.has_unread_mine ||
        orders.value
            .filter((o) => o.is_customer)
            .some((o) => o.unread),
);
const incomingHaveUnread = computed(
    () =>
        page.props.has_unread_incoming ||
        orders.value
            .filter((o) => !o.is_customer)
            .some((o) => o.unread),
);

const orderStatusLabels = computed(() => ({
    pending: __("order.status.pending"),
    accepted: __("order.status.accepted"),
    paid: __("order.status.paid"),
    completed: __("order.status.completed"),
    cancelled: __("order.status.cancelled"),
    refunded: __("order.status.refunded"),
    disputed: __("order.status.disputed"),
}));

const visibleOrders = computed(() => subtabOrders.value);

// ── Pay / complete / timer ────────────────────────────────
const orderTimerLabel = computed(() => {
    if (activeOrderData.value?.status !== "paid") return "";
    const paidAt = activeOrderData.value?.paid_at;
    if (!paidAt) return "— : — : —";

    let deadline;
    if (activeOrderData.value.auto_complete_at) {
        deadline = new Date(activeOrderData.value.auto_complete_at).getTime();
    } else {
        // Fallback if field missing (should not happen with new API)
        deadline = new Date(paidAt).getTime() + 72 * 3600 * 1000;
    }

    const diff = Math.max(0, deadline - nowTick.value);
    if (diff === 0) {
        // Show "Completing..." only for first 10 seconds after deadline
        const secondsPast = Math.floor((nowTick.value - deadline) / 1000);
        return secondsPast < 10 ? __("chat.completing") : "00:00:00";
    }
    const totalSec = Math.floor(diff / 1000);
    const days = Math.floor(totalSec / 86400);
    const hours = Math.floor((totalSec % 86400) / 3600);
    const mins = Math.floor((totalSec % 3600) / 60);
    const secs = totalSec % 60;
    if (days > 0)
        return `${days} ${__("chat.days")} ${String(hours).padStart(2, "0")}:${String(mins).padStart(2, "0")}:${String(secs).padStart(2, "0")}`;
    return `${String(hours).padStart(2, "0")}:${String(mins).padStart(2, "0")}:${String(secs).padStart(2, "0")}`;
});

const isDetailOpen = computed({
    get: () => (activeConversation.value !== null || activeOrderData.value !== null) && isMobile.value,
    set: (v) => {
        if (!v) backToList();
    },
});

const modalHistory = useModalHistory(isOpen, "chat");
const detailHistory = useModalHistory(isDetailOpen, "chat-detail");

const myConfirmation = computed(() => {
    if (!activeOrderData.value) return false;
    return activeOrderData.value.is_customer
        ? activeOrderData.value.completion_confirmed_by_customer
        : activeOrderData.value.completion_confirmed_by_idol;
});

async function payOrder() {
    if (
        !activeOrderData.value ||
        activeOrderData.value.status !== "accepted" ||
        orderAction.value
    )
        return;
    orderAction.value = "pay";
    try {
        await axios.patch(route("orders.pay", activeOrderData.value.id));
    } finally {
        orderAction.value = "";
    }
}

async function confirmCompletion() {
    if (
        !activeOrderData.value ||
        activeOrderData.value.status !== "paid" ||
        orderAction.value
    )
        return;
    orderAction.value = "complete";
    try {
        await axios.patch(
            route("orders.confirm-completion", activeOrderData.value.id),
        );
        completeModal.value = false;
    } finally {
        orderAction.value = "";
    }
}

// ── Order action loading ('accept' | 'pay' | 'complete' | '')
const orderAction = ref("");

// ── Accept order confirm modal ────────────────────────────
const acceptModal = ref(false);

// ── Complete order confirm modal ──────────────────────────
const completeModal = ref(false);

// ── Cancel order modal ────────────────────────────────────
const cancelModal = ref(false);
const cancelReason = ref("");
const cancelSubmitting = ref(false);
const cancelTemplatesCustomer = computed(() => [
    __("order.cancel.customer.1"),
    __("order.cancel.customer.2"),
    __("order.cancel.customer.3"),
    __("order.cancel.customer.4"),
    __("order.cancel.customer.5"),
    __("order.cancel.customer.6"),
]);

const cancelTemplatesIdol = computed(() => [
    __("order.cancel.idol.1"),
    __("order.cancel.idol.2"),
    __("order.cancel.idol.3"),
    __("order.cancel.idol.4"),
    __("order.cancel.idol.5"),
    __("order.cancel.idol.6"),
]);

const cancelTemplates = computed(() =>
    activeOrderData.value?.is_customer
        ? cancelTemplatesCustomer.value
        : cancelTemplatesIdol.value,
);

// ── Avatar fullscreen ─────────────────────────────────────
const avatarFullscreen = ref(false);

// ── Block state ───────────────────────────────────────────
const activeBlock = ref(null);
const blockModal = ref(false);
const blockReason = ref("");
const blockDuration = ref(null);
const blockDurationChosen = ref(false);
const blockSubmitting = ref(false);

const blockReasons = computed(() => page.props.chat_block_reasons ?? []);

const blockDurations = computed(() => [
    { label: __("chat.block.dur.1h"), minutes: 60 },
    { label: __("chat.block.dur.24h"), minutes: 1440 },
    { label: __("chat.block.dur.7d"), minutes: 10080 },
    { label: __("chat.block.dur.30d"), minutes: 43200 },
    { label: __("chat.block.dur.forever"), minutes: null },
]);

const nowTick = ref(Date.now());
let nowTimer = null;
onMounted(() => {
    nowTimer = setInterval(() => {
        nowTick.value = Date.now();
    }, 1000);
    subscribeUserEcho();
    checkMobile();
    window.addEventListener("resize", checkMobile);
});

const blockedUntilLabel = computed(() => {
    if (!activeBlock.value) return "";
    if (!activeBlock.value.blocked_until) return __("chat.block.dur.forever");
    const diff = Math.max(
        0,
        new Date(activeBlock.value.blocked_until).getTime() - nowTick.value,
    );
    if (diff === 0) return __("chat.block.expired");
    const totalSec = Math.floor(diff / 1000);
    const days = Math.floor(totalSec / 86400);
    const hours = Math.floor((totalSec % 86400) / 3600);
    const mins = Math.floor((totalSec % 3600) / 60);
    const secs = totalSec % 60;
    if (days > 0)
        return `${days} ${__("chat.block.time.d")} ${hours} ${__("chat.block.time.h")}`;
    if (hours > 0)
        return `${hours} ${__("chat.block.time.h")} ${mins} ${__("chat.block.time.m")}`;
    if (mins > 0)
        return `${mins} ${__("chat.block.time.m")} ${secs} ${__("chat.block.time.s")}`;
    return `${secs} ${__("chat.block.time.s")}`;
});

let echoChannel = null;
let ordersEchoChannel = null;
let userEchoChannel = null;

const isAnyModalOpen = computed(() => {
    return (
        blockModal.value ||
        cancelModal.value ||
        confirmOfferModal.value ||
        acceptModal.value ||
        completeModal.value ||
        showOfferModal.value ||
        repeatOrderOpen.value
    );
});

// Watch for modal changes
watch(isAnyModalOpen, (newVal, oldVal) => {
    if (!newVal && oldVal) {
        // Modal closed manually
    }
});

// ── Close panel ──────────────────────────────────────────
function close() {
    isOpen.value = false;
}

// ── Fetch conversation list ──────────────────────────────
async function fetchConversations(reset = true) {
    if (reset) {
        loadingConvs.value = true;
        conversations.value = [];
        convsCursor.value = null;
        convsHasMore.value = false;
    } else {
        if (loadingMoreConvs.value || !convsHasMore.value) return;
        loadingMoreConvs.value = true;
    }
    try {
        const params = {};
        if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
        if (convUnreadOnly.value) params.unread = 1;
        if (convsCursor.value) {
            params.cursor_at = convsCursor.value.at;
            params.cursor_id = convsCursor.value.id;
        }
        const res = await axios.get(route("conversations.index"), { params });
        const fresh = res.data.conversations ?? [];
        conversations.value = reset
            ? fresh
            : [...conversations.value, ...fresh];
        convsHasMore.value = res.data.has_more ?? false;
        if (fresh.length > 0) {
            const last = fresh[fresh.length - 1];
            convsCursor.value = { at: last.updated_at, id: last.id };
        }
    } finally {
        loadingConvs.value = false;
        loadingMoreConvs.value = false;
    }
}

// ── Filtered conversations (server handles search) ───────
const filteredConversations = computed(() => conversations.value);

// ── Open a conversation ──────────────────────────────────
async function openConversation(conv) {
    if (activeConversation.value?.id === conv.id && conv.id !== "draft") return;
    leaveEcho();

    if (isMobile.value) {
        // On mobile: first slide the panel in (empty/clean), then load
        setMobileView('detail');
        mobileOpening.value = true;
        // Wait for slide animation to complete before showing any content
        await new Promise(r => setTimeout(r, 300));
        mobileOpening.value = false;
    }

    loadingMsgs.value = true;
    activeConversation.value = conv;
    messages.value = [];
    newMessage.value = "";
    isTyping.value = false;
    otherLastReadAt.value = null;
    hasMore.value = false;

    if (conv.is_draft) {
        loadingMsgs.value = false;
        return;
    }

    try {
        const res = await axios.get(route("conversations.show", conv.id));
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
            closed_at: res.data.closed_at ?? null,
        };
        const local = conversations.value.find((c) => c.id === conv.id);
        if (local) local.unread = false;
        const order = orders.value.find((o) => o.conversation_id === conv.id);
        if (order) order.unread = false;
        router.reload({
            only: [
                "has_unread_messages",
                "has_unread_direct",
                "has_unread_orders",
                "has_unread_mine",
                "has_unread_incoming",
            ],
        });
    } finally {
        loadingMsgs.value = false;
    }

    if (!conv.is_draft) subscribeEcho(conv.id);
    await nextTick();
    messagesEnd.value?.scrollIntoView({ behavior: "instant" });
}

// ── Start conversation with user (called from outside) ───
async function startWith(userId) {
    isOpen.value = true;
    try {
        const res = await axios.get(route("conversations.check", userId));
        const { conversation_id, user, block } = res.data;

        if (conversation_id) {
            // Already exists, just open
            let conv = conversations.value.find((c) => c.id === conversation_id);
            if (!conv) {
                await fetchConversations();
                conv = conversations.value.find((c) => c.id === conversation_id);
            }
            await openConversation(
                conv ?? {
                    id: conversation_id,
                    other_user: user,
                    unread: false,
                },
            );
        } else {
            // Open as draft
            activeBlock.value = block ?? null;
            await openConversation({
                id: "draft",
                other_user: user,
                unread: false,
                is_draft: true,
            });
        }
    } catch (e) {
        console.error("Failed to check conversation", e);
    }
}

async function startConversation(convId) {
    isOpen.value = true;
    activeTab.value = "messages";
    let conv = conversations.value.find((c) => c.id === convId);
    if (!conv) {
        await fetchConversations();
        conv = conversations.value.find((c) => c.id === convId);
    }
    await openConversation(
        conv ?? { id: convId, other_user: null, unread: false },
    );
}

// ── Send message ─────────────────────────────────────────
async function ensureRealConversation() {
    if (!activeConversation.value?.is_draft) return true;

    try {
        const res = await axios.post(route("conversations.store"), {
            target_user_id: activeConversation.value.other_user.id,
        });
        const realId = res.data.conversation_id;

        // Update active conversation state
        activeConversation.value.id = realId;
        activeConversation.value.is_draft = false;

        // Subscribe to Echo for the real ID
        subscribeEcho(realId);

        // Refresh the list so it appears in the sidebar
        fetchConversations();

        return true;
    } catch (e) {
        console.error("Failed to create conversation", e);
        return false;
    }
}

async function sendMessage() {
    const body = newMessage.value.trim();
    if (!body || sending.value || !activeConversation.value) return;

    sending.value = true;

    if (!(await ensureRealConversation())) {
        sending.value = false;
        return;
    }

    const convId = activeConversation.value.id;
    newMessage.value = "";
    try {
        const res = await axios.post(route("conversations.message", convId), {
            body,
        });
        messages.value.push(res.data);
        scrollToBottom();
        updateLastMessage(convId, res.data);
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
    echoChannel.whisper("typing", { user_id: authUser.value?.id });
}

// ── Echo subscription ────────────────────────────────────
function subscribeEcho(conversationId) {
    if (!window.Echo || !authUser.value) return;
    echoChannel = window.Echo.private(`conversation.${conversationId}`)
        .listen(".message.sent", (data) => {
            if (
                data.sender_id !== authUser.value.id ||
                data.type === "system"
            ) {
                messages.value.push(data);
                scrollToBottom();
                markRead(conversationId);
                updateLastMessage(conversationId, data);
                if (data.type !== "system") playNotificationSound();
            }
            if (
                data.type === "system" &&
                activeConversation.value?.id === conversationId
            ) {
                if (data.metadata?.event === "chat_closed") {
                    activeConversation.value = {
                        ...activeConversation.value,
                        closed_at: data.created_at,
                    };
                } else if (data.metadata?.event === "chat_opened") {
                    activeConversation.value = {
                        ...activeConversation.value,
                        closed_at: null,
                    };
                }
            }
        })
        .listen(".message.read", (data) => {
            if (data.reader_id !== authUser.value.id) {
                otherLastReadAt.value = data.read_at;
            }
        })
        .listen(".order.status-changed", (data) => {
            if (
                activeOrderData.value &&
                activeOrderData.value.id === data.order_id
            ) {
                activeOrderData.value = {
                    ...activeOrderData.value,
                    status: data.status,
                    cancelled_by:
                        data.cancelled_by ?? activeOrderData.value.cancelled_by,
                    cancelled_by_name:
                        data.cancelled_by_name ??
                        activeOrderData.value.cancelled_by_name,
                    cancel_reason:
                        data.cancel_reason ??
                        activeOrderData.value.cancel_reason,
                    paid_at: data.paid_at ?? activeOrderData.value.paid_at,
                    auto_complete_at:
                        data.auto_complete_at ??
                        activeOrderData.value.auto_complete_at,
                    completed_at:
                        data.completed_at ?? activeOrderData.value.completed_at,
                    completion_confirmed_by_idol:
                        data.completion_confirmed_by_idol ??
                        activeOrderData.value.completion_confirmed_by_idol,
                    completion_confirmed_by_customer:
                        data.completion_confirmed_by_customer ??
                        activeOrderData.value.completion_confirmed_by_customer,
                };
            }
            const idx = orders.value.findIndex((o) => o.id === data.order_id);
            if (idx !== -1) {
                orders.value[idx] = {
                    ...orders.value[idx],
                    status: data.status,
                    cancelled_by:
                        data.cancelled_by ?? orders.value[idx].cancelled_by,
                    cancelled_by_name:
                        data.cancelled_by_name ??
                        orders.value[idx].cancelled_by_name,
                    cancel_reason:
                        data.cancel_reason ?? orders.value[idx].cancel_reason,
                };
            }
        })
        .listenForWhisper("typing", () => {
            isTyping.value = true;
            clearTimeout(typingTimer.value);
            typingTimer.value = setTimeout(() => {
                isTyping.value = false;
            }, 2000);
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
    ordersEchoChannel = window.Echo.private(
        `orders.${authUser.value.id}`,
    ).listen(".order.changed", ({ change_type, order }) => {
        const idx = orders.value.findIndex((o) => o.id === order.id);
        if (idx !== -1) {
            orders.value.splice(idx, 1, {
                ...order,
                unread: orders.value[idx].unread,
            });
        } else {
            orders.value.unshift({ ...order, unread: false });
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
    userEchoChannel = window.Echo.private(
        `App.Models.User.${authUser.value.id}`,
    ).listen(".message.received", handleIncomingMessageForList);
}

function leaveUserEcho() {
    if (userEchoChannel) {
        userEchoChannel.stopListening(
            ".message.received",
            handleIncomingMessageForList,
        );
    }
    userEchoChannel = null;
}

function handleIncomingMessageForList(data) {
    const { conversation_id, order_id, last_message } = data;

    // Не трогать активный диалог — он уже обрабатывается через markRead
    if (activeConversation.value?.id === conversation_id) return;

    if (order_id) {
        pendingOrderUnread.value = true;
        const order = orders.value.find(
            (o) => o.conversation_id === conversation_id,
        );
        if (order) {
            order.unread = true;
        }
    } else {
        const conv = conversations.value.find((c) => c.id === conversation_id);
        if (conv) {
            conv.last_message = last_message;
            conv.updated_at = last_message?.created_at;
            conv.unread = true;
        }
    }
}

async function markRead(conversationId) {
    await axios.get(route("conversations.show", conversationId));
    const local = conversations.value.find((c) => c.id === conversationId);
    if (local) local.unread = false;
    const order = orders.value.find(
        (o) => o.conversation_id === conversationId,
    );
    if (order) order.unread = false;
    router.reload({ only: ["has_unread_messages"] });
}

function updateLastMessage(convId, msg) {
    const conv = conversations.value.find((c) => c.id === convId);
    if (conv) {
        conv.last_message = msg;
        conv.updated_at = msg.created_at;
    }
}

// ── Scroll helpers ────────────────────────────────────────
function scrollToBottom(instant = false) {
    setTimeout(() => {
        messagesEnd.value?.scrollIntoView({
            behavior: instant ? "instant" : "smooth",
        });
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
        const res = await axios.get(
            route("conversations.show", activeConversation.value.id),
            {
                params: { before_id: firstId },
            },
        );
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
        osc.type = "sine";
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
            const loc = locale.value?.current === "ru" ? "ru-RU" : "en-US";
            if (dateKey === today.toDateString()) {
                label = __("chat.date.today");
            } else if (dateKey === yesterday.toDateString()) {
                label = __("chat.date.yesterday");
            } else {
                label = msgDate.toLocaleDateString(loc, {
                    day: "numeric",
                    month: "long",
                });
            }
            result.push({ type: "divider", label, key: "divider-" + dateKey });
            prevDate = dateKey;
            prevSenderId = null;
        }

        const nextMsg = messages.value[i + 1];
        const nextSenderId = nextMsg?.sender_id;
        const nextDateKey = nextMsg
            ? new Date(nextMsg.created_at).toDateString()
            : null;

        const isFirstInGroup = msg.sender_id !== prevSenderId;
        const isLastInGroup =
            msg.sender_id !== nextSenderId || nextDateKey !== dateKey;

        result.push({
            type: "message",
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
const isSupport = computed(() => !!activeConversation.value?.is_support);
const isChatClosed = computed(
    () => isSupport.value && !!activeConversation.value?.closed_at,
);
const showReviewForm = computed(
    () =>
        activeOrderData.value?.status === "completed" &&
        activeOrderData.value?.is_customer === true &&
        !hasReview.value,
);

function onReviewSubmitted() {
    hasReview.value = true;
    messages.value.push({
        id: Date.now(),
        sender_id: null,
        created_at: new Date().toISOString(),
        type: "system",
        body: null,
        metadata: { event: "review_submitted" },
        read_at: null,
    });
}

async function onRepeatOrderCreated({ conversation_id }) {
    repeatOrderOpen.value = false;
    await fetchConversations();
    const conv = conversations.value.find((c) => c.id === conversation_id);
    await openConversation(
        conv ?? { id: conversation_id, other_user: null, unread: false },
    );
}

function onOfferSent(msg) {
    // Push the message immediately on the idol's side (Echo skips own messages)
    messages.value.push(msg);
    nextTick(scrollToBottom);
    if (activeConversation.value) {
        updateLastMessage(activeConversation.value.id, msg);
    }
}

function handleOfferAction(services) {
    confirmOfferServices.value = services;
    if (activeOrderData.value && activeOrderData.value.status === "pending") {
        confirmOfferMode.value = "add";
        confirmOfferModal.value = true;
    } else {
        confirmOfferMode.value = "create";
        confirmOfferModal.value = true;
    }
}

async function confirmOfferAction() {
    if (!confirmOfferServices.value.length || confirmOfferLoading.value) return;
    confirmOfferLoading.value = true;
    try {
        if (confirmOfferMode.value === "add") {
            await Promise.all(
                confirmOfferServices.value.map((svc) =>
                    axios.post(route("orders.items.add", activeOrderData.value.id), {
                        service_id: svc.id,
                    })
                )
            );
            await openConversation(activeConversation.value);
            activeTab.value = "orders";
        } else {
            const idol = activeConversation.value?.other_user;
            if (!idol) throw new Error("Idol not found");
            const res = await axios.post(route("orders.store"), {
                idol_id: idol.id,
                services: confirmOfferServices.value.map((svc) => ({ id: svc.id, quantity: 1 })),
            });
            await openConversation({ id: res.data.conversation_id, other_user: idol });
            activeTab.value = "orders";
        }
        confirmOfferModal.value = false;
    } catch (e) {
        alert(e.response?.data?.error || e.response?.data?.message || __("chat.add_service.error"));
    } finally {
        confirmOfferLoading.value = false;
    }
}

async function uploadAndSendImage(file) {
    if (!file || uploading.value || !activeConversation.value) return;
    uploading.value = true;

    if (!(await ensureRealConversation())) {
        uploading.value = false;
        return;
    }

    const convId = activeConversation.value.id;
    try {
        const form = new FormData();
        form.append("file", file);
        const up = await axios.post(
            route("conversations.upload", convId),
            form,
            { headers: { "Content-Type": "multipart/form-data" } },
        );
        const res = await axios.post(route("conversations.message", convId), {
            body: "",
            type: "image",
            metadata: { image_url: up.data.url },
        });
        messages.value.push(res.data);
        scrollToBottom();
        updateLastMessage(convId, res.data);
    } finally {
        uploading.value = false;
    }
}

function onFileChange(e) {
    const file = e.target.files?.[0];
    if (file) uploadAndSendImage(file);
    e.target.value = "";
}

// ── Block / unblock ───────────────────────────────────────
async function submitBlock() {
    if (!blockReason.value || blockSubmitting.value) return;
    blockSubmitting.value = true;
    try {
        const res = await axios.post(
            route("conversations.block", activeConversation.value.id),
            { reason: blockReason.value, duration: blockDuration.value },
        );
        activeBlock.value = res.data.block;
        blockModal.value = false;
        blockReason.value = "";
        blockDuration.value = null;
        blockDurationChosen.value = false;
    } finally {
        blockSubmitting.value = false;
    }
}

async function submitUnblock() {
    await axios.delete(
        route("conversations.unblock", activeConversation.value.id),
    );
    activeBlock.value = null;
}

// ── Message read status ───────────────────────────────────
function isMessageRead(msg) {
    if (!otherLastReadAt.value) return false;
    return otherLastReadAt.value >= msg.created_at;
}

// ── Watch panel open ─────────────────────────────────────
watch(isOpen, (val, oldVal) => {
    if (val) {
        fetchConversations();
        fetchOrders();
        subscribeOrdersEcho();
        // Refresh lazy unread counters so dots are visible immediately on open
        router.reload({
            only: [
                "has_unread_messages",
                "has_unread_direct",
                "has_unread_orders",
                "has_unread_mine",
                "has_unread_incoming",
            ],
        });
    }
    if (!val && oldVal) {
        leaveEcho();
        leaveOrdersEcho();
        if (mobileViewTimer) clearTimeout(mobileViewTimer);
        mobileView.value = 'list';
        activeConversation.value = null;
        activeOrderData.value = null;
        searchQuery.value = "";
        convsCursor.value = null;
        convsHasMore.value = false;
        orderSearch.value = "";
        orderStatusFilter.value = "all";
        orderFiltersOpen.value = false;
    }
});

watch(activeConversation, (conv, oldConv) => {
    if (!isMobile.value) return;
});

watch(activeTab, (tab) => {
    if (tab === "orders") {
        fetchOrders(true);
        router.reload({
            only: [
                "has_unread_orders",
                "has_unread_mine",
                "has_unread_incoming",
            ],
        });
    }
});

watch(ordersSubTab, () => {
    orderStatusFilter.value = "all";
    orderSearch.value = "";
    orderFiltersOpen.value = false;
    if (!_skipSubTabFetch) fetchOrders(true);
    _skipSubTabFetch = false;
});

function doOrderSearch() {
    fetchOrders(true);
}
function clearOrderSearch() {
    orderSearch.value = "";
    fetchOrders(true);
}
function doConvSearch() {
    fetchConversations(true);
}
function clearConvSearch() {
    searchQuery.value = "";
    fetchConversations(true);
}

// ── Close on navigation (ignore reloads on same URL) ─────
watch(
    () => page.url,
    (newUrl, oldUrl) => {
        const normalize = (url) =>
            url.split("?")[0].split("#")[0].replace(/\/+$/, "");
        if (normalize(newUrl) !== normalize(oldUrl)) {
            if (modalHistory?.skipHistoryBack) modalHistory.skipHistoryBack();
            if (detailHistory?.skipHistoryBack) detailHistory.skipHistoryBack();
            isOpen.value = false;
        }
    },
);

function setScrollLock(locked) {
    if (locked) {
        window.scrollTo({ top: 0, behavior: "instant" });
        document.documentElement.classList.add("chat-scroll-locked");
    } else {
        document.documentElement.classList.remove("chat-scroll-locked");
    }
}

onUnmounted(() => {
    leaveEcho();
    leaveOrdersEcho();
    leaveUserEcho();
    clearInterval(nowTimer);
    window.removeEventListener("resize", checkMobile);
    setScrollLock(false);
});

watch(
    [isMobile, isOpen],
    ([mobile, open]) => {
        setScrollLock(mobile && open);
    },
    { immediate: true },
);

function backToList() {
    leaveEcho();
    // Switch mobile view immediately so slide OUT plays with content still visible
    if (isMobile.value) {
        setMobileView('list');
        // Clear data only after slide animation completes (280ms)
        if (mobileViewTimer) clearTimeout(mobileViewTimer);
        mobileViewTimer = setTimeout(() => {
            activeConversation.value = null;
            activeOrderData.value = null;
        }, 300);
    } else {
        activeConversation.value = null;
        activeOrderData.value = null;
    }
}

// ── Orders helpers ────────────────────────────────────────
let _skipSubTabFetch = false;

function applyStatusFilter(key) {
    orderStatusFilter.value = key;
    fetchOrders(true);
}

async function fetchOrders(reset = true) {
    if (reset) {
        loadingOrders.value = true;
        orders.value = [];
        ordersCursor.value = null;
        ordersHasMore.value = false;
    } else {
        if (loadingMoreOrders.value || !ordersHasMore.value) return;
        loadingMoreOrders.value = true;
    }
    try {
        const params = {};
        if (ordersCursor.value) params.cursor = ordersCursor.value;
        if (orderStatusFilter.value !== "all")
            params.status = orderStatusFilter.value;
        if (orderSearch.value.trim()) params.search = orderSearch.value.trim();
        if (orderUnreadOnly.value) params.unread = 1;
        if (authUser.value?.is_idol) {
            params.role = ordersSubTab.value === "mine" ? "customer" : "idol";
        } else {
            params.role = "customer";
        }
        const res = await axios.get(route("orders.index"), { params });
        const fresh = res.data.orders ?? [];
        orders.value = reset ? fresh : [...orders.value, ...fresh];
        ordersHasMore.value = res.data.has_more ?? false;
        if (fresh.length > 0) ordersCursor.value = fresh[fresh.length - 1].id;
        if (res.data.counts) orderCounts.value = res.data.counts;
        pendingOrderUnread.value = false;
    } finally {
        loadingOrders.value = false;
        loadingMoreOrders.value = false;
    }
}

async function openOrderConversation(order) {
    if (!order.conversation_id) return;
    const other = order.is_customer ? order.idol : order.customer;
    await openConversation({
        id: order.conversation_id,
        other_user: other,
        unread: false,
    });
}

function orderTotal(order) {
    return order.items.reduce(
        (s, i) => s + (i.service?.price ?? 0) * (i.quantity ?? 1),
        0,
    );
}

function cancelledByLabel(orderData) {
    if (!orderData?.cancelled_by) return null;
    if (orderData.cancelled_by === authUser.value?.id)
        return __("chat.msg.by_you");
    return orderData.cancelled_by_name ?? null;
}

async function acceptOrder() {
    if (
        !activeOrderData.value ||
        activeOrderData.value.status !== "pending" ||
        orderAction.value
    )
        return;
    orderAction.value = "accept";
    try {
        await axios.patch(route("orders.accept", activeOrderData.value.id));
        activeOrderData.value = {
            ...activeOrderData.value,
            status: "accepted",
        };
        orders.value = orders.value.map((o) =>
            o.id === activeOrderData.value.id
                ? { ...o, status: "accepted" }
                : o,
        );
        acceptModal.value = false;
        router.reload({ only: ["order_notifications_unread"] });
    } finally {
        orderAction.value = "";
    }
}

async function submitCancelOrder() {
    if (!cancelReason.value.trim() || cancelSubmitting.value) return;
    cancelSubmitting.value = true;
    try {
        await axios.patch(route("orders.cancel", activeOrderData.value.id), {
            cancel_reason: cancelReason.value,
        });
        const updated = {
            ...activeOrderData.value,
            status: "cancelled",
            cancel_reason: cancelReason.value,
            cancelled_by: authUser.value.id,
            cancelled_by_name: authUser.value.name,
        };
        activeOrderData.value = updated;
        orders.value = orders.value.map((o) =>
            o.id === updated.id
                ? {
                      ...o,
                      status: "cancelled",
                      cancel_reason: updated.cancel_reason,
                      cancelled_by: updated.cancelled_by,
                      cancelled_by_name: updated.cancelled_by_name,
                  }
                : o,
        );
        cancelModal.value = false;
        cancelReason.value = "";
        router.reload({ only: ["order_notifications_unread"] });
    } finally {
        cancelSubmitting.value = false;
    }
}

async function openOrder(orderId) {
    isOpen.value = true;
    activeTab.value = "orders";
    orderStatusFilter.value = "all";

    // Try current tab first
    await fetchOrders(true);
    let order = orders.value.find((o) => o.id == orderId);

    // If not found and we are an idol, try the other tab
    if (!order && authUser.value?.is_idol) {
        const otherTab = ordersSubTab.value === "mine" ? "incoming" : "mine";
        _skipSubTabFetch = true;
        ordersSubTab.value = otherTab;
        await fetchOrders(true);
        order = orders.value.find((o) => o.id == orderId);
    }

    if (order) {
        const targetTab = order.is_customer === false ? "incoming" : "mine";
        if (ordersSubTab.value !== targetTab) {
            _skipSubTabFetch = true;
            ordersSubTab.value = targetTab;
        } else {
            // Ensure any previous poison is cleared
            _skipSubTabFetch = false;
        }
        await openOrderConversation(order);
    } else {
        _skipSubTabFetch = false;
    }
}

function silentClose() {
    isOpen.value = false;
}

defineExpose({ startWith, startConversation, openOrder, silentClose });

// ── Helpers ──────────────────────────────────────────────
function getMsgPreview(msg) {
    if (!msg) return "";
    if (msg.type === "image")
        return `[${__("chat.photo.label") || "фото"}]`;
    if (msg.type === "service_offer") return __("chat.msg.offer_label");
    if (msg.type === "system") {
        const ev = msg.metadata?.event;
        if (ev === "order_created") return __("chat.msg.order_placed");
        if (ev === "order_accepted") return __("chat.msg.order_accepted");
        if (ev === "order_paid") return __("chat.msg.order_paid");
        if (ev === "order_cancelled") return __("chat.msg.order_cancelled");
        if (ev === "item_added") return __("chat.msg.order_updated");
        if (ev === "chat_closed") return __("chat.status.closed");
        if (ev === "chat_opened") return __("chat.status.open");
        if (ev === "review_submitted") return __("chat.status.reviewed");
        return "";
    }
    return msg.body || "";
}

function formatTime(iso) {
    if (!iso) return "";
    const loc = locale.value?.current === "ru" ? "ru-RU" : "en-US";
    return new Date(iso).toLocaleTimeString(loc, {
        hour: "2-digit",
        minute: "2-digit",
    });
}

function formatDate(iso) {
    if (!iso) return "";
    const d = new Date(iso);
    const now = new Date();
    if (d.toDateString() === now.toDateString()) return formatTime(iso);
    const loc = locale.value?.current === "ru" ? "ru-RU" : "en-US";
    return d.toLocaleDateString(loc, { day: "numeric", month: "short" });
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
                <div
                    class="chat-sidebar"
                    :class="{
                        'chat-sidebar--mobile-hidden':
                            isMobile && mobileView === 'detail',
                    }"
                >
                    <div class="chat-sidebar__header">
                        <span class="chat-sidebar__title">{{
                            __("chat.title")
                        }}</span>
                        <button class="chat-icon-btn" @click="close">
                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>

                    <!-- Табы -->
                    <div class="chat-tabs">
                        <button
                            class="chat-tab"
                            :class="{
                                'chat-tab--active': activeTab === 'messages',
                            }"
                            @click="activeTab = 'messages'"
                        >
                            {{ __("chat.messages") }}
                            <span
                                v-if="messagesHaveUnread"
                                class="chat-tab__dot"
                            ></span>
                        </button>
                        <button
                            class="chat-tab"
                            :class="{
                                'chat-tab--active': activeTab === 'orders',
                            }"
                            @click="activeTab = 'orders'"
                        >
                            {{ __("chat.tab.orders") }}
                            <span
                                v-if="ordersHaveUnread"
                                class="chat-tab__dot"
                            ></span>
                        </button>
                    </div>

                    <div class="chat-sidebar__list">
                        <!-- ── Сообщения ── -->
                        <template v-if="activeTab === 'messages'">
                            <div class="chat-sticky-controls">
                                <div class="chat-sidebar__search">
                                    <div class="search-row">
                                        <input
                                            v-model="searchQuery"
                                            type="text"
                                            class="chat-search-input"
                                            :placeholder="__('chat.search')"
                                            @keyup.enter="doConvSearch"
                                        />
                                        <button
                                            v-if="searchQuery.trim()"
                                            class="search-clear-btn"
                                            @click="clearConvSearch"
                                            title="Сбросить"
                                        >
                                            <svg
                                                width="12"
                                                height="12"
                                                viewBox="0 0 12 12"
                                                fill="none"
                                            >
                                                <path
                                                    d="M1 1l10 10M11 1L1 11"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                    stroke-linecap="round"
                                                />
                                            </svg>
                                        </button>
                                        <button
                                            class="search-go-btn"
                                            @click="doConvSearch"
                                            title="Найти"
                                        >
                                            <svg
                                                width="14"
                                                height="14"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2.2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <circle cx="11" cy="11" r="8" />
                                                <line
                                                    x1="21"
                                                    y1="21"
                                                    x2="16.65"
                                                    y2="16.65"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="chat-unread-toggle">
                                    <button
                                        class="chat-unread-btn"
                                        :class="{
                                            'chat-unread-btn--active':
                                                convUnreadOnly,
                                        }"
                                        @click="
                                            convUnreadOnly = !convUnreadOnly;
                                            fetchConversations(true);
                                        "
                                    >
                                        <svg
                                            class="chat-unread-btn__icon"
                                            width="13"
                                            height="13"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <path
                                                d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                                            />
                                            <circle
                                                cx="12"
                                                cy="9.5"
                                                r="1.5"
                                                fill="currentColor"
                                                stroke="none"
                                            />
                                        </svg>
                                        {{ __("chat.filter.unread_only") }}
                                    </button>
                                </div>
                            </div>
                            <template v-if="loadingConvs">
                                <div v-for="n in 6" :key="n" class="conv-skel">
                                    <div
                                        class="conv-skel__avatar skel-pulse"
                                    ></div>
                                    <div class="conv-skel__info">
                                        <div
                                            class="conv-skel__name skel-pulse"
                                            :style="{
                                                width:
                                                    [52, 45, 60, 38, 55, 48][
                                                        n - 1
                                                    ] + '%',
                                            }"
                                        ></div>
                                        <div
                                            class="conv-skel__preview skel-pulse"
                                            :style="{
                                                width:
                                                    [75, 85, 65, 90, 70, 80][
                                                        n - 1
                                                    ] + '%',
                                            }"
                                        ></div>
                                    </div>
                                    <div
                                        class="conv-skel__time skel-pulse"
                                    ></div>
                                </div>
                            </template>
                            <template v-else-if="conversations.length === 0">
                                <div class="chat-no-convs">
                                    <p>
                                        {{
                                            $page.props.is_idol
                                                ? __("chat.empty.dialogs")
                                                : __("chat.empty.dialogs.sub")
                                        }}
                                    </p>
                                    <a
                                        v-if="$page.props.is_idol"
                                        :href="route('users.search')"
                                        >{{ __("chat.find_users") }}</a
                                    >
                                </div>
                            </template>
                            <template v-else>
                                <button
                                    v-for="conv in filteredConversations"
                                    :key="conv.id"
                                    class="chat-conv-item"
                                    :class="{
                                        'chat-conv-item--active':
                                            activeConversation?.id === conv.id,
                                        'chat-conv-item--unread':
                                            conv.unread,
                                    }"
                                    @click="openConversation(conv)"
                                >
                                    <div
                                        class="chat-conv-avatar-wrapper"
                                    >
                                        <template v-if="conv.is_support">
                                            <div class="chat-conv-avatar chat-conv-avatar--support">
                                                <span class="chat-support-icon">✦</span>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <UserAvatar
                                                :user="conv.other_user"
                                                :size="48"
                                            />
                                        </template>
                                    </div>
                                    <div class="chat-conv-info">
                                        <div class="chat-conv-name">
                                            {{
                                                conv.is_support
                                                    ? __("chat.support")
                                                    : (conv.other_user?.name ??
                                                      "—")
                                            }}
                                        </div>
                                        <div class="chat-conv-preview">
                                            {{
                                                getMsgPreview(conv.last_message)
                                            }}
                                        </div>
                                    </div>
                                    <div class="chat-conv-meta">
                                        <span class="chat-conv-time">{{
                                            formatDate(
                                                conv.last_message?.created_at,
                                            )
                                        }}</span>
                                        <span
                                            v-if="conv.unread"
                                            class="chat-conv-badge chat-conv-badge--dot"
                                        ></span>
                                    </div>
                                </button>
                                <!-- Load more conversations -->
                                <div
                                    v-if="convsHasMore || loadingMoreConvs"
                                    class="orders-load-more"
                                >
                                    <button
                                        class="orders-load-more-btn"
                                        :disabled="loadingMoreConvs"
                                        @click="fetchConversations(false)"
                                    >
                                        <span v-if="!loadingMoreConvs">{{
                                            __("notification.load_more")
                                        }}</span>
                                        <span v-else class="orders-load-dots">
                                            <span
                                                class="orders-load-dot"
                                            ></span>
                                            <span
                                                class="orders-load-dot"
                                            ></span>
                                            <span
                                                class="orders-load-dot"
                                            ></span>
                                        </span>
                                    </button>
                                </div>
                            </template>
                        </template>

                        <!-- ── Заказы ── -->
                        <template v-else-if="activeTab === 'orders'">
                            <div class="chat-sticky-controls">
                                <!-- Саб-табы только для айдолов — всегда видны -->
                                <div
                                    v-if="authUser?.is_idol"
                                    class="chat-order-subtabs"
                                >
                                    <button
                                        class="chat-order-subtab"
                                        :class="{
                                            'chat-order-subtab--active':
                                                ordersSubTab === 'mine',
                                        }"
                                        @click="ordersSubTab = 'mine'"
                                    >
                                        {{ __("order.my") }}
                                        <span
                                            v-if="mineHaveUnread"
                                            class="chat-tab__dot"
                                        ></span>
                                    </button>
                                    <button
                                        class="chat-order-subtab"
                                        :class="{
                                            'chat-order-subtab--active':
                                                ordersSubTab === 'incoming',
                                        }"
                                        @click="ordersSubTab = 'incoming'"
                                    >
                                        {{ __("order.incoming") }}
                                        <span
                                            v-if="incomingHaveUnread"
                                            class="chat-tab__dot"
                                        ></span>
                                    </button>
                                </div>

                                <!-- ── Фильтры заказов — всегда видны ──────── -->
                                <div class="order-filters">
                                    <div class="search-row">
                                        <input
                                            v-model="orderSearch"
                                            type="text"
                                            class="chat-search-input"
                                            :placeholder="
                                                __('chat.search.name')
                                            "
                                            @keyup.enter="doOrderSearch"
                                        />
                                        <button
                                            v-if="orderSearch.trim()"
                                            class="search-clear-btn"
                                            @click="clearOrderSearch"
                                            title="Сбросить"
                                        >
                                            <svg
                                                width="12"
                                                height="12"
                                                viewBox="0 0 12 12"
                                                fill="none"
                                            >
                                                <path
                                                    d="M1 1l10 10M11 1L1 11"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                    stroke-linecap="round"
                                                />
                                            </svg>
                                        </button>
                                        <button
                                            class="search-go-btn"
                                            @click="doOrderSearch"
                                            title="Найти"
                                        >
                                            <svg
                                                width="14"
                                                height="14"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2.2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <circle cx="11" cy="11" r="8" />
                                                <line
                                                    x1="21"
                                                    y1="21"
                                                    x2="16.65"
                                                    y2="16.65"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="order-filters__btns-row">
                                        <button
                                            class="order-filters__toggle"
                                            :class="{
                                                'order-filters__toggle--open':
                                                    orderFiltersOpen,
                                            }"
                                            @click="
                                                orderFiltersOpen =
                                                    !orderFiltersOpen
                                            "
                                        >
                                            {{ __("chat.orders.filters") }}
                                            <svg
                                                class="order-filters__arrow"
                                                width="10"
                                                height="10"
                                                viewBox="0 0 10 10"
                                                fill="none"
                                            >
                                                <path
                                                    d="M2 3.5L5 6.5L8 3.5"
                                                    stroke="currentColor"
                                                    stroke-width="1.5"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />
                                            </svg>
                                        </button>
                                        <button
                                            class="chat-unread-btn"
                                            :class="{
                                                'chat-unread-btn--active':
                                                    orderUnreadOnly,
                                            }"
                                            @click="
                                                orderUnreadOnly =
                                                    !orderUnreadOnly;
                                                fetchOrders(true);
                                            "
                                        >
                                            <svg
                                                class="chat-unread-btn__icon"
                                                width="13"
                                                height="13"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <path
                                                    d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                                                />
                                                <circle
                                                    cx="12"
                                                    cy="9.5"
                                                    r="1.5"
                                                    fill="currentColor"
                                                    stroke="none"
                                                />
                                            </svg>
                                            {{ __("chat.filter.unread_only") }}
                                        </button>
                                    </div>
                                    <Transition name="of-expand">
                                        <div
                                            v-if="orderFiltersOpen"
                                            class="order-filters__pills"
                                        >
                                            <button
                                                v-for="pill in [
                                                    {
                                                        key: 'all',
                                                        label: __(
                                                            'chat.orders.filter.all',
                                                        ),
                                                    },
                                                    {
                                                        key: 'pending',
                                                        label: __(
                                                            'chat.orders.filter.pending',
                                                        ),
                                                    },
                                                    {
                                                        key: 'accepted',
                                                        label: __(
                                                            'chat.orders.filter.accepted',
                                                        ),
                                                    },
                                                    {
                                                        key: 'paid',
                                                        label: __(
                                                            'chat.orders.filter.paid',
                                                        ),
                                                    },
                                                    {
                                                        key: 'completed',
                                                        label: __(
                                                            'chat.orders.filter.completed',
                                                        ),
                                                    },
                                                    {
                                                        key: 'cancelled',
                                                        label: __(
                                                            'chat.orders.filter.cancelled',
                                                        ),
                                                    },
                                                    {
                                                        key: 'refunded',
                                                        label: __(
                                                            'chat.orders.filter.refunded',
                                                        ),
                                                    },
                                                    {
                                                        key: 'disputed',
                                                        label: __(
                                                            'chat.orders.filter.disputed',
                                                        ),
                                                    },
                                                ]"
                                                :key="pill.key"
                                                class="order-filter-pill"
                                                :class="{
                                                    'order-filter-pill--active':
                                                        orderStatusFilter ===
                                                        pill.key,
                                                    'order-filter-pill--empty':
                                                        (orderCounts[
                                                            pill.key
                                                        ] ?? 0) === 0 &&
                                                        orderStatusFilter !==
                                                            pill.key,
                                                    [`order-filter-pill--${pill.key}`]:
                                                        pill.key !== 'all',
                                                }"
                                                @click="
                                                    applyStatusFilter(pill.key)
                                                "
                                            >
                                                {{ pill.label }}
                                                <span
                                                    class="order-filter-pill__count"
                                                    >{{
                                                        orderCounts[pill.key] ??
                                                        0
                                                    }}</span
                                                >
                                            </button>
                                        </div>
                                    </Transition>
                                </div>
                            </div>
                            <!-- /chat-sticky-controls -->

                            <!-- Список — скелетон или данные -->
                            <template v-if="loadingOrders">
                                <div v-for="n in 4" :key="n" class="order-skel">
                                    <div class="order-skel__head">
                                        <div
                                            class="order-skel__avatar skel-pulse"
                                        ></div>
                                        <div class="order-skel__lines">
                                            <div
                                                class="order-skel__name skel-pulse"
                                                :style="{
                                                    width:
                                                        [55, 42, 60, 48][
                                                            n - 1
                                                        ] + '%',
                                                }"
                                            ></div>
                                            <div
                                                class="order-skel__date skel-pulse"
                                                :style="{
                                                    width:
                                                        [28, 35, 25, 32][
                                                            n - 1
                                                        ] + '%',
                                                }"
                                            ></div>
                                        </div>
                                        <div
                                            class="order-skel__badge skel-pulse"
                                        ></div>
                                    </div>
                                    <div class="order-skel__sep"></div>
                                    <div class="order-skel__foot">
                                        <div
                                            class="order-skel__count skel-pulse"
                                        ></div>
                                        <div
                                            class="order-skel__total skel-pulse"
                                        ></div>
                                    </div>
                                </div>
                            </template>
                            <template v-else>
                                <template v-if="visibleOrders.length === 0">
                                    <div class="chat-no-convs">
                                        <p>{{ __("chat.orders.empty") }}</p>
                                    </div>
                                </template>
                                <template v-else>
                                    <button
                                        v-for="order in visibleOrders"
                                        :key="order.id"
                                        class="order-stub"
                                        :class="[
                                            `order-stub--${order.status}`,
                                            {
                                                'order-stub--active':
                                                    activeOrderData?.id ===
                                                    order.id,
                                            },
                                        ]"
                                        @click="openOrderConversation(order)"
                                    >
                                        <div class="order-stub__head">
                                            <div
                                                class="chat-conv-avatar-wrapper"
                                            >
                                                <UserAvatar
                                                    :user="(order.is_customer ? order.idol : order.customer)"
                                                    :size="48"
                                                />
                                            </div>
                                            <div class="order-stub__who">
                                                <span
                                                    class="order-stub__name"
                                                    >{{
                                                        (order.is_customer
                                                            ? order.idol
                                                            : order.customer
                                                        ).name
                                                    }}</span
                                                >
                                                <span
                                                    class="order-stub__date"
                                                    >{{
                                                        formatDate(
                                                            order.created_at,
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                            <span
                                                class="order-stub__badge"
                                                :class="`order-stub__badge--${order.status}`"
                                            >
                                                {{
                                                    orderStatusLabels[
                                                        order.status
                                                    ]
                                                }}
                                            </span>
                                            <span
                                                v-if="order.unread"
                                                class="chat-conv-badge chat-conv-badge--dot"
                                            ></span>
                                        </div>
                                        <div class="order-stub__perf">
                                            <span
                                                class="order-stub__perf-dot"
                                                v-for="n in 14"
                                                :key="n"
                                            ></span>
                                        </div>
                                        <div class="order-stub__foot">
                                            <span class="order-stub__count">
                                                {{
                                                    transChoice(
                                                        "order.service_count",
                                                        order.items.length,
                                                        {
                                                            count: order.items
                                                                .length,
                                                        },
                                                    )
                                                }}
                                            </span>
                                            <span class="order-stub__total"
                                                >{{
                                                    orderTotal(
                                                        order,
                                                    ).toLocaleString("ru-RU")
                                                }}&thinsp;₽</span
                                            >
                                        </div>
                                    </button> </template
                                ><!-- /visibleOrders -->

                                <!-- Load more -->
                                <div
                                    v-if="ordersHasMore || loadingMoreOrders"
                                    class="orders-load-more"
                                >
                                    <button
                                        class="orders-load-more-btn"
                                        :disabled="loadingMoreOrders"
                                        @click="fetchOrders(false)"
                                    >
                                        <span v-if="!loadingMoreOrders">{{
                                            __("notification.load_more")
                                        }}</span>
                                        <span v-else class="orders-load-dots">
                                            <span
                                                class="orders-load-dot"
                                            ></span>
                                            <span
                                                class="orders-load-dot"
                                            ></span>
                                            <span
                                                class="orders-load-dot"
                                            ></span>
                                        </span>
                                    </button>
                                </div> </template
                            ><!-- /v-else (not loading) --> </template
                        ><!-- /orders tab -->
                    </div>
                </div>

                <!-- ── Main: переписка ────────────────────── -->
                <div
                    class="chat-main"
                    :class="{
                        'chat-main--mobile-hidden':
                            isMobile && mobileView === 'list',
                    }"
                >
                    <!-- Пусто — нет выбранного диалога -->
                    <!-- Пусто — нет выбранного диалога -->
                    <div v-if="!activeConversation && !mobileOpening" class="chat-main__empty">
                        <svg
                            width="48"
                            height="48"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            style="opacity: 0.2"
                        >
                            <path
                                d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                            />
                        </svg>
                        <span>{{ __("chat.empty.select") }}</span>
                    </div>

                    <!-- Единый лоадер: по центру всего chat-main, не прыгает при появлении шапки -->
                    <Transition name="msgs-fade">
                        <div v-show="mobileOpening || (loadingMsgs && activeConversation)" class="chat-msgs-loader">
                            <div class="chat-msgs-loader__dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                    </Transition>

                    <template v-if="activeConversation && !mobileOpening">
                        <!-- Шапка диалога -->
                        <div class="chat-main__header">
                            <button
                                v-if="isMobile"
                                class="chat-main__back-btn"
                                @click="backToList"
                            >
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <polyline points="15 18 9 12 15 6" />
                                </svg>
                            </button>
                            <div class="chat-main__avatar-wrapper">
                                <div
                                    class="chat-conv-avatar"
                                    :class="{
                                        'chat-conv-avatar--clickable': !isSupport,
                                        'chat-conv-avatar--support': isSupport
                                    }"
                                    @click="
                                        !isSupport && (avatarFullscreen = true)
                                    "
                                    :title="
                                        !isSupport
                                            ? __('chat.photo.view')
                                            : undefined
                                    "
                                >
                                    <template v-if="isSupport">
                                        <span class="chat-support-icon">✦</span>
                                    </template>
                                    <template v-else>
                                        <UserAvatar
                                            :user="activeConversation.other_user"
                                            :size="44"
                                        />
                                    </template>
                                </div>
                                <!-- Индикаторы на аватарке -->
                                <template v-if="!isSupport">
                                    <div
                                        v-if="isOtherOnline"
                                        class="chat-avatar-online-dot"
                                    ></div>
                                    <div
                                        v-if="
                                            activeConversation.other_user
                                                ?.is_idol
                                        "
                                        class="chat-avatar-idol-badge"
                                    >
                                        IDOL
                                    </div>
                                </template>
                            </div>

                            <div class="chat-main__header-info">
                                <div class="chat-main__name-row">
                                    <template v-if="isSupport">
                                        <span class="chat-main__name">{{
                                            __("chat.support")
                                        }}</span>
                                    </template>
                                    <template v-else>
                                        <Link
                                            v-if="
                                                activeConversation.other_user?.id
                                            "
                                            :href="
                                                route('profile.show', {
                                                    user: activeConversation
                                                        .other_user.id,
                                                })
                                            "
                                            class="chat-main__name"
                                            >{{
                                                activeConversation.other_user
                                                    ?.name ?? "…"
                                            }}</Link
                                        >
                                        <span v-else class="chat-main__name">{{
                                            activeConversation.other_user
                                                ?.name ?? "…"
                                        }}</span>
                                    </template>
                                </div>
                                <div v-if="isTyping" class="chat-main__typing">
                                    Печатает
                                    <span class="chat-typing-dots"><span>.</span><span>.</span><span>.</span></span>
                                </div>
                            </div>
                            <button
                                v-if="
                                    !isSupport &&
                                    !activeConversation.is_draft &&
                                    (!activeBlock?.active ||
                                        activeBlock?.i_am_blocker)
                                "                                class="chat-lock-btn"
                                :class="{
                                    'chat-lock-btn--active':
                                        activeBlock?.active &&
                                        activeBlock?.i_am_blocker,
                                }"
                                :title="
                                    activeBlock?.active
                                        ? __('chat.blocked')
                                        : __('chat.block.action')
                                "
                                @click="blockModal = true"
                            >
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <rect
                                        x="3"
                                        y="11"
                                        width="18"
                                        height="11"
                                        rx="2"
                                        ry="2"
                                    />
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                </svg>
                            </button>
                        </div>

                        <!-- Таймер авто-завершения — встроенный блок под шапкой -->
                        <Transition name="timer-pop">
                            <div
                                v-if="activeOrderData?.status === 'paid'"
                                class="chat-order-timer-bar"
                            >
                                <div class="chat-order-timer-bar__inner">
                                    <svg
                                        width="15"
                                        height="15"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        style="flex-shrink: 0"
                                    >
                                        <circle cx="12" cy="12" r="10" />
                                        <polyline points="12 6 12 12 16 14" />
                                    </svg>
                                    <span class="chat-order-timer-bar__label">{{
                                        __("chat.auto_complete")
                                    }}</span>
                                    <span class="chat-order-timer-bar__value">{{
                                        orderTimerLabel
                                    }}</span>
                                </div>
                            </div>
                        </Transition>

                        <!-- Сообщения -->
                        <div class="chat-messages-wrap">
                            <div
                                class="chat-messages"
                                ref="messagesContainer"
                                @scroll="onMessagesScroll"
                            >
                                    <!-- Индикатор подгрузки -->
                                    <div
                                        v-if="loadingMore"
                                        class="chat-loading-more"
                                    >
                                        {{ __("common.loading") }}
                                    </div>

                                    <TransitionGroup
                                        name="msg"
                                        tag="div"
                                        class="chat-messages-inner"
                                    >
                                        <template
                                            v-for="item in groupedMessages"
                                            :key="item.key ?? item.msg?.id"
                                        >
                                            <!-- Date divider -->
                                            <div
                                                v-if="item.type === 'divider'"
                                                class="chat-date-divider"
                                            >
                                                <span>{{ item.label }}</span>
                                            </div>

                                            <!-- System message -->
                                            <div
                                                v-else-if="
                                                    item.type === 'message' &&
                                                    item.msg.type === 'system'
                                                "
                                                class="chat-system-msg"
                                            >
                                                <template
                                                    v-if="
                                                        item.msg.metadata
                                                            ?.event ===
                                                        'order_created'
                                                    "
                                                >
                                                    <div class="sc-card">
                                                        <p class="sc-title">
                                                            {{
                                                                __(
                                                                    "chat.msg.order_placed",
                                                                )
                                                            }}
                                                        </p>
                                                        <div
                                                            class="sc-rule sc-rule--double"
                                                        ></div>
                                                        <div class="sc-lines">
                                                            <div
                                                                v-for="s in item
                                                                    .msg
                                                                    .metadata
                                                                    .services"
                                                                :key="s.id"
                                                                class="sc-line"
                                                            >
                                                                <span
                                                                    class="sc-line__name"
                                                                    >{{
                                                                        s.name
                                                                    }}</span
                                                                >
                                                                <span
                                                                    class="sc-line__dots"
                                                                ></span>
                                                                <span
                                                                    class="sc-line__qty"
                                                                    v-if="
                                                                        (s.quantity ??
                                                                            1) >
                                                                        1
                                                                    "
                                                                    >×{{
                                                                        s.quantity
                                                                    }}</span
                                                                >
                                                                <span
                                                                    class="sc-line__price"
                                                                    >{{
                                                                        (
                                                                            s.price *
                                                                            (s.quantity ??
                                                                                1)
                                                                        )?.toLocaleString(
                                                                            "ru-RU",
                                                                        )
                                                                    }}&thinsp;₽<template
                                                                        v-if="
                                                                            s.time_unit
                                                                        "
                                                                        >&thinsp;/&thinsp;{{
                                                                            s.time_unit
                                                                        }}</template
                                                                    ></span
                                                                >
                                                            </div>
                                                        </div>
                                                        <div class="sc-perf">
                                                            <span
                                                                class="sc-perf__line"
                                                            ></span>
                                                        </div>
                                                        <div class="sc-total">
                                                            <span
                                                                class="sc-total__label"
                                                                >{{
                                                                    __(
                                                                        "chat.msg.total",
                                                                    )
                                                                }}</span
                                                            >
                                                            <span
                                                                class="sc-total__value"
                                                                >{{
                                                                    item.msg.metadata.services
                                                                        .reduce(
                                                                            (
                                                                                sum,
                                                                                s,
                                                                            ) =>
                                                                                sum +
                                                                                (s.price ??
                                                                                    0) *
                                                                                    (s.quantity ??
                                                                                        1),
                                                                            0,
                                                                        )
                                                                        .toLocaleString(
                                                                            "ru-RU",
                                                                        )
                                                                }}&thinsp;₽</span
                                                            >
                                                        </div>
                                                        <p class="sc-date">
                                                            {{
                                                                formatTime(
                                                                    item.msg
                                                                        .created_at,
                                                                )
                                                            }}
                                                        </p>
                                                    </div>
                                                </template>
                                                <template
                                                    v-else-if="
                                                        item.msg.metadata
                                                            ?.event ===
                                                        'order_accepted'
                                                    "
                                                >
                                                    <div
                                                        class="sc-card sc-card--accept"
                                                    >
                                                        <div
                                                            class="sc-rule sc-rule--double sc-rule--green"
                                                        ></div>
                                                        <p
                                                            class="sc-title sc-title--accept"
                                                        >
                                                            {{
                                                                __(
                                                                    "chat.msg.order_accepted",
                                                                )
                                                            }}
                                                        </p>
                                                        <p class="sc-date">
                                                            {{
                                                                formatTime(
                                                                    item.msg
                                                                        .created_at,
                                                                )
                                                            }}
                                                        </p>
                                                        <div
                                                            class="sc-rule sc-rule--double sc-rule--green"
                                                        ></div>
                                                    </div>
                                                </template>
                                                <template
                                                    v-else-if="
                                                        item.msg.metadata
                                                            ?.event ===
                                                        'order_paid'
                                                    "
                                                >
                                                    <div
                                                        class="sc-card sc-card--paid"
                                                    >
                                                        <div
                                                            class="sc-rule sc-rule--double sc-rule--cyan"
                                                        ></div>
                                                        <p
                                                            class="sc-title sc-title--paid"
                                                        >
                                                            {{
                                                                __(
                                                                    "chat.msg.order_paid",
                                                                )
                                                            }}
                                                        </p>
                                                        <p
                                                            class="sc-date sc-date--paid"
                                                        >
                                                            {{
                                                                formatTime(
                                                                    item.msg
                                                                        .created_at,
                                                                )
                                                            }}
                                                        </p>
                                                        <div
                                                            class="sc-rule sc-rule--double sc-rule--cyan"
                                                        ></div>
                                                    </div>
                                                </template>
                                                <template
                                                    v-else-if="
                                                        item.msg.metadata
                                                            ?.event ===
                                                        'order_cancelled'
                                                    "
                                                >
                                                    <div
                                                        class="sc-card sc-card--cancel"
                                                    >
                                                        <div
                                                            class="sc-rule sc-rule--double sc-rule--red"
                                                        ></div>
                                                        <p
                                                            class="sc-title sc-title--cancel"
                                                        >
                                                            {{
                                                                __(
                                                                    "chat.msg.order_cancelled",
                                                                )
                                                            }}
                                                        </p>
                                                        <p
                                                            class="sc-who sc-who--cancel"
                                                        >
                                                            {{
                                                                item.msg
                                                                    .metadata
                                                                    .cancelled_by ===
                                                                authUser?.id
                                                                    ? __(
                                                                          "chat.msg.cancelled_by_you",
                                                                      )
                                                                    : (item.msg
                                                                          .metadata
                                                                          .cancelled_by_name ??
                                                                      __(
                                                                          "chat.msg.cancelled_by_other",
                                                                      ))
                                                            }}
                                                        </p>
                                                        <p
                                                            v-if="
                                                                item.msg
                                                                    .metadata
                                                                    .cancel_reason
                                                            "
                                                            class="sc-reason"
                                                        >
                                                            {{
                                                                item.msg
                                                                    .metadata
                                                                    .cancel_reason
                                                            }}
                                                        </p>
                                                        <p
                                                            class="sc-date sc-date--cancel"
                                                        >
                                                            {{
                                                                formatTime(
                                                                    item.msg
                                                                        .created_at,
                                                                )
                                                            }}
                                                        </p>
                                                        <div
                                                            class="sc-rule sc-rule--double sc-rule--red"
                                                        ></div>
                                                    </div>
                                                </template>
                                                <template
                                                    v-else-if="
                                                        item.msg.metadata
                                                            ?.event ===
                                                        'item_added'
                                                    "
                                                >
                                                    <div
                                                        class="sc-card sc-card--update"
                                                    >
                                                        <p
                                                            class="sc-title sc-title--update"
                                                        >
                                                            {{
                                                                __(
                                                                    "chat.msg.order_updated",
                                                                )
                                                            }}
                                                        </p>
                                                        <div
                                                            class="sc-rule sc-rule--double sc-rule--cyan"
                                                        ></div>
                                                        <div class="sc-lines">
                                                            <div
                                                                v-for="s in item
                                                                    .msg
                                                                    .metadata
                                                                    .services"
                                                                :key="s.id"
                                                                class="sc-line"
                                                            >
                                                                <span
                                                                    class="sc-line__name"
                                                                    >{{
                                                                        s.name
                                                                    }}</span
                                                                >
                                                                <span
                                                                    class="sc-line__dots"
                                                                ></span>
                                                                <span
                                                                    class="sc-line__qty"
                                                                    v-if="
                                                                        (s.quantity ??
                                                                            1) >
                                                                        1
                                                                    "
                                                                    >×{{
                                                                        s.quantity
                                                                    }}</span
                                                                >
                                                                <span
                                                                    class="sc-line__price"
                                                                    >{{
                                                                        (
                                                                            (s.price ??
                                                                                0) *
                                                                            (s.quantity ??
                                                                                1)
                                                                        ).toLocaleString(
                                                                            "ru-RU",
                                                                        )
                                                                    }}&thinsp;₽<template
                                                                        v-if="
                                                                            s.time_unit
                                                                        "
                                                                        >&thinsp;/&thinsp;{{
                                                                            s.time_unit
                                                                        }}</template
                                                                    ></span
                                                                >
                                                            </div>
                                                        </div>
                                                        <div class="sc-perf">
                                                            <span
                                                                class="sc-perf__line"
                                                            ></span>
                                                        </div>
                                                        <div class="sc-total">
                                                            <span
                                                                class="sc-total__label"
                                                                >{{
                                                                    __(
                                                                        "chat.msg.total",
                                                                    )
                                                                }}</span
                                                            >
                                                            <span
                                                                class="sc-total__value"
                                                                >{{
                                                                    (
                                                                        item.msg
                                                                            .metadata
                                                                            .services ??
                                                                        []
                                                                    )
                                                                        .reduce(
                                                                            (
                                                                                sum,
                                                                                s,
                                                                            ) =>
                                                                                sum +
                                                                                (s.price ??
                                                                                    0) *
                                                                                    (s.quantity ??
                                                                                        1),
                                                                            0,
                                                                        )
                                                                        .toLocaleString(
                                                                            "ru-RU",
                                                                        )
                                                                }}&thinsp;₽</span
                                                            >
                                                        </div>
                                                        <p class="sc-date">
                                                            {{
                                                                formatTime(
                                                                    item.msg
                                                                        .created_at,
                                                                )
                                                            }}
                                                        </p>
                                                    </div>
                                                </template>
                                                <template
                                                    v-else-if="
                                                        item.msg.metadata
                                                            ?.event ===
                                                        'completion_confirmed_by_idol'
                                                    "
                                                >
                                                    <div
                                                        class="sc-card sc-card--confirm"
                                                    >
                                                        <div
                                                            class="sc-rule sc-rule--green"
                                                        ></div>
                                                        <p
                                                            class="sc-title sc-title--confirm"
                                                        >
                                                            {{
                                                                __(
                                                                    "chat.msg.completed",
                                                                )
                                                            }}
                                                        </p>
                                                        <p class="sc-who">
                                                            {{
                                                                __(
                                                                    "chat.msg.completed.idol",
                                                                )
                                                            }}
                                                        </p>
                                                        <p class="sc-date">
                                                            {{
                                                                formatTime(
                                                                    item.msg
                                                                        .created_at,
                                                                )
                                                            }}
                                                        </p>
                                                        <div
                                                            class="sc-rule sc-rule--green"
                                                        ></div>
                                                    </div>
                                                </template>
                                                <template
                                                    v-else-if="
                                                        item.msg.metadata
                                                            ?.event ===
                                                        'completion_confirmed_by_customer'
                                                    "
                                                >
                                                    <div
                                                        class="sc-card sc-card--confirm"
                                                    >
                                                        <div
                                                            class="sc-rule sc-rule--green"
                                                        ></div>
                                                        <p
                                                            class="sc-title sc-title--confirm"
                                                        >
                                                            {{
                                                                __(
                                                                    "chat.msg.completed",
                                                                )
                                                            }}
                                                        </p>
                                                        <p class="sc-who">
                                                            {{
                                                                __(
                                                                    "chat.msg.completed.customer",
                                                                )
                                                            }}
                                                        </p>
                                                        <p class="sc-date">
                                                            {{
                                                                formatTime(
                                                                    item.msg
                                                                        .created_at,
                                                                )
                                                            }}
                                                        </p>
                                                        <div
                                                            class="sc-rule sc-rule--green"
                                                        ></div>
                                                    </div>
                                                </template>
                                                <template
                                                    v-else-if="
                                                        item.msg.metadata
                                                            ?.event ===
                                                            'order_completed' ||
                                                        item.msg.metadata
                                                            ?.event ===
                                                            'order_auto_completed'
                                                    "
                                                >
                                                    <div
                                                        v-if="
                                                            activeOrderData?.is_customer
                                                        "
                                                        class="chat-repeat-wrap"
                                                    >
                                                        <button
                                                            class="chat-repeat-btn"
                                                            @click="
                                                                repeatOrderOpen = true
                                                            "
                                                        >
                                                            {{
                                                                __(
                                                                    "chat.msg.repeat",
                                                                )
                                                            }}
                                                        </button>
                                                    </div>
                                                </template>
                                                <template
                                                    v-else-if="
                                                        item.msg.metadata
                                                            ?.event ===
                                                        'chat_closed'
                                                    "
                                                >
                                                    <div
                                                        class="chat-event-label"
                                                    >
                                                        {{
                                                            __(
                                                                "chat.status.closed",
                                                            )
                                                        }}
                                                        <span
                                                            class="chat-event-label__time"
                                                            >{{
                                                                formatTime(
                                                                    item.msg
                                                                        .created_at,
                                                                )
                                                            }}</span
                                                        >
                                                    </div>
                                                </template>
                                                <template
                                                    v-else-if="
                                                        item.msg.metadata
                                                            ?.event ===
                                                        'chat_opened'
                                                    "
                                                >
                                                    <div
                                                        class="chat-event-label"
                                                    >
                                                        {{
                                                            __(
                                                                "chat.status.open",
                                                            )
                                                        }}
                                                        <span
                                                            class="chat-event-label__time"
                                                            >{{
                                                                formatTime(
                                                                    item.msg
                                                                        .created_at,
                                                                )
                                                            }}</span
                                                        >
                                                    </div>
                                                </template>
                                                <template
                                                    v-else-if="
                                                        item.msg.metadata
                                                            ?.event ===
                                                        'review_submitted'
                                                    "
                                                >
                                                    <div
                                                        class="chat-event-label chat-event-label--review"
                                                    >
                                                        {{
                                                            __(
                                                                "chat.status.reviewed",
                                                            )
                                                        }}
                                                    </div>
                                                </template>
                                                <template v-else>
                                                    <div
                                                        class="chat-event-label"
                                                    >
                                                        {{
                                                            item.msg.body || "—"
                                                        }}
                                                        <span
                                                            class="chat-event-label__time"
                                                            >{{
                                                                formatTime(
                                                                    item.msg
                                                                        .created_at,
                                                                )
                                                            }}</span
                                                        >
                                                    </div>
                                                </template>
                                            </div>

                                            <!-- Service offer message — one bubble per service -->
                                            <template
                                                v-else-if="
                                                    item.type === 'message' &&
                                                    item.msg.type ===
                                                        'service_offer'
                                                "
                                            >
                                                <div
                                                    class="chat-msg"
                                                    :class="{
                                                        'chat-msg--mine':
                                                            item.msg.sender_id === authUser?.id,
                                                        'chat-msg--first-in-group':
                                                            item.isFirstInGroup,
                                                        'chat-msg--last-in-group':
                                                            item.isLastInGroup,
                                                    }"
                                                >
                                                    <div class="svc-offer-bubble">
                                                        <div class="svc-offer__header">
                                                            {{ __("chat.msg.offer_label") }}
                                                        </div>
                                                        <div class="svc-offer__cards-wrapper">
                                                            <div
                                                                v-for="svc in item.msg.metadata?.services ?? []"
                                                                :key="svc.id"
                                                                class="svc-offer__card"
                                                            >
                                                                <span class="svc-offer__svc-name">
                                                                    {{ svc.name }}
                                                                </span>
                                                                <span class="svc-offer__svc-time" v-if="svc.time_unit">
                                                                    <svg
                                                                        width="12"
                                                                        height="12"
                                                                        viewBox="0 0 24 24"
                                                                        fill="none"
                                                                        stroke="currentColor"
                                                                        stroke-width="2.5"
                                                                        stroke-linecap="round"
                                                                        stroke-linejoin="round"
                                                                        style="margin-right: 4px;"
                                                                    >
                                                                        <circle cx="12" cy="12" r="10" />
                                                                        <polyline points="12 6 12 12 16 14" />
                                                                    </svg>
                                                                    {{ svc.time_unit }}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div class="svc-offer__footer">
                                                            <button
                                                                v-if="
                                                                    item.msg.sender_id !== authUser?.id &&
                                                                    (!activeOrderData || activeOrderData.status === 'pending' || !activeOrderData.id)
                                                                "
                                                                class="svc-offer__cart-btn"
                                                                @click="handleOfferAction(item.msg.metadata?.services ?? [])"
                                                            >
                                                                <svg
                                                                    width="16"
                                                                    height="16"
                                                                    viewBox="0 0 24 24"
                                                                    fill="none"
                                                                    stroke="currentColor"
                                                                    stroke-width="2"
                                                                    stroke-linecap="round"
                                                                    stroke-linejoin="round"
                                                                >
                                                                    <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" />
                                                                    <line x1="3" y1="6" x2="21" y2="6" />
                                                                    <path d="M16 10a4 4 0 01-8 0" />
                                                                </svg>
                                                                <span>{{ (!activeOrderData || !activeOrderData.id) ? 'Создать заказ' : 'Добавить к заказу' }}</span>
                                                            </button>

                                                            <span class="chat-msg__meta">
                                                                <span class="chat-msg__time">{{ formatTime(item.msg.created_at) }}</span>
                                                                <span
                                                                    v-if="item.msg.sender_id === authUser?.id"
                                                                    class="chat-msg__status"
                                                                    :class="{
                                                                        'chat-msg__status--read': isMessageRead(item.msg),
                                                                    }"
                                                                >
                                                                    <span class="chat-ticks">
                                                                        <el-icon class="chat-tick chat-tick--1"><Check /></el-icon>
                                                                        <el-icon class="chat-tick chat-tick--2"><Check /></el-icon>
                                                                    </span>
                                                                </span>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Regular message -->
                                            <div
                                                v-else-if="
                                                    item.type === 'message'
                                                "
                                                class="chat-msg"
                                                :class="{
                                                    'chat-msg--mine':
                                                        item.msg.sender_id ===
                                                        authUser?.id,
                                                    'chat-msg--first-in-group':
                                                        item.isFirstInGroup,
                                                    'chat-msg--last-in-group':
                                                        item.isLastInGroup,
                                                }"
                                            >
                                                <div
                                                    class="chat-msg__bubble"
                                                    :class="{
                                                        'chat-msg__bubble--image':
                                                            item.msg.type ===
                                                                'image' &&
                                                            item.msg.metadata
                                                                ?.image_url,
                                                    }"
                                                >
                                                    <template
                                                        v-if="
                                                            item.msg.type ===
                                                                'image' &&
                                                            item.msg.metadata
                                                                ?.image_url
                                                        "
                                                    >
                                                        <a
                                                            :href="
                                                                item.msg
                                                                    .metadata
                                                                    .image_url
                                                            "
                                                            target="_blank"
                                                            rel="noopener"
                                                        >
                                                            <img
                                                                :src="
                                                                    item.msg
                                                                        .metadata
                                                                        .image_url
                                                                "
                                                                class="chat-msg__image"
                                                            />
                                                        </a>
                                                    </template>
                                                    <span
                                                        v-else
                                                        class="chat-msg__text"
                                                        >{{
                                                            item.msg.body
                                                        }}</span
                                                    >
                                                    <span
                                                        class="chat-msg__meta"
                                                    >
                                                        <span
                                                            class="chat-msg__time"
                                                            >{{
                                                                formatTime(
                                                                    item.msg
                                                                        .created_at,
                                                                )
                                                            }}</span
                                                        >
                                                        <span
                                                            v-if="
                                                                item.msg
                                                                    .sender_id ===
                                                                authUser?.id
                                                            "
                                                            class="chat-msg__status"
                                                            :class="{
                                                                'chat-msg__status--read':
                                                                    isMessageRead(
                                                                        item.msg,
                                                                    ),
                                                            }"
                                                        >
                                                            <span
                                                                class="chat-ticks"
                                                            >
                                                                <el-icon
                                                                    class="chat-tick chat-tick--1"
                                                                >
                                                                    <Check />
                                                                </el-icon>
                                                                <el-icon
                                                                    class="chat-tick chat-tick--2"
                                                                >
                                                                    <Check />
                                                                </el-icon>
                                                            </span>
                                                        </span>
                                                    </span>
                                                </div>
                                            </div>
                                        </template>
                                    </TransitionGroup>


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
                            </div>

                            <!-- Оверлей: скрывает скролл при открытии диалога -->
                            <Transition name="cover-fade">
                                <div
                                    v-if="coverMessages"
                                    class="chat-messages-cover"
                                />
                            </Transition>

                            <!-- Оверлей: заблокированный -->
                            <div
                                v-if="
                                    activeBlock?.active &&
                                    !activeBlock?.i_am_blocker
                                "
                                class="chat-blocked-overlay"
                            >
                                <div class="chat-blocked-card">
                                    <p class="chat-blocked-header">
                                        ================================
                                    </p>
                                    <p class="chat-blocked-title">
                                        {{ __("chat.access_denied") }}
                                    </p>
                                    <p class="chat-blocked-header">
                                        ================================
                                    </p>
                                    <p class="chat-blocked-reason">
                                        {{ __(activeBlock.reason) }}
                                    </p>
                                    <p class="chat-blocked-header">
                                        --------------------------------
                                    </p>
                                    <div class="chat-blocked-timer">
                                        <span
                                            class="chat-blocked-timer__label"
                                            v-if="activeBlock.blocked_until"
                                            >{{ __("chat.time_left") }}</span
                                        >
                                        <span
                                            class="chat-blocked-timer__label"
                                            v-else
                                            >{{ __("chat.block_until") }}</span
                                        >
                                        <span
                                            class="chat-blocked-timer__value"
                                            >{{ blockedUntilLabel }}</span
                                        >
                                    </div>
                                    <p class="chat-blocked-header">
                                        ================================
                                    </p>
                                </div>
                            </div>
                            <div class="chat-input-fade"></div>
                        </div>
                        <!-- end chat-messages-wrap -->

                        <!-- Поле ввода + панель заказа -->
                        <Transition name="chat-input-appear">
                            <div v-if="!loadingMsgs" class="chat-input-wrap">
                                <!-- Баннер: чат закрыт -->
                                <div
                                    v-if="isChatClosed"
                                    class="chat-closed-banner"
                                >
                                    {{ __("chat.wait_support") }}
                                </div>

                                <!-- Баннер блокировщика -->
                                <div
                                    v-if="
                                        activeBlock?.active &&
                                        activeBlock?.i_am_blocker
                                    "
                                    class="chat-block-banner"
                                >
                                    <div class="chat-block-banner__info">
                                        <span
                                            class="chat-block-banner__label"
                                            >{{
                                                __("chat.user_blocked_label")
                                            }}</span
                                        >
                                        <span class="chat-block-banner__timer">
                                            {{
                                                activeBlock.blocked_until
                                                    ? __(
                                                          "chat.block.time_remaining",
                                                          {
                                                              time: blockedUntilLabel,
                                                          },
                                                      )
                                                    : __(
                                                          "chat.block.dur.forever",
                                                      )
                                            }}
                                        </span>
                                    </div>
                                    <button
                                        @click="submitUnblock"
                                        class="chat-block-unblock-btn"
                                    >
                                        {{ __("chat.block.unblock") }}
                                    </button>
                                </div>

                                <!-- Панель действий заказа -->
                                <div
                                    v-if="
                                        activeOrderData &&
                                        [
                                            'pending',
                                            'accepted',
                                            'paid',
                                        ].includes(activeOrderData.status)
                                    "
                                    class="chat-order-actions"
                                >
                                    <button
                                        v-if="
                                            !activeOrderData.is_customer &&
                                            activeOrderData.status === 'pending'
                                        "
                                        class="chat-order-btn chat-order-btn--accept"
                                        :disabled="!!orderAction"
                                        @click="acceptModal = true"
                                    >
                                        {{ __("chat.btn.accept") }}
                                    </button>
                                    <button
                                        v-if="
                                            activeOrderData.is_customer &&
                                            activeOrderData.status ===
                                                'accepted'
                                        "
                                        class="chat-order-btn chat-order-btn--pay"
                                        :disabled="!!orderAction"
                                        @click="payOrder"
                                    >
                                        <span
                                            v-if="orderAction === 'pay'"
                                            class="order-btn-spinner"
                                        />
                                        <template v-else>{{
                                            __("chat.btn.pay_order")
                                        }}</template>
                                    </button>
                                    <button
                                        v-if="activeOrderData.status === 'paid'"
                                        class="chat-order-btn chat-order-btn--complete"
                                        :disabled="
                                            myConfirmation || !!orderAction
                                        "
                                        @click="completeModal = true"
                                    >
                                        {{
                                            myConfirmation
                                                ? __("chat.btn.confirmed")
                                                : __("chat.btn.order_done")
                                        }}
                                    </button>
                                    <button
                                        v-if="
                                            ['pending', 'accepted'].includes(
                                                activeOrderData.status,
                                            )
                                        "
                                        class="chat-order-btn chat-order-btn--cancel"
                                        :disabled="!!orderAction"
                                        @click="cancelModal = true"
                                    >
                                        {{ __("chat.btn.cancel") }}
                                    </button>
                                    <!-- Предложить услугу — рядом с кнопками заказа -->
                                    <button
                                        v-if="
                                            authUser?.is_idol &&
                                            !activeOrderData.is_customer &&
                                            activeOrderData.status === 'pending'
                                        "
                                        class="chat-order-btn chat-order-btn--offer"
                                        @click="showOfferModal = true"
                                    >
                                        {{ __("chat.btn.offer") }}
                                    </button>
                                </div>

                                <!-- Кнопка предложения услуги — для обычного чата (без заказа) -->
                                <div
                                    v-if="
                                        authUser?.is_idol &&
                                        !isSupport &&
                                        !activeOrderData &&
                                        !isChatClosed
                                    "
                                    class="chat-order-actions"
                                >
                                    <button
                                        class="chat-order-btn chat-order-btn--offer"
                                        @click="showOfferModal = true"
                                    >
                                        {{ __("chat.btn.offer") }}
                                    </button>
                                </div>

                                <!-- Плашка: заказ отменён -->
                                <div
                                    v-if="
                                        activeOrderData?.status === 'cancelled'
                                    "
                                    class="chat-order-cancelled-bar"
                                >
                                    <span
                                        class="chat-order-cancelled-bar__label"
                                        >{{
                                            __("chat.order.cancelled_label")
                                        }}</span
                                    >
                                    <template
                                        v-if="cancelledByLabel(activeOrderData)"
                                    >
                                        <span
                                            class="chat-order-cancelled-bar__who"
                                            >{{
                                                cancelledByLabel(
                                                    activeOrderData,
                                                )
                                            }}</span
                                        >
                                    </template>
                                    <span
                                        v-if="activeOrderData.cancel_reason"
                                        class="chat-order-cancelled-bar__reason"
                                        >{{
                                            activeOrderData.cancel_reason
                                        }}</span
                                    >
                                </div>

                                <!-- Плашка: заказ выполнен -->
                                <div
                                    v-if="
                                        activeOrderData?.status === 'completed'
                                    "
                                    class="chat-order-completed-bar"
                                >
                                    <span
                                        class="chat-order-completed-bar__label"
                                        >{{ __("chat.order.done_label") }}</span
                                    >
                                    <span
                                        v-if="activeOrderData.completed_at"
                                        class="chat-order-completed-bar__time"
                                        >{{
                                            formatDate(
                                                activeOrderData.completed_at,
                                            )
                                        }}</span
                                    >
                                </div>

                                <!-- Плашка: спор -->
                                <div
                                    v-if="
                                        activeOrderData?.status === 'disputed'
                                    "
                                    class="chat-order-disputed-bar"
                                >
                                    <span
                                        class="chat-order-disputed-bar__label"
                                        >{{
                                            __("chat.order.dispute_label")
                                        }}</span
                                    >
                                </div>

                                <!-- Плашка: заказ аннулирован -->
                                <div
                                    v-if="
                                        activeOrderData?.status === 'refunded'
                                    "
                                    class="chat-order-cancelled-bar"
                                >
                                    <span
                                        class="chat-order-cancelled-bar__label"
                                        >{{
                                            __("chat.order.annulled_label")
                                        }}</span
                                    >
                                </div>

                                <!-- Textarea (скрыт если заказ завершён в финальном статусе) -->
                                <div
                                    v-if="
                                        !activeOrderData ||
                                        ![
                                            'cancelled',
                                            'completed',
                                            'disputed',
                                            'refunded',
                                        ].includes(activeOrderData.status)
                                    "
                                    class="chat-input-inner"
                                >
                                    <input
                                        v-if="isSupport"
                                        type="file"
                                        ref="fileInput"
                                        accept="image/*"
                                        style="display: none"
                                        @change="onFileChange"
                                    />
                                    <button
                                        v-if="isSupport"
                                        class="chat-attach-btn"
                                        :disabled="
                                            isChatClosed ||
                                            uploading ||
                                            (!!activeBlock?.active &&
                                                !activeBlock?.i_am_blocker)
                                        "
                                        @click="fileInput.click()"
                                        :title="__('chat.attach')"
                                    >
                                        <svg
                                            width="16"
                                            height="16"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <path
                                                d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"
                                            />
                                        </svg>
                                    </button>
                                    <div class="chat-input-box">
                                        <textarea
                                            v-model="newMessage"
                                            class="chat-input"
                                            :placeholder="__('chat.placeholder')"
                                            rows="3"
                                            maxlength="500"
                                            :disabled="
                                                isChatClosed ||
                                                (!!activeBlock?.active &&
                                                    !activeBlock?.i_am_blocker)
                                            "
                                            @keydown.enter="handleEnter"
                                            @input="onInput"
                                        />
                                        <button
                                        class="chat-send"
                                        :disabled="
                                            !newMessage.trim() ||
                                            sending ||
                                            isChatClosed ||
                                            (!!activeBlock?.active &&
                                                !activeBlock?.i_am_blocker)
                                        "
                                        @mousedown.prevent
                                        @click="sendMessage"
                                    >
                                        <template v-if="sending">
                                            <span class="order-btn-spinner" />
                                        </template>
                                        <template v-else>
                                            <svg
                                                width="22"
                                                height="22"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <line
                                                    x1="22"
                                                    y1="2"
                                                    x2="11"
                                                    y2="13"
                                                />
                                                <polygon
                                                    points="22 2 15 22 11 13 2 9 22 2"
                                                />
                                            </svg>
                                        </template>
                                    </button>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ── Модалка блокировки ─────────────────────── -->
    <SiteModal
        :show="blockModal"
        variant="pink"
        compact
        @close="blockModal = false"
    >
        <h2 class="bm-title">
            {{ __("chat.block.user_title") }}
            <span class="bm-title-name">{{
                activeConversation?.other_user?.name
            }}</span>
        </h2>

        <div class="bm-section-label">{{ __("common.reason") }}</div>
        <div class="bm-reasons">
            <button
                v-for="r in blockReasons"
                :key="r"
                class="bm-reason-tag"
                :class="{ 'bm-reason-tag--selected': blockReason === r }"
                @click="blockReason = r"
            >
                {{ __(r) }}
            </button>
        </div>

        <div class="bm-section-label">{{ __("chat.block.duration") }}</div>
        <div class="bm-durations">
            <button
                v-for="d in blockDurations"
                :key="d.label"
                class="bm-duration-btn"
                :class="{
                    'bm-duration-btn--selected':
                        blockDurationChosen && blockDuration === d.minutes,
                }"
                @click="
                    () => {
                        blockDuration = d.minutes;
                        blockDurationChosen = true;
                    }
                "
            >
                {{ d.label }}
            </button>
        </div>

        <div class="bm-footer">
            <button class="bm-cancel" @click="blockModal = false">
                {{ __("common.cancel") }}
            </button>
            <button
                class="bm-submit"
                :disabled="
                    !blockReason || !blockDurationChosen || blockSubmitting
                "
                @click="submitBlock"
            >
                {{ __("chat.block.action") }}
            </button>
        </div>
    </SiteModal>

    <!-- ── Модалка отмены заказа ─────────────────────── -->
    <SiteModal
        :show="cancelModal"
        variant="pink"
        compact
        @close="cancelModal = false"
    >
        <div class="cm-wrap">
            <div class="cm-rule cm-rule--double cm-rule--red"></div>
            <h2 class="cm-title">{{ __("order.cancel.title") }}</h2>
            <div class="cm-rule cm-rule--double cm-rule--red"></div>

            <div class="cm-section-label">{{ __("order.cancel.choose") }}</div>
            <div class="cm-tags">
                <button
                    v-for="t in cancelTemplates"
                    :key="t"
                    class="cm-tag"
                    :class="{ 'cm-tag--selected': cancelReason === t }"
                    @click="cancelReason = t"
                >
                    {{ t }}
                </button>
            </div>

            <div class="cm-section-label">{{ __("order.cancel.custom") }}</div>
            <textarea
                v-model="cancelReason"
                class="cm-textarea"
                placeholder="причина отмены…"
                rows="3"
                maxlength="1000"
            ></textarea>

            <div class="cm-perf"><span class="cm-perf__line"></span></div>

            <div class="cm-footer">
                <button
                    class="cm-btn cm-btn--back"
                    @click="cancelModal = false"
                >
                    {{ __("order.cancel.back") }}
                </button>
                <button
                    class="cm-btn cm-btn--confirm"
                    :disabled="!cancelReason.trim() || cancelSubmitting"
                    @click="submitCancelOrder"
                >
                    {{
                        cancelSubmitting
                            ? __("order.cancel.loading")
                            : __("order.cancel.submit")
                    }}
                </button>
            </div>
        </div>
    </SiteModal>

    <!-- ── Подтверждение предложения (Создать или Добавить) ────────── -->
    <SiteModal
        :show="confirmOfferModal"
        variant="blue"
        compact
        max-width="420px"
        @close="confirmOfferModal = false"
    >
        <div class="cm-title cm-title--cyan">
            {{ confirmOfferMode === 'create' ? 'Создать заказ' : __("chat.add_to_order") }}
        </div>
        
        <div v-for="(svc, idx) in confirmOfferServices" :key="idx" style="margin-bottom: 0.75rem;">
            <div class="confirm-add__svc">{{ svc.name }}</div>
            <div class="confirm-add__price">
                {{ svc.price.toLocaleString("ru-RU") }}&thinsp;₽<template v-if="svc.time_unit">&thinsp;/&thinsp;{{ svc.time_unit }}</template>
            </div>
        </div>

        <div class="cm-perf">
            <span class="cm-perf__line cm-perf__line--cyan"></span>
        </div>
        
        <div class="confirm-add__total" style="text-align: center; margin: 1rem 0; font-weight: 600; font-size: 1.1rem; color: #fff;">
            Итого: {{ confirmOfferServices.reduce((sum, svc) => sum + (svc.price || 0), 0).toLocaleString("ru-RU") }}&thinsp;₽
        </div>
        
        <div class="cm-footer">
            <button
                class="cm-btn cm-btn--back"
                @click="confirmOfferModal = false"
            >
                {{ __("order.cancel.back") }}
            </button>
            <button
                class="cm-btn cm-btn--confirm-cyan"
                :disabled="confirmOfferLoading"
                @click="confirmOfferAction"
            >
                {{
                    confirmOfferLoading
                        ? (confirmOfferMode === 'create' ? 'Создание...' : __("chat.add_to_order.loading"))
                        : (confirmOfferMode === 'create' ? 'Создать заказ' : __("chat.add_to_order.btn"))
                }}
            </button>
        </div>
    </SiteModal>

    <!-- ── Модалка подтверждения принятия заказа ────────── -->
    <SiteModal
        :show="acceptModal"
        variant="blue"
        compact
        max-width="420px"
        @close="acceptModal = false"
    >
        <div class="cm-title cm-title--cyan">
            {{ __("chat.btn.accept") }}
        </div>
        <div class="cm-body">{{ __("chat.order.accept_confirm") }}</div>
        <div class="cm-perf">
            <span class="cm-perf__line cm-perf__line--cyan"></span>
        </div>
        <div class="cm-footer">
            <button
                class="cm-btn cm-btn--back"
                :disabled="orderAction === 'accept'"
                @click="acceptModal = false"
            >
                {{ __("order.cancel.back") }}
            </button>
            <button
                class="cm-btn cm-btn--confirm-cyan"
                :disabled="orderAction === 'accept'"
                @click="acceptOrder()"
            >
                <span
                    v-if="orderAction === 'accept'"
                    class="order-btn-spinner"
                />
                <template v-else>{{ __("order.accept") }}</template>
            </button>
        </div>
    </SiteModal>

    <!-- ── Модалка подтверждения выполнения заказа ─────── -->
    <SiteModal
        :show="completeModal"
        variant="blue"
        compact
        max-width="420px"
        @close="completeModal = false"
    >
        <div class="cm-title cm-title--green">
            {{ __("chat.btn.order_done") }}
        </div>
        <div class="cm-body" style="white-space: pre-line">{{ __("chat.order.complete_confirm") }}</div>
        <div class="cm-perf">
            <span class="cm-perf__line cm-perf__line--green"></span>
        </div>
        <div class="cm-footer">
            <button
                class="cm-btn cm-btn--back"
                :disabled="orderAction === 'complete'"
                @click="completeModal = false"
            >
                {{ __("order.cancel.back") }}
            </button>
            <button
                class="cm-btn cm-btn--confirm-green"
                :disabled="orderAction === 'complete'"
                @click="confirmCompletion()"
            >
                <span
                    v-if="orderAction === 'complete'"
                    class="order-btn-spinner"
                />
                <template v-else>{{ __("chat.btn.order_done") }}</template>
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
            <div
                v-if="avatarFullscreen"
                class="avatar-fs-backdrop"
                @click="avatarFullscreen = false"
            >
                <div class="avatar-fs-inner" @click.stop>
                    <button
                        class="avatar-fs-close"
                        @click="avatarFullscreen = false"
                    >
                        <svg
                            width="18"
                            height="18"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                    <img
                        v-if="activeConversation?.other_user?.avatar_url"
                        :src="activeConversation.other_user.avatar_url"
                        class="avatar-fs-img"
                        :alt="activeConversation.other_user?.name"
                    />
                    <div v-else class="avatar-fs-placeholder" :class="{ 'is-male': activeConversation?.other_user?.gender === 'male' }">
                        <span class="avatar-fs-initial">{{
                            activeConversation?.other_user?.name
                                ?.charAt(0)
                                ?.toUpperCase() ?? "?"
                        }}</span>
                        <span class="avatar-fs-noavatar">{{
                            __("chat.no_photos")
                        }}</span>
                    </div>
                    <div class="avatar-fs-name">
                        {{ activeConversation?.other_user?.name }}
                    </div>
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
    z-index: 1100;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

.backdrop-enter-active,
.backdrop-leave-active {
    transition: opacity 0.25s;
}

.backdrop-enter-from,
.backdrop-leave-to {
    opacity: 0;
}

/* ── Panel ────────────────────────────────────────────── */
.chat-panel {
    position: fixed;
    top: 60px;
    right: 0;
    bottom: 0;
    width: 1100px;
    max-width: 100vw;
    z-index: 1101;
    display: flex;
    background: linear-gradient(160deg, #0f0f22 0%, #0a0a16 100%);
    border-left: 1px solid rgba(255, 178, 239, 0.22);
    box-shadow:
        -8px 0 64px rgba(0, 0, 0, 0.7),
        -1px 0 0 rgba(255, 178, 239, 0.06);
    overscroll-behavior: contain;
}

@media (min-width: 1440px) {
    .chat-panel {
        width: 1320px;
    }
}

.slide-enter-active,
.slide-leave-active {
    transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
}

.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}

/* ── Sidebar ──────────────────────────────────────────── */
.chat-sidebar {
    width: 340px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(255, 178, 239, 0.14);
    background: #0b0b18;
    background-image: radial-gradient(
        ellipse 260px 140px at 50% 0%,
        rgba(255, 178, 239, 0.1) 0%,
        transparent 100%
    );
}

.chat-sidebar__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1rem 0.85rem;
    border-bottom: 1px solid rgba(255, 178, 239, 0.12);
    flex-shrink: 0;
}

@media (max-width: 768px) {
    .chat-sidebar__header {
        flex-direction: row-reverse;
    }
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
    transition:
        color 0.15s,
        background 0.15s;
}

.chat-icon-btn:hover {
    color: rgba(255, 255, 255, 0.85);
    background: rgba(255, 178, 239, 0.1);
}

/* ── Sticky sidebar controls ──────────────────────────── */
.chat-sticky-controls {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #0b0b18;
    border-bottom: 1px solid rgba(255, 178, 239, 0.1);
    padding-top: 1px;
}

/* ── Search ───────────────────────────────────────────── */
.chat-sidebar__search {
    padding: 0.55rem 0.75rem;
}

.chat-search-input {
    width: 100%;
    background: rgba(255, 178, 239, 0.07);
    border: 1px solid rgba(255, 178, 239, 0.18);
    border-radius: 8px;
    padding: 0.45rem 0.75rem;
    color: rgba(255, 255, 255, 0.85);
    font-size: 0.875rem;
    outline: none;
    transition: border-color 0.15s;
    box-sizing: border-box;
}

.chat-search-input:focus {
    border-color: rgba(255, 178, 239, 0.45);
}

.chat-search-input::placeholder {
    color: rgba(255, 255, 255, 0.22);
}

.chat-sidebar__list {
    flex: 1;
    overflow-y: auto;
    padding: 0 0 0.35rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.08) transparent;
}

.chat-sidebar__list::-webkit-scrollbar {
    width: 3px;
}

.chat-sidebar__list::-webkit-scrollbar-track {
    background: transparent;
}

.chat-sidebar__list::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 3px;
}

.chat-sidebar__list::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.15);
}

.chat-empty {
    padding: 2.5rem 1rem;
    text-align: center;
    color: rgba(255, 255, 255, 0.28);
    font-size: 0.95rem;
}

/* ── Messages skeleton loader ─────────────────────────── */
/* ── Messages loader ───────────────────────────────── */
.chat-msgs-loader {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    z-index: 5;
}

.chat-msgs-loader__dots {
    display: flex;
    align-items: center;
    gap: 10px;
}

.chat-msgs-loader__dots span {
    display: block;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: rgba(255, 178, 239, 0.85);
    box-shadow:
        0 0 8px rgba(255, 178, 239, 0.6),
        0 0 20px rgba(255, 178, 239, 0.25);
    animation: chat-dot-wave 1.3s ease-in-out infinite;
    will-change: transform, opacity;
}

.chat-msgs-loader__dots span:nth-child(1) { animation-delay: 0s; }
.chat-msgs-loader__dots span:nth-child(2) { animation-delay: 0.18s; background: rgba(180, 130, 255, 0.85); box-shadow: 0 0 8px rgba(180,130,255,0.6), 0 0 20px rgba(180,130,255,0.25); }
.chat-msgs-loader__dots span:nth-child(3) { animation-delay: 0.36s; background: rgba(130, 100, 255, 0.75); box-shadow: 0 0 8px rgba(130,100,255,0.5), 0 0 20px rgba(130,100,255,0.2); }

@keyframes chat-dot-wave {
    0%, 60%, 100% {
        transform: translateY(0) scale(1);
        opacity: 0.5;
    }
    30% {
        transform: translateY(-8px) scale(1.15);
        opacity: 1;
    }
}

.msgs-fade-enter-active,
.msgs-fade-leave-active {
    transition: opacity 0.2s ease;
}

.msgs-fade-enter-from,
.msgs-fade-leave-to {
    opacity: 0;
}

/* ── Empty state ──────────────────────────────────────── */
.chat-no-convs {
    padding: 2.5rem 1rem;
    text-align: center;
    color: rgba(255, 255, 255, 0.28);
    font-size: 0.92rem;
}

.chat-no-convs p {
    margin: 0 0 0.6rem;
}

.chat-no-convs a {
    color: rgba(255, 178, 239, 0.65);
    text-decoration: none;
    font-size: 0.88rem;
    transition: color 0.15s;
}

.chat-no-convs a:hover {
    color: rgba(255, 178, 239, 0.9);
}

/* ── Conversation items ───────────────────────────────── */
.chat-conv-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: calc(100% - 16px);
    margin: 8px;
    padding: 0.7rem 0.85rem;
    background: rgba(255, 255, 255, 0.015);
    border: none;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03);
    cursor: pointer;
    text-align: left;
    transition: background 0.15s, box-shadow 0.15s;
    border-radius: 8px;
    position: relative;
}

.chat-conv-item:hover {
    background: rgba(255, 178, 239, 0.04);
    box-shadow: inset 0 0 0 1px rgba(255, 178, 239, 0.08);
}

.chat-conv-item--active {
    background: rgba(255, 178, 239, 0.08);
    box-shadow: inset 0 0 0 1px rgba(255, 178, 239, 0.15);
}

.chat-conv-item--active:hover {
    background: rgba(255, 178, 239, 0.12);
    box-shadow: inset 0 0 0 1px rgba(255, 178, 239, 0.2);
    transform: none;
}


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
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.chat-conv-avatar-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.chat-conv-avatar--support {
    background: rgba(255, 178, 239, 0.15);
    border: 1.5px solid rgba(255, 178, 239, 0.3);
    overflow: hidden;
    color: #ffb2ef;
    font-weight: 700;
    font-size: 1.05rem;
}

.chat-conv-avatar.is-male {
    background: rgba(100, 210, 255, 0.15);
    border-color: rgba(100, 210, 255, 0.3);
    color: var(--color-base-2);
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

.chat-conv-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.chat-header-avatar-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

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
    background: linear-gradient(160deg, rgba(230, 90, 155, 0.95) 0%, rgba(175, 45, 105, 0.9) 100%);
    box-shadow: inset 0 1px 0 rgba(255, 210, 235, 0.35);
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 20px;
    text-align: center;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
}

.chat-conv-badge--dot {
    min-width: 10px;
    width: 10px;
    height: 10px;
    padding: 0;
    line-height: 1;
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
    justify-content: center;
    gap: 1.25rem;
    padding: 1rem;
    padding-bottom: 1.2rem;
    border-bottom: none;
    box-shadow:
        0 1px 0 rgba(255, 178, 239, 0.12),
        0 4px 20px rgba(0, 0, 0, 0.25);
    flex-shrink: 0;
}

.chat-main__header-info {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 2px;
    flex: 1;
    min-width: 0;
}

.chat-main__typing {
    position: absolute;
    top: 100%;
    left: 0;
    font-size: 0.75rem;
    color: var(--cat-accent, #ffb2ef);
    opacity: 0.9;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    animation: fadeIn 0.2s ease-out;
}

.chat-typing-dots span {
    animation: typingDots 1.4s infinite;
    opacity: 0;
}
.chat-typing-dots span:nth-child(1) { animation-delay: 0s; }
.chat-typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.chat-typing-dots span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typingDots {
    0% { opacity: 0; }
    50% { opacity: 1; }
    100% { opacity: 0; }
}

.chat-main__name-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.chat-main__name {
    font-size: 1rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    transition: color 0.15s;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.chat-main__name:hover {
    color: #ffb2ef;
}

.chat-main__avatar-wrapper {
    position: relative;
    display: inline-flex;
    flex-shrink: 0;
}

.chat-avatar-online-dot {
    position: absolute;
    top: -1px;
    right: -1px;
    width: 10px;
    height: 10px;
    background: #3ddc84;
    border: 2px solid #0f0f22;
    border-radius: 50%;
    z-index: 6;
    box-shadow: 0 0 6px rgba(61, 220, 132, 0.4);
}

.chat-avatar-idol-badge {
    position: absolute;
    bottom: -5px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(
        180deg,
        color-mix(in srgb, var(--color-base-1), #000 30%) 0%,
        color-mix(in srgb, var(--color-base-1), #000 60%) 100%
    );
    color: #000;
    font-size: 0.56rem;
    font-weight: 900;
    padding: 1px 4px;
    border-radius: 3px;
    letter-spacing: 0.06em;
    z-index: 7;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.5);
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    min-width: 32px;
}

/* ── Messages ─────────────────────────────────────────── */
.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 1.1rem 0 0.5rem;
    display: flex;
    flex-direction: column;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 178, 239, 0.15) transparent;
}

.chat-messages::-webkit-scrollbar {
    width: 4px;
}

.chat-messages::-webkit-scrollbar-track {
    background: transparent;
}

.chat-messages::-webkit-scrollbar-thumb {
    background: rgba(255, 178, 239, 0.15);
    border-radius: 99px;
}

.chat-messages::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 178, 239, 0.3);
}

.chat-messages-inner {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding-bottom: 210px;
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
    content: "";
    flex: 1;
    height: 1px;
    background: linear-gradient(
        to right,
        transparent,
        rgba(255, 178, 239, 0.22)
    );
}

.chat-date-divider::after {
    content: "";
    flex: 1;
    height: 1px;
    background: linear-gradient(
        to left,
        transparent,
        rgba(255, 178, 239, 0.22)
    );
}

/* ── Message row ──────────────────────────────────────── */
.chat-msg {
    display: flex;
    align-items: flex-end;
    gap: 6px;
    justify-content: flex-start;
    padding: 0 1.1rem;
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
    background: linear-gradient(
        135deg,
        rgba(255, 255, 255, 0.05) 0%,
        rgba(255, 255, 255, 0.02) 100%
    );
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow:
        0 2px 10px rgba(0, 0, 0, 0.25),
        inset 0 1px 0 rgba(255, 255, 255, 0.03);
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.chat-msg--mine .chat-msg__bubble {
    background: linear-gradient(
        135deg,
        rgba(255, 178, 239, 0.12) 0%,
        rgba(255, 178, 239, 0.05) 100%
    );
    border-color: rgba(255, 178, 239, 0.15);
    box-shadow:
        0 2px 8px rgba(0, 0, 0, 0.2),
        inset 0 1px 0 rgba(255, 178, 239, 0.08);
}

/* Smart corners — theirs (left side) */
.chat-msg:not(.chat-msg--mine).chat-msg--first-in-group .chat-msg__bubble {
    border-top-left-radius: 14px;
}

.chat-msg:not(.chat-msg--mine):not(.chat-msg--first-in-group)
    .chat-msg__bubble {
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
    width: 18px;
    /* ширина = иконка + сдвиг */
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
    transition:
        opacity 0.2s,
        color 0.2s;
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
    color: rgba(255, 178, 239, 0.55);
    padding: 0.25rem 0;
    font-style: italic;
}

/* ── Message enter animation ──────────────────────────── */
.msg-enter-active {
    transition: all 0.2s ease;
}

.msg-enter-from {
    opacity: 0;
    transform: translateY(8px);
}

/* ── Input ────────────────────────────────────────────── */
.chat-input-appear-enter-active {
    transition: opacity 0.2s ease;
}

.chat-input-appear-enter-from {
    opacity: 0;
}

.chat-input-appear-enter-to {
    opacity: 1;
}

.chat-input-wrap {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 10;
    padding: 0.85rem 0;
    background: rgba(14, 14, 28, 0.65);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.chat-input-fade {
    display: none;
}

.chat-input-inner {
    flex: 1;
    position: relative;
    padding: 0 1.1rem;
}

.chat-input-box {
    position: relative;
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: flex-end;
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 14px;
    padding: 0.25rem;
    transition: border-color 0.15s, background 0.15s;
}

.chat-input-box:focus-within {
    border-color: rgba(255, 178, 239, 0.4);
    background: rgba(255, 255, 255, 0.05);
    box-shadow: 0 0 0 3px rgba(130, 80, 255, 0.08);
}



.chat-input {
    width: 100%;
    flex: 1;
    box-sizing: border-box;
    background: transparent;
    border: none;
    padding: 0.5rem 3.5rem 0.5rem 0.75rem;
    color: rgba(255, 255, 255, 0.95);
    font-size: 0.95rem;
    resize: none;
    line-height: 1.55;
    outline: none;
    font-family: inherit;
    display: block;
}

.chat-input:focus {
    outline: none !important;
    box-shadow: none !important;
    border: none !important;
}

.chat-input::placeholder {
    color: rgba(255, 255, 255, 0.25);
}

.chat-char-count {
    position: absolute;
    right: 5rem;
    top: 0.8rem;
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
    position: relative;
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(
        135deg,
        rgba(255, 178, 239, 0.15),
        rgba(255, 178, 239, 0.08)
    );
    border: 1px solid rgba(255, 178, 239, 0.35);
    border-radius: 12px;
    color: rgba(255, 178, 239, 0.95);
    cursor: pointer;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.15), 0 4px 10px rgba(0, 0, 0, 0.15);
    transition:
        background 0.15s,
        border-color 0.15s,
        color 0.15s,
        box-shadow 0.15s;
}

.chat-send:hover:not(:disabled) {
    background: linear-gradient(
        135deg,
        rgba(255, 178, 239, 0.25),
        rgba(255, 178, 239, 0.15)
    );
    border-color: rgba(255, 178, 239, 0.6);
    color: #fff;
    box-shadow: 0 2px 16px rgba(255, 178, 239, 0.3);
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
    border-radius: 3px;
    color: rgba(220, 60, 60, 0.65);
    cursor: pointer;
    transition:
        color 0.15s,
        background 0.15s,
        border-color 0.15s;
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

.avatar-fade-enter-active,
.avatar-fade-leave-active {
    transition: opacity 0.2s;
}

.avatar-fade-enter-from,
.avatar-fade-leave-to {
    opacity: 0;
}

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
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.55);
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition:
        background 0.15s,
        color 0.15s;
}

.avatar-fs-close:hover {
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
}

.avatar-fs-img {
    width: min(72vw, 400px);
    height: min(72vw, 400px);
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.12);
}

.avatar-fs-placeholder {
    width: min(72vw, 400px);
    height: min(72vw, 400px);
    border-radius: 50%;
    background: rgba(255, 178, 239, 0.15);
    border: 2px solid rgba(255, 178, 239, 0.3);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.avatar-fs-placeholder.is-male {
    background: rgba(100, 210, 255, 0.15);
    border-color: rgba(100, 210, 255, 0.3);
}

.avatar-fs-initial {
    font-size: clamp(4rem, 15vw, 8rem);
    font-weight: 700;
    color: rgba(255, 178, 239, 0.85);
    line-height: 1;
}

.avatar-fs-placeholder.is-male .avatar-fs-initial {
    color: rgba(100, 210, 255, 0.85);
}

.avatar-fs-noavatar {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.3);
}

.avatar-fs-name {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.75);
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

.cover-fade-leave-active {
    transition: opacity 0.15s ease;
}

.cover-fade-leave-to {
    opacity: 0;
}

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
    font-family: "Courier New", Courier, monospace;
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
    font-family: "Courier New", Courier, monospace;
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
    font-family: "Courier New", Courier, monospace;
    transition:
        border-color 0.15s,
        color 0.15s,
        background 0.15s;
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
    border: 1px solid rgba(255, 178, 239, 0.3);
    background: transparent;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    cursor: pointer;
    font-family: inherit;
    transition:
        border-color 0.15s,
        color 0.15s,
        background 0.15s;
}

.bm-reason-tag:hover {
    border-color: rgba(255, 178, 239, 0.5);
    color: rgba(255, 255, 255, 0.85);
}

.bm-reason-tag--selected {
    border-color: rgba(255, 178, 239, 0.7);
    background: rgba(255, 178, 239, 0.2);
    color: #ffb2ef;
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
    border: 1px solid rgba(255, 178, 239, 0.3);
    background: transparent;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    cursor: pointer;
    font-family: inherit;
    transition:
        border-color 0.15s,
        color 0.15s,
        background 0.15s;
}

.bm-duration-btn:hover {
    border-color: rgba(255, 178, 239, 0.5);
    color: rgba(255, 255, 255, 0.85);
}

.bm-duration-btn--selected {
    border-color: rgba(255, 178, 239, 0.7);
    background: rgba(255, 178, 239, 0.2);
    color: #ffb2ef;
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
    transition:
        border-color 0.15s,
        color 0.15s;
}

.bm-cancel:hover {
    border-color: rgba(255, 255, 255, 0.25);
    color: rgba(255, 255, 255, 0.75);
}

.bm-submit {
    background: linear-gradient(
        135deg,
        rgba(220, 60, 100, 0.45),
        rgba(180, 30, 80, 0.4)
    );
    border: 1px solid rgba(220, 80, 110, 0.5);
    color: #ff9ab5;
    font-size: 0.88rem;
    padding: 0.45rem 1.25rem;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
    font-weight: 600;
    transition:
        background 0.15s,
        border-color 0.15s;
}

.bm-submit:hover:not(:disabled) {
    background: linear-gradient(
        135deg,
        rgba(220, 60, 100, 0.65),
        rgba(180, 30, 80, 0.6)
    );
    border-color: rgba(220, 80, 110, 0.75);
}

.bm-submit:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.bm-submit--danger {
    background: linear-gradient(
        135deg,
        rgba(220, 60, 60, 0.45),
        rgba(180, 30, 30, 0.4)
    );
    border: 1px solid rgba(220, 80, 80, 0.5);
    color: #ffaaaa;
}

.bm-submit--danger:hover:not(:disabled) {
    background: linear-gradient(
        135deg,
        rgba(220, 60, 60, 0.65),
        rgba(180, 30, 30, 0.6)
    );
    border-color: rgba(220, 80, 80, 0.75);
}

.bm-textarea {
    width: 100%;
    background: rgba(255, 178, 239, 0.07);
    border: 1px solid rgba(255, 178, 239, 0.2);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.85);
    padding: 0.55rem 0.75rem;
    font-size: 0.875rem;
    font-family: inherit;
    resize: vertical;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.15s;
}

.bm-textarea:focus {
    border-color: rgba(255, 178, 239, 0.4);
}

/* ── Cancel order modal ─────────────────────────────────── */
.cm-wrap {
    font-family: "Courier New", Courier, monospace;
    color: rgba(210, 240, 255, 0.78);
    display: flex;
    flex-direction: column;
    gap: 0;
}

.cm-rule {
    width: 100%;
    height: 0;
    border: none;
    border-top: 2px double rgba(100, 210, 255, 0.3);
    margin: 0.5rem 0;
}

.cm-rule--red {
    border-color: rgba(220, 80, 80, 0.45);
}

.cm-title {
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.22em;
    color: rgba(255, 120, 120, 0.9);
    text-align: center;
    margin: 0.3rem 0;
}

.cm-title--cyan {
    color: rgba(80, 230, 200, 0.9);
}

.cm-title--green {
    color: rgba(80, 240, 160, 0.9);
}

.cm-perf__line--cyan {
    border-top-color: rgba(60, 200, 180, 0.3);
    border-top-style: dashed;
}

.cm-perf__line--green {
    border-top-color: rgba(60, 200, 120, 0.3);
    border-top-style: dashed;
}

.cm-body {
    font-size: 0.88rem;
    color: rgba(255, 255, 255, 0.6);
    text-align: center;
    margin: 0.5rem 0;
    line-height: 1.5;
}

.confirm-add__svc {
    font-size: 1.05rem;
    color: rgba(255, 255, 255, 0.88);
    text-align: center;
    margin: 0.75rem 0 0.25rem;
    font-weight: 500;
}

.confirm-add__price {
    font-size: 0.95rem;
    color: rgba(100, 200, 255, 0.75);
    text-align: center;
    font-family: "Courier New", monospace;
    margin-bottom: 0.75rem;
}

.cm-section-label {
    font-size: 0.68rem;
    letter-spacing: 0.12em;
    color: rgba(210, 240, 255, 0.3);
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
    border: 1px dashed rgba(210, 240, 255, 0.2);
    background: transparent;
    color: rgba(210, 240, 255, 0.5);
    font-family: "Courier New", Courier, monospace;
    font-size: 0.75rem;
    letter-spacing: 0.04em;
    cursor: pointer;
    transition:
        border-color 0.15s,
        color 0.15s,
        background 0.15s;
}

.cm-tag:hover {
    border-color: rgba(210, 240, 255, 0.45);
    color: rgba(210, 240, 255, 0.85);
    background: rgba(100, 210, 255, 0.05);
}

.cm-tag--selected {
    border-color: rgba(100, 210, 255, 0.55);
    color: rgba(100, 210, 255, 0.95);
    background: rgba(100, 210, 255, 0.08);
    border-style: solid;
}

.cm-textarea {
    width: 100%;
    box-sizing: border-box;
    background: rgba(100, 210, 255, 0.04);
    border: 1px dashed rgba(100, 210, 255, 0.22);
    border-radius: 3px;
    color: rgba(210, 240, 255, 0.82);
    font-family: "Courier New", Courier, monospace;
    font-size: 0.82rem;
    padding: 0.65rem 0.85rem;
    resize: none;
    outline: none;
    transition: border-color 0.15s;
    margin-top: 0.1rem;
}

.cm-textarea::placeholder {
    color: rgba(210, 240, 255, 0.2);
}

.cm-textarea:focus {
    border-color: rgba(100, 210, 255, 0.45);
    border-style: solid;
}

.cm-perf {
    display: flex;
    align-items: center;
    margin: 1rem -0.1rem 0.85rem;
    position: relative;
}

.cm-perf::before,
.cm-perf::after {
    content: "";
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #12122a;
    border: 1px solid rgba(220, 80, 80, 0.15);
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
}

.cm-perf::before {
    left: -5px;
}

.cm-perf::after {
    right: -5px;
}

.cm-perf__line {
    flex: 1;
    display: block;
    border-top: 1px dashed rgba(220, 80, 80, 0.3);
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
    font-family: "Courier New", Courier, monospace;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.15em;
    cursor: pointer;
    line-height: 1;
    transition:
        background 0.15s,
        border-color 0.15s;
}

.cm-btn--back {
    background: transparent;
    border: 1px solid rgba(210, 240, 255, 0.25);
    color: rgba(210, 240, 255, 0.65);
}

.cm-btn--back:hover {
    border-color: rgba(210, 240, 255, 0.5);
    color: rgba(210, 240, 255, 0.9);
}

.cm-btn--confirm {
    background: rgba(220, 60, 60, 0.08);
    border: 1px solid rgba(220, 60, 60, 0.4);
    color: rgba(255, 120, 120, 0.9);
}

.cm-btn--confirm:hover:not(:disabled) {
    background: rgba(220, 60, 60, 0.18);
    border-color: rgba(220, 60, 60, 0.65);
}

.cm-btn--confirm:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.cm-btn--confirm-cyan {
    background: rgba(60, 200, 180, 0.08);
    border: 1px solid rgba(60, 200, 180, 0.4);
    color: rgba(80, 230, 200, 0.9);
}

.cm-btn--confirm-cyan:hover:not(:disabled) {
    background: rgba(60, 200, 180, 0.18);
    border-color: rgba(60, 200, 180, 0.65);
}

.cm-btn--confirm-cyan:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.cm-btn--confirm-green {
    background: rgba(60, 200, 120, 0.08);
    border: 1px solid rgba(60, 200, 120, 0.4);
    color: rgba(80, 240, 160, 0.9);
}

.cm-btn--confirm-green:hover:not(:disabled) {
    background: rgba(60, 200, 120, 0.18);
    border-color: rgba(60, 200, 120, 0.65);
}

.cm-btn--confirm-green:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

/* ── Chat tabs ──────────────────────────────────────────── */
.chat-tabs {
    display: flex;
    border-bottom: 1px solid rgba(255, 178, 239, 0.12);
    flex-shrink: 0;
    padding: 4px 6px;
    gap: 2px;
}

.chat-tab {
    flex: 1;
    padding: 0.42rem 0;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: rgba(255, 255, 255, 0.35);
    background: transparent;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition:
        color 0.15s,
        background 0.15s;
    font-family: inherit;
    position: relative;
}

.chat-tab + .chat-tab::before {
    content: "";
    position: absolute;
    left: -1px;
    top: 18%;
    height: 64%;
    width: 1px;
    background: rgba(255, 178, 239, 0.18);
    transition: opacity 0.15s;
}

.chat-tab--active + .chat-tab::before,
.chat-tab--active::before {
    opacity: 0;
}

.chat-tab:hover:not(.chat-tab--active) {
    color: rgba(255, 255, 255, 0.6);
    background: rgba(255, 255, 255, 0.04);
}

.chat-tab--active {
    color: var(--color-base-1);
    background: rgba(255, 178, 239, 0.1);
}

.chat-tab__dot {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: linear-gradient(160deg, rgba(240, 120, 175, 0.95) 0%, rgba(190, 55, 110, 0.9) 100%);
    box-shadow: inset 0 1px 0 rgba(255, 220, 235, 0.45);
    vertical-align: middle;
    margin-left: 5px;
    flex-shrink: 0;
    position: relative;
    top: -0.5px;
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
    background: rgba(255, 255, 255, 0.035);
    cursor: pointer;
    text-align: left;
    position: relative;
    overflow: visible;
    transition:
        background 0.15s,
        box-shadow 0.15s;
    box-shadow:
        0 1px 8px rgba(0, 0, 0, 0.35),
        inset 0 0 0 1px rgba(255, 178, 239, 0.13);
}

.order-stub:hover {
    background: rgba(255, 255, 255, 0.055);

    box-shadow:
        0 4px 16px rgba(0, 0, 0, 0.45),
        inset 0 0 0 1px rgba(255, 178, 239, 0.2);
}

.order-stub--active {
    background: rgba(255, 178, 239, 0.08);
    box-shadow:
        0 2px 10px rgba(0, 0, 0, 0.2),
        inset 0 0 0 1px rgba(255, 178, 239, 0.2);
}

.order-stub--active:hover {
    transform: none;
}

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
    color: rgba(255, 255, 255, 0.88);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.order-stub__date {
    font-size: 0.68rem;
    color: rgba(255, 255, 255, 0.28);
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
.order-stub__badge--paid {
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.order-stub__badge--completed {
    background: rgba(80, 240, 160, 0.1);
    color: rgba(80, 240, 160, 0.9);
    border: 1px solid rgba(80, 240, 160, 0.25);
}

.order-stub__badge--cancelled,
.order-stub__badge--refunded,
.order-stub__badge--disputed {
    background: rgba(255, 110, 110, 0.1);
    color: rgba(255, 110, 110, 0.85);
    border: 1px solid rgba(255, 110, 110, 0.25);
}

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
    content: "";
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #0b0b18;
    border: 1px solid rgba(255, 178, 239, 0.13);
    z-index: 2;
}

.order-stub__perf::before {
    left: -4px;
}

.order-stub__perf::after {
    right: -4px;
}

.order-stub__perf-dot {
    flex: 1;
    height: 1px;
    background: rgba(255, 255, 255, 0.07);
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
    color: rgba(255, 255, 255, 0.45);
    letter-spacing: 0.02em;
}

.order-stub__total {
    font-size: 0.92rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: rgba(255, 255, 255, 0.9);
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
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-top: none;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.38);
    cursor: pointer;
    transition:
        background 0.15s,
        color 0.15s,
        border-color 0.15s,
        box-shadow 0.15s;
    box-shadow:
        0 2px 4px rgba(0, 0, 0, 0.25),
        inset 0 1px 0 rgba(255, 255, 255, 0.18);
    position: relative;
}

.chat-order-subtab:hover {
    color: rgba(255, 255, 255, 0.65);
    border-color: rgba(255, 255, 255, 0.13);
    background: rgba(255, 255, 255, 0.07);
}

.chat-order-subtab--active {
    background: rgba(255, 178, 239, 0.13);
    border-color: rgba(255, 178, 239, 0.3);
    border-top: none;
    color: rgba(255, 220, 245, 0.95);
    box-shadow: inset 0 1px 0 rgba(255, 220, 245, 0.25);
}

.chat-order-subtab--active:hover {
    background: rgba(255, 178, 239, 0.18);
}

/* ── Unread-only toggle ──────────────────────────────────── */
.chat-unread-toggle {
    padding: 0.3rem 0.75rem 0.55rem;
    display: flex;
}

.chat-unread-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    height: 30px;
    padding: 0 0.65rem;
    font-size: 0.76rem;
    font-weight: 600;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.03);
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    font-family: inherit;
    white-space: nowrap;
    flex-shrink: 0;
    box-sizing: border-box;
    transition:
        background 0.15s,
        color 0.15s,
        border-color 0.15s;
}

.chat-unread-btn:hover {
    color: rgba(255, 255, 255, 0.75);
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.15);
}

.chat-unread-btn--active {
    color: #ffb2ef;
    border-color: rgba(255, 178, 239, 0.35);
    background: rgba(255, 178, 239, 0.1);
}

.chat-unread-btn__icon {
    flex-shrink: 0;
}

/* ── Order filters ───────────────────────────────────────── */
.order-filters__btns-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.order-filters__btns-row .order-filters__toggle {
    flex: 1;
}

.order-filters__btns-row .chat-unread-btn {
    flex-shrink: 0;
}

.order-filters {
    padding: 0.75rem 0.75rem 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.order-filters__search-input {
    padding-top: 0.28rem;
    padding-bottom: 0.28rem;
}

.order-filters__toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    height: 30px;
    padding: 0 0.65rem;
    font-size: 0.76rem;
    font-weight: 600;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.03);
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    font-family: inherit;
    white-space: nowrap;
    flex-shrink: 0;
    box-sizing: border-box;
    transition:
        background 0.15s,
        color 0.15s,
        border-color 0.15s;
}

.order-filters__toggle:hover {
    color: rgba(255, 255, 255, 0.75);
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.15);
}

.order-filters__toggle--open {
    color: var(--color-base-1);
    border-color: rgba(255, 178, 239, 0.35);
    background: rgba(255, 178, 239, 0.08);
}

.order-filters__arrow {
    transition: transform 0.2s ease;
}

.order-filters__toggle--open .order-filters__arrow {
    transform: rotate(180deg);
}

/* pills expand transition */
.of-expand-enter-active,
.of-expand-leave-active {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}

.of-expand-enter-from,
.of-expand-leave-to {
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
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    font-family: inherit;
    transition:
        background 0.15s,
        color 0.15s,
        border-color 0.15s;
}

.order-filter-pill:hover {
    color: rgba(255, 255, 255, 0.65);
    background: rgba(255, 255, 255, 0.07);
}

.order-filter-pill--active {
    color: rgba(255, 220, 245, 0.95);
    border-color: rgba(255, 178, 239, 0.4);
    background: rgba(255, 178, 239, 0.12);
}

.order-filter-pill--pending.order-filter-pill--active {
    color: rgba(255, 210, 80, 0.9);
    border-color: rgba(180, 130, 0, 0.45);
    background: rgba(180, 130, 0, 0.14);
}

.order-filter-pill--accepted.order-filter-pill--active {
    color: rgba(80, 240, 160, 0.9);
    border-color: rgba(0, 180, 100, 0.4);
    background: rgba(0, 180, 100, 0.12);
}

.order-filter-pill--paid.order-filter-pill--active {
    color: rgba(96, 165, 250, 0.95);
    border-color: rgba(59, 130, 246, 0.4);
    background: rgba(59, 130, 246, 0.12);
}

.order-filter-pill--completed.order-filter-pill--active {
    color: rgba(80, 240, 160, 0.9);
    border-color: rgba(0, 180, 100, 0.4);
    background: rgba(0, 180, 100, 0.12);
}

.order-filter-pill--cancelled.order-filter-pill--active {
    color: rgba(255, 130, 130, 0.85);
    border-color: rgba(180, 50, 50, 0.4);
    background: rgba(180, 50, 50, 0.12);
}

.order-filter-pill--refunded.order-filter-pill--active {
    color: rgba(255, 170, 80, 0.9);
    border-color: rgba(200, 100, 0, 0.4);
    background: rgba(200, 100, 0, 0.12);
}

.order-filter-pill--disputed.order-filter-pill--active {
    color: rgba(255, 100, 100, 0.9);
    border-color: rgba(200, 30, 30, 0.45);
    background: rgba(200, 30, 30, 0.13);
}

.order-filter-pill__count {
    font-size: 0.7rem;
    font-weight: 700;
    opacity: 0.6;
    min-width: 16px;
    text-align: center;
}

.order-filter-pill--active .order-filter-pill__count {
    opacity: 0.85;
}

.order-filter-pill--empty {
    opacity: 0.35;
    pointer-events: none;
}

/* ── Orders load more ───────────────────────────────────── */
.orders-load-more {
    display: flex;
    justify-content: center;
    padding: 0.75rem 1rem;
}

.orders-load-more-btn {
    position: relative;
    width: 100%;
    padding: 0.55rem 1rem;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.07);
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.35);
    cursor: pointer;
    transition: all 0.15s;
    font-family: inherit;
    min-height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.orders-load-more-btn:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.6);
    border-color: rgba(255, 255, 255, 0.12);
}

.orders-load-more-btn:disabled {
    cursor: default;
}

.orders-load-dots {
    display: flex;
    align-items: center;
    gap: 5px;
}

.orders-load-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: rgba(255, 178, 239, 0.5);
    animation: notif-bounce 1s ease-in-out infinite;
}

.orders-load-dot:nth-child(2) {
    animation-delay: 0.15s;
}

.orders-load-dot:nth-child(3) {
    animation-delay: 0.3s;
}

/* ── Order badge in sidebar ─────────────────────────────── */
.chat-order-status-row {
    margin-top: 0.15rem;
}

.chat-order-badge {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 0.12rem 0.45rem;
    border-radius: 3px;
}

.chat-order-badge--pending {
    background: rgba(180, 130, 0, 0.18);
    color: rgba(255, 210, 80, 0.85);
    border: 1px solid rgba(180, 130, 0, 0.3);
}

.chat-order-badge--accepted {
    background: rgba(0, 180, 100, 0.15);
    color: rgba(100, 255, 180, 0.85);
    border: 1px solid rgba(0, 180, 100, 0.3);
}

.chat-order-badge--cancelled {
    background: rgba(180, 50, 50, 0.15);
    color: rgba(255, 140, 140, 0.8);
    border: 1px solid rgba(180, 50, 50, 0.25);
}

/* ── Order detail link ──────────────────────────────────── */
.chat-order-detail-link {
    display: block;
    text-align: center;
    font-size: 0.78rem;
    color: rgba(255, 178, 239, 0.45);
    padding: 0.5rem 1rem;
    text-decoration: none;
    letter-spacing: 0.05em;
    transition: color 0.15s;
    border-bottom: 1px solid rgba(255, 178, 239, 0.08);
}

.chat-order-detail-link:hover {
    color: rgba(255, 178, 239, 0.85);
}

/* ── Order actions panel ────────────────────────────────── */
.chat-order-actions {
    display: flex;
    gap: 0.6rem;
    padding: 0.65rem 1.1rem 0.55rem;
    flex-shrink: 0;
    flex-wrap: wrap;
    border-top: 1px dashed rgba(100, 210, 255, 0.1);
}

@keyframes order-spin {
    to {
        transform: rotate(360deg);
    }
}

.order-btn-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid currentColor;
    border-top-color: transparent;
    border-radius: 50%;
    animation: order-spin 0.6s linear infinite;
    vertical-align: middle;
    opacity: 0.75;
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
    transition:
        background 0.15s,
        border-color 0.15s,
        box-shadow 0.15s;
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
    content: "";
    position: absolute;
    inset: 2px;
    border-radius: 2px;
    opacity: 0;
    transition: opacity 0.15s;
}

.chat-order-btn::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    border-radius: 3px 3px 0 0;
}

.chat-order-btn--accept {
    background: rgba(60, 200, 110, 0.07);
    border: 1px solid rgba(60, 200, 110, 0.4);
    color: rgba(90, 240, 145, 0.92);
    box-shadow: 0 0 12px rgba(60, 200, 110, 0.06);
}

.chat-order-btn--accept::after {
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(140, 255, 180, 0.65) 50%,
        transparent 100%
    );
}

.chat-order-btn--accept::before {
    border: 1px dashed rgba(60, 200, 110, 0.2);
}

.chat-order-btn--accept:hover {
    background: rgba(60, 200, 110, 0.14);
    border-color: rgba(60, 200, 110, 0.65);
    box-shadow: 0 0 18px rgba(60, 200, 110, 0.14);
}

.chat-order-btn--accept:hover::before {
    opacity: 1;
}

.chat-order-btn--pay {
    background: rgba(100, 210, 255, 0.07);
    border: 1px solid rgba(100, 210, 255, 0.38);
    color: rgba(100, 210, 255, 0.9);
    box-shadow: 0 0 12px rgba(100, 210, 255, 0.06);
}

.chat-order-btn--pay::after {
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(180, 235, 255, 0.65) 50%,
        transparent 100%
    );
}

.chat-order-btn--pay::before {
    border: 1px dashed rgba(100, 210, 255, 0.2);
}

.chat-order-btn--pay:hover {
    background: rgba(100, 210, 255, 0.14);
    border-color: rgba(100, 210, 255, 0.65);
    box-shadow: 0 0 18px rgba(100, 210, 255, 0.14);
}

.chat-order-btn--pay:hover::before {
    opacity: 1;
}

.chat-order-btn--cancel {
    background: rgba(220, 60, 60, 0.07);
    border: 1px solid rgba(220, 60, 60, 0.35);
    color: rgba(255, 120, 120, 0.88);
    box-shadow: 0 0 12px rgba(220, 60, 60, 0.05);
}

.chat-order-btn--cancel::after {
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 150, 150, 0.6) 50%,
        transparent 100%
    );
}

.chat-order-btn--cancel::before {
    border: 1px dashed rgba(220, 60, 60, 0.2);
}

.chat-order-btn--cancel:hover {
    background: rgba(220, 60, 60, 0.15);
    border-color: rgba(220, 60, 60, 0.6);
    box-shadow: 0 0 18px rgba(220, 60, 60, 0.13);
}

.chat-order-btn--cancel:hover::before {
    opacity: 1;
}

.chat-order-btn--complete {
    background: rgba(60, 200, 120, 0.07);
    border: 1px solid rgba(60, 200, 120, 0.35);
    color: rgba(80, 240, 160, 0.88);
    box-shadow: 0 0 12px rgba(60, 200, 120, 0.05);
}

.chat-order-btn--complete::after {
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(80, 240, 160, 0.6) 50%,
        transparent 100%
    );
}

.chat-order-btn--complete::before {
    border: 1px dashed rgba(60, 200, 120, 0.2);
}

.chat-order-btn--complete:hover:not(:disabled) {
    background: rgba(60, 200, 120, 0.14);
    border-color: rgba(60, 200, 120, 0.65);
    box-shadow: 0 0 18px rgba(60, 200, 120, 0.14);
}

.chat-order-btn--complete:hover:not(:disabled)::before {
    opacity: 1;
}

.chat-order-btn--complete:disabled {
    opacity: 0.5;
    cursor: default;
}

.chat-order-btn--offer {
    background: rgba(255, 178, 239, 0.07);
    border: 1px solid rgba(255, 178, 239, 0.3);
    color: rgba(255, 178, 239, 0.85);
}

.chat-order-btn--offer::after {
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 178, 239, 0.5) 50%,
        transparent 100%
    );
}

.chat-order-btn--offer::before {
    border: 1px dashed rgba(255, 178, 239, 0.18);
}

.chat-order-btn--offer:hover {
    background: rgba(255, 178, 239, 0.15);
    border-color: rgba(255, 178, 239, 0.5);
}

.chat-order-btn--offer:hover::before {
    opacity: 1;
}

.chat-repeat-wrap {
    display: flex;
    justify-content: center;
}

.chat-repeat-btn {
    padding: 0.75rem 2rem;
    border-radius: 3px;
    font-size: 0.88rem;
    font-weight: 700;
    font-family: "Courier New", Courier, monospace;
    letter-spacing: 0.1em;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    background: rgba(100, 210, 255, 0.06);
    border: 1px solid rgba(100, 210, 255, 0.28);
    color: var(--color-base-2);
    transition:
        background 0.15s,
        border-color 0.15s,
        color 0.15s;
}

.chat-repeat-btn::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(100, 210, 255, 0.45) 50%,
        transparent 100%
    );
}

.chat-repeat-btn::before {
    content: "";
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

.chat-repeat-btn:hover::before {
    opacity: 1;
}

.chat-order-timer-bar {
    flex-shrink: 0;
    padding: 0.55rem 1.25rem;
    background: linear-gradient(160deg, rgb(16, 11, 20) 0%, rgb(7, 6, 11) 100%);
    border-bottom: 1px solid rgba(255, 178, 239, 0.18);
}

.chat-order-timer-bar__inner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    color: rgba(255, 255, 255, 0.55);
}

.chat-order-timer-bar__label {
    font-size: 0.8rem;
    letter-spacing: 0.02em;
}

.chat-order-timer-bar__value {
    font-size: 1.05rem;
    font-weight: 700;
    font-family: "Courier New", Courier, monospace;
    color: rgba(255, 255, 255, 0.82);
    letter-spacing: 0.08em;
    min-width: 7ch;
    text-align: left;
}

.timer-pop-enter-active,
.timer-pop-leave-active {
    transition:
        opacity 0.2s,
        max-height 0.2s;
}

.timer-pop-enter-from,
.timer-pop-leave-to {
    opacity: 0;
}

/* ── Cancelled bar ──────────────────────────────────────── */
.chat-order-cancelled-bar {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.55rem 1.1rem;
    background: rgba(180, 30, 30, 0.07);
    border-top: 1px dashed rgba(255, 100, 100, 0.25);
    border-bottom: 1px dashed rgba(255, 100, 100, 0.25);
    margin: 0 0 0.4rem;
    flex-shrink: 0;
    font-family: "Courier New", Courier, monospace;
}

.chat-order-cancelled-bar__label {
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255, 120, 120, 0.8);
    letter-spacing: 0.12em;
}

.chat-order-cancelled-bar__who {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.45);
    letter-spacing: 0.04em;
}

.chat-order-cancelled-bar__reason {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.3);
    font-style: italic;
}

/* ── Completed bar ──────────────────────────────────────── */
.chat-order-completed-bar {
    display: flex;
    align-items: center;
    padding: 0.55rem 1.1rem;
    background: rgba(80, 240, 160, 0.05);
    border-top: 1px dashed rgba(80, 240, 160, 0.25);
    border-bottom: 1px dashed rgba(80, 240, 160, 0.25);
    margin: 0 0 0.4rem;
    flex-shrink: 0;
    font-family: "Courier New", Courier, monospace;
}

.chat-order-completed-bar__label {
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(80, 240, 160, 0.75);
    letter-spacing: 0.12em;
}

.chat-order-completed-bar__time {
    margin-left: auto;
    font-size: 0.78rem;
    color: rgba(80, 240, 160, 0.7);
    letter-spacing: 0.06em;
}

/* ── Disputed bar ───────────────────────────────────────── */
.chat-order-disputed-bar {
    display: flex;
    align-items: center;
    padding: 0.55rem 1.1rem;
    background: rgba(180, 30, 30, 0.07);
    border-top: 1px dashed rgba(255, 100, 100, 0.25);
    border-bottom: 1px dashed rgba(255, 100, 100, 0.25);
    margin: 0 0 0.4rem;
    flex-shrink: 0;
    font-family: "Courier New", Courier, monospace;
}

.chat-order-disputed-bar__label {
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255, 120, 120, 0.8);
    letter-spacing: 0.12em;
}

/* ── Service offer bubble ───────────────────────────────── */
.svc-offer-bubble {
    min-width: 180px;
    width: fit-content;
    max-width: 100%;
    background: linear-gradient(
        135deg,
        rgba(255, 150, 220, 0.22),
        rgba(200, 120, 180, 0.16)
    );
    border: 1px solid rgba(255, 178, 239, 0.3);
    border-radius: 10px;
    border-bottom-left-radius: 2px;
    overflow: hidden;
    font-family: inherit;
    display: flex;
    flex-direction: column;
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
    color: rgba(255, 178, 239, 0.75);
    border-bottom: 1px solid rgba(255, 178, 239, 0.13);
}

.svc-offer__cards-wrapper {
    background: rgba(255, 255, 255, 0.03);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    margin-bottom: 0.4rem;
    overflow: hidden;
}

.svc-offer__card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.6rem 0.8rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

.svc-offer__card:last-of-type {
    border-bottom: none;
}

.svc-offer__svc-name {
    font-size: 0.92rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.95);
    line-height: 1.35;
    flex: 1;
    word-break: break-word;
}

.svc-offer__svc-time {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--cat-accent, #ffb2ef);
    background: color-mix(in srgb, var(--cat-accent, #ffb2ef) 12%, transparent);
    border: 1px solid color-mix(in srgb, var(--cat-accent, #ffb2ef) 25%, transparent);
    border-radius: 6px;
    white-space: nowrap;
    flex-shrink: 0;
}

.svc-offer__footer {
    display: flex;
    flex-direction: column;
    padding: 0 0.7rem 0.5rem;
}

.svc-offer-bubble .chat-msg__meta {
    padding: 0;
    margin-top: 0.3rem;
    align-self: flex-end;
}

.svc-offer__cart-btn {
    width: 100%;
    margin-top: 0.4rem;
    padding: 0.65rem 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    background: rgba(28, 205, 178, 0.12);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(28, 205, 178, 0.3);
    border-radius: 8px;
    color: #1ccdb2;
    font-size: 0.95rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 
        0 4px 12px rgba(0, 0, 0, 0.15),
        inset 0 1px 0 rgba(255, 255, 255, 0.05);
}

.svc-offer__cart-btn:hover {
    background: rgba(28, 205, 178, 0.2);
    border-color: rgba(28, 205, 178, 0.5);
    color: #26e6c9;
    box-shadow: 
        0 6px 16px rgba(0, 0, 0, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
}
.svc-offer__cart-btn:active {
    background: rgba(28, 205, 178, 0.08);
    box-shadow: none;
}

/* ── Cancelled by in sidebar ────────────────────────────── */
.chat-order-cancelled-by {
    font-size: 0.68rem;
    color: rgba(255, 255, 255, 0.35);
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
    content: "";
    flex: 1;
    height: 0;
    border-top: 1px dashed rgba(100, 210, 255, 0.28);
}

.chat-system-msg:has(.sc-card--accept)::before,
.chat-system-msg:has(.sc-card--accept)::after {
    border-color: rgba(60, 200, 110, 0.32);
}

.chat-system-msg:has(.sc-card--cancel)::before,
.chat-system-msg:has(.sc-card--cancel)::after {
    border-color: rgba(200, 50, 50, 0.32);
}

.chat-system-msg:has(.sc-card--update)::before,
.chat-system-msg:has(.sc-card--update)::after {
    border-color: rgba(60, 180, 255, 0.32);
}

.chat-system-msg:has(.sc-card--paid)::before,
.chat-system-msg:has(.sc-card--paid)::after {
    border-color: rgba(100, 210, 255, 0.32);
}

.chat-system-msg:has(.sc-card--confirm)::before,
.chat-system-msg:has(.sc-card--confirm)::after {
    border-color: rgba(60, 200, 110, 0.32);
}

/* base card */
.sc-card {
    font-family: "Courier New", Courier, monospace;
    background: rgba(100, 210, 255, 0.04);
    border: 1px dashed rgba(100, 210, 255, 0.2);
    border-radius: 4px;
    padding: 0.65rem 1.1rem;
    width: min(620px, 86vw);
    max-width: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.sc-card--accept {
    background: rgba(60, 200, 110, 0.04);
    border-color: rgba(60, 200, 110, 0.25);
    text-align: center;
}

.sc-card--cancel {
    background: rgba(200, 50, 50, 0.05);
    border-color: rgba(200, 50, 50, 0.25);
}

.sc-card--update {
    background: rgba(60, 180, 255, 0.04);
    border-color: rgba(60, 180, 255, 0.22);
}

.sc-card--paid {
    background: rgba(100, 210, 255, 0.04);
    border-color: rgba(100, 210, 255, 0.22);
    text-align: center;
}

.sc-card--confirm {
    background: rgba(60, 200, 110, 0.04);
    border-color: rgba(60, 200, 110, 0.22);
    text-align: center;
}

/* double rule */
.sc-rule {
    width: 100%;
    height: 0;
    border: none;
    border-top: 1px solid rgba(100, 210, 255, 0.12);
    margin: 0.35rem 0;
}

.sc-rule--green {
    border-color: rgba(60, 200, 110, 0.15);
}

.sc-rule--red {
    border-color: rgba(200, 50, 50, 0.15);
}

.sc-rule--cyan {
    border-color: rgba(60, 180, 255, 0.15);
}

.sc-rule--gold {
    border-color: rgba(255, 210, 80, 0.15);
}

/* title */
.sc-title {
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    color: rgba(100, 210, 255, 0.85);
    margin: 0.2rem 0;
    text-align: center;
}

.sc-title--accept {
    color: rgba(80, 230, 130, 0.9);
}

.sc-title--cancel {
    color: rgba(255, 110, 110, 0.85);
}

.sc-title--update {
    color: rgba(60, 180, 255, 0.9);
}

.sc-title--paid {
    color: rgba(100, 210, 255, 0.9);
}

.sc-title--confirm {
    color: rgba(80, 230, 130, 0.9);
}

/* who (idol name / canceller) */
.sc-who {
    font-size: 0.78rem;
    color: rgba(210, 240, 255, 0.45);
    letter-spacing: 0.06em;
    text-align: center;
    margin: 0.1rem 0;
}

.sc-who--cancel {
    color: rgba(255, 200, 200, 0.45);
}

/* cancel reason */
.sc-reason {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.3);
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

.sc-date--cancel {
    color: rgba(255, 255, 255, 0.35);
}

.sc-date--paid {
    color: rgba(255, 255, 255, 0.35);
}

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
    border-bottom: 1px solid rgba(100, 210, 255, 0.06);
}

.sc-lines .sc-line:last-child {
    border-bottom: none;
}

.sc-line__name {
    font-size: 0.8rem;
    color: rgba(210, 240, 255, 0.72);
    white-space: normal;
    overflow: visible;
    max-width: 55%;
    flex-shrink: 1;
    min-width: 0;
}

.sc-line__dots {
    flex: 1;
    border-bottom: 1px dotted rgba(100, 210, 255, 0.28);
    margin: 0 0.4rem;
    position: relative;
    top: -3px;
    min-width: 0.5rem;
}

.sc-line__price {
    font-size: 0.8rem;
    font-weight: 700;
    color: rgba(100, 210, 255, 0.88);
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
    content: "";
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #0b0b18;
    border: 1px solid rgba(100, 210, 255, 0.12);
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
}

.sc-perf::before {
    left: -5px;
}

.sc-perf::after {
    right: -5px;
}

.sc-perf__line {
    flex: 1;
    display: block;
    border-top: 1px dashed rgba(100, 210, 255, 0.3);
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
    color: rgba(210, 240, 255, 0.38);
}

.sc-total__value {
    font-size: 1rem;
    font-weight: 700;
    color: rgba(100, 210, 255, 0.97);
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

.chat-system-card__who {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.45);
    margin: 0.2rem 0 0;
    font-weight: 600;
}

.chat-system-card__reason {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0.25rem 0 0;
    font-style: italic;
}

/* ── Support chat ─────────────────────────────────────── */
.chat-support-icon {
    font-size: 1rem;
    color: rgba(255, 178, 239, 0.85);
    line-height: 1;
}

.chat-event-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.25);
    letter-spacing: 0.06em;
    font-family: "Courier New", monospace;
    text-align: center;
    padding: 0.2rem 0;
}

.chat-event-label--review {
    color: rgba(255, 140, 175, 0.6);
}

.chat-closed-banner {
    padding: 0.5rem 1rem;
    background: rgba(255, 150, 220, 0.12);
    border-top: 1px solid rgba(255, 178, 239, 0.2);
    font-size: 0.78rem;
    color: rgba(255, 200, 240, 0.65);
    letter-spacing: 0.04em;
    text-align: center;
    font-family: "Courier New", monospace;
}

/* ── Attach button ────────────────────────────────────── */
.chat-attach-btn {
    position: absolute;
    right: 1.5rem;
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
    transition:
        color 0.15s,
        background 0.15s;
    z-index: 1;
}

.chat-attach-btn:hover:not(:disabled) {
    color: rgba(255, 178, 239, 0.8);
    background: rgba(255, 178, 239, 0.12);
}

.chat-attach-btn:disabled {
    opacity: 0.25;
    cursor: not-allowed;
}

/* ── Image messages ───────────────────────────────────── */
.chat-msg__bubble--image {
    padding: 0.3rem;
    background: transparent !important;
    border-color: rgba(255, 178, 239, 0.2) !important;
}

.chat-msg__image {
    display: block;
    max-width: 240px;
    max-height: 300px;
    border-radius: 6px;
    object-fit: cover;
    cursor: pointer;
}

/* ── Mobile responsive ────────────────────────────────── */
.chat-main__back-btn {
    display: none;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    border-radius: 6px;
    color: rgba(255, 255, 255, 0.55);
    cursor: pointer;
    margin-right: 0.25rem;
    transition:
        color 0.15s,
        background 0.15s;
}

.chat-main__back-btn:hover {
    color: rgba(255, 255, 255, 0.9);
    background: rgba(255, 178, 239, 0.12);
}

@media (max-width: 767px) {
    .chat-panel {
        top: 1rem;
        left: 1rem;
        right: 1rem;
        bottom: 1rem;
        max-width: none;
        width: auto;
        border-radius: 16px;
        border: 1px solid rgba(255, 178, 239, 0.28);
        overflow: hidden;
    }

    .chat-sidebar {
        width: 100%;
        position: absolute;
        inset: 0;
        transform: translateX(0);
        transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .chat-sidebar--mobile-hidden {
        transform: translateX(-100%);
        pointer-events: none;
    }

    .chat-main {
        width: 100%;
        position: absolute;
        inset: 0;
        transform: translateX(100%);
        transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .chat-main:not(.chat-main--mobile-hidden) {
        transform: translateX(0);
    }

    .chat-main--mobile-hidden {
        pointer-events: none;
    }

    .chat-main__back-btn {
        display: flex;
    }

    .chat-main__header {
        justify-content: flex-start;
        gap: 1rem;
    }

    .chat-textarea {
        padding-right: 0.5rem;
    }

    .chat-char-count {
        right: 4rem;
    }

    .chat-input-wrap {
        padding: 0.8rem 0 calc(1.5rem + env(safe-area-inset-bottom, 0px));
    }

    .chat-messages-inner {
        padding-bottom: calc(210px + env(safe-area-inset-bottom, 0px));
    }
}

/* ── Search row (messages & orders) ──────────────────────── */
.search-row {
    display: flex;
    gap: 0.35rem;
    align-items: center;
}

.search-row .chat-search-input {
    flex: 1;
    min-width: 0;
}

.search-go-btn,
.search-clear-btn {
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition:
        background 0.15s,
        color 0.15s,
        border-color 0.15s;
    font-family: inherit;
}

.search-go-btn:hover {
    background: rgba(255, 178, 239, 0.12);
    border-color: rgba(255, 178, 239, 0.3);
    color: var(--color-base-1);
}

.search-clear-btn:hover {
    background: rgba(255, 80, 80, 0.08);
    border-color: rgba(255, 100, 100, 0.25);
    color: rgba(255, 130, 130, 0.85);
}

/* ── Skeleton — shared shimmer ────────────────────────────── */
.skel-pulse {
    background: rgba(255, 255, 255, 0.055);
    border-radius: 4px;
    position: relative;
    overflow: hidden;
}

.skel-pulse::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.07) 50%,
        transparent 100%
    );
    transform: translateX(-100%);
    animation: skel-slide 1.5s ease-in-out infinite;
    will-change: transform;
}

/* ── Order skeleton ───────────────────────────────────────── */
.order-skel {
    width: calc(100% - 1.25rem);
    margin: 0.75rem auto;
    border-radius: 7px;
    background: rgba(255, 255, 255, 0.022);
    box-shadow: inset 0 0 0 1px rgba(255, 178, 239, 0.08);
    padding: 0.7rem 0.85rem 0.65rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.order-skel__head {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.order-skel__avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    flex-shrink: 0;
}

.order-skel__lines {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.order-skel__name {
    height: 12px;
}

.order-skel__date {
    height: 10px;
}

.order-skel__badge {
    height: 20px;
    width: 62px;
    border-radius: 4px;
    flex-shrink: 0;
}

.order-skel__sep {
    height: 1px;
    background: rgba(255, 255, 255, 0.045);
}

.order-skel__foot {
    display: flex;
    justify-content: space-between;
}

.order-skel__count {
    height: 11px;
    width: 38%;
}

.order-skel__total {
    height: 11px;
    width: 26%;
}

/* ── Conversation skeleton ────────────────────────────────── */
.conv-skel {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.72rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.035);
}

.conv-skel__avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    flex-shrink: 0;
}

.conv-skel__info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.32rem;
    min-width: 0;
}

.conv-skel__name {
    height: 12px;
}

.conv-skel__preview {
    height: 10px;
}

.conv-skel__time {
    height: 10px;
    width: 30px;
    flex-shrink: 0;
}

@media (min-width: 768px) {
    .chat-panel {
        top: 76px;
        right: 16px;
        bottom: 16px;
        max-width: calc(100vw - 32px);
        border-radius: 8px;
        border: 1px solid rgba(255, 178, 239, 0.28);
        box-shadow: 0 12px 48px rgba(0, 0, 0, 0.85);
        overflow: hidden;
    }
    .slide-enter-from,
    .slide-leave-to {
        transform: translateX(calc(100% + 24px));
    }
}
</style>
