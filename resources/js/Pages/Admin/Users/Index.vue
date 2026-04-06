<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import IdolBadge from '@/Components/IdolBadge.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    users:  Object,
    filter: Object,
});

// ─── Filters ─────────────────────────────────────────────────────────────────
const search       = ref(props.filter?.q ?? '');
const filterIdol   = ref(props.filter?.is_idol ?? '');
const filterBanned = ref(props.filter?.is_banned ?? '');
const filterGender = ref(props.filter?.gender ?? '');

let searchTimer = null;

function applyFilters(immediate = false) {
    clearTimeout(searchTimer);
    const run = () => {
        router.get(
            route('admin.users.index'),
            {
                q:           search.value || undefined,
                is_idol:     filterIdol.value !== '' ? filterIdol.value : undefined,
                is_banned:   filterBanned.value !== '' ? filterBanned.value : undefined,
                gender:      filterGender.value || undefined,
            },
            { preserveState: true, replace: true }
        );
    };
    if (immediate) run();
    else searchTimer = setTimeout(run, 300);
}

function resetFilters() {
    search.value       = '';
    filterIdol.value   = '';
    filterBanned.value = '';
    filterGender.value = '';
    router.get(route('admin.users.index'), {}, { preserveState: false });
}

// ─── Countdown timer ─────────────────────────────────────────────────────────
const now = ref(Date.now());
let tickTimer = null;

onMounted(() => {
    tickTimer = setInterval(() => { now.value = Date.now(); }, 1000);
});

onUnmounted(() => {
    clearInterval(tickTimer);
    clearTimeout(searchTimer);
});

function formatRemaining(cooldownUntil) {
    const diff = Math.max(0, new Date(cooldownUntil).getTime() - now.value);
    if (diff === 0) return null;
    const totalSec = Math.floor(diff / 1000);
    const h = Math.floor(totalSec / 3600);
    const m = Math.floor((totalSec % 3600) / 60);
    const s = totalSec % 60;
    if (h > 0) return `${h}ч ${m}м ${s}с`;
    if (m > 0) return `${m}м ${s}с`;
    return `${s}с`;
}

function isCooldownActive(cooldownUntil) {
    return cooldownUntil && new Date(cooldownUntil).getTime() > now.value;
}

// ─── Rating ──────────────────────────────────────────────────────────────────
function ratingColor(rating) {
    if (rating === null || rating === undefined) return 'rgba(255,255,255,0.3)';
    if (rating >= 60) return 'rgba(74,222,128,0.85)';
    if (rating >= 30) return 'rgba(251,183,64,0.85)';
    return 'rgba(239,68,68,0.85)';
}

function ratingBarColor(rating) {
    if (rating === null || rating === undefined) return 'rgba(255,255,255,0.1)';
    if (rating >= 60) return 'rgba(74,222,128,0.6)';
    if (rating >= 30) return 'rgba(251,183,64,0.6)';
    return 'rgba(239,68,68,0.6)';
}

// ─── Ban modal ────────────────────────────────────────────────────────────────
const showBanModal = ref(false);
const banTarget    = ref(null);
const banForm      = ref({ reason: '', duration: null });
const banErrors    = ref({});

function openBanModal(user) {
    banTarget.value = user;
    banForm.value   = { reason: '', duration: null };
    banErrors.value = {};
    showBanModal.value = true;
}

function closeBanModal() {
    showBanModal.value = false;
    banTarget.value    = null;
}

const banPresets = [
    { label: '30м',    minutes: 30 },
    { label: '1ч',     minutes: 60 },
    { label: '3ч',     minutes: 180 },
    { label: '6ч',     minutes: 360 },
    { label: '12ч',    minutes: 720 },
    { label: '24ч',    minutes: 1440 },
    { label: '3дн',    minutes: 4320 },
    { label: '7дн',    minutes: 10080 },
    { label: '14дн',   minutes: 20160 },
    { label: '30дн',   minutes: 43200 },
    { label: '90дн',   minutes: 129600 },
    { label: 'Навсегда', minutes: null },
];

function submitBan() {
    banErrors.value = {};
    const bannedUntil = banForm.value.duration
        ? new Date(Date.now() + banForm.value.duration * 60 * 1000).toISOString()
        : null;
    router.post(route('admin.users.ban', banTarget.value.id), {
        reason:       banForm.value.reason,
        banned_until: bannedUntil,
    }, {
        preserveScroll: false,
        onSuccess: closeBanModal,
        onError: (errors) => { banErrors.value = errors; },
    });
}

