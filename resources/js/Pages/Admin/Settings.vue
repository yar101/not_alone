<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    settings:      Object,
    rating_deltas: Object,
    coinPacks:     { type: Array, default: () => [] },
});

const form = useForm({
    rating_low_threshold:      props.settings.rating_low_threshold,
    order_auto_complete_delay: props.settings.order_auto_complete_delay,
    order_dispute_window_minutes: props.settings.order_dispute_window_minutes ?? 60,
    content_pack_price_min:    props.settings.content_pack_price_min,
    content_pack_price_max:  props.settings.content_pack_price_max,
    moderate_new_packs:      props.settings.moderate_new_packs,
    moderate_existing_packs: props.settings.moderate_existing_packs,
    deposit_fee_percent:     props.settings.deposit_fee_percent ?? 4,
    withdrawal_fee_percent:  props.settings.withdrawal_fee_percent ?? 4,
    platform_fee_percent:    props.settings.platform_fee_percent ?? 10,
    order_customer_service_fee_percent: props.settings.order_customer_service_fee_percent ?? 5,
    donation_min_amount: props.settings.donation_min_amount ?? 1,
    donation_max_amount: props.settings.donation_max_amount ?? 15000,
    donation_daily_limit: props.settings.donation_daily_limit ?? 10,
    rating_deltas: { ...props.rating_deltas },
});

function save() {
    form.patch(route('admin.settings.update'), { preserveScroll: true });
}

// ── Event groups ──────────────────────────────────────────────────────────────

const EVENT_GROUPS = [
    {
        label: 'Отзывы',
        events: [
            { key: 'review_5star', label: 'Отзыв 5★', positive: true },
            { key: 'review_4star', label: 'Отзыв 4★', positive: true },
            { key: 'review_2star', label: 'Отзыв 2★', positive: false },
            { key: 'review_1star', label: 'Отзыв 1★', positive: false },
        ],
    },
    {
        label: 'Активность',
        events: [
            { key: 'order_completed',         label: 'Заказ выполнен',       positive: true },
            { key: 'review_dispute_approved',  label: 'Спор по отзыву выигран', positive: true },
        ],
    },
    {
        label: 'Штрафы',
        events: [
            { key: 'report_accepted', label: 'Репорт принят', positive: false },
            { key: 'order_refunded',  label: 'Возврат по спору заказа', positive: false },
        ],
    },
];

// ── Stepper ───────────────────────────────────────────────────────────────────

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

