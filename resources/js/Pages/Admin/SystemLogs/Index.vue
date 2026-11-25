<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessageBox, ElMessage } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    logs: Object,
    filters: Object,
    stats: Object,
});

const currentLevel = ref(props.filters?.level || 'all');
const searchQuery = ref(props.filters?.search || '');
const expandedTraces = ref({});

function toggleTrace(id) {
    expandedTraces.value[id] = !expandedTraces.value[id];
}

function applyFilters(newLevel = currentLevel.value) {
    currentLevel.value = newLevel;
    router.get(
        route('admin.system-logs.index'),
        {
            level: newLevel,
            search: searchQuery.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
}

function refreshLogs() {
    applyFilters(currentLevel.value);
}

function clearSearch() {
    searchQuery.value = '';
    applyFilters(currentLevel.value);
}

function confirmClearLogs() {
    ElMessageBox.confirm(
        'Вы уверены, что хотите полностью очистить файл laravel.log? Это действие будет зафиксировано в журнале аудита.',
        'Очистка системных логов',
        {
            confirmButtonText: 'Да, очистить',
            cancelButtonText: 'Отмена',
            type: 'warning',
            confirmButtonClass: 'el-button--danger',
        }
    ).then(() => {
        router.delete(route('admin.system-logs.clear'), {
            preserveScroll: true,
            onSuccess: () => ElMessage.success('Лог-файл очищен'),
        });
    }).catch(() => {});
}

function copyToClipboard(text) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success('Скопировано в буфер обмена');
    }).catch(() => {
        ElMessage.error('Не удалось скопировать');
    });
}

