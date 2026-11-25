<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import { ArrowLeft, Search, Check, Close, Delete } from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    suggestions:   Object,
    currentStatus: String,
    pendingCount:  Number,
    search:        String,
});

const STATUS_TABS = [
    { key: 'pending',     label: 'Ожидают' },
    { key: 'approved',    label: 'Одобрены' },
    { key: 'implemented', label: 'Реализованы' },
    { key: 'rejected',    label: 'Отклонены' },
    { key: 'all',         label: 'Все' },
];

const search = ref(props.search ?? '');

watch(search, (val) => {
    router.get(route('admin.interests.suggestions.index'), { status: props.currentStatus, search: val }, { preserveState: true, replace: true });
});

function switchStatus(status) {
    router.get(route('admin.interests.suggestions.index'), { status, search: search.value }, { preserveState: false });
}

function approve(id) {
    router.patch(route('admin.interests.suggestions.approve', id), {}, { preserveScroll: true });
}

function reject(id) {
    router.patch(route('admin.interests.suggestions.reject', id), {}, { preserveScroll: true });
}

function implement(id) {
    router.patch(route('admin.interests.suggestions.implement', id), {}, { preserveScroll: true });
}

function reopen(id) {
    router.patch(route('admin.interests.suggestions.reopen', id), {}, { preserveScroll: true });
}

function destroy(id) {
    router.delete(route('admin.interests.suggestions.destroy', id), { preserveScroll: true });
}

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function getBadgeVariant(status) {
    switch (status) {
        case 'pending': return 'warning';
        case 'approved': return 'primary';
        case 'implemented': return 'success';
        case 'rejected': return 'danger';
        default: return 'neutral';
    }
}

function statusLabel(status) {
    if (status === 'pending') return 'Ожидает';
    if (status === 'approved') return 'Одобрено';
    if (status === 'implemented') return 'Реализовано';
    return 'Отклонено';
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <Link
                    :href="route('admin.interests.index')"
                    class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition"
                >
                    <el-icon :size="16"><ArrowLeft /></el-icon>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-zinc-100 flex items-center gap-2">
                        <span>Предложенные интересы</span>
                        <span v-if="pendingCount > 0" class="px-2 py-0.5 rounded-full text-xs font-mono bg-amber-500/20 text-amber-300 border border-amber-500/30">
                            {{ pendingCount }}
                        </span>
                    </h1>
                    <p class="text-xs text-zinc-400 mt-0.5">
                        Модерация предложений интересов от пользователей платформы
                    </p>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div class="flex items-center gap-1.5 overflow-x-auto text-xs">
                <button
                    v-for="t in STATUS_TABS"
                    :key="t.key"
                    class="px-3 py-1.5 rounded-lg border text-xs font-medium transition"
                    :class="(currentStatus || 'pending') === t.key
                        ? 'border-violet-500/50 bg-violet-500/15 text-violet-300'
                        : 'border-zinc-800 bg-zinc-900/60 text-zinc-400 hover:text-zinc-200'"
                    @click="switchStatus(t.key)"
                >
                    {{ t.label }}
                </button>
            </div>

            <div class="relative flex-1 max-w-xs">
                <el-icon class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500"><Search /></el-icon>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Поиск по названию..."
                    class="w-full h-9 pl-9 pr-3 rounded-lg border border-zinc-800 bg-zinc-900/80 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                />
            </div>
        </div>

        <!-- Table -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 overflow-hidden backdrop-blur-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40 text-zinc-400">
                            <th class="py-3 px-4 font-medium w-12">#</th>
                            <th class="py-3 px-4 font-medium">Пользователь</th>
                            <th class="py-3 px-4 font-medium">Категория</th>
                            <th class="py-3 px-4 font-medium">Название (RU)</th>
                            <th class="py-3 px-4 font-medium">Название (EN)</th>
                            <th class="py-3 px-4 font-medium">Статус</th>
                            <th class="py-3 px-4 font-medium">Дата</th>
                            <th class="py-3 px-4 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                        <tr v-if="!suggestions.data?.length">
                            <td colspan="8" class="py-12 text-center text-zinc-500">
                                Предложений интересов в этой категории нет
                            </td>
                        </tr>
                        <tr
                            v-for="s in suggestions.data"
                            :key="s.id"
                            class="hover:bg-zinc-800/30 transition"
                        >
                            <td class="py-3 px-4 font-mono text-zinc-500">#{{ s.id }}</td>
                            <td class="py-3 px-4 font-medium text-zinc-200">
                                {{ s.user?.name || s.user?.email || '—' }}
                            </td>
                            <td class="py-3 px-4 text-zinc-400">
                                {{ s.category?.name_ru || s.category_name || '—' }}
                            </td>
                            <td class="py-3 px-4 font-semibold text-zinc-100">{{ s.name_ru }}</td>
                            <td class="py-3 px-4 text-zinc-400">{{ s.name_en || '—' }}</td>
                            <td class="py-3 px-4">
                                <AdminBadge :variant="getBadgeVariant(s.status)" size="sm">
                                    {{ statusLabel(s.status) }}
                                </AdminBadge>
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-500 whitespace-nowrap">
                                {{ formatDate(s.created_at) }}
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <template v-if="s.status === 'pending'">
                                        <button
                                            type="button"
                                            class="px-2 py-1 rounded border border-emerald-900/40 bg-emerald-950/20 text-emerald-400 hover:bg-emerald-900/40 text-[11px] transition"
                                            @click="approve(s.id)"
                                        >
                                            Одобрить
                                        </button>
                                        <button
                                            type="button"
                                            class="px-2 py-1 rounded border border-rose-900/40 bg-rose-950/20 text-rose-400 hover:bg-rose-900/40 text-[11px] transition"
                                            @click="reject(s.id)"
                                        >
                                            Отклонить
                                        </button>
                                    </template>
                                    <template v-else-if="s.status === 'approved'">
                                        <button
                                            type="button"
                                            class="px-2 py-1 rounded border border-violet-900/40 bg-violet-950/20 text-violet-300 hover:bg-violet-900/40 text-[11px] transition"
                                            @click="implement(s.id)"
                                        >
                                            В справочник
                                        </button>
                                        <button
                                            type="button"
                                            class="px-2 py-1 rounded border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-200 text-[11px] transition"
                                            @click="reopen(s.id)"
                                        >
                                            Вернуть
                                        </button>
                                    </template>
                                    <template v-else>
                                        <button
                                            type="button"
                                            class="px-2 py-1 rounded border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-200 text-[11px] transition"
                                            @click="reopen(s.id)"
                                        >
                                            Вернуть
                                        </button>
                                    </template>
                                    <button
                                        type="button"
                                        class="p-1 rounded text-zinc-500 hover:text-rose-400 transition"
                                        title="Удалить"
                                        @click="destroy(s.id)"
                                    >
                                        <el-icon><Delete /></el-icon>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4 border-t border-zinc-800">
                <AdminPagination :pagination="suggestions" />
            </div>
        </div>
    </div>
</template>