// ── Управление пакетами монет ─────────────────────────────────────────────────
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
    <div>
        <h1 class="page-title">Настройки платформы</h1>

        <form @submit.prevent="save" class="settings-form">

            <!-- ── Раздел: Финансы и комиссии ── -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Финансы и комиссии</h2>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Комиссия на ввод средств (пополнение), %</label>
                            <p class="field-hint">Процент, удерживаемый платформой при пополнении кошелька (0% — без комиссии)</p>
                        </div>
                        <div class="field-control">
                            <input
                                v-model.number="form.deposit_fee_percent"
                                type="number"
                                step="0.1"
                                min="0"
                                max="50"
                                class="input input--sm"
                                :class="{ 'input--err': form.errors.deposit_fee_percent }"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.deposit_fee_percent" class="err">{{ form.errors.deposit_fee_percent }}</p>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Комиссия на вывод средств, %</label>
                            <p class="field-hint">Процент, удерживаемый при выводе средств айдолом на карту/счёт</p>
                        </div>
                        <div class="field-control">
                            <input
                                v-model.number="form.withdrawal_fee_percent"
                                type="number"
                                step="0.1"
                                min="0"
                                max="50"
                                class="input input--sm"
                                :class="{ 'input--err': form.errors.withdrawal_fee_percent }"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.withdrawal_fee_percent" class="err">{{ form.errors.withdrawal_fee_percent }}</p>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Базовая комиссия сервиса по сделкам, %</label>
                            <p class="field-hint">Процент платформы при выполнении заказов и покупке контент-паков</p>
                        </div>
                        <div class="field-control">
                            <input
                                v-model.number="form.platform_fee_percent"
                                type="number"
                                step="0.1"
                                min="0"
                                max="50"
                                class="input input--sm"
                                :class="{ 'input--err': form.errors.platform_fee_percent }"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.platform_fee_percent" class="err">{{ form.errors.platform_fee_percent }}</p>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Сервисный сбор с покупателя при заказе, %</label>
                            <p class="field-hint">Процент, удерживаемый с покупателя при оформлении заказа (не возвращается при возврате/отмене)</p>
                        </div>
                        <div class="field-control">
                            <input
                                v-model.number="form.order_customer_service_fee_percent"
                                type="number"
                                step="0.1"
                                min="0"
                                max="50"
                                class="input input--sm"
                                :class="{ 'input--err': form.errors.order_customer_service_fee_percent }"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.order_customer_service_fee_percent" class="err">{{ form.errors.order_customer_service_fee_percent }}</p>
                </div>
            </div>

            <!-- ── Раздел: Донаты ── -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Донаты (пожертвования)</h2>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Минимальная сумма доната, монет</label>
                            <p class="field-hint">Минимальный размер разового пожертвования айдолу</p>
                        </div>
                        <div class="field-control">
                            <input
                                v-model.number="form.donation_min_amount"
                                type="number"
                                min="1"
                                class="input input--sm"
                                :class="{ 'input--err': form.errors.donation_min_amount }"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.donation_min_amount" class="err">{{ form.errors.donation_min_amount }}</p>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Максимальная сумма доната, монет</label>
                            <p class="field-hint">Максимальный размер разового пожертвования (в соответствии с лимитами РФ)</p>
                        </div>
                        <div class="field-control">
                            <input
                                v-model.number="form.donation_max_amount"
                                type="number"
                                min="1"
                                class="input input--sm"
                                :class="{ 'input--err': form.errors.donation_max_amount }"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.donation_max_amount" class="err">{{ form.errors.donation_max_amount }}</p>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Дневной лимит донатов на айдола</label>
                            <p class="field-hint">Максимальное количество донатов от одного пользователя одному айдолу в день (антиспам)</p>
                        </div>
                        <div class="field-control">
                            <input
                                v-model.number="form.donation_daily_limit"
                                type="number"
                                min="1"
                                class="input input--sm"
                                :class="{ 'input--err': form.errors.donation_daily_limit }"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.donation_daily_limit" class="err">{{ form.errors.donation_daily_limit }}</p>
                </div>
            </div>

            <!-- ── Раздел: Пакеты монет (пополнение счёта) ── -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Пакеты монет (пополнение счёта)</h2>
                    <p class="section-hint">Пользователи покупают синие монеты только этими пакетами. Стартовый пакет доступен 1 раз на аккаунт.</p>
                </div>

                <div class="coin-packs-body">
                    <!-- Таблица паков -->
                    <div class="coin-packs-table-wrap">
                        <table class="coin-packs-table">
                            <thead>
                                <tr>
                                    <th>Монеты (₽)</th>
                                    <th>Видимость</th>
                                    <th>Тип</th>
                                    <th>Порядок</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="pack in localPacks" :key="pack.id">
                                    <td>
                                        <input
                                            v-model.number="pack.coins"
                                            type="number"
                                            min="1"
                                            class="input input--sm input--coins"
                                        />
                                    </td>
                                    <td>
                                        <label class="toggle">
                                            <input type="checkbox" v-model="pack.is_visible" />
                                            <span class="toggle__track"></span>
                                        </label>
                                    </td>
                                    <td>
                                        <label class="pack-type-toggle">
                                            <input type="checkbox" v-model="pack.is_starter" />
                                            <span :class="pack.is_starter ? 'text-starter' : 'text-regular'">
                                                {{ pack.is_starter ? 'Стартовый' : 'Обычный' }}
                                            </span>
                                        </label>
                                    </td>
                                    <td>
                                        <input
                                            v-model.number="pack.sort_order"
                                            type="number"
                                            min="0"
                                            class="input input--sm input--order"
                                        />
                                    </td>
                                    <td>
                                        <div class="coin-pack-actions">
                                            <button
                                                type="button"
                                                class="btn-pack-save"
                                                :disabled="savingPackId === pack.id"
                                                @click="savePack(pack)"
                                            >
                                                {{ savingPackId === pack.id ? '…' : 'Сохранить' }}
                                            </button>
                                            <button
                                                type="button"
                                                class="btn-pack-delete"
                                                @click="deletePack(pack)"
                                            >
                                                Удалить
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!localPacks.length">
                                    <td colspan="5" class="empty-packs-td">
                                        Пакетов нет. Добавьте первый пакет ниже.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Форма добавления нового пака -->
                    <div class="add-pack-box">
                        <h4 class="add-pack-title">Добавить пакет монет</h4>
                        <div class="add-pack-row">
                            <div class="add-pack-col">
                                <span class="add-pack-label">Монеты (₽)</span>
                                <input
                                    v-model.number="newPack.coins"
                                    type="number"
                                    min="1"
                                    placeholder="500"
                                    class="input input--sm input--coins"
                                />
                            </div>
                            <div class="add-pack-col">
                                <span class="add-pack-label">Порядок</span>
                                <input
                                    v-model.number="newPack.sort_order"
                                    type="number"
                                    min="0"
                                    placeholder="0"
                                    class="input input--sm input--order"
                                />
                            </div>
                            <div class="add-pack-col">
                                <span class="add-pack-label">Видимость</span>
                                <label class="toggle">
                                    <input type="checkbox" v-model="newPack.is_visible" />
                                    <span class="toggle__track"></span>
                                </label>
                            </div>
                            <div class="add-pack-col">
                                <span class="add-pack-label">Стартовый (1 раз)</span>
                                <label class="pack-type-toggle">
                                    <input type="checkbox" v-model="newPack.is_starter" />
                                    <span :class="newPack.is_starter ? 'text-starter' : 'text-regular'">
                                        {{ newPack.is_starter ? 'Да' : 'Нет' }}
                                    </span>
                                </label>
                            </div>
                            <div class="add-pack-col-btn">
                                <button
                                    type="button"
                                    class="btn-add-pack"
                                    :disabled="isCreatingPack"
                                    @click="createPack"
                                >
                                    {{ isCreatingPack ? 'Создание…' : '+ Добавить' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Раздел: Заказы ── -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Заказы</h2>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Автозавершение заказа, ч</label>
                            <p class="field-hint">Через сколько часов после оплаты заказ будет завершен автоматически</p>
                        </div>
                        <div class="field-control">
                            <input
                                v-model.number="form.order_auto_complete_delay"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="input input--sm"
                                :class="{ 'input--err': form.errors.order_auto_complete_delay }"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.order_auto_complete_delay" class="err">{{ form.errors.order_auto_complete_delay }}</p>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Окно для оспаривания (холд выплаты айдолу), мин</label>
                            <p class="field-hint">Время в минутах после завершения заказа, в течение которого клиент может подать жалобу, а средства удерживаются в заморозке у айдола</p>
                        </div>
                        <div class="field-control">
                            <input
                                v-model.number="form.order_dispute_window_minutes"
                                type="number"
                                step="1"
                                min="1"
                                max="10080"
                                class="input input--sm"
                                :class="{ 'input--err': form.errors.order_dispute_window_minutes }"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.order_dispute_window_minutes" class="err">{{ form.errors.order_dispute_window_minutes }}</p>
                </div>
            </div>

            <!-- ── Раздел: Контент-паки ── -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Контент-паки</h2>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Минимальная цена</label>
                            <p class="field-hint">Айдол не сможет выставить цену ниже этого значения</p>
                        </div>
                        <div class="field-control">
                            <input
                                v-model.number="form.content_pack_price_min"
                                type="number"
                                min="1"
                                class="input input--sm"
                                :class="{ 'input--err': form.errors.content_pack_price_min }"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.content_pack_price_min" class="err">{{ form.errors.content_pack_price_min }}</p>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Максимальная цена</label>
                            <p class="field-hint">Айдол не сможет выставить цену выше этого значения</p>
                        </div>
                        <div class="field-control">
                            <input
                                v-model.number="form.content_pack_price_max"
                                type="number"
                                min="1"
                                class="input input--sm"
                                :class="{ 'input--err': form.errors.content_pack_price_max }"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.content_pack_price_max" class="err">{{ form.errors.content_pack_price_max }}</p>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Модерировать новые паки</label>
                            <p class="field-hint">Новые паки проходят проверку модератором перед публикацией</p>
                        </div>
                        <div class="field-control">
                            <label class="toggle">
                                <input type="checkbox" v-model="form.moderate_new_packs" />
                                <span class="toggle__track"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Модерировать изменения в паках</label>
                            <p class="field-hint">Изменения названия, описания и цены в опубликованных паках проходят проверку модератором</p>
                        </div>
                        <div class="field-control">
                            <label class="toggle">
                                <input type="checkbox" v-model="form.moderate_existing_packs" />
                                <span class="toggle__track"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Раздел: Рейтинг ── -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Рейтинг</h2>
                </div>

                <div class="field">
                    <div class="field-row">
                        <div class="field-meta">
                            <label class="field-label">Порог низкого рейтинга</label>
                            <p class="field-hint">Айдолы ниже этого значения ограничены в ценах на услуги</p>
                        </div>
                        <div class="field-control">
                            <input
                                v-model.number="form.rating_low_threshold"
                                type="number"
                                min="0"
                                max="100"
                                class="input input--sm"
                                :class="{ 'input--err': form.errors.rating_low_threshold }"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.rating_low_threshold" class="err">{{ form.errors.rating_low_threshold }}</p>
                </div>
            </div>

            <!-- ── Раздел: Дельты ── -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Базовые дельты событий</h2>
                    <p class="section-hint">Позитивные значения затухают с ростом рейтинга. Штрафы — полные.</p>
                </div>

                <div class="delta-groups">
                    <div
                        v-for="group in EVENT_GROUPS"
                        :key="group.label"
                        class="delta-group"
                    >
                        <div class="delta-group-label">{{ group.label }}</div>

                        <div
                            v-for="event in group.events"
                            :key="event.key"
                            class="delta-row"
                            :class="event.positive ? 'delta-row--pos' : 'delta-row--neg'"
                        >
                            <span class="delta-row__dot" />
                            <span class="delta-row__label">{{ event.label }}</span>
                            <div class="stepper" :class="{ 'stepper--err': form.errors['rating_deltas.' + event.key] }">
                                <button type="button" class="stepper__btn" @click="step(event.key, -1)">−</button>
                                <input
                                    v-model.number="form.rating_deltas[event.key]"
                                    type="number"
                                    step="0.1"
                                    class="stepper__input"
                                />
                                <button type="button" class="stepper__btn" @click="step(event.key, +1)">+</button>
                            </div>
                            <button
                                type="button"
                                class="btn-preview"
                                @click="openPreview(event.key, event.label)"
                            >
                                Пример
                            </button>
                            <p v-if="form.errors['rating_deltas.' + event.key]" class="delta-row__err">
                                {{ form.errors['rating_deltas.' + event.key] }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Сохранить ── -->
            <div class="form-actions">
                <Transition name="fade">
                    <span v-if="form.wasSuccessful" class="success-msg">Сохранено</span>
                </Transition>
                <button type="submit" class="btn-submit" :disabled="form.processing">
                    {{ form.processing ? 'Сохранение…' : 'Сохранить' }}
                </button>
            </div>
        </form>

        <!-- ── Превью ── -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="previewEvent" class="overlay" @click.self="closePreview">
                    <div class="modal">
                        <div class="modal-header">
                            <div>
                                <div class="modal-title">{{ previewLabel }}</div>
                                <div class="modal-sub">
                                    Базовая дельта:
                                    <span :class="previewBase >= 0 ? 'pos' : 'neg'">
                                        {{ previewBase >= 0 ? '+' : '' }}{{ previewBase }}
                                    </span>
                                </div>
                            </div>
                            <button class="modal-close" @click="closePreview">✕</button>
                        </div>
                        <div class="modal-body">
                            <p class="modal-hint">
                                Фактическое изменение при разных уровнях рейтинга.
                                <template v-if="previewBase > 0">
                                    Формула: <code>max(0.01, base × (100 − R) / 80)</code>
                                </template>
                                <template v-else>
                                    Штраф применяется полностью при любом рейтинге.
                                </template>
                            </p>
                            <table class="preview-table">
                                <thead>
                                    <tr>
                                        <th>Рейтинг айдола</th>
                                        <th>Эффективная дельта</th>
                                        <th>Станет</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in previewRows" :key="row.rating">
                                        <td class="td-rating">{{ row.rating }}</td>
                                        <td :class="['td-delta', row.effective >= 0 ? 'pos' : 'neg']">
                                            {{ row.effective >= 0 ? '+' : '' }}{{ row.effective }}
                                        </td>
                                        <td class="td-result">
                                            {{ Math.min(100, Math.max(0, Math.round((row.rating + row.effective) * 100) / 100)) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.page-title { font-size: 1.4rem; color: #fff; margin: 0 0 1.5rem; font-weight: 600; }

/* ── Form ── */
.settings-form { max-width: 680px; display: flex; flex-direction: column; gap: 1.25rem; }

/* ── Section ── */
.section {
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.08);
    overflow: hidden;
}

.section-header {
    padding: 0.9rem 1.1rem 0.75rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

.section-title {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(190,145,255,0.65);
    margin: 0;
}

.section-hint {
    font-size: 0.95rem;
    color: rgba(255,255,255,0.28);
    margin: 0.3rem 0 0;
}

/* ── Field (threshold) ── */
.field { padding: 0.9rem 1.1rem; }

.field-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    justify-content: space-between;
}

.field-meta { flex: 1; }
.field-label { font-size: 1.1rem; font-weight: 500; color: rgba(255,255,255,0.8); display: block; margin-bottom: 0.2rem; }
.field-hint  { font-size: 0.92rem; color: rgba(255,255,255,0.28); margin: 0; }
.field-control { flex-shrink: 0; }

/* ── Inputs ── */
.input {
    padding: 0.5rem 0.75rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.85);
    font-family: inherit;
    font-size: 1rem;
    outline: none;
    transition: border-color 0.15s;
    box-sizing: border-box;
}
.input:focus    { border-color: rgba(190,145,255,0.5); }
.input--err     { border-color: rgba(239,68,68,0.55); }
.input--sm      { width: 88px; text-align: center; }

/* ── Toggle ── */
.toggle {
    position: relative;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}
.toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
.toggle__track {
    width: 44px;
    height: 24px;
    border-radius: 12px;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.15);
    transition: background 0.2s, border-color 0.2s;
    position: relative;
}
.toggle__track::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: rgba(255,255,255,0.5);
    transition: transform 0.2s, background 0.2s;
}
.toggle input:checked + .toggle__track {
    background: rgba(255, 178, 239,0.7);
    border-color: rgba(255, 178, 239,0.5);
}
.toggle input:checked + .toggle__track::after {
    transform: translateX(20px);
    background: #fff;
}

/* hide native spinners */
.input[type=number]::-webkit-inner-spin-button,
.input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
.input[type=number] { -moz-appearance: textfield; }

/* ── Stepper ── */
.stepper {
    display: flex;
    align-items: stretch;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.05);
    flex-shrink: 0;
    transition: border-color 0.15s;
}
.stepper:focus-within { border-color: rgba(190,145,255,0.5); }
.stepper--err         { border-color: rgba(239,68,68,0.55); }

.stepper__btn {
    width: 34px;
    background: transparent;
    border: none;
    color: rgba(255,255,255,0.45);
    font-size: 1.15rem;
    line-height: 1;
    cursor: pointer;
    flex-shrink: 0;
    transition: color 0.12s, background 0.12s;
    padding: 0;
    font-family: inherit;
}
.stepper__btn:hover { color: rgba(255,255,255,0.9); background: rgba(255,255,255,0.06); }
.stepper__btn:active { background: rgba(255,255,255,0.1); }

.stepper__input {
    width: 70px;
    padding: 0.5rem 0.25rem;
    background: transparent;
    border: none;
    border-left: 1px solid rgba(255,255,255,0.08);
    border-right: 1px solid rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.9);
    font-family: inherit;
    font-size: 1.05rem;
    font-variant-numeric: tabular-nums;
    outline: none;
    text-align: center;
    -moz-appearance: textfield;
}
.stepper__input::-webkit-inner-spin-button,
.stepper__input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }

