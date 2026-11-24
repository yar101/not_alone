<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import {
    Check,
    Close,
    WarningFilled,
    CircleCheckFilled,
    InfoFilled,
    RefreshRight,
    Cellphone,
    Document,
    Money,
    OfficeBuilding,
    ArrowLeft,
    Loading,
} from '@element-plus/icons-vue';
import { ElNotification } from 'element-plus';

defineOptions({ layout: AppLayout });

const props = defineProps({
    inn: {
        type: String,
        default: null,
    },
    taxStatus: {
        type: String,
        default: 'none', // 'none' | 'pending_binding' | 'self_employed' | 'rejected'
    },
    taxVerifiedAt: {
        type: String,
        default: null,
    },
    payoutPhone: {
        type: String,
        default: null,
    },
    sbpBankId: {
        type: String,
        default: null,
    },
    isPayoutReady: {
        type: Boolean,
        default: false,
    },
    cooldownUntil: {
        type: String,
        default: null,
    },
    cooldownReason: {
        type: String,
        default: null,
    },
    sbpBanks: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

// ── Form 1: INN ──────────────────────────────────────────────
const innForm = useForm({
    inn: props.inn || '',
});

function submitInn() {
    innForm.post(route('profile.idol_onboarding.inn'), {
        preserveScroll: true,
        onSuccess: () => {
            ElNotification({
                title: 'ИНН сохранён',
                message: page.props.flash?.success || 'Запрос отправлен в ФНС.',
                type: 'success',
            });
        },
    });
}

// ── Form 2: Payout Requisites ────────────────────────────────
const requisitesForm = useForm({
    phone: props.payoutPhone || '',
    bank_id: props.sbpBankId || (props.sbpBanks[0]?.id || '100000000004'),
});

function saveRequisites() {
    requisitesForm.post(route('profile.idol_onboarding.payout_requisites'), {
        preserveScroll: true,
        onSuccess: () => {
            ElNotification({
                title: 'Реквизиты сохранены',
                message: page.props.flash?.success || 'Реквизиты СБП обновлены.',
                type: 'success',
            });
        },
    });
}

// ── Sync Tax Status ──────────────────────────────────────────
const isSyncing = ref(false);

function syncStatus() {
    isSyncing.value = true;
    innForm.post(route('profile.idol_onboarding.sync'), {
        preserveScroll: true,
        onFinish: () => {
            isSyncing.value = false;
        },
        onSuccess: () => {
            ElNotification({
                title: 'Статус синхронизирован',
                message: page.props.flash?.success || 'Статус самозанятого обновлен.',
                type: 'success',
            });
        },
    });
}

const statusBadge = computed(() => {
    switch (props.taxStatus) {
        case 'self_employed':
            return {
                text: 'Самозанятый подтверждён (422-ФЗ)',
                class: 'badge--success',
                icon: CircleCheckFilled,
            };
        case 'pending_binding':
            return {
                text: 'Ожидает подтверждения в «Мой налог»',
                class: 'badge--warning',
                icon: WarningFilled,
            };
        case 'rejected':
            return {
                text: 'Отклонён ФНС / Лимит превышен',
                class: 'badge--danger',
                icon: Close,
            };
        default:
            return {
                text: 'Не привязан',
                class: 'badge--neutral',
                icon: InfoFilled,
            };
    }
});

const isVerified = computed(() => props.taxStatus === 'self_employed');
</script>

<template>
    <Head title="Выплаты самозанятого (СБП)" />

    <div class="onboarding-page py-8 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
        <!-- Navigation Back -->
        <div class="mb-6">
            <Link :href="route('wallet.show')" class="back-link inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition-colors">
                <el-icon><ArrowLeft /></el-icon>
                <span>Вернуться в Кошелёк</span>
            </Link>
        </div>

        <!-- Header -->
        <div class="onboarding-header mb-8">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-xl bg-pink-500/10 border border-pink-500/20 flex items-center justify-center text-pink-400 text-xl font-bold">
                    <CoinIcon color="pink" size="md" />
                </div>
                <div>
                    <h1 class="text-2xl font-black text-white tracking-tight">Подключение выплат самозанятого</h1>
                    <p class="text-sm text-slate-400">Mandarin BaaS • Выплаты по СБП (0% комиссия) • 422-ФЗ</p>
                </div>
            </div>
        </div>

        <!-- Cooldown Alert -->
        <div v-if="cooldownUntil" class="cooldown-banner mb-6 p-4 rounded-xl bg-rose-950/40 border border-rose-500/30 flex items-start gap-3">
            <el-icon class="text-rose-400 text-xl mt-0.5"><WarningFilled /></el-icon>
            <div>
                <h3 class="font-bold text-rose-200 text-sm">Действует ограничение по ИНН (кулдаун 365 дней)</h3>
                <p class="text-xs text-rose-300/80 mt-1">
                    Повторная привязка данного ИНН возможна только после окончания периода ожидания.
                    {{ cooldownReason ? `Причина: ${cooldownReason}` : '' }}
                </p>
            </div>
        </div>

        <!-- Platform Guarantees & Legal Info Box -->
        <div class="legal-info-card mb-8 p-5 rounded-2xl bg-white/[0.03] border border-white/10 backdrop-blur-md">
            <h2 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                <el-icon class="text-blue-400"><InfoFilled /></el-icon>
                <span>Финансовые правила и гарантии</span>
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-slate-300">
                <div class="info-pill p-3 rounded-xl bg-white/[0.02] border border-white/5">
                    <div class="font-semibold text-white mb-1">0% комиссия за вывод</div>
                    <p class="text-slate-400">Все банковские расходы на выплаты по СБП платформа Not Alone покрывает за свой счёт.</p>
                </div>
                <div class="info-pill p-3 rounded-xl bg-white/[0.02] border border-white/5">
                    <div class="font-semibold text-white mb-1">Номинальный счёт (ст. 860.1 ГК)</div>
                    <p class="text-slate-400">Средства хранятся в банке-партнёре Mandarin и защищены законом от блокировок платформы.</p>
                </div>
                <div class="info-pill p-3 rounded-xl bg-white/[0.02] border border-white/5">
                    <div class="font-semibold text-white mb-1">Авто-чеки ФНС (422-ФЗ)</div>
                    <p class="text-slate-400">Платформа автоматически регистрирует чеки в «Мой налог» в момент закрытия сделок.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6">
            <!-- STEP 1: ИНН самозанятого -->
            <div class="onboarding-card p-6 rounded-2xl bg-[#141424] border border-white/10 shadow-xl">
                <div class="flex items-center justify-between mb-4 pb-4 border-b border-white/5">
                    <div class="flex items-center gap-2.5">
                        <span class="step-num w-6 h-6 rounded-full bg-pink-500/20 text-pink-400 text-xs font-bold flex items-center justify-center">1</span>
                        <h2 class="text-base font-bold text-white">ИНН плательщика НПД («Мой налог»)</h2>
                    </div>
                    <div class="status-badge px-2.5 py-1 rounded-full text-xs font-medium flex items-center gap-1.5" :class="statusBadge.class">
                        <component :is="statusBadge.icon" class="w-3.5 h-3.5" />
                        <span>{{ statusBadge.text }}</span>
                    </div>
                </div>

                <form @submit.prevent="submitInn" class="space-y-4">
                    <div>
                        <label for="inn-input" class="block text-xs font-medium text-slate-300 mb-1.5">
                            12-значный ИНН гражданина РФ
                        </label>
                        <div class="relative">
                            <input
                                id="inn-input"
                                v-model="innForm.inn"
                                type="text"
                                maxlength="12"
                                placeholder="Например: 500100732259"
                                class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-pink-500 transition-colors font-mono text-sm"
                                :disabled="innForm.processing || isVerified"
                            />
                            <div v-if="isVerified" class="absolute right-3 top-2.5 text-emerald-400 text-lg">
                                <el-icon><CircleCheckFilled /></el-icon>
                            </div>
                        </div>
                        <div v-if="innForm.errors.inn" class="text-rose-400 text-xs mt-1.5">
                            {{ innForm.errors.inn }}
                        </div>
                    </div>

                    <div v-if="!isVerified" class="flex items-center gap-3 pt-2">
                        <button
                            type="submit"
                            class="submit-inn-btn px-5 py-2.5 rounded-xl bg-pink-600 hover:bg-pink-500 text-white font-medium text-xs transition-colors flex items-center gap-2 shadow-lg shadow-pink-500/20"
                            :disabled="innForm.processing"
                        >
                            <el-icon v-if="innForm.processing" class="is-loading"><Loading /></el-icon>
                            <span>{{ inn ? 'Обновить ИНН' : 'Привязать ИНН' }}</span>
                        </button>

                        <button
                            v-if="taxStatus === 'pending_binding'"
                            type="button"
                            @click="syncStatus"
                            class="sync-btn px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-medium text-xs transition-colors flex items-center gap-2 border border-white/10"
                            :disabled="isSyncing"
                        >
                            <el-icon :class="{ 'is-loading': isSyncing }"><RefreshRight /></el-icon>
                            <span>Проверить статус подключения</span>
                        </button>
                    </div>
                </form>

                <!-- Instructions for Pending Binding -->
                <div v-if="taxStatus === 'pending_binding'" class="mt-5 p-4 rounded-xl bg-amber-500/10 border border-amber-500/20">
                    <h4 class="text-xs font-bold text-amber-300 mb-2 flex items-center gap-1.5">
                        <el-icon><InfoFilled /></el-icon>
                        <span>Шаги для подтверждения партнёрства:</span>
                    </h4>
                    <ol class="text-xs text-slate-300 list-decimal list-inside space-y-1.5 leading-relaxed">
                        <li>Откройте мобильное приложение <strong>«Мой налог»</strong> или веб-кабинет ФНС.</li>
                        <li>Перейдите в раздел <strong>«Профиль» → «Партнёры»</strong>.</li>
                        <li>В списке входящих запросов найдите <strong>Mandarin (ООО «Мандарин»)</strong> / Not Alone.</li>
                        <li>Нажмите <strong>«Подтвердить»</strong> и разрешите формирование чеков.</li>
                        <li>Вернитесь на эту страницу и нажмите кнопку <strong>«Проверить статус подключения»</strong>.</li>
                    </ol>
                </div>
            </div>

            <!-- STEP 2: Реквизиты выплат по СБП -->
            <div class="onboarding-card p-6 rounded-2xl bg-[#141424] border border-white/10 shadow-xl">
                <div class="flex items-center justify-between mb-4 pb-4 border-b border-white/5">
                    <div class="flex items-center gap-2.5">
                        <span class="step-num w-6 h-6 rounded-full bg-pink-500/20 text-pink-400 text-xs font-bold flex items-center justify-center">2</span>
                        <h2 class="text-base font-bold text-white">Реквизиты Системы быстрых платежей (СБП)</h2>
                    </div>
                    <div class="status-badge px-2.5 py-1 rounded-full text-xs font-medium flex items-center gap-1.5" :class="isPayoutReady ? 'badge--success' : 'badge--neutral'">
                        <component :is="isPayoutReady ? CircleCheckFilled : InfoFilled" class="w-3.5 h-3.5" />
                        <span>{{ isPayoutReady ? 'Выплаты готовы к автовыводу' : 'Требуется настройка' }}</span>
                    </div>
                </div>

                <form @submit.prevent="saveRequisites" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Phone -->
                        <div>
                            <label for="payout-phone-input" class="block text-xs font-medium text-slate-300 mb-1.5">
                                Номер телефона в СБП
                            </label>
                            <input
                                id="payout-phone-input"
                                v-model="requisitesForm.phone"
                                type="tel"
                                placeholder="+79998887766"
                                class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-pink-500 transition-colors font-mono text-sm"
                                :disabled="requisitesForm.processing"
                            />
                            <div v-if="requisitesForm.errors.phone" class="text-rose-400 text-xs mt-1.5">
                                {{ requisitesForm.errors.phone }}
                            </div>
                        </div>

                        <!-- Bank Selection -->
                        <div>
                            <label for="sbp-bank-select" class="block text-xs font-medium text-slate-300 mb-1.5">
                                Банк получателя
                            </label>
                            <select
                                id="sbp-bank-select"
                                v-model="requisitesForm.bank_id"
                                class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-pink-500 transition-colors text-sm"
                                :disabled="requisitesForm.processing"
                            >
                                <option v-for="bank in sbpBanks" :key="bank.id" :value="bank.id" class="bg-[#141424] text-white">
                                    {{ bank.name }}
                                </option>
                            </select>
                            <div v-if="requisitesForm.errors.bank_id" class="text-rose-400 text-xs mt-1.5">
                                {{ requisitesForm.errors.bank_id }}
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 flex items-center justify-between">
                        <button
                            type="submit"
                            class="save-requisites-btn px-5 py-2.5 rounded-xl bg-pink-600 hover:bg-pink-500 text-white font-medium text-xs transition-colors flex items-center gap-2 shadow-lg shadow-pink-500/20"
                            :disabled="requisitesForm.processing"
                        >
                            <el-icon v-if="requisitesForm.processing" class="is-loading"><Loading /></el-icon>
                            <span>Сохранить реквизиты СБП</span>
                        </button>

                        <div class="text-[11px] text-slate-400">
                            Выплаты производятся еженедельно по понедельникам (от 1 000 ₽)
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.onboarding-page {
    min-height: calc(100vh - 120px);
}

.badge--success {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.25);
}

.badge--warning {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.25);
}

.badge--danger {
    background: rgba(244, 63, 94, 0.15);
    color: #fb7185;
    border: 1px solid rgba(244, 63, 94, 0.25);
}

.badge--neutral {
    background: rgba(255, 255, 255, 0.06);
    color: #94a3b8;
    border: 1px solid rgba(255, 255, 255, 0.1);
}
</style>
