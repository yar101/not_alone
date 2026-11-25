<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminModal from '@/Components/Admin/AdminModal.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import { ElNotification } from 'element-plus';
import { formatMoney } from '@/Utils/money';
import {
    ArrowLeft, ChatDotRound, User, Coin, Clock, Check,
    CircleClose, Warning, Document, EditPen, InfoFilled
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    statuses: {
        type: Array,
        required: true,
    },
    status_transitions: {
        type: Object,
        default: () => ({}),
    },
});

// ─── Status change modal ──────────────────────────────────────────────────────
const showStatusModal = ref(false);
const newStatus = ref(props.order.status);
const adminNote = ref('');
const isSubmitting = ref(false);

const availableStatuses = computed(() => {
    return props.status_transitions?.[props.order.status] ?? [];
});

function openStatusModal() {
    newStatus.value = availableStatuses.value[0]?.value || '';
    adminNote.value = '';
    showStatusModal.value = true;
}

function submitStatusChange() {
    if (isSubmitting.value) return;
    isSubmitting.value = true;

    router.patch(
        route('admin.orders.status', props.order.id),
        {
            status: newStatus.value,
            admin_note: adminNote.value || undefined,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showStatusModal.value = false;
                ElNotification.success({
                    title: 'Статус изменен',
                    message: `Заказ #${props.order.id} успешно переведен в новый статус`,
                });
            },
            onError: (err) => {
                ElNotification.error({
                    title: 'Ошибка смены статуса',
                    message: Object.values(err)[0] || 'Не удалось обновить статус заказа',
                });
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        }
    );
}

function getTxTypeLabel(type) {
    const labels = {
        deposit: 'Пополнение',
        withdrawal: 'Вывод',
        order_hold: 'Холдирование',
        order_payout: 'Выплата айдолу',
        order_refund: 'Возврат клиенту',
        order_hold_release: 'Снятие холда',
        order_hold_cancelled: 'Отмена холда',
        platform_fee: 'Комиссия сервиса',
        admin_adjustment: 'Корректировка',
        order_clawback: 'Удержание',
        pack_purchase: 'Покупка пака',
        pack_sale: 'Продажа пака',
    };
    return labels[type] || type;
}

function getBadgeVariant(status) {
    switch (status) {
        case 'completed':
        case 'closed':
            return 'success';
        case 'paid':
        case 'in_progress':
            return 'primary';
        case 'disputed':
            return 'warning';
        case 'cancelled':
        case 'refunded':
            return 'danger';
        default:
            return 'neutral';
    }
}
</script>

