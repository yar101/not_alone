<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import UserPickerModal from '@/Components/Admin/UserPickerModal.vue';
import AppSelect from '@/Components/AppSelect.vue';
import { ElMessageBox, ElNotification } from 'element-plus';
import { Warning, Plus, Delete, Search, User as UserIcon } from '@element-plus/icons-vue';

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
</script>

<template>
    <div class="strikes-page">
        <AdminPageHeader
            title="Управление страйками"
            description="Журнал выданных предупреждений, штрафов и санкций за нарушения"
        >
            <template #actions>
                <button
                    class="btn-toggle-form"
                    :class="{ 'btn-toggle-form--active': showCreateForm }"
                    @click="showCreateForm = !showCreateForm"
                >
                    <el-icon><Plus /></el-icon>
                    {{ showCreateForm ? 'Скрыть форму' : 'Выдать страйк' }}
                </button>
            </template>
        </AdminPageHeader>

        <!-- Compose Form (Collapsible) -->
        <div v-show="showCreateForm" class="compose-card">
            <h2 class="compose-title">Выдать новый страйк</h2>

            <form @submit.prevent="submit" class="compose-form">
                <div class="field">
                    <label class="field-label">Пользователь</label>
                    <div v-if="selectedUser" class="user-chip">
                        <span class="user-chip-avatar">{{ (selectedUser.name || selectedUser.email || '?')[0].toUpperCase() }}</span>
                        <span class="user-chip-info">
                            <span class="user-chip-name">
                                {{ selectedUser.name || '—' }} 
                                <span v-if="selectedUser.is_idol" class="badge-idol">Айдол</span>
                            </span>
                            <span class="user-chip-email">{{ selectedUser.email }}</span>
                        </span>
                        <div class="strikes-badge" :class="{'strikes-danger': activeStrikesCount > 0}">
                            Активных страйков: {{ activeStrikesCount }}
                        </div>
                        <button type="button" class="btn-change" @click="showPicker = true">Изменить</button>
                    </div>
                    <button v-else type="button" class="btn-pick" @click="showPicker = true">Выбрать пользователя</button>
                    <p v-if="form.errors.user_id" class="field-error">{{ form.errors.user_id }}</p>
                </div>

                <template v-if="selectedUser">
                    <div class="field" v-if="selectedUser.is_idol">
                        <label class="field-label">Списание рейтинга (только для айдолов)</label>
                        <AppSelect
                            v-model="form.rating_deducted"
                            :options="ratingOptions"
                        />
                        <p v-if="form.errors.rating_deducted" class="field-error">{{ form.errors.rating_deducted }}</p>
                    </div>

                    <div class="field">
                        <label class="field-label">Причина и примечание (видно в аудите)</label>
                        <textarea
                            v-model="form.admin_note"
                            class="field-textarea"
                            rows="3"
                            placeholder="Опишите суть нарушения и контекст..."
                            required
                        ></textarea>
                        <p v-if="form.errors.admin_note" class="field-error">{{ form.errors.admin_note }}</p>
                    </div>

                    <div v-if="isThirdStrike" class="ban-warning-block">
                        <div class="ban-warning-title">
                            <el-icon><Warning /></el-icon>
                            Внимание: Это 3-й активный страйк! Пользователь будет заблокирован.
                        </div>
                        <div class="field">
                            <label class="field-label">Причина блокировки</label>
                            <input
                                v-model="form.ban_reason"
                                class="field-input"
                                placeholder="Например: Систематическое нарушение правил платформы (3 страйка)"
                                required
                            />
                            <p v-if="form.errors.ban_reason" class="field-error">{{ form.errors.ban_reason }}</p>
                        </div>
                        <div class="field">
                            <label class="field-label">Срок блокировки (оставьте пустым для бессрочного бана)</label>
                            <input
                                v-model="form.banned_until"
                                type="datetime-local"
                                class="field-input"
                            />
                            <p v-if="form.errors.banned_until" class="field-error">{{ form.errors.banned_until }}</p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-cancel" @click="showCreateForm = false">Отмена</button>
                        <button type="submit" class="btn-submit" :disabled="form.processing">
                            {{ form.processing ? 'Сохранение...' : (isThirdStrike ? 'Выдать страйк и забанить' : 'Выдать страйк') }}
                        </button>
                    </div>
                </template>
            </form>
        </div>

        <!-- Filter Tabs & Search Bar -->
        <div class="table-controls">
            <div class="filter-tabs">
                <button
                    class="tab-btn"
                    :class="{ 'tab-btn--active': currentStatus === 'all' }"
                    @click="setStatus('all')"
                >
                    Все страйки
                    <span class="tab-badge">{{ counts.all }}</span>
                </button>
                <button
                    class="tab-btn"
                    :class="{ 'tab-btn--active': currentStatus === 'active' }"
                    @click="setStatus('active')"
                >
                    Активные
                    <span class="tab-badge tab-badge--danger">{{ counts.active }}</span>
                </button>
                <button
                    class="tab-btn"
                    :class="{ 'tab-btn--active': currentStatus === 'expired' }"
                    @click="setStatus('expired')"
                >
                    Истекшие
                    <span class="tab-badge">{{ counts.expired }}</span>
                </button>
            </div>

            <div class="search-box">
                <el-icon class="search-icon"><Search /></el-icon>
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Поиск по ID, имени, email или причине..."
                    class="search-input"
                    @input="onSearchInput"
                />
            </div>
        </div>

        <!-- Strikes History Table -->
        <div class="strikes-table-card">
            <div class="table-wrap">
                <table class="strikes-table">
                    <thead>
                        <tr>
                            <th class="w-id">#</th>
                            <th>Пользователь</th>
                            <th>Выдал</th>
                            <th class="text-center">Рейтинг</th>
                            <th>Причина / Комментарий</th>
                            <th>Истекает</th>
                            <th>Дата</th>
                            <th class="text-center">Статус</th>
                            <th class="text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="strike in strikes.data" :key="strike.id" :class="{ 'row-expired': !strike.is_active }">
                            <td class="font-mono text-muted">#{{ strike.id }}</td>
                            <td>
                                <div class="user-cell">
                                    <img v-if="strike.user.avatar_url" :src="strike.user.avatar_url" class="user-avatar" />
                                    <div v-else class="user-avatar-empty">{{ strike.user.name.charAt(0) }}</div>
                                    <div class="user-info">
                                        <Link v-if="strike.user.id" :href="route('admin.users.show', strike.user.id)" class="user-name">
                                            {{ strike.user.name }}
                                        </Link>
                                        <span v-else class="user-name">{{ strike.user.name }}</span>
                                        <div class="user-sub">
                                            <span v-if="strike.user.is_idol" class="badge-idol-mini">Айдол</span>
                                            <span v-if="strike.user.is_banned" class="badge-banned-mini">Бан</span>
                                            <span class="user-email">{{ strike.user.email }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="admin-badge">{{ strike.admin.name }}</span>
                            </td>
                            <td class="text-center font-mono font-bold" :class="strike.rating_deducted < 0 ? 'text-danger' : 'text-muted'">
                                {{ strike.rating_deducted !== 0 ? strike.rating_deducted : '—' }}
                            </td>
                            <td>
                                <div class="strike-note">{{ strike.admin_note }}</div>
                            </td>
                            <td class="text-sm">
                                <span v-if="strike.expires_at" :class="strike.is_active ? 'text-warning' : 'text-muted'">
                                    {{ strike.expires_at }}
                                </span>
                                <span v-else class="text-muted">Бессрочно</span>
                            </td>
                            <td class="text-sm text-muted">
                                {{ strike.created_at }}
                            </td>
                            <td class="text-center">
                                <span v-if="strike.is_active" class="status-pill status-pill--active">
                                    Активен
                                </span>
                                <span v-else class="status-pill status-pill--expired">
                                    Истек
                                </span>
                            </td>
                            <td class="text-right">
                                <button
                                    v-if="strike.is_active"
                                    class="btn-revoke"
                                    title="Отозвать страйк"
                                    @click="revokeStrike(strike)"
                                >
                                    Отозвать
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!strikes.data || strikes.data.length === 0">
                            <td colspan="9" class="empty-state">
                                Страйков по выбранным критериям не найдено.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AdminPagination
                v-if="strikes.links && strikes.links.length > 3"
                :links="strikes.links"
                style="margin-top: 1.25rem;"
            />
        </div>

        <UserPickerModal
            v-model="showPicker"
            title="Выбор нарушителя"
            @select="selectUser"
        />
    </div>
</template>

<style scoped>
.strikes-page {
    padding: 1.5rem;
}

.btn-toggle-form {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    background: linear-gradient(135deg, #ec4899, #8b5cf6);
    color: #fff;
    border: none;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-toggle-form:hover {
    opacity: 0.9;
}
.btn-toggle-form--active {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
}

.compose-card {
    background: #18181b;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.compose-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #f4f4f5;
    margin: 0 0 1.25rem 0;
}
.compose-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.field-label {
    font-size: 0.82rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.7);
}
.field-input, .field-textarea {
    background: #09090b;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    padding: 0.6rem 0.85rem;
    color: #fff;
    font-size: 0.9rem;
    font-family: inherit;
    box-sizing: border-box;
    width: 100%;
}
.field-input:focus, .field-textarea:focus {
    outline: none;
    border-color: #ec4899;
}
.field-error {
    color: #f87171;
    font-size: 0.8rem;
    margin: 0.2rem 0 0 0;
}

.btn-pick {
    background: rgba(255, 255, 255, 0.05);
    border: 1px dashed rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 0.75rem;
    color: rgba(255, 255, 255, 0.7);
    cursor: pointer;
    text-align: center;
    font-size: 0.88rem;
    transition: all 0.2s;
}
.btn-pick:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: #ec4899;
    color: #fff;
}

.user-chip {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 0.6rem 0.85rem;
}
.user-chip-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #8b5cf6;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}
.user-chip-info {
    display: flex;
    flex-direction: column;
}
.user-chip-name {
    font-weight: 500;
    color: #fff;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.user-chip-email {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
}
.strikes-badge {
    margin-left: auto;
    font-size: 0.78rem;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.7);
}
.strikes-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.btn-change {
    background: none;
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: rgba(255, 255, 255, 0.7);
    border-radius: 6px;
    padding: 0.25rem 0.6rem;
    font-size: 0.78rem;
    cursor: pointer;
}

