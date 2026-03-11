<script setup>
import { ref, watch, nextTick, computed, onBeforeUnmount } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    broadcasts: Array,
});

const form = useForm({
    title: '',
    body: '',
    target: 'all',
    target_user_id: '',
    target_filters: null,
});

const targetOptions = [
    { value: 'all',      label: 'Все пользователи' },
    { value: 'user',     label: 'Конкретный пользователь' },
    { value: 'filtered', label: 'По фильтру' },
];

function submit() {
    form.post(route('admin.messages.store'), {
        onSuccess: () => {
            form.reset();
            selectedUser.value = null;
            selectedFilteredCount.value = null;
        },
    });
}

// ─── Detail modal ──────────────────────────────────────────────────────────
const detailBroadcast = ref(null);

function formatDate(str) {
    return new Date(str).toLocaleString('ru', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatDateShort(str) {
    return new Date(str).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: '2-digit' });
}

// ─── Picker state ──────────────────────────────────────────────────────────
const showPicker = ref(false);
const selectedUser = ref(null);
const selectedFilteredCount = ref(null);

const pickerUsers = ref([]);
const pickerLoading = ref(false);
const pickerPage = ref(1);
const pickerHasMore = ref(true);
const pickerTotal = ref(0);

const filters = ref({
    q: '',
    is_idol: null,
    gender: null,
    age_from: '',
    age_to: '',
    registered_from: '',
    registered_to: '',
});

const activeFiltersCount = computed(() => {
    const f = filters.value;
    return [f.q, f.is_idol, f.gender, f.age_from, f.age_to, f.registered_from, f.registered_to]
        .filter(v => v !== '' && v !== null).length;
});

let debounceTimer = null;
let observer = null;

async function loadUsers(reset = false) {
    if (pickerLoading.value) return;
    if (!reset && !pickerHasMore.value) return;

    pickerLoading.value = true;
    if (reset) {
        pickerPage.value = 1;
        pickerHasMore.value = true;
    }

    try {
        const params = { ...filters.value, page: pickerPage.value };
        Object.keys(params).forEach(k => {
            if (params[k] === '' || params[k] === null) delete params[k];
        });

        const { data } = await axios.get(route('admin.users.search'), { params });
        if (reset) {
            pickerUsers.value = data.data;
        } else {
            pickerUsers.value.push(...data.data);
        }
        pickerHasMore.value = data.meta.current_page < data.meta.last_page;
        pickerPage.value = data.meta.current_page + 1;
        pickerTotal.value = data.meta.total;
    } finally {
        pickerLoading.value = false;
    }
}

function onFiltersChange() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadUsers(true), 300);
}

watch(filters, onFiltersChange, { deep: true });

function openPicker() {
    showPicker.value = true;
    loadUsers(true);
    nextTick(setupObserver);
}

function closePicker() {
    showPicker.value = false;
    teardownObserver();
}

function selectUser(user) {
    selectedUser.value = user;
    selectedFilteredCount.value = null;
    form.target_user_id = user.id;
    form.target_filters = null;
    closePicker();
}

function selectAllFiltered() {
    const activeFilters = { ...filters.value };
    Object.keys(activeFilters).forEach(k => {
        if (activeFilters[k] === '' || activeFilters[k] === null) delete activeFilters[k];
    });

    selectedFilteredCount.value = pickerTotal.value;
    selectedUser.value = null;
    form.target_user_id = '';
    form.target_filters = Object.keys(activeFilters).length ? activeFilters : {};
    closePicker();
}

function resetFilters() {
    filters.value = {
        q: '',
        is_idol: null,
        gender: null,
        age_from: '',
        age_to: '',
        registered_from: '',
        registered_to: '',
    };
}

function clearSelection() {
    selectedUser.value = null;
    selectedFilteredCount.value = null;
    form.target_user_id = '';
    form.target_filters = null;
}

function setupObserver() {
    const sentinel = document.getElementById('picker-sentinel');
    if (!sentinel) return;
    observer = new IntersectionObserver(entries => {
        if (entries[0].isIntersecting && pickerHasMore.value && !pickerLoading.value) {
            loadUsers(false);
        }
    }, { threshold: 0.1 });
    observer.observe(sentinel);
}

