<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { formatMoney } from '@/Utils/money';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import {
    ChatDotRound, Search, User, TopRight, Close, Clock,
    Document, InfoFilled, Loading
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    conversations: Object, // Paginated conversations list
    total_count:   Number,
    filters:       Object, // Search/filter fields
});

// Search & Filter fields reactive state
const filterId = ref(props.filters?.id || '');
const filterOrderId = ref(props.filters?.order_id || '');
const filterType = ref(props.filters?.type || 'all');
const filterUserSearch = ref(props.filters?.user_search || '');

function submitFilter() {
    router.get(route('admin.conversations.index'), {
        id: filterId.value || undefined,
        order_id: filterOrderId.value || undefined,
        type: filterType.value === 'all' ? undefined : filterType.value,
        user_search: filterUserSearch.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
}

function resetFilter() {
    filterId.value = '';
    filterOrderId.value = '';
    filterType.value = 'all';
    filterUserSearch.value = '';
    submitFilter();
}

// Active conversation state
const activeConversation = ref(null);
const messages = ref([]);
const messagesLoading = ref(false);
const hasMoreMessages = ref(false);
const messagesContainer = ref(null);

async function selectConversation(conv) {
    activeConversation.value = conv;
    messages.value = [];
    hasMoreMessages.value = false;
    messagesLoading.value = true;

    try {
        const res = await axios.get(`/admin/conversations/${conv.id}/messages`);
        messages.value = res.data.messages;
        hasMoreMessages.value = res.data.has_more;
        scrollToBottom();
    } catch (e) {
        console.error('Ошибка при загрузке сообщений:', e);
    } finally {
        messagesLoading.value = false;
    }
}

function autoSelectIfRequested() {
    if (props.filters?.id || props.filters?.order_id) {
        const found = props.conversations?.data?.find(c => 
            (props.filters?.id && String(c.id) === String(props.filters.id)) ||
            (props.filters?.order_id && String(c.order_id) === String(props.filters.order_id))
        ) || props.conversations?.data?.[0];
        if (found && (!activeConversation.value || activeConversation.value.id !== found.id)) {
            selectConversation(found);
        }
    }
}

onMounted(() => {
    autoSelectIfRequested();
});

watch(() => props.conversations?.data, () => {
    if (!activeConversation.value && (props.filters?.id || props.filters?.order_id)) {
        autoSelectIfRequested();
    }
});

async function loadMoreMessages() {
    if (messagesLoading.value || !hasMoreMessages.value || !activeConversation.value) return;

    messagesLoading.value = true;
    const firstMsgId = messages.value[0]?.id;

    try {
        const res = await axios.get(`/admin/conversations/${activeConversation.value.id}/messages`, {
            params: { before_id: firstMsgId },
        });

        const container = messagesContainer.value;
        const previousScrollHeight = container ? container.scrollHeight : 0;

        messages.value = [...res.data.messages, ...messages.value];
        hasMoreMessages.value = res.data.has_more;

        await nextTick();
        if (container) {
            container.scrollTop = container.scrollHeight - previousScrollHeight;
        }
    } catch (e) {
        console.error('Ошибка при загрузке более старых сообщений:', e);
    } finally {
        messagesLoading.value = false;
    }
}

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
}

function formatTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function isSystemMessage(msg) {
    return !msg.sender_id || (msg.type && msg.type !== 'user' && msg.type !== 'image');
}

const page = usePage();
const feePercent = computed(() => Number(page.props.customer_service_fee_percent ?? 5));

function getSystemText(msg) {
    const event = msg.metadata?.event ?? msg.type;
    const meta = msg.metadata ?? {};
    switch (event) {
        case 'order_created':
            return 'Оформлен новый заказ';
        case 'item_added':
            return 'В заказ добавлены новые услуги';
        case 'order_accepted':
            return 'Айдол принял предложение и готов выполнить заказ';
        case 'order_paid':
            return meta.by_admin ? 'Оплата заказа подтверждена администратором' : 'Заказ оплачен заказчиком';
        case 'order_cancelled':
            return 'Заказ отменён' + (meta.cancel_reason ? `: ${meta.cancel_reason}` : '');
        case 'order_completed':
            return 'Заказ завершён и закрыт';
        case 'order_auto_completed':
            return 'Заказ завершён автоматически по истечении времени';
        case 'completion_confirmed_by_idol':
            return `Айдол ${meta.actor_name || ''} подтвердил выполнение заказа`;
        case 'completion_confirmed_by_customer':
            return `Клиент ${meta.actor_name || ''} подтвердил выполнение заказа`;
        case 'review_submitted':
            return 'Клиент оставил отзыв о заказе';
        case 'order_disputed':
            return 'Был открыт спор по заказу';
        case 'chat_closed':
            return 'Чат закрыт';
        case 'chat_opened':
            return 'Чат открыт';
        default:
            return msg.body || 'Системное событие';
    }
}
</script>

<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-100 flex items-center gap-2.5">
                    <el-icon class="text-violet-400"><ChatDotRound /></el-icon>
                    <span>Аудит переписок пользователей</span>
                </h1>
                <p class="text-xs text-zinc-400 mt-0.5">
                    Всего диалогов: <span class="font-mono text-zinc-200">{{ (conversations?.total ?? total_count ?? conversations?.data?.length ?? 0).toLocaleString() }}</span> • Мониторинг коммуникаций между клиентами и айдолами
                </p>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="p-3.5 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm flex flex-col md:flex-row items-stretch md:items-center gap-2.5">
            <div class="relative flex-1">
                <el-icon class="absolute left-2.5 top-1/2 -translate-y-1/2 text-zinc-500"><Search /></el-icon>
                <input
                    v-model="filterUserSearch"
                    type="text"
                    placeholder="Поиск по имени или email участника..."
                    class="w-full h-8 pl-8 pr-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                    @keyup.enter="submitFilter"
                />
            </div>

            <div class="flex items-center gap-2">
                <input
                    v-model="filterOrderId"
                    type="text"
                    placeholder="Заказ #"
                    class="w-24 h-8 px-2.5 rounded-lg border border-zinc-800 bg-zinc-950 text-xs font-mono text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                    @keyup.enter="submitFilter"
                />
                <select
                    v-model="filterType"
                    class="h-8 px-2.5 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-200 focus:outline-none focus:border-violet-500"
                    @change="submitFilter"
                >
                    <option value="all">Все диалоги</option>
                    <option value="order">Только по заказам</option>
                    <option value="direct">Прямые чаты</option>
                </select>

                <AdminButton variant="primary" size="sm" @click="submitFilter">
                    Найти
                </AdminButton>
                <AdminButton variant="ghost" size="sm" @click="resetFilter">
                    Сбросить
                </AdminButton>
            </div>
        </div>

        <!-- 2-Pane Split: Left Conversation List, Right Chat History -->
        <div class="h-[calc(100vh-14rem)] flex rounded-xl border border-zinc-800 bg-zinc-950 overflow-hidden shadow-2xl">
            <!-- Left List -->
            <aside class="w-80 md:w-96 border-r border-zinc-800 flex flex-col bg-zinc-900/40">
                <div class="flex-1 overflow-y-auto divide-y divide-zinc-800/60">
                    <div
                        v-for="conv in conversations.data"
                        :key="conv.id"
                        class="p-3 cursor-pointer transition flex items-start gap-3"
                        :class="activeConversation?.id === conv.id ? 'bg-violet-950/20 border-l-2 border-violet-500' : 'hover:bg-zinc-800/40'"
                        @click="selectConversation(conv)"
                    >
                        <div class="w-9 h-9 rounded-full bg-zinc-800 text-zinc-300 font-bold flex items-center justify-center text-xs shrink-0 border border-zinc-700">
                            {{ conv.participants?.[0]?.name?.charAt(0) ?? '?' }}
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-0.5">
                                <div class="text-xs font-semibold text-zinc-200 truncate">
                                    {{ conv.participants?.map(p => p.name).join(' ↔ ') }}
                                </div>
                                <span class="text-[10px] font-mono text-zinc-500 shrink-0">{{ formatTime(conv.last_message?.created_at || conv.updated_at) }}</span>
                            </div>

                            <p class="text-[11px] text-zinc-400 truncate">
                                {{ conv.last_message?.body || 'Нет сообщений' }}
                            </p>

                            <div v-if="conv.order_id" class="mt-1 flex items-center gap-1.5">
                                <AdminBadge variant="primary" size="sm">Заказ #{{ conv.order_id }}</AdminBadge>
                            </div>
                        </div>
                    </div>

                    <div v-if="!conversations.data?.length" class="py-12 text-center text-xs text-zinc-500">
                        Переписок не найдено
                    </div>
                </div>

                <div class="p-2 border-t border-zinc-800">
                    <AdminPagination :pagination="conversations" />
                </div>
            </aside>

            <!-- Right Message Stream -->
            <main class="flex-1 flex flex-col bg-zinc-950">
                <template v-if="activeConversation">
                    <!-- Top Info Bar -->
                    <div class="p-3.5 border-b border-zinc-800 flex items-center justify-between bg-zinc-900/30">
                        <div class="flex items-center gap-2">
                            <div class="text-xs font-semibold text-zinc-200">
                                {{ activeConversation.participants?.map(p => `${p.name} (${p.email})`).join(' • ') }}
                            </div>
                        </div>

                        <div v-if="activeConversation.order_id" class="flex items-center gap-2">
                            <Link
                                :href="route('admin.orders.show', activeConversation.order_id)"
                                class="px-2.5 py-1 rounded-lg border border-zinc-800 bg-zinc-900 text-[11px] font-medium text-violet-300 hover:text-white transition flex items-center gap-1"
                            >
                                <span>Заказ #{{ activeConversation.order_id }}</span>
                                <el-icon :size="10"><TopRight /></el-icon>
                            </Link>
                        </div>
                    </div>

                    <!-- Messages Stream -->
                    <div
                        ref="messagesContainer"
                        class="flex-1 overflow-y-auto p-4 space-y-3"
                    >
                        <div v-if="hasMoreMessages" class="text-center py-2">
                            <button
                                type="button"
                                class="text-xs text-violet-400 hover:underline"
                                @click="loadMoreMessages"
                            >
                                Загрузить более ранние сообщения
                            </button>
                        </div>

                        <div
                            v-for="msg in messages"
                            :key="msg.id"
                            class="flex flex-col"
                            :class="msg.sender_id === activeConversation.participants?.[0]?.id ? 'items-start' : (isSystemMessage(msg) ? 'items-center' : 'items-end')"
                        >
                            <div
                                v-if="isSystemMessage(msg)"
                                class="px-3 py-1 rounded-full bg-zinc-900 border border-zinc-800 text-[11px] text-zinc-400 my-1"
                            >
                                {{ getSystemText(msg) }}
                            </div>

                            <div
                                v-else
                                class="max-w-[70%] rounded-xl p-3 text-xs space-y-1 shadow-sm"
                                :class="msg.sender_id === activeConversation.participants?.[0]?.id
                                    ? 'bg-zinc-900 border border-zinc-800 text-zinc-200 rounded-bl-none'
                                    : 'bg-violet-950/40 border border-violet-800/40 text-violet-100 rounded-br-none'"
                            >
                                <div class="text-[10px] text-zinc-500 flex items-center justify-between gap-4 font-mono">
                                    <span>{{ msg.sender_name }}</span>
                                    <span>{{ formatTime(msg.created_at) }}</span>
                                </div>
                                <div v-if="msg.type === 'image'" class="pt-1">
                                    <img :src="msg.metadata?.image_url" class="rounded-lg max-h-64 object-cover" />
                                </div>
                                <div v-else class="leading-relaxed whitespace-pre-wrap">{{ msg.body }}</div>
                            </div>
                        </div>
                    </div>
                </template>

                <div v-else class="flex-1 flex flex-col items-center justify-center text-center p-8 text-zinc-500">
                    <el-icon :size="48" class="text-zinc-700 mb-3"><ChatDotRound /></el-icon>
                    <h3 class="text-sm font-semibold text-zinc-300">Выберите диалог для просмотра</h3>
                    <p class="text-xs text-zinc-500 mt-1 max-w-sm">
                        Слева представлен список всех переписок между клиентами и айдолами
                    </p>
                </div>
            </main>
        </div>
    </div>
</template>
