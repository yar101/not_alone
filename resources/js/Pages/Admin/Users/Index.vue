<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    users: Object,
    filter: Object,
});

const search = ref(props.filter?.q ?? '');
let searchTimer = null;
let tickTimer = null;
const now = ref(Date.now());

onMounted(() => {
    tickTimer = setInterval(() => { now.value = Date.now(); }, 1000);
});

onUnmounted(() => {
    clearInterval(tickTimer);
    clearTimeout(searchTimer);
});

function onSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('admin.users.index'), { q: search.value || undefined }, { preserveState: true, replace: true });
    }, 300);
}

function formatRemaining(cooldownUntil) {
    const diff = Math.max(0, new Date(cooldownUntil).getTime() - now.value);
    if (diff === 0) return 'истёк';
    const totalSec = Math.floor(diff / 1000);
    const h = Math.floor(totalSec / 3600);
    const m = Math.floor((totalSec % 3600) / 60);
    const s = totalSec % 60;
    if (h > 0) return `${h}ч ${m}м ${s}с`;
    if (m > 0) return `${m}м ${s}с`;
    return `${s}с`;
}

function remainingSeconds(cooldownUntil) {
    return Math.max(0, (new Date(cooldownUntil).getTime() - now.value) / 1000);
}

function adjustCooldown(userId, minutes) {
    router.patch(route('admin.users.cooldown.update', userId), { minutes }, { preserveState: false });
}

function clearCooldown(userId) {
    router.delete(route('admin.users.cooldown.clear', userId), { preserveState: false });
}

function statusLabel(user) {
    if (user.is_idol) return { text: 'Айдол', cls: 'badge--idol' };
    if (user.idol_quiz_passed_at) return { text: 'Тест пройден', cls: 'badge--passed' };
    return { text: 'Кулдаун', cls: 'badge--cooldown' };
}

const ratingInputs = ref({});

function adjustRating(userId, delta) {
    const note = ratingInputs.value[userId] || null;
    router.patch(route('admin.users.rating.update', userId), { delta, note }, { preserveScroll: true });
}

function resetQuiz(userId) {
    router.patch(route('admin.users.reset-quiz', userId), {}, { preserveScroll: false });
}
</script>