function unban(userId) {
    if (!confirm('Разбанить пользователя?')) return;
    router.delete(route('admin.users.unban', userId), { preserveScroll: false });
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('ru', { day: '2-digit', month: '2-digit', year: '2-digit' });
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">Пользователи</h1>
            <span class="page-count">{{ users.total }}</span>
            <a :href="route('admin.export.users')" class="export-link">Экспорт CSV →</a>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <input
                v-model="search"
                @input="applyFilters(false)"
                class="filter-search"
                placeholder="Поиск по имени или email..."
            />

            <select v-model="filterIdol" @change="applyFilters(true)" class="filter-select">
                <option value="">Айдол: все</option>
                <option value="1">Айдол: да</option>
                <option value="0">Айдол: нет</option>
            </select>

            <select v-model="filterBanned" @change="applyFilters(true)" class="filter-select">
                <option value="">Статус: все</option>
                <option value="0">Активные</option>
                <option value="1">Забаненные</option>
            </select>

            <select v-model="filterGender" @change="applyFilters(true)" class="filter-select">
                <option value="">Пол: все</option>
                <option value="male">Мужской</option>
                <option value="female">Женский</option>
            </select>

            <button class="btn-reset" @click="resetFilters">Сбросить</button>
        </div>

        <!-- Table -->
        <div class="table-wrap">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>Статус</th>
                        <th>Рейтинг</th>
                        <th>Кулдаун</th>
                        <th>Зарегистрирован</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users.data" :key="user.id">
                        <td>
                            <div class="user-cell">
                                <img v-if="user.avatar_url" :src="user.avatar_url" class="user-avatar" alt="" />
                                <div v-else class="user-avatar user-avatar--initials">{{ user.name[0] }}</div>
                                <div>
                                    <Link :href="route('admin.users.show', user.id)" class="user-name-link">
                                        <div class="user-name">{{ user.name }}</div>
                                    </Link>
                                    <div class="user-email">{{ user.email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="badges">
                                <IdolBadge v-if="user.is_idol" />
                                <span v-if="user.is_banned" class="badge badge--banned">Забанен</span>
                            </div>
                        </td>
                        <td>
                            <template v-if="user.is_idol">
                                <div class="rating-cell">
                                    <span class="rating-num" :style="{ color: ratingColor(user.rating) }">
                                        {{ user.rating ?? '—' }}
                                    </span>
                                    <div class="rating-bar-track">
                                        <div
                                            class="rating-bar-fill"
                                            :style="{
                                                width: (user.rating ?? 0) + '%',
                                                background: ratingBarColor(user.rating),
                                            }"
                                        />
                                    </div>
                                </div>
                            </template>
                            <span v-else class="muted-dash">—</span>
                        </td>
                        <td class="timer-cell">
                            <template v-if="isCooldownActive(user.idol_quiz_cooldown_until)">
                                {{ formatRemaining(user.idol_quiz_cooldown_until) }}
                            </template>
                            <span v-else class="muted-dash">—</span>
                        </td>
                        <td class="muted">{{ formatDate(user.created_at) }}</td>
                        <td>
                            <div class="actions">
                                <Link :href="route('admin.users.show', user.id)" class="btn-profile">Профиль →</Link>
                                <button
                                    v-if="!user.is_banned"
                                    class="btn-ban"
                                    @click="openBanModal(user)"
                                >Забанить</button>
                                <button
                                    v-else
                                    class="btn-unban"
                                    @click="unban(user.id)"
                                >Разбанить</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!users.data.length">
                        <td colspan="6" class="empty-row">Пользователей не найдено</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination" v-if="users.last_page > 1">
            <a
                v-for="link in users.links"
                :key="link.label"
                :href="link.url || '#'"
                class="page-link"
                :class="{ 'page-link--active': link.active, 'page-link--disabled': !link.url }"
                v-html="link.label"
                @click.prevent="link.url && router.get(link.url, {}, { preserveState: true })"
            />
        </div>

        <!-- Ban Modal -->
        <Teleport to="body">
            <div v-if="showBanModal" class="overlay" @click.self="closeBanModal">
                <div class="modal">
                    <div class="modal__header">
                        <span>Заблокировать: {{ banTarget?.name }}</span>
                        <button class="modal__close" @click="closeBanModal">✕</button>
                    </div>
                    <form @submit.prevent="submitBan" class="modal__body">
                        <div class="field">
                            <label>Причина *</label>
                            <div v-if="$page.props.user_ban_reasons?.length" class="ban-reason-presets">
                                <button
                                    v-for="r in $page.props.user_ban_reasons"
                                    :key="r"
                                    type="button"
                                    class="preset-tag"
                                    :class="{ 'preset-tag--active': banForm.reason === r }"
                                    @click="banForm.reason = r"
                                >{{ r }}</button>
                            </div>
                            <textarea
                                v-model="banForm.reason"
                                class="input input--textarea"
                                rows="3"
                                maxlength="500"
                                required
                                :class="{ 'input--err': banErrors.reason }"
                            />
                            <p v-if="banErrors.reason" class="err">{{ banErrors.reason }}</p>
                        </div>
                        <div class="field">
                            <label>Длительность</label>
                            <div class="presets">
                                <button
                                    v-for="p in banPresets"
                                    :key="p.label"
                                    type="button"
                                    class="preset-btn"
                                    :class="{ 'preset-btn--active': banForm.duration === p.minutes }"
                                    @click="banForm.duration = p.minutes"
                                >{{ p.label }}</button>
                            </div>
                            <p v-if="banErrors.banned_until" class="err">{{ banErrors.banned_until }}</p>
                        </div>
                        <div class="modal__actions">
                            <button type="button" class="btn-cancel" @click="closeBanModal">Отмена</button>
                            <button type="submit" class="btn-danger-submit">Заблокировать</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
/* Header */
.page-header { display: flex; align-items: baseline; gap: 0.75rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
.page-title  { font-size: 1.4rem; color: #fff; margin: 0; }
.page-count  { font-size: 0.82rem; color: rgba(255,255,255,0.3); }
.export-link { font-size: 0.78rem; padding: 0.3rem 0.65rem; border: 1px solid rgba(155,110,232,0.3); color: rgba(190,145,255,0.75); text-decoration: none; white-space: nowrap; margin-left: auto; }
.export-link:hover { background: rgba(155,110,232,0.08); }

/* Filters */
.filters-bar {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
    align-items: center;
}
.filter-search {
    flex: 1;
    min-width: 200px;
    max-width: 320px;
    padding: 0.45rem 0.8rem;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.04);
    color: #fff;
    font-size: 0.875rem;
    outline: none;
    font-family: inherit;
}
.filter-search::placeholder { color: rgba(255,255,255,0.25); }
.filter-search:focus { border-color: rgba(155,110,232,0.6); }

.filter-select {
    padding: 0.45rem 0.7rem;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.7);
    font-size: 0.82rem;
    outline: none;
    cursor: pointer;
    font-family: inherit;
}
.filter-select:focus { border-color: rgba(155,110,232,0.6); }
.filter-select option { background: #1a1a2e; color: #fff; }

.btn-reset {
    padding: 0.45rem 0.8rem;
    border: 1px solid rgba(255,255,255,0.12);
    background: transparent;
    color: rgba(255,255,255,0.35);
    font-size: 0.82rem;
    cursor: pointer;
    font-family: inherit;
}
.btn-reset:hover { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.6); }

/* Table */
.table-wrap { overflow-x: auto; }
.users-table { width: 100%; border-collapse: collapse; }
.users-table th {
    text-align: left;
    padding: 0.75rem 1.25rem;
    font-size: 0.82rem;
    color: rgba(255,255,255,0.35);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    border-bottom: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.025);
}
.users-table td {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    vertical-align: middle;
    font-size: 0.93rem;
}
.users-table tr:hover td { background: rgba(255,255,255,0.03); }

.user-cell { display: flex; align-items: center; gap: 0.75rem; }
.user-avatar {
    width: 34px; height: 34px; border-radius: 50%; object-fit: cover;
    background: rgba(155,110,232,0.15); flex-shrink: 0;
}
.user-avatar--initials {
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; font-weight: 600; color: #9B6EE8;
    border: 1px solid rgba(155,110,232,0.35);
}
.user-name-link { text-decoration: none; }
.user-name-link:hover .user-name { color: #9B6EE8; }
.user-name  { font-size: 0.9rem; color: rgba(255,255,255,0.88); font-weight: 500; transition: color 0.15s; }
.user-email { font-size: 0.78rem; color: rgba(255,255,255,0.35); }

.badges { display: flex; gap: 0.3rem; flex-wrap: wrap; }
.badge {
    padding: 0.18rem 0.5rem;
    font-size: 0.7rem;
    font-weight: 700;
    white-space: nowrap;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.badge--banned { background: rgba(239,68,68,0.12);   color: #f87171; border: 1px solid rgba(239,68,68,0.3); }

/* Rating */
.rating-cell { display: flex; flex-direction: column; gap: 0.25rem; min-width: 80px; }
.rating-num  { font-size: 1.1rem; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1; }
.rating-bar-track { height: 3px; background: rgba(255,255,255,0.08); border-radius: 99px; overflow: hidden; }
.rating-bar-fill  { height: 100%; border-radius: 99px; transition: width 0.4s ease; }

/* Cooldown timer */
.timer-cell {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.6);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.muted { color: rgba(255,255,255,0.35); font-size: 0.82rem; }
.muted-dash { color: rgba(255,255,255,0.2); font-size: 0.85rem; }

/* Actions */
.actions { display: flex; gap: 0.35rem; flex-wrap: wrap; align-items: center; }
.btn-profile {
    padding: 0.22rem 0.6rem;
    font-size: 0.77rem;
    border: 1px solid rgba(155,110,232,0.35);
    color: rgba(190,145,255,0.8);
    text-decoration: none;
    white-space: nowrap;
}
.btn-profile:hover { background: rgba(155,110,232,0.1); }
.btn-ban {
    padding: 0.22rem 0.6rem;
    font-size: 0.77rem;
    cursor: pointer;
    border: 1px solid rgba(239,68,68,0.4);
    color: #f87171;
    background: rgba(239,68,68,0.07);
    white-space: nowrap;
    font-family: inherit;
}
.btn-ban:hover  { background: rgba(239,68,68,0.18); }
.btn-unban {
    padding: 0.22rem 0.6rem;
    font-size: 0.77rem;
    cursor: pointer;
    border: 1px solid rgba(76,222,143,0.4);
    color: #4cde8f;
    background: rgba(76,222,143,0.07);
    white-space: nowrap;
    font-family: inherit;
}
.btn-unban:hover { background: rgba(76,222,143,0.18); }

.empty-row { text-align: center; color: rgba(255,255,255,0.3); padding: 3rem; }

/* Pagination */
.pagination { display: flex; gap: 0.25rem; margin-top: 1.5rem; flex-wrap: wrap; }
.page-link {
    padding: 0.28rem 0.6rem;
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.5);
    font-size: 0.8rem;
    text-decoration: none;
    cursor: pointer;
}
.page-link--active   { border-color: rgba(155,110,232,0.6); color: #9B6EE8; background: rgba(155,110,232,0.1); }
.page-link--disabled { opacity: 0.3; pointer-events: none; }

/* Modal */
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #0a0a0f; border: 1px solid rgba(255,255,255,0.12); width: 100%; max-width: 420px; margin: 1rem; max-height: 90vh; overflow-y: auto; }
.modal__header { display: flex; align-items: center; justify-content: space-between; padding: 0.8rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.07); font-size: 0.88rem; color: rgba(255,255,255,0.85); font-weight: 600; }
.modal__close { background: none; border: none; color: rgba(255,255,255,0.4); cursor: pointer; font-size: 1rem; }
.modal__body { padding: 1rem; display: flex; flex-direction: column; gap: 0.8rem; }
.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.07em; color: rgba(255,255,255,0.35); }
.input { padding: 0.45rem 0.7rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.85); font-family: inherit; font-size: 0.88rem; outline: none; transition: border-color 0.15s; box-sizing: border-box; width: 100%; }
.input:focus { border-color: rgba(190,145,255,0.45); }
.input--err { border-color: rgba(239,68,68,0.5); }
.input--textarea { resize: vertical; min-height: 72px; }
.err { font-size: 0.75rem; color: rgba(239,68,68,0.8); margin: 0; }
.modal__actions { display: flex; justify-content: flex-end; gap: 0.5rem; padding-top: 0.25rem; }
.btn-cancel { padding: 0.45rem 0.9rem; border: 1px solid rgba(255,255,255,0.12); background: transparent; color: rgba(255,255,255,0.4); font-family: inherit; font-size: 0.82rem; cursor: pointer; }
.btn-danger-submit { padding: 0.45rem 1rem; border: 1px solid rgba(239,68,68,0.45); background: rgba(239,68,68,0.1); color: rgba(255,255,255,0.9); font-family: inherit; font-size: 0.82rem; cursor: pointer; }

.ban-reason-presets { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-bottom: 0.4rem; }
.preset-tag { padding: 0.22rem 0.6rem; border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.5); font-family: inherit; font-size: 0.78rem; cursor: pointer; transition: all 0.15s; }
.preset-tag:hover { border-color: rgba(155,110,232,0.4); color: rgba(255,255,255,0.8); }
.preset-tag--active { border-color: rgba(239,68,68,0.5); background: rgba(239,68,68,0.1); color: #f87171; }
.presets { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.preset-btn { padding: 0.3rem 0.65rem; border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.55); font-family: inherit; font-size: 0.8rem; cursor: pointer; transition: all 0.15s; }
.preset-btn:hover { border-color: rgba(155,110,232,0.4); color: rgba(255,255,255,0.8); }
.preset-btn--active { border-color: rgba(239,68,68,0.6); background: rgba(239,68,68,0.12); color: #f87171; }
</style>
