<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import axios from 'axios';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import IdolBadge from '@/Components/IdolBadge.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Search, Loading, User } from '@element-plus/icons-vue';

const { transChoice } = useTranslations();

const props = defineProps({
    modelValue:    { type: Boolean, default: false },
    show:          { type: Boolean, default: undefined },
    title:         { type: String,  default: 'Выбор пользователя' },
    showSelectAll: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'close', 'select', 'select-all']);

const isOpen = computed(() => props.show !== undefined ? props.show : props.modelValue);

// ── State ─────────────────────────────────────────────────
const searchInput = ref(null);
const users       = ref([]);
const loading     = ref(false);
const nextCursor  = ref(null);
const hasMore     = ref(true);
const total       = ref(0);

const filters = ref({
    q:               '',
    is_idol:         null,
    gender:          null,
    age_from:        '',
    age_to:          '',
    registered_from: '',
    registered_to:   '',
});

const activeFiltersCount = computed(() =>
    Object.values(filters.value).filter(v => v !== '' && v !== null).length
);

// ── API ───────────────────────────────────────────────────
let debounceTimer = null;
let observer = null;

async function loadUsers(reset = false) {
    if (loading.value) return;
    if (!reset && !hasMore.value) return;

    loading.value = true;
    if (reset) {
        nextCursor.value = null;
        hasMore.value = true;
    }

    try {
        const params = { ...filters.value };
        if (!reset && nextCursor.value) {
            params.cursor = nextCursor.value;
        }
        Object.keys(params).forEach(k => {
            if (params[k] === '' || params[k] === null) delete params[k];
        });

        const { data } = await axios.get(route('admin.users.search', undefined, false), { params });
        users.value      = reset ? data.data : [...users.value, ...data.data];
        nextCursor.value = data.meta?.next_cursor ?? null;
        hasMore.value    = data.meta?.has_more ?? !!data.meta?.next_cursor;
        if (data.meta?.total !== undefined && data.meta?.total !== null) {
            total.value  = data.meta.total;
        }
    } catch (err) {
        console.error('Error loading users:', err);
    } finally {
        loading.value = false;
    }
}

function onFiltersChange() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadUsers(true), 300);
}

watch(filters, onFiltersChange, { deep: true });

function resetFilters() {
    filters.value = { q: '', is_idol: null, gender: null, age_from: '', age_to: '', registered_from: '', registered_to: '' };
}

// ── Observer (infinite scroll) ────────────────────────────
function setupObserver() {
    const sentinel = document.getElementById('upm-sentinel');
    if (!sentinel) return;
    observer = new IntersectionObserver(entries => {
        if (entries[0].isIntersecting && hasMore.value && !loading.value) {
            loadUsers(false);
        }
    }, { threshold: 0.1 });
    observer.observe(sentinel);
}

function teardownObserver() {
    if (observer) { observer.disconnect(); observer = null; }
}

// ── Open / close ──────────────────────────────────────────
watch(isOpen, (val) => {
    if (val) {
        loadUsers(true);
        nextTick(() => {
            setupObserver();
            searchInput.value?.focus();
        });
    } else {
        teardownObserver();
        users.value = [];
        total.value = 0;
        resetFilters();
    }
});

function close() {
    emit('update:modelValue', false);
    emit('close');
}

// ── Select ────────────────────────────────────────────────
function selectUser(user) {
    emit('select', user);
    close();
}

function selectAll() {
    const activeFilters = { ...filters.value };
    Object.keys(activeFilters).forEach(k => {
        if (activeFilters[k] === '' || activeFilters[k] === null) delete activeFilters[k];
    });
    emit('select-all', { filters: activeFilters, total: total.value });
    close();
}
</script>

