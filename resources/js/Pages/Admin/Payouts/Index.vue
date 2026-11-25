<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminDrawer from '@/Components/Admin/UI/AdminDrawer.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import { formatMoney } from '@/Utils/money';
import axios from 'axios';
import {
    Money, RefreshRight, Check, Close, Warning, WarningFilled,
    User, Tickets, Document, Wallet, Search, ArrowRight,
    CircleCheck, InfoFilled, Loading, TopRight
} from '@element-plus/icons-vue';
import { ElNotification } from 'element-plus';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    preview: Object,
    batches: Object,
    transactions: Object,
});

const page = usePage();
const activeTab = ref('queue'); // 'queue' | 'history'

// ─── Auto-Payout Toggle ──────────────────────────────────────────────────────
const autoEnabled = ref(Boolean(props.preview?.auto_payout_enabled));
const toggleLoading = ref(false);

function onToggleAuto() {
    const newVal = !autoEnabled.value;
    toggleLoading.value = true;
    router.post(route('admin.payouts.toggle-auto'), {
        enabled: newVal,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            autoEnabled.value = newVal;
            toggleLoading.value = false;
        },
        onError: () => {
            toggleLoading.value = false;
        },
    });
}

// ─── Refresh / Next Payout Data ──────────────────────────────────────────────
const refreshing = ref(false);
function refreshPreview() {
    refreshing.value = true;
    router.get(route('admin.payouts.index'), {}, {
        preserveScroll: true,
        onFinish: () => {
            refreshing.value = false;
        },
    });
}

// ─── Queue Search & Filter ───────────────────────────────────────────────────
const queueSearch = ref('');
const thresholdFilter = ref('all'); // 'all' | 'eligible' | 'under_threshold'

const filteredIdols = computed(() => {
    let list = props.preview?.idols ?? [];

    if (thresholdFilter.value === 'eligible') {
        list = list.filter(i => i.is_ready_for_auto_batch);
    } else if (thresholdFilter.value === 'under_threshold') {
        list = list.filter(i => !i.meets_threshold || i.has_antifraud_warning);
    }

    if (queueSearch.value.trim()) {
        const q = queueSearch.value.trim().toLowerCase();
        list = list.filter(i =>
            i.name?.toLowerCase().includes(q) ||
            i.email?.toLowerCase().includes(q) ||
            i.payout_phone?.includes(q) ||
            i.tax_inn?.includes(q)
        );
    }

    return list;
});

// ─── Breakdown Drawer ────────────────────────────────────────────────────────
const breakdownDrawerOpen = ref(false);
const breakdownLoading = ref(false);
const breakdownData = ref(null);
const selectedBreakdownIdol = ref(null);

async function openBreakdown(idol) {
    selectedBreakdownIdol.value = idol;
    breakdownDrawerOpen.value = true;
    breakdownLoading.value = true;
    breakdownData.value = null;

    try {
        const res = await axios.get(route('admin.payouts.breakdown', idol.idol_id));
        breakdownData.value = res.data;
    } catch (e) {
        ElNotification.error({
            title: 'Ошибка',
            message: 'Не удалось загрузить детализацию начислений.',
        });
    } finally {
        breakdownLoading.value = false;
    }
}

// ─── Out-of-turn Single Payout Modal ─────────────────────────────────────────
const singleModalOpen = ref(false);
const singleLoading = ref(false);
const selectedIdol = ref(null);
const payoutMode = ref('full'); // 'full' | 'custom'
const customAmount = ref(100);
const bypassAntiFraud = ref(false);

function openSinglePayoutModal(idol) {
    selectedIdol.value = idol;
    payoutMode.value = 'full';
    customAmount.value = Number(idol.available_for_payout);
    bypassAntiFraud.value = false;
    singleModalOpen.value = true;
}

const currentSingleAmount = computed(() => {
    if (payoutMode.value === 'full') {
        return Number(selectedIdol.value?.available_for_payout ?? 0);
    }
    return Number(customAmount.value ?? 0);
});

const canSubmitSinglePayout = computed(() => {
    if (!selectedIdol.value) return false;
    const amt = currentSingleAmount.value;
    if (amt < 1.0 || amt > Number(selectedIdol.value.available_for_payout)) {
        return false;
    }
    if (selectedIdol.value.has_antifraud_warning && !bypassAntiFraud.value) {
        return false;
    }
    return true;
});

