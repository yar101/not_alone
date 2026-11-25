<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import AdminInput from '@/Components/Admin/UI/AdminInput.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    settings:      { type: Object, default: () => ({}) },
    rating_deltas: { type: Object, default: () => ({}) },
    coinPacks:     { type: Array, default: () => [] },
});

const activeTab = ref('finance'); // 'finance', 'orders', 'rating'

const form = useForm({
    rating_low_threshold:      props.settings.rating_low_threshold ?? 30,
    order_auto_complete_delay: props.settings.order_auto_complete_delay ?? 72,
    order_dispute_window_minutes: props.settings.order_dispute_window_minutes ?? 60,
    content_pack_price_min:    props.settings.content_pack_price_min ?? 50,
    content_pack_price_max:    props.settings.content_pack_price_max ?? 50000,
    moderate_new_packs:        Boolean(props.settings.moderate_new_packs),
    moderate_existing_packs:   Boolean(props.settings.moderate_existing_packs),
    deposit_fee_percent:       props.settings.deposit_fee_percent ?? 0,
    withdrawal_fee_percent:    props.settings.withdrawal_fee_percent ?? 0,
    platform_fee_percent:      props.settings.platform_fee_percent ?? 12,
    order_customer_service_fee_percent: props.settings.order_customer_service_fee_percent ?? 5,
    donation_min_amount:       props.settings.donation_min_amount ?? 1,
    donation_max_amount:       props.settings.donation_max_amount ?? 15000,
    donation_daily_limit:      props.settings.donation_daily_limit ?? 10,
    rating_deltas:             { ...props.rating_deltas },
});

function save() {
    form.patch(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Настройки платформы успешно сохранены');
        },
        onError: () => {
            ElMessage.error('Проверьте корректность введённых данных');
        }
    });
}

// ── Event groups for rating deltas ──────────────────────────────────────────
const EVENT_GROUPS = [
    {
        label: 'Отзывы клиентов',
        description: 'Начисление за оценки в отзывах после завершения заказа',
        events: [
            { key: 'review_5star', label: 'Отзыв 5★', positive: true },
            { key: 'review_4star', label: 'Отзыв 4★', positive: true },
            { key: 'review_2star', label: 'Отзыв 2★', positive: false },
            { key: 'review_1star', label: 'Отзыв 1★', positive: false },
        ],
    },
    {
        label: 'Исполнение заказов и активность',
        description: 'Успешные сделки и выигранные апелляции',
        events: [
            { key: 'order_completed',         label: 'Заказ выполнен без претензий', positive: true },
            { key: 'review_dispute_approved', label: 'Спор по отзыву удовлетворён', positive: true },
        ],
    },
    {
        label: 'Штрафы и взыскания',
        description: 'Штрафные баллы за нарушения регламента (применяются без затухания)',
        events: [
            { key: 'report_accepted', label: 'Обоснованная жалоба клиента', positive: false },
            { key: 'order_refunded',  label: 'Возврат средств по спору заказа', positive: false },
        ],
    },
];

function step(key, dir) {
    const val = Math.round((Number(form.rating_deltas[key] || 0) + dir * 0.1) * 100) / 100;
    form.rating_deltas[key] = val;
}

// ── Preview modal ─────────────────────────────────────────────────────────────
const PREVIEW_RATINGS = [20, 50, 75, 90, 95];
const previewEvent = ref(null);
const previewLabel = ref('');

function openPreview(key, label) {
    previewEvent.value = key;
    previewLabel.value = label;
}

function closePreview() {
    previewEvent.value = null;
}

const previewRows = computed(() => {
    if (!previewEvent.value) return [];
    const base = Number(form.rating_deltas[previewEvent.value]) || 0;
    return PREVIEW_RATINGS.map(rating => {
        const effective = base > 0
            ? Math.max(0.01, Math.round(base * (100 - rating) / 80 * 100) / 100)
            : base;
        return { rating, effective };
    });
});

