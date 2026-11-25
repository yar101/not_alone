<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import AdminTable from '@/Components/Admin/UI/AdminTable.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminInput from '@/Components/Admin/UI/AdminInput.vue';
import AdminSelect from '@/Components/Admin/UI/AdminSelect.vue';
import AdminCheckbox from '@/Components/Admin/UI/AdminCheckbox.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import ServicesNavTabs from '@/Components/Admin/ServicesNavTabs.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import { formatMoney } from '@/Utils/money';
import {
    Check, Close, Search, Filter, Warning,
    ArrowRight, View
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    moderation_services:    Object,
    moderation_filters:     Object,
    moderation_counts:      Object,
    moderation_categories:  Array,
});

// ─── Filters ─────────────────────────────────────────────────────────────────
const filterStatus     = ref(props.moderation_filters?.status ?? 'pending');
const filterSearch     = ref(props.moderation_filters?.search ?? '');
const filterCategoryId = ref(props.moderation_filters?.category_id ?? '');
let searchTimer = null;

function applyFilters() {
    router.get(route('admin.services.moderation.index'), {
        status:      filterStatus.value,
        search:      filterSearch.value || undefined,
        category_id: filterCategoryId.value || undefined,
    }, { preserveState: true, replace: true });
}

function onSearchInput(val) {
    filterSearch.value = val;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
}

function setStatus(status) {
    filterStatus.value = status;
    applyFilters();
}

// ─── Multi-select / Bulk Actions ─────────────────────────────────────────────
const selectedIds = ref(new Set());

const allPageIds = computed(() =>
    (props.moderation_services?.data ?? []).map(s => s.id)
);

const allSelected = computed(() =>
    allPageIds.value.length > 0 && allPageIds.value.every(id => selectedIds.value.has(id))
);

function toggleSelectAll(checked) {
    if (checked) {
        allPageIds.value.forEach(id => selectedIds.value.add(id));
    } else {
        selectedIds.value.clear();
    }
    selectedIds.value = new Set(selectedIds.value);
}

function toggleRow(id) {
    const s = new Set(selectedIds.value);
    if (s.has(id)) s.delete(id);
    else s.add(id);
    selectedIds.value = s;
}

// ─── Reject Modal & Templates ────────────────────────────────────────────────
const REJECT_TEMPLATES = [
    'Название не соответствует категории услуг',
    'Слишком низкая или нереалистичная цена',
    'Описание услуги отсутствует или слишком короткое',
    'Услуга нарушает правила платформы',
    'Дублирует уже существующую услугу',
    'Недопустимый контент',
    'Требуется уточнение деталей услуги',
];

const showRejectModal = ref(false);
const rejectTarget    = ref(null);
const isBulkReject    = ref(false);
const rejectReason    = ref('');
const rejectLoading   = ref(false);

function openRejectSingle(service) {
    rejectTarget.value = service;
    isBulkReject.value = false;
    rejectReason.value = '';
    showRejectModal.value = true;
}

function openBulkReject() {
    rejectTarget.value = null;
    isBulkReject.value = true;
    rejectReason.value = '';
    showRejectModal.value = true;
}

function submitRejectModal() {
    if (!rejectReason.value.trim()) return;
    rejectLoading.value = true;

    if (isBulkReject.value) {
        router.post(route('admin.services.moderation.bulk-reject'), {
            ids: [...selectedIds.value],
            reason: rejectReason.value,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                showRejectModal.value = false;
                selectedIds.value = new Set();
            },
            onFinish: () => { rejectLoading.value = false; },
        });
    } else if (rejectTarget.value) {
        router.patch(route('admin.services.moderation.reject', rejectTarget.value.id), {
            rejection_reason: rejectReason.value,
        }, {
            preserveScroll: true,
            onSuccess: () => { showRejectModal.value = false; },
            onFinish: () => { rejectLoading.value = false; },
        });
    }
}

function approveSingle(service) {
    router.patch(route('admin.services.moderation.approve', service.id), {}, { preserveScroll: true });
}

function bulkApprove() {
    router.post(route('admin.services.moderation.bulk-approve'), {
        ids: [...selectedIds.value],
    }, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = new Set(); },
    });
}

const statusVariant = {
    pending:     'warning',
    approved:    'success',
    rejected:    'danger',
    has_remarks: 'info',
};

const statusLabel = {
    pending:     'Ожидает проверки',
    approved:    'Одобрена',
    rejected:    'Отклонена',
    has_remarks: 'Замечания',
};
</script>

