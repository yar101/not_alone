<script setup>
import { ref, watch } from 'vue';
import AdminInput from './AdminInput.vue';
import AdminButton from './AdminButton.vue';

const props = defineProps({
    search: {
        type: String,
        default: '',
    },
    searchPlaceholder: {
        type: String,
        default: 'Поиск...',
    },
    hasActiveFilters: {
        type: Boolean,
        default: false,
    },
    debounceTime: {
        type: Number,
        default: 300,
    },
});

const emit = defineEmits(['update:search', 'search', 'reset']);

const localSearch = ref(props.search);
let timer = null;

watch(() => props.search, (val) => {
    localSearch.value = val;
});

function onInput(val) {
    localSearch.value = val;
    emit('update:search', val);
    clearTimeout(timer);
    timer = setTimeout(() => {
        emit('search', val);
    }, props.debounceTime);
}

function clearSearch() {
    localSearch.value = '';
    emit('update:search', '');
    emit('search', '');
}
</script>

<template>
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 mb-4">
        <!-- Left: Search & Filter Popovers -->
        <div class="flex items-center flex-wrap gap-2.5 flex-1 min-w-0">
            <div class="w-full sm:w-72 lg:w-80">
                <AdminInput
                    :model-value="localSearch"
                    :placeholder="searchPlaceholder"
                    size="md"
                    @update:model-value="onInput"
                >
                    <template #prefix>
                        <svg class="w-4 h-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </template>
                    <template #suffix v-if="localSearch">
                        <button
                            type="button"
                            class="text-zinc-500 hover:text-zinc-300 p-0.5 rounded transition-colors"
                            title="Очистить поиск"
                            @click="clearSearch"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </template>
                </AdminInput>
            </div>

            <!-- Custom Filters Slot -->
            <slot name="filters" />

            <!-- Reset Filters Button -->
            <AdminButton
                v-if="hasActiveFilters"
                variant="ghost"
                size="sm"
                class="text-zinc-400 hover:text-rose-400 text-xs"
                @click="emit('reset')"
            >
                <template #icon>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </template>
                Сбросить
            </AdminButton>
        </div>

        <!-- Right: Actions Slot (Bulk, Export, Create) -->
        <div v-if="$slots.actions" class="flex items-center gap-2 shrink-0">
            <slot name="actions" />
        </div>
    </div>
</template>
