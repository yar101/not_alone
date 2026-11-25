<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { ElMessageBox } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import AdminTable from '@/Components/Admin/UI/AdminTable.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminInput from '@/Components/Admin/UI/AdminInput.vue';
import ContentPacksNavTabs from '@/Components/Admin/ContentPacksNavTabs.vue';
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import { formatMoney } from '@/Utils/money';
import {
    Check, Close, Search, View, Picture, ArrowRight
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    packs:   Object,
    filters: Object,
    counts:  Object,
});

const filterStatus = ref(props.filters?.status ?? 'pending_review');
const filterSearch = ref(props.filters?.search ?? '');
let searchTimer = null;

function applyFilters() {
    router.get(route('admin.content-packs.index'), {
        status: filterStatus.value,
        search: filterSearch.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function switchStatus(status) {
    filterStatus.value = status;
    applyFilters();
}

function onSearchInput(val) {
    filterSearch.value = val;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
}

// ─── Multi-select / Bulk ─────────────────────────────────────────────────────
const selectedIds = ref(new Set());

const allPageIds = computed(() =>
    (props.packs?.data ?? []).map(p => p.id)
);

const allSelected = computed(() => {
    const list = props.packs?.data ?? [];
    return list.length > 0 && list.every(p => selectedIds.value.has(p.id));
});

function toggleSelectAll(checked) {
    if (checked) {
        allPageIds.value.forEach(id => selectedIds.value.add(id));
    } else {
        selectedIds.value.clear();
    }
    selectedIds.value = new Set(selectedIds.value);
}

function toggleRow(id) {
    const next = new Set(selectedIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selectedIds.value = next;
}

function bulkApprove() {
    ElMessageBox.confirm(`Одобрить выбранные контент-паки (${selectedIds.value.size} шт.)?`, 'Одобрение', {
        confirmButtonText: 'Одобрить',
        cancelButtonText: 'Отмена',
        type: 'success',
    }).then(() => {
        router.post(route('admin.content-packs.bulk-approve'), {
            ids: [...selectedIds.value],
        }, {
            preserveScroll: true,
            onSuccess: () => { selectedIds.value = new Set(); },
        });
    }).catch(() => {});
}

function bulkReject() {
    ElMessageBox.confirm(`Отклонить выбранные контент-паки (${selectedIds.value.size} шт.)?`, 'Отклонение', {
        confirmButtonText: 'Отклонить',
        cancelButtonText: 'Отмена',
        type: 'warning',
        confirmButtonClass: 'el-button--danger',
    }).then(() => {
        router.post(route('admin.content-packs.bulk-reject'), {
            ids: [...selectedIds.value],
        }, {
            preserveScroll: true,
            onSuccess: () => { selectedIds.value = new Set(); },
        });
    }).catch(() => {});
}

const statusVariant = {
    pending_review: 'warning',
    resubmitted:    'warning',
    has_remarks:    'info',
    approved:       'success',
    published:      'success',
    rejected:       'danger',
};

const STATUS_LABELS = {
    pending_review: 'На рассмотрении',
    resubmitted:    'Переподано',
    has_remarks:    'Есть замечания',
    approved:       'Одобрен',
    published:      'Опубликован',
    rejected:       'Отклонён',
};

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
</script>

<template>
    <div class="space-y-6 max-w-7xl mx-auto">
        <!-- Sub-navigation Tabs -->
        <ContentPacksNavTabs active="moderation" />

        <!-- Header -->
        <AdminPageHeader
            title="Модерация контент-паков"
            subtitle="Проверка авторских фотографий, аудиофайлов и цен перед публикацией в каталоге"
            :count="packs?.total"
        />

        <!-- Status Filter Tabs -->
        <div class="flex items-center gap-2 border-b border-zinc-800 pb-3 overflow-x-auto">
            <button
                type="button"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-2 shrink-0"
                :class="[filterStatus === 'pending_review' ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60']"
                @click="switchStatus('pending_review')"
            >
                <span>На рассмотрении</span>
                <span v-if="(counts?.pending_review ?? 0) > 0" class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse" />
                <span v-if="counts?.pending_review > 0" class="text-[11px] font-bold">({{ counts.pending_review }})</span>
            </button>
            <button
                type="button"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shrink-0"
                :class="[filterStatus === 'resubmitted' ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60']"
                @click="switchStatus('resubmitted')"
            >
                Переподано ({{ counts?.resubmitted ?? 0 }})
            </button>
            <button
                type="button"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shrink-0"
                :class="[filterStatus === 'has_remarks' ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60']"
                @click="switchStatus('has_remarks')"
            >
                С замечаниями ({{ counts?.has_remarks ?? 0 }})
            </button>
            <button
                type="button"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shrink-0"
                :class="[filterStatus === 'approved' ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60']"
                @click="switchStatus('approved')"
            >
                Одобрены ({{ counts?.approved ?? 0 }})
            </button>
            <button
                type="button"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shrink-0"
                :class="[filterStatus === 'all' ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60']"
                @click="switchStatus('all')"
            >
                Все ({{ counts?.all ?? 0 }})
            </button>
        </div>

        <!-- Filter Toolbar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div class="w-full sm:w-80">
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

            <!-- Bulk actions if selected -->
            <div v-if="selectedIds.size > 0" class="flex items-center gap-2 p-1.5 px-3 bg-violet-950/60 border border-violet-800/70 rounded-xl text-xs">
                <span class="text-violet-300 font-semibold">Выбрано: {{ selectedIds.size }}</span>
                <AdminButton variant="success" size="sm" @click="bulkApprove">
                    <template #icon><el-icon><Check /></el-icon></template>
                    Одобрить
                </AdminButton>
                <AdminButton variant="destructive" size="sm" @click="bulkReject">
                    <template #icon><el-icon><Close /></el-icon></template>
                    Отклонить
                </AdminButton>
            </div>
        </div>

        <!-- Data Table -->
        <AdminTable :empty="!packs.data?.length" empty-title="Контент-паков не найдено">
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
                    <th class="py-3 px-4 w-16">Обложка</th>
                    <th class="py-3 px-4">Пак</th>
                    <th class="py-3 px-4">Айдол</th>
                    <th class="py-3 px-4">Медиа</th>
                    <th class="py-3 px-4">Стоимость</th>
                    <th class="py-3 px-4">Статус</th>
                    <th class="py-3 px-4">Дата</th>
                    <th class="py-3 px-4 text-right">Действия</th>
                </tr>
            </template>

            <tr
                v-for="pack in packs.data"
                :key="pack.id"
                class="hover:bg-zinc-800/40 transition-colors cursor-pointer group"
                @click="router.visit(route('admin.content-packs.show', pack.id))"
            >
                <!-- Checkbox -->
                <td class="py-3 px-4" @click.stop>
                    <input
                        type="checkbox"
                        :checked="selectedIds.has(pack.id)"
                        class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                        @change="toggleRow(pack.id)"
                    />
                </td>

                <!-- Cover Thumbnail -->
                <td class="py-3 px-4">
                    <img
                        v-if="pack.cover_photo?.url || pack.first_photo?.url"
                        :src="pack.cover_photo?.url || pack.first_photo?.url"
                        class="w-11 h-11 rounded-lg object-cover border border-zinc-700 shrink-0"
                        alt=""
                    />
                    <div v-else class="w-11 h-11 rounded-lg bg-zinc-800 border border-zinc-700 flex items-center justify-center text-zinc-500">
                        <el-icon :size="16"><Picture /></el-icon>
                    </div>
                </td>

                <!-- Pack Info -->
                <td class="py-3 px-4">
                    <div class="font-semibold text-zinc-100 group-hover:text-violet-300 transition-colors">
                        {{ pack.title }}
                    </div>
                    <div class="text-[11px] text-zinc-500">ID: #{{ pack.id }}</div>
                </td>

                <!-- Idol -->
                <td class="py-3 px-4" @click.stop>
                    <div class="flex items-center gap-2.5" v-if="pack.user">
                        <img
                            v-if="pack.user.avatar_url"
                            :src="pack.user.avatar_url"
                            class="w-7 h-7 rounded-full object-cover shrink-0 border border-zinc-700/60"
                            alt=""
                        />
                        <div
                            v-else
                            class="w-7 h-7 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center font-bold text-[10px] text-zinc-300 shrink-0"
                        >
                            {{ pack.user.name?.[0] }}
                        </div>
                        <div class="min-w-0">
                            <Link :href="route('admin.users.show', pack.user.id)" class="text-xs font-medium text-zinc-300 hover:text-violet-400 truncate block">
                                {{ pack.user.name }}
                            </Link>
                        </div>
                    </div>
                    <span v-else class="text-zinc-500 text-xs">—</span>
                </td>

                <!-- Media count -->
                <td class="py-3 px-4 text-xs text-zinc-400">
                    {{ pack.photos_count ?? pack.photos?.length ?? 0 }} фото
                </td>

                <!-- Price -->
                <td class="py-3 px-4 whitespace-nowrap">
                    <div class="flex items-center gap-1.5 font-bold text-zinc-100 text-xs">
                        <span>{{ formatMoney(pack.price) }}</span>
                        <CoinIcon color="blue" size="xs" />
                    </div>
                </td>

                <!-- Status -->
                <td class="py-3 px-4">
                    <AdminBadge :variant="statusVariant[pack.status] || 'neutral'" dot size="sm">
                        {{ STATUS_LABELS[pack.status] ?? pack.status }}
                    </AdminBadge>
                </td>

                <!-- Date -->
                <td class="py-3 px-4 text-xs text-zinc-400 whitespace-nowrap">
                    {{ formatDate(pack.created_at) }}
                </td>

                <!-- Actions -->
                <td class="py-3 px-4 text-right whitespace-nowrap" @click.stop>
                    <AdminButton
                        variant="secondary"
                        size="sm"
                        @click="router.visit(route('admin.content-packs.show', pack.id))"
                    >
                        <template #icon><el-icon><View /></el-icon></template>
                        Рассмотреть
                    </AdminButton>
                </td>
            </tr>

            <template #footer>
                <AdminPagination :pagination="packs" />
            </template>
        </AdminTable>
    </div>
</template>
