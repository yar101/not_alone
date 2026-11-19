<script setup>
import { ref, computed, watch, onMounted, onUnmounted, h } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import AppSelect from '@/Components/AppSelect.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import TransactionLedger from '@/Components/Wallet/TransactionLedger.vue';
import axios from 'axios';
import { extractErrorMessage } from '@/Utils/error';
import { formatMoney } from '@/Utils/money';
import { ElNotification, ElTooltip, ElIcon } from 'element-plus';
import {
    Wallet,
    WalletFilled,
    Lock,
    Coin,
    Plus,
    Upload,
    TopRight,
    InfoFilled,
    Tickets,
    Loading,
    Check,
    Close,
    CircleCloseFilled,
    WarningFilled,
    RefreshRight,
    StarFilled,
} from '@element-plus/icons-vue';

const props = defineProps({
    wallet: {
        type: Object,
        required: true,
    },
    transactions: {
        type: Object,
        default: () => ({ data: [], prev_page_url: null, next_page_url: null }),
    },
    displayTransactions: {
        type: Array,
        default: () => [],
    },
    activeFilter: {
        type: String,
        default: 'all',
    },
    canDeposit: {
        type: Boolean,
        default: false,
    },
    isIdol: {
        type: Boolean,
        default: false,
    },
    canWithdraw: {
        type: Boolean,
        default: false,
    },
    depositFeePercent: {
        type: Number,
        default: 0,
    },
    withdrawalFeePercent: {
        type: Number,
        default: 0,
    },
    holdMinutes: {
        type: Number,
        default: 60,
    },
    coinPacks: {
        type: Array,
        default: () => [],
    },
    starterUsed: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const currentWallet = ref({ ...props.wallet });
const isDepositing = ref(false);
const isWithdrawing = ref(false);
const isConverting = ref(false);
let lastDepositTime = 0;

watch(
    () => props.wallet,
    (newWallet) => {
        if (newWallet) {
            if (isDepositing.value && Number(newWallet.balance) < Number(currentWallet.value.balance)) {
                return;
            }
            currentWallet.value = { ...newWallet };
        }
    },
    { deep: true }
);

function onWalletUpdated(e) {
    if (e.detail) {
        const prevBal = Number(currentWallet.value.balance || 0);
        const newBal = Number(e.detail.balance);
        const diff = newBal - prevBal;

        const prevPink = Number(currentWallet.value.pink_balance || 0);
        const hasPink = e.detail.pink_balance !== undefined;
        const newPink = hasPink ? Number(e.detail.pink_balance) : prevPink;
        const pinkDiff = newPink - prevPink;

        const isStaleBalance = isDepositing.value && newBal < prevBal;
        const targetBalance = isStaleBalance ? prevBal : newBal;
        const targetTotal = isStaleBalance ? Number(currentWallet.value.total_balance) : Number(e.detail.total_balance);

        currentWallet.value = {
            ...currentWallet.value,
            balance: targetBalance,
            held_balance: Number(e.detail.held_balance),
            total_balance: targetTotal,
            pink_balance: hasPink ? newPink : currentWallet.value.pink_balance,
            pink_held_balance: e.detail.pink_held_balance !== undefined ? Number(e.detail.pink_held_balance) : currentWallet.value.pink_held_balance,
            pink_total_balance: e.detail.pink_total_balance !== undefined ? Number(e.detail.pink_total_balance) : currentWallet.value.pink_total_balance,
        };

        if (!isDepositing.value && !isWithdrawing.value && !isConverting.value && Math.abs(diff) > 0.001) {
            const isRecentDeposit = (Date.now() - lastDepositTime) < 4000;
            if (!(isRecentDeposit && diff < 0)) {
                const isPlus = diff > 0;
                showWalletToast({
                    type: isPlus ? 'deposit' : 'admin',
                    title: 'Баланс обновлён',
                    message: `${isPlus ? '+' : '−'}${formatMoney(Math.abs(diff))}`,
                });
            }
        }

        const isIdolUser = Boolean(props.isIdol || page.props.auth?.user?.is_idol);
        if (isIdolUser && !isDepositing.value && !isWithdrawing.value && !isConverting.value && Math.abs(pinkDiff) > 0.001) {
            const isPlus = pinkDiff > 0;
            showWalletToast({
                type: isPlus ? 'deposit' : 'admin',
                title: 'Заработок обновлён',
                message: `${isPlus ? '+' : '−'}${formatMoney(Math.abs(pinkDiff))}`,
            });
        }
    }
    router.reload({
        only: ['transactions', 'displayTransactions'],
        preserveScroll: true,
        preserveState: true,
    });
}

onMounted(() => {
    window.addEventListener('notalone:wallet-updated', onWalletUpdated);
    const userId = page.props.auth?.user?.id;
    if (window.Echo && userId) {
        window.Echo.private(`App.Models.User.${userId}`)
            .listen('.wallet.updated', (data) => {
                onWalletUpdated({
                    detail: {
                        balance: data.balance,
                        held_balance: data.held_balance,
                        total_balance: data.total_balance,
                        pink_balance: data.pink_balance,
                        pink_held_balance: data.pink_held_balance,
                        pink_total_balance: data.pink_total_balance,
                    },
                });
            });
    }
});

onUnmounted(() => {
    window.removeEventListener('notalone:wallet-updated', onWalletUpdated);
    const userId = page.props.auth?.user?.id;
    if (window.Echo && userId) {
        window.Echo.leave(`App.Models.User.${userId}`);
    }
});

// ── Navigation View ──────────────────────────────────────────
const getInitialView = () => {
    if (typeof window === 'undefined') return 'overview';
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab') || params.get('view');
    if (tabParam === 'history' || params.has('cursor') || (params.has('filter') && params.get('filter') !== 'all')) {
        return 'history';
    }
    return 'overview';
};

const activeView = ref(getInitialView()); // 'overview' | 'history'
const actionTab = ref('deposit'); // 'deposit' | 'withdraw'
const availableTipVisible = ref(false);
const pinkAvailableTipVisible = ref(false);
const pinkFrozenTipVisible = ref(false);

// ── Idol Conversion Modal State ──────────────────────────────
const showConvertModal = ref(false);
const convertAmount = ref('');
const convertError = ref(null);

function openConvertModal() {
    convertError.value = null;
    convertAmount.value = '';
    showConvertModal.value = true;
}

function convertPreset(pct) {
    const max = Number(currentWallet.value.pink_balance || 0);
    if (max <= 0) {
        convertAmount.value = 0;
        return;
    }
    if (pct === 100) {
        convertAmount.value = Number(max.toFixed(2));
        return;
    }
    const val = Math.floor(max * (pct / 100));
    convertAmount.value = val > 0 ? val : 0;
}

async function handleConvert() {
    const amt = Number(convertAmount.value);
    const max = Number(currentWallet.value.pink_balance || 0);
    if (!amt || amt < 1) {
        convertError.value = 'Минимальная сумма обмена — 1';
        return;
    }
    if (amt > max) {
        convertError.value = 'Сумма превышает доступный баланс';
        return;
    }

    isConverting.value = true;
    convertError.value = null;
    try {
        const { data } = await axios.post(route('wallet.convert_pink'), {
            amount: amt,
        });

        currentWallet.value.pink_balance = Number(data.pink_balance);
        currentWallet.value.pink_total_balance = Number(data.pink_balance) + Number(currentWallet.value.pink_held_balance || 0);
        currentWallet.value.balance = Number(data.balance);
        currentWallet.value.total_balance = Number(data.balance) + Number(currentWallet.value.held_balance || 0);

        if (page.props.auth?.user?.wallet) {
            page.props.auth.user.wallet.pink_balance = Number(data.pink_balance);
            page.props.auth.user.wallet.balance = Number(data.balance);
        }

        showWalletToast({
            type: 'deposit',
            title: 'Обмен выполнен',
            message: `+${formatMoney(amt)} зачислено`,
        });

        showConvertModal.value = false;
        convertAmount.value = '';

        router.reload({ only: ['transactions', 'displayTransactions', 'wallet', 'auth'] });
    } catch (e) {
        convertError.value = extractErrorMessage(e, 'Не удалось выполнить обмен');
    } finally {
        isConverting.value = false;
    }
}

function setView(view) {
    activeView.value = view;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        if (view === 'history') {
            url.searchParams.set('tab', 'history');
        } else {
            url.searchParams.delete('tab');
            url.searchParams.delete('view');
            url.searchParams.delete('page');
            url.searchParams.delete('cursor');
            url.searchParams.delete('filter');
        }
        const state = (typeof history !== 'undefined' && history.state) || {};
        if (state.page) {
            state.page.url = url.pathname + url.search;
        }
        window.history.replaceState(state, '', url.pathname + url.search);
    }
}

function getPaginationUrl(url) {
    if (!url) return null;
    try {
        const origin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';
        const parsed = new URL(url, origin);
        parsed.searchParams.set('tab', 'history');
        return parsed.pathname + parsed.search;
    } catch {
        return url;
    }
}

function syncViewFromUrl() {
    activeView.value = getInitialView();
}

onMounted(() => {
    window.addEventListener('popstate', syncViewFromUrl);
});

onUnmounted(() => {
    window.removeEventListener('popstate', syncViewFromUrl);
});

// ── Financial Actions & Coin Packs ────────────────────────────
const selectedPackId = ref(null);
const withdrawAmount = ref('');

const availablePacks = computed(() => {
    return (props.coinPacks || []).filter(pack => {
        if (pack.is_starter && props.starterUsed) {
            return false;
        }
        return true;
    });
});

const starterPack = computed(() => {
    if (props.starterUsed) return null;
    return (props.coinPacks || []).find(pack => pack.is_starter) || null;
});

const regularPacks = computed(() => {
    return (props.coinPacks || []).filter(pack => !pack.is_starter);
});

const displayPacks = computed(() => {
    const list = [];
    if (starterPack.value) {
        list.push(starterPack.value);
    }
    regularPacks.value.forEach(p => list.push(p));
    return list;
});

const selectedPack = computed(() => {
    if (!displayPacks.value.length) return null;
    return displayPacks.value.find(p => p.id === selectedPackId.value) || displayPacks.value[0];
});

watch(
    () => displayPacks.value,
    (packs) => {
        if (packs.length > 0 && (!selectedPackId.value || !packs.some(p => p.id === selectedPackId.value))) {
            const starter = packs.find(p => p.is_starter);
            selectedPackId.value = starter ? starter.id : packs[0].id;
        }
    },
    { immediate: true }
);

// ── Fee & Total Payment Calculations (Fee added to rubles to pay) ──
const totalPriceRub = computed(() => {
    if (!selectedPack.value) return 0;
    const coins = Number(selectedPack.value.coins) || 0;
    const feePct = Number(props.depositFeePercent) || 0;
    const fee = Math.round(coins * (feePct / 100) * 100) / 100;
    return Math.round((coins + fee) * 100) / 100;
});

const withdrawFeeAmount = computed(() => {
    const amt = Number(withdrawAmount.value) || 0;
    if (amt <= 0 || !props.withdrawalFeePercent) return 0;
    return Math.round(amt * (props.withdrawalFeePercent / 100) * 100) / 100;
});

const withdrawPayoutAmount = computed(() => {
    const amt = Number(withdrawAmount.value) || 0;
    if (amt <= 0) return 0;
    return Math.max(0, Math.round((amt - withdrawFeeAmount.value) * 100) / 100);
});

// ── History Filter ───────────────────────────────────────────
const activeHistoryTab = ref(props.activeFilter || 'all');

watch(
    () => props.activeFilter,
    (newFilter) => {
        if (newFilter) {
            activeHistoryTab.value = newFilter;
        }
    }
);

function setHistoryFilter(tabId) {
    if (activeHistoryTab.value === tabId && activeView.value === 'history') return;
    activeHistoryTab.value = tabId;
    activeView.value = 'history';

    router.get(
        route('wallet.show'),
        { tab: 'history', filter: tabId },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

const historyTabs = computed(() => {
    const tabs = [
        { id: 'all', label: 'Все' },
        { id: 'deposit', label: 'Пополнения' },
        { id: 'orders', label: 'Заказы' },
        { id: 'holds', label: 'Заморозки' },
        { id: 'packs', label: 'Контент' },
        { id: 'donations', label: 'Донаты' },
    ];
    if (props.isIdol) {
        tabs.push({ id: 'withdrawal', label: 'Вывод' });
        tabs.push({ id: 'conversions', label: 'Обмен (1:1)' });
    }
    return tabs;
});

const historyTabOptions = computed(() => {
    return historyTabs.value.map(tab => ({
        value: tab.id,
        label: tab.label,
    }));
});

const filteredTransactions = computed(() => {
    return props.displayTransactions || [];
});

// ── Wallet Toast (Glassmorphism) ─────────────────────────────
function showWalletToast({
    title,
    message,
    type = 'deposit', // 'deposit' | 'withdraw' | 'hold' | 'payout' | 'refund' | 'admin' | 'danger' | 'warning'
    iconComp,
    duration = 4500,
}) {
    let defaultIcon = Coin;
    let iconClass = `icon--${type}`;

    if (type === 'deposit') {
        defaultIcon = Coin;
        iconClass = 'icon--deposit';
    } else if (type === 'withdraw') {
        defaultIcon = Wallet;
        iconClass = 'icon--withdraw';
    } else if (type === 'hold') {
        defaultIcon = Lock;
        iconClass = 'icon--hold';
    } else if (type === 'payout') {
        defaultIcon = Check;
        iconClass = 'icon--payout';
    } else if (type === 'refund') {
        defaultIcon = RefreshRight;
        iconClass = 'icon--refund';
    } else if (type === 'admin') {
        defaultIcon = TopRight;
        iconClass = 'icon--admin';
    } else if (type === 'danger') {
        defaultIcon = CircleCloseFilled;
        iconClass = 'icon--danger';
    } else if (type === 'warning') {
        defaultIcon = WarningFilled;
        iconClass = 'icon--warning';
    }

    const finalIcon = iconComp || defaultIcon;

    ElNotification({
        duration,
        position: 'top-right',
        offset: 70,
        customClass: type === 'warning' ? 'app-notif app-notif--warn' : 'app-notif',
        showClose: true,
        message: h('div', { class: 'app-notif__body' }, [
            h('div', { class: `app-notif__icon ${iconClass}` }, [
                h(ElIcon, null, { default: () => h(finalIcon) }),
            ]),
            h('div', { class: 'app-notif__text' }, [
                h('p', { class: 'app-notif__title' }, title),
                ...(message ? [h('p', { class: 'app-notif__msg' }, message)] : []),
            ]),
        ]),
    });
}

// ── Deposit ──────────────────────────────────────────────────
async function handleDeposit() {
    if (isDepositing.value) return;
    if (!selectedPack.value) {
        showWalletToast({
            type: 'warning',
            title: 'Выберите пакет',
            message: 'Пожалуйста, выберите пакет монет для пополнения',
        });
        return;
    }

    isDepositing.value = true;
    lastDepositTime = Date.now();
    try {
        const { data } = await axios.post(route('wallet.deposit_pack'), {
            coin_pack_id: selectedPack.value.id,
        });

        currentWallet.value.balance = Number(data.balance);
        currentWallet.value.total_balance = Number(data.balance) + Number(currentWallet.value.held_balance || 0);

        if (page.props.auth?.user?.wallet) {
            page.props.auth.user.wallet.balance = Number(data.balance);
        }

        showWalletToast({
            type: 'deposit',
            title: 'Пополнение счёта',
            message: `+${formatMoney(selectedPack.value.coins)} зачислено на баланс`,
        });

        router.reload({
            only: ['transactions', 'displayTransactions', 'wallet', 'auth', 'starterUsed', 'coinPacks'],
            onFinish: () => {
                isDepositing.value = false;
            },
        });
    } catch (e) {
        showWalletToast({
            type: 'danger',
            title: 'Ошибка пополнения',
            message: extractErrorMessage(e, 'Не удалось выполнить пополнение'),
        });
        isDepositing.value = false;
    }
}

// ── Withdraw ─────────────────────────────────────────────────
async function handleWithdraw() {
    const amt = Number(withdrawAmount.value);
    const maxPink = Number(currentWallet.value.pink_balance || 0);
    if (!amt || amt < 100) {
        showWalletToast({
            type: 'warning',
            title: 'Неверная сумма',
            message: 'Минимальная сумма вывода — 100',
        });
        return;
    }

    if (amt > maxPink) {
        showWalletToast({
            type: 'warning',
            title: 'Недостаточно средств',
            message: 'Сумма превышает доступный баланс',
        });
        return;
    }

    isWithdrawing.value = true;
    try {
        const { data } = await axios.post(route('wallet.withdraw'), {
            amount: amt,
        });

        showWalletToast({
            type: 'withdraw',
            title: 'Заявка на вывод',
            message: `${formatMoney(amt)} отправлено на выплату`,
        });

        currentWallet.value.pink_balance = Number(data.pink_balance);
        currentWallet.value.pink_total_balance = Number(data.pink_balance) + Number(currentWallet.value.pink_held_balance || 0);
        withdrawAmount.value = '';

        if (page.props.auth?.user?.wallet) {
            page.props.auth.user.wallet.pink_balance = Number(data.pink_balance);
        }

        router.reload({ only: ['transactions', 'displayTransactions', 'wallet', 'auth'] });
    } catch (e) {
        showWalletToast({
            type: 'danger',
            title: 'Ошибка вывода',
            message: extractErrorMessage(e, 'Не удалось выполнить вывод средств'),
        });
    } finally {
        isWithdrawing.value = false;
    }
}

// ── Formatters ───────────────────────────────────────────────
function formatInteger(val) {
    return Number(val || 0).toLocaleString('ru-RU', {
        maximumFractionDigits: 0,
    });
}

function formatMinutes(m) {
    if (!m) return '60 минут';
    const n = Math.abs(Math.round(m));
    const mod10 = n % 10;
    const mod100 = n % 100;
    if (mod100 >= 11 && mod100 <= 19) return `${n} минут`;
    if (mod10 === 1) return `${n} минуту`;
    if (mod10 >= 2 && mod10 <= 4) return `${n} минуты`;
    return `${n} минут`;
}
</script>

<template>
    <Head title="Кошелёк" />
    <AppLayout>
        <div class="wallet-viewport">
            <!-- Background Ambient Lighting -->
            <div class="wallet-ambient ambient-blue" />

            <div class="wallet-wrapper">
                <!-- Top Navigation & View Switcher -->
                <div class="wallet-topbar">
                    <div class="wallet-brand">
                        <h1 class="wallet-brand__title">
                            <el-icon class="wallet-brand__title-icon"><Wallet /></el-icon>
                            <span>Кошелёк</span>
                        </h1>
                    </div>

                    <!-- 2 Main Tabs: Balance Overview vs History -->
                    <div class="wallet-views">
                        <button
                            type="button"
                            class="wallet-view-btn"
                            :class="{ 'wallet-view-btn--active': activeView === 'overview' }"
                            @click="setView('overview')"
                        >
                            <el-icon><Wallet /></el-icon>
                            <span>Баланс</span>
                        </button>

                        <button
                            type="button"
                            class="wallet-view-btn"
                            :class="{ 'wallet-view-btn--active': activeView === 'history' }"
                            @click="setView('history')"
                        >
                            <el-icon><Tickets /></el-icon>
                            <span>История</span>
                        </button>
                    </div>
                </div>

                <!-- ════════════ VIEW 1: MY BALANCE (100% ONE-SCREEN FIT) ════════════ -->
                <div v-if="activeView === 'overview'" class="wallet-overview">
                    <!-- SECTION 1 (IDOL ONLY): PINK COINS ACCOUNT (EARNINGS) -->
                    <template v-if="isIdol">
                        <div class="wallet-section-header">
                            <div class="wallet-section-title">
                                <CoinIcon color="pink" size="sm" />
                                <span>Счёт заработка</span>
                            </div>
                            <div class="wallet-section-actions">
                                <button
                                    type="button"
                                    class="wallet-pill-btn"
                                    @click="openConvertModal"
                                >
                                    <el-icon><RefreshRight /></el-icon>
                                    <span>Обменять (1:1)</span>
                                </button>
                            </div>
                        </div>

                        <div class="wallet-cards-grid">
                            <!-- Idol Pink Card 1: Available -->
                            <el-tooltip
                                v-model:visible="pinkAvailableTipVisible"
                                placement="top"
                                effect="dark"
                                popper-class="wallet-dark-tooltip"
                                trigger="click"
                                :hide-after="0"
                            >
                                <template #content>
                                    <div class="wallet-tip-text">
                                        Заработанные средства за заказы, паки и чаевые.<br>
                                        Доступны для вывода на карту (комиссия {{ props.withdrawalFeePercent > 0 ? `${props.withdrawalFeePercent}%` : '4%' }}) или обмена 1:1.
                                    </div>
                                </template>
                                <div
                                    class="wcard wcard--pink-available"
                                    :class="{ 'wcard--active': pinkAvailableTipVisible }"
                                    tabindex="0"
                                    role="button"
                                    aria-label="Карточка: Заработанный баланс"
                                >
                                    <div class="wcard__watermark wcard__watermark--pink">
                                        <i class="fa-solid fa-coins"></i>
                                    </div>
                                    <div class="wcard__top">
                                        <div class="wcard__badge wcard__badge--pink">
                                            <el-icon class="wcard__badge-icon"><WalletFilled /></el-icon>
                                            Заработано
                                        </div>
                                        <div class="wcard__info-trigger" aria-hidden="true">
                                            <el-icon><InfoFilled /></el-icon>
                                        </div>
                                    </div>
                                    <div class="wcard__amount-row">
                                        <span class="wcard__amount wcard__amount--pink">
                                            {{ formatMoney(currentWallet.pink_balance) }}
                                        </span>
                                        <CoinIcon color="pink" size="sm" />
                                    </div>
                                </div>
                            </el-tooltip>

                            <!-- Idol Pink Card 2: Frozen / Held -->
                            <el-tooltip
                                v-model:visible="pinkFrozenTipVisible"
                                placement="top"
                                effect="dark"
                                popper-class="wallet-dark-tooltip"
                                trigger="click"
                                :hide-after="0"
                            >
                                <template #content>
                                    <div class="wallet-tip-text">
                                        Оплата за завершённый заказ резервируется на {{ formatMinutes(holdMinutes) }}, после чего поступает на доступный баланс.
                                    </div>
                                </template>
                                <div
                                    class="wcard wcard--pink-frozen"
                                    :class="{ 'wcard--active': pinkFrozenTipVisible }"
                                    tabindex="0"
                                    role="button"
                                    aria-label="Карточка: Замороженный баланс"
                                >
                                    <div class="wcard__watermark wcard__watermark--orange">
                                        <i class="fa-solid fa-clock"></i>
                                    </div>
                                    <div class="wcard__top">
                                        <div class="wcard__badge wcard__badge--orange">
                                            <el-icon class="wcard__badge-icon"><Lock /></el-icon>
                                            В холде ({{ formatMinutes(holdMinutes) }})
                                        </div>
                                        <div class="wcard__info-trigger" aria-hidden="true">
                                            <el-icon><InfoFilled /></el-icon>
                                        </div>
                                    </div>
                                    <div class="wcard__amount-row">
                                        <span class="wcard__amount wcard__amount--orange">
                                            {{ formatMoney(currentWallet.pink_held_balance) }}
                                        </span>
                                        <CoinIcon color="pink" size="sm" />
                                    </div>
                                </div>
                            </el-tooltip>
                        </div>
                    </template>

                    <!-- SECTION 2: BLUE COINS ACCOUNT (PURCHASES) -->
                    <div class="wallet-section-header" :style="isIdol ? 'margin-top: 1.5rem;' : ''">
                        <div class="wallet-section-title">
                            <CoinIcon color="blue" size="sm" />
                            <span>{{ isIdol ? 'Счёт покупок' : 'Баланс' }}</span>
                        </div>
                    </div>

                    <div class="wallet-cards-grid">
                        <!-- Card 1: Available Blue -->
                        <el-tooltip
                            v-model:visible="availableTipVisible"
                            placement="top"
                            effect="dark"
                            popper-class="wallet-dark-tooltip"
                            trigger="click"
                            :hide-after="0"
                        >
                            <template #content>
                                <div class="wallet-tip-text">
                                    <span>Свободные средства.<br>Доступны для оплаты услуг<br>и покупки контент-паков.</span>
                                </div>
                            </template>
                            <div
                                class="wcard wcard--available"
                                :class="{ 'wcard--active': availableTipVisible }"
                                tabindex="0"
                                role="button"
                                aria-label="Карточка: Доступный баланс"
                            >
                                <div class="wcard__watermark wcard__watermark--emerald">
                                    <i class="fa-solid fa-wallet"></i>
                                </div>
                                <div class="wcard__top">
                                    <div class="wcard__badge wcard__badge--emerald">
                                        <el-icon class="wcard__badge-icon"><WalletFilled /></el-icon>
                                        Доступно
                                    </div>
                                    <div class="wcard__info-trigger" aria-hidden="true">
                                        <el-icon><InfoFilled /></el-icon>
                                    </div>
                                </div>
                                <div class="wcard__amount-row">
                                    <span class="wcard__amount wcard__amount--emerald">
                                        {{ formatMoney(currentWallet.balance) }}
                                    </span>
                                    <CoinIcon color="blue" size="sm" />
                                </div>
                            </div>
                        </el-tooltip>
                    </div>

                    <!-- Deactivated Wallet Warning Banner -->
                    <div v-if="currentWallet && currentWallet.is_active === false" class="wallet-deactivated-banner">
                        <el-icon class="wallet-deactivated-banner__icon"><CircleCloseFilled /></el-icon>
                        <div class="wallet-deactivated-banner__content">
                            <span class="wallet-deactivated-banner__title">Кошелёк деактивирован</span>
                            <span class="wallet-deactivated-banner__text">Финансовые операции временно недоступны. Обратитесь в службу поддержки для уточнения статуса.</span>
                        </div>
                    </div>

                    <!-- Financial Actions Panel (Deposit / Withdraw) -->
                    <div class="wallet-action-box">
                        <!-- Idol Role Selector (Deposit / Withdraw) -->
                        <div v-if="isIdol" class="wallet-role-tabs">
                            <button
                                type="button"
                                class="wallet-role-tab flex items-center justify-center gap-1.5"
                                :class="{ 'wallet-role-tab--active-deposit': actionTab === 'deposit' }"
                                @click="actionTab = 'deposit'"
                            >
                                <span>Пополнить</span>
                                <CoinIcon color="blue" size="xs" />
                            </button>
                            <button
                                type="button"
                                class="wallet-role-tab flex items-center justify-center gap-1.5"
                                :class="{ 'wallet-role-tab--active-withdraw': actionTab === 'withdraw' }"
                                @click="actionTab = 'withdraw'"
                            >
                                <span>Вывести</span>
                                <CoinIcon color="pink" size="xs" />
                            </button>
                        </div>

                        <!-- DEPOSIT SECTION (Blue Coins Packs) -->
                        <div v-if="!isIdol || actionTab === 'deposit'" class="wallet-deposit-section">
                            <!-- Unified Vertical Coin Packs Grid -->
                            <div v-if="displayPacks.length" class="coin-packs-grid">
                                <button
                                    v-for="pack in displayPacks"
                                    :key="pack.id"
                                    type="button"
                                    class="coin-pack-card"
                                    :class="{
                                        'coin-pack-card--active': selectedPackId === pack.id,
                                        'coin-pack-card--starter': pack.is_starter,
                                    }"
                                    @click="selectedPackId = pack.id"
                                >
                                    <!-- Starter Badge Ribbon -->
                                    <span v-if="pack.is_starter" class="coin-pack-ribbon">1 раз</span>

                                    <!-- Centered Coin Icon with Quantity Gradation -->
                                    <div class="coin-pack-card__icon-wrap">
                                        <!-- 1000+ coins: Triple stack -->
                                        <div v-if="Number(pack.coins) >= 1000" class="coin-stack coin-stack--triple">
                                            <CoinIcon color="blue" size="md" class="coin-stack__coin coin-stack__coin--1" />
                                            <CoinIcon color="blue" size="md" class="coin-stack__coin coin-stack__coin--2" />
                                            <CoinIcon color="blue" size="md" class="coin-stack__coin coin-stack__coin--3" />
                                        </div>
                                        <!-- 500-999 coins: Double coins -->
                                        <div v-else-if="Number(pack.coins) >= 500" class="coin-stack coin-stack--double">
                                            <CoinIcon color="blue" size="md" class="coin-stack__coin coin-stack__coin--back" />
                                            <CoinIcon color="blue" size="md" class="coin-stack__coin coin-stack__coin--front" />
                                        </div>
                                        <!-- 1-499 coins: Single coin -->
                                        <div v-else class="coin-stack coin-stack--single">
                                            <CoinIcon color="blue" size="md" />
                                        </div>
                                    </div>

                                    <!-- Bold Coins Amount (No rubles, no word 'монет') -->
                                    <div class="coin-pack-card__amount">
                                        {{ formatInteger(pack.coins) }}
                                    </div>
                                </button>
                            </div>

                            <!-- Empty state if no packs configured -->
                            <div v-else class="text-sm text-slate-400 py-4 text-center">
                                Нет доступных пакетов пополнения.
                            </div>

                            <!-- Footer Bar: Summary on Left, Compact Button on Right -->
                            <div class="wallet-deposit-footer-bar">
                                <div class="wallet-deposit-summary">
                                    <span class="wallet-deposit-summary__label">К зачислению:</span>
                                    <div class="wallet-deposit-summary__value">
                                        <span class="wallet-deposit-summary__amount">{{ formatInteger(selectedPack?.coins || 0) }}</span>
                                        <CoinIcon color="blue" size="sm" />
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="wallet-primary-btn wallet-primary-btn--deposit wallet-primary-btn--pack-compact"
                                    :disabled="isDepositing || currentWallet.is_active === false || !selectedPack"
                                    @click="handleDeposit"
                                >
                                    <el-icon v-if="isDepositing" class="is-loading"><Loading /></el-icon>
                                    <span>{{ isDepositing ? 'Обработка...' : `Оплатить ${selectedPack ? `${formatInteger(totalPriceRub)} ₽` : ''}` }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- WITHDRAW SECTION (Idol Only - Pink Coins) -->
                        <div v-else-if="isIdol && actionTab === 'withdraw'" class="wallet-withdraw-section">
                            <div class="wallet-withdraw-balance-hint mb-3 flex items-center justify-between text-xs text-slate-400">
                                <span>Доступно для вывода:</span>
                                <span class="flex items-center gap-1 font-semibold text-pink-400">
                                    {{ formatMoney(currentWallet.pink_balance) }}
                                    <CoinIcon color="pink" size="xs" />
                                </span>
                            </div>
                            <div class="wallet-action-form">
                                <div class="wallet-input-container">
                                    <input
                                        v-model.number="withdrawAmount"
                                        type="number"
                                        min="100"
                                        :max="currentWallet.pink_balance"
                                        step="100"
                                        class="wallet-glass-input"
                                        placeholder="Сумма от 100"
                                        @keydown.enter.prevent="handleWithdraw"
                                    />
                                    <div class="wallet-input-currency-coin">
                                        <CoinIcon color="pink" size="sm" />
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="wallet-primary-btn wallet-primary-btn--withdraw"
                                    :disabled="isWithdrawing || currentWallet.is_active === false || !withdrawAmount || withdrawAmount > (currentWallet.pink_balance || 0)"
                                    @click="handleWithdraw"
                                >
                                    <el-icon v-if="isWithdrawing" class="is-loading"><Loading /></el-icon>
                                    <span>{{ isWithdrawing ? 'Обработка...' : 'Вывести' }}</span>
                                </button>
                            </div>
                            <div class="wallet-fee-info">
                                <span class="wallet-fee-info__item">
                                    <span>Комиссия:</span>
                                    <strong class="wallet-fee-info__val">{{ props.withdrawalFeePercent > 0 ? `${props.withdrawalFeePercent}%` : '4%' }}</strong>
                                </span>
                                <span class="wallet-fee-info__item">
                                    <span>К получению на карту:</span>
                                    <strong class="wallet-fee-info__highlight wallet-fee-info__highlight--withdraw">
                                        {{ withdrawAmount > 0 ? `${formatMoney(withdrawPayoutAmount)} ₽` : '—' }}
                                    </strong>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════════════ VIEW 2: TRANSACTIONS HISTORY ════════════ -->
                <div v-else class="wallet-history">
                    <!-- Filter Tabs Bar -->
                    <div class="wallet-history-nav">
                        <!-- Desktop Tabs -->
                        <div class="wallet-history-tabs wallet-history-tabs--desktop">
                            <button
                                v-for="tab in historyTabs"
                                :key="tab.id"
                                type="button"
                                class="wallet-htab"
                                :class="{ 'wallet-htab--active': activeHistoryTab === tab.id }"
                                @click="setHistoryFilter(tab.id)"
                            >
                                {{ tab.label }}
                            </button>
                        </div>

                        <!-- Mobile Dropdown Select -->
                        <div class="wallet-history-select-wrap wallet-history-select--mobile">
                            <AppSelect
                                :model-value="activeHistoryTab"
                                :options="historyTabOptions"
                                class="wallet-app-select"
                                @update:model-value="setHistoryFilter"
                            />
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="!filteredTransactions.length" class="wallet-empty-state">
                        <div class="wallet-empty-icon">
                            <el-icon><Tickets /></el-icon>
                        </div>
                        <h3 class="wallet-empty-title">Транзакции не найдены</h3>
                        <p class="wallet-empty-text">
                            {{ activeHistoryTab === 'all' 
                                ? 'В вашем кошельке пока нет завершённых операций.' 
                                : 'В выбранной категории операций записей не обнаружено.' }}
                        </p>
                        <button
                            type="button"
                            class="wallet-empty-btn"
                            @click="setView('overview')"
                        >
                            <el-icon><Plus /></el-icon>
                            <span>Пополнить</span>
                        </button>
                    </div>

                    <!-- Transaction Table / Ledger -->
                    <TransactionLedger v-else :transactions="filteredTransactions" />

                    <!-- Pagination -->
                    <div v-if="transactions.prev_page_url || transactions.next_page_url" class="wallet-pagination">
                        <Link
                            v-if="transactions.prev_page_url"
                            :href="getPaginationUrl(transactions.prev_page_url)"
                            preserve-scroll
                            preserve-state
                            class="wallet-page-link"
                        >
                            &laquo; Предыдущая
                        </Link>
                        <span
                            v-else
                            class="wallet-page-link wallet-page-link--disabled"
                        >
                            &laquo; Предыдущая
                        </span>

                        <Link
                            v-if="transactions.next_page_url"
                            :href="getPaginationUrl(transactions.next_page_url)"
                            preserve-scroll
                            preserve-state
                            class="wallet-page-link"
                        >
                            Следующая &raquo;
                        </Link>
                        <span
                            v-else
                            class="wallet-page-link wallet-page-link--disabled"
                        >
                            Следующая &raquo;
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Idol Coin Conversion Modal (Pink -> Blue 1:1) -->
        <SiteModal
            v-if="isIdol"
            :show="showConvertModal"
            variant="pink"
            compact
            max-width="440px"
            no-history
            @close="showConvertModal = false"
        >
            <div class="convert-modal">
                <div class="convert-modal__header">
                    <div class="convert-modal__icon">
                        <CoinIcon color="pink" size="md" />
                        <el-icon class="convert-arrow"><RefreshRight /></el-icon>
                        <CoinIcon color="blue" size="md" />
                    </div>
                    <h3 class="convert-modal__title">Обмен (1:1)</h3>
                    <p class="convert-modal__subtitle">
                        Перевод заработанных средств 1:1 для оплаты услуг на платформе.<br>
                        Обратный обмен невозможен.
                    </p>
                </div>

                <div class="convert-modal__balance-row">
                    <span class="convert-bal-label">Доступно к обмену:</span>
                    <div class="convert-bal-val">
                        <span>{{ formatMoney(currentWallet.pink_balance) }}</span>
                        <CoinIcon color="pink" size="xs" />
                    </div>
                </div>

                <div class="convert-modal__form">
                    <div class="wallet-preset-chips mb-3">
                        <button
                            type="button"
                            class="wallet-preset-chip"
                            @click="convertPreset(25)"
                        >
                            25%
                        </button>
                        <button
                            type="button"
                            class="wallet-preset-chip"
                            @click="convertPreset(50)"
                        >
                            50%
                        </button>
                        <button
                            type="button"
                            class="wallet-preset-chip"
                            @click="convertPreset(100)"
                        >
                            100% (Все)
                        </button>
                    </div>

                    <div class="wallet-input-container">
                        <input
                            v-model.number="convertAmount"
                            type="number"
                            min="1"
                            :max="currentWallet.pink_balance"
                            step="1"
                            class="wallet-glass-input"
                            placeholder="Количество"
                            @keydown.enter.prevent="handleConvert"
                        />
                        <div class="wallet-input-currency-coin">
                            <CoinIcon color="pink" size="sm" />
                        </div>
                    </div>

                    <div v-if="convertAmount > 0" class="convert-preview">
                        <span>К зачислению:</span>
                        <div class="convert-preview-val">
                            <strong>+{{ formatMoney(convertAmount) }}</strong>
                            <CoinIcon color="blue" size="xs" />
                        </div>
                    </div>

                    <div v-if="convertError" class="convert-error">
                        <el-icon><CircleCloseFilled /></el-icon>
                        <span>{{ convertError }}</span>
                    </div>

                    <div class="convert-modal__actions">
                        <button
                            type="button"
                            class="wallet-primary-btn wallet-primary-btn--convert"
                            :disabled="isConverting || !convertAmount || convertAmount < 1 || convertAmount > (currentWallet.pink_balance || 0)"
                            @click="handleConvert"
                        >
                            <el-icon v-if="isConverting" class="is-loading"><Loading /></el-icon>
                            <span>{{ isConverting ? 'Обмен...' : 'Обменять 1:1' }}</span>
                        </button>
                        <button
                            type="button"
                            class="wallet-secondary-btn"
                            @click="showConvertModal = false"
                        >
                            Отмена
                        </button>
                    </div>
                </div>
            </div>
        </SiteModal>
    </AppLayout>
</template>

<style scoped>
/* ── Viewport & Canvas ─────────────────────────────────────── */
.wallet-viewport {
    position: relative;
    min-height: calc(100vh - 72px);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.wallet-ambient {
    position: absolute;
    pointer-events: none;
    z-index: 0;
    border-radius: 50%;
    filter: blur(80px);
}

.ambient-pink {
    display: none;
}

.ambient-blue {
    top: 180px;
    right: 8%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(100, 210, 255, 0.08) 0%, transparent 70%);
}

.wallet-wrapper {
    position: relative;
    z-index: 1;
    max-width: 960px;
    width: 100%;
    margin: 0 auto;
    padding: 1.5rem 1.25rem calc(4.5rem + env(safe-area-inset-bottom));
}

/* ── Topbar & Main Tabs Switcher ──────────────────────────── */
.wallet-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.wallet-brand {
    display: flex;
    align-items: center;
}

.wallet-brand__title {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 1.55rem;
    font-weight: 800;
    color: rgba(245, 245, 255, 0.98);
    margin: 0;
    line-height: 1.2;
    letter-spacing: -0.015em;
}

.wallet-brand__title-icon {
    font-size: 1.5rem;
    color: var(--color-base-1, #ffb2ef);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    filter: drop-shadow(0 2px 6px rgba(255, 178, 239, 0.3));
}

.wallet-views {
    display: flex;
    background: rgba(255, 178, 239, 0.05);
    border: 1px solid rgba(255, 178, 239, 0.14);
    border-radius: 8px;
    padding: 3px;
    gap: 3px;
}

.wallet-view-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.95rem;
    font-size: 0.84rem;
    font-weight: 600;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: rgba(255, 178, 239, 0.65);
    cursor: pointer;
    transition: all 0.15s ease;
}

.wallet-view-btn:hover {
    color: #ffffff;
}

.wallet-view-btn--active {
    background: rgba(255, 178, 239, 0.18);
    color: var(--color-base-1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
}

.wallet-view-badge {
    font-family: var(--font-receipt);
    font-size: 0.72rem;
    padding: 0.1rem 0.45rem;
    border-radius: 9999px;
    background: rgba(255, 178, 239, 0.15);
    color: var(--color-base-1);
}

/* ── 2 Compact Balance Cards ──────────────────────────────── */
.wallet-cards-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}

.wcard {
    position: relative;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 14px;
    padding: 1.3rem 1.45rem;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.08);
    overflow: hidden;
    cursor: pointer;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
    transition: border-color 0.25s ease, box-shadow 0.25s ease;
}

.wcard--available {
    background: radial-gradient(circle at 95% 90%, rgba(76, 222, 143, 0.09) 0%, transparent 60%),
                linear-gradient(135deg, rgba(16, 12, 28, 0.78) 0%, rgba(10, 7, 18, 0.88) 100%);
    border: 1px solid rgba(76, 222, 143, 0.22);
}

.wcard--pink-available {
    background: radial-gradient(circle at 95% 90%, rgba(244, 114, 182, 0.14) 0%, transparent 60%),
                linear-gradient(135deg, rgba(28, 12, 22, 0.78) 0%, rgba(18, 7, 14, 0.88) 100%);
    border: 1px solid rgba(244, 114, 182, 0.28);
}

.wcard--pink-frozen {
    background: radial-gradient(circle at 95% 90%, rgba(251, 146, 60, 0.14) 0%, transparent 60%),
                linear-gradient(135deg, rgba(28, 12, 22, 0.78) 0%, rgba(18, 7, 14, 0.88) 100%);
    border: 1px solid rgba(251, 146, 60, 0.28);
}

@media (hover: hover) and (pointer: fine) {
    .wcard--available:hover {
        border-color: rgba(76, 222, 143, 0.4);
        box-shadow: 0 10px 36px rgba(0, 0, 0, 0.5), 0 0 24px rgba(76, 222, 143, 0.12);
    }

    .wcard--pink-available:hover {
        border-color: rgba(244, 114, 182, 0.48);
        box-shadow: 0 10px 36px rgba(0, 0, 0, 0.5), 0 0 24px rgba(244, 114, 182, 0.18);
    }

    .wcard--pink-frozen:hover {
        border-color: rgba(251, 146, 60, 0.48);
        box-shadow: 0 10px 36px rgba(0, 0, 0, 0.5), 0 0 24px rgba(251, 146, 60, 0.18);
    }
}

.wcard--available.wcard--active {
    border-color: rgba(76, 222, 143, 0.45);
    box-shadow: 0 10px 36px rgba(0, 0, 0, 0.5), 0 0 24px rgba(76, 222, 143, 0.16);
}

.wcard--pink-available.wcard--active {
    border-color: rgba(244, 114, 182, 0.55);
    box-shadow: 0 10px 36px rgba(0, 0, 0, 0.5), 0 0 24px rgba(244, 114, 182, 0.22);
}

.wcard--pink-frozen.wcard--active {
    border-color: rgba(251, 146, 60, 0.55);
    box-shadow: 0 10px 36px rgba(0, 0, 0, 0.5), 0 0 24px rgba(251, 146, 60, 0.22);
}

.wcard:hover,
.wcard:active {
    transform: none !important;
}

/* Watermark silhouettes with scale + rotation micro-interactions */
.wcard__watermark {
    position: absolute;
    right: -10px;
    bottom: -15px;
    font-size: 6.5rem;
    line-height: 1;
    pointer-events: none;
    transform-origin: center center;
    transform: rotate(-12deg) scale(1);
    transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.3s ease;
    z-index: 0;
}

.wcard__watermark--emerald {
    color: rgba(76, 222, 143, 0.07);
}

.wcard__watermark--pink {
    color: rgba(244, 114, 182, 0.08);
}

.wcard__watermark--orange {
    color: rgba(251, 146, 60, 0.08);
}

.wcard:hover .wcard__watermark,
.wcard:active .wcard__watermark,
.wcard.wcard--active .wcard__watermark {
    transform: rotate(-4deg) scale(1.15);
}

.wcard--available:hover .wcard__watermark--emerald,
.wcard--available:active .wcard__watermark--emerald,
.wcard--available.wcard--active .wcard__watermark--emerald {
    color: rgba(76, 222, 143, 0.13);
}

.wcard--pink-available:hover .wcard__watermark--pink,
.wcard--pink-available:active .wcard__watermark--pink,
.wcard--pink-available.wcard--active .wcard__watermark--pink {
    color: rgba(244, 114, 182, 0.14);
}

.wcard--pink-frozen:hover .wcard__watermark--orange,
.wcard--pink-frozen:active .wcard__watermark--orange,
.wcard--pink-frozen.wcard--active .wcard__watermark--orange {
    color: rgba(251, 146, 60, 0.14);
}

.wcard__top {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.95rem;
}

.wcard__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0;
    background: transparent;
    border: none;
}

.wcard__badge--emerald {
    color: #4cde8f;
}

.wcard__badge--pink {
    color: #f472b6;
}

.wcard__badge--orange {
    color: #fb923c;
}

.wcard__badge-icon {
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transform-origin: center center;
    transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.wcard:hover .wcard__badge-icon,
.wcard:active .wcard__badge-icon,
.wcard.wcard--active .wcard__badge-icon {
    transform: scale(1.22) rotate(12deg);
}

.wcard__info-trigger {
    background: transparent;
    border: none;
    padding: 0;
    outline: none;
    color: rgba(220, 220, 255, 0.4);
    font-size: 1.18rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    -webkit-tap-highlight-color: transparent;
    transform-origin: center center;
    transition: color 0.2s ease, background 0.2s ease;
}

.wcard__info-trigger .el-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transform-origin: center center;
    transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.wcard:hover .wcard__info-trigger,
.wcard:active .wcard__info-trigger,
.wcard.wcard--active .wcard__info-trigger,
.wcard__info-trigger:hover {
    color: rgba(255, 255, 255, 0.95);
    background: rgba(255, 255, 255, 0.08);
}

.wcard:hover .wcard__info-trigger .el-icon,
.wcard:active .wcard__info-trigger .el-icon,
.wcard.wcard--active .wcard__info-trigger .el-icon,
.wcard__info-trigger:hover .el-icon {
    transform: scale(1.2) rotate(15deg);
}

.wcard__amount-row {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0;
}

.wcard__amount {
    font-family: var(--font-receipt);
    font-size: 1.85rem;
    font-weight: 800;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

.wcard__amount--emerald {
    color: #4cde8f;
}

.wcard__amount--pink {
    color: #f472b6;
    text-shadow: 0 0 20px rgba(244, 114, 182, 0.35);
}

.wcard__amount--orange {
    color: #fb923c;
    text-shadow: 0 0 20px rgba(251, 146, 60, 0.35);
}

.wcard__currency {
    font-family: var(--font-receipt);
    font-size: 1.2rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.4);
}

/* ── Section Headers & Dual Account Controls ──────────────── */
.wallet-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.85rem;
    padding: 0 0.25rem;
}

.wallet-section-title {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    font-size: 0.95rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.92);
    letter-spacing: -0.01em;
}

