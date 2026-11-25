<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import UserPickerModal from '@/Components/Admin/UserPickerModal.vue';
import { ElMessageBox, ElNotification } from 'element-plus';
import { Warning, Plus, Delete, Search, User as UserIcon, Close } from '@element-plus/icons-vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    users:   { type: Array, default: () => [] },
    strikes: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({ q: '', status: 'all' }) },
    counts:  { type: Object, default: () => ({ all: 0, active: 0, expired: 0 }) },
});

// ── Form State ────────────────────────────────────────────────────────────────
const showCreateForm = ref(false);
const form = useForm({
    user_id: '',
    rating_deducted: 0,
    admin_note: '',
    ban_reason: '',
    banned_until: '',
});

const showPicker = ref(false);
const selectedUser = ref(null);

const activeStrikesCount = computed(() => {
    return selectedUser.value ? (selectedUser.value.active_strikes_count || 0) : 0;
});

const isThirdStrike = computed(() => activeStrikesCount.value >= 2);

const ratingOptions = [
    { value: 0, label: 'Без вычета (0)' },
    { value: -0.1, label: '-0.1 (Легкое предупреждение)' },
    { value: -0.3, label: '-0.3 (Среднее нарушение)' },
    { value: -0.5, label: '-0.5 (Серьезное нарушение)' },
    { value: -1.0, label: '-1.0 (Грубое нарушение)' },
];

function selectUser(user) {
    selectedUser.value = user;
    form.user_id = user.id;

    router.reload({
        data: { q: user.id },
        only: ['users'],
        onSuccess: (page) => {
            const found = page.props.users?.find(u => u.id === user.id);
            if (found) {
                selectedUser.value = found;
            }
        }
    });
}

function submit() {
    form.post(route('admin.strikes.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            selectedUser.value = null;
            showCreateForm.value = false;
        },
    });
}

// ── Search & Filter State ─────────────────────────────────────────────────────
const searchQuery = ref(props.filters.q || '');
const currentStatus = ref(props.filters.status || 'all');
let searchDebounce = null;