function teardownObserver() {
    if (observer) { observer.disconnect(); observer = null; }
}

function filterLabel(key, value) {
    const labels = {
        is_idol: { '1': 'Айдолы', '0': 'Не айдолы' },
        gender: { male: 'Мужчины', female: 'Женщины' },
    };
    if (labels[key]) return labels[key][value] ?? value;
    if (key === 'age_from') return `от ${value} лет`;
    if (key === 'age_to') return `до ${value} лет`;
    if (key === 'registered_from') return `рег. с ${value}`;
    if (key === 'registered_to') return `рег. до ${value}`;
    if (key === 'q') return `«${value}»`;
    return value;
}

function targetLabel(b) {
    if (b.target === 'all') return 'Все';
    if (b.target === 'user') {
        return b.target_user
            ? (b.target_user.name || b.target_user.email)
            : `#${b.target_user_id}`;
    }
    if (b.target === 'filtered') {
        const f = b.target_filters;
        if (!f || !Object.keys(f).length) return 'По фильтру (все)';
        return 'По фильтру';
    }
    return b.target;
}
</script>

<template>
    <div>
        <h1 class="page-title">Рассылки платформы</h1>

        <!-- Create form -->
        <div class="compose-card">
            <h2 class="compose-title">Новая рассылка</h2>
            <form @submit.prevent="submit" class="compose-form">
                <div class="field">
                    <label class="field-label">Заголовок</label>
                    <input v-model="form.title" class="field-input" placeholder="Заголовок сообщения" required />
                    <p v-if="form.errors.title" class="field-error">{{ form.errors.title }}</p>
                </div>
                <div class="field">
                    <label class="field-label">Сообщение</label>
                    <textarea v-model="form.body" class="field-textarea" rows="5" placeholder="Текст сообщения..." required />
                    <p v-if="form.errors.body" class="field-error">{{ form.errors.body }}</p>
                </div>
                <div class="field">
                    <label class="field-label">Получатели</label>
                    <AppSelect
                        v-model="form.target"
                        :options="targetOptions"
                        @change="clearSelection"
                    />
                </div>

                <!-- Конкретный пользователь -->
                <div class="field" v-if="form.target === 'user'">
                    <label class="field-label">Пользователь</label>
                    <div v-if="selectedUser" class="user-chip">
                        <span class="user-chip-avatar">{{ (selectedUser.name || selectedUser.email || '?')[0].toUpperCase() }}</span>
                        <span class="user-chip-info">
                            <span class="user-chip-name">{{ selectedUser.name || '—' }}</span>
                            <span class="user-chip-email">{{ selectedUser.email }}</span>
                        </span>
                        <button type="button" class="btn-change" @click="openPicker">Изменить</button>
                    </div>
                    <button v-else type="button" class="btn-pick" @click="openPicker">Выбрать пользователя</button>
                    <p v-if="form.errors.target_user_id" class="field-error">{{ form.errors.target_user_id }}</p>
                </div>

                <!-- По фильтру -->
                <div class="field" v-if="form.target === 'filtered'">
                    <label class="field-label">Аудитория</label>
                    <div v-if="selectedFilteredCount !== null" class="filtered-chip">
                        <div class="filtered-chip-main">
                            <span class="filtered-count">{{ selectedFilteredCount.toLocaleString('ru') }} пользователей</span>
                            <div v-if="form.target_filters && Object.keys(form.target_filters).length" class="filter-tags">
                                <span
                                    v-for="(val, key) in form.target_filters"
                                    :key="key"
                                    class="filter-tag"
                                >{{ filterLabel(key, val) }}</span>
                            </div>
                            <span v-else class="filtered-hint">без фильтров — все пользователи</span>
                        </div>
                        <button type="button" class="btn-change" @click="openPicker">Изменить</button>
                    </div>
                    <button v-else type="button" class="btn-pick" @click="openPicker">Настроить фильтры</button>
                    <p v-if="form.errors.target_filters" class="field-error">{{ form.errors.target_filters }}</p>
                </div>

                <button type="submit" class="btn-send" :disabled="form.processing">
                    {{ form.processing ? 'Отправка...' : 'Отправить рассылку' }}
                </button>
            </form>
        </div>

        <!-- Sent list -->
        <div class="sent-section">
            <h2 class="sent-title">Отправленные рассылки</h2>
            <div v-if="broadcasts.length === 0" class="sent-empty">Рассылок пока нет</div>
            <div v-else class="broadcasts-table-wrap">
                <table class="broadcasts-table">
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th class="col-date">Дата</th>
                            <th class="col-title">Заголовок</th>
                            <th class="col-target">Получатели</th>
                            <th class="col-admin">Отправил</th>
                            <th class="col-action"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in broadcasts" :key="b.id" class="bcast-row">
                            <td class="col-num bcast-id">{{ b.id }}</td>
                            <td class="col-date bcast-date">{{ formatDateShort(b.created_at) }}</td>
                            <td class="col-title bcast-title">{{ b.title }}</td>
                            <td class="col-target">
                                <span class="sent-target" :class="{
                                    'target--all': b.target === 'all',
                                    'target--user': b.target === 'user',
                                    'target--filtered': b.target === 'filtered',
                                }">{{ targetLabel(b) }}</span>
                            </td>
                            <td class="col-admin bcast-admin">{{ b.admin.name }}</td>
                            <td class="col-action">
                                <button class="btn-detail" @click="detailBroadcast = b" title="Детали">→</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Detail Modal -->
        <Teleport to="body">
            <div v-if="detailBroadcast" class="modal-backdrop" @click.self="detailBroadcast = null">
                <div class="modal modal--detail">
                    <div class="modal-header">
                        <h3 class="modal-title">Рассылка #{{ detailBroadcast.id }}</h3>
                        <button class="modal-close" @click="detailBroadcast = null">✕</button>
                    </div>
                    <div class="detail-body">
                        <div class="detail-section">
                            <p class="detail-label">Заголовок</p>
                            <p class="detail-value detail-headline">{{ detailBroadcast.title }}</p>
                        </div>
                        <div class="detail-section">
                            <p class="detail-label">Текст</p>
                            <p class="detail-value detail-text">{{ detailBroadcast.body }}</p>
                        </div>
                        <div class="detail-section">
                            <p class="detail-label">Получатели</p>
                            <div class="detail-value">
                                <template v-if="detailBroadcast.target === 'all'">
                                    <span class="sent-target target--all">Все пользователи</span>
                                </template>
                                <template v-else-if="detailBroadcast.target === 'user'">
                                    <div v-if="detailBroadcast.target_user" class="detail-user">
                                        <span class="detail-user-name">{{ detailBroadcast.target_user.name || '—' }}</span>
                                        <span class="detail-user-email">{{ detailBroadcast.target_user.email }}</span>
                                    </div>
                                    <span v-else class="sent-target target--user">#{{ detailBroadcast.target_user_id }}</span>
                                </template>
                                <template v-else-if="detailBroadcast.target === 'filtered'">
                                    <div v-if="detailBroadcast.target_filters && Object.keys(detailBroadcast.target_filters).length" class="filter-tags">
                                        <span
                                            v-for="(val, key) in detailBroadcast.target_filters"
                                            :key="key"
                                            class="filter-tag"
                                        >{{ filterLabel(key, val) }}</span>
                                    </div>
                                    <span v-else class="detail-muted">без фильтров — все пользователи</span>
                                </template>
                            </div>
                        </div>
                        <div class="detail-section detail-meta-row">
                            <div>
                                <p class="detail-label">Отправил</p>
                                <p class="detail-value">{{ detailBroadcast.admin.name }}</p>
                            </div>
                            <div>
                                <p class="detail-label">Дата отправки</p>
                                <p class="detail-value">{{ formatDate(detailBroadcast.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- User Picker Modal -->
        <Teleport to="body">
            <div v-if="showPicker" class="modal-backdrop" @click.self="closePicker">
                <div class="modal">
                    <div class="modal-header">
                        <h3 class="modal-title">Выбор пользователей</h3>
                        <button class="modal-close" @click="closePicker">✕</button>
                    </div>

                    <!-- Filters -->
                    <div class="filters">
                        <input v-model="filters.q" class="filter-search" placeholder="Поиск по имени или email..." />
                        <div class="filter-row">
                            <div class="filter-group">
                                <span class="filter-group-label">Айдол</span>
                                <div class="btn-group">
                                    <button type="button" :class="['btn-toggle', filters.is_idol === null ? 'active' : '']" @click="filters.is_idol = null">Все</button>
                                    <button type="button" :class="['btn-toggle', filters.is_idol === '1' ? 'active' : '']" @click="filters.is_idol = '1'">Айдол</button>
                                    <button type="button" :class="['btn-toggle', filters.is_idol === '0' ? 'active' : '']" @click="filters.is_idol = '0'">Не айдол</button>
                                </div>
                            </div>
                            <div class="filter-group">
                                <span class="filter-group-label">Пол</span>
                                <div class="btn-group">
                                    <button type="button" :class="['btn-toggle', filters.gender === null ? 'active' : '']" @click="filters.gender = null">Все</button>
                                    <button type="button" :class="['btn-toggle', filters.gender === 'male' ? 'active' : '']" @click="filters.gender = 'male'">М</button>
                                    <button type="button" :class="['btn-toggle', filters.gender === 'female' ? 'active' : '']" @click="filters.gender = 'female'">Ж</button>
                                </div>
                            </div>
                        </div>
                        <div class="filter-row">
                            <div class="filter-group">
                                <span class="filter-group-label">Возраст</span>
                                <div class="filter-range">
                                    <input v-model="filters.age_from" type="number" class="filter-num" placeholder="от" min="0" max="120" />
                                    <span class="filter-dash">—</span>
                                    <input v-model="filters.age_to" type="number" class="filter-num" placeholder="до" min="0" max="120" />
                                </div>
                            </div>
                            <div class="filter-group">
                                <span class="filter-group-label">Регистрация</span>
                                <div class="filter-range">
                                    <input v-model="filters.registered_from" type="date" class="filter-date" />
                                    <span class="filter-dash">—</span>
                                    <input v-model="filters.registered_to" type="date" class="filter-date" />
                                </div>
                            </div>
                            <button type="button" class="btn-reset" @click="resetFilters">Сбросить</button>
                        </div>
                    </div>

                    <!-- Select all banner (only for filtered mode) -->
                    <div v-if="form.target === 'filtered' && !pickerLoading && pickerTotal > 0" class="select-all-bar">
                        <span class="select-all-count">
                            Найдено: <strong>{{ pickerTotal.toLocaleString('ru') }}</strong> пользователей
                            <span v-if="activeFiltersCount"> по {{ activeFiltersCount }} фильтрам</span>
                        </span>
                        <button type="button" class="btn-select-all" @click="selectAllFiltered">
                            Выбрать всех ({{ pickerTotal.toLocaleString('ru') }})
                        </button>
                    </div>

                    <!-- User list -->
                    <div class="picker-list">
                        <div
                            v-for="user in pickerUsers"
                            :key="user.id"
                            class="picker-row"
                            :class="{ 'picker-row--clickable': form.target === 'user' }"
                            @click="form.target === 'user' && selectUser(user)"
                        >
                            <div class="picker-avatar">{{ (user.name || user.email || '?')[0].toUpperCase() }}</div>
                            <div class="picker-info">
                                <span class="picker-name">{{ user.name || '—' }}</span>
                                <span class="picker-email">{{ user.email }}</span>
                            </div>
                            <div class="picker-badges">
                                <span v-if="user.is_idol" class="badge badge--idol">Айдол</span>
                                <span v-if="user.gender === 'male'" class="badge badge--male">М</span>
                                <span v-if="user.gender === 'female'" class="badge badge--female">Ж</span>
                                <span v-if="user.age" class="badge badge--age">{{ user.age }} лет</span>
                            </div>
                        </div>

                        <div id="picker-sentinel" style="height:1px;"></div>
                        <div v-if="pickerLoading" class="picker-loader">Загрузка...</div>
                        <div v-if="!pickerLoading && pickerUsers.length === 0" class="picker-empty">Пользователи не найдены</div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.page-title { font-size: 1.4rem; color: #fff; margin: 0 0 1.5rem; }

.compose-card {
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(200,70,126,0.3);
    padding: 1.5rem;
    margin-bottom: 2rem;
    max-width: 600px;
}
.compose-title { font-size: 1rem; color: rgba(255,255,255,0.8); margin: 0 0 1.25rem; font-weight: 600; }
.compose-form { display: flex; flex-direction: column; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field-label { font-size: 0.78rem; color: rgba(255,255,255,0.4); letter-spacing: 0.04em; text-transform: uppercase; }
.field-input, .field-textarea {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.12);
    color: #fff;
    padding: 0.55rem 0.75rem; font-size: 0.88rem;
    outline: none; width: 100%; box-sizing: border-box;
    font-family: inherit;
}
.field-input:focus, .field-textarea:focus { border-color: rgba(200,70,126,0.6); }
.field-textarea { resize: vertical; }
.field-error { font-size: 0.78rem; color: #ff6b6b; margin: 0; }

.btn-send {
    align-self: flex-start;
    padding: 0.5rem 1.2rem;
    background: rgba(200,70,126,0.15); border: 1px solid rgba(200,70,126,0.45);
    color: #C8467E; font-size: 0.88rem; cursor: pointer; font-family: inherit;
}
.btn-send:hover { background: rgba(200,70,126,0.28); }
.btn-send:disabled { opacity: 0.5; }

/* User chip */
.user-chip {
    display: flex; align-items: center; gap: 0.75rem;
    background: rgba(200,70,126,0.08);
    border: 1px solid rgba(200,70,126,0.3);
    padding: 0.55rem 0.75rem;
}
.user-chip-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: rgba(200,70,126,0.3);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; font-weight: 600; color: #fff; flex-shrink: 0;
}
.user-chip-info { display: flex; flex-direction: column; gap: 0.1rem; flex: 1; min-width: 0; }
.user-chip-name { font-size: 0.88rem; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-chip-email { font-size: 0.75rem; color: rgba(255,255,255,0.4); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Filtered chip */
.filtered-chip {
    display: flex; align-items: flex-start; gap: 0.75rem;
    background: rgba(200,70,126,0.08);
    border: 1px solid rgba(200,70,126,0.3);
    padding: 0.65rem 0.75rem;
}
.filtered-chip-main { display: flex; flex-direction: column; gap: 0.35rem; flex: 1; min-width: 0; }
.filtered-count { font-size: 0.9rem; color: #fff; font-weight: 500; }
.filtered-hint { font-size: 0.78rem; color: rgba(255,255,255,0.35); }
.filter-tags { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.filter-tag {
    font-size: 0.72rem; padding: 0.12rem 0.45rem;
    background: rgba(200,70,126,0.15); color: #C8467E;
    border: 1px solid rgba(200,70,126,0.35);
}

.btn-change {
    padding: 0.28rem 0.65rem;
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.14);
    color: rgba(255,255,255,0.6); font-size: 0.78rem; cursor: pointer; white-space: nowrap; flex-shrink: 0;
    font-family: inherit;
}
.btn-change:hover { background: rgba(255,255,255,0.12); }

.btn-pick {
    align-self: flex-start;
    padding: 0.45rem 0.95rem;
    background: rgba(200,70,126,0.1); border: 1px solid rgba(200,70,126,0.35);
    color: #C8467E; font-size: 0.85rem; cursor: pointer; font-family: inherit;
}
.btn-pick:hover { background: rgba(200,70,126,0.22); }

/* Sent section */
.sent-section { max-width: 900px; }
.sent-title { font-size: 1rem; color: rgba(255,255,255,0.6); margin: 0 0 1rem; }
.sent-empty { color: rgba(255,255,255,0.3); font-size: 0.85rem; }

/* Broadcasts table */
.broadcasts-table-wrap { overflow-x: auto; border: 1px solid rgba(255,255,255,0.1); }
.broadcasts-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.broadcasts-table thead tr { background: rgba(255,255,255,0.03); }
.broadcasts-table th {
    padding: 0.55rem 0.9rem; text-align: left;
    color: rgba(255,255,255,0.35); font-weight: 600; font-size: 0.72rem;
    border-bottom: 1px solid rgba(255,255,255,0.1); white-space: nowrap;
    text-transform: uppercase; letter-spacing: 0.06em;
}
.bcast-row { border-bottom: 1px solid rgba(255,255,255,0.06); }
.bcast-row:last-child { border-bottom: none; }
.bcast-row:hover { background: rgba(200,70,126,0.06); }
.broadcasts-table td { padding: 0.6rem 0.9rem; color: rgba(255,255,255,0.75); vertical-align: middle; }
.col-num { width: 48px; }
.col-date { width: 90px; white-space: nowrap; }
.col-title { max-width: 220px; }
.col-target { width: 140px; }
.col-admin { width: 120px; white-space: nowrap; }
.col-action { width: 48px; text-align: center; }
.bcast-id { color: rgba(255,255,255,0.25); font-size: 0.78rem; }
.bcast-date { color: rgba(255,255,255,0.35); font-size: 0.78rem; }
.bcast-title { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bcast-admin { color: rgba(255,255,255,0.4); font-size: 0.8rem; }

.sent-target { font-size: 0.72rem; padding: 0.12rem 0.5rem; white-space: nowrap; display: inline-block; font-weight: 600; letter-spacing: 0.03em; }
.target--all { background: rgba(76,222,143,0.1); color: #4cde8f; border: 1px solid rgba(76,222,143,0.22); }
.target--user { background: rgba(200,70,126,0.1); color: #C8467E; border: 1px solid rgba(200,70,126,0.22); }
.target--filtered { background: rgba(139,92,246,0.1); color: #a78bfa; border: 1px solid rgba(139,92,246,0.22); }

.btn-detail {
    background: none; border: 1px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.4);
    width: 28px; height: 28px; cursor: pointer;
    font-size: 0.9rem; line-height: 1;
    display: inline-flex; align-items: center; justify-content: center;
}
.btn-detail:hover { background: rgba(200,70,126,0.15); border-color: rgba(200,70,126,0.45); color: #C8467E; }

/* Detail modal */
.modal--detail { max-width: 520px; }
.detail-body { padding: 1.25rem; overflow-y: auto; display: flex; flex-direction: column; gap: 1.25rem; }
.detail-section { display: flex; flex-direction: column; gap: 0.3rem; }
.detail-label { font-size: 0.7rem; color: rgba(255,255,255,0.3); margin: 0; text-transform: uppercase; letter-spacing: 0.06em; }
.detail-value { font-size: 0.9rem; color: rgba(255,255,255,0.85); margin: 0; }
.detail-headline { font-weight: 600; }
.detail-text { white-space: pre-wrap; line-height: 1.6; color: rgba(255,255,255,0.65); font-size: 0.85rem; }
.detail-meta-row { flex-direction: row; gap: 2rem; }
.detail-muted { font-size: 0.82rem; color: rgba(255,255,255,0.3); }
.detail-user { display: flex; flex-direction: column; gap: 0.15rem; }
.detail-user-name { font-size: 0.88rem; color: #fff; }
.detail-user-email { font-size: 0.78rem; color: rgba(255,255,255,0.4); }

/* Modal */
.modal-backdrop {
    position: fixed; inset: 0; z-index: 1000;
    background: rgba(0,0,0,0.75);
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
}
.modal {
    background: #0d0d1b;
    border: 1px solid rgba(200,70,126,0.35);
    width: 100%; max-width: 640px;
    max-height: 85vh;
    display: flex; flex-direction: column;
    overflow: hidden;
}
.modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    flex-shrink: 0;
}
.modal-title { font-size: 0.95rem; color: #fff; margin: 0; font-weight: 600; }
.modal-close {
    background: none; border: none; color: rgba(255,255,255,0.4);
    font-size: 1rem; cursor: pointer; padding: 0.25rem; line-height: 1;
}
.modal-close:hover { color: #fff; }

/* Filters */
.filters {
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex; flex-direction: column; gap: 0.75rem;
    flex-shrink: 0;
}
.filter-search {
    width: 100%; box-sizing: border-box;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.12);
    color: #fff;
    padding: 0.5rem 0.75rem; font-size: 0.85rem; outline: none;
    font-family: inherit;
}
.filter-search:focus { border-color: rgba(200,70,126,0.6); }
.filter-search::placeholder { color: rgba(255,255,255,0.25); }
.filter-row { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.filter-group { display: flex; align-items: center; gap: 0.5rem; }
.filter-group-label { font-size: 0.75rem; color: rgba(255,255,255,0.35); white-space: nowrap; }
.btn-group { display: flex; gap: 0; }
.btn-toggle {
    padding: 0.22rem 0.58rem;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.45); font-size: 0.75rem; cursor: pointer; font-family: inherit;
    margin-left: -1px;
}
.btn-toggle:hover { background: rgba(255,255,255,0.09); color: rgba(255,255,255,0.75); }
.btn-toggle.active { background: rgba(200,70,126,0.2); border-color: rgba(200,70,126,0.45); color: #C8467E; }
.filter-range { display: flex; align-items: center; gap: 0.4rem; }
.filter-num {
    width: 60px; background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.12);
    color: #fff;
    padding: 0.22rem 0.4rem; font-size: 0.8rem; outline: none; text-align: center;
    font-family: inherit;
}
.filter-date {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.7);
    padding: 0.22rem 0.4rem; font-size: 0.8rem; outline: none; color-scheme: dark;
}
.filter-dash { color: rgba(255,255,255,0.25); font-size: 0.8rem; }
.btn-reset {
    margin-left: auto;
    padding: 0.22rem 0.65rem;
    background: none; border: 1px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.35); font-size: 0.75rem; cursor: pointer; white-space: nowrap;
    font-family: inherit;
}
.btn-reset:hover { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.65); }

/* Select all bar */
.select-all-bar {
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    padding: 0.6rem 1.25rem;
    background: rgba(139,92,246,0.06);
    border-bottom: 1px solid rgba(139,92,246,0.18);
    flex-shrink: 0;
}
.select-all-count { font-size: 0.82rem; color: rgba(255,255,255,0.5); }
.select-all-count strong { color: #a78bfa; }
.btn-select-all {
    padding: 0.3rem 0.85rem;
    background: rgba(139,92,246,0.15); border: 1px solid rgba(139,92,246,0.4);
    color: #a78bfa; font-size: 0.8rem; cursor: pointer; white-space: nowrap;
    font-family: inherit;
}
.btn-select-all:hover { background: rgba(139,92,246,0.28); }

/* Picker list */
.picker-list { overflow-y: auto; flex: 1; padding: 0.35rem 0; }
.picker-row {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.6rem 1.25rem;
}
.picker-row--clickable { cursor: pointer; }
.picker-row--clickable:hover { background: rgba(255,255,255,0.04); }
.picker-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: rgba(200,70,126,0.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.82rem; font-weight: 600; color: #C8467E; flex-shrink: 0;
}
.picker-info { display: flex; flex-direction: column; gap: 0.1rem; flex: 1; min-width: 0; }
.picker-name { font-size: 0.87rem; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.picker-email { font-size: 0.75rem; color: rgba(255,255,255,0.35); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.picker-badges { display: flex; gap: 0.3rem; flex-wrap: wrap; }
.badge { font-size: 0.68rem; padding: 0.1rem 0.4rem; font-weight: 600; letter-spacing: 0.03em; }
.badge--idol { background: rgba(200,70,126,0.15); color: #C8467E; border: 1px solid rgba(200,70,126,0.3); }
.badge--male { background: rgba(59,130,246,0.1); color: #60a5fa; border: 1px solid rgba(59,130,246,0.2); }
.badge--female { background: rgba(236,72,153,0.1); color: #f472b6; border: 1px solid rgba(236,72,153,0.2); }
.badge--age { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.4); border: 1px solid rgba(255,255,255,0.1); }
.picker-loader { text-align: center; padding: 1rem; font-size: 0.82rem; color: rgba(255,255,255,0.3); }
.picker-empty { text-align: center; padding: 2rem 1rem; font-size: 0.85rem; color: rgba(255,255,255,0.25); }
</style>
