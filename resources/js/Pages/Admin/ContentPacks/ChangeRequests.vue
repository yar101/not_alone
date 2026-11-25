<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import AdminTable from '@/Components/Admin/UI/AdminTable.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import ContentPacksNavTabs from '@/Components/Admin/ContentPacksNavTabs.vue';
import { View, ArrowRight } from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

defineProps({
    requests: Object,
});

const FIELD_LABELS = {
    title:       'Название',
    description: 'Описание',
    price:       'Цена',
};

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
</script>

<template>
    <div class="space-y-6 max-w-7xl mx-auto">
        <!-- Sub-navigation Tabs -->
        <ContentPacksNavTabs active="change-requests" />

        <!-- Header -->
        <AdminPageHeader
            title="Запросы на изменение контент-паков"
            subtitle="Модерация правок названий, описаний, цен и состава медиафайлов в опубликованных паках"
            :count="requests?.total"
        />

        <!-- Data Table -->
        <AdminTable :empty="!requests.data?.length" empty-title="Ожидающих запросов нет">
            <template #header>
                <tr>
                    <th class="py-3 px-4">Айдол</th>
                    <th class="py-3 px-4">Контент-пак</th>
                    <th class="py-3 px-4">Изменяемые поля</th>
                    <th class="py-3 px-4">Дата запроса</th>
                    <th class="py-3 px-4 text-right">Действия</th>
                </tr>
            </template>

            <tr
                v-for="req in requests.data"
                :key="req.id"
                class="hover:bg-zinc-800/40 transition-colors cursor-pointer group"
                @click="router.visit(route('admin.content-packs.change-requests.show', req.id))"
            >
                <!-- Idol -->
                <td class="py-3 px-4">
                    <div class="flex items-center gap-2.5" v-if="req.content_pack?.user">
                        <img
                            v-if="req.content_pack.user.avatar_url"
                            :src="req.content_pack.user.avatar_url"
                            class="w-8 h-8 rounded-full object-cover shrink-0 border border-zinc-700/60"
                            alt=""
                        />
                        <div
                            v-else
                            class="w-8 h-8 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center font-bold text-xs text-zinc-300 shrink-0"
                        >
                            {{ req.content_pack.user.name?.[0] }}
                        </div>
                        <div class="min-w-0">
                            <div class="font-semibold text-zinc-100 group-hover:text-violet-300 transition-colors">
                                {{ req.content_pack.user.name }}
                            </div>
                            <div class="text-xs text-zinc-400 truncate">
                                {{ req.content_pack.user.email }}
                            </div>
                        </div>
                    </div>
                    <span v-else class="text-zinc-500">—</span>
                </td>

                <!-- Pack -->
                <td class="py-3 px-4">
                    <div class="font-medium text-zinc-200">
                        {{ req.content_pack?.title ?? '—' }}
                    </div>
                    <div class="text-[11px] text-zinc-500">ID пака: #{{ req.content_pack?.id }}</div>
                </td>

                <!-- Changed Fields -->
                <td class="py-3 px-4">
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="field in req.changed_fields"
                            :key="field"
                            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono bg-violet-950/60 border border-violet-800/60 text-violet-300"
                        >
                            {{ FIELD_LABELS[field] ?? field }}
                        </span>
                    </div>
                </td>

                <!-- Date -->
                <td class="py-3 px-4 text-xs text-zinc-400 whitespace-nowrap">
                    {{ formatDate(req.created_at) }}
                </td>

                <!-- Actions -->
                <td class="py-3 px-4 text-right whitespace-nowrap" @click.stop>
                    <AdminButton
                        variant="secondary"
                        size="sm"
                        @click="router.visit(route('admin.content-packs.change-requests.show', req.id))"
                    >
                        <template #icon><el-icon><View /></el-icon></template>
                        Рассмотреть
                    </AdminButton>
                </td>
            </tr>

            <template #footer>
                <AdminPagination :pagination="requests" />
            </template>
        </AdminTable>
    </div>
</template>