function applyFilters() {
    router.get(
        route('admin.strikes.index'),
        {
            q: searchQuery.value || undefined,
            status: currentStatus.value !== 'all' ? currentStatus.value : undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
}

function onSearchInput() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(applyFilters, 350);
}

function setStatus(status) {
    currentStatus.value = status;
    applyFilters();
}

function revokeStrike(strike) {
    ElMessageBox.confirm(
        `Вы уверены, что хотите отозвать страйк #${strike.id} пользователя ${strike.user.name}? Если с айдола снимался рейтинг, он будет возвращен.`,
        'Подтверждение отзыва',
        {
            confirmButtonText: 'Отозвать страйк',
            cancelButtonText: 'Отмена',
            type: 'warning',
            customClass: 'dark-message-box',
        }
    ).then(() => {
        router.delete(route('admin.strikes.destroy', strike.id), {
            preserveScroll: true,
            onSuccess: () => {
                ElNotification.success({
                    title: 'Успешно',
                    message: `Страйк #${strike.id} успешно отозван`,
                });
            },
        });
    }).catch(() => {});
}

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-100 flex items-center gap-2.5">
                    <el-icon class="text-amber-400"><Warning /></el-icon>
                    <span>Управление страйками</span>
                </h1>
                <p class="text-xs text-zinc-400 mt-0.5">
                    Журнал выданных предупреждений, штрафов и санкций. 3 активных страйка = автоматический бан.
                </p>
            </div>
            <div>
                <AdminButton
                    :variant="showCreateForm ? 'outline' : 'primary'"
                    size="sm"
                    @click="showCreateForm = !showCreateForm"
                >
                    <el-icon class="mr-1.5"><Plus /></el-icon>
                    {{ showCreateForm ? 'Скрыть форму' : 'Выдать страйк' }}
                </AdminButton>
            </div>
        </div>

        <!-- Compose Form (Collapsible Card) -->
        <div v-show="showCreateForm" class="p-5 rounded-xl border border-zinc-800 bg-zinc-900/60 backdrop-blur-sm space-y-4">
            <h2 class="text-sm font-semibold text-zinc-100 flex items-center gap-2">
                <el-icon class="text-amber-400"><Warning /></el-icon>
                <span>Выдать новый страйк пользователю</span>
            </h2>

            <form @submit.prevent="submit" class="space-y-4 text-xs">
                <!-- User Selector -->
                <div class="space-y-1.5">
                    <label class="font-medium text-zinc-300">Пользователь <span class="text-rose-400">*</span></label>

                    <div v-if="selectedUser" class="p-3 rounded-lg border border-zinc-800 bg-zinc-950 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-zinc-800 text-zinc-300 flex items-center justify-center font-bold text-xs border border-zinc-700">
                                {{ (selectedUser.name || selectedUser.email || '?')[0].toUpperCase() }}
                            </div>
                            <div>
                                <div class="font-semibold text-zinc-100 flex items-center gap-2">
                                    <span>{{ selectedUser.name || '—' }}</span>
                                    <AdminBadge v-if="selectedUser.is_idol" variant="primary" size="sm">Айдол</AdminBadge>
                                </div>
                                <div class="text-[11px] text-zinc-400 font-mono">{{ selectedUser.email }}</div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <AdminBadge :variant="activeStrikesCount > 0 ? 'danger' : 'neutral'" size="sm">
                                Активных страйков: {{ activeStrikesCount }}
                            </AdminBadge>
                            <AdminButton variant="ghost" size="sm" type="button" @click="showPicker = true">
                                Сменить
                            </AdminButton>
                        </div>
                    </div>

                    <div v-else>
                        <AdminButton variant="outline" size="sm" type="button" @click="showPicker = true">
                            <el-icon class="mr-1.5"><UserIcon /></el-icon>
                            Выбрать пользователя...
                        </AdminButton>
                    </div>
                    <p v-if="form.errors.user_id" class="text-xs text-rose-400">{{ form.errors.user_id }}</p>
                </div>

                <template v-if="selectedUser">
                    <!-- Rating Deduction (if idol) -->
                    <div v-if="selectedUser.is_idol" class="space-y-1.5">
                        <label class="font-medium text-zinc-300">Штраф рейтинга (только для айдолов)</label>
                        <select
                            v-model="form.rating_deducted"
                            class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 focus:outline-none focus:border-violet-500"
                        >
                            <option v-for="opt in ratingOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.rating_deducted" class="text-xs text-rose-400">{{ form.errors.rating_deducted }}</p>
                    </div>

                    <!-- Reason / Note -->
                    <div class="space-y-1.5">
                        <label class="font-medium text-zinc-300">Причина нарушения и заметка <span class="text-rose-400">*</span></label>
                        <textarea
                            v-model="form.admin_note"
                            rows="2"
                            required
                            placeholder="Опишите суть нарушения и контекст для аудита..."
                            class="w-full px-3 py-2 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                        ></textarea>
                        <p v-if="form.errors.admin_note" class="text-xs text-rose-400">{{ form.errors.admin_note }}</p>
                    </div>

                    <!-- 3rd Strike Auto-Ban Warning -->
                    <div v-if="isThirdStrike" class="p-3 rounded-lg border border-rose-900/40 bg-rose-950/20 space-y-3">
                        <div class="flex items-center gap-2 font-semibold text-rose-300">
                            <el-icon><Warning /></el-icon>
                            <span>Внимание: Это 3-й активный страйк! Пользователь будет заблокирован.</span>
                        </div>

                        <div class="space-y-1">
                            <label class="font-medium text-zinc-300">Причина блокировки *</label>
                            <input
                                v-model="form.ban_reason"
                                type="text"
                                required
                                placeholder="Например: Систематическое нарушение правил платформы (3 страйка)"
                                class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                            />
                            <p v-if="form.errors.ban_reason" class="text-xs text-rose-400">{{ form.errors.ban_reason }}</p>
                        </div>

                        <div class="space-y-1">
                            <label class="font-medium text-zinc-300">Срок блокировки (оставьте пустым для бессрочного бана)</label>
                            <input
                                v-model="form.banned_until"
                                type="datetime-local"
                                class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 focus:outline-none focus:border-violet-500"
                            />
                            <p v-if="form.errors.banned_until" class="text-xs text-rose-400">{{ form.errors.banned_until }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-800">
                        <AdminButton variant="outline" size="sm" type="button" @click="showCreateForm = false">
                            Отмена
                        </AdminButton>
                        <AdminButton
                            :variant="isThirdStrike ? 'destructive' : 'primary'"
                            size="sm"
                            type="submit"
                            :loading="form.processing"
                        >
                            {{ isThirdStrike ? 'Выдать страйк и забанить' : 'Выдать страйк' }}
                        </AdminButton>
                    </div>
                </template>
            </form>
        </div>

        <!-- Filter Tabs & Search Bar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div class="flex items-center gap-1.5 overflow-x-auto">
                <button
                    class="px-3 py-1.5 rounded-lg text-xs font-medium border transition flex items-center gap-2"
                    :class="currentStatus === 'all'
                        ? 'border-violet-500/50 bg-violet-500/15 text-violet-300'
                        : 'border-zinc-800 bg-zinc-900/60 text-zinc-400 hover:text-zinc-200'"
                    @click="setStatus('all')"
                >
                    <span>Все страйки</span>
                    <span class="px-1.5 py-0.2 rounded text-[10px] font-mono bg-zinc-800 text-zinc-400">{{ counts.all }}</span>
                </button>
                <button
                    class="px-3 py-1.5 rounded-lg text-xs font-medium border transition flex items-center gap-2"
                    :class="currentStatus === 'active'
                        ? 'border-rose-500/50 bg-rose-500/15 text-rose-300'
                        : 'border-zinc-800 bg-zinc-900/60 text-zinc-400 hover:text-zinc-200'"
                    @click="setStatus('active')"
                >
                    <span>Активные</span>
                    <span class="px-1.5 py-0.2 rounded text-[10px] font-mono bg-rose-950 text-rose-400">{{ counts.active }}</span>
                </button>
                <button
                    class="px-3 py-1.5 rounded-lg text-xs font-medium border transition flex items-center gap-2"
                    :class="currentStatus === 'expired'
                        ? 'border-zinc-700 bg-zinc-800 text-zinc-200'
                        : 'border-zinc-800 bg-zinc-900/60 text-zinc-400 hover:text-zinc-200'"
                    @click="setStatus('expired')"
                >
                    <span>Истекшие</span>
                    <span class="px-1.5 py-0.2 rounded text-[10px] font-mono bg-zinc-800 text-zinc-500">{{ counts.expired }}</span>
                </button>
            </div>

            <div class="relative flex-1 max-w-sm">
                <el-icon class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500"><Search /></el-icon>
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Поиск по имени, email или причине..."
                    class="w-full h-9 pl-9 pr-3 rounded-lg border border-zinc-800 bg-zinc-900/80 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                    @input="onSearchInput"
                />
            </div>
        </div>

        <!-- Strikes Table -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 overflow-hidden backdrop-blur-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40 text-zinc-400">
                            <th class="py-3 px-4 font-medium">Пользователь</th>
                            <th class="py-3 px-4 font-medium">Штраф рейтинга</th>
                            <th class="py-3 px-4 font-medium">Статус</th>
                            <th class="py-3 px-4 font-medium">Истекает</th>
                            <th class="py-3 px-4 font-medium">Примечание / Контекст</th>
                            <th class="py-3 px-4 font-medium">Дата выдачи</th>
                            <th class="py-3 px-4 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                        <tr v-if="!strikes.data?.length">
                            <td colspan="7" class="py-12 text-center text-zinc-500">
                                Страйков не найдено
                            </td>
                        </tr>
                        <tr
                            v-for="strike in strikes.data"
                            :key="strike.id"
                            class="hover:bg-zinc-800/30 transition"
                        >
                            <!-- User -->
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full bg-zinc-800 text-zinc-300 flex items-center justify-center font-bold text-xs border border-zinc-700">
                                        {{ (strike.user?.name || strike.user?.email || '?')[0].toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <Link
                                            :href="route('admin.users.show', strike.user.id)"
                                            class="font-semibold text-zinc-200 hover:text-violet-400 transition truncate block max-w-[140px]"
                                        >
                                            {{ strike.user?.name || '—' }}
                                        </Link>
                                        <span class="text-[11px] text-zinc-500 font-mono truncate block max-w-[140px]">{{ strike.user?.email }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Rating Deducted -->
                            <td class="py-3 px-4 font-mono font-bold">
                                <span v-if="strike.rating_deducted" class="text-rose-400">
                                    {{ strike.rating_deducted }}
                                </span>
                                <span v-else class="text-zinc-500">0</span>
                            </td>

                            <!-- Status -->
                            <td class="py-3 px-4">
                                <AdminBadge :variant="strike.is_active ? 'danger' : 'neutral'" size="sm" dot>
                                    {{ strike.is_active ? 'Активен' : 'Истёк' }}
                                </AdminBadge>
                            </td>

                            <!-- Expires At -->
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-400 whitespace-nowrap">
                                {{ formatDate(strike.expires_at) }}
                            </td>

                            <!-- Admin Note -->
                            <td class="py-3 px-4 max-w-[200px] truncate text-zinc-300" :title="strike.admin_note">
                                {{ strike.admin_note || '—' }}
                            </td>

                            <!-- Created At -->
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-500 whitespace-nowrap">
                                {{ formatDate(strike.created_at) }}
                            </td>

                            <!-- Actions -->
                            <td class="py-3 px-4 text-right">
                                <button
                                    class="text-rose-400 hover:text-rose-300 font-medium text-xs hover:underline"
                                    @click="revokeStrike(strike)"
                                >
                                    Отозвать
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4 border-t border-zinc-800">
                <AdminPagination :pagination="strikes" />
            </div>
        </div>

        <!-- User Picker Modal -->
        <UserPickerModal
            v-if="showPicker"
            :show="showPicker"
            @close="showPicker = false"
            @select="selectUser"
        />
    </div>
</template>