.wallet-section-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.wallet-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.85rem;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 9999px;
    background: rgba(244, 114, 182, 0.12);
    border: 1px solid rgba(244, 114, 182, 0.28);
    color: #f472b6;
    cursor: pointer;
    transition: all 0.2s ease;
}

.wallet-pill-btn:hover {
    background: rgba(244, 114, 182, 0.22);
    border-color: rgba(244, 114, 182, 0.45);
    color: #ffffff;
}

/* ── Financial Actions Panel ──────────────────────────────── */
.wallet-action-box {
    position: relative;
    background: rgba(14, 10, 24, 0.7);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 178, 239, 0.14);
    border-radius: 12px;
    padding: 1.35rem 1.5rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.08);
    overflow: hidden;
}

.wallet-action-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(100, 210, 255, 0.4) 50%, transparent 100%);
    pointer-events: none;
}

/* Idol subtabs */
.wallet-role-tabs {
    display: flex;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    padding: 3px;
    gap: 3px;
    margin-bottom: 1.15rem;
    width: fit-content;
}

.wallet-role-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.85rem;
    font-size: 0.82rem;
    font-weight: 600;
    border-radius: 4px;
    border: none;
    background: transparent;
    color: rgba(220, 220, 255, 0.6);
    cursor: pointer;
    transition: all 0.15s ease;
}