function submitSinglePayout() {
    if (!canSubmitSinglePayout.value) return;

    singleLoading.value = true;
    router.post(route('admin.payouts.dispatch-single', selectedIdol.value.idol_id), {
        amount: currentSingleAmount.value,
        bypass_anti_fraud: bypassAntiFraud.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            singleModalOpen.value = false;
            singleLoading.value = false;
        },
        onError: () => {
            singleLoading.value = false;
        },
    });
}

// ─── Batch Dispatch Confirmation ─────────────────────────────────────────────
const batchModalOpen = ref(false);
const batchLoading = ref(false);

function confirmBatchDispatch() {
    batchModalOpen.value = true;
}

function submitBatchDispatch() {
    batchLoading.value = true;
    router.post(route('admin.payouts.dispatch-batch'), {
        batch_id: props.preview?.pending_batch?.id ?? null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            batchModalOpen.value = false;
            batchLoading.value = false;
        },
        onError: () => {
            batchLoading.value = false;
        },
    });
}

// ─── Reconcile Single Transaction ────────────────────────────────────────────
function reconcileTransaction(tx) {
    router.post(route('admin.payouts.reconcile', tx.id), {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-100 flex items-center gap-2.5">
                    <el-icon class="text-pink-400"><Money /></el-icon>
                    <span>Выплаты айдолам (СБП)</span>
                </h1>
                <p class="text-xs text-zinc-400 mt-0.5">
                    Реестры еженедельных выплат самозанятым по СБП (B2C), фискализация в ФНС РФ (422-ФЗ) и переводы вне очереди
                </p>
            </div>

            <!-- Top Controls -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <!-- Auto Toggle Button -->
                <button
                    type="button"
                    class="h-9 px-3 rounded-lg border text-xs font-medium flex items-center gap-2 transition"
                    :class="autoEnabled
                        ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300'
                        : 'border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-200'"
                    :disabled="toggleLoading"
                    @click="onToggleAuto"
                >
                    <span class="w-2 h-2 rounded-full" :class="autoEnabled ? 'bg-emerald-400 animate-pulse' : 'bg-zinc-600'"></span>
                    <span>Автовыплаты: <strong>{{ autoEnabled ? 'ВКЛ' : 'ВЫКЛ' }}</strong></span>
                </button>

                <!-- Refresh Button -->
                <AdminButton
                    variant="outline"
                    size="sm"
                    :loading="refreshing"
                    @click="refreshPreview"
                    title="Собрать актуальные данные по остаткам"
                >
                    <el-icon class="mr-1.5"><RefreshRight /></el-icon>
                    Собрать данные
                </AdminButton>

                <!-- Batch Dispatch Button -->
                <AdminButton
                    variant="primary"
                    size="sm"
                    :disabled="preview?.total_eligible_count === 0"
                    @click="confirmBatchDispatch"
                >
                    <el-icon class="mr-1.5"><Check /></el-icon>
                    <span>Отправить всем ({{ preview?.total_eligible_count ?? 0 }})</span>
                </AdminButton>
            </div>
        </div>

        <!-- Pending Batch Alert Banner (if exists) -->
        <div v-if="preview?.pending_batch" class="p-4 rounded-xl border border-amber-800/60 bg-amber-950/30 backdrop-blur-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-start gap-3">
                <el-icon class="text-amber-400 text-2xl mt-0.5 shrink-0"><WarningFilled /></el-icon>
                <div>
                    <h3 class="text-amber-200 font-semibold text-sm">
                        Ожидает подтверждения реестр выплат #{{ preview.pending_batch.batch_number }}
                    </h3>
                    <p class="text-xs text-amber-300/80 mt-0.5">
                        Сформирован предварительный батч на сумму <strong>{{ formatMoney(preview.pending_batch.total_amount) }} ₽</strong> для <strong>{{ preview.pending_batch.total_recipients }}</strong> получателей. Деньги в банк пока не отправлены.
                    </p>
                </div>
            </div>
            <AdminButton variant="primary" size="sm" class="whitespace-nowrap shrink-0" @click="confirmBatchDispatch">
                Утвердить и отправить реестр
            </AdminButton>
        </div>

        <!-- KPI Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm">
                <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                    <span>К выплате (готово)</span>
                    <el-icon class="text-emerald-400"><Wallet /></el-icon>
                </div>
                <div class="text-xl font-bold font-mono text-emerald-400">
                    {{ formatMoney(preview?.total_eligible_amount ?? 0) }} ₽
                </div>
                <div class="text-[11px] text-zinc-500 mt-1">
                    {{ preview?.total_eligible_count ?? 0 }} айдолов (баланс &ge; {{ formatMoney(preview?.min_payout_threshold ?? 1000) }} ₽)
                </div>
            </div>

            <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm">
                <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                    <span>Всего на балансах</span>
                    <el-icon class="text-pink-400"><Tickets /></el-icon>
                </div>
                <div class="text-xl font-bold font-mono text-pink-400">
                    {{ formatMoney(preview?.total_pending_amount ?? 0) }} ₽
                </div>
                <div class="text-[11px] text-zinc-500 mt-1">
                    {{ preview?.total_idols_count ?? 0 }} айдолов с остатком &gt; 0 ₽
                </div>
            </div>

            <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm">
                <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                    <span>Комиссия за вывод по СБП</span>
                    <span class="text-zinc-500 font-mono text-xs">0%</span>
                </div>
                <div class="text-xl font-bold font-mono text-zinc-200">
                    0.00 ₽ (0.0%)
                </div>
                <div class="text-[11px] text-zinc-500 mt-1">
                    Банковские расходы покрывает платформа
                </div>
            </div>

            <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm">
                <div class="flex items-center justify-between text-xs text-zinc-400 mb-2">
                    <span>Режим расписания</span>
                    <AdminBadge :variant="autoEnabled ? 'success' : 'warning'" size="sm">
                        {{ autoEnabled ? 'АВТО' : 'РУЧНОЙ' }}
                    </AdminBadge>
                </div>
                <div class="text-sm font-semibold text-zinc-200">
                    {{ autoEnabled ? 'Автовыплаты в Пн 06:00 UTC' : 'Крон формирует драфт-реестр' }}
                </div>
                <div class="text-[11px] text-zinc-500 mt-1">
                    Mandarin BaaS Номинальный счёт
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="border-b border-zinc-800 flex items-center gap-6">
            <button
                class="pb-3 text-xs font-semibold uppercase tracking-wider border-b-2 transition flex items-center gap-2"
                :class="activeTab === 'queue' ? 'border-pink-500 text-pink-400' : 'border-transparent text-zinc-400 hover:text-zinc-200'"
                @click="activeTab = 'queue'"
            >
                <span>Очередь выплат</span>
                <span class="px-2 py-0.5 rounded-full text-[11px] font-mono bg-zinc-800 text-zinc-300">
                    {{ preview?.total_idols_count ?? 0 }}
                </span>
            </button>
            <button
                class="pb-3 text-xs font-semibold uppercase tracking-wider border-b-2 transition flex items-center gap-2"
                :class="activeTab === 'history' ? 'border-pink-500 text-pink-400' : 'border-transparent text-zinc-400 hover:text-zinc-200'"
                @click="activeTab = 'history'"
            >
                <span>История реестров</span>
                <span class="px-2 py-0.5 rounded-full text-[11px] font-mono bg-zinc-800 text-zinc-300">
                    {{ batches?.total ?? 0 }}
                </span>
            </button>
        </div>

        <!-- TAB 1: Queue / Next Payout Preview -->
        <div v-if="activeTab === 'queue'" class="space-y-4">
            <!-- Filter Bar -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                <div class="relative flex-1 max-w-sm">
                    <el-icon class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500"><Search /></el-icon>
                    <input
                        v-model="queueSearch"
                        type="text"
                        placeholder="Поиск по имени, email, телефону, ИНН..."
                        class="w-full h-9 pl-9 pr-3 rounded-lg border border-zinc-800 bg-zinc-900/80 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                    />
                </div>

                <div class="flex items-center gap-1.5 flex-wrap">
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium border transition"
                        :class="thresholdFilter === 'all'
                            ? 'border-pink-500/50 bg-pink-500/15 text-pink-300'
                            : 'border-zinc-800 bg-zinc-900/60 text-zinc-400 hover:text-zinc-200'"
                        @click="thresholdFilter = 'all'"
                    >
                        Все ({{ preview?.total_idols_count ?? 0 }})
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium border transition"
                        :class="thresholdFilter === 'eligible'
                            ? 'border-emerald-500/50 bg-emerald-500/15 text-emerald-300'
                            : 'border-zinc-800 bg-zinc-900/60 text-zinc-400 hover:text-zinc-200'"
                        @click="thresholdFilter = 'eligible'"
                    >
                        Готовы к автобатчу ({{ preview?.total_eligible_count ?? 0 }})
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium border transition"
                        :class="thresholdFilter === 'under_threshold'
                            ? 'border-amber-500/50 bg-amber-500/15 text-amber-300'
                            : 'border-zinc-800 bg-zinc-900/60 text-zinc-400 hover:text-zinc-200'"
                        @click="thresholdFilter = 'under_threshold'"
                    >
                        Менее 1 000 ₽ или алерт
                    </button>
                </div>
            </div>

            <!-- Idols Queue Table -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 overflow-hidden backdrop-blur-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-zinc-800 bg-zinc-950/40 text-zinc-400">
                                <th class="py-3 px-4 font-medium">Айдол</th>
                                <th class="py-3 px-4 font-medium">Реквизиты СБП</th>
                                <th class="py-3 px-4 font-medium">К выплате</th>
                                <th class="py-3 px-4 font-medium">Антифрод</th>
                                <th class="py-3 px-4 font-medium">Детализация</th>
                                <th class="py-3 px-4 font-medium text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                            <tr v-if="filteredIdols.length === 0">
                                <td colspan="6" class="py-12 text-center text-zinc-500">
                                    Нет айдолов, соответствующих выбранным критериям.
                                </td>
                            </tr>
                            <tr
                                v-for="idol in filteredIdols"
                                :key="idol.idol_id"
                                class="hover:bg-zinc-800/30 transition"
                            >
                                <!-- Idol Info -->
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3">
                                        <img
                                            v-if="idol.avatar"
                                            :src="idol.avatar"
                                            class="w-8 h-8 rounded-full object-cover border border-zinc-700"
                                        />
                                        <div v-else class="w-8 h-8 rounded-full bg-zinc-800 text-zinc-300 font-bold flex items-center justify-center text-xs">
                                            {{ idol.name?.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-zinc-100 flex items-center gap-1.5">
                                                <span>{{ idol.name }}</span>
                                                <span v-if="idol.nickname && idol.nickname !== idol.name" class="text-zinc-500 text-[11px]">
                                                    (@{{ idol.nickname }})
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-zinc-500">
                                                ИНН: <span class="font-mono text-zinc-400">{{ idol.tax_inn || 'Не указан' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- SBP Requisites -->
                                <td class="py-3 px-4">
                                    <div class="text-zinc-200 font-mono text-xs">{{ idol.payout_phone || '—' }}</div>
                                    <div class="text-[11px] text-zinc-500">Банк: {{ idol.sbp_bank_id || '—' }}</div>
                                </td>

                                <!-- Balance -->
                                <td class="py-3 px-4">
                                    <div class="font-bold text-zinc-100 text-sm font-mono">
                                        {{ formatMoney(idol.available_for_payout) }} ₽
                                    </div>
                                    <div class="mt-0.5">
                                        <span
                                            v-if="idol.meets_threshold"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-950/60 text-emerald-400 border border-emerald-800/40"
                                        >
                                            &ge; 1 000 ₽ (Автобатч)
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-zinc-800 text-zinc-400"
                                        >
                                            &lt; 1 000 ₽ (Вручную)
                                        </span>
                                    </div>
                                </td>

                                <!-- Anti-fraud Status -->
                                <td class="py-3 px-4">
                                    <AdminBadge v-if="!idol.has_antifraud_warning" variant="success" size="sm" dot>
                                        В норме
                                    </AdminBadge>
                                    <div v-else class="space-y-1">
                                        <AdminBadge
                                            v-if="idol.antifraud_details?.requisites_changed_recently"
                                            variant="warning"
                                            size="sm"
                                            :title="`Реквизиты изменены ${idol.antifraud_details?.requisites_hours_ago} ч назад`"
                                        >
                                            Смена реквизитов (&lt; 24ч)
                                        </AdminBadge>
                                        <AdminBadge
                                            v-if="idol.antifraud_details?.account_too_new"
                                            variant="warning"
                                            size="sm"
                                        >
                                            Новый аккаунт (&lt; 72ч)
                                        </AdminBadge>
                                    </div>
                                </td>

                                <!-- Breakdown Button -->
                                <td class="py-3 px-4">
                                    <button
                                        class="text-xs text-pink-400 hover:text-pink-300 flex items-center gap-1 font-medium hover:underline"
                                        @click="openBreakdown(idol)"
                                    >
                                        <el-icon><Document /></el-icon>
                                        <span>За что начислено</span>
                                    </button>
                                </td>

                                <!-- Actions -->
                                <td class="py-3 px-4 text-right">
                                    <AdminButton
                                        variant="outline"
                                        size="sm"
                                        @click="openSinglePayoutModal(idol)"
                                    >
                                        Выплатить вне очереди
                                    </AdminButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: Payout History -->
        <div v-if="activeTab === 'history'" class="space-y-6">
            <!-- Batches Sub-section -->
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-zinc-100 flex items-center gap-2">
                    <el-icon class="text-pink-400"><Tickets /></el-icon>
                    <span>Реестры выплат (Батчи)</span>
                </h3>
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 overflow-hidden backdrop-blur-sm">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-zinc-800 bg-zinc-950/40 text-zinc-400">
                                <th class="py-3 px-4 font-medium">Номер реестра</th>
                                <th class="py-3 px-4 font-medium">Статус</th>
                                <th class="py-3 px-4 font-medium">Сумма</th>
                                <th class="py-3 px-4 font-medium">Получателей</th>
                                <th class="py-3 px-4 font-medium">Успешно / Ошибок</th>
                                <th class="py-3 px-4 font-medium">Тип / Администратор</th>
                                <th class="py-3 px-4 font-medium">Дата исполнения</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                            <tr v-if="!batches?.data?.length">
                                <td colspan="7" class="py-8 text-center text-zinc-500">
                                    История реестров пуста.
                                </td>
                            </tr>
                            <tr v-for="b in batches?.data" :key="b.id" class="hover:bg-zinc-800/30">
                                <td class="py-3 px-4 font-mono font-medium text-zinc-200">
                                    {{ b.batch_number }}
                                </td>
                                <td class="py-3 px-4">
                                    <AdminBadge
                                        :variant="b.status === 'completed' ? 'success' : (b.status === 'pending_approval' ? 'warning' : 'danger')"
                                        size="sm"
                                    >
                                        {{ b.status }}
                                    </AdminBadge>
                                </td>
                                <td class="py-3 px-4 font-bold font-mono text-zinc-100">
                                    {{ formatMoney(b.total_amount) }} ₽
                                </td>
                                <td class="py-3 px-4 text-zinc-300">
                                    {{ b.total_recipients }}
                                </td>
                                <td class="py-3 px-4 font-mono">
                                    <span class="text-emerald-400 font-semibold">{{ b.successful_count }}</span> /
                                    <span class="text-rose-400 font-semibold">{{ b.failed_count }}</span>
                                </td>
                                <td class="py-3 px-4 text-zinc-400">
                                    <span v-if="b.is_automated" class="text-emerald-400 font-medium">Автоматический (крон)</span>
                                    <span v-else class="text-zinc-300">Ручной ({{ b.approver_name || 'Администратор' }})</span>
                                </td>
                                <td class="py-3 px-4 font-mono text-[11px] text-zinc-500">
                                    {{ b.executed_at ? new Date(b.executed_at).toLocaleString('ru-RU') : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Individual Transactions Sub-section -->
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-zinc-100 flex items-center gap-2">
                    <el-icon class="text-pink-400"><Wallet /></el-icon>
                    <span>Все транзакции выплат по СБП</span>
                </h3>
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 overflow-hidden backdrop-blur-sm">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-zinc-800 bg-zinc-950/40 text-zinc-400">
                                <th class="py-3 px-4 font-medium">ID</th>
                                <th class="py-3 px-4 font-medium">Получатель</th>
                                <th class="py-3 px-4 font-medium">Сумма</th>
                                <th class="py-3 px-4 font-medium">Статус</th>
                                <th class="py-3 px-4 font-medium">Чек ФНС (422-ФЗ)</th>
                                <th class="py-3 px-4 font-medium">Дата</th>
                                <th class="py-3 px-4 font-medium text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                            <tr v-if="!transactions?.data?.length">
                                <td colspan="7" class="py-8 text-center text-zinc-500">
                                    Транзакций выплат пока нет.
                                </td>
                            </tr>
                            <tr v-for="tx in transactions?.data" :key="tx.id" class="hover:bg-zinc-800/30">
                                <td class="py-3 px-4 font-mono text-zinc-400">
                                    #{{ tx.id }}
                                </td>
                                <td class="py-3 px-4 font-medium text-zinc-200">
                                    {{ tx.user_name }}
                                </td>
                                <td class="py-3 px-4 font-bold font-mono text-zinc-100">
                                    {{ formatMoney(tx.amount) }} ₽
                                </td>
                                <td class="py-3 px-4">
                                    <AdminBadge
                                        :variant="tx.status === 'completed' ? 'success' : (tx.status === 'pending_bank' ? 'warning' : 'danger')"
                                        size="sm"
                                    >
                                        {{ tx.status_label || tx.status }}
                                    </AdminBadge>
                                </td>
                                <td class="py-3 px-4">
                                    <a
                                        v-if="tx.metadata?.fns_receipt_url"
                                        :href="tx.metadata.fns_receipt_url"
                                        target="_blank"
                                        class="text-violet-400 hover:text-violet-300 hover:underline flex items-center gap-1 font-medium"
                                    >
                                        <span>Чек «Мой налог»</span>
                                        <el-icon :size="12"><TopRight /></el-icon>
                                    </a>
                                    <span v-else class="text-zinc-600">—</span>
                                </td>
                                <td class="py-3 px-4 font-mono text-[11px] text-zinc-500">
                                    {{ new Date(tx.created_at).toLocaleString('ru-RU') }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <button
                                        v-if="tx.status === 'pending_bank'"
                                        class="text-violet-400 hover:text-violet-300 hover:underline text-xs font-medium"
                                        @click="reconcileTransaction(tx)"
                                    >
                                        Сверить статус
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ════════════ DRAWER: Breakdown ("За что начислено") ════════════ -->
        <AdminDrawer
            :show="breakdownDrawerOpen"
            title="Детализация начислений айдола"
            :subtitle="selectedBreakdownIdol ? selectedBreakdownIdol.name : ''"
            width="560px"
            @close="breakdownDrawerOpen = false"
        >
            <div v-if="breakdownLoading" class="py-12 text-center text-zinc-400">
                <el-icon class="animate-spin text-3xl"><Loading /></el-icon>
                <div class="mt-2 text-xs">Загрузка структуры начислений...</div>
            </div>
            <div v-else-if="breakdownData" class="space-y-5 text-xs">
                <!-- Idol Summary Card -->
                <div class="p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-semibold text-zinc-100">{{ breakdownData.idol?.name }}</div>
                        <AdminBadge variant="success" size="sm">Доступно к выводу</AdminBadge>
                    </div>

                    <div class="flex items-baseline justify-between pt-2 border-t border-zinc-800">
                        <span class="text-zinc-400">Сумма к выводу:</span>
                        <div class="text-lg font-bold font-mono text-emerald-400">
                            {{ formatMoney(breakdownData.idol?.available_for_payout) }} ₽
                        </div>
                    </div>

                    <!-- Category totals -->
                    <div class="grid grid-cols-3 gap-2 pt-3 border-t border-zinc-800 text-xs">
                        <div class="p-2 rounded bg-zinc-900 border border-zinc-800/80">
                            <span class="text-zinc-500 text-[11px]">Заказы:</span>
                            <div class="font-bold font-mono text-zinc-200 mt-0.5">{{ formatMoney(breakdownData.totals?.orders ?? 0) }} ₽</div>
                        </div>
                        <div class="p-2 rounded bg-zinc-900 border border-zinc-800/80">
                            <span class="text-zinc-500 text-[11px]">Паки:</span>
                            <div class="font-bold font-mono text-zinc-200 mt-0.5">{{ formatMoney(breakdownData.totals?.packs ?? 0) }} ₽</div>
                        </div>
                        <div class="p-2 rounded bg-zinc-900 border border-zinc-800/80">
                            <span class="text-zinc-500 text-[11px]">Донаты:</span>
                            <div class="font-bold font-mono text-zinc-200 mt-0.5">{{ formatMoney(breakdownData.totals?.donations ?? 0) }} ₽</div>
                        </div>
                    </div>
                </div>

                <!-- Itemized List -->
                <div class="space-y-2">
                    <div class="text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">
                        История начислений (последние операции)
                    </div>
                    <div class="space-y-2 max-h-[50vh] overflow-y-auto pr-1">
                        <div
                            v-for="item in breakdownData.items"
                            :key="item.id"
                            class="p-3 bg-zinc-950/60 border border-zinc-800 rounded-lg space-y-1.5"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-zinc-200">{{ item.type_label }}</span>
                                <span class="font-bold font-mono text-emerald-400">+{{ formatMoney(item.amount) }} ₽</span>
                            </div>

                            <!-- Order breakdown details -->
                            <div v-if="item.order" class="text-zinc-400 space-y-0.5 text-[11px]">
                                <div>Заказ #{{ item.order.id }}: <strong class="text-zinc-300">{{ item.order.service_title }}</strong></div>
                                <div class="text-zinc-500">
                                    Клиент: {{ item.order.customer_name }} • Сумма: {{ formatMoney(item.order.gross_amount) }} ₽ • Сбор 12%: −{{ formatMoney(item.order.fee_amount) }} ₽
                                </div>
                            </div>

                            <!-- Pack breakdown details -->
                            <div v-else-if="item.pack" class="text-zinc-400 space-y-0.5 text-[11px]">
                                <div>Пак: <strong class="text-zinc-300">{{ item.pack.title }}</strong></div>
                                <div class="text-zinc-500">
                                    Цена: {{ formatMoney(item.pack.gross_amount) }} ₽ • Сбор: −{{ formatMoney(item.pack.fee_amount) }} ₽
                                </div>
                            </div>

                            <!-- Donation details -->
                            <div v-else-if="item.donation" class="text-zinc-400 text-[11px]">
                                Донат от: <strong class="text-zinc-300">{{ item.donation.donor_name }}</strong>
                            </div>

                            <div class="text-[10px] text-zinc-500 font-mono pt-1 border-t border-zinc-800/60">
                                {{ new Date(item.date).toLocaleString('ru-RU') }}
                            </div>
                        </div>

                        <div v-if="!breakdownData.items?.length" class="text-center py-6 text-zinc-500">
                            Нет завершённых начислений.
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end">
                    <AdminButton variant="outline" size="sm" @click="breakdownDrawerOpen = false">
                        Закрыть
                    </AdminButton>
                </div>
            </template>
        </AdminDrawer>

        <!-- ════════════ MODAL: Out-of-turn Single Payout ════════════ -->
        <AdminModal
            :show="singleModalOpen"
            title="Внеочередная выплата айдолу"
            @close="singleModalOpen = false"
        >
            <div v-if="selectedIdol" class="space-y-4 text-xs">
                <div class="p-3 bg-zinc-950 border border-zinc-800 rounded-lg space-y-1">
                    <div class="font-semibold text-zinc-100 text-sm">{{ selectedIdol.name }}</div>
                    <div class="text-zinc-400">
                        СБП: <span class="font-mono text-zinc-200">{{ selectedIdol.payout_phone }}</span> (Банк: {{ selectedIdol.sbp_bank_id }})
                    </div>
                    <div class="text-zinc-400">
                        Доступно к выплате: <strong class="text-emerald-400 font-mono">{{ formatMoney(selectedIdol.available_for_payout) }} ₽</strong>
                    </div>
                </div>

                <!-- Amount selection -->
                <div class="space-y-2">
                    <label class="font-medium text-zinc-300">Сумма выплаты</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="p-2.5 rounded-lg border flex items-center gap-2 cursor-pointer transition"
                            :class="payoutMode === 'full' ? 'border-pink-500/50 bg-pink-500/10 text-pink-300' : 'border-zinc-800 bg-zinc-950 text-zinc-400'"
                        >
                            <input
                                type="radio"
                                value="full"
                                v-model="payoutMode"
                                class="text-pink-600 focus:ring-0"
                            />
                            <span>Вся сумма ({{ formatMoney(selectedIdol.available_for_payout) }} ₽)</span>
                        </label>
                        <label class="p-2.5 rounded-lg border flex items-center gap-2 cursor-pointer transition"
                            :class="payoutMode === 'custom' ? 'border-pink-500/50 bg-pink-500/10 text-pink-300' : 'border-zinc-800 bg-zinc-950 text-zinc-400'"
                        >
                            <input
                                type="radio"
                                value="custom"
                                v-model="payoutMode"
                                class="text-pink-600 focus:ring-0"
                            />
                            <span>Своя сумма</span>
                        </label>
                    </div>

                    <div v-if="payoutMode === 'custom'" class="pt-1">
                        <input
                            v-model.number="customAmount"
                            type="number"
                            step="0.01"
                            min="1"
                            :max="Number(selectedIdol.available_for_payout)"
                            class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs font-mono text-zinc-100 focus:outline-none focus:border-violet-500"
                        />
                    </div>
                </div>

                <!-- Anti-fraud Warning Alert (if active) -->
                <div v-if="selectedIdol.has_antifraud_warning" class="p-3 bg-amber-950/40 border border-amber-700/60 rounded-lg space-y-2 text-amber-200">
                    <div class="flex items-center gap-2 font-semibold text-amber-300">
                        <el-icon><Warning /></el-icon>
                        <span>Внимание: Сработало антифрод-ограничение</span>
                    </div>
                    <p class="text-amber-200/80 leading-relaxed">
                        <span v-if="selectedIdol.antifraud_details?.requisites_changed_recently">
                            Реквизиты СБП были изменены менее 24 часов назад ({{ selectedIdol.antifraud_details?.requisites_hours_ago }} ч назад).
                        </span>
                        <span v-if="selectedIdol.antifraud_details?.account_too_new">
                            Аккаунт айдола зарегистрирован менее 72 часов назад.
                        </span>
                    </p>
                    <label class="flex items-start gap-2 pt-1 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="bypassAntiFraud"
                            class="rounded border-amber-600 text-pink-600 mt-0.5"
                        />
                        <span class="font-medium text-amber-300">
                            Я проверил реквизиты и подтверждаю снятие ограничения
                        </span>
                    </label>
                </div>

                <div class="text-[11px] text-zinc-500 pt-1">
                    Средства будут мгновенно переведены через Mandarin BaaS по номеру СБП с автоматической регистрацией чека в ФНС («Мой налог»).
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-800">
                    <AdminButton variant="outline" size="sm" type="button" @click="singleModalOpen = false">
                        Отмена
                    </AdminButton>
                    <AdminButton
                        variant="primary"
                        size="sm"
                        :loading="singleLoading"
                        :disabled="!canSubmitSinglePayout"
                        @click="submitSinglePayout"
                    >
                        Отправить {{ formatMoney(currentSingleAmount) }} ₽ в СБП
                    </AdminButton>
                </div>
            </div>
        </AdminModal>

        <!-- ════════════ MODAL: Batch Dispatch Confirmation ════════════ -->
        <AdminModal
            :show="batchModalOpen"
            title="Подтверждение пакетной выплаты"
            @close="batchModalOpen = false"
        >
            <div class="space-y-4 text-xs text-zinc-300">
                <p>
                    Вы собираетесь отправить реестр выплат для <strong>{{ preview?.total_eligible_count ?? 0 }}</strong> айдолов на общую сумму <strong class="text-emerald-400 font-mono">{{ formatMoney(preview?.total_eligible_amount ?? 0) }} ₽</strong>.
                </p>

                <div class="p-3 bg-zinc-950 border border-zinc-800 rounded-lg space-y-1.5 text-zinc-400">
                    <div>Метод: <strong class="text-zinc-200">СБП (B2C) через номинальный счёт</strong></div>
                    <div>Банковская комиссия: <strong class="text-zinc-200">0.00 ₽ (0%)</strong></div>
                    <div>Фискализация: <strong class="text-zinc-200">Автоматическая регистрация в ФНС РФ (422-ФЗ)</strong></div>
                </div>

                <p class="text-zinc-500">
                    Операция необратима: средства будут списаны с балансов айдолов и отправлены в банк через Mandarin BaaS.
                </p>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-800">
                    <AdminButton variant="outline" size="sm" type="button" @click="batchModalOpen = false">
                        Отмена
                    </AdminButton>
                    <AdminButton
                        variant="primary"
                        size="sm"
                        :loading="batchLoading"
                        @click="submitBatchDispatch"
                    >
                        Подтвердить и отправить реестр
                    </AdminButton>
                </div>
            </div>
        </AdminModal>
    </div>
</template>