<template>
    <AdminModal
        :show="isOpen"
        :title="title"
        description="Поиск и фильтрация пользователей платформы"
        maxWidth="max-w-2xl"
        @close="close"
    >
        <div class="space-y-4">
            <!-- Search & Filters Strip -->
            <div class="space-y-3 bg-zinc-950/60 p-3.5 rounded-xl border border-zinc-800">
                <!-- Search bar -->
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-zinc-500">
                        <el-icon :size="14"><Search /></el-icon>
                    </span>
                    <input
                        ref="searchInput"
                        v-model="filters.q"
                        type="text"
                        placeholder="Поиск по имени или email..."
                        class="w-full bg-zinc-900 border border-zinc-800 rounded-lg pl-9 pr-3 py-2 text-xs text-zinc-100 placeholder-zinc-500 outline-none focus:border-violet-500"
                    />
                </div>

                <!-- Filter Controls Row 1: Idol & Gender -->
                <div class="flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-semibold uppercase text-zinc-500">Роль:</span>
                        <div class="flex rounded-lg bg-zinc-900 p-0.5 border border-zinc-800">
                            <button
                                type="button"
                                class="px-2.5 py-1 rounded text-xs transition-colors"
                                :class="filters.is_idol === null ? 'bg-violet-600 text-white font-medium shadow-sm' : 'text-zinc-400 hover:text-zinc-200'"
                                @click="filters.is_idol = null"
                            >
                                Все
                            </button>
                            <button
                                type="button"
                                class="px-2.5 py-1 rounded text-xs transition-colors"
                                :class="filters.is_idol === '1' ? 'bg-violet-600 text-white font-medium shadow-sm' : 'text-zinc-400 hover:text-zinc-200'"
                                @click="filters.is_idol = '1'"
                            >
                                Айдолы
                            </button>
                            <button
                                type="button"
                                class="px-2.5 py-1 rounded text-xs transition-colors"
                                :class="filters.is_idol === '0' ? 'bg-violet-600 text-white font-medium shadow-sm' : 'text-zinc-400 hover:text-zinc-200'"
                                @click="filters.is_idol = '0'"
                            >
                                Клиенты
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-semibold uppercase text-zinc-500">Пол:</span>
                        <div class="flex rounded-lg bg-zinc-900 p-0.5 border border-zinc-800">
                            <button
                                type="button"
                                class="px-2.5 py-1 rounded text-xs transition-colors"
                                :class="filters.gender === null ? 'bg-violet-600 text-white font-medium shadow-sm' : 'text-zinc-400 hover:text-zinc-200'"
                                @click="filters.gender = null"
                            >
                                Все
                            </button>
                            <button
                                type="button"
                                class="px-2.5 py-1 rounded text-xs transition-colors"
                                :class="filters.gender === 'male' ? 'bg-violet-600 text-white font-medium shadow-sm' : 'text-zinc-400 hover:text-zinc-200'"
                                @click="filters.gender = 'male'"
                            >
                                М
                            </button>
                            <button
                                type="button"
                                class="px-2.5 py-1 rounded text-xs transition-colors"
                                :class="filters.gender === 'female' ? 'bg-violet-600 text-white font-medium shadow-sm' : 'text-zinc-400 hover:text-zinc-200'"
                                @click="filters.gender = 'female'"
                            >
                                Ж
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filter Controls Row 2: Age, Registration & Reset -->
                <div class="flex flex-wrap items-center justify-between gap-3 text-xs pt-1 border-t border-zinc-900">
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-semibold uppercase text-zinc-500">Возраст:</span>
                        <div class="flex items-center gap-1">
                            <input
                                v-model="filters.age_from"
                                type="number"
                                placeholder="от"
                                min="0"
                                max="120"
                                class="w-12 bg-zinc-900 border border-zinc-800 rounded px-1.5 py-1 text-center text-xs text-zinc-200 outline-none focus:border-violet-500"
                            />
                            <span class="text-zinc-600">—</span>
                            <input
                                v-model="filters.age_to"
                                type="number"
                                placeholder="до"
                                min="0"
                                max="120"
                                class="w-12 bg-zinc-900 border border-zinc-800 rounded px-1.5 py-1 text-center text-xs text-zinc-200 outline-none focus:border-violet-500"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-semibold uppercase text-zinc-500">Рег.:</span>
                        <div class="flex items-center gap-1">
                            <input
                                v-model="filters.registered_from"
                                type="date"
                                style="color-scheme: dark"
                                class="bg-zinc-900 border border-zinc-800 rounded px-2 py-1 text-xs text-zinc-300 outline-none focus:border-violet-500"
                            />
                            <span class="text-zinc-600">—</span>
                            <input
                                v-model="filters.registered_to"
                                type="date"
                                style="color-scheme: dark"
                                class="bg-zinc-900 border border-zinc-800 rounded px-2 py-1 text-xs text-zinc-300 outline-none focus:border-violet-500"
                            />
                        </div>
                    </div>

                    <button
                        v-if="activeFiltersCount > 0"
                        type="button"
                        class="text-[11px] text-zinc-400 hover:text-zinc-200 underline transition-colors ml-auto"
                        @click="resetFilters"
                    >
                        Сбросить фильтры
                    </button>
                </div>
            </div>

            <!-- Optional: Select All banner -->
            <div v-if="showSelectAll && !loading && total > 0" class="flex items-center justify-between p-3 rounded-lg bg-violet-950/20 border border-violet-500/30 text-xs">
                <span class="text-zinc-300">
                    Найдено: <strong class="text-violet-300">{{ total.toLocaleString('ru') }}</strong> пользователей
                    <template v-if="activeFiltersCount"> (по выбранным критериям)</template>
                </span>
                <AdminButton variant="primary" size="sm" @click="selectAll">
                    Выбрать всех ({{ total.toLocaleString('ru') }})
                </AdminButton>
            </div>

            <!-- Users List -->
            <div class="max-h-80 overflow-y-auto divide-y divide-zinc-800/60 rounded-xl border border-zinc-800 bg-zinc-950/40 p-1">
                <div
                    v-for="user in users"
                    :key="user.id"
                    class="flex items-center justify-between p-2.5 rounded-lg hover:bg-zinc-800/40 cursor-pointer transition-colors group"
                    @click="selectUser(user)"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <!-- Avatar -->
                        <div class="w-9 h-9 rounded-full bg-violet-500/20 border border-violet-500/30 text-violet-300 font-bold flex items-center justify-center flex-shrink-0 overflow-hidden text-xs">
                            <img v-if="user.avatar_url" :src="user.avatar_url" class="w-full h-full object-cover" alt="" />
                            <span v-else>{{ (user.name || user.email || '?')[0].toUpperCase() }}</span>
                        </div>

                        <!-- User Info -->
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-zinc-200 truncate group-hover:text-white transition-colors">
                                {{ user.name || '—' }}
                            </div>
                            <div class="text-[11px] text-zinc-500 font-mono truncate">
                                {{ user.email }}
                            </div>
                        </div>
                    </div>

                    <!-- Badges -->
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        <IdolBadge v-if="user.is_idol" />
                        <AdminBadge v-if="user.gender === 'male'" variant="info" size="sm">М</AdminBadge>
                        <AdminBadge v-if="user.gender === 'female'" variant="danger" size="sm">Ж</AdminBadge>
                        <AdminBadge v-if="user.age" variant="neutral" size="sm">
                            {{ user.age }} {{ transChoice('search.age.years', user.age) }}
                        </AdminBadge>
                        <span class="text-zinc-600 group-hover:text-violet-400 transition-colors ml-2 font-mono text-xs">
                            Выбрать →
                        </span>
                    </div>
                </div>

                <div id="upm-sentinel" class="h-1"></div>

                <!-- Loading State -->
                <div v-if="loading" class="flex items-center justify-center gap-2 py-6 text-xs text-zinc-500">
                    <el-icon class="is-loading"><Loading /></el-icon>
                    Загрузка пользователей...
                </div>

                <!-- Empty State -->
                <div v-if="!loading && users.length === 0" class="flex flex-col items-center justify-center py-10 text-xs text-zinc-500">
                    <el-icon :size="32" class="text-zinc-600 mb-2"><User /></el-icon>
                    <span class="font-medium text-zinc-400">Пользователи не найдены</span>
                    <span class="text-[11px] text-zinc-600 mt-0.5">Попробуйте изменить параметры поиска или сбросить фильтры</span>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex items-center justify-between w-full text-xs text-zinc-500">
                <span>Кликните на пользователя для выбора</span>
                <AdminButton variant="outline" size="sm" @click="close">
                    Отмена
                </AdminButton>
            </div>
        </template>
    </AdminModal>
</template>