.wallet-role-tab--active {
    background: rgba(255, 178, 239, 0.15);
    color: var(--color-base-1);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.25);
}

.wallet-role-tab--active-deposit {
    background: rgba(255, 178, 239, 0.18);
    border: 1px solid rgba(255, 178, 239, 0.35);
    color: var(--color-base-1);
    box-shadow: 0 1px 6px rgba(255, 178, 239, 0.15);
}

.wallet-role-tab--active-withdraw {
    background: rgba(100, 210, 255, 0.18);
    border: 1px solid rgba(100, 210, 255, 0.35);
    color: var(--color-base-2, #64d2ff);
    box-shadow: 0 1px 6px rgba(100, 210, 255, 0.15);
}

.wallet-action-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.95rem;
}

.wallet-action-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: rgba(240, 240, 255, 0.95);
}

.wallet-action-available {
    font-size: 0.82rem;
    color: rgba(220, 220, 255, 0.5);
}

.wallet-action-available strong {
    font-family: var(--font-receipt);
    color: #4cde8f;
}

/* Coin Packs Vertical Grid & Cards */
.coin-packs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}

@media (max-width: 640px) {
    .coin-packs-grid {
        grid-template-columns: repeat(auto-fit, minmax(88px, 1fr));
        gap: 0.5rem;
    }
}

