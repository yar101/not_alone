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
    ArrowLeft, Check, Close, Warning, ArrowRight
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    changeRequest: Object,
    service:       Object,
    fields:        Object, // field -> { current, pending, flagged, admin_comment }
});

const FIELD_LABELS = {
    name:         'Название',
    name_ru:      'Название (RU)',
    name_en:      'Название (EN)',
    price:        'Цена',
    category_id:  'Категория',
    time_unit_id: 'Единица времени',
};

// Flags for remarks
const flaggedFields = reactive(Object.fromEntries(
    Object.keys(props.fields).map(f => [f, (props.changeRequest.flagged_fields || []).includes(f)])
));
const fieldComments = reactive(Object.fromEntries(
    Object.keys(props.fields).map(f => [f, (props.changeRequest.field_comments || {})[f] || ''])
));

const isApproved   = ref(false);
const submitting   = ref(false);
const errors       = ref({});
const adminComment = ref(props.changeRequest.admin_comment || '');
const showReject   = ref(false);

function onToggleFlag(field) {
    if (flaggedFields[field]) {
        isApproved.value = false;
    }
}
const canSubmit  = computed(() => isApproved.value || hasAnyFlag.value);

const STATUS_LABELS = {
    pending:     'На рассмотрении',
    has_remarks: 'Есть замечания',
    approved:    'Одобрен',
    rejected:    'Отклонён',
};

const statusVariant = {
    pending:     'warning',
    has_remarks: 'info',
    approved:    'success',
    rejected:    'danger',
};

function formatValue(field, data, side) {
    const val = data[side];
    if (val === null || val === undefined) return '—';
    if (field === 'name') {
        if (typeof val === 'object') {
            const parts = [];
            if (val.ru) parts.push(`RU: ${val.ru}`);
            if (val.en) parts.push(`EN: ${val.en}`);
            return parts.join('\n') || '—';
        }
        return val;
    }
    if (field === 'category_id' || field === 'time_unit_id') {
        return val?.name ?? '—';
    }
    if (field === 'price') {
        return `${formatMoney(val)} 🪙`;
    }
    return String(val);
}

function isChanged(field, data) {
    if (field === 'name') {
        return JSON.stringify(data.current) !== JSON.stringify(data.pending);
    }
    if (field === 'category_id' || field === 'time_unit_id') {
        return data.current?.id !== data.pending?.id;
    }
    return data.current !== data.pending;
}

function submitDecision() {
    if (!canSubmit.value) return;
    errors.value = {};

    const flagged = Object.keys(flaggedFields).filter(f => flaggedFields[f]);
    const comments = {};
    flagged.forEach(f => { if (fieldComments[f]) comments[f] = fieldComments[f]; });

    submitting.value = true;
    router.post(route('admin.services.change-requests.decide', props.changeRequest.id), {
        decision:       isApproved.value ? 'approved' : 'has_remarks',
        flagged_fields: flagged,
        field_comments: comments,
    }, {
        preserveScroll: true,
        onError: (errs) => { errors.value = errs; submitting.value = false; },
        onFinish: () => { submitting.value = false; },
    });
}

function reject() {
    submitting.value = true;
    router.post(route('admin.services.change-requests.decide', props.changeRequest.id), {
        decision:      'rejected',
        admin_comment: adminComment.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            submitting.value = false;
            showReject.value = false;
        },
    });
}
</script>

