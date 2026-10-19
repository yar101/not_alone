<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessageBox, ElMessage } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';

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

function getLevelBadgeClass(level) {
    const lvl = (level || '').toLowerCase();
    if (['error', 'critical', 'alert', 'emergency'].includes(lvl)) return 'badge--error';
    if (['warning', 'warn'].includes(lvl)) return 'badge--warning';
    if (['info', 'notice'].includes(lvl)) return 'badge--info';
    return 'badge--debug';
}
</script>

<template>
    <div class="system-logs-page">
        <AdminPageHeader
            title="Системные логи"
            :subtitle="`Файл: laravel.log (${stats?.file_size || '0 B'}, изменен: ${stats?.last_modified || '—'})`"
            :count="stats?.counts?.total ?? 0"
        >
            <template #actions>
                <button class="btn btn-secondary" @click="refreshLogs" title="Обновить список логов">
                    <span>↻</span>
                    <span>Обновить</span>
                </button>
                <button class="btn btn-danger" @click="confirmClearLogs" title="Очистить файл laravel.log">
                    <span>🗑</span>
                    <span>Очистить лог</span>
                </button>
            </template>
        </AdminPageHeader>

        <!-- Stats and Level Filter Tabs -->
        <div class="filter-strip">
            <div class="level-tabs">
                <button
                    class="tab-btn"
                    :class="{ 'tab-btn--active': currentLevel === 'all' }"
                    @click="applyFilters('all')"
                >
                    Все
                    <span class="tab-count">{{ stats?.counts?.total ?? 0 }}</span>
                </button>
                <button
                    class="tab-btn tab-btn--error"
                    :class="{ 'tab-btn--active': currentLevel === 'error' }"
                    @click="applyFilters('error')"
                >
                    Ошибки
                    <span class="tab-count">{{ stats?.counts?.error ?? 0 }}</span>
                </button>
                <button
                    class="tab-btn tab-btn--warning"
                    :class="{ 'tab-btn--active': currentLevel === 'warning' }"
                    @click="applyFilters('warning')"
                >
                    Предупреждения
                    <span class="tab-count">{{ stats?.counts?.warning ?? 0 }}</span>
                </button>
                <button
                    class="tab-btn tab-btn--info"
                    :class="{ 'tab-btn--active': currentLevel === 'info' }"
                    @click="applyFilters('info')"
                >
                    Инфо
                    <span class="tab-count">{{ stats?.counts?.info ?? 0 }}</span>
                </button>
                <button
                    class="tab-btn tab-btn--debug"
                    :class="{ 'tab-btn--active': currentLevel === 'debug' }"
                    @click="applyFilters('debug')"
                >
                    Дебаг
                    <span class="tab-count">{{ stats?.counts?.debug ?? 0 }}</span>
                </button>
            </div>

            <!-- Search box -->
            <form @submit.prevent="applyFilters(currentLevel)" class="search-form">
                <div class="search-wrap">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Поиск по тексту ошибки или стеку..."
                        class="search-input"
                    />
                    <button v-if="searchQuery" type="button" class="btn-clear" @click="clearSearch">✕</button>
                </div>
                <button type="submit" class="btn btn-search">Найти</button>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="table-card">
            <div v-if="!logs.data?.length" class="empty-state">
                <div class="empty-icon">✓</div>
                <div class="empty-text">Логи не найдены или файл пуст</div>
            </div>

            <div v-else class="log-entries">
                <div
                    v-for="entry in logs.data"
                    :key="entry.id"
                    class="log-row"
                    :class="{ 'log-row--expanded': expandedTraces[entry.id] }"
                >
                    <div class="log-main">
                        <div class="log-meta">
                            <span :class="['level-badge', getLevelBadgeClass(entry.level)]">
                                {{ entry.level }}
                            </span>
                            <span class="log-date">{{ entry.date }}</span>
                            <span class="log-env">[{{ entry.env }}]</span>
                        </div>

                        <div class="log-message-wrap">
                            <div class="log-message">{{ entry.message }}</div>
                            <div class="log-actions">
                                <button
                                    class="action-link"
                                    @click="copyToClipboard(entry.message)"
                                    title="Скопировать сообщение"
                                >
                                    Копировать
                                </button>
                                <button
                                    v-if="entry.trace"
                                    class="action-link"
                                    @click="toggleTrace(entry.id)"
                                >
                                    {{ expandedTraces[entry.id] ? 'Скрыть стек ↑' : 'Показать стек ↓' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Expandable Stack Trace -->
                    <div v-if="entry.trace && expandedTraces[entry.id]" class="log-trace-wrap">
                        <div class="trace-header">
                            <span>Стек вызовов (Stack Trace):</span>
                            <button
                                class="action-link"
                                @click="copyToClipboard(entry.trace)"
                                title="Скопировать стек ошибки"
                            >
                                Скопировать стек
                            </button>
                        </div>
                        <pre class="log-trace">{{ entry.trace }}</pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reusable Pagination -->
        <AdminPagination :pagination="logs" />
    </div>
</template>

<style scoped>
.system-logs-page {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* Filter Strip */
.filter-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    padding: 0.75rem 1rem;
}

.level-tabs {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.4rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.6);
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.1);
    cursor: pointer;
    transition: all 0.15s ease;
    font-family: inherit;
}