.coin-pack-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.25rem 0.5rem 1rem;
    min-height: 105px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
    overflow: hidden;
    box-shadow: none !important;
}

.coin-pack-card:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.18);
}

/* NO NEON GLOW AT ALL - clean solid accent border and subtle background */
.coin-pack-card--active {
    background: rgba(100, 210, 255, 0.08);
    border-color: #64d2ff;
    box-shadow: none !important;
}

.coin-pack-card--active:hover {
    background: rgba(100, 210, 255, 0.11);
    border-color: #64d2ff;
}

.coin-pack-card--starter.coin-pack-card--active {
    background: rgba(255, 178, 239, 0.08);
    border-color: #ffb2ef;
}

/* Starter pack ribbon badge in the corner */
.coin-pack-ribbon {
    position: absolute;
    top: 0;
    right: 0;
    background: #ffb2ef;
    color: #120c1d;
    font-size: 0.65rem;
    font-weight: 800;
    line-height: 1;
    padding: 0.25rem 0.45rem;
    border-bottom-left-radius: 6px;
    letter-spacing: 0.02em;
    user-select: none;
}

.coin-pack-card__icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 32px;
    margin-bottom: 0.55rem;
}

/* Coin stack gradation */
.coin-stack {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 28px;
}

.coin-stack--single {
    width: 24px;
}