const previewBase = computed(() =>
    previewEvent.value ? Number(form.rating_deltas[previewEvent.value]) || 0 : 0
);

// ── Coin Packs ────────────────────────────────────────────────────────────────
const localPacks = ref((props.coinPacks || []).map(p => ({ ...p })));

watch(() => props.coinPacks, (newPacks) => {
    localPacks.value = (newPacks || []).map(p => ({ ...p }));
}, { deep: true });

const newPack = ref({
    coins: '',
    is_visible: true,
    is_starter: false,
    sort_order: 0,
});
const isCreatingPack = ref(false);
const savingPackId = ref(null);

function savePack(pack) {
    if (!pack.coins || Number(pack.coins) <= 0) {
        ElMessage.error('Укажите корректную сумму монет');
        return;
    }
    savingPackId.value = pack.id;
    router.patch(route('admin.coin-packs.update', pack.id), {
        coins: Number(pack.coins),
        is_visible: Boolean(pack.is_visible),
        is_starter: Boolean(pack.is_starter),
        sort_order: Number(pack.sort_order) || 0,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Пакет сохранён');
            savingPackId.value = null;
        },
        onError: (errors) => {
            ElMessage.error(Object.values(errors)[0] || 'Ошибка сохранения');
            savingPackId.value = null;
        },
    });
}

function deletePack(pack) {
    ElMessageBox.confirm(`Удалить пакет на ${pack.coins} монет?`, 'Удаление пакета', {
        confirmButtonText: 'Удалить',
        cancelButtonText: 'Отмена',
        type: 'warning',
    }).then(() => {
        router.delete(route('admin.coin-packs.destroy', pack.id), {
            preserveScroll: true,
            onSuccess: () => ElMessage.success('Пакет удалён'),
            onError: (errors) => ElMessage.error(Object.values(errors)[0] || 'Ошибка удаления'),
        });
    }).catch(() => {});
}

function createPack() {
    if (!newPack.value.coins || Number(newPack.value.coins) <= 0) {
        ElMessage.error('Укажите сумму монет для нового пакета');
        return;
    }
    isCreatingPack.value = true;
    router.post(route('admin.coin-packs.store'), {
        coins: Number(newPack.value.coins),
        is_visible: Boolean(newPack.value.is_visible),
        is_starter: Boolean(newPack.value.is_starter),
        sort_order: Number(newPack.value.sort_order) || 0,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Новый пакет добавлен');
            newPack.value = {
                coins: '',
                is_visible: true,
                is_starter: false,
                sort_order: (localPacks.value.length || 0),
            };
            isCreatingPack.value = false;
        },
        onError: (errors) => {
            ElMessage.error(Object.values(errors)[0] || 'Ошибка создания пакета');
            isCreatingPack.value = false;
        },
    });
}
</script>

