<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import SiteModal from '@/Components/Site/SiteModal.vue';
import { useTranslations } from '@/composables/useTranslations';

const { __ } = useTranslations();

const props = defineProps({
    show:  { type: Boolean, default: false },
    order: { type: Object, required: true },
});
const emit = defineEmits(['created', 'close']);

const submitting = ref(false);
const error      = ref(null);

const availableItems = computed(() =>
    props.order.items.filter(i => i.service !== null)
);
const unavailableItems = computed(() =>
    props.order.items.filter(i => i.service === null)
);
const total = computed(() =>
    availableItems.value.reduce((s, i) => s + (i.service.price ?? 0) * (i.quantity ?? 1), 0)
);
const canSubmit = computed(() => availableItems.value.length > 0 && !submitting.value);

async function submit() {
    if (!canSubmit.value) return;
    submitting.value = true;
    error.value = null;
    try {
        const res = await axios.post(route('orders.store'), {
            idol_id:  props.order.idol.id,
            services: availableItems.value.map(i => ({
                id:       i.service.id,
                quantity: i.quantity ?? 1,
            })),
        });
        emit('created', { conversation_id: res.data.conversation_id });
    } catch (e) {
        error.value = e.response?.data?.error ?? __('chat.repeat.error');
        submitting.value = false;
    }
}

function formatPrice(n) {
    const num = Number(n);
    if (isNaN(num)) return '—';
    return num.toLocaleString('ru-RU') + '\u2009₽';
}
</script>

<template>
    <SiteModal :show="show" :closeable="!submitting" variant="pink" :compact="true" max-width="720px" @close="emit('close')">
        <div class="rom">
            <!-- Idol -->
            <div class="rom__idol">
                <span class="rom__idol-label">{{ __('chat.idol') }}</span>
<span class="rom__idol-name">{{ order.idol.name }}</span>
            </div>

            <div class="rom__divider" />

            <!-- Receipt -->
            <div class="rom__receipt">
                <div v-for="item in availableItems" :key="item.id" class="rom__line">
                    <span class="rom__line-name">
                        {{ item.service.name }}
                        <span class="rom__line-qty">× {{ item.quantity ?? 1 }}</span>
                    </span>
                    <span class="rom__line-price">
                        {{ formatPrice((item.service.price ?? 0) * (item.quantity ?? 1)) }}
                        <span v-if="item.service.time_unit" class="rom__line-unit">/ {{ item.service.time_unit }}</span>
                    </span>
                </div>

                <div v-for="item in unavailableItems" :key="'u-' + item.id" class="rom__line rom__line--unavailable">
                    <span class="rom__line-name">—</span>
                    <span class="rom__line-tag">{{ __('chat.unavailable') }}</span>
                    <span class="rom__line-price">—</span>
                </div>

                <div class="rom__divider rom__divider--sm" />

                <div class="rom__total">
                    <span class="rom__total-label">{{ __('cart.total') }}</span>
                    <span class="rom__total-value">{{ formatPrice(total) }}</span>
                </div>
            </div>

            <!-- Warnings -->
            <div v-if="unavailableItems.length > 0 && availableItems.length > 0" class="rom__warn">
                {{ __('chat.repeat.warn.partial') }}
            </div>
            <div v-else-if="availableItems.length === 0" class="rom__warn rom__warn--block">
                {{ __('chat.repeat.warn.all') }}
            </div>

            <!-- Error -->
            <div v-if="error" class="rom__error">{{ error }}</div>

            <!-- Actions -->
            <div class="rom__actions">
<button class="rom__btn rom__btn--submit" :disabled="!canSubmit" @click="submit">
                    <span v-if="submitting" class="rom__spinner" />
                    <span v-else>{{ __('chat.msg.repeat') }}</span>
                </button>
            </div>
        </div>
    </SiteModal>
</template>

<style scoped>
.rom {
    font-family: 'Courier New', Courier, monospace;
    display: flex;
    flex-direction: column;
    gap: 0;
}

.rom__header {
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    color: rgba(200, 185, 255, 0.7);
    margin-bottom: 0.9rem;
}

