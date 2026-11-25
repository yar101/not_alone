<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ElMessageBox, ElMessage } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import IdolBadge from '@/Components/IdolBadge.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import { formatMoney } from '@/Utils/money';
import {
    ArrowLeft, User, Warning, Check, Close, Wallet, TopRight,
    Document, Star, EditPen, Lock, Unlock, SwitchButton
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    user: Object,
});

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function ratingDeltaColor(delta) {
    if (delta > 0) return 'text-emerald-400';
    if (delta < 0) return 'text-rose-400';
    return 'text-zinc-500';
}

const appStatusLabel = {
    pending:  { text: 'На рассмотрении', variant: 'warning' },
    approved: { text: 'Одобрена', variant: 'success' },
    rejected: { text: 'Отклонена', variant: 'danger' },
};

const ratingEventLabels = {
    order_completed: 'Заказ успешно выполнен',
    order_cancelled: 'Заказ отменен',
    order_refunded: 'Возврат по заказу (спор)',
    strike: 'Вынесен страйк',
    manual_adjust: 'Ручная корректировка',
    review_positive: 'Положительный отзыв',
    review_negative: 'Отрицательный отзыв',
};

// Ban inline form
const showBanForm = ref(false);
const banReason = ref('');
const banDuration = ref(null);
const banErrors = ref({});

const banPresets = [
    { label: '30м',    minutes: 30 },
    { label: '1ч',     minutes: 60 },
    { label: '3ч',     minutes: 180 },
    { label: '6ч',     minutes: 360 },
    { label: '12ч',    minutes: 720 },
    { label: '24ч',    minutes: 1440 },
    { label: '3дн',    minutes: 4320 },
    { label: '7дн',    minutes: 10080 },
    { label: '14дн',   minutes: 20160 },
    { label: '30дн',   minutes: 43200 },
    { label: '90дн',   minutes: 129600 },
    { label: 'Навсегда', minutes: null },
];

function submitBan() {
    banErrors.value = {};
    const bannedUntil = banDuration.value
        ? new Date(Date.now() + banDuration.value * 60 * 1000).toISOString()
        : null;
    router.post(route('admin.users.ban', props.user.id), {
        reason: banReason.value,
        banned_until: bannedUntil,
    }, {
        preserveScroll: false,
        onError: (errors) => { banErrors.value = errors; },
    });
}

function unban() {
    ElMessageBox.confirm(
        'Разблокировать пользователя и восстановить доступ к аккаунту?',
        'Разблокировка пользователя',
        {
            confirmButtonText: 'Разблокировать',
            cancelButtonText: 'Отмена',
            type: 'info',
        }
    ).then(() => {
        router.delete(route('admin.users.unban', props.user.id), { preserveScroll: false });
    }).catch(() => {});
}

function impersonateUser() {
    ElMessageBox.confirm(
        `Вы уверены, что хотите авторизоваться как пользователь ${props.user.name}? Вы перейдете в клиентский интерфейс от его лица. Вверху экрана будет баннер для возврата в админку.`,
        'Вход под пользователем',
        {
            confirmButtonText: 'Войти',
            cancelButtonText: 'Отмена',
            type: 'warning',
        }
    ).then(() => {
        router.post(route('admin.users.impersonate', props.user.id));
    }).catch(() => {});
}

// Rating adjustment form
const showRatingForm = ref(false);
const ratingDelta = ref(0);
const ratingNote = ref('');
const ratingErrors = ref({});

const projectedRating = computed(() => {
    const current = Number(props.user.rating ?? 20);
    const delta = Number(ratingDelta.value || 0);

    if (delta === 0) return current;

    let finalDelta = delta;
    if (delta > 0) {
        // Apply system dampening formula: delta * (100 - oldRating) / 80
        finalDelta = Math.max(0.01, (delta * (100 - current)) / 80);
    }

    return Math.max(0, Math.min(100, Number((current + finalDelta).toFixed(2))));
});

function submitRating() {
    ratingErrors.value = {};
    router.patch(route('admin.users.rating.update', props.user.id), {
        delta: ratingDelta.value,
        note: ratingNote.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showRatingForm.value = false;
            ratingDelta.value = 0;
            ratingNote.value = '';
        },
        onError: (errors) => { ratingErrors.value = errors; },
    });
}
</script>

