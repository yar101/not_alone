<script setup>
import { ref, computed, watch, h } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import SiteModal from '@/Components/Site/SiteModal.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { ElNotification, ElIcon } from 'element-plus';
import { Check } from '@element-plus/icons-vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    idol: {
        type: Object,
        required: true,
    },
    feePercent: {
        type: Number,
        default: 10,
    },
});

const emit = defineEmits(['close', 'donated']);
const page = usePage();

const PRESET_AMOUNTS = [50, 150, 250, 550];

const amount = ref(150);
const feeCovered = ref(false);
const message = ref('');
const loading = ref(false);
const errorMessage = ref('');

const user = computed(() => page.props.auth?.user);
const userBalance = computed(() => {
    return Number(user.value?.wallet?.balance ?? user.value?.balance ?? 0);
});

// Calculate charged amount and payout
const amountCharged = computed(() => {
    const val = Number(amount.value) || 0;
    if (val <= 0) return 0;

    if (feeCovered.value) {
        const factor = (100 - props.feePercent) / 100;
        return Number((val / factor).toFixed(2));
    }
    return Number(val.toFixed(2));
});

const idolPayout = computed(() => {
    const val = Number(amount.value) || 0;
    if (val <= 0) return 0;

    if (feeCovered.value) {
        return Number(val.toFixed(2));
    }
    const fee = Number(((val * props.feePercent) / 100).toFixed(2));
    return Number((val - fee).toFixed(2));
});

const hasSufficientBalance = computed(() => {
    return userBalance.value >= amountCharged.value;
});

const canSubmit = computed(() => {
    return (
        amount.value > 0 &&
        amountCharged.value > 0 &&
        hasSufficientBalance.value &&
        !loading.value &&
        message.value.length <= 200
    );
});

function selectPreset(val) {
    amount.value = val;
    errorMessage.value = '';
}

watch(
    () => props.show,
    (val) => {
        if (val) {
            errorMessage.value = '';
            loading.value = false;
        }
    }
);

