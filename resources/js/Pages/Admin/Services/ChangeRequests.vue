<script setup>
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import AdminTable from '@/Components/Admin/UI/AdminTable.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import ServicesNavTabs from '@/Components/Admin/ServicesNavTabs.vue';
import { ArrowRight, View } from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    requests: Object,
});

const statusVariant = {
    pending:     'warning',
    has_remarks: 'info',
    approved:    'success',
    rejected:    'danger',
};

const statusLabel = {
    pending:     'На рассмотрении',
    has_remarks: 'Есть замечания',
    approved:    'Одобрен',
    rejected:    'Отклонён',
};

const FIELD_LABELS = {
    name:         'Название',
    name_ru:      'Название (RU)',
    name_en:      'Название (EN)',
    price:        'Цена',
    category_id:  'Категория',
    time_unit_id: 'Единица времени',
};
</script>

<template>
    <div class="space-y-6 max-w-7xl mx-auto">
        <!-- Sub-navigation Tabs -->
        <ServicesNavTabs active="change-requests" />

        <!-- Header -->
        <AdminPageHeader
            title="Запросы на изменение услуг"
            subtitle="Модерация правок, внесённых айдолами в ранее опубликованные услуги (цены, названия, категории)"
            :count="requests?.total"
        />

        <!-- Data Table -->
        <AdminTable :empty="!requests.data?.length" empty-title="Запросов на изменение не найдено">
            <template #header>
                <tr>
                    <th class="py-3 px-4">Айдол</th>
                    <th class="py-3 px-4">Услуга</th>
                    <th class="py-3 px-4">Изменяемые поля</th>
                    <th class="py-3 px-4">Статус</th>
                    <th class="py-3 px-4">Дата запроса</th>
                    <th class="py-3 px-4 text-right">Действия</th>
                </tr>
            </template>

            <tr
                v-for="r in requests.data"
                :key="r.id"
                class="hover:bg-zinc-800/40 transition-colors cursor-pointer group"
                @click="router.visit(route('admin.services.change-requests.show', r.id))"
            >
                <!-- Idol -->
                <td class="py-3 px-4">
                    <div class="flex items-center gap-2.5" v-if="r.service?.user">
                        <img
                            v-if="r.service.user.avatar_url"
                            :src="r.service.user.avatar_url"
                            class="w-8 h-8 rounded-full object-cover shrink-0 border border-zinc-700/60"
                            alt=""
                        />
                        <div
                            v-else
                            class="w-8 h-8 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center font-bold text-xs text-zinc-300 shrink-0"
                        >
                            {{ r.service.user.name?.[0] }}
                        </div>

                        <div class="min-w-0">
                            <div class="font-semibold text-zinc-100 group-hover:text-violet-300 transition-colors">
                                {{ r.service.user.name }}
                            </div>
                            <div class="text-xs text-zinc-400 truncate">
                                {{ r.service.user.email }}
                            </div>
                        </div>
                    </div>
                    <span v-else class="text-zinc-500">—</span>
                </td>

                <!-- Service -->
                <td class="py-3 px-4">
                    <div class="font-medium text-zinc-200">
                        {{ r.service?.name || '—' }}
                    </div>
                    <div class="text-[11px] text-zinc-500">ID услуги: #{{ r.service?.id }}</div>
                </td>

                <!-- Changed Fields -->
                <td class="py-3 px-4">
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="field in r.changed_fields"
                            :key="field"
                            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono bg-violet-950/60 border border-violet-800/60 text-violet-300"
                        >
                            {{ FIELD_LABELS[field] || field }}
                        </span>
                    </div>
                </td>

                <!-- Status -->
                <td class="py-3 px-4">
                    <AdminBadge :variant="statusVariant[r.status] || 'neutral'" dot size="sm">
                        {{ statusLabel[r.status] ?? r.status }}
                    </AdminBadge>
                </td>

                <!-- Date -->
                <td class="py-3 px-4 text-xs text-zinc-400 whitespace-nowrap">
                    {{ new Date(r.created_at).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                </td>

                <!-- Actions -->
                <td class="py-3 px-4 text-right whitespace-nowrap" @click.stop>
                    <AdminButton
                        variant="secondary"
                        size="sm"
                        @click="router.visit(route('admin.services.change-requests.show', r.id))"
                    >
                        <template #icon><el-icon><View /></el-icon></template>
                        Проверить
                    </AdminButton>
                </td>
            </tr>

            <template #footer>
                <AdminPagination :pagination="requests" />
            </template>
        </AdminTable>
    </div>
</template>
