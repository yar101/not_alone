<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    user: Object,
});

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('ru', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function ratingDeltaColor(delta) {
    if (delta > 0) return 'rgba(74,222,128,0.85)';
    if (delta < 0) return 'rgba(239,68,68,0.85)';
    return 'rgba(255,255,255,0.35)';
}

const appStatusLabel = {
    pending:  { text: 'На рассмотрении', cls: 'status--pending' },
    approved: { text: 'Одобрена', cls: 'status--approved' },
    rejected: { text: 'Отклонена', cls: 'status--rejected' },
};

// Ban inline form
const showBanForm = ref(false);
const banReason = ref('');
const banDuration = ref(null);
const banErrors = ref({});

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
    const bannedUntil = banDuration.value
        ? new Date(Date.now() + banDuration.value * 60 * 1000).toISOString()
        : null;
    router.post(route('admin.users.ban', props.user.id), {
        reason: banReason.value,
        banned_until: bannedUntil,
    }, {
        preserveScroll: false,
        onError: (errors) => { banErrors.value = errors; },
    });
}

function unban() {
    if (!confirm('Разбанить пользователя?')) return;
    router.delete(route('admin.users.unban', props.user.id), { preserveScroll: false });
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="page-top">
            <Link :href="route('admin.users.index')" class="back-link">← Пользователи</Link>
            <div class="user-header">
                <div class="user-avatar-wrap">
                    <img v-if="user.avatar_url" :src="user.avatar_url" class="avatar" alt="" />
                    <div v-else class="avatar avatar--initials">{{ user.name[0] }}</div>
                </div>
                <div class="user-info">
                    <h1 class="user-name">{{ user.name }}</h1>
                    <div class="user-meta">{{ user.email }}</div>
                    <div class="user-meta">
                        <span v-if="user.is_idol" class="badge badge--idol">Айдол</span>
                        <span v-else class="badge badge--user">Пользователь</span>
                        <span v-if="user.is_banned" class="badge badge--banned">Заблокирован</span>
                    </div>
                    <div v-if="user.is_idol" class="rating-display">
                        Рейтинг: <strong>{{ user.rating ?? 50 }}</strong>
                    </div>
                    <a :href="route('profile.show', user.id)" class="btn-profile-link" target="_blank" rel="noopener">
                        Страница пользователя ↗
                    </a>
                </div>
            </div>
        </div>

        <div class="sections">
            <!-- Ban status -->
            <div class="section">
                <h2 class="section-title">Блокировка</h2>

                <div v-if="user.is_banned" class="ban-card">
                    <div class="ban-info">
                        <div class="ban-row">
                            <span class="ban-label">Причина:</span>
                            <span>{{ user.ban_reason ?? '—' }}</span>
                        </div>
                        <div class="ban-row">
                            <span class="ban-label">До:</span>
                            <span>{{ user.banned_until ? formatDate(user.banned_until) : 'навсегда' }}</span>
                        </div>
                        <div class="ban-row" v-if="user.banned_by">
                            <span class="ban-label">Заблокировал:</span>
                            <span>{{ user.banned_by.name }}</span>
                        </div>
                    </div>
                    <button class="btn-clear" @click="unban">Разбанить</button>
                </div>

                <div v-else>
                    <div v-if="!showBanForm">
                        <button class="btn-danger" @click="showBanForm = true">Заблокировать</button>
                    </div>
                    <form v-else @submit.prevent="submitBan" class="ban-form">
                        <div class="field">
                            <label>Причина *</label>
                            <div v-if="$page.props.user_ban_reasons?.length" class="ban-reason-presets">
                                <button
                                    v-for="r in $page.props.user_ban_reasons"
                                    :key="r"
                                    type="button"
                                    class="preset-tag"
                                    :class="{ 'preset-tag--active': banReason === r }"
                                    @click="banReason = r"
                                >{{ r }}</button>
                            </div>
                            <textarea
                                v-model="banReason"
                                class="input input--textarea"
                                rows="2"
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
                                    :class="{ 'preset-btn--active': banDuration === p.minutes }"
                                    @click="banDuration = p.minutes"
                                >{{ p.label }}</button>
                            </div>
                            <p v-if="banErrors.banned_until" class="err">{{ banErrors.banned_until }}</p>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-cancel" @click="showBanForm = false">Отмена</button>
                            <button type="submit" class="btn-danger">Заблокировать</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Application -->
            <div class="section" v-if="user.application">
                <h2 class="section-title">Заявка на айдола</h2>
                <div class="app-row">
                    <span :class="['status-badge', appStatusLabel[user.application.status]?.cls]">
                        {{ appStatusLabel[user.application.status]?.text ?? user.application.status }}
                    </span>
                    <span class="muted">{{ formatDate(user.application.created_at) }}</span>
                </div>
                <div v-if="user.application.rejection_reason" class="rejection-reason">
                    Причина отказа: {{ user.application.rejection_reason }}
                </div>
            </div>

            <!-- Services -->
            <div class="section" v-if="user.services?.length">
                <h2 class="section-title">Услуги</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Категория</th>
                                <th>Ед. времени</th>
                                <th>Цена</th>
                                <th>Активна</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in user.services" :key="s.id">
                                <td>{{ s.name }}</td>
                                <td class="muted">{{ s.category ?? '—' }}</td>
                                <td class="muted">{{ s.time_unit ?? '—' }}</td>
                                <td>{{ s.price?.toLocaleString('ru') }} ₽</td>
                                <td>
                                    <span :class="['badge-sm', s.is_active ? 'badge-sm--on' : 'badge-sm--off']">
                                        {{ s.is_active ? 'Да' : 'Нет' }}
                                    </span>
                                </td>
                                <td>
                                    <span :class="['badge-sm', s.status === 'approved' ? 'badge-sm--on' : s.status === 'rejected' ? 'badge-sm--off' : 'badge-sm--pending']">
                                        {{ s.status === 'approved' ? 'Одобрена' : s.status === 'rejected' ? 'Отклонена' : 'На рассмотрении' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Rating logs -->
            <div class="section" v-if="user.rating_logs?.length">
                <h2 class="section-title">История рейтинга</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Изменение</th>
                                <th>Событие</th>
                                <th>Заметка</th>
                                <th>Дата</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(log, i) in user.rating_logs" :key="i">
                                <td>
                                    <span class="delta" :style="{ color: ratingDeltaColor(log.delta) }">
                                        {{ log.delta > 0 ? '+' : '' }}{{ log.delta }}
                                    </span>
                                </td>
                                <td class="muted">{{ log.event }}</td>
                                <td class="muted">{{ log.note ?? '—' }}</td>
                                <td class="muted">{{ formatDate(log.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.page-top { margin-bottom: 2rem; }
.back-link { font-size: 0.83rem; color: rgba(155,110,232,0.7); text-decoration: none; display: inline-block; margin-bottom: 1.25rem; }
.back-link:hover { color: #9B6EE8; }

.user-header { display: flex; align-items: center; gap: 1.25rem; }
.avatar { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; background: rgba(155,110,232,0.15); flex-shrink: 0; }
.avatar--initials { display: flex; align-items: center; justify-content: center; font-size: 1.6rem; font-weight: 700; color: #9B6EE8; border: 2px solid rgba(155,110,232,0.3); }
.user-info { display: flex; flex-direction: column; gap: 0.35rem; }
.user-name { font-size: 1.5rem; color: #fff; margin: 0; }
.user-meta { font-size: 0.83rem; color: rgba(255,255,255,0.4); display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.rating-display { font-size: 0.88rem; color: rgba(255,255,255,0.6); margin-top: 0.15rem; }
.btn-profile-link { display: inline-block; margin-top: 0.5rem; font-size: 0.8rem; color: rgba(190,145,255,0.7); text-decoration: none; border: 1px solid rgba(190,145,255,0.25); padding: 0.25rem 0.6rem; transition: all 0.15s; }
.btn-profile-link:hover { color: #BE91FF; border-color: rgba(190,145,255,0.55); background: rgba(190,145,255,0.06); }

.badge { padding: 0.15rem 0.5rem; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
.badge--idol   { background: rgba(155,110,232,0.15); color: #9B6EE8; border: 1px solid rgba(155,110,232,0.3); }
.badge--user   { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.4); border: 1px solid rgba(255,255,255,0.12); }
.badge--banned { background: rgba(239,68,68,0.12); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }

.sections { display: flex; flex-direction: column; gap: 1.5rem; }
.section { background: rgba(255,255,255,0.025); border: 1px solid rgba(255,255,255,0.08); padding: 1.25rem; }
.section-title { font-size: 0.9rem; color: rgba(255,255,255,0.6); font-weight: 600; text-transform: uppercase; letter-spacing: 0.07em; margin: 0 0 1rem; }

.ban-card { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.2); padding: 0.85rem 1rem; }
.ban-info { display: flex; flex-direction: column; gap: 0.4rem; }
.ban-row { display: flex; gap: 0.5rem; font-size: 0.85rem; color: rgba(255,255,255,0.7); }
.ban-label { color: rgba(255,255,255,0.35); min-width: 90px; }

.ban-form { display: flex; flex-direction: column; gap: 0.75rem; max-width: 480px; }
.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.07em; color: rgba(255,255,255,0.35); }
.input { padding: 0.45rem 0.7rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.85); font-family: inherit; font-size: 0.88rem; outline: none; transition: border-color 0.15s; box-sizing: border-box; width: 100%; }
.input:focus { border-color: rgba(190,145,255,0.45); }
.input--err { border-color: rgba(239,68,68,0.5); }
.input--textarea { resize: vertical; min-height: 60px; }
.err { font-size: 0.75rem; color: rgba(239,68,68,0.8); margin: 0; }
.form-actions { display: flex; gap: 0.5rem; }

.btn-danger { padding: 0.38rem 0.85rem; border: 1px solid rgba(239,68,68,0.45); background: rgba(239,68,68,0.1); color: rgba(239,68,68,0.9); font-family: inherit; font-size: 0.82rem; cursor: pointer; }
.btn-danger:hover { background: rgba(239,68,68,0.2); }
.btn-cancel { padding: 0.38rem 0.85rem; border: 1px solid rgba(255,255,255,0.12); background: transparent; color: rgba(255,255,255,0.4); font-family: inherit; font-size: 0.82rem; cursor: pointer; }

.ban-reason-presets { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-bottom: 0.4rem; }
.preset-tag { padding: 0.22rem 0.6rem; border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.5); font-family: inherit; font-size: 0.78rem; cursor: pointer; transition: all 0.15s; }
.preset-tag:hover { border-color: rgba(155,110,232,0.4); color: rgba(255,255,255,0.8); }
.preset-tag--active { border-color: rgba(239,68,68,0.5); background: rgba(239,68,68,0.1); color: #f87171; }
.presets { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.preset-btn { padding: 0.3rem 0.65rem; border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.55); font-family: inherit; font-size: 0.8rem; cursor: pointer; transition: all 0.15s; }
.preset-btn:hover { border-color: rgba(155,110,232,0.4); color: rgba(255,255,255,0.8); }
.preset-btn--active { border-color: rgba(239,68,68,0.6); background: rgba(239,68,68,0.12); color: #f87171; }
.btn-clear { padding: 0.38rem 0.85rem; border: 1px solid rgba(76,222,143,0.4); background: rgba(76,222,143,0.07); color: #4cde8f; font-family: inherit; font-size: 0.82rem; cursor: pointer; }
.btn-clear:hover { background: rgba(76,222,143,0.18); }

.app-row { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; }
.status-badge { font-size: 0.68rem; padding: 0.12rem 0.45rem; font-weight: 700; white-space: nowrap; letter-spacing: 0.03em; text-transform: uppercase; }
.status--pending  { background: rgba(251,183,64,0.1); color: #fbb740; border: 1px solid rgba(251,183,64,0.25); }
.status--approved { background: rgba(74,222,128,0.1); color: #4ade80; border: 1px solid rgba(74,222,128,0.25); }
.status--rejected { background: rgba(239,68,68,0.1);  color: #f87171; border: 1px solid rgba(239,68,68,0.25); }
.rejection-reason { font-size: 0.83rem; color: rgba(255,255,255,0.45); border-left: 2px solid rgba(239,68,68,0.3); padding-left: 0.75rem; }

.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; padding: 0.75rem 1.25rem; font-size: 0.82rem; color: rgba(255,255,255,0.3); text-transform: uppercase; letter-spacing: 0.07em; border-bottom: 1px solid rgba(255,255,255,0.08); }
.data-table td { padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.93rem; color: rgba(255,255,255,0.75); }

.badge-sm { padding: 0.12rem 0.4rem; font-size: 0.68rem; font-weight: 600; }
.badge-sm--on      { background: rgba(74,222,128,0.1); color: rgba(74,222,128,0.8); }
.badge-sm--off     { background: rgba(239,68,68,0.1);  color: rgba(239,68,68,0.7); }
.badge-sm--pending { background: rgba(251,183,64,0.1); color: #fbb740; }

.delta { font-weight: 700; font-size: 0.95rem; font-variant-numeric: tabular-nums; }
.muted { color: rgba(255,255,255,0.35); }
</style>