<template>
    <div class="space-y-6 pb-20">
        <!-- Page Header -->
        <AdminPageHeader
            title="Параметры платформы"
            subtitle="Глобальная конфигурация финансовых комиссий, номинального счёта, таймеров сделок и формул рейтинга"
        >
            <template #actions>
                <AdminButton
                    variant="primary"
                    size="sm"
                    :loading="form.processing"
                    @click="save"
                >
                    <svg class="w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                    </svg>
                    {{ form.processing ? 'Сохранение...' : 'Сохранить изменения' }}
                </AdminButton>
            </template>
        </AdminPageHeader>

        <!-- Category Tabs -->
        <div class="flex items-center gap-1 border-b border-zinc-800 pb-px">
            <button
                type="button"
                @click="activeTab = 'finance'"
                class="flex items-center gap-2 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider border-b-2 transition-colors"
                :class="activeTab === 'finance'
                    ? 'border-violet-500 text-violet-400 bg-violet-500/5'
                    : 'border-transparent text-zinc-400 hover:text-zinc-200 hover:border-zinc-700'"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
                </svg>
                Финансы и Пакеты монет
            </button>

            <button
                type="button"
                @click="activeTab = 'orders'"
                class="flex items-center gap-2 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider border-b-2 transition-colors"
                :class="activeTab === 'orders'
                    ? 'border-violet-500 text-violet-400 bg-violet-500/5'
                    : 'border-transparent text-zinc-400 hover:text-zinc-200 hover:border-zinc-700'"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                Сделки и Контент-паки
            </button>

            <button
                type="button"
                @click="activeTab = 'rating'"
                class="flex items-center gap-2 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider border-b-2 transition-colors"
                :class="activeTab === 'rating'
                    ? 'border-violet-500 text-violet-400 bg-violet-500/5'
                    : 'border-transparent text-zinc-400 hover:text-zinc-200 hover:border-zinc-700'"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                Рейтинг айдолов и Формулы
            </button>
        </div>

        <form @submit.prevent="save" class="space-y-6">

            <!-- ══════════════════════════════════════════════════════════════
                 TAB 1: ФИНАНСЫ И МОНЕТЫ
                 ══════════════════════════════════════════════════════════════ -->
            <div v-show="activeTab === 'finance'" class="space-y-6">

                <!-- Комиссии и сборы -->
                <div class="bg-zinc-900/60 border border-zinc-800 rounded-xl p-5 shadow-sm space-y-4">
                    <div class="border-b border-zinc-800 pb-3">
                        <h3 class="text-sm font-semibold text-zinc-100 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                            Тарифная сетка и Сервисные сборы
                        </h3>
                        <p class="text-xs text-zinc-400 mt-0.5">В соответствии со ст. 860.1 ГК РФ (номинальный счёт) и фискализацией 54-ФЗ</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Deposit fee -->
                        <div class="p-4 rounded-lg bg-zinc-950/40 border border-zinc-800/80">
                            <div class="flex items-start justify-between">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-200">Комиссия на ввод средств (пополнение), %</label>
                                    <p class="text-[11px] text-zinc-400 mt-1">Процент, удерживаемый при покупке синих монет (по регламенту 0% — эквайринг покрывает платформа)</p>
                                </div>
                                <span class="text-xs font-mono font-bold text-violet-400">{{ form.deposit_fee_percent }}%</span>
                            </div>
                            <div class="mt-3">
                                <AdminInput
                                    v-model.number="form.deposit_fee_percent"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="50"
                                    :error="form.errors.deposit_fee_percent"
                                />
                            </div>
                        </div>

                        <!-- Withdrawal fee -->
                        <div class="p-4 rounded-lg bg-zinc-950/40 border border-zinc-800/80">
                            <div class="flex items-start justify-between">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-200">Комиссия на вывод средств, %</label>
                                    <p class="text-[11px] text-zinc-400 mt-1">Процент, удерживаемый при выплате айдолу по СБП (по регламенту 0% — комиссия за счёт платформы)</p>
                                </div>
                                <span class="text-xs font-mono font-bold text-violet-400">{{ form.withdrawal_fee_percent }}%</span>
                            </div>
                            <div class="mt-3">
                                <AdminInput
                                    v-model.number="form.withdrawal_fee_percent"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="50"
                                    :error="form.errors.withdrawal_fee_percent"
                                />
                            </div>
                        </div>

                        <!-- Platform fee -->
                        <div class="p-4 rounded-lg bg-zinc-950/40 border border-zinc-800/80">
                            <div class="flex items-start justify-between">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-200">Комиссия платформы со сделок айдола, %</label>
                                    <p class="text-[11px] text-zinc-400 mt-1">Удерживается при закрытии заказа или продаже контент-пака (стандарт: 12%)</p>
                                </div>
                                <span class="text-xs font-mono font-bold text-violet-400">{{ form.platform_fee_percent }}%</span>
                            </div>
                            <div class="mt-3">
                                <AdminInput
                                    v-model.number="form.platform_fee_percent"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="50"
                                    :error="form.errors.platform_fee_percent"
                                />
                            </div>
                        </div>

                        <!-- Customer service fee -->
                        <div class="p-4 rounded-lg bg-zinc-950/40 border border-zinc-800/80">
                            <div class="flex items-start justify-between">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-200">Сервисный сбор с покупателя, %</label>
                                    <p class="text-[11px] text-zinc-400 mt-1">Взимается с клиента поверх стоимости услуг, фискализируется отдельным чеком 54-ФЗ (стандарт: 5%)</p>
                                </div>
                                <span class="text-xs font-mono font-bold text-violet-400">{{ form.order_customer_service_fee_percent }}%</span>
                            </div>
                            <div class="mt-3">
                                <AdminInput
                                    v-model.number="form.order_customer_service_fee_percent"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="50"
                                    :error="form.errors.order_customer_service_fee_percent"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Донаты -->
                <div class="bg-zinc-900/60 border border-zinc-800 rounded-xl p-5 shadow-sm space-y-4">
                    <div class="border-b border-zinc-800 pb-3">
                        <h3 class="text-sm font-semibold text-zinc-100 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-pink-500"></span>
                            Донаты (Пожертвования)
                        </h3>
                        <p class="text-xs text-zinc-400 mt-0.5">Лимиты добровольных пожертвований пользователями в адрес айдолов</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <AdminInput
                                v-model.number="form.donation_min_amount"
                                label="Минимальный донат (🪙)"
                                type="number"
                                min="1"
                                hint="Минимальный разовый платёж (1 🪙 = 1 ₽)"
                                :error="form.errors.donation_min_amount"
                            />
                        </div>
                        <div>
                            <AdminInput
                                v-model.number="form.donation_max_amount"
                                label="Максимальный донат (🪙)"
                                type="number"
                                min="1"
                                hint="Предел разового перевода (ст. 115-ФЗ)"
                                :error="form.errors.donation_max_amount"
                            />
                        </div>
                        <div>
                            <AdminInput
                                v-model.number="form.donation_daily_limit"
                                label="Дневной лимит донатов"
                                type="number"
                                min="1"
                                hint="Макс. транзакций от 1 юзера айдолу в сутки"
                                :error="form.errors.donation_daily_limit"
                            />
                        </div>
                    </div>
                </div>

                <!-- Пакеты монет CoinPacks -->
                <div class="bg-zinc-900/60 border border-zinc-800 rounded-xl p-5 shadow-sm space-y-4">
                    <div class="border-b border-zinc-800 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-100 uppercase tracking-wider flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                Пакеты монет (CoinPack)
                            </h3>
                            <p class="text-xs text-zinc-400 mt-0.5">Пополнение доступно только фиксированными пакетами. 1 монета = 1.00 ₽ строго.</p>
                        </div>
                        <AdminBadge variant="info">Курс: 1 🪙 = 1.00 ₽</AdminBadge>
                    </div>

                    <!-- Packs table -->
                    <div class="overflow-x-auto rounded-lg border border-zinc-800/80">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-zinc-950/60 text-zinc-400 font-semibold uppercase tracking-wider border-b border-zinc-800">
                                <tr>
                                    <th class="px-4 py-3">Сумма (🪙 / ₽)</th>
                                    <th class="px-4 py-3">Видимость</th>
                                    <th class="px-4 py-3">Тип пакета</th>
                                    <th class="px-4 py-3">Порядок</th>
                                    <th class="px-4 py-3 text-right">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-800/60">
                                <tr v-for="pack in localPacks" :key="pack.id" class="hover:bg-zinc-800/20 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1.5">
                                            <input
                                                v-model.number="pack.coins"
                                                type="number"
                                                min="1"
                                                class="w-28 bg-zinc-950 border border-zinc-800 rounded px-2.5 py-1 text-xs text-zinc-100 font-mono font-medium focus:border-violet-500 outline-none"
                                            />
                                            <span class="text-zinc-500">🪙 ({{ pack.coins }} ₽)</span>
                                        </div>
                                    </td>

                                    <td class="px-4 py-3">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="pack.is_visible" class="sr-only peer" />
                                            <div class="w-9 h-5 bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                                        </label>
                                    </td>

                                    <td class="px-4 py-3">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" v-model="pack.is_starter" class="sr-only peer" />
                                            <span
                                                class="text-[11px] font-medium px-2 py-0.5 rounded transition-colors"
                                                :class="pack.is_starter ? 'bg-amber-500/10 text-amber-300 border border-amber-500/20' : 'bg-zinc-800 text-zinc-400'"
                                            >
                                                {{ pack.is_starter ? 'Стартовый (1 раз)' : 'Обычный' }}
                                            </span>
                                        </label>
                                    </td>

                                    <td class="px-4 py-3">
                                        <input
                                            v-model.number="pack.sort_order"
                                            type="number"
                                            min="0"
                                            class="w-16 bg-zinc-950 border border-zinc-800 rounded px-2 py-1 text-xs text-zinc-100 font-mono text-center focus:border-violet-500 outline-none"
                                        />
                                    </td>

                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button
                                                type="button"
                                                :disabled="savingPackId === pack.id"
                                                @click="savePack(pack)"
                                                class="px-2.5 py-1 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-xs font-medium transition-colors"
                                            >
                                                {{ savingPackId === pack.id ? '...' : 'Сохранить' }}
                                            </button>
                                            <button
                                                type="button"
                                                @click="deletePack(pack)"
                                                class="p-1 rounded hover:bg-rose-500/20 text-zinc-400 hover:text-rose-400 transition-colors"
                                                title="Удалить пакет"
                                            >
                                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="!localPacks.length">
                                    <td colspan="5" class="px-4 py-8 text-center text-xs text-zinc-500 italic">
                                        Пакетов монет нет. Добавьте первый пакет через форму ниже.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Create Pack inline card -->
                    <div class="bg-zinc-950/60 border border-zinc-800/80 rounded-lg p-4 space-y-3">
                        <div class="text-xs font-semibold uppercase text-zinc-300">Добавить новый CoinPack</div>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                            <div>
                                <label class="block text-[11px] text-zinc-400 mb-1">Сумма монет (₽)</label>
                                <input
                                    v-model.number="newPack.coins"
                                    type="number"
                                    min="1"
                                    placeholder="500"
                                    class="w-full bg-zinc-900 border border-zinc-800 rounded px-3 py-1.5 text-xs text-zinc-100 font-mono outline-none focus:border-violet-500"
                                />
                            </div>

                            <div>
                                <label class="block text-[11px] text-zinc-400 mb-1">Порядковый номер</label>
                                <input
                                    v-model.number="newPack.sort_order"
                                    type="number"
                                    min="0"
                                    placeholder="0"
                                    class="w-full bg-zinc-900 border border-zinc-800 rounded px-3 py-1.5 text-xs text-zinc-100 font-mono outline-none focus:border-violet-500"
                                />
                            </div>

                            <div class="flex items-center gap-4 py-2">
                                <label class="flex items-center gap-1.5 text-xs text-zinc-300 cursor-pointer">
                                    <input type="checkbox" v-model="newPack.is_visible" class="rounded bg-zinc-900 border-zinc-800 text-violet-500 focus:ring-0" />
                                    Видим
                                </label>
                                <label class="flex items-center gap-1.5 text-xs text-zinc-300 cursor-pointer">
                                    <input type="checkbox" v-model="newPack.is_starter" class="rounded bg-zinc-900 border-zinc-800 text-amber-500 focus:ring-0" />
                                    Стартовый
                                </label>
                            </div>

                            <div>
                                <AdminButton
                                    variant="outline"
                                    size="sm"
                                    class="w-full"
                                    :loading="isCreatingPack"
                                    @click="createPack"
                                >
                                    + Добавить пакет
                                </AdminButton>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════
                 TAB 2: СДЕЛКИ И КОНТЕНТ-ПАКИ
                 ══════════════════════════════════════════════════════════════ -->
            <div v-show="activeTab === 'orders'" class="space-y-6">

                <!-- Заказы и таймеры -->
                <div class="bg-zinc-900/60 border border-zinc-800 rounded-xl p-5 shadow-sm space-y-4">
                    <div class="border-b border-zinc-800 pb-3">
                        <h3 class="text-sm font-semibold text-zinc-100 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Таймеры жизненного цикла заказов
                        </h3>
                        <p class="text-xs text-zinc-400 mt-0.5">В соответствии со ст. 720 ГК РФ (приёмка услуг) и 60-минутным холдом оспаривания</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="p-4 rounded-lg bg-zinc-950/40 border border-zinc-800/80">
                            <div class="flex items-start justify-between">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-200">Автозавершение заказа, часов</label>
                                    <p class="text-[11px] text-zinc-400 mt-1">Через сколько часов после оплаты заказ автоматически переходит в статус Completed (стандарт: 72 ч)</p>
                                </div>
                                <span class="text-xs font-mono font-bold text-emerald-400">{{ form.order_auto_complete_delay }} ч</span>
                            </div>
                            <div class="mt-3">
                                <AdminInput
                                    v-model.number="form.order_auto_complete_delay"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    :error="form.errors.order_auto_complete_delay"
                                />
                            </div>
                        </div>

                        <div class="p-4 rounded-lg bg-zinc-950/40 border border-zinc-800/80">
                            <div class="flex items-start justify-between">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-200">Окно спора / Холд айдола, минут</label>
                                    <p class="text-[11px] text-zinc-400 mt-1">Время заморозки pink_held_balance после завершения заказа для подачи претензий (стандарт: 60 мин)</p>
                                </div>
                                <span class="text-xs font-mono font-bold text-emerald-400">{{ form.order_dispute_window_minutes }} мин</span>
                            </div>
                            <div class="mt-3">
                                <AdminInput
                                    v-model.number="form.order_dispute_window_minutes"
                                    type="number"
                                    step="1"
                                    min="1"
                                    max="10080"
                                    :error="form.errors.order_dispute_window_minutes"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Контент-паки -->
                <div class="bg-zinc-900/60 border border-zinc-800 rounded-xl p-5 shadow-sm space-y-4">
                    <div class="border-b border-zinc-800 pb-3">
                        <h3 class="text-sm font-semibold text-zinc-100 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                            Лимиты и Модерация контент-паков
                        </h3>
                        <p class="text-xs text-zinc-400 mt-0.5">Параметры ценообразования и правила премодерации фото/видео контента</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <AdminInput
                                v-model.number="form.content_pack_price_min"
                                label="Минимальная цена пака (🪙)"
                                type="number"
                                min="1"
                                hint="Айдол не может установить цену ниже этого предела"
                                :error="form.errors.content_pack_price_min"
                            />
                        </div>

                        <div>
                            <AdminInput
                                v-model.number="form.content_pack_price_max"
                                label="Максимальная цена пака (🪙)"
                                type="number"
                                min="1"
                                hint="Верхний предел стоимости для предотвращения фрода"
                                :error="form.errors.content_pack_price_max"
                            />
                        </div>

                        <div class="p-4 rounded-lg bg-zinc-950/40 border border-zinc-800/80 flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-semibold text-zinc-200">Премодерация новых паков</label>
                                <p class="text-[11px] text-zinc-400 mt-0.5">Каждый новый контент-пак требует ручного аппрува администратором перед появлением в каталоге</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer ml-4">
                                <input type="checkbox" v-model="form.moderate_new_packs" class="sr-only peer" />
                                <div class="w-11 h-6 bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-600"></div>
                            </label>
                        </div>

                        <div class="p-4 rounded-lg bg-zinc-950/40 border border-zinc-800/80 flex items-center justify-between">
                            <div>
                                <label class="block text-xs font-semibold text-zinc-200">Премодерация изменений в паках</label>
                                <p class="text-[11px] text-zinc-400 mt-0.5">Изменение названия, описания или цены уже опубликованного пака отправляется на ревью</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer ml-4">
                                <input type="checkbox" v-model="form.moderate_existing_packs" class="sr-only peer" />
                                <div class="w-11 h-6 bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-600"></div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════
                 TAB 3: РЕЙТИНГ АЙДОЛОВ И ФОРМУЛЫ
                 ══════════════════════════════════════════════════════════════ -->
            <div v-show="activeTab === 'rating'" class="space-y-6">

                <!-- Порог рейтинга -->
                <div class="bg-zinc-900/60 border border-zinc-800 rounded-xl p-5 shadow-sm space-y-4">
                    <div class="border-b border-zinc-800 pb-3">
                        <h3 class="text-sm font-semibold text-zinc-100 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            Ограничения по шкале рейтинга (0 – 100)
                        </h3>
                        <p class="text-xs text-zinc-400 mt-0.5">Порог, ниже которого действуют пониженные лимиты цен на услуги (ServicePriceLimit)</p>
                    </div>

                    <div class="max-w-md">
                        <AdminInput
                            v-model.number="form.rating_low_threshold"
                            label="Порог низкого рейтинга (баллы)"
                            type="number"
                            min="0"
                            max="100"
                            hint="Стандарт: 30 баллов. Айдолы с рейтингом < 30 ограничены в стоимости услуг"
                            :error="form.errors.rating_low_threshold"
                        />
                    </div>
                </div>

                <!-- Базовые дельты событий -->
                <div class="bg-zinc-900/60 border border-zinc-800 rounded-xl p-5 shadow-sm space-y-6">
                    <div class="border-b border-zinc-800 pb-3">
                        <h3 class="text-sm font-semibold text-zinc-100 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                            Базовые дельты изменения рейтинга
                        </h3>
                        <p class="text-xs text-zinc-400 mt-0.5">
                            Позитивные дельты затухают с приближением к 100 баллам по формуле: <code class="text-violet-300 font-mono">max(0.01, base × (100 - R) / 80)</code>. Штрафы применяются в полном объёме.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div
                            v-for="group in EVENT_GROUPS"
                            :key="group.label"
                            class="bg-zinc-950/40 border border-zinc-800/80 rounded-xl p-4 flex flex-col justify-between"
                        >
                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-300 mb-1">{{ group.label }}</h4>
                                <p class="text-[11px] text-zinc-500 mb-4">{{ group.description }}</p>

                                <div class="space-y-3">
                                    <div
                                        v-for="event in group.events"
                                        :key="event.key"
                                        class="p-2.5 rounded-lg border bg-zinc-900/50"
                                        :class="event.positive ? 'border-emerald-500/20' : 'border-rose-500/20'"
                                    >
                                        <div class="flex items-center justify-between text-xs mb-2">
                                            <span class="font-medium text-zinc-200">{{ event.label }}</span>
                                            <button
                                                type="button"
                                                @click="openPreview(event.key, event.label)"
                                                class="text-[10px] text-violet-400 hover:text-violet-300 transition-colors font-medium"
                                            >
                                                Формула →
                                            </button>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                @click="step(event.key, -1)"
                                                class="w-7 h-7 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-bold flex items-center justify-center transition-colors text-xs"
                                            >−</button>

                                            <input
                                                v-model.number="form.rating_deltas[event.key]"
                                                type="number"
                                                step="0.1"
                                                class="w-full text-center bg-zinc-950 border border-zinc-800 rounded py-1 text-xs font-mono font-semibold focus:border-violet-500 outline-none"
                                                :class="form.rating_deltas[event.key] >= 0 ? 'text-emerald-400' : 'text-rose-400'"
                                            />

                                            <button
                                                type="button"
                                                @click="step(event.key, +1)"
                                                class="w-7 h-7 rounded bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-bold flex items-center justify-center transition-colors text-xs"
                                            >+</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Bottom Action Bar -->
            <div class="sticky bottom-4 z-20 bg-zinc-900/90 backdrop-blur-md border border-zinc-800 p-4 rounded-xl flex items-center justify-between shadow-2xl">
                <div class="flex items-center gap-3">
                    <span v-if="form.wasSuccessful" class="text-xs font-medium text-emerald-400 flex items-center gap-1.5">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Все настройки успешно сохранены
                    </span>
                    <span v-else class="text-xs text-zinc-400">
                        Не забудьте нажать «Сохранить изменения» после редактирования параметров
                    </span>
                </div>

                <AdminButton
                    type="submit"
                    variant="primary"
                    size="sm"
                    :loading="form.processing"
                >
                    <svg class="w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                    </svg>
                    {{ form.processing ? 'Сохранение...' : 'Сохранить изменения' }}
                </AdminButton>
            </div>
        </form>

        <!-- Preview Modal for Rating Dampening Formula -->
        <AdminModal
            :open="Boolean(previewEvent)"
            :title="`Калькулятор затухания: ${previewLabel}`"
            description="Демонстрация эффективного изменения рейтинга айдола при разных текущих баллах"
            @close="closePreview"
        >
            <div class="space-y-4 py-2">
                <div class="p-3 bg-zinc-950/60 border border-zinc-800 rounded-lg flex items-center justify-between text-xs">
                    <span class="text-zinc-400">Базовая дельта события:</span>
                    <span class="font-mono font-bold" :class="previewBase >= 0 ? 'text-emerald-400' : 'text-rose-400'">
                        {{ previewBase >= 0 ? '+' : '' }}{{ previewBase }}
                    </span>
                </div>

                <div class="text-xs text-zinc-400 leading-relaxed">
                    <template v-if="previewBase > 0">
                        Формула затухания: <code class="text-violet-300 font-mono">effective = max(0.01, base × (100 − R) / 80)</code>. Чем выше текущий рейтинг айдола, тем сложнее получить дополнительные баллы.
                    </template>
                    <template v-else>
                        Штраф применяется в полном объёме при любом текущем рейтинге айдола для поддержания строгой дисциплины.
                    </template>
                </div>

                <div class="overflow-x-auto rounded-lg border border-zinc-800">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-zinc-950 text-zinc-400 font-semibold uppercase tracking-wider border-b border-zinc-800">
                            <tr>
                                <th class="px-3 py-2">Текущий рейтинг</th>
                                <th class="px-3 py-2">Эффективная дельта</th>
                                <th class="px-3 py-2 text-right">Итоговый рейтинг</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/60">
                            <tr v-for="row in previewRows" :key="row.rating" class="hover:bg-zinc-800/20">
                                <td class="px-3 py-2 font-mono text-zinc-300">{{ row.rating }}</td>
                                <td class="px-3 py-2 font-mono font-semibold" :class="row.effective >= 0 ? 'text-emerald-400' : 'text-rose-400'">
                                    {{ row.effective >= 0 ? '+' : '' }}{{ row.effective }}
                                </td>
                                <td class="px-3 py-2 font-mono text-right font-bold text-zinc-100">
                                    {{ Math.min(100, Math.max(0, Math.round((row.rating + row.effective) * 100) / 100)) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end">
                    <AdminButton variant="outline" size="sm" @click="closePreview">Закрыть</AdminButton>
                </div>
            </template>
        </AdminModal>
    </div>
</template>
