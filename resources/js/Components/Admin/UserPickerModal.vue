<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import axios from 'axios';
import IdolBadge from '@/Components/IdolBadge.vue';
import { useTranslations } from '@/composables/useTranslations';

const { transChoice } = useTranslations();

const props = defineProps({
    modelValue:    { type: Boolean, default: false },
    title:         { type: String,  default: 'Выбор пользователя' },
    showSelectAll: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'select', 'select-all']);

// ── State ─────────────────────────────────────────────────
const users      = ref([]);
const loading    = ref(false);
const page       = ref(1);
const hasMore    = ref(true);
const total      = ref(0);

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
        page.value = 1;
        hasMore.value = true;
    }

    try {
        const params = { ...filters.value, page: page.value };
        Object.keys(params).forEach(k => {
            if (params[k] === '' || params[k] === null) delete params[k];
        });

        const { data } = await axios.get(route('admin.users.search'), { params });
        users.value   = reset ? data.data : [...users.value, ...data.data];
        hasMore.value = data.meta.current_page < data.meta.last_page;
        page.value    = data.meta.current_page + 1;
        total.value   = data.meta.total;
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
watch(() => props.modelValue, (val) => {
    if (val) {
        loadUsers(true);
        nextTick(setupObserver);
    } else {
        teardownObserver();
        users.value = [];
        total.value = 0;
        resetFilters();
    }
});

function close() {
    emit('update:modelValue', false);
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
    <Teleport to="body">
        <Transition name="upm-fade">
            <div v-if="modelValue" class="upm-backdrop" @click.self="close">
                <div class="upm-modal">

                    <!-- Header -->
                    <div class="upm-header">
                        <h3 class="upm-title">{{ title }}</h3>
                        <button class="upm-close" @click="close">✕</button>
                    </div>

                    <!-- Filters -->
                    <div class="upm-filters">
                        <input
                            v-model="filters.q"
                            class="upm-search"
                            placeholder="Поиск по имени или email…"
                            autofocus
                        />
                        <div class="upm-filter-row">
                            <div class="upm-filter-group">
                                <span class="upm-filter-label">Айдол</span>
                                <div class="upm-btn-group">
                                    <button type="button" :class="['upm-toggle', filters.is_idol === null   && 'upm-toggle--active']" @click="filters.is_idol = null">Все</button>
                                    <button type="button" :class="['upm-toggle', filters.is_idol === '1'    && 'upm-toggle--active']" @click="filters.is_idol = '1'">Айдол</button>
                                    <button type="button" :class="['upm-toggle', filters.is_idol === '0'    && 'upm-toggle--active']" @click="filters.is_idol = '0'">Не айдол</button>
                                </div>
                            </div>
                            <div class="upm-filter-group">
                                <span class="upm-filter-label">Пол</span>
                                <div class="upm-btn-group">
                                    <button type="button" :class="['upm-toggle', filters.gender === null     && 'upm-toggle--active']" @click="filters.gender = null">Все</button>
                                    <button type="button" :class="['upm-toggle', filters.gender === 'male'   && 'upm-toggle--active']" @click="filters.gender = 'male'">М</button>
                                    <button type="button" :class="['upm-toggle', filters.gender === 'female' && 'upm-toggle--active']" @click="filters.gender = 'female'">Ж</button>
                                </div>
                            </div>
                        </div>
                        <div class="upm-filter-row">
                            <div class="upm-filter-group">
                                <span class="upm-filter-label">Возраст</span>
                                <div class="upm-range">
                                    <input v-model="filters.age_from" type="number" class="upm-num" placeholder="от" min="0" max="120" />
                                    <span class="upm-dash">—</span>
                                    <input v-model="filters.age_to" type="number" class="upm-num" placeholder="до" min="0" max="120" />
                                </div>
                            </div>
                            <div class="upm-filter-group">
                                <span class="upm-filter-label">Регистрация</span>
                                <div class="upm-range">
                                    <input v-model="filters.registered_from" type="date" class="upm-date" />
                                    <span class="upm-dash">—</span>
                                    <input v-model="filters.registered_to" type="date" class="upm-date" />
                                </div>
                            </div>
                            <button type="button" class="upm-reset" @click="resetFilters">Сбросить</button>
                        </div>
                    </div>

                    <!-- Select-all bar (опционально) -->
                    <div v-if="showSelectAll && !loading && total > 0" class="upm-select-all-bar">
                        <span class="upm-select-all-count">
                            Найдено: <strong>{{ total.toLocaleString('ru') }}</strong> пользователей
                            <template v-if="activeFiltersCount"> по {{ activeFiltersCount }} фильтрам</template>
                        </span>
                        <button type="button" class="upm-btn-select-all" @click="selectAll">
                            Выбрать всех ({{ total.toLocaleString('ru') }})
                        </button>
                    </div>

                    <!-- List -->
                    <div class="upm-list">
                        <div
                            v-for="user in users"
                            :key="user.id"
                            class="upm-row"
                            @click="selectUser(user)"
                        >
                            <div class="upm-avatar">
                                <img v-if="user.avatar_url" :src="user.avatar_url" class="upm-avatar__img" alt="" />
                                <span v-else class="upm-avatar__initials">{{ (user.name || user.email || '?')[0].toUpperCase() }}</span>
                            </div>
                            <div class="upm-info">
                                <span class="upm-name">{{ user.name || '—' }}</span>
                                <span class="upm-email">{{ user.email }}</span>
                            </div>
                            <div class="upm-badges">
                                <IdolBadge v-if="user.is_idol" />
                                <span v-if="user.gender === 'male'"   class="upm-badge upm-badge--male"><i class="fa-solid fa-mars"></i></span>
                                <span v-if="user.gender === 'female'" class="upm-badge upm-badge--female"><i class="fa-solid fa-venus"></i></span>
                                <span v-if="user.age" class="upm-badge upm-badge--age">{{ user.age }} {{ transChoice('search.age.years', user.age) }}</span>
                            </div>
                        </div>

                        <div id="upm-sentinel" style="height:1px;"></div>
                        <div v-if="loading" class="upm-loader">Загрузка…</div>
                        <div v-if="!loading && users.length === 0" class="upm-empty">Пользователи не найдены</div>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* Backdrop */
.upm-backdrop {
    position: fixed; inset: 0; z-index: 1100;
    background: rgba(0,0,0,0.75);
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
}
.upm-fade-enter-active, .upm-fade-leave-active { transition: opacity 0.18s; }
.upm-fade-enter-from, .upm-fade-leave-to { opacity: 0; }

/* Modal */
.upm-modal {
    background: #0d0d1b;
    border: 1px solid rgba(155,110,232,0.35);
    width: 100%; max-width: 640px;
    max-height: 85vh;
    display: flex; flex-direction: column;
    overflow: hidden;
}

/* Header */
.upm-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    flex-shrink: 0;
}
.upm-title { font-size: 0.95rem; color: #fff; margin: 0; font-weight: 600; }
.upm-close {
    background: none; border: none; color: rgba(255,255,255,0.4);
    font-size: 1rem; cursor: pointer; padding: 0.25rem; line-height: 1;
}
.upm-close:hover { color: #fff; }

/* Filters */
.upm-filters {
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex; flex-direction: column; gap: 0.75rem;
    flex-shrink: 0;
}
.upm-search {
    width: 100%; box-sizing: border-box;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.12);
    color: #fff;
    padding: 0.5rem 0.75rem; font-size: 0.85rem; outline: none;
    font-family: inherit;
}
.upm-search:focus { border-color: rgba(155,110,232,0.6); }
.upm-search::placeholder { color: rgba(255,255,255,0.25); }

.upm-filter-row { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.upm-filter-group { display: flex; align-items: center; gap: 0.5rem; }
.upm-filter-label { font-size: 0.75rem; color: rgba(255,255,255,0.35); white-space: nowrap; }
.upm-btn-group { display: flex; }
.upm-toggle {
    padding: 0.22rem 0.58rem;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.45); font-size: 0.75rem; cursor: pointer; font-family: inherit;
    margin-left: -1px;
}
.upm-toggle:hover { background: rgba(255,255,255,0.09); color: rgba(255,255,255,0.75); }
.upm-toggle--active { background: rgba(155,110,232,0.2); border-color: rgba(155,110,232,0.45); color: #9B6EE8; }

.upm-range { display: flex; align-items: center; gap: 0.4rem; }
.upm-num {
    width: 60px; background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.12);
    color: #fff; padding: 0.22rem 0.4rem; font-size: 0.8rem;
    outline: none; text-align: center; font-family: inherit;
}
.upm-date {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.7);
    padding: 0.22rem 0.4rem; font-size: 0.8rem; outline: none; color-scheme: dark;
}
.upm-dash { color: rgba(255,255,255,0.25); font-size: 0.8rem; }
.upm-reset {
    margin-left: auto;
    padding: 0.22rem 0.65rem;
    background: none; border: 1px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.35); font-size: 0.75rem; cursor: pointer; white-space: nowrap;
    font-family: inherit;
}
.upm-reset:hover { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.65); }