function getLevelBadgeVariant(level) {
    const lvl = (level || '').toLowerCase();
    if (['error', 'critical', 'alert', 'emergency'].includes(lvl)) return 'danger';
    if (['warning', 'warn'].includes(lvl)) return 'warning';
    if (['info', 'notice'].includes(lvl)) return 'info';
    return 'secondary';
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <AdminPageHeader
            title="Системные логи (laravel.log)"
            :subtitle="`Размер файла: ${stats?.file_size || '0 B'}, последнее обновление: ${stats?.last_modified || '—'}`"
            :count="stats?.counts?.total ?? 0"
        >
            <template #actions>
                <div class="flex items-center gap-2">
                    <AdminButton variant="outline" size="sm" @click="refreshLogs">
                        <svg class="w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                        </svg>
                        Обновить
                    </AdminButton>

                    <AdminButton variant="danger" size="sm" @click="confirmClearLogs">
                        <svg class="w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                        </svg>
                        Очистить лог
                    </AdminButton>
                </div>
            </template>
        </AdminPageHeader>

        <!-- Filter Strip -->
        <div class="bg-zinc-900/60 border border-zinc-800 rounded-xl p-3.5 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
            <!-- Level Tabs -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 md:pb-0">
                <button
                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-1.5"
                    :class="currentLevel === 'all'
                        ? 'bg-zinc-800 text-zinc-100 border border-zinc-700'
                        : 'text-zinc-400 hover:text-zinc-200'"
                    @click="applyFilters('all')"
                >
                    Все
                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-zinc-950 text-zinc-400">{{ stats?.counts?.total ?? 0 }}</span>
                </button>

                <button
                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-1.5"
                    :class="currentLevel === 'error'
                        ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30'
                        : 'text-zinc-400 hover:text-zinc-200'"
                    @click="applyFilters('error')"
                >
                    Ошибки
                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-zinc-950 text-rose-400">{{ stats?.counts?.error ?? 0 }}</span>
                </button>

                <button
                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-1.5"
                    :class="currentLevel === 'warning'
                        ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
                        : 'text-zinc-400 hover:text-zinc-200'"
                    @click="applyFilters('warning')"
                >
                    Предупреждения
                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-zinc-950 text-amber-400">{{ stats?.counts?.warning ?? 0 }}</span>
                </button>

                <button
                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-1.5"
                    :class="currentLevel === 'info'
                        ? 'bg-blue-500/20 text-blue-300 border border-blue-500/30'
                        : 'text-zinc-400 hover:text-zinc-200'"
                    @click="applyFilters('info')"
                >
                    Инфо
                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-zinc-950 text-blue-400">{{ stats?.counts?.info ?? 0 }}</span>
                </button>

                <button
                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-1.5"
                    :class="currentLevel === 'debug'
                        ? 'bg-zinc-800 text-zinc-300 border border-zinc-700'
                        : 'text-zinc-400 hover:text-zinc-200'"
                    @click="applyFilters('debug')"
                >
                    Дебаг
                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-zinc-950 text-zinc-400">{{ stats?.counts?.debug ?? 0 }}</span>
                </button>
            </div>

            <!-- Search box -->
            <form @submit.prevent="applyFilters(currentLevel)" class="flex items-center gap-2">
                <div class="relative w-full md:w-72">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Поиск по ошибке или трейсу..."
                        class="w-full bg-zinc-950 border border-zinc-800 rounded-lg pl-3 pr-7 py-1.5 text-xs text-zinc-200 outline-none focus:border-violet-500"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="absolute right-2 top-1.5 text-zinc-500 hover:text-zinc-300 text-xs"
                        @click="clearSearch"
                    >
                        ✕
                    </button>
                </div>
                <button
                    type="submit"
                    class="px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-500 text-white text-xs font-medium transition-colors"
                >
                    Найти
                </button>
            </form>
        </div>

        <!-- Log Entries Card -->
        <div class="bg-zinc-900/60 border border-zinc-800 rounded-xl overflow-hidden shadow-sm">
            <div v-if="!logs.data?.length" class="text-center py-16">
                <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-lg">
                    ✓
                </div>
                <div class="text-sm font-medium text-zinc-300">Лог-файл пуст или записи не найдены</div>
                <p class="text-xs text-zinc-500 mt-1">Ошибок по текущему фильтру нет</p>
            </div>

            <div v-else class="divide-y divide-zinc-800/70">
                <div
                    v-for="entry in logs.data"
                    :key="entry.id"
                    class="p-4 hover:bg-zinc-800/10 transition-colors space-y-2"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-2 flex-wrap">
                            <AdminBadge :variant="getLevelBadgeVariant(entry.level)" size="sm">
                                {{ entry.level }}
                            </AdminBadge>
                            <span class="text-xs font-mono text-zinc-400">{{ entry.date }}</span>
                            <span class="text-[11px] font-mono text-zinc-500">[{{ entry.env }}]</span>
                        </div>

                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button
                                @click="copyToClipboard(entry.message)"
                                class="text-[11px] text-zinc-400 hover:text-zinc-200 transition-colors"
                                title="Скопировать текст сообщения"
                            >
                                Копировать
                            </button>
                            <button
                                v-if="entry.trace"
                                @click="toggleTrace(entry.id)"
                                class="text-[11px] text-violet-400 hover:text-violet-300 transition-colors font-medium"
                            >
                                {{ expandedTraces[entry.id] ? 'Скрыть стек ↑' : 'Стек вызовов ↓' }}
                            </button>
                        </div>
                    </div>

                    <div class="text-xs font-mono text-zinc-200 whitespace-pre-wrap break-all leading-relaxed">
                        {{ entry.message }}
                    </div>

                    <!-- Expandable Stack Trace -->
                    <div v-if="entry.trace && expandedTraces[entry.id]" class="mt-3 p-3 bg-zinc-950 border border-zinc-800/80 rounded-lg">
                        <div class="flex items-center justify-between pb-2 mb-2 border-b border-zinc-800">
                            <span class="text-[11px] font-semibold uppercase text-zinc-400">Stack Trace:</span>
                            <button
                                @click="copyToClipboard(entry.trace)"
                                class="text-[11px] text-zinc-400 hover:text-zinc-200 transition-colors"
                            >
                                Скопировать стек
                            </button>
                        </div>
                        <pre class="text-[11px] font-mono text-zinc-400 overflow-x-auto max-h-96 leading-relaxed whitespace-pre-wrap">{{ entry.trace }}</pre>
                    </div>
                </div>
            </div>
        </div>

        <AdminPagination :pagination="logs" />
    </div>
</template>
