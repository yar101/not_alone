<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { ElMessageBox } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import IdolBadge from '@/Components/IdolBadge.vue';
import {
    Search, User, Warning, Check, Close, Download, Clock
} from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    users:       Object,
    total_count: { type: Number, default: 0 },
    filter:      Object,
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
    ElMessageBox.confirm(
        'Разблокировать пользователя и восстановить доступ к сайту?',
        'Разблокировка',
        {
            confirmButtonText: 'Разблокировать',
            cancelButtonText: 'Отмена',
            type: 'info',
        }
    ).then(() => {
        router.delete(route('admin.users.unban', userId), { preserveScroll: false });
    }).catch(() => {});
}

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit' });
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-100 flex items-center gap-2.5">
                    <el-icon class="text-violet-400"><User /></el-icon>
                    <span>Пользователи платформы</span>
                </h1>
                <p class="text-xs text-zinc-400 mt-0.5">
                    Всего зарегистрировано: <span class="font-mono text-zinc-200">{{ (users?.total ?? total_count ?? 0).toLocaleString() }}</span> аккаунтов
                </p>
            </div>
            <div>
                <a
                    :href="route('admin.export.users', undefined, false)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-xs font-medium text-zinc-300 hover:text-zinc-100 hover:bg-zinc-800 transition"
                    download="users.csv"
                >
                    <el-icon><Download /></el-icon>
                    Экспорт CSV
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
            <div class="relative flex-1">
                <el-icon class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500"><Search /></el-icon>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Поиск по имени или email..."
                    class="w-full h-9 pl-9 pr-8 rounded-lg border border-zinc-800 bg-zinc-900/80 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                    @input="applyFilters(false)"
                />
                <button
                    v-if="search"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-300 text-xs"
                    @click="search = ''; applyFilters(true);"
                >
                    ✕
                </button>
            </div>

            <select
                v-model="filterIdol"
                class="h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-900/80 text-xs text-zinc-200 focus:outline-none focus:border-violet-500"
                @change="applyFilters(true)"
            >
                <option value="">Роль: все</option>
                <option value="1">Айдолы</option>
                <option value="0">Клиенты</option>
            </select>

            <select
                v-model="filterBanned"
                class="h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-900/80 text-xs text-zinc-200 focus:outline-none focus:border-violet-500"
                @change="applyFilters(true)"
            >
                <option value="">Статус: все</option>
                <option value="0">Активные</option>
                <option value="1">Заблокированные</option>
            </select>

            <select
                v-model="filterGender"
                class="h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-900/80 text-xs text-zinc-200 focus:outline-none focus:border-violet-500"
                @change="applyFilters(true)"
            >
                <option value="">Пол: все</option>
                <option value="male">Мужской</option>
                <option value="female">Женский</option>
            </select>

            <AdminButton variant="ghost" size="sm" @click="resetFilters">
                Сбросить
            </AdminButton>
        </div>

        <!-- Users Table -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 overflow-hidden backdrop-blur-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40 text-zinc-400">
                            <th class="py-3 px-4 font-medium">Пользователь</th>
                            <th class="py-3 px-4 font-medium">Роль / Статус</th>
                            <th class="py-3 px-4 font-medium">Рейтинг (0–100)</th>
                            <th class="py-3 px-4 font-medium">Кулдаун квиза</th>
                            <th class="py-3 px-4 font-medium">Регистрация</th>
                            <th class="py-3 px-4 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                        <tr v-if="!users.data.length">
                            <td colspan="6" class="py-12 text-center text-zinc-500">
                                Пользователей не найдено
                            </td>
                        </tr>
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="hover:bg-zinc-800/30 transition"
                        >
                            <!-- User cell -->
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="user.avatar_url"
                                        :src="user.avatar_url"
                                        class="w-8 h-8 rounded-full object-cover border border-zinc-700"
                                    />
                                    <div
                                        v-else
                                        class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs"
                                        :class="user.is_idol ? 'bg-pink-900/40 text-pink-300 border border-pink-500/40' : 'bg-zinc-800 text-zinc-300 border border-zinc-700'"
                                    >
                                        {{ user.name?.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <Link
                                            :href="route('admin.users.show', user.id)"
                                            class="font-semibold text-zinc-100 hover:text-violet-400 transition truncate block max-w-[160px]"
                                        >
                                            {{ user.name }}
                                        </Link>
                                        <span class="text-[11px] text-zinc-500 font-mono truncate block max-w-[160px]">{{ user.email }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Badges -->
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <IdolBadge v-if="user.is_idol" />
                                    <AdminBadge v-else variant="neutral" size="sm">Клиент</AdminBadge>
                                    <AdminBadge v-if="user.is_banned" variant="danger" size="sm">Забанен</AdminBadge>
                                </div>
                            </td>

                            <!-- Rating bar -->
                            <td class="py-3 px-4">
                                <template v-if="user.is_idol">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-xs" :class="(user.rating ?? 0) >= 60 ? 'text-emerald-400' : ((user.rating ?? 0) >= 30 ? 'text-amber-400' : 'text-rose-400')">
                                            {{ user.rating ?? '—' }}
                                        </span>
                                        <div class="w-16 h-1.5 rounded-full bg-zinc-800 overflow-hidden">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :style="{
                                                    width: `${Math.min(100, Math.max(0, user.rating ?? 0))}%`,
                                                    backgroundColor: (user.rating ?? 0) >= 60 ? '#10b981' : ((user.rating ?? 0) >= 30 ? '#f59e0b' : '#ef4444'),
                                                }"
                                            ></div>
                                        </div>
                                    </div>
                                </template>
                                <span v-else class="text-zinc-600">—</span>
                            </td>

                            <!-- Cooldown -->
                            <td class="py-3 px-4">
                                <span
                                    v-if="isCooldownActive(user.idol_quiz_cooldown_until)"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-mono bg-amber-950/40 text-amber-300 border border-amber-800/40"
                                >
                                    <el-icon :size="12"><Clock /></el-icon>
                                    {{ formatRemaining(user.idol_quiz_cooldown_until) }}
                                </span>
                                <span v-else class="text-zinc-600">—</span>
                            </td>

                            <!-- Created At -->
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-500 whitespace-nowrap">
                                {{ formatDate(user.created_at) }}
                            </td>

                            <!-- Actions -->
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="route('admin.users.show', user.id)"
                                        class="px-2.5 py-1 rounded-lg border border-zinc-800 bg-zinc-900 text-[11px] font-medium text-zinc-300 hover:text-white hover:bg-zinc-800 transition"
                                    >
                                        Профиль →
                                    </Link>
                                    <button
                                        v-if="!user.is_banned"
                                        type="button"
                                        class="px-2.5 py-1 rounded-lg border border-rose-900/40 bg-rose-950/20 text-[11px] font-medium text-rose-300 hover:bg-rose-900/40 transition"
                                        @click="openBanModal(user)"
                                    >
                                        Забанить
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="px-2.5 py-1 rounded-lg border border-emerald-900/40 bg-emerald-950/20 text-[11px] font-medium text-emerald-300 hover:bg-emerald-900/40 transition"
                                        @click="unban(user.id)"
                                    >
                                        Разбанить
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4 border-t border-zinc-800">
                <AdminPagination :pagination="users" />
            </div>
        </div>

        <!-- Ban Modal -->
        <AdminModal
            :show="showBanModal"
            :title="`Блокировка: ${banTarget?.name || ''}`"
            @close="closeBanModal"
        >
            <form @submit.prevent="submitBan" class="space-y-4 text-xs">
                <div class="space-y-1.5">
                    <label class="block font-medium text-zinc-300">Причина блокировки <span class="text-rose-400">*</span></label>
                    <textarea
                        v-model="banForm.reason"
                        rows="2"
                        class="w-full px-3 py-2 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                        placeholder="Укажите причину для пользователя и аудита..."
                        required
                    ></textarea>
                    <p v-if="banErrors.reason" class="text-xs text-rose-400">{{ banErrors.reason }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="block font-medium text-zinc-300">Длительность блокировки</label>
                    <div class="grid grid-cols-4 sm:grid-cols-6 gap-1.5">
                        <button
                            v-for="p in banPresets"
                            :key="p.label"
                            type="button"
                            class="px-2 py-1.5 rounded-lg border text-[11px] font-medium transition"
                            :class="banForm.duration === p.minutes
                                ? 'border-rose-500/50 bg-rose-500/20 text-rose-300'
                                : 'border-zinc-800 bg-zinc-950 text-zinc-400 hover:text-zinc-200'"
                            @click="banForm.duration = p.minutes"
                        >
                            {{ p.label }}
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-800">
                    <AdminButton variant="outline" size="sm" type="button" @click="closeBanModal">
                        Отмена
                    </AdminButton>
                    <AdminButton variant="destructive" size="sm" type="submit">
                        Заблокировать
                    </AdminButton>
                </div>
            </form>
        </AdminModal>
    </div>
</template>
