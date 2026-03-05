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
                        <td colspan="4" class="empty-row">Нет пользователей с активным кулдауном</td>
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
    padding: 0.55rem 0.9rem;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04);
    color: #fff;
    font-size: 0.875rem;
    outline: none;
    transition: border-color 0.15s;
}
.search-input::placeholder { color: rgba(255,255,255,0.25); }
.search-input:focus { border-color: rgba(200,70,126,0.5); }

.table-wrap { overflow-x: auto; }
.users-table { width: 100%; border-collapse: collapse; }
.users-table th {
    text-align: left;
    padding: 0.75rem 1rem;
    font-size: 0.75rem;
    color: rgba(255,255,255,0.4);
    text-transform: uppercase;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.users-table td {
    padding: 0.9rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: middle;
}
.users-table tr:hover td { background: rgba(255,255,255,0.02); }

.user-cell { display: flex; align-items: center; gap: 0.75rem; }
.user-avatar {
    width: 36px; height: 36px; border-radius: 50%; object-fit: cover;
    background: rgba(200,70,126,0.15); flex-shrink: 0;
}
.user-avatar--initials {
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem; font-weight: 600; color: #C8467E;
    border: 1px solid rgba(200,70,126,0.3);
}
.user-name { font-size: 0.9rem; color: rgba(255,255,255,0.85); font-weight: 500; }
.user-email { font-size: 0.78rem; color: rgba(255,255,255,0.35); }

.badge {
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
}
.badge--idol    { background: rgba(200,70,126,0.15); color: #C8467E; }
.badge--passed  { background: rgba(76,222,143,0.12); color: #4cde8f; }
.badge--cooldown { background: rgba(255,180,0,0.12); color: #fbb740; }

.timer-cell {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.6);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.actions { display: flex; gap: 0.35rem; flex-wrap: wrap; align-items: center; }

.btn-add, .btn-sub, .btn-clear {
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    font-size: 0.78rem;
    cursor: pointer;
    border: 1px solid;
    transition: all 0.15s;
    white-space: nowrap;
}
.btn-add  { border-color: rgba(76,222,143,0.35); color: #4cde8f; background: rgba(76,222,143,0.07); }
.btn-add:hover  { background: rgba(76,222,143,0.15); }
.btn-sub  { border-color: rgba(255,180,0,0.35); color: #fbb740; background: rgba(255,180,0,0.07); }
.btn-sub:hover  { background: rgba(255,180,0,0.15); }
.btn-clear { border-color: rgba(255,107,107,0.35); color: #ff6b6b; background: rgba(255,107,107,0.07); }
.btn-clear:hover { background: rgba(255,107,107,0.15); }

.empty-row { text-align: center; color: rgba(255,255,255,0.3); padding: 3rem; }

.pagination { display: flex; gap: 0.35rem; margin-top: 1.5rem; flex-wrap: wrap; }
.page-link {
    padding: 0.3rem 0.65rem;
    border-radius: 6px;
    border: 1px solid rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.5);
    font-size: 0.82rem;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s;
}
.page-link--active { border-color: rgba(200,70,126,0.5); color: #C8467E; background: rgba(200,70,126,0.08); }
.page-link--disabled { opacity: 0.3; pointer-events: none; }
</style>