<template>
    <div class="space-y-6 max-w-5xl mx-auto">
        <!-- Back Link -->
        <div>
            <Link
                :href="route('admin.services.change-requests.index')"
                class="inline-flex items-center gap-2 text-xs font-medium text-zinc-400 hover:text-zinc-100 transition-colors"
            >
                <el-icon :size="12"><ArrowLeft /></el-icon>
                <span>Назад ко всем запросам</span>
            </Link>
        </div>

        <!-- Header -->
        <AdminPageHeader
            :title="`Запрос на изменение: ${service?.name}`"
            :subtitle="`ID запроса #${changeRequest.id} • Подан ${new Date(changeRequest.created_at).toLocaleString('ru')}`"
        >
            <template #badge>
                <AdminBadge :variant="statusVariant[changeRequest.status] || 'neutral'" dot size="md">
                    {{ STATUS_LABELS[changeRequest.status] ?? changeRequest.status }}
                </AdminBadge>
            </template>
        </AdminPageHeader>

        <!-- Service & Idol Summary -->
        <div class="p-4 rounded-xl bg-zinc-900/60 border border-zinc-800 flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <img
                    v-if="service?.user?.avatar_url"
                    :src="service.user.avatar_url"
                    class="w-10 h-10 rounded-full object-cover border border-zinc-700 shrink-0"
                    alt=""
                />
                <div class="min-w-0">
                    <div class="text-sm font-bold text-zinc-100">{{ service?.user?.name }}</div>
                    <div class="text-xs text-zinc-400">{{ service?.user?.email }}</div>
                </div>
            </div>

            <div class="flex items-center gap-4 text-xs text-zinc-400">
                <span>Услуга ID: <strong class="text-zinc-200">#{{ service?.id }}</strong></span>
                <span>•</span>
                <Link
                    :href="route('admin.services.moderation.show', service?.id)"
                    class="text-violet-400 hover:underline"
                >
                    Карточка услуги ↗
                </Link>
            </div>
        </div>

        <!-- Side-by-Side Diff Table of Fields -->
        <div class="space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                Сравнение изменённых параметров (Diff)
            </h3>

            <div class="space-y-3">
                <div
                    v-for="(data, field) in fields"
                    :key="field"
                    class="rounded-xl border transition-all p-4"
                    :class="[
                        isChanged(field, data)
                            ? 'bg-zinc-900/70 border-violet-800/40 shadow-sm'
                            : 'bg-zinc-900/30 border-zinc-800/80 opacity-70',
                        flaggedFields[field] ? 'border-amber-600/80 bg-amber-950/10' : ''
                    ]"
                >
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-zinc-200">
                                {{ FIELD_LABELS[field] || field }}
                            </span>
                            <span
                                v-if="isChanged(field, data)"
                                class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-violet-950 text-violet-300 border border-violet-800"
                            >
                                Изменено
                            </span>
                        </div>

                        <!-- Flag remark checkbox (only when pending) -->
                        <div v-if="changeRequest.status === 'pending'" class="flex items-center gap-2">
                            <label class="inline-flex items-center gap-2 text-xs text-zinc-400 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    v-model="flaggedFields[field]"
                                    class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-amber-500 focus:ring-amber-500"
                                    @change="onToggleFlag(field)"
                                />
                                <span :class="{ 'text-amber-400 font-semibold': flaggedFields[field] }">
                                    Есть замечание к полю
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Comparison Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-mono">
                        <!-- Current (Old) -->
                        <div class="p-3 rounded-lg bg-zinc-950 border border-zinc-800/80 space-y-1">
                            <span class="text-[10px] uppercase tracking-wider text-zinc-500 block">Было (Текущее):</span>
                            <div class="text-rose-400/90 whitespace-pre-wrap">
                                {{ formatValue(field, data, 'current') }}
                            </div>
                        </div>

                        <!-- Pending (New) -->
                        <div class="p-3 rounded-lg bg-zinc-950 border border-zinc-800/80 space-y-1">
                            <span class="text-[10px] uppercase tracking-wider text-zinc-500 block">Стало (Предлагаемое):</span>
                            <div class="text-emerald-400 whitespace-pre-wrap font-semibold">
                                {{ formatValue(field, data, 'pending') }}
                            </div>
                        </div>
                    </div>

                    <!-- Remark input if flagged -->
                    <div v-if="flaggedFields[field]" class="mt-3 pt-3 border-t border-zinc-800 space-y-1.5">
                        <label class="text-[11px] font-semibold text-amber-400 block">
                            Комментарий айдолу по этому полю:
                        </label>
                        <input
                            v-model="fieldComments[field]"
                            type="text"
                            class="w-full bg-zinc-950 border border-amber-900/60 rounded-lg p-2.5 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                            placeholder="Например: Некорректная цена для данного типа услуги..."
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Decision Box (When Pending) -->
        <div v-if="changeRequest.status === 'pending'" class="p-5 rounded-2xl bg-zinc-900/60 border border-zinc-800 space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                Принятие решения по запросу
            </h3>

            <!-- Decision Options -->
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
                        <div class="font-bold text-xs text-zinc-100">Одобрить изменения</div>
                        <div class="text-[11px] text-zinc-400">Все новые значения будут моментально применены к опубликованной услуге</div>
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
                        <div class="text-[11px] text-zinc-400">Отметьте поля выше галочками и укажите конкретные замечания для айдола</div>
                    </div>
                </label>
            </div>

            <!-- Submit Buttons -->
            <div class="flex items-center justify-between pt-3 border-t border-zinc-800">
                <AdminButton
                    variant="destructive"
                    size="sm"
                    @click="showReject = true"
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
                    {{ isApproved ? 'Применить и одобрить' : 'Отправить замечания айдолу' }}
                </AdminButton>
            </div>
        </div>

        <!-- Reject Modal -->
        <AdminModal
            :show="showReject"
            title="Отклонить запрос на изменение?"
            subtitle="Айдол получит уведомление, изменения будут отменены"
            @close="showReject = false"
        >
            <div class="space-y-3">
                <label class="text-xs font-semibold text-zinc-300">Причина отклонения:</label>
                <textarea
                    v-model="adminComment"
                    rows="3"
                    class="w-full bg-zinc-950 border border-zinc-800 rounded-lg p-3 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-rose-500"
                    placeholder="Укажите причину..."
                    required
                />
            </div>

            <template #footer>
                <AdminButton variant="ghost" @click="showReject = false">Отмена</AdminButton>
                <AdminButton
                    variant="destructive"
                    :loading="submitting"
                    :disabled="!adminComment.trim()"
                    @click="reject"
                >
                    Подтвердить отклонение
                </AdminButton>
            </template>
        </AdminModal>
    </div>
</template>