/* ── Delta groups ── */
.delta-groups { display: flex; flex-direction: column; }

.delta-group { border-bottom: 1px solid rgba(255,255,255,0.04); }
.delta-group:last-child { border-bottom: none; }

.delta-group-label {
    padding: 0.5rem 1.1rem;
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.015);
    border-bottom: 1px solid rgba(255,255,255,0.04);
}

/* ── Delta row ── */
.delta-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.7rem 1.1rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    flex-wrap: wrap;
}
.delta-row:last-child { border-bottom: none; }
.delta-row:hover { background: rgba(255,255,255,0.015); }

.delta-row__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}
.delta-row--pos .delta-row__dot { background: rgba(74,222,128,0.55); }
.delta-row--neg .delta-row__dot { background: rgba(248,113,113,0.55); }

.delta-row__label {
    flex: 1;
    font-size: 1.1rem;
    color: rgba(255,255,255,0.72);
    min-width: 0;
}

.delta-row__err {
    width: 100%;
    font-size: 0.82rem;
    color: rgba(239,68,68,0.75);
    margin: 0;
    padding-left: 1.4rem;
}

/* ── Preview button ── */
.btn-preview {
    padding: 0.4rem 0.85rem;
    border: 1px solid rgba(255,255,255,0.08);
    background: transparent;
    color: rgba(255,255,255,0.35);
    font-family: inherit;
    font-size: 0.95rem;
    cursor: pointer;
    white-space: nowrap;
    transition: color 0.12s, border-color 0.12s;
    flex-shrink: 0;
}
.btn-preview:hover { color: rgba(190,145,255,0.8); border-color: rgba(190,145,255,0.3); }