async function submitDonation() {
    if (!canSubmit.value) return;

    loading.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.post(
            route('users.donate', props.idol.id),
            {
                amount: Number(amount.value),
                fee_covered_by_donor: feeCovered.value,
                message: message.value.trim() || null,
            },
            {
                headers: {
                    Accept: 'application/json',
                },
            }
        );

        if (response.data.ok) {
            ElNotification({
                duration: 4500,
                position: 'top-right',
                offset: 70,
                customClass: 'app-notif',
                showClose: true,
                message: h('div', { class: 'app-notif__body' }, [
                    h('div', { class: 'app-notif__icon icon--payout' }, [
                        h(ElIcon, null, { default: () => h(Check) }),
                    ]),
                    h('div', { class: 'app-notif__text' }, [
                        h('p', { class: 'app-notif__title' }, 'Успешно'),
                        h('p', { class: 'app-notif__msg' }, response.data.message || 'Донат успешно отправлен!'),
                    ]),
                ]),
            });
            message.value = '';
            emit('donated', response.data.donation);
            emit('close');
        }
    } catch (err) {
        const msg =
            err.response?.data?.message ||
            err.response?.data?.error ||
            'Произошла ошибка при отправке доната.';
        errorMessage.value = msg;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <SiteModal
        :show="show"
        variant="pink"
        max-width="480px"
        @close="$emit('close')"
    >
        <div class="donation-modal">
            <!-- Header -->
            <div class="donation-modal__header">
                <div class="donation-modal__header-left">
                    <UserAvatar :user="idol" :size="46" />
                    <div class="donation-modal__title-group">
                        <h3 class="donation-modal__title">Подарить {{ idol.name }}</h3>
                        <p class="donation-modal__subtitle">Поддержите любимого Айдола</p>
                    </div>
                </div>
            </div>

            <!-- Amount presets -->
            <div class="donation-modal__presets">
                <button
                    v-for="preset in PRESET_AMOUNTS"
                    :key="preset"
                    type="button"
                    class="donation-modal__preset-btn"
                    :class="{ 'donation-modal__preset-btn--active': amount === preset }"
                    @click="selectPreset(preset)"
                >
                    <span class="donation-modal__preset-num">{{ preset }}</span>
                    <CoinIcon color="pink" size="xs" />
                </button>
            </div>

            <!-- Custom amount input -->
            <div class="donation-modal__field">
                <label class="donation-modal__label">Сумма пожертвования</label>
                <div class="donation-modal__input-wrap">
                    <input
                        v-model.number="amount"
                        type="number"
                        min="1"
                        max="15000"
                        step="1"
                        class="donation-modal__input"
                        placeholder="Введите сумму"
                    />
                    <div class="donation-modal__input-coin">
                        <CoinIcon color="pink" size="sm" />
                    </div>
                </div>
            </div>

            <!-- Fee coverage toggle -->
            <div class="donation-modal__toggle-card">
                <div class="donation-modal__toggle-meta">
                    <span class="donation-modal__toggle-title">Айдол получит 100%</span>
                    <span class="donation-modal__toggle-hint">
                        Покрыть комиссию сервиса
                    </span>
                </div>
                <label class="donation-modal__switch">
                    <input v-model="feeCovered" type="checkbox" />
                    <span class="donation-modal__slider"></span>
                </label>
            </div>

            <!-- Calculation Breakdown -->
            <div class="donation-modal__breakdown">
                <div class="donation-modal__breakdown-row">
                    <span class="donation-modal__breakdown-label">{{ idol.name }} получит:</span>
                    <span class="donation-modal__breakdown-val text-pink">
                        {{ idolPayout }}
                        <CoinIcon color="pink" size="xs" />
                    </span>
                </div>
            </div>

            <!-- Message input -->
            <div class="donation-modal__field">
                <div class="donation-modal__label-row">
                    <label class="donation-modal__label">Сообщение (необязательно)</label>
                    <span class="donation-modal__counter" :class="{ 'text-danger': message.length > 200 }">
                        {{ message.length }}/200
                    </span>
                </div>
                <textarea
                    v-model="message"
                    rows="3"
                    maxlength="200"
                    class="donation-modal__textarea"
                    placeholder="Напишите тёплые слова..."
                ></textarea>
            </div>

            <!-- Balance indicator & Warning -->
            <div class="donation-modal__balance-card" :class="{ 'donation-modal__balance-card--warning': !hasSufficientBalance }">
                <div class="donation-modal__balance-info">
                    <span class="donation-modal__balance-title">Ваш баланс:</span>
                    <span class="donation-modal__balance-val">
                        {{ userBalance }}
                        <CoinIcon color="blue" size="xs" />
                    </span>
                </div>
                <div v-if="!hasSufficientBalance" class="donation-modal__balance-warn">
                    Недостаточно средств. <a href="/wallet" class="donation-modal__link">Пополнить баланс</a>
                </div>
            </div>

            <!-- Error message if any -->
            <div v-if="errorMessage" class="donation-modal__error">
                {{ errorMessage }}
            </div>

            <!-- Actions -->
            <div class="donation-modal__actions">
                <button
                    type="button"
                    class="donation-modal__btn-submit"
                    :disabled="!canSubmit"
                    @click="submitDonation"
                >
                    <span v-if="loading">Отправка...</span>
                    <span v-else>Подарить {{ amountCharged }}</span>
                    <CoinIcon v-if="!loading" color="blue" size="xs" />
                </button>
                <button
                    type="button"
                    class="donation-modal__btn-cancel"
                    :disabled="loading"
                    @click="$emit('close')"
                >
                    Отмена
                </button>
            </div>
        </div>
    </SiteModal>
</template>

<style scoped>
.donation-modal {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 8px 4px;
}

.donation-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.donation-modal__header-left {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.donation-modal__title-group {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.donation-modal__title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text-primary, #ffffff);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.donation-modal__subtitle {
    font-size: 0.85rem;
    color: var(--text-muted, #9ca3af);
    margin: 2px 0 0 0;
}

.donation-modal__presets {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}

.donation-modal__preset-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 8px 6px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: var(--text-primary, #ffffff);
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.15s ease;
}

.donation-modal__preset-btn:hover {
    background: rgba(244, 114, 182, 0.12);
    border-color: rgba(244, 114, 182, 0.4);
}

.donation-modal__preset-btn--active {
    background: rgba(244, 114, 182, 0.2);
    border-color: #f472b6;
    color: #f472b6;
}

.donation-modal__field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.donation-modal__label-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.donation-modal__label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-secondary, #d1d5db);
}

.donation-modal__counter {
    font-size: 0.75rem;
    color: var(--text-muted, #9ca3af);
}

.donation-modal__input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.donation-modal__input {
    width: 100%;
    padding: 10px 40px 10px 14px;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #ffffff;
    font-size: 1.05rem;
    font-weight: 600;
    outline: none;
    transition: border-color 0.15s;
}

.donation-modal__input:focus {
    border-color: #f472b6;
}

.donation-modal__input-coin {
    position: absolute;
    right: 12px;
    display: flex;
    align-items: center;
}

.donation-modal__toggle-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.donation-modal__toggle-meta {
    display: flex;
    flex-direction: column;
}

.donation-modal__toggle-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: #ffffff;
}

.donation-modal__toggle-hint {
    font-size: 0.75rem;
    color: var(--text-muted, #9ca3af);
}

/* Switch styling */
.donation-modal__switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
    flex-shrink: 0;
}

.donation-modal__switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.donation-modal__slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.15);
    transition: 0.2s;
    border-radius: 24px;
}