.ban-warning-block {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 8px;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}
.ban-warning-title {
    font-weight: 600;
    color: #f87171;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 0.5rem;
}
.btn-cancel {
    background: rgba(255, 255, 255, 0.05);
    border: none;
    color: rgba(255, 255, 255, 0.7);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
}
.btn-submit {
    background: #ec4899;
    color: #fff;
    border: none;
    padding: 0.5rem 1.25rem;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
}
.btn-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Table Controls */
.table-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}
.filter-tabs {
    display: flex;
    gap: 0.5rem;
}
.tab-btn {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.6);
    padding: 0.45rem 0.85rem;
    border-radius: 8px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
}
.tab-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #fff;
}
.tab-btn--active {
    background: rgba(236, 72, 153, 0.15);
    border-color: rgba(236, 72, 153, 0.4);
    color: #ec4899;
    font-weight: 500;
}
.tab-badge {
    background: rgba(255, 255, 255, 0.1);
    padding: 0.1rem 0.4rem;
    border-radius: 6px;
    font-size: 0.75rem;
}
.tab-badge--danger {
    background: rgba(239, 68, 68, 0.25);
    color: #f87171;
}

.search-box {
    position: relative;
    min-width: 280px;
}
.search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.4);
}
.search-input {
    width: 100%;
    background: #18181b;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 0.45rem 0.85rem 0.45rem 2.2rem;
    color: #fff;
    font-size: 0.85rem;
    box-sizing: border-box;
}
.search-input:focus {
    outline: none;
    border-color: #8b5cf6;
}