/* ── Actions ── */
.form-actions { display: flex; align-items: center; gap: 1rem; }

.success-msg { font-size: 0.92rem; color: rgba(74,222,128,0.8); }

.btn-submit {
    padding: 0.6rem 1.6rem;
    border: 1px solid rgba(190,145,255,0.4);
    background: rgba(190,145,255,0.1);
    color: rgba(255,255,255,0.9);
    font-family: inherit;
    font-size: 1.05rem;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-submit:hover:not(:disabled) { background: rgba(190,145,255,0.2); }
.btn-submit:disabled { opacity: 0.45; cursor: default; }

/* ── Error ── */
.err { font-size: 0.82rem; color: rgba(239,68,68,0.8); margin: 0.35rem 0 0; }

/* ── Modal ── */
.overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.65);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal {
    background: #0d0d14;
    border: 1px solid rgba(255,255,255,0.1);
    width: 100%;
    max-width: 440px;
    margin: 1rem;
}

.modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 1rem 1.1rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
}

.modal-title { font-size: 1rem; font-weight: 600; color: rgba(255,255,255,0.9); }
.modal-sub   { font-size: 0.86rem; color: rgba(255,255,255,0.35); margin-top: 0.2rem; }

.modal-close {
    background: none;
    border: none;
    color: rgba(255,255,255,0.35);
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    padding: 0.1rem;
    flex-shrink: 0;
}
.modal-close:hover { color: rgba(255,255,255,0.7); }