.coin-stack--double {
    width: 34px;
}

.coin-stack--double .coin-stack__coin--back {
    position: absolute;
    left: 0;
    top: 2px;
    opacity: 0.82;
    transform: scale(0.9);
}

.coin-stack--double .coin-stack__coin--front {
    position: absolute;
    right: 0;
    top: 0;
    z-index: 2;
    filter: drop-shadow(-2px 2px 3px rgba(0, 0, 0, 0.45));
}

.coin-stack--triple {
    width: 42px;
}

.coin-stack--triple .coin-stack__coin--1 {
    position: absolute;
    left: 0;
    top: 3px;
    opacity: 0.75;
    transform: scale(0.85);
}

.coin-stack--triple .coin-stack__coin--2 {
    position: absolute;
    left: 9px;
    top: 1px;
    z-index: 1;
    opacity: 0.9;
    transform: scale(0.92);
    filter: drop-shadow(-1px 1px 2px rgba(0, 0, 0, 0.35));
}

.coin-stack--triple .coin-stack__coin--3 {
    position: absolute;
    right: 0;
    top: 0;
    z-index: 2;
    filter: drop-shadow(-2px 2px 4px rgba(0, 0, 0, 0.45));
}

.coin-pack-card__amount {
    font-family: var(--font-receipt);
    font-size: 1.15rem;
    font-weight: 800;
    color: #ffffff;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.01em;
}

