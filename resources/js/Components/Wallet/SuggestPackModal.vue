<script setup>
import { ref, computed, watch } from 'vue';
import SiteModal from '@/Components/Site/SiteModal.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import axios from 'axios';
import { formatMoney } from '@/Utils/money';
import { extractErrorMessage } from '@/Utils/error';
import { ElIcon } from 'element-plus';
import {
    Loading,
    Check,
    StarFilled,
    CircleCloseFilled,
    WarningFilled,
    Plus,
} from '@element-plus/icons-vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    cost: {
        type: Number,
        required: true,
    },
    currentBalance: {
        type: Number,
        default: 0,
    },
    starterUsed: {
        type: Boolean,
        default: false,
    },
    coinPacks: {
        type: Array,
        default: () => [
            { id: 2, coins: 300, is_starter: true, sort_order: 0 },
            { id: 3, coins: 500, is_starter: false, sort_order: 1 },
            { id: 6, coins: 700, is_starter: false, sort_order: 2 },
            { id: 4, coins: 1000, is_starter: false, sort_order: 3 },
        ],
    },
});

const emit = defineEmits(['close', 'success']);

const selectedPackId = ref(null);
const isSubmitting = ref(false);
const errorMessage = ref(null);

const needed = computed(() => {
    return Math.max(0, Math.round((Number(props.cost) - Number(props.currentBalance)) * 100) / 100);
});

const availablePacks = computed(() => {
    return (props.coinPacks || []).filter(pack => {
        if (pack.is_starter && props.starterUsed) {
            return false;
        }
        return true;
    }).sort((a, b) => Number(a.coins) - Number(b.coins));
});

// Determine recommended pack:
// 1. If starter pack available and covers or is very close, or
// 2. Smallest pack where pack.coins >= needed
const recommendedPackId = computed(() => {
    if (!availablePacks.value.length) return null;

    const starter = availablePacks.value.find(p => p.is_starter);
    if (starter && Number(starter.coins) >= needed.value) {
        return starter.id;
    }

    const covering = availablePacks.value.find(p => Number(p.coins) >= needed.value);
    if (covering) {
        return covering.id;
    }

    // If needed exceeds largest pack, pick the largest pack
    return availablePacks.value[availablePacks.value.length - 1].id;
});

// Auto-select recommended pack when modal opens or packs change
watch(
    () => [props.show, recommendedPackId.value],
    ([isOpen, recId]) => {
        if (isOpen && recId) {
            selectedPackId.value = recId;
            errorMessage.value = null;
        }
    },
    { immediate: true }
);

const selectedPack = computed(() => {
    if (!availablePacks.value.length) return null;
    return availablePacks.value.find(p => p.id === selectedPackId.value) || availablePacks.value[0];
});

const change = computed(() => {
    if (!selectedPack.value) return 0;
    const totalAfter = Number(props.currentBalance) + Number(selectedPack.value.coins);
    return Math.max(0, Math.round((totalAfter - Number(props.cost)) * 100) / 100);
});

function formatInteger(val) {
    return Number(val || 0).toLocaleString('ru-RU', {
        maximumFractionDigits: 0,
    });
}