.modal-body { padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: 0.9rem; }

.modal-hint {
    font-size: 0.86rem;
    color: rgba(255,255,255,0.28);
    margin: 0;
    line-height: 1.5;
}

.modal-hint code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 0.82rem;
    color: rgba(190,145,255,0.6);
    background: rgba(190,145,255,0.06);
    padding: 0.1rem 0.3rem;
    border-radius: 4px;
}

/* ── Preview table ── */
.preview-table { width: 100%; border-collapse: collapse; }

.preview-table th {
    text-align: left;
    padding: 0.5rem 0.85rem;
    font-size: 0.76rem;
    color: rgba(255,255,255,0.28);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    border-bottom: 1px solid rgba(255,255,255,0.07);
}

.preview-table td {
    padding: 0.65rem 0.85rem;
    font-size: 0.97rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    font-variant-numeric: tabular-nums;
}

.preview-table tr:last-child td { border-bottom: none; }

.td-rating { color: rgba(255,255,255,0.5); }
.td-delta  { font-weight: 600; }
.td-result { color: rgba(255,255,255,0.65); }

.pos { color: #4ade80; }
.neg { color: #f87171; }

/* ── Transitions ── */
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.modal-enter-active, .modal-leave-active { transition: opacity 0.2s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

/* ── Coin Packs Section ── */
.coin-packs-body {
    padding: 1rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.coin-packs-table-wrap {
    overflow-x: auto;
}

.coin-packs-table {
    width: 100%;
    border-collapse: collapse;
}

.coin-packs-table th {
    text-align: left;
    padding: 0.5rem 0.65rem;
    font-size: 0.74rem;
    color: rgba(255,255,255,0.35);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}

.coin-packs-table td {
    padding: 0.6rem 0.65rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: middle;
}

.coin-packs-table tr:last-child td {
    border-bottom: none;
}

.input--coins {
    width: 95px;
    text-align: right;
    font-family: monospace;
    font-weight: 600;
}

.input--order {
    width: 65px;
    text-align: center;
}

.pack-type-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
    user-select: none;
}

.text-starter {
    color: #ffb2ef;
    font-weight: 700;
    font-size: 0.8rem;
}

.text-regular {
    color: rgba(255,255,255,0.45);
    font-size: 0.8rem;
}

.coin-pack-actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.btn-pack-save {
    padding: 0.35rem 0.65rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: #64d2ff;
    background: rgba(100, 210, 255, 0.1);
    border: 1px solid rgba(100, 210, 255, 0.25);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-pack-save:hover:not(:disabled) {
    background: rgba(100, 210, 255, 0.2);
    border-color: rgba(100, 210, 255, 0.45);
}

.btn-pack-delete {
    padding: 0.35rem 0.65rem;
    font-size: 0.78rem;
    font-weight: 500;
    color: #f87171;
    background: rgba(239, 68, 68, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-pack-delete:hover {
    background: rgba(239, 68, 68, 0.18);
    border-color: rgba(239, 68, 68, 0.4);
}

.empty-packs-td {
    text-align: center;
    padding: 1.5rem 0;
    color: rgba(255,255,255,0.3);
    font-size: 0.88rem;
}

/* ── Add Pack Box ── */
.add-pack-box {
    background: rgba(255,255,255,0.02);
    border: 1px dashed rgba(255,255,255,0.12);
    border-radius: 6px;
    padding: 0.85rem 1rem;
}

.add-pack-title {
    font-size: 0.82rem;
    font-weight: 600;
    color: rgba(255,255,255,0.7);
    margin: 0 0 0.75rem;
}

.add-pack-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.75rem;
}

.add-pack-col {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.add-pack-label {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.4);
}

.add-pack-col-btn {
    margin-left: auto;
}

.btn-add-pack {
    padding: 0.45rem 0.95rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: #fff;
    background: rgba(255, 178, 239, 0.2);
    border: 1px solid rgba(255, 178, 239, 0.4);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-add-pack:hover:not(:disabled) {
    background: rgba(255, 178, 239, 0.3);
    border-color: rgba(255, 178, 239, 0.6);
}
</style>