/* Strikes Table Card */
.strikes-table-card {
    background: #18181b;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 1.25rem;
}
.table-wrap {
    overflow-x: auto;
}
.strikes-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
}
.strikes-table th {
    text-align: left;
    padding: 0.65rem 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    font-weight: 500;
}
.strikes-table td {
    padding: 0.75rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    color: #e4e4e7;
}
.row-expired {
    opacity: 0.65;
}

.user-cell {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}
.user-avatar, .user-avatar-empty {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}
.user-avatar-empty {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.85rem;
}
.user-name {
    font-weight: 500;
    color: #f4f4f5;
    text-decoration: none;
}
.user-name:hover {
    color: #38bdf8;
}
.user-sub {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.1rem;
}
.user-email {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
}
.badge-idol-mini {
    background: rgba(236, 72, 153, 0.2);
    color: #f472b6;
    font-size: 0.68rem;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}
.badge-banned-mini {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    font-size: 0.68rem;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}
.admin-badge {
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.78rem;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
}
.strike-note {
    max-width: 320px;
    line-height: 1.4;
    font-size: 0.85rem;
}
.status-pill {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}
.status-pill--active {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.status-pill--expired {
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.5);
}

.btn-revoke {
    background: transparent;
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #f87171;
    padding: 0.25rem 0.65rem;
    border-radius: 6px;
    font-size: 0.78rem;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-revoke:hover {
    background: rgba(239, 68, 68, 0.15);
    border-color: #ef4444;
}

.empty-state {
    text-align: center;
    padding: 2.5rem 1rem;
    color: rgba(255, 255, 255, 0.4);
    font-style: italic;
}

.w-id { width: 50px; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.font-mono { font-family: ui-monospace, monospace; }
.font-bold { font-weight: 600; }
.text-muted { color: rgba(255, 255, 255, 0.5); }
.text-danger { color: #f87171; }
.text-warning { color: #fbbf24; }
.text-sm { font-size: 0.8rem; }
</style>
