<script setup>
import { ref, reactive, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { ElMessageBox } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import { formatMoney } from '@/Utils/money';
import {
    ArrowLeft, Check, Close, Warning, ArrowRight, User
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    service: Object,
});

const FIELDS = ['name_ru', 'name_en', 'price', 'category_id', 'time_unit_id'];
const FIELD_LABELS = {
    name_ru:      'Название (RU)',
    name_en:      'Название (EN)',
    price:        'Цена',
    category_id:  'Категория',
    time_unit_id: 'Единица времени',
};

const flaggedFields  = reactive(Object.fromEntries(FIELDS.map(f => [f, false])));
const fieldComments  = reactive(Object.fromEntries(FIELDS.map(f => [f, ''])));

const PRESETS = {
    name_ru: [
        'Содержит ссылки / контактную информацию',
        'Нецензурная лексика или оскорбления',
        'Опечатки или некорректный регистр',
    ],
    name_en: [
        'Contains links or contact info',
        'Inappropriate language',
        'Typos or incorrect casing',
    ],
    price: [
        'Некорректная стоимость услуги',
        'Цена превышает установленный лимит',
    ],
    category_id: [
        'Выбрана неподходящая категория',
    ],
    time_unit_id: [
        'Некорректная единица времени',
    ],
};

const isApproved  = ref(false);
const submitting  = ref(false);
const errors      = ref({});
const rejectionReason = ref('');
const showRejectModal = ref(false);

const hasAnyFlag = computed(() => Object.values(flaggedFields).some(v => v));
const canSubmit  = computed(() => isApproved.value || hasAnyFlag.value);

function onToggleFlag(field) {
    if (flaggedFields[field]) {
        isApproved.value = false;
    }
}

const STATUS_LABELS = {
    pending:     'На рассмотрении',
    approved:    'Одобрена',
    has_remarks: 'Есть замечания',
    rejected:    'Отклонена',
};

const statusVariant = {
    pending:     'warning',
    approved:    'success',
    has_remarks: 'info',
    rejected:    'danger',
};

function submitDecision() {
    if (!canSubmit.value) return;
    errors.value = {};

    const flagged = Object.keys(flaggedFields).filter(f => flaggedFields[f]);
    const comments = {};
    flagged.forEach(f => { if (fieldComments[f]) comments[f] = fieldComments[f]; });

    submitting.value = true;
    router.post(route('admin.services.moderation.decide', props.service.id), {
        decision:      isApproved.value ? 'approved' : 'has_remarks',
        flagged_fields: flagged,
        field_comments: comments,
    }, {
        preserveScroll: true,
        onError: (errs) => { errors.value = errs; submitting.value = false; },
        onFinish: () => { submitting.value = false; },
    });
}

function reject() {
    if (!rejectionReason.value.trim()) return;
    submitting.value = true;
    router.post(route('admin.services.moderation.decide', props.service.id), {
        decision:         'rejected',
        rejection_reason: rejectionReason.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            submitting.value = false;
            showRejectModal.value = false;
        },
    });
}
</script>

