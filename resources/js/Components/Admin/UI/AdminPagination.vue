<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    pagination: {
        type: Object,
        default: null,
    },
    links: {
        type: Array,
        default: null,
    },
});

const prevUrl = computed(() => props.pagination?.prev_page_url || null);
const nextUrl = computed(() => props.pagination?.next_page_url || null);
const hasCursorPagination = computed(() => !!prevUrl.value || !!nextUrl.value || (props.pagination && !props.pagination.links && !props.links));

const resolvedLinks = computed(() => {
    if (props.links) return props.links;
    if (props.pagination?.links) return props.pagination.links;
    return [];
});

const shouldShow = computed(() => {
    if (hasCursorPagination.value) {
        return !!prevUrl.value || !!nextUrl.value;
    }
    if (props.pagination?.last_page) {
        return props.pagination.last_page > 1;
    }
    return resolvedLinks.value.length > 3;
});

const summary = computed(() => {
    if (!props.pagination) return null;
    const { from, to, total } = props.pagination;
    if (from !== undefined && to !== undefined && total !== undefined) {
        return `Показано ${from}–${to} из ${total}`;
    }
    if (total !== undefined) {
        return `Всего записей: ${total}`;
    }
    if (props.pagination.data && Array.isArray(props.pagination.data)) {
        return `Записей на странице: ${props.pagination.data.length}`;
    }
    return null;
});

function cleanLabel(label) {
    return label
        .replace('&laquo; Previous', '← Назад')
        .replace('Next &raquo;', 'Вперед →')
        .replace('&laquo;', '←')
        .replace('&raquo;', '→');
}
</script>

<template>
    <div v-if="shouldShow" class="flex flex-col sm:flex-row items-center justify-between gap-3 py-3 px-1 text-sm text-zinc-400 select-none">
        <div v-if="summary" class="text-xs font-medium text-zinc-400">
            {{ summary }}
        </div>

        <nav class="flex items-center gap-1" aria-label="Пагинация">
            <!-- Cursor Mode -->
            <template v-if="hasCursorPagination">
                <Link
                    v-if="prevUrl"
                    :href="prevUrl"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-zinc-800/80 hover:bg-zinc-700 text-zinc-200 border border-zinc-700/80 transition-colors"
                    preserve-scroll
                    preserve-state
                >
                    ← Назад
                </Link>
                <span
                    v-else
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-zinc-900 text-zinc-600 border border-zinc-800/60 cursor-not-allowed opacity-50"
                >
                    ← Назад
                </span>

                <Link
                    v-if="nextUrl"
                    :href="nextUrl"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-zinc-800/80 hover:bg-zinc-700 text-zinc-200 border border-zinc-700/80 transition-colors"
                    preserve-scroll
                    preserve-state
                >
                    Вперед →
                </Link>
                <span
                    v-else
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-zinc-900 text-zinc-600 border border-zinc-800/60 cursor-not-allowed opacity-50"
                >
                    Вперед →
                </span>
            </template>

            <!-- Numbered Links Mode -->
            <template v-else>
                <template v-for="(link, idx) in resolvedLinks" :key="idx">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="inline-flex items-center justify-center min-w-[32px] h-8 px-2.5 text-xs font-medium rounded-lg transition-colors border"
                        :class="[
                            link.active
                                ? 'bg-violet-600 border-violet-500 text-white font-semibold shadow-sm'
                                : 'bg-zinc-800/70 hover:bg-zinc-700 border-zinc-700/70 text-zinc-300'
                        ]"
                        preserve-scroll
                        preserve-state
                        v-html="cleanLabel(link.label)"
                    />
                    <span
                        v-else
                        class="inline-flex items-center justify-center min-w-[32px] h-8 px-2 text-xs text-zinc-600 border border-transparent select-none"
                        v-html="cleanLabel(link.label)"
                    />
                </template>
            </template>
        </nav>
    </div>
</template>
