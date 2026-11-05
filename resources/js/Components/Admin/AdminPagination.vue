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

function formatLabel(label) {
    if (label.includes('&laquo;') || label.includes('Previous')) {
        return '← Назад';
    }
    if (label.includes('&raquo;') || label.includes('Next')) {
        return 'Вперед →';
    }
    return label;
}
</script>

<template>
    <div v-if="shouldShow" class="admin-pagination-wrapper">
        <div v-if="summary" class="pagination-summary">
            {{ summary }}
        </div>
        <nav class="pagination-links" aria-label="Пагинация">
            <!-- Режим курсорной пагинации O(1) -->
            <template v-if="hasCursorPagination">
                <Link
                    v-if="prevUrl"
                    :href="prevUrl"
                    class="page-btn"
                    preserve-scroll
                    preserve-state
                >
                    <span>← Назад</span>
                </Link>
                <span v-else class="page-btn page-btn--disabled">
                    <span>← Назад</span>
                </span>

                <Link
                    v-if="nextUrl"
                    :href="nextUrl"
                    class="page-btn"
                    preserve-scroll
                    preserve-state
                >
                    <span>Вперед →</span>
                </Link>
                <span v-else class="page-btn page-btn--disabled">
                    <span>Вперед →</span>
                </span>
            </template>

            <!-- Традиционный режим номерных ссылок -->
            <template v-else>
                <template v-for="(link, idx) in resolvedLinks" :key="idx">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="page-btn"
                        :class="{ 'page-btn--active': link.active }"
                        preserve-scroll
                        preserve-state
                    >
                        <span v-html="formatLabel(link.label)" />
                    </Link>
                    <span
                        v-else
                        class="page-btn page-btn--disabled"
                        :class="{ 'page-btn--ellipsis': link.label === '...' }"
                    >
                        <span v-html="formatLabel(link.label)" />
                    </span>
                </template>
            </template>
        </nav>
    </div>
</template>

<style scoped>
.admin-pagination-wrapper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    flex-wrap: wrap;
}

.pagination-summary {
    font-size: 0.81rem;
    color: rgba(255, 255, 255, 0.4);
}

.pagination-links {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.1rem;
    height: 2.1rem;
    padding: 0 0.55rem;
    font-size: 0.82rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.7);
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    text-decoration: none;
    transition: all 0.15s ease;
    user-select: none;
}

.page-btn:hover:not(.page-btn--disabled) {
    color: #fff;
    background: rgba(192, 132, 252, 0.15);
    border-color: rgba(192, 132, 252, 0.35);
}

.page-btn--active {
    color: #fff;
    font-weight: 700;
    background: #9b6ee8;
    border-color: #9b6ee8;
    box-shadow: 0 0 10px rgba(155, 110, 232, 0.35);
}

.page-btn--disabled {
    color: rgba(255, 255, 255, 0.2);
    background: transparent;
    border-color: transparent;
    cursor: not-allowed;
}

.page-btn--ellipsis {
    min-width: 1.5rem;
    padding: 0;
}
</style>
