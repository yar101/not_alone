<script setup>
import { ref, computed, nextTick } from 'vue';
import axios from 'axios';
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminDrawer from '@/Components/Admin/UI/AdminDrawer.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import { formatMoney } from '@/Utils/money';
import {
    Warning, Check, Close, ChatDotRound, User, TopRight,
    Document, InfoFilled, Loading
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    disputes:      Object,
    status_filter: String,
    counts:        Object,
});

const selected    = ref(null);
const isDrawerOpen = ref(false);
const decision    = ref('');
const adminNote   = ref('');
const submitting  = ref(false);

// Chat panel inside drawer
const chatMessages = ref([]);
const chatLoading  = ref(false);
const chatHasMore  = ref(false);
const chatOpen     = ref(false);
const chatConvId   = ref(null);
const resolveError = ref('');

function openDrawer(dispute) {
    selected.value     = dispute;
    decision.value     = '';
    adminNote.value    = '';
    resolveError.value = '';
    chatOpen.value     = false;
    chatMessages.value = [];
    chatConvId.value   = null;
    isDrawerOpen.value = true;
}

function closeDrawer() {
    isDrawerOpen.value = false;
    selected.value     = null;
    resolveError.value = '';
    chatOpen.value     = false;
    chatMessages.value = [];
}

function setFilter(val) {
    router.get(route('admin.disputes.index'), { status: val }, { preserveState: true, preserveScroll: true });
}

function submitResolve() {
    if (!decision.value || submitting.value) return;
    submitting.value = true;
    resolveError.value = '';
    router.patch(
        route('admin.disputes.resolve', selected.value.id),
        { decision: decision.value, admin_note: adminNote.value },
        {
            onFinish:  () => { submitting.value = false; },
            onSuccess: () => { closeDrawer(); },
            onError:   (errors) => {
                resolveError.value = Object.values(errors).flat()[0] || 'Произошла ошибка при сохранении решения';
            },
        }
    );
}

async function openChat(conversationId) {
    if (chatLoading.value) return;
    chatConvId.value   = conversationId;
    chatOpen.value     = true;
    chatMessages.value = [];
    chatHasMore.value  = false;
    chatLoading.value  = true;
    try {
        const res = await axios.get(`/admin/conversations/${conversationId}/messages`);
        chatMessages.value = res.data.messages;
        chatHasMore.value  = res.data.has_more;
    } finally {
        chatLoading.value = false;
    }
}

async function loadMore() {
    if (chatLoading.value || !chatHasMore.value) return;
    chatLoading.value = true;
    const firstId = chatMessages.value[0]?.id;
    try {
        const res = await axios.get(`/admin/conversations/${chatConvId.value}/messages`, {
            params: { before_id: firstId },
        });
        chatMessages.value = [...res.data.messages, ...chatMessages.value];
        chatHasMore.value  = res.data.has_more;
    } finally {
        chatLoading.value = false;
    }
}

function isCustomer(msg) {
    return selected.value && msg.sender_id === selected.value.order.customer.id;
}

function isSystem(msg) {
    return !msg.sender_id || (msg.type && msg.type !== 'user');
}

function fmtTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('ru-RU', {
        day: '2-digit', month: '2-digit', year: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function getDisputeBadgeVariant(status) {
    switch (status) {
        case 'open':
            return 'warning';
        case 'approved':
            return 'success';
        case 'rejected':
            return 'danger';
        default:
            return 'neutral';
    }
}

const STATUS_LABELS = { open: 'Открыт', approved: 'Удовлетворён', rejected: 'Отклонён' };
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-100 flex items-center gap-2.5">
                    <el-icon class="text-amber-400"><Warning /></el-icon>
                    <span>Арбитраж и споры по заказам</span>
                </h1>
                <p class="text-xs text-zinc-400 mt-0.5">
                    Разрешение финансовых претензий клиентов и айдолов до закрытия сделки (ст. 720 ГК РФ)
                </p>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
            <button
                v-for="tab in [
                    { key: 'open',     label: 'Открытые',        count: counts.open },
                    { key: 'approved', label: 'Удовлетворённые', count: counts.approved },
                    { key: 'rejected', label: 'Отклонённые',     count: counts.rejected },
                    { key: 'all',      label: 'Все',             count: counts.all ?? (counts.open + counts.approved + counts.rejected) },
                ]"
                :key="tab.key"
                class="px-3 py-1.5 rounded-lg border text-xs font-medium transition flex items-center gap-2"
                :class="(status_filter || 'open') === tab.key
                    ? 'border-violet-500/50 bg-violet-500/15 text-violet-300'
                    : 'border-zinc-800 bg-zinc-900/60 text-zinc-400 hover:text-zinc-200'"
                @click="setFilter(tab.key)"
            >
                <span>{{ tab.label }}</span>
                <span
                    class="px-1.5 py-0.2 rounded text-[10px] font-mono"
                    :class="(status_filter || 'open') === tab.key ? 'bg-violet-500/30 text-violet-200' : 'bg-zinc-800 text-zinc-500'"
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>

        <!-- Disputes Table -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 overflow-hidden backdrop-blur-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40 text-zinc-400">
                            <th class="py-3 px-4 font-medium">Заказ</th>
                            <th class="py-3 px-4 font-medium">Заказчик</th>
                            <th class="py-3 px-4 font-medium">Айдол</th>
                            <th class="py-3 px-4 font-medium">Причина спора</th>
                            <th class="py-3 px-4 font-medium">Статус</th>
                            <th class="py-3 px-4 font-medium text-right">Сумма</th>
                            <th class="py-3 px-4 font-medium">Дата создания</th>
                            <th class="py-3 px-4 font-medium">Решение</th>
                            <th class="py-3 px-4 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                        <tr v-if="!disputes.data?.length">
                            <td colspan="9" class="py-12 text-center text-zinc-500">
                                Споров в выбранном статусе нет
                            </td>
                        </tr>
                        <tr
                            v-for="d in disputes.data"
                            :key="d.id"
                            class="hover:bg-zinc-800/30 transition cursor-pointer"
                            @click="openDrawer(d)"
                        >
                            <!-- Order Link -->
                            <td class="py-3 px-4 font-mono font-medium text-violet-400" @click.stop>
                                <Link :href="route('admin.orders.show', d.order_id)" class="hover:underline">
                                    #{{ d.order_id }}
                                </Link>
                            </td>

                            <!-- Customer -->
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2">
                                    <img
                                        v-if="d.order.customer.avatar"
                                        :src="d.order.customer.avatar"
                                        class="w-6 h-6 rounded-full object-cover border border-zinc-700"
                                    />
                                    <div v-else class="w-6 h-6 rounded-full bg-zinc-800 text-zinc-400 flex items-center justify-center font-bold text-[10px]">
                                        {{ d.order.customer.name?.charAt(0).toUpperCase() }}
                                    </div>
                                    <span class="text-zinc-200 font-medium truncate max-w-[120px]">{{ d.order.customer.name }}</span>
                                </div>
                            </td>

                            <!-- Idol -->
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2">
                                    <img
                                        v-if="d.order.idol.avatar"
                                        :src="d.order.idol.avatar"
                                        class="w-6 h-6 rounded-full object-cover border border-pink-500/40"
                                    />
                                    <div v-else class="w-6 h-6 rounded-full bg-pink-900/40 text-pink-300 flex items-center justify-center font-bold text-[10px]">
                                        {{ d.order.idol.name?.charAt(0).toUpperCase() }}
                                    </div>
                                    <span class="text-zinc-200 font-medium truncate max-w-[120px]">{{ d.order.idol.name }}</span>
                                </div>
                            </td>

                            <!-- Reason -->
                            <td class="py-3 px-4 max-w-[180px] truncate text-zinc-300" :title="d.reason">
                                {{ d.reason }}
                            </td>

                            <!-- Status -->
                            <td class="py-3 px-4">
                                <AdminBadge :variant="getDisputeBadgeVariant(d.status)" size="sm">
                                    {{ STATUS_LABELS[d.status] || d.status }}
                                </AdminBadge>
                            </td>

                            <!-- Amount -->
                            <td class="py-3 px-4 text-right font-mono font-bold text-zinc-100 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 justify-end">
                                    <span>{{ formatMoney(d.order.total) }}</span>
                                    <CoinIcon color="blue" size="xs" />
                                </span>
                            </td>

                            <!-- Dates -->
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-500 whitespace-nowrap">
                                {{ d.created_at }}
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-500 whitespace-nowrap">
                                {{ d.resolved_at || '—' }}
                            </td>

                            <!-- Action -->
                            <td class="py-3 px-4 text-right" @click.stop>
                                <button
                                    class="text-violet-400 hover:text-violet-300 font-medium text-xs hover:underline"
                                    @click="openDrawer(d)"
                                >
                                    {{ d.status === 'open' ? 'Рассмотреть' : 'Детали' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4 border-t border-zinc-800">
                <AdminPagination :pagination="disputes" />
            </div>
        </div>

        <!-- ════════════ DRAWER: DISPUTE DETAILS & RESOLUTION ════════════ -->
        <AdminDrawer
            :show="isDrawerOpen"
            :title="`Спор по заказу #${selected?.order_id || ''}`"
            :subtitle="selected ? `Подан ${selected.created_at}` : ''"
            width="640px"
            @close="closeDrawer"
        >
            <div v-if="selected" class="space-y-5 text-xs">
                <!-- Summary Card -->
                <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-400">Статус арбитража:</span>
                        <AdminBadge :variant="getDisputeBadgeVariant(selected.status)" size="md">
                            {{ STATUS_LABELS[selected.status] || selected.status }}
                        </AdminBadge>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-3 border-t border-zinc-800">
                        <div>
                            <span class="text-zinc-500 text-[11px]">Заказчик (Клиент):</span>
                            <div class="font-semibold text-zinc-200 mt-0.5">{{ selected.order.customer.name }}</div>
                        </div>
                        <div>
                            <span class="text-zinc-500 text-[11px]">Исполнитель (Айдол):</span>
                            <div class="font-semibold text-pink-300 mt-0.5">{{ selected.order.idol.name }}</div>
                        </div>
                    </div>

                    <div class="flex items-baseline justify-between pt-3 border-t border-zinc-800">
                        <span class="text-zinc-400">Сумма в эскроу:</span>
                        <div class="text-base font-bold font-mono text-zinc-100 flex items-center gap-1.5">
                            <span>{{ formatMoney(selected.order.total) }}</span>
                            <CoinIcon color="blue" size="xs" />
                            <span class="text-xs font-normal text-zinc-500">({{ formatMoney(selected.order.total) }} ₽)</span>
                        </div>
                    </div>
                </div>

                <!-- Claim Details -->
                <div class="p-4 rounded-xl border border-amber-900/40 bg-amber-950/20 space-y-2">
                    <div class="text-amber-400 font-semibold text-xs flex items-center gap-1.5">
                        <el-icon><Warning /></el-icon>
                        <span>Претензия заказчика</span>
                    </div>
                    <div class="text-zinc-200 font-medium">
                        Причина: <span class="text-white">{{ selected.reason }}</span>
                    </div>
                    <div v-if="selected.details" class="p-3 rounded-lg bg-zinc-950 border border-zinc-800 text-zinc-300 leading-relaxed">
                        {{ selected.details }}
                    </div>
                </div>

                <!-- Chat Toggle & Inline Messages -->
                <div v-if="selected.order.conversation_id" class="space-y-3">
                    <AdminButton
                        variant="outline"
                        size="sm"
                        class="w-full"
                        @click="chatOpen ? (chatOpen = false) : openChat(selected.order.conversation_id)"
                    >
                        <el-icon class="mr-1.5"><ChatDotRound /></el-icon>
                        <span>{{ chatOpen ? 'Скрыть переписку по заказу' : 'Посмотреть переписку между клиентом и айдолом' }}</span>
                    </AdminButton>

                    <div v-if="chatOpen" class="rounded-xl border border-zinc-800 bg-zinc-950 p-3 space-y-2">
                        <div v-if="chatLoading && !chatMessages.length" class="py-6 text-center text-zinc-500">
                            <el-icon class="animate-spin"><Loading /></el-icon>
                            <span class="ml-2">Загрузка сообщений...</span>
                        </div>
                        <div v-else class="space-y-2 max-h-64 overflow-y-auto pr-1 text-xs">
                            <button
                                v-if="chatHasMore"
                                type="button"
                                class="w-full py-1 text-center text-[11px] text-violet-400 hover:underline"
                                @click="loadMore"
                            >
                                Загрузить более ранние сообщения
                            </button>

                            <div
                                v-for="msg in chatMessages"
                                :key="msg.id"
                                class="flex flex-col"
                                :class="isCustomer(msg) ? 'items-end' : 'items-start'"
                            >
                                <div
                                    class="max-w-[80%] rounded-lg p-2.5 space-y-1"
                                    :class="isCustomer(msg) ? 'bg-violet-600/30 border border-violet-500/40 text-violet-100' : 'bg-zinc-900 border border-zinc-800 text-zinc-200'"
                                >
                                    <div class="text-[10px] text-zinc-400 flex items-center justify-between gap-3">
                                        <span>{{ msg.sender_name || (isCustomer(msg) ? selected.order.customer.name : selected.order.idol.name) }}</span>
                                        <span>{{ fmtTime(msg.created_at) }}</span>
                                    </div>
                                    <div class="leading-relaxed">{{ msg.body }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Existing Decision Note if not open -->
                <div v-if="selected.status !== 'open' && selected.admin_note" class="p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 space-y-1.5">
                    <div class="text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">Решение арбитража</div>
                    <p class="text-zinc-200 leading-relaxed">{{ selected.admin_note }}</p>
                </div>

                <!-- Resolution Panel (if status === 'open') -->
                <div v-if="selected.status === 'open'" class="p-4 rounded-xl border border-violet-800/40 bg-violet-950/20 space-y-4">
                    <div class="text-xs font-semibold text-violet-300">Вынесение вердикта арбитража</div>

                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            class="p-3 rounded-lg border text-left transition"
                            :class="decision === 'approved'
                                ? 'border-emerald-500 bg-emerald-500/20 text-emerald-300'
                                : 'border-zinc-800 bg-zinc-950 text-zinc-400 hover:text-zinc-200'"
                            @click="decision = 'approved'"
                        >
                            <div class="font-semibold text-xs">Удовлетворить спор</div>
                            <div class="text-[10px] text-zinc-500 mt-1">100% возврат клиенту (услуги + сбор 5%), штраф айдолу -10 баллов</div>
                        </button>

                        <button
                            type="button"
                            class="p-3 rounded-lg border text-left transition"
                            :class="decision === 'rejected'
                                ? 'border-rose-500 bg-rose-500/20 text-rose-300'
                                : 'border-zinc-800 bg-zinc-950 text-zinc-400 hover:text-zinc-200'"
                            @click="decision = 'rejected'"
                        >
                            <div class="font-semibold text-xs">Отклонить спор</div>
                            <div class="text-[10px] text-zinc-500 mt-1">Сделка закрывается (Closed), выплата 88% айдолу, чек в ФНС</div>
                        </button>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-medium text-zinc-300">Мотивировочная часть решения (для обеих сторон) *</label>
                        <textarea
                            v-model="adminNote"
                            rows="3"
                            class="w-full px-3 py-2 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                            placeholder="Опишите основания принятого решения..."
                        ></textarea>
                    </div>

                    <p v-if="resolveError" class="text-xs text-rose-400">{{ resolveError }}</p>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <AdminButton variant="outline" size="sm" @click="closeDrawer">
                        Закрыть
                    </AdminButton>
                    <AdminButton
                        v-if="selected?.status === 'open'"
                        :variant="decision === 'approved' ? 'success' : 'destructive'"
                        size="sm"
                        :disabled="!decision || submitting"
                        :loading="submitting"
                        @click="submitResolve"
                    >
                        Зафиксировать решение
                    </AdminButton>
                </div>
            </template>
        </AdminDrawer>
    </div>
</template>