<template>
    <div class="space-y-6">
        <!-- Top Navigation & User Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-5">
            <div class="flex items-center gap-3">
                <Link
                    :href="route('admin.users.index')"
                    class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition"
                    title="Назад к пользователям"
                >
                    <el-icon :size="16"><ArrowLeft /></el-icon>
                </Link>
                <div class="flex items-center gap-3">
                    <img
                        v-if="user.avatar_url"
                        :src="user.avatar_url"
                        class="w-11 h-11 rounded-full object-cover border border-zinc-700"
                    />
                    <div
                        v-else
                        class="w-11 h-11 rounded-full flex items-center justify-center font-bold text-base"
                        :class="user.is_idol ? 'bg-pink-900/40 text-pink-300 border border-pink-500/40' : 'bg-zinc-800 text-zinc-300 border border-zinc-700'"
                    >
                        {{ user.name?.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h1 class="text-xl font-bold text-zinc-100 tracking-tight">{{ user.name }}</h1>
                            <IdolBadge v-if="user.is_idol" />
                            <AdminBadge v-else variant="neutral" size="sm">Клиент</AdminBadge>
                            <AdminBadge v-if="user.is_banned" variant="danger" size="sm">Заблокирован</AdminBadge>
                        </div>
                        <p class="text-xs text-zinc-400 font-mono mt-0.5">
                            {{ user.email }} • ID: #{{ user.id }} • Зарегистрирован {{ formatDate(user.created_at) }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <a
                    :href="route('profile.show', user.id)"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-xs font-medium text-zinc-300 hover:text-zinc-100 hover:bg-zinc-800 transition"
                >
                    <span>Профиль на сайте</span>
                    <el-icon :size="12"><TopRight /></el-icon>
                </a>
                <Link
                    :href="route('admin.transactions.index', { user_id: user.id })"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-xs font-medium text-zinc-300 hover:text-zinc-100 hover:bg-zinc-800 transition"
                >
                    <el-icon><Wallet /></el-icon>
                    Транзакции кошелька
                </Link>
                <AdminButton variant="outline" size="sm" @click="impersonateUser">
                    <el-icon class="mr-1.5"><SwitchButton /></el-icon>
                    Войти под пользователем
                </AdminButton>
            </div>
        </div>

        <!-- 2-Column Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Main Column (2 cols) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Idol Rating Management -->
                <div v-if="user.is_idol" class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 backdrop-blur-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-zinc-800/80 pb-3">
                        <div class="flex items-center gap-2">
                            <el-icon class="text-amber-400"><Star /></el-icon>
                            <h2 class="text-sm font-semibold text-zinc-100">Рейтинг айдола</h2>
                            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-zinc-800 text-amber-300">
                                {{ user.rating ?? 20 }} / 100
                            </span>
                        </div>
                        <AdminButton v-if="!showRatingForm" variant="outline" size="sm" @click="showRatingForm = true">
                            <el-icon class="mr-1.5"><EditPen /></el-icon>
                            Изменить вручную
                        </AdminButton>
                    </div>

                    <!-- Rating Form -->
                    <form v-if="showRatingForm" @submit.prevent="submitRating" class="space-y-4 text-xs pt-1">
                        <div class="p-3 rounded-lg border border-violet-800/40 bg-violet-950/20 text-zinc-300">
                            Формула демпфирования: при положительной дельте действует затухание <code>delta * (100 - rating) / 80</code>, чтобы рейтинг приближался к 100 без скачков.
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="font-medium text-zinc-300">Изменение рейтинга (Delta от -100 до +100)</label>
                                <input
                                    v-model.number="ratingDelta"
                                    type="number"
                                    step="0.1"
                                    min="-100"
                                    max="100"
                                    required
                                    class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 font-mono focus:outline-none focus:border-violet-500"
                                />
                                <p v-if="ratingErrors.delta" class="text-xs text-rose-400">{{ ratingErrors.delta }}</p>
                            </div>
                            <div class="p-3 rounded-lg border border-zinc-800 bg-zinc-950/60 flex items-center justify-between">
                                <span class="text-zinc-400">Итоговый прогноз:</span>
                                <div class="text-base font-bold font-mono" :class="projectedRating > (user.rating ?? 20) ? 'text-emerald-400' : 'text-rose-400'">
                                    {{ projectedRating }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="font-medium text-zinc-300">Заметка к изменению (для аудита)</label>
                            <input
                                v-model="ratingNote"
                                type="text"
                                placeholder="Укажите причину корректировки..."
                                class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-800">
                            <AdminButton variant="outline" size="sm" type="button" @click="showRatingForm = false">
                                Отмена
                            </AdminButton>
                            <AdminButton variant="primary" size="sm" type="submit">
                                Применить рейтинг
                            </AdminButton>
                        </div>
                    </form>

                    <!-- Rating Logs Table -->
                    <div v-if="user.rating_logs?.length" class="space-y-2 pt-2">
                        <div class="text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">История изменений рейтинга</div>
                        <div class="rounded-lg border border-zinc-800 overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-zinc-950 border-b border-zinc-800 text-zinc-400">
                                        <th class="py-2 px-3">Дельта</th>
                                        <th class="py-2 px-3">Событие</th>
                                        <th class="py-2 px-3">Заметка</th>
                                        <th class="py-2 px-3">Дата</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                                    <tr v-for="(log, i) in user.rating_logs" :key="i">
                                        <td class="py-2 px-3 font-mono font-bold" :class="ratingDeltaColor(log.delta)">
                                            {{ log.delta > 0 ? '+' : '' }}{{ log.delta }}
                                        </td>
                                        <td class="py-2 px-3">{{ ratingEventLabels[log.event] || log.event }}</td>
                                        <td class="py-2 px-3 text-zinc-400">{{ log.note || '—' }}</td>
                                        <td class="py-2 px-3 font-mono text-[11px] text-zinc-500">{{ formatDate(log.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Services List (if idol) -->
                <div v-if="user.services?.length" class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 backdrop-blur-sm space-y-3">
                    <div class="flex items-center justify-between border-b border-zinc-800/80 pb-3">
                        <h2 class="text-sm font-semibold text-zinc-100 flex items-center gap-2">
                            <span>Услуги айдола</span>
                            <span class="text-xs font-normal text-zinc-500">({{ user.services.length }})</span>
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
                                    <th class="py-2 font-medium text-center">Статус</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                                <tr v-for="s in user.services" :key="s.id">
                                    <td class="py-2.5 font-medium text-zinc-200">{{ s.name }}</td>
                                    <td class="py-2.5 text-zinc-400">{{ s.category || '—' }}</td>
                                    <td class="py-2.5 text-zinc-400">{{ s.time_unit || '—' }}</td>
                                    <td class="py-2.5 text-right font-mono">
                                        <span class="inline-flex items-center gap-1 justify-end">
                                            <span>{{ formatMoney(s.price) }}</span>
                                            <CoinIcon color="blue" size="xs" />
                                        </span>
                                    </td>
                                    <td class="py-2.5 text-center">
                                        <AdminBadge :variant="s.status === 'approved' ? 'success' : (s.status === 'rejected' ? 'danger' : 'warning')" size="sm">
                                            {{ s.status === 'approved' ? 'Одобрена' : (s.status === 'rejected' ? 'Отклонена' : 'На проверке') }}
                                        </AdminBadge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Idol Application Card -->
                <div v-if="user.application" class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 backdrop-blur-sm space-y-3">
                    <div class="flex items-center justify-between border-b border-zinc-800/80 pb-3">
                        <h2 class="text-sm font-semibold text-zinc-100">Заявка на статус айдола</h2>
                        <AdminBadge :variant="appStatusLabel[user.application.status]?.variant || 'neutral'" size="sm">
                            {{ appStatusLabel[user.application.status]?.text || user.application.status }}
                        </AdminBadge>
                    </div>

                    <div class="text-xs space-y-2 text-zinc-300">
                        <div class="flex items-center justify-between text-zinc-400">
                            <span>Подана:</span>
                            <span class="font-mono text-zinc-200">{{ formatDate(user.application.created_at) }}</span>
                        </div>
                        <div v-if="user.application.rejection_reason" class="p-3 rounded-lg border border-rose-900/40 bg-rose-950/20 text-rose-300">
                            <strong>Причина отклонения:</strong> {{ user.application.rejection_reason }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side Column (1 col) -->
            <div class="space-y-6">
                <!-- Wallet & Balances Card -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 backdrop-blur-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-zinc-800/80 pb-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Финансы и кошелёк</h3>
                        <Link
                            :href="route('admin.transactions.index', { user_id: user.id })"
                            class="text-xs text-violet-400 hover:text-violet-300 hover:underline"
                        >
                            Проводки →
                        </Link>
                    </div>

                    <div v-if="user.wallet" class="space-y-3">
                        <div class="p-3 rounded-lg border border-zinc-800 bg-zinc-950">
                            <div class="text-[11px] text-zinc-400 mb-1">Синий баланс (потребительский)</div>
                            <div class="flex items-baseline gap-1.5 text-lg font-bold font-mono text-zinc-100">
                                <span>{{ formatMoney(user.wallet.balance) }}</span>
                                <CoinIcon color="blue" size="xs" />
                            </div>
                        </div>

                        <div v-if="user.wallet.held_balance > 0" class="p-3 rounded-lg border border-amber-900/40 bg-amber-950/20">
                            <div class="text-[11px] text-amber-400 mb-1">Заморожено в эскроу (холд)</div>
                            <div class="flex items-baseline gap-1.5 text-lg font-bold font-mono text-amber-300">
                                <span>{{ formatMoney(user.wallet.held_balance) }}</span>
                                <CoinIcon color="blue" size="xs" />
                            </div>
                        </div>

                        <div v-if="user.is_idol && user.wallet.pink_balance !== undefined" class="p-3 rounded-lg border border-pink-900/40 bg-pink-950/20">
                            <div class="text-[11px] text-pink-400 mb-1">Розовый баланс (заработок)</div>
                            <div class="flex items-baseline gap-1.5 text-lg font-bold font-mono text-pink-300">
                                <span>{{ formatMoney(user.wallet.pink_balance) }}</span>
                                <CoinIcon color="pink" size="xs" />
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-xs text-zinc-500">Кошелек пока не инициализирован</div>
                </div>

                <!-- Ban / Account Security Card -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 backdrop-blur-sm space-y-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Безопасность и блокировка</h3>

                    <div v-if="user.is_banned" class="p-3 rounded-lg border border-rose-900/40 bg-rose-950/20 space-y-2 text-xs">
                        <div class="flex items-center gap-2 font-semibold text-rose-300">
                            <el-icon><Lock /></el-icon>
                            <span>Пользователь заблокирован</span>
                        </div>
                        <div class="text-zinc-300">
                            <span class="text-zinc-500">Причина:</span> {{ user.ban_reason || 'Не указана' }}
                        </div>
                        <div class="text-zinc-300">
                            <span class="text-zinc-500">Срок:</span> {{ user.banned_until ? formatDate(user.banned_until) : 'Бессрочно' }}
                        </div>
                        <div v-if="user.banned_by" class="text-zinc-400 text-[11px]">
                            Заблокировал: {{ user.banned_by.name }}
                        </div>
                        <div class="pt-2">
                            <AdminButton variant="outline" size="sm" class="w-full" @click="unban">
                                <el-icon class="mr-1"><Unlock /></el-icon>
                                Разблокировать аккаунт
                            </AdminButton>
                        </div>
                    </div>

                    <div v-else>
                        <div v-if="!showBanForm">
                            <AdminButton variant="destructive" size="sm" class="w-full" @click="showBanForm = true">
                                <el-icon class="mr-1.5"><Lock /></el-icon>
                                Заблокировать пользователя
                            </AdminButton>
                        </div>

                        <form v-else @submit.prevent="submitBan" class="space-y-3 text-xs">
                            <div class="space-y-1">
                                <label class="font-medium text-zinc-300">Причина блокировки *</label>
                                <textarea
                                    v-model="banReason"
                                    rows="2"
                                    required
                                    class="w-full px-3 py-2 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                                    placeholder="Укажите причину блокировки..."
                                ></textarea>
                                <p v-if="banErrors.reason" class="text-rose-400 text-[11px]">{{ banErrors.reason }}</p>
                            </div>

                            <div class="space-y-1">
                                <label class="font-medium text-zinc-300">Срок блокировки</label>
                                <div class="grid grid-cols-4 gap-1">
                                    <button
                                        v-for="p in banPresets"
                                        :key="p.label"
                                        type="button"
                                        class="px-2 py-1 rounded border text-[10px] font-medium transition"
                                        :class="banDuration === p.minutes
                                            ? 'border-rose-500/50 bg-rose-500/20 text-rose-300'
                                            : 'border-zinc-800 bg-zinc-950 text-zinc-400 hover:text-zinc-200'"
                                        @click="banDuration = p.minutes"
                                    >
                                        {{ p.label }}
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-800">
                                <AdminButton variant="outline" size="sm" type="button" @click="showBanForm = false">
                                    Отмена
                                </AdminButton>
                                <AdminButton variant="destructive" size="sm" type="submit">
                                    Забанить
                                </AdminButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
