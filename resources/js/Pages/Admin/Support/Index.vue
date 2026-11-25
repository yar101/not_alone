<script setup>
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { ElMessage } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import UserPickerModal from '@/Components/Admin/UserPickerModal.vue';
import {
    ChatDotRound, Plus, Search, Paperclip, Check, Close,
    Lock, Unlock, User, TopRight, Picture, Loading
} from '@element-plus/icons-vue';

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
const convListEl     = ref(null);
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

    activeConv.value  = { ...conv, unread: 0 };
    messages.value    = [];
    hasMore.value     = false;
    loadingMsgs.value = true;
    updateConvInList(conv.id, { unread: 0 });

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

    subscribeGlobalEcho();
}

const globalEchoChannels = new Map();

function subscribeGlobalEcho() {
    conversations.value.forEach(conv => {
        if (conv.id === activeConv.value?.id) return;
        if (globalEchoChannels.has(conv.id)) return;
        if (!window.Echo) return;
        const ch = window.Echo.private('admin.support.' + conv.id)
            .listen('.message.sent', (data) => {
                if (data.sender_id !== null) {
                    updateConvInList(conv.id, {
                        unread: true,
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

function triggerFileInput() { fileInput.value?.click(); }

async function onFileSelected(e) {
    const file = e.target.files?.[0];
    if (!file || !activeConv.value) return;
    e.target.value = '';
    if (file.size > 5 * 1024 * 1024) { ElMessage.warning('Файл слишком большой. Максимум 5 МБ.'); return; }
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

async function startChatWith(user) {
    try {
        const res = await axios.post(route('admin.support.store'), { user_id: user.id });
        const existing = conversations.value.find(c => c.id === res.data.id);
        if (!existing) conversations.value.unshift(res.data);
        openConversation(existing ?? res.data);
        showNewChat.value = false;
    } catch (err) {
        ElMessage.error(err.response?.data?.message || 'Не удалось открыть диалог с пользователем');
    }
}

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
    chat_closed:    'Тикет закрыт',
    chat_opened:    'Тикет открыт',
    order_paid:     'Заказ оплачен',
    order_completed:'Заказ завершён',
    order_disputed: 'Открыт спор',
    order_cancelled:'Заказ отменён',
    order_accepted: 'Заказ принят',
};
</script>

<template>
    <div class="h-[calc(100vh-8rem)] flex rounded-xl border border-zinc-800 bg-zinc-950 overflow-hidden shadow-2xl">
        <!-- ── Left Sidebar: Conversations List ── -->
        <aside class="w-80 md:w-88 border-r border-zinc-800 flex flex-col bg-zinc-900/50">
            <!-- Header -->
            <div class="p-3.5 border-b border-zinc-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <el-icon class="text-violet-400"><ChatDotRound /></el-icon>
                    <span class="font-bold text-sm text-zinc-100">Тикеты поддержки</span>
                </div>
                <button
                    type="button"
                    class="w-7 h-7 rounded-lg border border-zinc-800 bg-zinc-900 text-zinc-300 hover:text-white hover:bg-zinc-800 transition flex items-center justify-center"
                    title="Создать тикет с пользователем"
                    @click="showNewChat = true"
                >
                    <el-icon :size="14"><Plus /></el-icon>
                </button>
            </div>

            <!-- Search & Filter Tabs -->
            <div class="p-2.5 border-b border-zinc-800 space-y-2">
                <div class="relative">
                    <el-icon class="absolute left-2.5 top-1/2 -translate-y-1/2 text-zinc-500"><Search /></el-icon>
                    <input
                        v-model="convSearch"
                        type="text"
                        placeholder="Поиск по имени или email..."
                        class="w-full h-8 pl-8 pr-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                        @input="onSearchInput"
                    />
                </div>

                <div class="grid grid-cols-3 gap-1 text-[11px]">
                    <button
                        type="button"
                        class="py-1 rounded-md text-center font-medium transition"
                        :class="convStatus === 'all' ? 'bg-zinc-800 text-white' : 'text-zinc-400 hover:text-zinc-200'"
                        @click="convStatus = 'all'"
                    >
                        Все
                    </button>
                    <button
                        type="button"
                        class="py-1 rounded-md text-center font-medium transition"
                        :class="convStatus === 'open' ? 'bg-zinc-800 text-emerald-400' : 'text-zinc-400 hover:text-zinc-200'"
                        @click="convStatus = 'open'"
                    >
                        Открытые
                    </button>
                    <button
                        type="button"
                        class="py-1 rounded-md text-center font-medium transition"
                        :class="convStatus === 'closed' ? 'bg-zinc-800 text-zinc-400' : 'text-zinc-500 hover:text-zinc-200'"
                        @click="convStatus = 'closed'"
                    >
                        Закрытые
                    </button>
                </div>
            </div>

            <!-- Conversations Scrollable Feed -->
            <div class="flex-1 overflow-y-auto divide-y divide-zinc-800/60" ref="convListEl">
                <div
                    v-for="conv in conversations"
                    :key="conv.id"
                    class="p-3 cursor-pointer transition flex items-start gap-3"
                    :class="activeConv?.id === conv.id ? 'bg-violet-950/20 border-l-2 border-violet-500' : 'hover:bg-zinc-800/40'"
                    @click="openConversation(conv)"
                >
                    <div class="relative shrink-0">
                        <img
                            v-if="conv.user?.avatar"
                            :src="conv.user.avatar"
                            class="w-9 h-9 rounded-full object-cover border border-zinc-700"
                        />
                        <div
                            v-else
                            class="w-9 h-9 rounded-full bg-zinc-800 text-zinc-300 font-bold flex items-center justify-center text-xs border border-zinc-700"
                        >
                            {{ conv.user?.name?.charAt(0) ?? '?' }}
                        </div>
                        <span
                            v-if="conv.unread"
                            class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-violet-500 ring-2 ring-zinc-950"
                        ></span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-0.5">
                            <span class="text-xs font-semibold text-zinc-200 truncate">{{ conv.user?.name ?? 'Пользователь' }}</span>
                            <span class="text-[10px] font-mono text-zinc-500 shrink-0">{{ fmtTime(conv.last_message?.created_at ?? conv.updated_at) }}</span>
                        </div>
                        <p class="text-[11px] text-zinc-400 truncate">
                            {{ conv.last_message?.body ?? 'Нет сообщений' }}
                        </p>
                    </div>

                    <div v-if="conv.closed_at" class="text-zinc-500 shrink-0 pt-0.5" title="Тикет закрыт">
                        <el-icon :size="12"><Lock /></el-icon>
                    </div>
                </div>

                <div v-if="!conversations.length && !loadingConvs" class="py-12 text-center text-xs text-zinc-500">
                    Тикетов не найдено
                </div>
                <div v-if="loadingConvs" class="py-4 text-center text-xs text-zinc-500">
                    Загрузка тикетов...
                </div>
                <div id="conv-sentinel" class="h-1"></div>
            </div>
        </aside>

        <!-- ── Right Main Chat Pane ── -->
        <main class="flex-1 flex flex-col bg-zinc-950">
            <template v-if="activeConv">
                <!-- Chat Header -->
                <div class="p-3.5 border-b border-zinc-800 flex items-center justify-between bg-zinc-900/30">
                    <div class="flex items-center gap-3">
                        <img
                            v-if="activeConv.user?.avatar"
                            :src="activeConv.user.avatar"
                            class="w-8 h-8 rounded-full object-cover border border-zinc-700"
                        />
                        <div v-else class="w-8 h-8 rounded-full bg-zinc-800 text-zinc-300 font-bold flex items-center justify-center text-xs">
                            {{ activeConv.user?.name?.charAt(0) ?? '?' }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-xs text-zinc-100">{{ activeConv.user?.name }}</span>
                                <AdminBadge v-if="activeConv.closed_at" variant="neutral" size="sm">Закрыт</AdminBadge>
                                <AdminBadge v-else variant="success" size="sm" dot>Открыт</AdminBadge>
                            </div>
                            <span class="text-[11px] text-zinc-500 font-mono">{{ activeConv.user?.email }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Link
                            v-if="activeConv.user?.id"
                            :href="route('admin.users.show', activeConv.user.id)"
                            target="_blank"
                            class="px-2.5 py-1 rounded-lg border border-zinc-800 bg-zinc-900 text-[11px] font-medium text-zinc-300 hover:text-white transition flex items-center gap-1"
                        >
                            <span>Профиль</span>
                            <el-icon :size="10"><TopRight /></el-icon>
                        </Link>
                        <AdminButton
                            v-if="!activeConv.closed_at"
                            variant="outline"
                            size="sm"
                            @click="closeChat"
                        >
                            <el-icon class="mr-1"><Lock /></el-icon>
                            Закрыть тикет
                        </AdminButton>
                        <AdminButton
                            v-else
                            variant="outline"
                            size="sm"
                            @click="openChat"
                        >
                            <el-icon class="mr-1"><Unlock /></el-icon>
                            Открыть заново
                        </AdminButton>
                    </div>
                </div>

                <!-- Messages Feed -->
                <div
                    ref="messagesEl"
                    class="flex-1 overflow-y-auto p-4 space-y-3"
                    @scroll="onMessagesScroll"
                >
                    <div v-if="hasMore" class="text-center py-2">
                        <button type="button" class="text-xs text-violet-400 hover:underline" @click="loadOlder">
                            Загрузить более ранние сообщения
                        </button>
                    </div>

                    <div
                        v-for="msg in messages"
                        :key="msg.id"
                        class="flex flex-col"
                        :class="isAdminMsg(msg) ? 'items-end' : (msg.type === 'system' ? 'items-center' : 'items-start')"
                    >
                        <!-- System notice -->
                        <div
                            v-if="msg.type === 'system'"
                            class="px-3 py-1 rounded-full bg-zinc-900 border border-zinc-800 text-[11px] text-zinc-400 my-1"
                        >
                            {{ SYSTEM_LABELS[msg.metadata?.event] || msg.body }}
                        </div>

                        <!-- Chat bubble -->
                        <div
                            v-else
                            class="max-w-[70%] rounded-xl p-3 text-xs space-y-1.5 shadow-sm"
                            :class="isAdminMsg(msg)
                                ? 'bg-violet-600 text-white rounded-br-none'
                                : 'bg-zinc-900 border border-zinc-800 text-zinc-200 rounded-bl-none'"
                        >
                            <div class="text-[10px] opacity-75 flex items-center justify-between gap-4 font-mono">
                                <span>{{ isAdminMsg(msg) ? 'Служба поддержки' : activeConv.user?.name }}</span>
                                <span>{{ fmtTime(msg.created_at) }}</span>
                            </div>

                            <div v-if="msg.type === 'image'" class="pt-1">
                                <img :src="msg.metadata?.image_url" class="rounded-lg max-h-64 object-cover" />
                            </div>
                            <div v-else class="leading-relaxed whitespace-pre-wrap">{{ msg.body }}</div>
                        </div>
                    </div>

                    <div ref="messagesEnd"></div>
                </div>

                <!-- Input Footer -->
                <div class="p-3 border-t border-zinc-800 bg-zinc-900/30">
                    <form @submit.prevent="sendMessage" class="flex items-center gap-2">
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="onFileSelected"
                        />
                        <button
                            type="button"
                            class="p-2 rounded-lg text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800 transition"
                            title="Прикрепить изображение"
                            :disabled="uploading"
                            @click="triggerFileInput"
                        >
                            <el-icon v-if="uploading" class="animate-spin"><Loading /></el-icon>
                            <el-icon v-else><Picture /></el-icon>
                        </button>

                        <input
                            v-model="newMessage"
                            type="text"
                            placeholder="Напишите ответ пользователю (Enter для отправки)..."
                            class="flex-1 h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                            :disabled="sending"
                            @keydown.enter="handleEnter"
                        />

                        <AdminButton variant="primary" size="sm" type="submit" :loading="sending" :disabled="!newMessage.trim()">
                            Отправить
                        </AdminButton>
                    </form>
                </div>
            </template>

            <!-- Empty State -->
            <div v-else class="flex-1 flex flex-col items-center justify-center text-center p-8 text-zinc-500">
                <el-icon :size="48" class="text-zinc-700 mb-3"><ChatDotRound /></el-icon>
                <h3 class="text-sm font-semibold text-zinc-300">Выберите тикет для общения</h3>
                <p class="text-xs text-zinc-500 mt-1 max-w-sm">
                    Слева представлен список обращений пользователей в службу поддержки платформы
                </p>
            </div>
        </main>

        <!-- User Picker Modal for starting new support chat -->
        <UserPickerModal
            v-model="showNewChat"
            @select="startChatWith"
        />
    </div>
</template>