<template>
    <div class="space-y-6 max-w-5xl mx-auto">
        <!-- Back Link -->
        <div>
            <Link
                :href="route('admin.services.moderation.index')"
                class="inline-flex items-center gap-2 text-xs font-medium text-zinc-400 hover:text-zinc-100 transition-colors"
            >
                <el-icon :size="12"><ArrowLeft /></el-icon>
                <span>Назад к модерации услуг</span>
            </Link>
        </div>

        <!-- Header -->
        <AdminPageHeader
            :title="service.name"
            :subtitle="`ID услуги #${service.id} • Создана ${new Date(service.created_at).toLocaleString('ru')}`"
        >
            <template #badge>
                <AdminBadge :variant="statusVariant[service.status] || 'neutral'" dot size="md">
                    {{ STATUS_LABELS[service.status] ?? service.status }}
                </AdminBadge>
            </template>
        </AdminPageHeader>

        <!-- Service & Idol Overview Card -->
        <div class="p-5 rounded-2xl bg-zinc-900/60 border border-zinc-800 flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <img
                    v-if="service.user?.avatar_url"
                    :src="service.user.avatar_url"
                    class="w-12 h-12 rounded-full object-cover border border-zinc-700 shrink-0"
                    alt=""
                />
                <div class="min-w-0">
                    <div class="text-sm font-bold text-zinc-100">{{ service.user?.name }}</div>
                    <div class="text-xs text-zinc-400">{{ service.user?.email }}</div>
                    <div class="mt-1 flex items-center gap-3 text-xs text-zinc-500">
                        <span>Айдол ID: #{{ service.user?.id }}</span>
                        <span>•</span>
                        <Link :href="route('admin.users.show', service.user?.id)" class="text-violet-400 hover:underline">
                            Открыть профиль ↗
                        </Link>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-6">
                <div class="text-right">
                    <span class="text-[11px] uppercase tracking-wider text-zinc-500 block">Стоимость</span>
                    <div class="flex items-center gap-1.5 font-bold text-base text-zinc-100">
                        <span>{{ formatMoney(service.price) }}</span>
                        <CoinIcon color="blue" size="xs" />
                        <span class="text-xs text-zinc-400 font-normal">/ {{ service.time_unit?.name || (typeof service.time_unit === 'string' ? service.time_unit : 'ед.') }}</span>
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-[11px] uppercase tracking-wider text-zinc-500 block">Категория</span>
                    <span class="text-xs font-semibold text-zinc-200">{{ service.category?.name || (typeof service.category === 'string' ? service.category : '—') }}</span>
                </div>
            </div>
        </div>

        <!-- Service Parameters Review Grid -->
        <div class="space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                Параметры услуги для проверки
            </h3>

            <div class="space-y-3">
                <!-- Name RU -->
                <div class="p-4 rounded-xl border bg-zinc-900/40 border-zinc-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-zinc-400">Название (RU)</span>
                        <label v-if="service.status === 'pending'" class="inline-flex items-center gap-2 text-xs text-zinc-400 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                v-model="flaggedFields.name_ru"
                                class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-amber-500 focus:ring-amber-500"
                                @change="onToggleFlag('name_ru')"
                            />
                            <span :class="{ 'text-amber-400 font-semibold': flaggedFields.name_ru }">Есть замечание</span>
                        </label>
                    </div>
                    <div class="text-sm font-semibold text-zinc-100">{{ service.name_ru || service.name }}</div>

                    <div v-if="flaggedFields.name_ru" class="mt-3 pt-3 border-t border-zinc-800 space-y-2">
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="p in PRESETS.name_ru"
                                :key="p"
                                type="button"
                                class="text-[11px] px-2 py-0.5 rounded bg-zinc-800 text-zinc-300 hover:text-white"
                                @click="fieldComments.name_ru = p"
                            >
                                {{ p }}
                            </button>
                        </div>
                        <input
                            v-model="fieldComments.name_ru"
                            type="text"
                            class="w-full bg-zinc-950 border border-amber-900/60 rounded-lg p-2 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none"
                            placeholder="Пояснение к замечанию..."
                        />
                    </div>
                </div>

                <!-- Price -->
                <div class="p-4 rounded-xl border bg-zinc-900/40 border-zinc-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-zinc-400">Цена</span>
                        <label v-if="service.status === 'pending'" class="inline-flex items-center gap-2 text-xs text-zinc-400 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                v-model="flaggedFields.price"
                                class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-amber-500 focus:ring-amber-500"
                                @change="onToggleFlag('price')"
                            />
                            <span :class="{ 'text-amber-400 font-semibold': flaggedFields.price }">Есть замечание</span>
                        </label>
                    </div>
                    <div class="text-sm font-bold text-zinc-100 flex items-center gap-1.5">
                        <span>{{ formatMoney(service.price) }}</span>
                        <CoinIcon color="blue" size="xs" />
                    </div>

                    <div v-if="flaggedFields.price" class="mt-3 pt-3 border-t border-zinc-800 space-y-2">
                        <input
                            v-model="fieldComments.price"
                            type="text"
                            class="w-full bg-zinc-950 border border-amber-900/60 rounded-lg p-2 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none"
                            placeholder="Пояснение к замечанию по цене..."
                        />
                    </div>
                </div>

                <!-- Category -->
                <div class="p-4 rounded-xl border bg-zinc-900/40 border-zinc-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-zinc-400">Категория</span>
                        <label v-if="service.status === 'pending'" class="inline-flex items-center gap-2 text-xs text-zinc-400 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                v-model="flaggedFields.category_id"
                                class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-amber-500 focus:ring-amber-500"
                                @change="onToggleFlag('category_id')"
                            />
                            <span :class="{ 'text-amber-400 font-semibold': flaggedFields.category_id }">Есть замечание</span>
                        </label>
                    </div>
                    <div class="text-sm text-zinc-200">{{ service.category?.name || (typeof service.category === 'string' ? service.category : '—') }}</div>

                    <div v-if="flaggedFields.category_id" class="mt-3 pt-3 border-t border-zinc-800 space-y-2">
                        <input
                            v-model="fieldComments.category_id"
                            type="text"
                            class="w-full bg-zinc-950 border border-amber-900/60 rounded-lg p-2 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none"
                            placeholder="Пояснение по категории..."
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Decision Box (Pending Mode) -->
        <div v-if="service.status === 'pending'" class="p-5 rounded-2xl bg-zinc-900/60 border border-zinc-800 space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                Решение по услуге
            </h3>

            <div class="space-y-3">
                <label
                    class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer select-none transition-all"
                    :class="[
                        isApproved
                            ? 'bg-emerald-950/20 border-emerald-600/80 text-emerald-300'
                            : 'bg-zinc-900/40 border-zinc-800 text-zinc-400 hover:border-zinc-700'
                    ]"
                >
                    <input
                        type="radio"
                        :value="true"
                        v-model="isApproved"
                        class="w-4 h-4 text-emerald-600 focus:ring-emerald-500"
                        @change="Object.keys(flaggedFields).forEach(f => flaggedFields[f] = false)"
                    />
                    <div>
                        <div class="font-bold text-xs text-zinc-100">Одобрить услугу</div>
                        <div class="text-[11px] text-zinc-400">Услуга станет доступна клиентам в каталоге платформы</div>
                    </div>
                </label>

                <label
                    class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer select-none transition-all"
                    :class="[
                        hasAnyFlag && !isApproved
                            ? 'bg-amber-950/20 border-amber-600/80 text-amber-300'
                            : 'bg-zinc-900/40 border-zinc-800 text-zinc-400 hover:border-zinc-700'
                    ]"
                >
                    <input
                        type="radio"
                        :value="false"
                        v-model="isApproved"
                        class="w-4 h-4 text-amber-500 focus:ring-amber-500"
                    />
                    <div>
                        <div class="font-bold text-xs text-zinc-100">Вернуть на доработку с замечаниями</div>
                        <div class="text-[11px] text-zinc-400">Айдол получит замечания и сможет исправить услугу без потери позиции</div>
                    </div>
                </label>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-zinc-800">
                <AdminButton
                    variant="destructive"
                    size="sm"
                    @click="showRejectModal = true"
                >
                    Полностью отклонить
                </AdminButton>

                <AdminButton
                    :variant="isApproved ? 'success' : 'warning'"
                    size="md"
                    :disabled="!canSubmit || submitting"
                    :loading="submitting"
                    @click="submitDecision"
                >
                    {{ isApproved ? 'Одобрить услугу' : 'Отправить замечания айдолу' }}
                </AdminButton>
            </div>
        </div>

        <!-- Reject Modal -->
        <AdminModal
            :show="showRejectModal"
            title="Отклонить услугу?"
            subtitle="Услуга будет окончательно отклонена, айдол получит уведомление"
            @close="showRejectModal = false"
        >
            <div class="space-y-3">
                <label class="text-xs font-semibold text-zinc-300">Причина отклонения:</label>
                <textarea
                    v-model="rejectionReason"
                    rows="3"
                    class="w-full bg-zinc-950 border border-zinc-800 rounded-lg p-3 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-rose-500"
                    placeholder="Укажите подробную причину отклонения..."
                    required
                />
            </div>

            <template #footer>
                <AdminButton variant="ghost" @click="showRejectModal = false">Отмена</AdminButton>
                <AdminButton
                    variant="destructive"
                    :loading="submitting"
                    :disabled="!rejectionReason.trim()"
                    @click="reject"
                >
                    Подтвердить отклонение
                </AdminButton>
            </template>
        </AdminModal>
    </div>
</template>