<template>
    <div class="space-y-6">
        <!-- Top Navigation & Order Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-5">
            <div class="flex items-center gap-3">
                <Link
                    :href="route('admin.orders.index')"
                    class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition"
                    title="Назад к заказам"
                >
                    <el-icon :size="16"><ArrowLeft /></el-icon>
                </Link>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-bold text-zinc-100 tracking-tight">Заказ #{{ order.id }}</h1>
                        <AdminBadge :variant="getBadgeVariant(order.status)" dot size="md">
                            {{ order.status_label }}
                        </AdminBadge>
                    </div>
                    <p class="text-xs text-zinc-400 mt-0.5">
                        Создан {{ order.created_at }} • Номер в системе: <span class="font-mono text-zinc-300">#{{ order.id }}</span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Link
                    v-if="order.conversation_id"
                    :href="route('admin.conversations.index', { id: order.conversation_id, order_id: order.id })"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-xs font-medium text-zinc-300 hover:text-zinc-100 hover:bg-zinc-800 transition"
                >
                    <el-icon><ChatDotRound /></el-icon>
                    Чат по заказу
                </Link>
                <AdminButton variant="outline" size="sm" @click="openStatusModal">
                    <el-icon class="mr-1.5"><EditPen /></el-icon>
                    Сменить статус
                </AdminButton>
            </div>
        </div>

        <!-- 2-Column Main Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Main Column (2 cols) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Order Items Card -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 backdrop-blur-sm">
                    <div class="flex items-center justify-between mb-4 border-b border-zinc-800/80 pb-3">
                        <h2 class="text-sm font-semibold text-zinc-100 flex items-center gap-2">
                            <span>Состав заказа</span>
                            <span class="text-xs font-normal text-zinc-500">({{ order.items.length }} поз.)</span>
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-zinc-400 border-b border-zinc-800 pb-2">
                                    <th class="py-2 font-medium">Услуга</th>
                                    <th class="py-2 font-medium">Категория</th>
                                    <th class="py-2 font-medium">Ед. времени</th>
                                    <th class="py-2 font-medium text-right">Цена</th>
                                    <th class="py-2 font-medium text-center">Кол-во</th>
                                    <th class="py-2 font-medium text-right">Итого</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                                <tr v-for="item in order.items" :key="item.id">
                                    <td class="py-3 pr-3">
                                        <div class="font-medium text-zinc-200">{{ item.title }}</div>
                                        <div v-if="item.options && Object.keys(item.options).length" class="mt-1 flex flex-wrap gap-1">
                                            <span
                                                v-for="(val, key) in item.options"
                                                :key="key"
                                                class="px-1.5 py-0.5 rounded bg-zinc-800/80 text-[10px] text-zinc-400 font-mono"
                                            >
                                                {{ key }}: {{ val }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3 text-zinc-400">{{ item.category_name || '—' }}</td>
                                    <td class="py-3 text-zinc-400">{{ item.time_unit || '—' }}</td>
                                    <td class="py-3 text-right font-mono">
                                        <span class="inline-flex items-center gap-1 justify-end">
                                            <span>{{ formatMoney(item.price) }}</span>
                                            <CoinIcon color="blue" size="xs" />
                                        </span>
                                    </td>
                                    <td class="py-3 text-center font-mono">{{ item.quantity }}</td>
                                    <td class="py-3 text-right font-mono font-medium text-zinc-100">
                                        <span class="inline-flex items-center gap-1 justify-end">
                                            <span>{{ formatMoney(item.subtotal) }}</span>
                                            <CoinIcon color="blue" size="xs" />
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-zinc-800 text-zinc-200 font-medium">
                                    <td colspan="5" class="py-3 text-right pr-4 text-zinc-400">Сумма услуг:</td>
                                    <td class="py-3 text-right font-mono font-bold text-sm text-zinc-100">
                                        <div class="inline-flex items-center gap-1 justify-end">
                                            <span>{{ formatMoney(order.total) }}</span>
                                            <CoinIcon color="blue" size="xs" />
                                        </div>
                                        <div class="text-[11px] text-zinc-500 font-normal">({{ formatMoney(order.total) }} ₽)</div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Financial Breakdown Card -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 backdrop-blur-sm">
                    <div class="flex items-center justify-between mb-4 border-b border-zinc-800/80 pb-3">
                        <h2 class="text-sm font-semibold text-zinc-100">Финансовая модель сделки (BaaS / Номинальный счёт)</h2>
                        <span class="text-[11px] text-zinc-500 font-mono">1 🪙 = 1.00 ₽</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="p-3 rounded-lg border border-zinc-800/80 bg-zinc-950/40">
                            <div class="text-[11px] text-zinc-400 mb-1">Сумма услуг (100%)</div>
                            <div class="flex items-center gap-1.5 text-base font-bold font-mono text-zinc-100">
                                <span>{{ formatMoney(order.total) }}</span>
                                <CoinIcon color="blue" size="xs" />
                            </div>
                            <div class="text-[10px] text-zinc-500 mt-1">Оплачивается клиентом в эскроу</div>
                        </div>

                        <div class="p-3 rounded-lg border border-zinc-800/80 bg-zinc-950/40">
                            <div class="text-[11px] text-zinc-400 mb-1">Сервисный сбор клиента (5%)</div>
                            <div class="flex items-center gap-1.5 text-base font-bold font-mono text-violet-400">
                                <span>{{ formatMoney(order.total * 0.05) }}</span>
                                <CoinIcon color="blue" size="xs" />
                            </div>
                            <div class="text-[10px] text-zinc-500 mt-1">Чек онлайн-кассы 54-ФЗ</div>
                        </div>

                        <div class="p-3 rounded-lg border border-zinc-800/80 bg-zinc-950/40">
                            <div class="text-[11px] text-zinc-400 mb-1">Чистый заработок айдола (88%)</div>
                            <div class="flex items-center gap-1.5 text-base font-bold font-mono text-pink-400">
                                <span>{{ formatMoney(order.total * 0.88) }}</span>
                                <CoinIcon color="pink" size="xs" />
                            </div>
                            <div class="text-[10px] text-zinc-500 mt-1">Чек самозанятого 422-ФЗ при Closed</div>
                        </div>
                    </div>
                </div>

                <!-- Financial Transactions Ledger -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 backdrop-blur-sm">
                    <div class="flex items-center justify-between mb-4 border-b border-zinc-800/80 pb-3">
                        <h2 class="text-sm font-semibold text-zinc-100 flex items-center gap-2">
                            <span>Финансовые проводки по заказу</span>
                            <span class="text-xs font-normal text-zinc-500">({{ order.transactions.length }})</span>
                        </h2>
                    </div>

                    <div v-if="order.transactions.length === 0" class="py-6 text-center text-xs text-zinc-500">
                        Финансовых транзакций по данному заказу пока нет.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-zinc-400 border-b border-zinc-800 pb-2">
                                    <th class="py-2 font-medium">ID</th>
                                    <th class="py-2 font-medium">Пользователь</th>
                                    <th class="py-2 font-medium">Тип проводки</th>
                                    <th class="py-2 font-medium text-right">Сумма</th>
                                    <th class="py-2 font-medium text-right">Баланс после</th>
                                    <th class="py-2 font-medium">Дата</th>
                                    <th class="py-2 font-medium">Статус</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                                <tr v-for="tx in order.transactions" :key="tx.id">
                                    <td class="py-2.5 font-mono">
                                        <Link
                                            :href="route('admin.transactions.index', { search: tx.id })"
                                            class="text-violet-400 hover:underline"
                                        >
                                            #{{ tx.id }}
                                        </Link>
                                    </td>
                                    <td class="py-2.5">
                                        <Link
                                            v-if="tx.user && tx.user.id"
                                            :href="route('admin.users.show', tx.user.id)"
                                            class="text-zinc-200 hover:text-white transition"
                                        >
                                            {{ tx.user.name }}
                                        </Link>
                                        <span v-else class="text-zinc-600">—</span>
                                    </td>
                                    <td class="py-2.5">
                                        <span class="px-2 py-0.5 rounded text-[11px] bg-zinc-800 text-zinc-300 border border-zinc-700/50">
                                            {{ getTxTypeLabel(tx.type) }}
                                        </span>
                                    </td>
                                    <td
                                        class="py-2.5 text-right font-mono font-bold"
                                        :class="tx.amount >= 0 ? 'text-emerald-400' : 'text-rose-400'"
                                    >
                                        <span class="inline-flex items-center gap-1 justify-end">
                                            <span>{{ tx.amount > 0 ? '+' : '' }}{{ formatMoney(tx.amount) }}</span>
                                            <CoinIcon :color="tx.currency === 'pink' ? 'pink' : 'blue'" size="xs" />
                                        </span>
                                    </td>
                                    <td class="py-2.5 text-right font-mono text-zinc-400">
                                        <span class="inline-flex items-center gap-1 justify-end">
                                            <span>{{ formatMoney(tx.balance_after) }}</span>
                                            <CoinIcon :color="tx.currency === 'pink' ? 'pink' : 'blue'" size="xs" />
                                        </span>
                                    </td>
                                    <td class="py-2.5 text-zinc-500 font-mono text-[11px]">{{ tx.created_at }}</td>
                                    <td class="py-2.5">
                                        <AdminBadge :variant="tx.status === 'completed' ? 'success' : 'neutral'" size="sm">
                                            {{ tx.status }}
                                        </AdminBadge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Disputes Section if any -->
                <div v-if="order.disputes && order.disputes.length" class="rounded-xl border border-amber-900/40 bg-amber-950/20 p-5">
                    <div class="flex items-center gap-2 mb-3 text-amber-400 font-semibold text-sm">
                        <el-icon><Warning /></el-icon>
                        <span>Споры и претензии по заказу</span>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="d in order.disputes"
                            :key="d.id"
                            class="p-4 rounded-lg border border-amber-800/40 bg-zinc-900/80 space-y-2 text-xs"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-zinc-300 font-medium">
                                    Инициатор: <strong class="text-white">{{ d.customer_name }}</strong> ({{ d.created_at }})
                                </span>
                                <AdminBadge variant="warning" size="sm">{{ d.status }}</AdminBadge>
                            </div>
                            <div class="text-zinc-300">
                                <span class="text-zinc-400">Причина:</span> {{ d.reason }}
                            </div>
                            <div v-if="d.details" class="p-2.5 rounded bg-zinc-950 border border-zinc-800 text-zinc-400">
                                {{ d.details }}
                            </div>
                            <div v-if="d.admin_note" class="text-violet-400 bg-violet-950/20 p-2.5 rounded border border-violet-800/30">
                                <strong>Решение администратора:</strong> {{ d.admin_note }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Timeline Card -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 backdrop-blur-sm">
                    <div class="flex items-center justify-between mb-4 border-b border-zinc-800/80 pb-3">
                        <h2 class="text-sm font-semibold text-zinc-100">История переходов статуса</h2>
                    </div>

                    <div v-if="order.history.length === 0" class="py-6 text-center text-xs text-zinc-500">
                        История переходов пока пуста.
                    </div>
                    <div v-else class="space-y-4">
                        <div
                            v-for="(h, idx) in order.history"
                            :key="h.id || idx"
                            class="relative flex items-start gap-3 pl-6 before:absolute before:left-2 before:top-2 before:bottom-0 before:w-0.5 before:bg-zinc-800 last:before:hidden"
                        >
                            <div class="absolute left-0.5 top-1.5 w-3 h-3 rounded-full border-2 border-violet-500 bg-zinc-950"></div>
                            <div class="flex-1 bg-zinc-950/40 p-3 rounded-lg border border-zinc-800/70 text-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-zinc-200">{{ h.to }}</span>
                                        <span v-if="h.from" class="text-zinc-500 text-[11px]">(из {{ h.from }})</span>
                                    </div>
                                    <span class="text-zinc-500 font-mono text-[11px]">{{ h.created_at }}</span>
                                </div>
                                <div class="text-[11px] text-zinc-400">
                                    Инициатор: <span class="text-zinc-300 font-mono">{{ h.actor_type }}<template v-if="h.actor_id"> #{{ h.actor_id }}</template></span>
                                </div>
                                <div v-if="h.note" class="mt-2 text-zinc-300 bg-zinc-900 p-2 rounded border border-zinc-800 italic">
                                    «{{ h.note }}»
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side Column (1 col) -->
            <div class="space-y-6">
                <!-- Customer Card -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 backdrop-blur-sm">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-3">Заказчик (Клиент)</h3>
                    <div v-if="order.customer" class="flex items-start gap-3">
                        <img
                            v-if="order.customer.avatar_url"
                            :src="order.customer.avatar_url"
                            class="w-10 h-10 rounded-full object-cover border border-zinc-700"
                        />
                        <div v-else class="w-10 h-10 rounded-full bg-violet-600/30 border border-violet-500/40 text-violet-300 flex items-center justify-center font-bold text-sm">
                            {{ order.customer.name.charAt(0).toUpperCase() }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <Link
                                :href="route('admin.users.show', order.customer.id)"
                                class="text-sm font-semibold text-zinc-100 hover:text-violet-400 transition truncate block"
                            >
                                {{ order.customer.name }}
                            </Link>
                            <div class="text-xs text-zinc-400 truncate">{{ order.customer.email }}</div>
                            <div class="flex items-center gap-2 mt-2">
                                <AdminBadge v-if="order.customer.is_banned" variant="danger" size="sm">Заблокирован</AdminBadge>
                                <AdminBadge v-if="order.customer.is_deleted" variant="neutral" size="sm">Удален</AdminBadge>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-xs text-zinc-500 py-2">Заказчик удален</div>
                </div>

                <!-- Idol Card -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 backdrop-blur-sm">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-3">Исполнитель (Айдол)</h3>
                    <div v-if="order.idol" class="flex items-start gap-3">
                        <img
                            v-if="order.idol.avatar_url"
                            :src="order.idol.avatar_url"
                            class="w-10 h-10 rounded-full object-cover border border-pink-500/40"
                        />
                        <div v-else class="w-10 h-10 rounded-full bg-pink-600/30 border border-pink-500/40 text-pink-300 flex items-center justify-center font-bold text-sm">
                            {{ order.idol.name.charAt(0).toUpperCase() }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <Link
                                :href="route('admin.users.show', order.idol.id)"
                                class="text-sm font-semibold text-zinc-100 hover:text-pink-400 transition truncate block"
                            >
                                {{ order.idol.name }}
                            </Link>
                            <div class="text-xs text-zinc-400 truncate">{{ order.idol.email }}</div>
                            <div class="flex items-center gap-2 mt-1.5 text-xs text-zinc-300">
                                <span>⭐ Рейтинг: <strong class="text-amber-400">{{ order.idol.rating ?? '0.00' }}</strong></span>
                            </div>
                            <div class="flex items-center gap-2 mt-2">
                                <AdminBadge v-if="order.idol.is_banned" variant="danger" size="sm">Заблокирован</AdminBadge>
                                <AdminBadge v-if="order.idol.is_deleted" variant="neutral" size="sm">Удален</AdminBadge>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-xs text-zinc-500 py-2">Исполнитель удален</div>
                </div>

                <!-- Order Milestones / Timestamps -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 backdrop-blur-sm space-y-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Временные метки</h3>

                    <div class="space-y-2 text-xs">
                        <div class="flex items-center justify-between text-zinc-400">
                            <span>Создан:</span>
                            <span class="font-mono text-zinc-200">{{ order.created_at }}</span>
                        </div>
                        <div v-if="order.paid_at" class="flex items-center justify-between text-zinc-400">
                            <span>Оплачен:</span>
                            <span class="font-mono text-zinc-200">{{ order.paid_at }}</span>
                        </div>
                        <div v-if="order.completed_at" class="flex items-center justify-between text-zinc-400">
                            <span>Завершен:</span>
                            <span class="font-mono text-zinc-200">{{ order.completed_at }}</span>
                        </div>
                        <div v-if="order.cancel_reason" class="pt-2 border-t border-zinc-800">
                            <span class="text-rose-400 font-medium">Причина отмены:</span>
                            <p class="text-zinc-300 mt-0.5">{{ order.cancel_reason }}</p>
                        </div>
                        <div v-if="order.cancelled_by" class="flex items-center justify-between text-zinc-400">
                            <span>Отменил:</span>
                            <span class="text-zinc-200">{{ order.cancelled_by.name }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Change Modal -->
        <AdminModal
            :show="showStatusModal"
            title="Ручное изменение статуса заказа"
            @close="showStatusModal = false"
        >
            <div class="space-y-4">
                <div class="p-3 rounded-lg border border-amber-900/40 bg-amber-950/20 text-xs text-amber-300 flex items-start gap-2">
                    <el-icon :size="16" class="mt-0.5 shrink-0"><InfoFilled /></el-icon>
                    <span>
                        Внимание: принудительная смена статуса запускает финансовую логику платформы (снятие холда, зачисление выплат айдолу или возврат клиенту).
                    </span>
                </div>

                <template v-if="availableStatuses.length > 0">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-medium text-zinc-300">Новый допустимый статус (State Machine)</label>
                        <select
                            v-model="newStatus"
                            class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 focus:outline-none focus:border-violet-500"
                        >
                            <option v-for="s in availableStatuses" :key="s.value" :value="s.value">
                                {{ s.label }} ({{ s.value }})
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-medium text-zinc-300">Примечание администратора (причина)</label>
                        <textarea
                            v-model="adminNote"
                            rows="3"
                            class="w-full px-3 py-2 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                            placeholder="Укажите причину ручной корректировки для аудита..."
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-800">
                        <AdminButton variant="outline" size="sm" @click="showStatusModal = false">
                            Отмена
                        </AdminButton>
                        <AdminButton
                            variant="primary"
                            size="sm"
                            :loading="isSubmitting"
                            :disabled="!newStatus"
                            @click="submitStatusChange"
                        >
                            Применить статус
                        </AdminButton>
                    </div>
                </template>
                <div v-else class="space-y-3">
                    <div class="p-3 rounded-lg bg-zinc-950 border border-zinc-800 text-xs text-zinc-400">
                        Заказ находится в конечном статусе «{{ order.status_label || order.status }}». Дальнейшие переходы по стейт-машине заблокированы.
                    </div>
                    <div class="flex justify-end pt-2 border-t border-zinc-800">
                        <AdminButton variant="outline" size="sm" @click="showStatusModal = false">
                            Закрыть
                        </AdminButton>
                    </div>
                </div>
            </div>
        </AdminModal>
    </div>
</template>