.donation-modal__slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.2s;
    border-radius: 50%;
}

.donation-modal__switch input:checked + .donation-modal__slider {
    background-color: #f472b6;
}

.donation-modal__switch input:checked + .donation-modal__slider:before {
    transform: translateX(20px);
}

/* Breakdown */
.donation-modal__breakdown {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 10px 14px;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.2);
    border: 1px dashed rgba(255, 255, 255, 0.12);
    font-size: 0.85rem;
}

.donation-modal__breakdown-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.donation-modal__breakdown-label {
    color: var(--text-muted, #9ca3af);
    font-weight: 500;
}

.donation-modal__breakdown-val {
    display: flex;
    align-items: center;
    gap: 4px;
    font-weight: 600;
}

.text-pink {
    color: #f472b6;
}

.donation-modal__textarea {
    width: 100%;
    padding: 10px 14px;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #ffffff;
    font-size: 0.9rem;
    resize: none;
    outline: none;
    transition: border-color 0.15s;
}

.donation-modal__textarea:focus {
    border-color: #f472b6;
}

.donation-modal__balance-card {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 8px 12px;
    border-radius: 8px;
    background: rgba(96, 165, 250, 0.08);
    border: 1px solid rgba(96, 165, 250, 0.2);
    font-size: 0.85rem;
}

.donation-modal__balance-card--warning {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
}

.donation-modal__balance-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.donation-modal__balance-title {
    color: var(--text-secondary, #d1d5db);
}

.donation-modal__balance-val {
    display: flex;
    align-items: center;
    gap: 4px;
    font-weight: 600;
    color: #60a5fa;
}

.donation-modal__balance-warn {
    font-size: 0.78rem;
    color: #f87171;
}

.donation-modal__link {
    color: #60a5fa;
    text-decoration: underline;
}

.donation-modal__error {
    padding: 8px 12px;
    border-radius: 8px;
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
    font-size: 0.85rem;
}

.donation-modal__actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    margin-top: 6px;
}

.donation-modal__btn-submit {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 22px;
    border-radius: 10px;
    background: #180d24;
    border: 1px solid rgba(244, 114, 182, 0.75);
    color: #ffffff;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1);
    transition: all 0.2s ease;
}

.donation-modal__btn-submit:hover:not(:disabled) {
    background: #271438;
    border-color: #f472b6;
    box-shadow: 0 4px 16px rgba(244, 114, 182, 0.35);
    transform: translateY(-1px);
}

.donation-modal__btn-submit:active:not(:disabled) {
    transform: translateY(0);
}

.donation-modal__btn-submit:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    border-color: rgba(255, 255, 255, 0.15);
    background: rgba(255, 255, 255, 0.05);
    box-shadow: none;
    transform: none;
}

.donation-modal__btn-cancel {
    padding: 10px 18px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: var(--text-secondary, #d1d5db);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}

.donation-modal__btn-cancel:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}
</style>