async function handlePurchase() {
    if (!selectedPack.value || isSubmitting.value) return;

    isSubmitting.value = true;
    errorMessage.value = null;

    try {
        const { data } = await axios.post(route('wallet.deposit_pack'), {
            coin_pack_id: selectedPack.value.id,
        });

        emit('success', data);
        emit('close');
    } catch (e) {
        errorMessage.value = extractErrorMessage(e, 'Не удалось оформить пополнение.');
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <SiteModal
        :show="show"
        variant="blue"
        compact
        max-width="500px"
        no-history
        @close="$emit('close')"
    >
        <div class="suggest-modal">
            <!-- Header -->
            <div class="suggest-modal__header">
                <div class="suggest-modal__icon-wrap">
                    <CoinIcon color="blue" size="md" />
                </div>
                <h3 class="suggest-modal__title">Недостаточно монет</h3>
                <p class="suggest-modal__subtitle">
                    Для оформления заказа требуется больше монет. Выберите подходящий пакет — пополнение без комиссии через СБП или картой.
                </p>
            </div>

            <!-- Deficit Breakdown Card -->
            <div class="suggest-modal__deficit-card">
                <div class="deficit-row">
                    <span class="deficit-label">Стоимость услуги:</span>
                    <span class="deficit-val font-semibold text-slate-200">
                        {{ formatInteger(cost) }} 🪙 ({{ formatInteger(cost) }} ₽)
                    </span>
                </div>
                <div class="deficit-row">
                    <span class="deficit-label">На вашем балансе:</span>
                    <span class="deficit-val text-slate-300">
                        {{ formatInteger(currentBalance) }} 🪙
                    </span>
                </div>
                <div class="deficit-row deficit-row--highlight">
                    <span class="deficit-label font-bold text-sky-400">Не хватает:</span>
                    <span class="deficit-val font-extrabold text-sky-400 flex items-center gap-1">
                        +{{ formatInteger(needed) }}
                        <CoinIcon color="blue" size="xs" />
                    </span>
                </div>
            </div>

            <!-- Coin Packs Selector -->
            <div class="suggest-modal__packs-section">
                <div class="packs-section-title mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                    Выберите пакет монет:
                </div>

                <div class="suggest-packs-grid">
                    <button
                        v-for="pack in availablePacks"
                        :key="pack.id"
                        type="button"
                        class="suggest-pack-card"
                        :class="{
                            'suggest-pack-card--active': selectedPackId === pack.id,
                            'suggest-pack-card--recommended': pack.id === recommendedPackId,
                        }"
                        @click="selectedPackId = pack.id"
                    >
                        <!-- Badges -->
                        <span v-if="pack.id === recommendedPackId && !pack.is_starter" class="pack-badge pack-badge--rec">
                            Рекомендуем
                        </span>
                        <span v-else-if="pack.is_starter" class="pack-badge pack-badge--starter">
                            Выгодно 1 раз
                        </span>

                        <div class="suggest-pack-card__body">
                            <div class="suggest-pack-card__amount flex items-center justify-center gap-1">
                                <span class="text-lg font-extrabold text-white">{{ formatInteger(pack.coins) }}</span>
                                <CoinIcon color="blue" size="xs" />
                            </div>
                            <div class="suggest-pack-card__price text-xs font-medium text-slate-400 mt-1">
                                {{ formatInteger(pack.coins) }} ₽
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Change & Settlement Calculation -->
            <div v-if="selectedPack" class="suggest-modal__calc-box">
                <div class="calc-row text-xs text-slate-300 leading-relaxed">
                    <span class="text-sky-300 font-bold">+{{ formatInteger(needed) }} 🪙</span> спишется за оплату заказа,
                    остаток <span class="text-emerald-400 font-bold">{{ formatInteger(change) }} 🪙</span> останется на синем счёте.
                </div>
            </div>

            <!-- Error Banner -->
            <div v-if="errorMessage" class="suggest-modal__error">
                <el-icon><CircleCloseFilled /></el-icon>
                <span>{{ errorMessage }}</span>
            </div>

            <!-- Actions -->
            <div class="suggest-modal__actions">
                <button
                    type="button"
                    class="suggest-submit-btn"
                    :disabled="isSubmitting || !selectedPack"
                    @click="handlePurchase"
                >
                    <el-icon v-if="isSubmitting" class="is-loading mr-1.5"><Loading /></el-icon>
                    <span>{{ isSubmitting ? 'Оформление...' : `Оплатить ${formatInteger(selectedPack?.coins || 0)} ₽ (СБП / Карта)` }}</span>
                </button>
                <div class="suggest-modal__fee-note text-center text-xs text-slate-400 mt-2">
                    0% комиссия эквайринга • Чек 54-ФЗ онлайн
                </div>
            </div>
        </div>
    </SiteModal>
</template>

<style scoped>
.suggest-modal {
    padding: 1.25rem 1rem 0.5rem;
    color: #f1f5f9;
}

.suggest-modal__header {
    text-align: center;
    margin-bottom: 1.25rem;
}

.suggest-modal__icon-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(56, 189, 248, 0.14);
    border: 1px solid rgba(56, 189, 248, 0.3);
    margin-bottom: 0.75rem;
}

.suggest-modal__title {
    font-size: 1.25rem;
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 0.4rem;
    letter-spacing: -0.01em;
}

.suggest-modal__subtitle {
    font-size: 0.82rem;
    line-height: 1.45;
    color: #94a3b8;
    margin: 0;
}

.suggest-modal__deficit-card {
    background: rgba(15, 23, 42, 0.65);
    border: 1px solid rgba(56, 189, 248, 0.2);
    border-radius: 10px;
    padding: 0.85rem 1rem;
    margin-bottom: 1.25rem;
}

.deficit-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
    padding: 0.25rem 0;
}

.deficit-row--highlight {
    border-top: 1px dashed rgba(56, 189, 248, 0.25);
    margin-top: 0.4rem;
    padding-top: 0.45rem;
}

.deficit-label {
    color: #94a3b8;
}

.suggest-packs-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.6rem;
    margin-bottom: 1rem;
}

@media (max-width: 480px) {
    .suggest-packs-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.suggest-pack-card {
    position: relative;
    background: rgba(15, 23, 42, 0.55);
    border: 1px solid rgba(56, 189, 248, 0.18);
    border-radius: 10px;
    padding: 0.75rem 0.5rem;
    cursor: pointer;
    text-align: center;
    transition: all 0.18s ease;
}

.suggest-pack-card:hover {
    border-color: rgba(56, 189, 248, 0.45);
    background: rgba(56, 189, 248, 0.08);
}

.suggest-pack-card--active {
    border-color: #38bdf8 !important;
    background: rgba(56, 189, 248, 0.16) !important;
    box-shadow: 0 0 14px rgba(56, 189, 248, 0.25);
}

.suggest-pack-card--recommended {
    border-color: rgba(56, 189, 248, 0.35);
}

.pack-badge {
    position: absolute;
    top: -8px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.62rem;
    font-weight: 700;
    padding: 0.1rem 0.4rem;
    border-radius: 9999px;
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.pack-badge--rec {
    background: #0284c7;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(2, 132, 199, 0.4);
}

.pack-badge--starter {
    background: #e11d48;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(225, 29, 72, 0.4);
}

.suggest-modal__calc-box {
    background: rgba(56, 189, 248, 0.08);
    border: 1px solid rgba(56, 189, 248, 0.22);
    border-radius: 8px;
    padding: 0.65rem 0.85rem;
    margin-bottom: 1.25rem;
    text-align: center;
}

.suggest-modal__error {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
    padding: 0.6rem 0.8rem;
    border-radius: 8px;
    font-size: 0.82rem;
    margin-bottom: 1rem;
}

.suggest-submit-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.8rem 1.25rem;
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    border: 1px solid rgba(56, 189, 248, 0.5);
    border-radius: 10px;
    font-size: 0.92rem;
    font-weight: 700;
    color: #ffffff;
    cursor: pointer;
    transition: all 0.18s ease;
    box-shadow: 0 4px 16px rgba(2, 132, 199, 0.35);
}

.suggest-submit-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
    box-shadow: 0 6px 20px rgba(2, 132, 199, 0.5);
}

.suggest-submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
