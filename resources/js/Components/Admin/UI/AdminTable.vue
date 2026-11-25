<script setup>
defineProps({
    loading: {
        type: Boolean,
        default: false,
    },
    empty: {
        type: Boolean,
        default: false,
    },
    emptyTitle: {
        type: String,
        default: 'Ничего не найдено',
    },
    emptyDescription: {
        type: String,
        default: 'По заданным параметрам поиска или фильтрам записей нет.',
    },
});
</script>

<template>
    <div class="w-full overflow-hidden rounded-xl border border-admin-subtle bg-admin-card shadow-sm">
        <div class="relative overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-300">
                <thead class="bg-admin-surface/90 backdrop-blur-sm border-b border-admin-theme text-xs font-semibold uppercase tracking-wider text-zinc-400 select-none">
                    <slot name="header" />
                </thead>

                <tbody class="divide-y divide-zinc-800/60 font-normal">
                    <slot />
                </tbody>
            </table>
        </div>

        <!-- Loading Overlay -->
        <div
            v-if="loading"
            class="flex items-center justify-center p-12 bg-zinc-950/40 backdrop-blur-[2px]"
        >
            <div class="flex flex-col items-center gap-3 text-zinc-400">
                <svg class="animate-spin h-6 w-6 text-violet-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span class="text-xs font-medium tracking-wide">Загрузка данных...</span>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-else-if="empty"
            class="flex flex-col items-center justify-center py-16 px-4 text-center"
        >
            <div class="w-12 h-12 rounded-full bg-zinc-800/60 border border-zinc-700/60 flex items-center justify-center text-zinc-400 mb-3">
                <slot name="empty-icon">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                </slot>
            </div>
            <h3 class="text-sm font-semibold text-zinc-200">{{ emptyTitle }}</h3>
            <p class="text-xs text-zinc-400 mt-1 max-w-sm">{{ emptyDescription }}</p>
            <div v-if="$slots['empty-action']" class="mt-4">
                <slot name="empty-action" />
            </div>
        </div>

        <!-- Table Footer / Pagination Slot -->
        <div v-if="$slots.footer" class="border-t border-zinc-800 bg-zinc-900/60 px-4 py-3">
            <slot name="footer" />
        </div>
    </div>
</template>