.coin-pack-card--active .coin-pack-card__amount {
    color: #64d2ff;
}

.coin-pack-card--starter.coin-pack-card--active .coin-pack-card__amount {
    color: #ffb2ef;
}

/* Footer Bar: Selected pack summary on left, compact button on right */
.wallet-deposit-footer-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding-top: 0.25rem;
}

.wallet-deposit-summary {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.wallet-deposit-summary__label {
    font-size: 0.82rem;
    color: rgba(220, 220, 255, 0.55);
    font-weight: 500;
}

.wallet-deposit-summary__value {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.wallet-deposit-summary__amount {
    font-family: var(--font-receipt);
    font-size: 1.15rem;
    font-weight: 800;
    color: #ffffff;
    font-variant-numeric: tabular-nums;
}

.wallet-primary-btn--pack-compact {
    min-width: 170px;
    height: 42px;
    padding: 0 1.5rem;
    font-size: 0.92rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    border-radius: 8px;
    white-space: nowrap;
}

@media (max-width: 640px) {
    .wallet-deposit-footer-bar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    .wallet-deposit-summary {
        justify-content: space-between;
    }
    .wallet-primary-btn--pack-compact {
        width: 100%;
    }
}

/* 3 Preset Chips (250, 550, 950) */
.wallet-preset-chips {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.wallet-preset-chip {
    font-family: var(--font-receipt);
    height: 42px;
    font-size: 0.9rem;
    font-weight: 700;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.09);
    color: rgba(230, 230, 255, 0.85);
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    -webkit-tap-highlight-color: transparent;
    user-select: none;
}

@media (hover: hover) and (pointer: fine) {
    .wallet-preset-chip:hover {
        background: rgba(255, 255, 255, 0.07);
        border-color: rgba(255, 255, 255, 0.18);
        color: #ffffff;
    }
}

.wallet-preset-chip--active {
    background: rgba(255, 178, 239, 0.14);
    border-color: rgba(255, 178, 239, 0.45);
    color: var(--color-base-1, #ffb2ef);
    box-shadow: none;
}

@media (hover: hover) and (pointer: fine) {
    .wallet-preset-chip--active:hover {
        background: rgba(255, 178, 239, 0.18);
        border-color: rgba(255, 178, 239, 0.6);
        color: #ffffff;
    }
}

/* Form row */
.wallet-action-form {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.wallet-input-container {
    position: relative;
    flex: 1;
    min-width: 0;
}

.wallet-glass-input {
    width: 100%;
    box-sizing: border-box;
    height: 44px;
    padding: 0 2rem 0 1rem;
    font-family: var(--font-receipt);
    font-size: 0.98rem;
    font-weight: 600;
    background: rgba(10, 7, 20, 0.7);
    border: 1px solid rgba(255, 178, 239, 0.2);
    border-radius: 8px;
    color: #ffffff;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    -moz-appearance: textfield;
}

.wallet-glass-input::-webkit-outer-spin-button,
.wallet-glass-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.wallet-glass-input:focus {
    border-color: var(--color-base-1);
    box-shadow: 0 0 0 3px rgba(255, 178, 239, 0.15);
}

.wallet-input-currency,
.wallet-input-currency-coin {
    position: absolute;
    right: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    font-family: var(--font-receipt);
    font-size: 0.95rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.4);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}

/* ── Conversion Modal (Pink -> Blue) ──────────────────────── */
.convert-modal {
    padding: 0.5rem 0.25rem;
}

.convert-modal__header {
    text-align: center;
    margin-bottom: 1.25rem;
}

.convert-modal__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.convert-arrow {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.6);
}

.convert-modal__title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 0.4rem;
}

.convert-modal__subtitle {
    font-size: 0.82rem;
    line-height: 1.45;
    color: rgba(255, 255, 255, 0.65);
    margin: 0;
}

.convert-modal__balance-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.65rem 0.95rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    margin-bottom: 1rem;
    font-size: 0.85rem;
}