/* Select-all bar */
.upm-select-all-bar {
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    padding: 0.6rem 1.25rem;
    background: rgba(139,92,246,0.06);
    border-bottom: 1px solid rgba(139,92,246,0.18);
    flex-shrink: 0;
}
.upm-select-all-count { font-size: 0.82rem; color: rgba(255,255,255,0.5); }
.upm-select-all-count strong { color: #a78bfa; }
.upm-btn-select-all {
    padding: 0.3rem 0.85rem;
    background: rgba(139,92,246,0.15); border: 1px solid rgba(139,92,246,0.4);
    color: #a78bfa; font-size: 0.8rem; cursor: pointer; white-space: nowrap;
    font-family: inherit;
}
.upm-btn-select-all:hover { background: rgba(139,92,246,0.28); }

/* List */
.upm-list { overflow-y: auto; flex: 1; padding: 0.35rem 0; }
.upm-row {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.6rem 1.25rem;
    cursor: pointer;
}
.upm-row:hover { background: rgba(255,255,255,0.04); }

/* Avatar */
.upm-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: rgba(155,110,232,0.2);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; overflow: hidden;
}
.upm-avatar__img { width: 100%; height: 100%; object-fit: cover; }
.upm-avatar__initials { font-size: 0.82rem; font-weight: 600; color: #9B6EE8; }

.upm-info { display: flex; flex-direction: column; gap: 0.1rem; flex: 1; min-width: 0; }
.upm-name { font-size: 0.87rem; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.upm-email { font-size: 0.75rem; color: rgba(255,255,255,0.35); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.upm-badges { display: flex; gap: 0.3rem; flex-wrap: wrap; align-items: center; }
.upm-badge { font-size: 0.68rem; padding: 0.1rem 0.4rem; font-weight: 600; letter-spacing: 0.03em; }
.upm-badge--male   { background: rgba(59,130,246,0.1); color: #60a5fa; border: 1px solid rgba(59,130,246,0.2); }
.upm-badge--female { background: rgba(236,72,153,0.1); color: #f472b6; border: 1px solid rgba(236,72,153,0.2); }
.upm-badge--age    { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.4); border: 1px solid rgba(255,255,255,0.1); }

.upm-loader { text-align: center; padding: 1rem; font-size: 0.82rem; color: rgba(255,255,255,0.3); }
.upm-empty  { text-align: center; padding: 2rem 1rem; font-size: 0.85rem; color: rgba(255,255,255,0.25); }
</style>