<template>
    <div>
        <div class="page-header">
            <h1 class="page-title">Пользователи с кулдауном</h1>
        </div>

        <div class="search-wrap">
            <input
                v-model="search"
                @input="onSearch"
                class="search-input"
                placeholder="Поиск по имени или email..."
            />
        </div>

        <div class="table-wrap">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>Статус</th>
                        <th>Рейтинг</th>
                        <th>Осталось</th>
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
                                    <div class="user-name">{{ user.name }}</div>
                                    <div class="user-email">{{ user.email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span :class="['badge', statusLabel(user).cls]">{{ statusLabel(user).text }}</span>
                        </td>
                        <td>
                            <template v-if="user.is_idol">
                                <div class="rating-cell">
                                    <span class="rating-val">{{ user.idol_rating ?? 50 }}</span>
                                    <div class="rating-actions">
                                        <button @click="adjustRating(user.id, 5)"  class="btn-add" title="+5">+5</button>
                                        <button @click="adjustRating(user.id, -5)" class="btn-sub" title="-5">-5</button>
                                    </div>
                                    <input
                                        v-model="ratingInputs[user.id]"
                                        class="rating-note"
                                        placeholder="Причина..."
                                    />
                                </div>
                            </template>
                            <span v-else class="rating-na">—</span>
                        </td>
                        <td class="timer-cell">
                            {{ formatRemaining(user.idol_quiz_cooldown_until) }}
                        </td>
                        <td>
                            <div class="actions">
                                <button @click="adjustCooldown(user.id, 60)" class="btn-add" title="+1ч">+1ч</button>
                                <button @click="adjustCooldown(user.id, 360)" class="btn-add" title="+6ч">+6ч</button>
                                <button @click="adjustCooldown(user.id, 1440)" class="btn-add" title="+24ч">+24ч</button>
                                <button
                                    v-if="remainingSeconds(user.idol_quiz_cooldown_until) > 60 * 60"
                                    @click="adjustCooldown(user.id, -60)"
                                    class="btn-sub"
                                    title="-1ч"
                                >-1ч</button>
                                <button
                                    v-if="remainingSeconds(user.idol_quiz_cooldown_until) > 60 * 360"
                                    @click="adjustCooldown(user.id, -360)"
                                    class="btn-sub"
                                    title="-6ч"
                                >-6ч</button>
                                <button @click="clearCooldown(user.id)" class="btn-clear">Аннулировать</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!users.data.length">
                        <td colspan="5" class="empty-row">Нет пользователей с активным кулдауном</td>
                    </tr>
                </tbody>
            </table>
        </div>

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
    </div>
</template>

<style scoped>
.page-header { margin-bottom: 1.25rem; }
.page-title { font-size: 1.4rem; color: #fff; margin: 0; }

.search-wrap { margin-bottom: 1.25rem; }
.search-input {
    width: 100%;
    max-width: 380px;
    padding: 0.5rem 0.85rem;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.04);
    color: #fff;
    font-size: 0.875rem;
    outline: none;
    font-family: inherit;
}
.search-input::placeholder { color: rgba(255,255,255,0.25); }
.search-input:focus { border-color: rgba(200,70,126,0.6); }

.table-wrap { overflow-x: auto; }
.users-table { width: 100%; border-collapse: collapse; }
.users-table th {
    text-align: left;
    padding: 0.6rem 1rem;
    font-size: 0.72rem;
    color: rgba(255,255,255,0.35);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    border-bottom: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.025);
}
.users-table td {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    vertical-align: middle;
}
.users-table tr:hover td { background: rgba(255,255,255,0.03); }

.user-cell { display: flex; align-items: center; gap: 0.75rem; }
.user-avatar {
    width: 34px; height: 34px; border-radius: 50%; object-fit: cover;
    background: rgba(200,70,126,0.15); flex-shrink: 0;
}
.user-avatar--initials {
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; font-weight: 600; color: #C8467E;
    border: 1px solid rgba(200,70,126,0.35);
}
.user-name { font-size: 0.9rem; color: rgba(255,255,255,0.88); font-weight: 500; }
.user-email { font-size: 0.78rem; color: rgba(255,255,255,0.35); }

.badge {
    padding: 0.18rem 0.5rem;
    font-size: 0.7rem;
    font-weight: 700;
    white-space: nowrap;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.badge--idol     { background: rgba(200,70,126,0.15); color: #C8467E; border: 1px solid rgba(200,70,126,0.3); }
.badge--passed   { background: rgba(76,222,143,0.1); color: #4cde8f; border: 1px solid rgba(76,222,143,0.25); }
.badge--cooldown { background: rgba(255,180,0,0.1); color: #fbb740; border: 1px solid rgba(255,180,0,0.25); }

.timer-cell {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.6);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.actions { display: flex; gap: 0.3rem; flex-wrap: wrap; align-items: center; }

.btn-add, .btn-sub, .btn-clear {
    padding: 0.22rem 0.55rem;
    font-size: 0.77rem;
    cursor: pointer;
    border: 1px solid;
    white-space: nowrap;
    font-family: inherit;
}
.btn-add  { border-color: rgba(76,222,143,0.4); color: #4cde8f; background: rgba(76,222,143,0.07); }
.btn-add:hover  { background: rgba(76,222,143,0.18); }
.btn-sub  { border-color: rgba(255,180,0,0.4); color: #fbb740; background: rgba(255,180,0,0.07); }
.btn-sub:hover  { background: rgba(255,180,0,0.18); }
.btn-clear { border-color: rgba(255,107,107,0.4); color: #ff6b6b; background: rgba(255,107,107,0.07); }
.btn-clear:hover { background: rgba(255,107,107,0.18); }

.empty-row { text-align: center; color: rgba(255,255,255,0.3); padding: 3rem; }

.rating-cell { display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; }
.rating-val  { font-size: 0.95rem; font-weight: 700; color: rgba(254,40,162,0.85); min-width: 28px; }
.rating-actions { display: flex; gap: 0.25rem; }
.rating-note {
    padding: 0.2rem 0.5rem;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.7);
    font-family: inherit;
    font-size: 0.75rem;
    outline: none;
    width: 110px;
    border-radius: 2px;
}
.rating-note:focus { border-color: rgba(254,40,162,0.4); }
.rating-na { color: rgba(255,255,255,0.2); font-size: 0.85rem; }

.pagination { display: flex; gap: 0.25rem; margin-top: 1.5rem; flex-wrap: wrap; }
.page-link {
    padding: 0.28rem 0.6rem;
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.5);
    font-size: 0.8rem;
    text-decoration: none;
    cursor: pointer;
}
.page-link--active { border-color: rgba(200,70,126,0.6); color: #C8467E; background: rgba(200,70,126,0.1); }
.page-link--disabled { opacity: 0.3; pointer-events: none; }
</style>