.rom__idol {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.75rem;
}
.rom__idol-label {
    font-size: 0.92rem;
    color: rgba(180, 165, 255, 0.75);
    white-space: nowrap;
}
.rom__idol-name {
    font-size: 1.05rem;
    color: var(--color-base-1);
    font-weight: 600;
}

.rom__divider {
    border: none;
    border-top: 1px dashed rgba(160, 140, 255, 0.2);
    margin: 0.1rem 0;
}
.rom__divider--sm {
    margin: 0.5rem 0 0.4rem;
}

.rom__receipt {
    padding: 0.5rem 0 0.2rem;
}

.rom__line {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    padding: 0.35rem 0;
    font-size: 0.95rem;
}
.rom__line-name {
    flex: 1;
    color: rgba(210, 200, 255, 0.8);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rom__line-qty {
    color: rgba(160, 150, 210, 0.55);
    font-size: 0.82rem;
    margin-left: 0.35rem;
}
.rom__line-price {
    color: rgba(215, 205, 255, 0.85);
    white-space: nowrap;
}
.rom__line-unit {
    color: rgba(160, 150, 210, 0.5);
    font-size: 0.8rem;
    margin-left: 0.1rem;
}
.rom__line--unavailable .rom__line-name {
    text-decoration: line-through;
    color: rgba(160, 150, 210, 0.3);
}
.rom__line--unavailable .rom__line-price {
    color: rgba(160, 150, 210, 0.3);
}
.rom__line-tag {
    font-size: 0.78rem;
    color: rgba(200, 100, 100, 0.55);
    border: 1px solid rgba(200, 100, 100, 0.2);
    border-radius: 3px;
    padding: 0.1rem 0.3rem;
    white-space: nowrap;
    margin-left: auto;
    margin-right: 0.4rem;
}

.rom__total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.2rem 0 0.5rem;
}
.rom__total-label {
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    color: rgba(200, 185, 255, 0.5);
}
.rom__total-value {
    font-size: 1.15rem;
    font-weight: 700;
    color: rgba(230, 220, 255, 0.95);
}

.rom__warn {
    font-size: 0.82rem;
    color: rgba(255, 200, 100, 0.6);
    line-height: 1.4;
    margin-bottom: 0.5rem;
}
.rom__warn--block {
    color: rgba(255, 100, 100, 0.65);
}
.rom__error {
    font-size: 0.82rem;
    color: rgba(255, 100, 100, 0.75);
    margin-bottom: 0.5rem;
}

.rom__actions {
    display: flex;
    gap: 0.6rem;
    margin-top: 0.8rem;
    padding-top: 0.8rem;
    border-top: none;
}
.rom__btn {
    flex: 1;
    padding: 1.1rem 0.8rem;
    border-radius: 3px;
    font-size: 0.88rem;
    font-weight: 700;
    font-family: 'Courier New', Courier, monospace;
    letter-spacing: 0.06em;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s, border-color 0.15s;
}
.rom__btn--cancel {
    background: transparent;
    border: 1px solid rgba(160, 140, 255, 0.2);
    color: rgba(180, 170, 220, 0.55);
}
.rom__btn--cancel:hover:not(:disabled) {
    border-color: rgba(160, 140, 255, 0.4);
    color: rgba(180, 170, 220, 0.75);
}
.rom__btn--cancel:disabled { opacity: 0.4; cursor: default; }

.rom__btn--submit {
    position: relative;
    overflow: hidden;
    background: rgba(100, 210, 255, 0.07);
    border: 1px solid rgba(100, 210, 255, 0.3);
    color: var(--color-base-2);
    box-shadow: inset 0 1px 0 rgba(100, 210, 255, 0.1);
}
.rom__btn--submit::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(100, 210, 255, 0.7) 50%, transparent 100%);
}
.rom__btn--submit:hover:not(:disabled) {
    background: rgba(100, 210, 255, 0.13);
    border-color: rgba(100, 210, 255, 0.5);
}
.rom__btn--submit:disabled { opacity: 0.4; cursor: default; }

.rom__spinner {
    width: 12px;
    height: 12px;
    border: 2px solid rgba(255, 210, 230, 0.3);
    border-top-color: rgba(255, 210, 230, 0.9);
    border-radius: 50%;
    animation: rom-spin 0.6s linear infinite;
}
@keyframes rom-spin { to { transform: rotate(360deg); } }
</style>
