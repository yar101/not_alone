<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import { formatMoney } from '@/Utils/money';
import axios from 'axios';
import {
    Money, RefreshRight, Check, Close, Warning, WarningFilled,
    User, Tickets, Document, Wallet, Search, ArrowRight,
    CircleCheck, InfoFilled, Loading
} from '@element-plus/icons-vue';
import { ElMessageBox, ElNotification } from 'element-plus';

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

function onToggleAuto(val) {
    toggleLoading.value = true;
    router.post(route('admin.payouts.toggle-auto'), {
        enabled: val,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            autoEnabled.value = val;
            toggleLoading.value = false;
        },
        onError: () => {
            autoEnabled.value = !val;
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

async function openBreakdown(idol) {
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
    <div class="payouts-page space-y-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                    <el-icon class="text-pink-400"><Money /></el-icon>
                    Выплаты айдолам (СБП)
                </h1>
                <p class="text-sm text-zinc-400 mt-1">
                    Управление еженедельными выплатами самозанятым по СБП, контроль расписания и внеочередные переводы
                </p>
            </div>

            <!-- Top Controls -->
            <div class="flex items-center gap-3 flex-wrap">
                <!-- Auto Toggle -->
                <div class="flex items-center gap-2 px-3 py-1.5 bg-zinc-900 border border-zinc-800 rounded-lg">
                    <span class="text-xs font-medium text-zinc-300">Автовыплаты (Пн 06:00 UTC):</span>
                    <el-switch
                        v-model="autoEnabled"
                        :loading="toggleLoading"
                        active-color="#10b981"
                        inactive-color="#4b5563"
                        @change="onToggleAuto"
                    />
                    <span class="text-xs" :class="autoEnabled ? 'text-emerald-400 font-semibold' : 'text-zinc-500'">
                        {{ autoEnabled ? 'ВКЛ' : 'ВЫКЛ' }}
                    </span>
                </div>

                <!-- Refresh Button -->
                <button
                    class="btn-secondary"
                    :disabled="refreshing"
                    @click="refreshPreview"
                    title="Собрать актуальные данные по остаткам"
                >
                    <el-icon :class="{ 'animate-spin': refreshing }"><RefreshRight /></el-icon>
                    <span>След. выплата / Собрать данные</span>
                </button>

                <!-- Batch Dispatch Button -->
                <button
                    class="btn-primary"
                    :disabled="preview?.total_eligible_count === 0"
                    @click="confirmBatchDispatch"
                >
                    <el-icon><Check /></el-icon>
                    <span>Отправить всем ({{ preview?.total_eligible_count ?? 0 }} айдолов)</span>
                </button>
            </div>
        </div>

        <!-- Pending Batch Alert Banner (if exists) -->
        <div v-if="preview?.pending_batch" class="p-4 bg-amber-950/40 border border-amber-600/50 rounded-xl flex items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <el-icon class="text-amber-400 text-2xl mt-0.5"><WarningFilled /></el-icon>
                <div>
                    <h3 class="text-amber-200 font-semibold text-sm">
                        Ожидает подтверждения реестр выплат #{{ preview.pending_batch.batch_number }}
                    </h3>
                    <p class="text-xs text-amber-300/80 mt-0.5">
                        Сформирован предварительный батч на сумму <strong>{{ formatMoney(preview.pending_batch.total_amount) }} ₽</strong> для <strong>{{ preview.pending_batch.total_recipients }}</strong> получателей. Деньги в банк пока не отправлены.
                    </p>
                </div>
            </div>
            <button class="btn-primary whitespace-nowrap" @click="confirmBatchDispatch">
                Утвердить и отправить реестр
            </button>
        </div>

        <!-- KPI Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="kpi-card bg-zinc-900 border border-zinc-800 rounded-xl p-4">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span>К выплате (готово)</span>
                    <el-icon class="text-emerald-400"><Wallet /></el-icon>
                </div>
                <div class="text-xl font-bold text-emerald-400 mt-2 flex items-center gap-1.5">
                    <span>{{ formatMoney(preview?.total_eligible_amount ?? 0) }} ₽</span>
                </div>
                <div class="text-xs text-zinc-500 mt-1">
                    {{ preview?.total_eligible_count ?? 0 }} айдолов (баланс &ge; {{ formatMoney(preview?.min_payout_threshold ?? 1000) }} ₽)
                </div>
            </div>

            <div class="kpi-card bg-zinc-900 border border-zinc-800 rounded-xl p-4">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span>Всего на балансах</span>
                    <el-icon class="text-pink-400"><Tickets /></el-icon>
                </div>
                <div class="text-xl font-bold text-pink-400 mt-2 flex items-center gap-1.5">
                    <span>{{ formatMoney(preview?.total_pending_amount ?? 0) }} ₽</span>
                </div>
                <div class="text-xs text-zinc-500 mt-1">
                    {{ preview?.total_idols_count ?? 0 }} айдолов с остатком &gt; 0 ₽
                </div>
            </div>

            <div class="kpi-card bg-zinc-900 border border-zinc-800 rounded-xl p-4">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span>Комиссия СБП</span>
                    <el-icon class="text-blue-400"><CoinIcon color="pink" size="xs" /></el-icon>
                </div>
                <div class="text-xl font-bold text-zinc-100 mt-2">
                    0.00 ₽ (0.0%)
                </div>
                <div class="text-xs text-zinc-500 mt-1">
                    Банковские расходы покрывает платформа
                </div>
            </div>

            <div class="kpi-card bg-zinc-900 border border-zinc-800 rounded-xl p-4">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span>Текущий режим</span>
                    <el-icon :class="autoEnabled ? 'text-emerald-400' : 'text-amber-400'"><Warning /></el-icon>
                </div>
                <div class="text-lg font-bold mt-2" :class="autoEnabled ? 'text-emerald-400' : 'text-amber-400'">
                    {{ autoEnabled ? 'Автовыплаты ВКЛ' : 'Ручная проверка' }}
                </div>
                <div class="text-xs text-zinc-500 mt-1">
                    {{ autoEnabled ? 'Запуск в Пн 06:00 UTC' : 'Крон формирует драфт' }}
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="border-b border-zinc-800 flex items-center gap-6">
            <button
                class="pb-3 text-sm font-medium border-b-2 transition-colors"
                :class="activeTab === 'queue' ? 'border-pink-500 text-pink-400' : 'border-transparent text-zinc-400 hover:text-zinc-200'"
                @click="activeTab = 'queue'"
            >
                Очередь / Предпросмотр след. выплаты
                <span class="ml-1.5 px-2 py-0.5 rounded-full text-xs bg-zinc-800 text-zinc-300">
                    {{ preview?.total_idols_count ?? 0 }}
                </span>
            </button>
            <button
                class="pb-3 text-sm font-medium border-b-2 transition-colors"
                :class="activeTab === 'history' ? 'border-pink-500 text-pink-400' : 'border-transparent text-zinc-400 hover:text-zinc-200'"
                @click="activeTab = 'history'"
            >
                История выплат
                <span class="ml-1.5 px-2 py-0.5 rounded-full text-xs bg-zinc-800 text-zinc-300">
                    {{ batches?.total ?? 0 }}
                </span>
            </button>
        </div>

        <!-- TAB 1: Queue / Next Payout Preview -->
        <div v-if="activeTab === 'queue'" class="space-y-4">
            <!-- Filter Bar -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-zinc-900/60 p-3 rounded-xl border border-zinc-800">
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <div class="relative w-full sm:w-64">
                        <el-input
                            v-model="queueSearch"
                            placeholder="Поиск по имени, email, телефону..."
                            clearable
                            size="default"
                            :prefix-icon="Search"
                        />
                    </div>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <button
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                        :class="thresholdFilter === 'all' ? 'bg-pink-600 text-white' : 'bg-zinc-800 text-zinc-300 hover:bg-zinc-700'"
                        @click="thresholdFilter = 'all'"
                    >
                        Все ({{ preview?.total_idols_count ?? 0 }})
                    </button>
                    <button
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                        :class="thresholdFilter === 'eligible' ? 'bg-emerald-600 text-white' : 'bg-zinc-800 text-zinc-300 hover:bg-zinc-700'"
                        @click="thresholdFilter = 'eligible'"
                    >
                        Готовы к автобатчу ({{ preview?.total_eligible_count ?? 0 }})
                    </button>
                    <button
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                        :class="thresholdFilter === 'under_threshold' ? 'bg-amber-600 text-white' : 'bg-zinc-800 text-zinc-300 hover:bg-zinc-700'"
                        @click="thresholdFilter = 'under_threshold'"
                    >
                        Менее 1 000 ₽ или алерт
                    </button>
                </div>
            </div>

            <!-- Idols Queue Table -->
            <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-800/60 text-xs text-zinc-400 uppercase tracking-wider border-b border-zinc-800">
                        <tr>
                            <th class="py-3 px-4">Айдол</th>
                            <th class="py-3 px-4">Реквизиты СБП</th>
                            <th class="py-3 px-4">К выплате</th>
                            <th class="py-3 px-4">Антифрод</th>
                            <th class="py-3 px-4">Детализация</th>
                            <th class="py-3 px-4 text-right">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60">
                        <tr v-if="filteredIdols.length === 0">
                            <td colspan="6" class="py-8 text-center text-zinc-500">
                                Нет айдолов, соответствующих выбранным критериям.
                            </td>
                        </tr>
                        <tr
                            v-for="idol in filteredIdols"
                            :key="idol.idol_id"
                            class="hover:bg-zinc-800/30 transition-colors"
                        >
                            <!-- Idol Info -->
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="idol.avatar"
                                        :src="idol.avatar"
                                        class="w-9 h-9 rounded-full object-cover border border-zinc-700"
                                    />
                                    <div v-else class="w-9 h-9 rounded-full bg-zinc-800 text-zinc-300 font-bold flex items-center justify-center border border-zinc-700 text-xs">
                                        {{ idol.name?.charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <div class="font-semibold text-zinc-100 flex items-center gap-1.5">
                                            <span>{{ idol.name }}</span>
                                            <span v-if="idol.nickname && idol.nickname !== idol.name" class="text-xs text-zinc-400">
                                                (@{{ idol.nickname }})
                                            </span>
                                        </div>
                                        <div class="text-xs text-zinc-400">
                                            ИНН: {{ idol.tax_inn || 'Не указан' }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- SBP Requisites -->
                            <td class="py-3 px-4">
                                <div class="text-zinc-200 font-mono text-xs">{{ idol.payout_phone || '—' }}</div>
                                <div class="text-xs text-zinc-400">Банк: {{ idol.sbp_bank_id || '—' }}</div>
                            </td>

                            <!-- Balance -->
                            <td class="py-3 px-4">
                                <div class="font-bold text-zinc-100 text-base">
                                    {{ formatMoney(idol.available_for_payout) }} ₽
                                </div>
                                <div class="mt-0.5">
                                    <span
                                        v-if="idol.meets_threshold"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-emerald-950/60 text-emerald-400 border border-emerald-800/40"
                                    >
                                        &ge; 1 000 ₽ (Автобатч)
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-zinc-800 text-zinc-400"
                                    >
                                        &lt; 1 000 ₽ (Только вручную)
                                    </span>
                                </div>
                            </td>

                            <!-- Anti-fraud Status -->
                            <td class="py-3 px-4">
                                <span
                                    v-if="!idol.has_antifraud_warning"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-950/60 text-emerald-400 border border-emerald-800/50"
                                >
                                    <el-icon><CircleCheck /></el-icon>
                                    В норме
                                </span>
                                <div v-else class="space-y-1">
                                    <span
                                        v-if="idol.antifraud_details?.requisites_changed_recently"
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-950/80 text-amber-300 border border-amber-800"
                                        :title="`Реквизиты изменены ${idol.antifraud_details?.requisites_hours_ago} ч назад`"
                                    >
                                        <el-icon><Warning /></el-icon>
                                        Смена реквизитов (&lt; 24ч)
                                    </span>
                                    <span
                                        v-if="idol.antifraud_details?.account_too_new"
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-950/80 text-amber-300 border border-amber-800"
                                    >
                                        <el-icon><Warning /></el-icon>
                                        Новый аккаунт (&lt; 72ч)
                                    </span>
                                </div>
                            </td>

                            <!-- Breakdown Button -->
                            <td class="py-3 px-4">
                                <button
                                    class="text-xs text-pink-400 hover:text-pink-300 flex items-center gap-1 font-medium underline underline-offset-4"
                                    @click="openBreakdown(idol)"
                                >
                                    <el-icon><Document /></el-icon>
                                    <span>За что начислено</span>
                                </button>
                            </td>

                            <!-- Actions -->
                            <td class="py-3 px-4 text-right">
                                <button
                                    class="px-3 py-1.5 bg-pink-600 hover:bg-pink-500 text-white rounded-lg text-xs font-medium transition-colors shadow-sm"
                                    @click="openSinglePayoutModal(idol)"
                                >
                                    Выплатить вне очереди
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: Payout History -->
        <div v-if="activeTab === 'history'" class="space-y-6">
            <!-- Batches Sub-section -->
            <div class="space-y-3">
                <h3 class="text-base font-semibold text-zinc-100 flex items-center gap-2">
                    <el-icon class="text-pink-400"><Tickets /></el-icon>
                    Реестры выплат (Батчи)
                </h3>
                <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-800/60 text-xs text-zinc-400 uppercase tracking-wider border-b border-zinc-800">
                            <tr>
                                <th class="py-3 px-4">Номер реестра</th>
                                <th class="py-3 px-4">Статус</th>
                                <th class="py-3 px-4">Сумма</th>
                                <th class="py-3 px-4">Получателей</th>
                                <th class="py-3 px-4">Успешно / Ошибок</th>
                                <th class="py-3 px-4">Тип / Администратор</th>
                                <th class="py-3 px-4">Дата исполнения</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/60">
                            <tr v-if="!batches?.data?.length">
                                <td colspan="7" class="py-6 text-center text-zinc-500">
                                    История реестров пуста.
                                </td>
                            </tr>
                            <tr v-for="b in batches?.data" :key="b.id" class="hover:bg-zinc-800/30">
                                <td class="py-3 px-4 font-mono font-medium text-zinc-200">
                                    {{ b.batch_number }}
                                </td>
                                <td class="py-3 px-4">
                                    <span
                                        class="px-2 py-0.5 rounded text-xs font-semibold"
                                        :class="{
                                            'bg-emerald-950 text-emerald-400 border border-emerald-800': b.status === 'completed',
                                            'bg-amber-950 text-amber-400 border border-amber-800': b.status === 'pending_approval' || b.status === 'processing',
                                            'bg-rose-950 text-rose-400 border border-rose-800': b.status === 'failed' || b.status === 'partially_failed',
                                        }"
                                    >
                                        {{ b.status }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-bold text-zinc-100">
                                    {{ formatMoney(b.total_amount) }} ₽
                                </td>
                                <td class="py-3 px-4 text-zinc-300">
                                    {{ b.total_recipients }}
                                </td>
                                <td class="py-3 px-4 text-xs">
                                    <span class="text-emerald-400 font-semibold">{{ b.successful_count }}</span> /
                                    <span class="text-rose-400 font-semibold">{{ b.failed_count }}</span>
                                </td>
                                <td class="py-3 px-4 text-xs text-zinc-400">
                                    <div v-if="b.is_automated" class="text-emerald-400 font-medium">Автоматический (крон)</div>
                                    <div v-else class="text-zinc-300">Ручной ({{ b.approver_name || 'Администратор' }})</div>
                                </td>
                                <td class="py-3 px-4 text-xs text-zinc-400">
                                    {{ b.executed_at ? new Date(b.executed_at).toLocaleString('ru-RU') : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Individual Transactions Sub-section -->
            <div class="space-y-3">
                <h3 class="text-base font-semibold text-zinc-100 flex items-center gap-2">
                    <el-icon class="text-pink-400"><Wallet /></el-icon>
                    Все транзакции выплат по СБП
                </h3>
                <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-800/60 text-xs text-zinc-400 uppercase tracking-wider border-b border-zinc-800">
                            <tr>
                                <th class="py-3 px-4">ID</th>
                                <th class="py-3 px-4">Получатель</th>
                                <th class="py-3 px-4">Сумма</th>
                                <th class="py-3 px-4">Статус</th>
                                <th class="py-3 px-4">Чек ФНС</th>
                                <th class="py-3 px-4">Дата</th>
                                <th class="py-3 px-4 text-right">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/60">
                            <tr v-if="!transactions?.data?.length">
                                <td colspan="7" class="py-6 text-center text-zinc-500">
                                    Транзакций выплат пока нет.
                                </td>
                            </tr>
                            <tr v-for="tx in transactions?.data" :key="tx.id" class="hover:bg-zinc-800/30">
                                <td class="py-3 px-4 font-mono text-xs text-zinc-400">
                                    #{{ tx.id }}
                                </td>
                                <td class="py-3 px-4 font-medium text-zinc-200">
                                    {{ tx.user_name }}
                                </td>
                                <td class="py-3 px-4 font-bold text-zinc-100">
                                    {{ formatMoney(tx.amount) }} ₽
                                </td>
                                <td class="py-3 px-4">
                                    <span
                                        class="px-2 py-0.5 rounded text-xs font-semibold"
                                        :class="{
                                            'bg-emerald-950 text-emerald-400 border border-emerald-800': tx.status === 'completed',
                                            'bg-amber-950 text-amber-400 border border-amber-800': tx.status === 'pending_bank',
                                            'bg-rose-950 text-rose-400 border border-rose-800': tx.status === 'failed',
                                        }"
                                    >
                                        {{ tx.status_label || tx.status }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-xs">
                                    <a
                                        v-if="tx.metadata?.fns_receipt_url"
                                        :href="tx.metadata.fns_receipt_url"
                                        target="_blank"
                                        class="text-blue-400 hover:underline flex items-center gap-1"
                                    >
                                        Чек «Мой налог»
                                    </a>
                                    <span v-else class="text-zinc-500">—</span>
                                </td>
                                <td class="py-3 px-4 text-xs text-zinc-400">
                                    {{ new Date(tx.created_at).toLocaleString('ru-RU') }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <button
                                        v-if="tx.status === 'pending_bank'"
                                        class="text-xs text-blue-400 hover:text-blue-300 font-medium underline"
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

        <!-- DRAWER: Breakdown ("За что начислено") -->
        <el-drawer
            v-model="breakdownDrawerOpen"
            title="Детализация начислений айдола"
            direction="rtl"
            size="550px"
        >
            <div v-if="breakdownLoading" class="p-8 text-center text-zinc-400">
                <el-icon class="animate-spin text-3xl"><Loading /></el-icon>
                <div class="mt-2 text-sm">Загрузка структуры начислений...</div>
            </div>
            <div v-else-if="breakdownData" class="space-y-5">
                <!-- Idol Summary -->
                <div class="bg-zinc-900 border border-zinc-800 p-4 rounded-xl">
                    <div class="text-sm font-semibold text-zinc-200">
                        {{ breakdownData.idol?.name }}
                    </div>
                    <div class="text-xs text-zinc-400 mt-0.5">
                        Доступный баланс к выводу: <strong class="text-emerald-400">{{ formatMoney(breakdownData.idol?.available_for_payout) }} ₽</strong>
                    </div>

                    <!-- Category totals -->
                    <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-zinc-800 text-xs">
                        <div>
                            <span class="text-zinc-500">Заказы:</span>
                            <div class="font-bold text-zinc-200">{{ formatMoney(breakdownData.totals?.orders ?? 0) }} ₽</div>
                        </div>
                        <div>
                            <span class="text-zinc-500">Паки:</span>
                            <div class="font-bold text-zinc-200">{{ formatMoney(breakdownData.totals?.packs ?? 0) }} ₽</div>
                        </div>
                        <div>
                            <span class="text-zinc-500">Донаты:</span>
                            <div class="font-bold text-zinc-200">{{ formatMoney(breakdownData.totals?.donations ?? 0) }} ₽</div>
                        </div>
                    </div>
                </div>

                <!-- Itemized List -->
                <div>
                    <h4 class="text-xs uppercase font-semibold text-zinc-400 tracking-wider mb-2">
                        История начислений (последние операции)
                    </h4>
                    <div class="space-y-2 max-h-[60vh] overflow-y-auto pr-1">
                        <div
                            v-for="item in breakdownData.items"
                            :key="item.id"
                            class="p-3 bg-zinc-900/80 border border-zinc-800 rounded-lg text-xs space-y-1.5"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-zinc-200">{{ item.type_label }}</span>
                                <span class="font-bold text-emerald-400">+{{ formatMoney(item.amount) }} ₽</span>
                            </div>

                            <!-- Order breakdown details -->
                            <div v-if="item.order" class="text-zinc-400 space-y-0.5">
                                <div>Заказ #{{ item.order.id }}: {{ item.order.service_title }}</div>
                                <div class="text-zinc-500">
                                    Клиент: {{ item.order.customer_name }} | Сумма услуги: {{ formatMoney(item.order.gross_amount) }} ₽ | Комиссия (12%): −{{ formatMoney(item.order.fee_amount) }} ₽
                                </div>
                            </div>

                            <!-- Pack breakdown details -->
                            <div v-else-if="item.pack" class="text-zinc-400 space-y-0.5">
                                <div>Пак: {{ item.pack.title }}</div>
                                <div class="text-zinc-500">
                                    Цена: {{ formatMoney(item.pack.gross_amount) }} ₽ | Комиссия: −{{ formatMoney(item.pack.fee_amount) }} ₽
                                </div>
                            </div>

                            <!-- Donation details -->
                            <div v-else-if="item.donation" class="text-zinc-400">
                                Донат от: {{ item.donation.donor_name }}
                            </div>

                            <div class="text-[10px] text-zinc-500 pt-1 border-t border-zinc-800/60">
                                {{ new Date(item.date).toLocaleString('ru-RU') }}
                            </div>
                        </div>
                        <div v-if="!breakdownData.items?.length" class="text-center py-6 text-zinc-500 text-xs">
                            Нет завершённых начислений.
                        </div>
                    </div>
                </div>
            </div>
        </el-drawer>

        <!-- MODAL: Out-of-turn Single Payout -->
        <el-dialog
            v-model="singleModalOpen"
            title="Внеочередная выплата айдолу"
            width="480px"
        >
            <div v-if="selectedIdol" class="space-y-4 text-sm">
                <div class="p-3 bg-zinc-900 border border-zinc-800 rounded-lg">
                    <div class="font-semibold text-zinc-100">{{ selectedIdol.name }}</div>
                    <div class="text-xs text-zinc-400 mt-0.5">
                        СБП: {{ selectedIdol.payout_phone }} (Банк: {{ selectedIdol.sbp_bank_id }})
                    </div>
                    <div class="text-xs text-zinc-400 mt-1">
                        Доступно к выплате: <strong class="text-emerald-400">{{ formatMoney(selectedIdol.available_for_payout) }} ₽</strong>
                    </div>
                </div>

                <!-- Amount selection -->
                <div class="space-y-2">
                    <label class="text-xs font-medium text-zinc-300">Сумма выплаты</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer text-xs text-zinc-300">
                            <input
                                type="radio"
                                value="full"
                                v-model="payoutMode"
                                class="text-pink-600 focus:ring-pink-500"
                            />
                            <span>Вся сумма ({{ formatMoney(selectedIdol.available_for_payout) }} ₽)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer text-xs text-zinc-300">
                            <input
                                type="radio"
                                value="custom"
                                v-model="payoutMode"
                                class="text-pink-600 focus:ring-pink-500"
                            />
                            <span>Своя сумма</span>
                        </label>
                    </div>

                    <div v-if="payoutMode === 'custom'" class="mt-2">
                        <el-input-number
                            v-model="customAmount"
                            :min="1"
                            :max="Number(selectedIdol.available_for_payout)"
                            :precision="2"
                            :step="100"
                            class="w-full"
                        />
                    </div>
                </div>

                <!-- Anti-fraud Warning Alert (if active) -->
                <div v-if="selectedIdol.has_antifraud_warning" class="p-3 bg-amber-950/60 border border-amber-700/60 rounded-lg space-y-2">
                    <div class="flex items-center gap-2 text-xs font-semibold text-amber-300">
                        <el-icon><Warning /></el-icon>
                        Внимание: Сработало антифрод-ограничение
                    </div>
                    <p class="text-xs text-amber-200/80">
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
                        <span class="text-xs text-amber-200 font-medium">
                            Я проверил реквизиты и подтверждаю снятие ограничения
                        </span>
                    </label>
                </div>

                <div class="pt-2 text-xs text-zinc-500">
                    Средства будут мгновенно переведены через Mandarin BaaS по номеру СБП.
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button class="btn-secondary" @click="singleModalOpen = false">Отмена</button>
                    <button
                        class="btn-primary"
                        :disabled="!canSubmitSinglePayout || singleLoading"
                        @click="submitSinglePayout"
                    >
                        <el-icon v-if="singleLoading" class="animate-spin"><Loading /></el-icon>
                        <span>Отправить {{ formatMoney(currentSingleAmount) }} ₽ в СБП</span>
                    </button>
                </div>
            </template>
        </el-dialog>

        <!-- MODAL: Batch Dispatch Confirmation -->
        <el-dialog
            v-model="batchModalOpen"
            title="Подтверждение пакетной выплаты"
            width="460px"
        >
            <div class="space-y-3 text-sm text-zinc-300">
                <p>
                    Вы собираетесь отправить реестр выплат для <strong>{{ preview?.total_eligible_count ?? 0 }}</strong> айдолов на общую сумму <strong>{{ formatMoney(preview?.total_eligible_amount ?? 0) }} ₽</strong>.
                </p>
                <div class="p-3 bg-zinc-900 border border-zinc-800 rounded-lg text-xs space-y-1">
                    <div>Метод: <strong>СБП (B2C) через номинальный счёт</strong></div>
                    <div>Банковская комиссия: <strong>0.00 ₽ (0%)</strong></div>
                    <div>Фискализация: <strong>Автоматическая регистрация в ФНС РФ</strong></div>
                </div>
                <p class="text-xs text-zinc-500">
                    Операция необратима: средства будут списаны с балансов айдолов и отправлены в банк.
                </p>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button class="btn-secondary" @click="batchModalOpen = false">Отмена</button>
                    <button
                        class="btn-primary"
                        :disabled="batchLoading"
                        @click="submitBatchDispatch"
                    >
                        <el-icon v-if="batchLoading" class="animate-spin"><Loading /></el-icon>
                        <span>Подтвердить и отправить реестр</span>
                    </button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
.btn-primary {
    @apply inline-flex items-center gap-1.5 px-4 py-2 bg-pink-600 hover:bg-pink-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-semibold rounded-lg transition-colors shadow-sm;
}
.btn-secondary {
    @apply inline-flex items-center gap-1.5 px-3 py-2 bg-zinc-800 hover:bg-zinc-700 disabled:opacity-50 text-zinc-200 text-xs font-medium rounded-lg transition-colors border border-zinc-700/60;
}
</style>