.tab-btn:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.05);
}

.tab-btn--active {
    color: #fff;
    font-weight: 600;
    background: rgba(155, 110, 232, 0.15);
    border-color: #9b6ee8;
}

.tab-count {
    padding: 0.1rem 0.4rem;
    font-size: 0.7rem;
    font-weight: 700;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.08);
}

.tab-btn--error.tab-btn--active {
    background: rgba(239, 68, 68, 0.15);
    border-color: #ef4444;
    color: #f87171;
}

.tab-btn--warning.tab-btn--active {
    background: rgba(245, 158, 11, 0.15);
    border-color: #f59e0b;
    color: #fbbf24;
}

.tab-btn--info.tab-btn--active {
    background: rgba(59, 130, 246, 0.15);
    border-color: #3b82f6;
    color: #60a5fa;
}

/* Search Box */
.search-form {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.search-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.search-input {
    width: 280px;
    padding: 0.45rem 0.75rem;
    font-size: 0.82rem;
    color: #fff;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    font-family: inherit;
    outline: none;
    transition: border-color 0.15s;
}

.search-input:focus {
    border-color: #9b6ee8;
}

.btn-clear {
    position: absolute;
    right: 0.5rem;
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    font-size: 0.8rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.85rem;
    font-size: 0.82rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: all 0.15s ease;
    font-family: inherit;
}

.btn-secondary {
    color: rgba(255, 255, 255, 0.8);
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.btn-danger {
    color: #f87171;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.25);
}

.btn-danger:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.45);
    color: #fff;
}

.btn-search {
    color: #c084fc;
    background: rgba(155, 110, 232, 0.12);
    border: 1px solid rgba(155, 110, 232, 0.3);
}

.btn-search:hover {
    background: rgba(155, 110, 232, 0.25);
    color: #fff;
}

/* Log Table / List */
.table-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    overflow: hidden;
}

.empty-state {
    padding: 4rem 2rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
}

.empty-icon {
    font-size: 2rem;
    color: #10b981;
}

.empty-text {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.4);
}

.log-entries {
    display: flex;
    flex-direction: column;
    divide-y: 1px solid rgba(255, 255, 255, 0.04);
}

.log-row {
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    transition: background 0.15s ease;
}

.log-row:hover {
    background: rgba(255, 255, 255, 0.02);
}

.log-row--expanded {
    background: rgba(255, 255, 255, 0.025);
}

.log-main {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.log-meta {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-size: 0.78rem;
}

.level-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.15rem 0.5rem;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-radius: 3px;
}

.badge--error {
    background: rgba(239, 68, 68, 0.18);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.35);
}

.badge--warning {
    background: rgba(245, 158, 11, 0.18);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.35);
}

.badge--info {
    background: rgba(59, 130, 246, 0.18);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.35);
}

.badge--debug {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.log-date {
    color: rgba(255, 255, 255, 0.55);
    font-family: monospace;
}

.log-env {
    color: rgba(255, 255, 255, 0.3);
    font-family: monospace;
}

.log-message-wrap {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.log-message {
    font-size: 0.88rem;
    color: #fff;
    line-height: 1.45;
    word-break: break-word;
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
}

.log-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
}

.action-link {
    background: none;
    border: none;
    padding: 0;
    font-size: 0.75rem;
    color: #c084fc;
    cursor: pointer;
    font-family: inherit;
    text-decoration: underline;
    text-underline-offset: 3px;
    opacity: 0.75;
    transition: opacity 0.15s;
}

.action-link:hover {
    opacity: 1;
}

/* Stack Trace */
.log-trace-wrap {
    margin-top: 0.75rem;
    padding: 0.85rem;
    background: #050508;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 4px;
}

.trace-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.74rem;
    color: rgba(255, 255, 255, 0.45);
}

.log-trace {
    margin: 0;
    padding: 0.5rem;
    font-size: 0.76rem;
    line-height: 1.4;
    color: rgba(255, 255, 255, 0.7);
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    white-space: pre-wrap;
    word-break: break-all;
    max-height: 350px;
    overflow-y: auto;
}
</style>
