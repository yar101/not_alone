<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    price: {
        type: [Number, String],
        default: null,
    },
    feePercent: {
        type: Number,
        default: null,
    },
});

const page = usePage();
const { __ } = useTranslations();

const effectiveFeePercent = computed(() => {
    if (props.feePercent !== null && props.feePercent !== undefined) {
        return Number(props.feePercent);
    }
    return Number(page.props.platform_fee_percent ?? 10);
});

const payout = computed(() => {
    const val = Number(props.price);
    if (!val || val <= 0 || isNaN(val)) return 0;
    // Matches backend calculation in WalletService:
    // $platformFee = round($serviceAmount * ($feePercent / 100), 2);
    // $netPayout = round($serviceAmount - $platformFee, 2);
    const fee = Math.round(val * effectiveFeePercent.value) / 100;
    return Math.max(0, Math.round((val - fee) * 100) / 100);
});

const formattedPayout = computed(() => {
    if (!payout.value) return '';
    const hasDecimals = payout.value % 1 !== 0;
    return payout.value.toLocaleString('ru-RU', {
        minimumFractionDigits: hasDecimals ? 2 : 0,
        maximumFractionDigits: hasDecimals ? 2 : 0,
    });
});
</script>

<template>
    <div v-if="payout > 0" class="idol-payout-hint">
        <span class="idol-payout-hint__label">{{ __('common.payout_hint_prefix') }}</span>
        <span class="idol-payout-hint__value inline-flex items-center gap-1">
            <span class="idol-payout-hint__num">{{ formattedPayout }}</span>
            <CoinIcon color="pink" size="xs" />
        </span>
        <span class="idol-payout-hint__suffix">{{ __('common.payout_hint_suffix') }}</span>
    </div>
</template>

<style scoped>
.idol-payout-hint {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.3rem;
    font-size: 0.8125rem;
    line-height: 1.25;
    color: rgba(255, 255, 255, 0.65);
    margin-top: 0.35rem;
}

.idol-payout-hint__label,
.idol-payout-hint__suffix {
    user-select: none;
}

.idol-payout-hint__value {
    color: #fff;
    font-weight: 600;
}
</style>