.convert-bal-label {
    color: rgba(255, 255, 255, 0.65);
}

.convert-bal-val {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 700;
    color: #f472b6;
}

.convert-preview {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.65rem 0.95rem;
    background: rgba(100, 210, 255, 0.06);
    border: 1px solid rgba(100, 210, 255, 0.18);
    border-radius: 8px;
    margin-top: 0.75rem;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.8);
}

.convert-preview-val {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 700;
    color: #38bdf8;
}

.convert-error {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.75rem;
    font-size: 0.8rem;
    color: #fb7185;
}

.convert-modal__actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1.25rem;
}

.wallet-primary-btn--convert {
    flex: 1;
    background: linear-gradient(135deg, #ec4899 0%, #a855f7 100%);
    border: 1px solid rgba(236, 72, 153, 0.4);
    color: #ffffff;
    box-shadow: 0 4px 14px rgba(236, 72, 153, 0.35);
}

.wallet-secondary-btn {
    padding: 0.65rem 1.2rem;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.8);
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.wallet-secondary-btn:hover {
    background: rgba(255, 255, 255, 0.14);
    color: #fff;
}

.wallet-primary-btn {
    height: 44px;
    padding: 0 1.5rem;
    font-size: 0.86rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    transition: all 0.2s ease;
}

.wallet-primary-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}

.wallet-primary-btn--deposit {
    background: linear-gradient(135deg, rgba(255, 178, 239, 0.15) 0%, rgba(244, 114, 182, 0.08) 100%);
    border: 1px solid rgba(255, 178, 239, 0.35);
    color: var(--color-base-1);
    font-weight: 700;
    letter-spacing: 0.04em;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.15);
}