<template>
    <div class="space-y-6 max-w-7xl mx-auto">
        <!-- Sub-navigation Tabs -->
        <ServicesNavTabs active="moderation" />

        <!-- Page Header -->
        <AdminPageHeader
            title="Модерация услуг"
            subtitle="Проверка публикуемых услуг айдолов, контроль цен, категорий и единиц времени"
        />

        <!-- Status Filter Tabs -->
        <div class="flex items-center gap-2 border-b border-zinc-800 pb-3 overflow-x-auto">
            <button
                type="button"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-2"
                :class="[filterStatus === 'pending' ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60']"
                @click="setStatus('pending')"
            >
                <span>Новые на проверку</span>
                <span v-if="(moderation_counts?.pending ?? 0) > 0" class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse" />
                <span v-if="moderation_counts?.pending > 0" class="text-[11px] font-bold">({{ moderation_counts.pending }})</span>
            </button>
            <button
                type="button"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                :class="[filterStatus === 'resubmitted' ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60']"
                @click="setStatus('resubmitted')"
            >
                Повторно поданные ({{ moderation_counts?.resubmitted ?? 0 }})
            </button>
            <button
                type="button"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                :class="[filterStatus === 'approved' ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60']"
                @click="setStatus('approved')"
            >
                Одобренные ({{ moderation_counts?.approved ?? 0 }})
            </button>
            <button
                type="button"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                :class="[filterStatus === 'rejected' ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60']"
                @click="setStatus('rejected')"
            >
                Отклонённые ({{ moderation_counts?.rejected ?? 0 }})
            </button>
            <button
                type="button"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                :class="[filterStatus === 'all' ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60']"
                @click="setStatus('all')"
            >
                Все
            </button>
        </div>

        <!-- Filter Toolbar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 flex-1 flex-wrap">
                <div class="w-full sm:w-72">
                    <AdminInput
                        :model-value="filterSearch"
                        placeholder="Поиск по названию или айдолу..."
                        size="md"
                        @update:model-value="onSearchInput"
                    >
                        <template #prefix>
                            <el-icon><Search /></el-icon>
                        </template>
                    </AdminInput>
                </div>

                <div class="w-48">
                    <select
                        v-model="filterCategoryId"
                        class="block w-full bg-zinc-900 border border-zinc-800 hover:border-zinc-700 text-zinc-100 rounded-lg text-xs py-2 px-3 focus:outline-none focus:ring-1 focus:ring-violet-500 transition-colors"
                        @change="applyFilters"
                    >
                        <option value="">Все категории</option>
                        <option v-for="cat in moderation_categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Bulk actions banner if selected -->
            <div v-if="selectedIds.size > 0" class="flex items-center gap-2 p-1.5 px-3 bg-violet-950/60 border border-violet-800/70 rounded-xl text-xs">
                <span class="text-violet-300 font-semibold">Выбрано: {{ selectedIds.size }}</span>
                <AdminButton variant="success" size="sm" @click="bulkApprove">
                    <template #icon><el-icon><Check /></el-icon></template>
                    Одобрить
                </AdminButton>
                <AdminButton variant="destructive" size="sm" @click="openBulkReject">
                    <template #icon><el-icon><Close /></el-icon></template>
                    Отклонить
                </AdminButton>
            </div>
        </div>

        <!-- Services Data Table -->
        <AdminTable :empty="!moderation_services.data?.length" empty-title="Услуг не найдено">
            <template #header>
                <tr>
                    <th class="py-3 px-4 w-10">
                        <input
                            type="checkbox"
                            :checked="allSelected"
                            class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                            @change="toggleSelectAll($event.target.checked)"
                        />
                    </th>
                    <th class="py-3 px-4">Услуга</th>
                    <th class="py-3 px-4">Айдол</th>
                    <th class="py-3 px-4">Категория</th>
                    <th class="py-3 px-4">Стоимость</th>
                    <th class="py-3 px-4">Статус</th>
                    <th class="py-3 px-4 text-right">Действия</th>
                </tr>
            </template>

            <tr
                v-for="service in moderation_services.data"
                :key="service.id"
                class="hover:bg-zinc-800/40 transition-colors cursor-pointer group"
                @click="router.visit(route('admin.services.moderation.show', service.id))"
            >
                <!-- Checkbox -->
                <td class="py-3 px-4" @click.stop>
                    <input
                        type="checkbox"
                        :checked="selectedIds.has(service.id)"
                        class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                        @change="toggleRow(service.id)"
                    />
                </td>

                <!-- Service Name -->
                <td class="py-3 px-4">
                    <div class="font-semibold text-zinc-100 group-hover:text-violet-300 transition-colors">
                        {{ service.name }}
                    </div>
                    <div class="text-[11px] text-zinc-500">ID: {{ service.id }}</div>
                </td>

                <!-- Idol -->
                <td class="py-3 px-4" @click.stop>
                    <div class="flex items-center gap-2.5" v-if="service.user">
                        <img
                            v-if="service.user.avatar_url"
                            :src="service.user.avatar_url"
                            class="w-7 h-7 rounded-full object-cover shrink-0 border border-zinc-700/60"
                            alt=""
                        />
                        <div
                            v-else
                            class="w-7 h-7 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center font-bold text-[10px] text-zinc-300 shrink-0"
                        >
                            {{ service.user.name?.[0] }}
                        </div>
                        <div class="min-w-0">
                            <Link :href="route('admin.users.show', service.user.id)" class="text-xs font-medium text-zinc-300 hover:text-violet-400 truncate block">
                                {{ service.user.name }}
                            </Link>
                        </div>
                    </div>
                    <span v-else class="text-zinc-600 text-xs">—</span>
                </td>

                <!-- Category -->
                <td class="py-3 px-4 text-xs text-zinc-300">
                    {{ service.category || '—' }}
                </td>

                <!-- Price -->
                <td class="py-3 px-4 whitespace-nowrap">
                    <div class="flex items-center gap-1.5 font-bold text-zinc-100 text-xs">
                        <span>{{ formatMoney(service.price) }}</span>
                        <CoinIcon color="blue" size="xs" />
                    </div>
                    <div class="text-[10px] text-zinc-500" v-if="service.time_unit">
                        / {{ service.time_unit }}
                    </div>
                </td>

                <!-- Status -->
                <td class="py-3 px-4">
                    <AdminBadge :variant="statusVariant[service.status] || 'neutral'" dot size="sm">
                        {{ statusLabel[service.status] ?? service.status }}
                    </AdminBadge>
                </td>

                <!-- Actions -->
                <td class="py-3 px-4 text-right whitespace-nowrap" @click.stop>
                    <div class="flex items-center justify-end gap-1.5" v-if="service.status === 'pending'">
                        <AdminButton variant="success" size="sm" @click="approveSingle(service)">
                            <template #icon><el-icon :size="12"><Check /></el-icon></template>
                            Одобрить
                        </AdminButton>
                        <AdminButton variant="destructive" size="sm" @click="openRejectSingle(service)">
                            <template #icon><el-icon :size="12"><Close /></el-icon></template>
                            Отклонить
                        </AdminButton>
                        <AdminButton
                            variant="secondary"
                            size="sm"
                            @click="router.visit(route('admin.services.moderation.show', service.id))"
                        >
                            Проверить
                        </AdminButton>
                    </div>

                    <AdminButton
                        v-else
                        variant="secondary"
                        size="sm"
                        @click="router.visit(route('admin.services.moderation.show', service.id))"
                    >
                        Детали
                    </AdminButton>
                </td>
            </tr>

            <template #footer>
                <AdminPagination :pagination="moderation_services" />
            </template>
        </AdminTable>

        <!-- ─── Reject Modal ────────────────────────────────────────────────── -->
        <AdminModal
            :show="showRejectModal"
            :title="isBulkReject ? `Отклонить выбранные услуги (${selectedIds.size})` : `Отклонить услугу: ${rejectTarget?.name}`"
            subtitle="Айдол получит уведомление с указанной причиной и сможет внести правки"
            @close="showRejectModal = false"
        >
            <div class="space-y-4">
                <!-- Preset chips -->
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400 block mb-2">
                        Шаблоны причин:
                    </span>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="tmpl in REJECT_TEMPLATES"
                            :key="tmpl"
                            type="button"
                            class="px-2.5 py-1 rounded-lg text-xs font-medium border transition-colors text-left"
                            :class="[
                                rejectReason === tmpl
                                    ? 'bg-rose-600 border-rose-500 text-white'
                                    : 'bg-zinc-900 border-zinc-800 text-zinc-400 hover:text-zinc-200 hover:border-zinc-700'
                            ]"
                            @click="rejectReason = tmpl"
                        >
                            {{ tmpl }}
                        </button>
                    </div>
                </div>

                <!-- Textarea -->
                <div>
                    <textarea
                        v-model="rejectReason"
                        rows="3"
                        class="w-full bg-zinc-900 border border-zinc-800 rounded-lg p-3 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500/30"
                        placeholder="Или введите подробный комментарий..."
                        required
                    />
                </div>
            </div>

            <template #footer>
                <AdminButton variant="ghost" @click="showRejectModal = false">
                    Отмена
                </AdminButton>
                <AdminButton
                    variant="destructive"
                    :loading="rejectLoading"
                    :disabled="!rejectReason.trim()"
                    @click="submitRejectModal"
                >
                    Подтвердить отклонение
                </AdminButton>
            </template>
        </AdminModal>
    </div>
</template>