.wallet-primary-btn--deposit:hover:not(:disabled) {
    background: linear-gradient(135deg, rgba(255, 178, 239, 0.22) 0%, rgba(244, 114, 182, 0.12) 100%);
    border-color: rgba(255, 178, 239, 0.55);
    color: #ffffff;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.wallet-primary-btn--withdraw {
    background: linear-gradient(135deg, rgba(100, 210, 255, 0.15) 0%, rgba(56, 189, 248, 0.08) 100%);
    border: 1px solid rgba(100, 210, 255, 0.35);
    color: var(--color-base-2, #64d2ff);
    font-weight: 700;
    letter-spacing: 0.04em;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.15);
}

.wallet-primary-btn--withdraw:hover:not(:disabled) {
    background: linear-gradient(135deg, rgba(100, 210, 255, 0.22) 0%, rgba(56, 189, 248, 0.12) 100%);
    border-color: rgba(100, 210, 255, 0.55);
    color: #ffffff;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

/* Fee and net amounts below action form */
.wallet-fee-info {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
    margin-top: 0.6rem;
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.55);
    line-height: 1.4;
}

.wallet-fee-info__item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.wallet-fee-info__val {
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
}

.wallet-fee-info__sub {
    color: rgba(255, 255, 255, 0.45);
    font-weight: 400;
}

.wallet-fee-info__dot {
    color: rgba(255, 255, 255, 0.25);
}

.wallet-fee-info__highlight {
    font-weight: 600;
    font-family: var(--font-receipt);
}

.wallet-fee-info__highlight--deposit {
    color: #34d399;
}

.wallet-fee-info__highlight--withdraw {
    color: #fbbf24;
}

/* ── VIEW 2: History Ledger ───────────────────────────────── */
.wallet-history {
    position: relative;
    background: rgba(14, 10, 24, 0.7);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 178, 239, 0.14);
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.wallet-history-nav {
    display: flex;
    justify-content: flex-start;
    margin-bottom: 1.15rem;
}

.wallet-history-tabs--desktop {
    display: flex;
}

.wallet-history-select--mobile {
    display: none;
}

.wallet-history-select-wrap {
    width: 100%;
}

.wallet-app-select {
    width: 100% !important;
    min-height: 42px !important;
    border-radius: 8px !important;
    background: rgba(255, 178, 239, 0.05) !important;
    border: 1px solid rgba(255, 178, 239, 0.2) !important;
    color: #ffffff !important;
    font-size: 0.92rem !important;
    font-weight: 600 !important;
    padding: 0.55rem 0.85rem !important;
    box-sizing: border-box !important;
    transition: all 0.2s ease !important;
}

.wallet-app-select:hover {
    border-color: rgba(255, 178, 239, 0.4) !important;
    background: rgba(255, 178, 239, 0.08) !important;
}

.wallet-app-select.app-select--open {
    border-color: rgba(255, 178, 239, 0.6) !important;
    box-shadow: 0 0 12px rgba(255, 178, 239, 0.2) !important;
}

.wallet-history-tabs {
    display: flex;
    background: rgba(255, 178, 239, 0.04);
    border: 1px solid rgba(255, 178, 239, 0.12);
    border-radius: 6px;
    padding: 3px;
    gap: 2px;
}

.wallet-htab {
    background: transparent;
    border: none;
    color: rgba(255, 178, 239, 0.6);
    font-size: 0.82rem;
    font-weight: 600;
    padding: 0.35rem 0.75rem;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.wallet-htab:hover {
    color: #ffffff;
}

.wallet-htab--active {
    background: rgba(255, 178, 239, 0.16);
    color: var(--color-base-1);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
}

/* Empty State */
.wallet-empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.wallet-empty-icon {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    margin: 0 auto 1rem;
    background: rgba(255, 178, 239, 0.06);
    border: 1px solid rgba(255, 178, 239, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: rgba(255, 178, 239, 0.5);
}

.wallet-empty-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: rgba(240, 240, 255, 0.9);
    margin: 0 0 0.35rem 0;
}

.wallet-empty-text {
    font-size: 0.84rem;
    color: rgba(220, 220, 255, 0.45);
    max-width: 380px;
    margin: 0 auto 1.25rem;
    line-height: 1.4;
}

.wallet-empty-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 1.1rem;
    font-size: 0.82rem;
    font-weight: 700;
    border-radius: 6px;
    background: rgba(255, 178, 239, 0.12);
    border: 1px solid rgba(255, 178, 239, 0.3);
    color: var(--color-base-1);
    cursor: pointer;
    transition: all 0.15s ease;
}

.wallet-empty-btn:hover {
    background: rgba(255, 178, 239, 0.2);
    color: #ffffff;
}

/* Pagination */
.wallet-pagination {
    display: flex;
    justify-content: center;
    gap: 0.35rem;
    margin-top: 1.25rem;
    flex-wrap: wrap;
}

.wallet-page-link {
    font-family: var(--font-receipt);
    padding: 0.3rem 0.65rem;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 178, 239, 0.12);
    color: rgba(220, 220, 255, 0.75);
    text-decoration: none;
    transition: all 0.15s ease;
}

.wallet-page-link:hover:not(.wallet-page-link--disabled) {
    background: rgba(255, 178, 239, 0.1);
    color: #ffffff;
}

.wallet-page-link--active {
    background: rgba(255, 178, 239, 0.2);
    border-color: var(--color-base-1);
    color: var(--color-base-1);
}

.wallet-page-link--disabled {
    opacity: 0.35;
    pointer-events: none;
}

/* ── Responsiveness ───────────────────────────────────────── */
@media (max-width: 860px) {
    .wallet-history-tabs--desktop {
        display: none;
    }

    .wallet-history-select--mobile {
        display: block;
        width: 100%;
    }
}

@media (max-width: 768px) {
    .wallet-wrapper {
        padding: 1rem 0.75rem calc(5rem + env(safe-area-inset-bottom));
    }

    .wallet-topbar {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 1.15rem;
        margin-bottom: 1.25rem;
    }

    .wallet-brand {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    .wallet-brand__title {
        font-size: 1.4rem;
    }

    .wallet-brand__title-icon {
        font-size: 1.35rem;
    }

    .wallet-views {
        width: 100%;
    }

    .wallet-view-btn {
        flex: 1;
        justify-content: center;
        height: 42px;
    }

    .wallet-cards-grid {
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .wcard {
        padding: 1.15rem 1.15rem;
    }

    .wcard__watermark {
        font-size: 5.2rem;
        right: -8px;
        bottom: -10px;
    }

    .wallet-action-box {
        padding: 1.25rem 1rem;
    }

    .wallet-role-tabs {
        width: 100%;
        display: flex;
        box-sizing: border-box;
        margin-bottom: 1.2rem;
    }

    .wallet-role-tab {
        flex: 1;
        justify-content: center;
        text-align: center;
        height: 40px;
        font-size: 0.86rem;
    }

    .wallet-action-header {
        justify-content: center;
        text-align: center;
        margin-bottom: 1.1rem;
    }

    .wallet-action-title {
        text-align: center;
        font-size: 1rem;
    }

    .wallet-preset-chips {
        width: 100%;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
        margin-bottom: 1.1rem;
    }

    .wallet-preset-chip {
        height: 44px;
        font-size: 0.92rem;
    }

    .wallet-action-form {
        flex-direction: column;
        align-items: stretch;
        width: 100%;
        gap: 0.85rem;
    }

    .wallet-input-container {
        width: 100%;
        flex: none;
    }

    .wallet-glass-input {
        width: 100%;
        box-sizing: border-box;
        height: 46px;
        font-size: 1rem;
    }

    .wallet-primary-btn {
        width: 100%;
        height: 46px;
        justify-content: center;
        font-size: 0.92rem;
    }

    .wallet-history {
        padding: 1rem 0.75rem;
    }

    .wallet-fee-info {
        justify-content: center;
        font-size: 0.78rem;
        margin-top: 0.65rem;
        text-align: center;
    }
}

.wallet-deactivated-banner {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.85rem 1.25rem;
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.35);
    border-radius: 14px;
    margin-bottom: 1.25rem;
    color: #fca5a5;
}

.wallet-deactivated-banner__icon {
    font-size: 1.5rem;
    color: #ef4444;
    flex-shrink: 0;
}

.wallet-deactivated-banner__content {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.wallet-deactivated-banner__title {
    font-weight: 600;
    font-size: 0.95rem;
    color: #fff;
}

.wallet-deactivated-banner__text {
    font-size: 0.85rem;
    color: rgba(254, 202, 202, 0.9);
    line-height: 1.35;
}
</style>
